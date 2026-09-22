<?php

namespace hpr_distributor\Admin;

use hpr_distributor\Config;
use hpr_distributor\Diagnostics\DistributorDiagnostics;
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Lifecycle\DeletionSync;
use hpr_distributor\Media\ExternalImageSizing;
use hpr_distributor\Migration\SourceSlugRepair;

defined( 'ABSPATH' ) || exit;

final class DashboardActions {
    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        $actions = [
            'hpr_save_import_settings' => 'save_import_settings',
            'hpr_test_feed'            => 'test_feed',
            'hpr_import_operation'     => 'import_operation',
            'hpr_force_pull'           => 'force_pull',
            'hpr_reset_import_cursor'  => 'reset_import_cursor',
            'hpr_repair_source_slugs'  => 'repair_source_slugs',
            'hpr_save_general_settings'=> 'save_general_settings',
            'hpr_test_remote_image'    => 'test_remote_image',
            'hpr_repair_remote_image'  => 'repair_remote_image',
            'hpr_inspect_acf_post'     => 'inspect_acf_post',
            'hpr_run_diagnostics'      => 'run_diagnostics',
            'hpr_preview_purge'        => 'preview_purge',
            'hpr_execute_purge'        => 'execute_purge',
            'hpr_test_import_cron'     => 'test_import_cron',
            'hpr_test_deletion_cron'   => 'test_deletion_cron',
            'hpr_apply_legacy_action'  => 'apply_legacy_action',
        ];
        foreach ( $actions as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ self::class, $method ] );
        }
        self::$registered = true;
    }

    public static function save_import_settings(): void {
        self::guard();
        $before = NativeFeedSettings::get();
        $requested_slug = self::post_text( 'publication_slug' );
        $bound_slug = trim( (string) $before['publication_slug'] );
        $input = [
            'feed_url'          => self::post_text( 'feed_url' ),
            'publication_slug'  => '' !== $bound_slug ? $bound_slug : $requested_slug,
            'enabled'           => self::post_bool( 'enabled' ),
            'schedule_enabled'  => self::post_bool( 'schedule_enabled' ),
            'interval'          => self::post_text( 'interval' ),
            'author_id'         => absint( $_POST['author_id'] ?? 0 ),
            'post_status'       => self::post_text( 'post_status' ),
            'max_items'         => absint( $_POST['max_items'] ?? 0 ),
            'update_existing'   => self::post_bool( 'update_existing' ),
            'cache_bust'        => self::post_bool( 'cache_bust' ),
            'run_history_limit' => absint( $_POST['run_history_limit'] ?? 0 ),
            'item_history_limit'=> absint( $_POST['item_history_limit'] ?? 0 ),
            'allowed_host'      => 'hexaprwire.com',
            'configured_by'     => 'admin',
        ];

        try {
            $saved = NativeFeedSettings::save( $input );
            if ( $before['feed_url'] !== $saved['feed_url'] || $before['publication_slug'] !== $saved['publication_slug'] ) {
                NativeFeedImporter::reset_cursor();
            }
            DistributorActivity::record( 'Import settings saved.', [ 'publication_slug' => $saved['publication_slug'], 'schedule' => $saved['schedule_enabled'] ? $saved['interval'] : 'disabled', 'batch_size' => $saved['max_items'] ], 'success' );
            $hexa_user = get_user_by( 'login', 'hexaprwire' );
            $author_warning = $hexa_user instanceof \WP_User && (int) $saved['author_id'] !== (int) $hexa_user->ID;
            wp_send_json_success(
                [
                    'message'   => $author_warning
                        ? 'Settings saved. The selected author is not the recommended Hexa PR Wire author.'
                        : 'Import settings saved and the schedule was reconciled.',
                    'notice'    => [
                        'tone'    => $author_warning ? 'warning' : 'success',
                        'title'   => $author_warning ? 'Settings saved with a non-default author' : 'Import settings saved',
                        'message' => $author_warning
                            ? 'Imports will use the selected author. Hexa PR Wire remains the recommended default.'
                            : 'The saved configuration is active.',
                    ],
                    'settings'  => $saved,
                    'readiness' => NativeFeedSettings::readiness(),
                ]
            );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 400 );
        }
    }

    public static function test_feed(): void {
        self::guard();
        try {
            $settings = NativeFeedSettings::get();
            $url = NativeFeedImporter::effective_feed_url( (string) $settings['feed_url'], '', true );
            $items = NativeFeedImporter::fetch_items( $url, (string) $settings['allowed_host'] );
            $sample = [];
            foreach ( array_slice( $items, 0, 10 ) as $item ) {
                $sample[] = [
                    'title'       => (string) $item['title'],
                    'source_id'   => (string) $item['source_id'],
                    'source_url'  => (string) $item['source_url'],
                    'image_url'   => (string) $item['featured_image'],
                    'published_gmt'=> (string) $item['published_gmt'],
                ];
            }
            wp_send_json_success( [
                'message'      => sprintf( 'Feed test passed with %d valid items.', count( $items ) ),
                'feed_url'     => $url,
                'item_count'   => count( $items ),
                'sample'       => $sample,
                'tested_gmt'   => current_time( 'mysql', true ),
            ] );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 500 );
        }
    }

    public static function import_operation(): void {
        self::guard();
        $operation = self::post_text( 'operation' );
        if ( ! in_array( $operation, [ 'dry-run', 'run' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Unknown import operation.' ], 400 );
        }
        try {
            $result = NativeFeedImporter::run(
                [
                    'trigger' => 'admin-' . $operation,
                    'dry_run' => 'dry-run' === $operation,
                ]
            );
            DistributorActivity::record( 'Native import operation completed.', [ 'operation' => $operation, 'run_id' => $result['run_id'] ?? '', 'status' => $result['status'] ?? '', 'processed' => $result['items_processed'] ?? 0 ], ! empty( $result['success'] ) ? 'success' : 'warning' );
            wp_send_json_success( $result + [ 'message' => 'dry-run' === $operation ? 'Dry run completed.' : 'Native import completed.' ] );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 500 );
        }
    }

    public static function force_pull(): void {
        self::guard();
        $identifier = trim( self::post_text( 'identifier' ) );
        if ( '' === $identifier ) {
            wp_send_json_error( [ 'message' => 'Enter a source URL, source ID, or source slug.' ], 400 );
        }
        $targets = [ 'source_urls' => [], 'source_ids' => [], 'source_slugs' => [] ];
        if ( preg_match( '#^https://#i', $identifier ) ) {
            $targets['source_urls'][] = $identifier;
        } elseif ( preg_match( '/^(?:post:)?\d+$/', $identifier ) ) {
            $targets['source_ids'][] = str_starts_with( $identifier, 'post:' ) ? $identifier : 'post:' . $identifier;
        } else {
            $targets['source_slugs'][] = $identifier;
        }

        try {
            $result = NativeFeedImporter::run(
                [
                    'trigger'     => 'admin-force-pull',
                    'dry_run'     => self::post_bool( 'dry_run' ),
                    'feed_action' => 'force',
                    'targets'     => $targets,
                ]
            );
            if ( 0 === (int) $result['items_processed'] ) {
                wp_send_json_error( [ 'message' => 'No matching source item was found.', 'result' => $result ], 404 );
            }
            DistributorActivity::record( 'Force Pull completed.', [ 'identifier' => $identifier, 'dry_run' => ! empty( $result['dry_run'] ), 'status' => $result['status'] ?? '', 'processed' => $result['items_processed'] ?? 0 ], ! empty( $result['success'] ) ? 'success' : 'warning' );
            wp_send_json_success( $result + [ 'message' => ! empty( $result['dry_run'] ) ? 'Force Pull dry run completed.' : 'Force Pull completed.' ] );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 500 );
        }
    }

    public static function reset_import_cursor(): void {
        self::guard();
        $cursor = NativeFeedImporter::reset_cursor();
        DistributorActivity::record( 'Import cursor reset.', $cursor, 'warning' );
        wp_send_json_success( [ 'message' => 'Import cursor reset to the first feed item.', 'cursor' => $cursor ] );
    }

    public static function repair_source_slugs(): void {
        self::guard();
        $dry_run = self::post_bool( 'dry_run' );
        $result = SourceSlugRepair::run( $dry_run, 1000 );
        if ( ! $dry_run ) {
            DistributorActivity::record( 'Source slugs repaired.', [ 'changed' => $result['changed'], 'conflicts' => $result['conflicts'] ], 0 === (int) $result['conflicts'] ? 'success' : 'warning' );
        }
        wp_send_json_success( $result + [ 'message' => $dry_run ? 'Source-slug preview completed. No posts changed.' : 'Source-slug repair completed.' ] );
    }

    public static function save_general_settings(): void {
        self::guard();
        $boolean_options = [
            'enable_hpr_auto_deletes',
            'enable_press_release_category_on_new_post',
            'disable_rss_caching',
            'hide_press_release_from_home_loop',
            'hide_press_release_from_author_loop',
            'hide_press_release_from_category_loop',
            'hide_press_release_from_tag_loop',
            'hide_press_release_from_related_single_loop',
            'add_press_release_to_author_page',
            'add_press_release_to_category_archives',
        ];
        foreach ( $boolean_options as $option ) {
            update_option( $option, self::post_bool( $option ), false );
        }

        $schedule = DeletionSync::reconcile_schedule();
        DistributorActivity::record( 'General settings saved.', [ 'deletion_schedule' => $schedule ], 'success' );
        wp_send_json_success( [ 'message' => 'General settings saved.', 'deletion_schedule' => $schedule ] );
    }

    public static function test_remote_image(): void {
        self::guard();
        $result = ExternalImageSizing::inspect_url( self::post_text( 'image_url' ), true );
        if ( empty( $result['success'] ) ) {
            wp_send_json_error( $result, 422 );
        }
        wp_send_json_success( $result );
    }

    public static function repair_remote_image(): void {
        self::guard();
        $post_id = absint( $_POST['post_id'] ?? 0 );
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'press-release' !== $post->post_type ) {
            wp_send_json_error( [ 'message' => 'Choose a valid Press Release post ID.' ], 400 );
        }
        $image = DashboardData::post_image( $post_id );
        $inspection = ExternalImageSizing::inspect_url( (string) $image['url'], false );
        if ( empty( $inspection['success'] ) ) {
            wp_send_json_error( [ 'message' => $inspection['message'] ?? 'The source image is not eligible for repair.', 'inspection' => $inspection ], 422 );
        }
        if ( self::post_bool( 'dry_run' ) ) {
            wp_send_json_success( [
                'message'       => 'Repair preview completed. No data changed.',
                'post_id'       => $post_id,
                'current_image' => $image,
                'inspection'    => $inspection,
            ] );
        }
        try {
            $result = ExternalImageSizing::sync_remote_featured_image( $post_id, (string) $image['url'], (string) $post->post_title );
            DistributorActivity::record( 'Remote image binding repaired.', [ 'post_id' => $post_id, 'attachment_id' => $result['attachment_id'] ?? 0 ], 'success' );
            wp_send_json_success( [ 'message' => 'Remote image metadata and featured-image binding were repaired.', 'post_id' => $post_id, 'result' => $result ] );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 500 );
        }
    }

    public static function inspect_acf_post(): void {
        self::guard();
        try {
            wp_send_json_success( DashboardData::inspect_acf_post( absint( $_POST['post_id'] ?? 0 ) ) );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 400 );
        }
    }

    public static function run_diagnostics(): void {
        self::guard();
        $report = DistributorDiagnostics::run( true );
        $check_id = self::post_text( 'check_id' );
        if ( '' !== $check_id ) {
            $matches = array_values( array_filter( $report['checks'], static fn( array $check ): bool => $check_id === $check['id'] ) );
            if ( [] === $matches ) {
                wp_send_json_error( [ 'message' => 'Unknown diagnostic check.' ], 400 );
            }
            $report['checks'] = $matches;
            $report['passed'] = ! empty( $matches[0]['success'] ) ? 1 : 0;
            $report['failed'] = ! empty( $matches[0]['success'] ) ? 0 : 1;
            $report['success'] = ! empty( $matches[0]['success'] );
        }
        wp_send_json_success( $report );
    }

    public static function preview_purge(): void {
        self::guard();
        try {
            wp_send_json_success( DeletionSync::preview() );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 500 );
        }
    }

    public static function execute_purge(): void {
        self::guard();
        try {
            $result = DeletionSync::execute( self::post_text( 'signature' ) );
            DistributorActivity::record( 'Deletion synchronization completed.', [ 'trashed_count' => $result['trashed_count'] ?? 0, 'failed_ids' => $result['failed_ids'] ?? [] ], empty( $result['failed_ids'] ) ? 'success' : 'warning' );
            wp_send_json_success( $result );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 409 );
        }
    }

    public static function test_import_cron(): void {
        self::guard();
        try {
            $result = NativeFeedImporter::run(
                [
                    'trigger'        => 'cron-test',
                    'dry_run'        => true,
                    'record_history' => false,
                ]
            );
            wp_send_json_success(
                $result + [
                    'message'            => 'Import cron test completed without publishing, updating posts, or moving the live cursor.',
                    'read_only'           => true,
                    'live_cursor_changed' => false,
                ]
            );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage(), 'read_only' => true ], 500 );
        }
    }

    public static function test_deletion_cron(): void {
        self::guard();
        try {
            $preview = DeletionSync::preview();
            wp_send_json_success(
                $preview + [
                    'message'   => 'Deletion cron test completed as a preview. No posts were moved to Trash.',
                    'read_only'  => true,
                    'posts_changed' => false,
                ]
            );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage(), 'read_only' => true ], 500 );
        }
    }

    public static function apply_legacy_action(): void {
        self::guard();
        $action = self::post_text( 'legacy_action' );
        try {
            $receipt = \hpr_distributor\Migration\LegacyDependencyRetirement::apply( $action );
            if ( empty( $receipt['success'] ) ) {
                wp_send_json_error(
                    [
                        'message' => 'The selected plugin action did not complete. Review the returned state and try again.',
                        'receipt' => $receipt,
                        'state'   => $receipt['after'] ?? [],
                    ],
                    409
                );
            }
            $label = match ( $action ) {
                \hpr_distributor\Migration\LegacyDependencyRetirement::ACTION_DISABLE_ECHO_JOB => 'The matching Hexa PR Wire Echo job is disabled.',
                \hpr_distributor\Migration\LegacyDependencyRetirement::ACTION_DISABLE_ECHO_PLUGIN => 'Echo RSS is disabled.',
                \hpr_distributor\Migration\LegacyDependencyRetirement::ACTION_DISABLE_FIFU_PLUGIN => 'FIFU is disabled.',
                default => 'The selected action completed.',
            };
            DistributorActivity::record( $label, [ 'action' => $action ], 'success' );
            wp_send_json_success( [ 'message' => $label, 'receipt' => $receipt, 'state' => $receipt['after'] ] );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ 'message' => $throwable->getMessage() ], 400 );
        }
    }

    private static function guard(): void {
        check_ajax_referer( Config::AJAX_NONCE, 'nonce' );
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
        }
    }

    private static function post_text( string $key ): string {
        return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
    }

    private static function post_bool( string $key ): bool {
        $value = isset( $_POST[ $key ] ) ? strtolower( (string) wp_unslash( $_POST[ $key ] ) ) : '';
        return in_array( $value, [ '1', 'true', 'yes', 'on' ], true );
    }
}
