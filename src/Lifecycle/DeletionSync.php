<?php

namespace hpr_distributor\Lifecycle;

defined( 'ABSPATH' ) || exit;

final class DeletionSync {
    public const CRON_HOOK = 'hexaprwire_process_deletes';
    public const RECEIPT_OPTION = 'hpr_distributor_deletion_sync_last_run';
    public const LAST_SUCCESS_OPTION = 'hpr_distributor_deletion_sync_last_success';
    private const SOURCE_URL = 'https://hexaprwire.com/wp-admin/admin-ajax.php?action=purge_release_list';

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        add_action( 'init', [ self::class, 'reconcile_schedule' ], 9 );
        add_action( self::CRON_HOOK, [ self::class, 'run_scheduled' ] );
        self::$registered = true;
    }

    public static function reconcile_schedule(): array {
        $enabled = (bool) get_option( 'enable_hpr_auto_deletes', false );
        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( ! $enabled && false !== $next ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return [ 'enabled' => false, 'scheduled' => false, 'changed' => true, 'next_run' => 0 ];
        }
        if ( $enabled && false === $next ) {
            wp_schedule_event( time() + 120, 'hourly', self::CRON_HOOK );
            $next = wp_next_scheduled( self::CRON_HOOK );
            return [ 'enabled' => true, 'scheduled' => false !== $next, 'changed' => true, 'next_run' => (int) $next ];
        }
        return [ 'enabled' => $enabled, 'scheduled' => false !== $next, 'changed' => false, 'next_run' => false === $next ? 0 : (int) $next ];
    }

    public static function preview(): array {
        $slugs = self::fetch_slugs();
        $found = [];
        $missing = [];
        foreach ( $slugs as $slug ) {
            $posts = get_posts(
                [
                    'name'           => $slug,
                    'post_type'      => 'press-release',
                    'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
                    'posts_per_page' => 1,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                ]
            );
            if ( empty( $posts ) ) {
                $missing[] = $slug;
                continue;
            }
            $post = reset( $posts );
            $found[] = [
                'slug'     => $slug,
                'post_id'  => (int) $post->ID,
                'title'    => (string) $post->post_title,
                'view_url' => (string) get_permalink( $post->ID ),
            ];
        }

        return [
            'success'       => true,
            'source_url'    => self::SOURCE_URL,
            'source_count'  => count( $slugs ),
            'found_count'   => count( $found ),
            'missing_count' => count( $missing ),
            'found'         => $found,
            'missing'       => $missing,
            'signature'     => self::signature( $slugs, $found ),
            'generated_gmt' => current_time( 'mysql', true ),
        ];
    }

    public static function execute( string $expected_signature = '', string $trigger = 'manual' ): array {
        $started = microtime( true );
        $started_gmt = current_time( 'mysql', true );
        $preview = self::preview();
        if ( '' !== $expected_signature && ! hash_equals( (string) $preview['signature'], $expected_signature ) ) {
            throw new \RuntimeException( 'The purge list changed after preview. Preview it again before running deletion synchronization.' );
        }

        $trashed = [];
        $failed = [];
        foreach ( $preview['found'] as $row ) {
            $post_id = (int) $row['post_id'];
            $result = wp_trash_post( $post_id );
            if ( $result ) {
                $trashed[] = $post_id;
            } else {
                $failed[] = $post_id;
            }
        }

        $receipt = $preview + [
            'success'       => [] === $failed,
            'status'        => [] === $failed ? 'success' : 'partial',
            'trigger'       => sanitize_key( $trigger ),
            'trashed_count' => count( $trashed ),
            'trashed_ids'   => $trashed,
            'failed_ids'    => $failed,
            'started_gmt'   => $started_gmt,
            'completed_gmt' => current_time( 'mysql', true ),
            'duration_ms'   => (int) round( ( microtime( true ) - $started ) * 1000 ),
        ];
        update_option( self::RECEIPT_OPTION, $receipt, false );
        if ( $receipt['success'] ) {
            update_option( self::LAST_SUCCESS_OPTION, $receipt, false );
        }
        return $receipt;
    }

    public static function run_scheduled(): void {
        if ( ! get_option( 'enable_hpr_auto_deletes', false ) ) {
            return;
        }
        $started = microtime( true );
        $started_gmt = current_time( 'mysql', true );
        try {
            self::execute( '', 'schedule' );
        } catch ( \Throwable $throwable ) {
            update_option(
                self::RECEIPT_OPTION,
                [
                    'success'       => false,
                    'status'        => 'failed',
                    'trigger'       => 'schedule',
                    'error'         => $throwable->getMessage(),
                    'started_gmt'   => $started_gmt,
                    'completed_gmt' => current_time( 'mysql', true ),
                    'duration_ms'   => (int) round( ( microtime( true ) - $started ) * 1000 ),
                    'trashed_count' => 0,
                    'failed_ids'    => [],
                ],
                false
            );
        }
    }

    private static function fetch_slugs(): array {
        $response = wp_safe_remote_get(
            self::SOURCE_URL,
            [
                'timeout'     => 30,
                'redirection' => 2,
                'headers'     => [ 'Accept' => 'text/plain' ],
            ]
        );
        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Purge-list request failed: ' . $response->get_error_message() );
        }
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            throw new \RuntimeException( 'Purge-list request returned HTTP ' . $status . '.' );
        }

        $body = trim( (string) wp_remote_retrieve_body( $response ) );
        if ( '' === $body ) {
            return [];
        }
        $parts = preg_split( '/[\r\n,]+/', $body );
        $slugs = array_map( 'sanitize_title', is_array( $parts ) ? $parts : [] );
        return array_values( array_unique( array_filter( $slugs ) ) );
    }

    private static function signature( array $slugs, array $found ): string {
        $ids = array_map( static fn( array $row ): int => (int) $row['post_id'], $found );
        return hash( 'sha256', wp_json_encode( [ array_values( $slugs ), $ids ] ) );
    }
}
