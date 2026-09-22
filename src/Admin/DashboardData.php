<?php

namespace hpr_distributor\Admin;

use hpr_distributor\ContentTypes\PressReleaseFieldGroups;
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;
use hpr_distributor\Lifecycle\DeletionSync;
use hpr_distributor\Media\ExternalImageSizing;

defined( 'ABSPATH' ) || exit;

final class DashboardData {
    public static function overview(): array {
        return [
            'readiness'  => NativeFeedSettings::readiness(),
            'last_run'   => self::last_run(),
            'cursor'     => NativeFeedImporter::cursor( (string) NativeFeedSettings::get()['feed_url'] ),
            'counts'     => self::post_counts(),
            'images'     => self::image_inventory( 10 ),
            'duplicates' => self::duplicate_report(),
            'recent'     => self::recent_articles( 10 ),
            'crons'      => self::cron_status(),
        ];
    }

    public static function last_run(): array {
        $last = get_option( NativeFeedImporter::LAST_RUN_OPTION, [] );
        return is_array( $last ) ? $last : [];
    }

    public static function post_counts(): array {
        $counts = post_type_exists( 'press-release' ) ? wp_count_posts( 'press-release' ) : null;
        return [
            'publish' => is_object( $counts ) ? (int) ( $counts->publish ?? 0 ) : 0,
            'draft'   => is_object( $counts ) ? (int) ( $counts->draft ?? 0 ) : 0,
            'pending' => is_object( $counts ) ? (int) ( $counts->pending ?? 0 ) : 0,
            'private' => is_object( $counts ) ? (int) ( $counts->private ?? 0 ) : 0,
            'trash'   => is_object( $counts ) ? (int) ( $counts->trash ?? 0 ) : 0,
        ];
    }

    public static function recent_articles( int $limit = 10 ): array {
        $posts = get_posts(
            [
                'post_type'      => 'press-release',
                'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                'posts_per_page' => max( 1, min( 50, $limit ) ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $image = self::post_image( (int) $post->ID );
            $rows[] = [
                'post_id'       => (int) $post->ID,
                'title'         => (string) $post->post_title,
                'status'        => (string) $post->post_status,
                'published_gmt' => (string) $post->post_date_gmt,
                'imported_gmt'  => (string) get_post_meta( $post->ID, '_hpr_last_imported_gmt', true ),
                'source_id'     => (string) get_post_meta( $post->ID, '_hpr_source_id', true ),
                'source_url'    => (string) get_post_meta( $post->ID, '_hpr_canonical_source_url', true ),
                'edit_url'      => (string) get_edit_post_link( $post->ID ),
                'view_url'      => (string) get_permalink( $post->ID ),
                'image'         => $image,
            ];
        }
        return $rows;
    }

    public static function image_inventory( int $row_limit = 25 ): array {
        $ids = self::all_press_release_ids();
        $settings = NativeFeedSettings::get();
        $allowed_host = (string) $settings['allowed_host'];
        $totals = [
            'posts'          => count( $ids ),
            'allowed_remote' => 0,
            'other_remote'   => 0,
            'local'          => 0,
            'no_image'       => 0,
        ];
        $by_host = [];
        $rows = [];

        foreach ( $ids as $post_id ) {
            $image = self::post_image( (int) $post_id );
            $type = (string) $image['type'];
            if ( 'remote' === $type && SourceIdentity::allowed_host( (string) $image['url'], $allowed_host ) ) {
                $totals['allowed_remote']++;
            } elseif ( 'remote' === $type ) {
                $totals['other_remote']++;
            } elseif ( 'local' === $type ) {
                $totals['local']++;
            } else {
                $totals['no_image']++;
            }

            $host = (string) $image['host'];
            if ( '' !== $host ) {
                $by_host[ $host ] = (int) ( $by_host[ $host ] ?? 0 ) + 1;
            }

            if ( count( $rows ) < max( 0, $row_limit ) ) {
                $rows[] = [
                    'post_id'  => (int) $post_id,
                    'title'    => (string) get_the_title( $post_id ),
                    'view_url' => (string) get_permalink( $post_id ),
                    'image'    => $image,
                ];
            }
        }

        arsort( $by_host );
        return [ 'totals' => $totals, 'by_host' => $by_host, 'rows' => $rows ];
    }

    public static function duplicate_report(): array {
        $keys = [
            'canonical_url'   => '_hpr_canonical_source_url',
            'source_id'       => '_hpr_source_id',
            'source_identity' => '_hpr_source_identity',
        ];
        $result = [];
        foreach ( $keys as $label => $meta_key ) {
            $values = [];
            foreach ( self::all_press_release_ids() as $post_id ) {
                $value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
                if ( '' !== $value ) {
                    $values[ $value ][] = (int) $post_id;
                }
            }
            $groups = array_filter( $values, static fn( array $ids ): bool => 1 < count( $ids ) );
            $affected = 0;
            foreach ( $groups as $ids ) {
                $affected += count( $ids );
            }
            $result[ $label ] = [
                'group_count'   => count( $groups ),
                'affected_rows' => $affected,
                'groups'        => array_slice( $groups, 0, 25, true ),
            ];
        }
        return $result;
    }

    public static function acf_report(): array {
        $definitions = [
            PressReleaseFieldGroups::source_metadata(),
            PressReleaseFieldGroups::seo_overrides(),
        ];
        $groups = [];
        foreach ( $definitions as $definition ) {
            $key = (string) $definition['key'];
            $registered = false;
            if ( function_exists( 'acf_get_local_field_group' ) ) {
                $registered = (bool) acf_get_local_field_group( $key );
            }
            if ( ! $registered && function_exists( 'acf_get_field_group' ) ) {
                $registered = (bool) acf_get_field_group( $key );
            }
            $groups[] = [
                'key'         => $key,
                'title'       => (string) $definition['title'],
                'active'      => ! empty( $definition['active'] ),
                'registered'  => $registered,
                'field_count' => self::count_fields( (array) $definition['fields'] ),
                'fields'      => self::flatten_fields( (array) $definition['fields'] ),
            ];
        }

        return [
            'acf_active' => function_exists( 'acf_get_field_group' ) || function_exists( 'acf_get_local_field_group' ),
            'groups'     => $groups,
        ];
    }

    public static function inspect_acf_post( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'press-release' !== $post->post_type ) {
            throw new \InvalidArgumentException( 'Choose a valid Press Release post ID.' );
        }

        $fields = [];
        foreach ( self::acf_report()['groups'] as $group ) {
            foreach ( $group['fields'] as $field ) {
                $name = (string) $field['name'];
                $value = function_exists( 'get_field' ) ? get_field( $name, $post_id, false ) : get_post_meta( $post_id, $name, true );
                $fields[] = [
                    'group'     => (string) $group['title'],
                    'label'     => (string) $field['label'],
                    'name'      => $name,
                    'type'      => (string) $field['type'],
                    'has_value' => ! ( null === $value || '' === $value || [] === $value ),
                    'value'     => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
                ];
            }
        }

        return [
            'post_id'  => $post_id,
            'title'    => (string) $post->post_title,
            'edit_url' => (string) get_edit_post_link( $post_id ),
            'fields'   => $fields,
        ];
    }

    public static function cron_status(): array {
        $hooks = [
            NativeFeedSettings::CRON_HOOK => 'Native feed polling',
            'hexaprwire_process_deletes'  => 'Deletion synchronization',
        ];
        $rows = [];
        foreach ( $hooks as $hook => $label ) {
            $next = wp_next_scheduled( $hook );
            $event = false !== $next && function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( $hook ) : null;
            $rows[] = [
                'hook'     => $hook,
                'label'    => $label,
                'scheduled'=> false !== $next,
                'next_run' => false === $next ? 0 : (int) $next,
                'interval' => is_object( $event ) ? (string) ( $event->schedule ?? '' ) : '',
            ];
        }
        return $rows;
    }

    public static function cron_report(): array {
        $settings = NativeFeedSettings::get();
        $history = NativeFeedImporter::history();
        $scheduled_attempts = array_values(
            array_filter(
                $history,
                static fn( array $run ): bool => 'schedule' === (string) ( $run['trigger'] ?? '' )
            )
        );
        $scheduled_successes = array_values(
            array_filter(
                $scheduled_attempts,
                static fn( array $run ): bool => ! empty( $run['success'] )
            )
        );
        $last_deletion = get_option( DeletionSync::RECEIPT_OPTION, [] );
        $last_deletion_success = get_option( DeletionSync::LAST_SUCCESS_OPTION, [] );

        return [
            'tasks' => self::cron_status(),
            'import' => [
                'settings'             => $settings,
                'cursor'               => NativeFeedImporter::cursor( (string) $settings['feed_url'] ),
                'last_run'             => self::last_run(),
                'last_scheduled'       => $scheduled_attempts[0] ?? [],
                'last_scheduled_success'=> $scheduled_successes[0] ?? [],
                'history'              => $history,
            ],
            'deletion' => [
                'enabled'      => (bool) get_option( 'enable_hpr_auto_deletes', false ),
                'last_run'     => is_array( $last_deletion ) ? $last_deletion : [],
                'last_success' => is_array( $last_deletion_success ) ? $last_deletion_success : [],
            ],
        ];
    }

    public static function post_image( int $post_id ): array {
        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        $candidates = [];
        if ( 0 < $attachment_id ) {
            foreach ( [ '_hpr_remote_featured_image_url', '_hpr_external_featured_image_url', '_wp_attached_file' ] as $key ) {
                $candidates[] = (string) get_post_meta( $attachment_id, $key, true );
            }
        }
        foreach ( [ '_hpr_remote_featured_image_url', 'fifu_image_url', 'echo_featured_img' ] as $key ) {
            $candidates[] = (string) get_post_meta( $post_id, $key, true );
        }
        if ( 0 < $attachment_id ) {
            $candidates[] = (string) ExternalImageSizing::attachment_url( $attachment_id );
            $candidates[] = (string) wp_get_attachment_url( $attachment_id );
        }

        $url = '';
        foreach ( $candidates as $candidate ) {
            $candidate = trim( $candidate );
            if ( preg_match( '#^https?://#i', $candidate ) ) {
                $url = esc_url_raw( $candidate );
                break;
            }
        }

        if ( '' === $url ) {
            return [
                'type'          => 0 < $attachment_id ? 'local' : 'none',
                'url'           => 0 < $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '',
                'host'          => '',
                'attachment_id' => $attachment_id,
            ];
        }

        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        return [
            'type'          => '' !== $host && $host !== $site_host ? 'remote' : 'local',
            'url'           => $url,
            'host'          => $host,
            'attachment_id' => $attachment_id,
        ];
    }

    private static function all_press_release_ids(): array {
        static $ids = null;
        if ( is_array( $ids ) ) {
            return $ids;
        }
        $ids = get_posts(
            [
                'post_type'              => 'press-release',
                'post_status'            => [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ],
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );
        return array_values( array_map( 'intval', $ids ) );
    }

    private static function count_fields( array $fields ): int {
        return count( self::flatten_fields( $fields ) );
    }

    private static function flatten_fields( array $fields ): array {
        $flat = [];
        foreach ( $fields as $field ) {
            $flat[] = [
                'key'      => (string) ( $field['key'] ?? '' ),
                'label'    => (string) ( $field['label'] ?? '' ),
                'name'     => (string) ( $field['name'] ?? '' ),
                'type'     => (string) ( $field['type'] ?? '' ),
                'required' => ! empty( $field['required'] ),
            ];
            if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
                $flat = array_merge( $flat, self::flatten_fields( $field['sub_fields'] ) );
            }
        }
        return $flat;
    }
}
