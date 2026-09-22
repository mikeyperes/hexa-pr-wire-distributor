<?php

namespace hpr_distributor\Migration;

use hpr_distributor\Import\SourceIdentity;

defined( 'ABSPATH' ) || exit;

final class SourceSlugRepair {
    public static function run( bool $dry_run = true, int $limit = 500 ): array {
        $ids = get_posts(
            [
                'post_type'              => 'press-release',
                'post_status'            => [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ],
                'posts_per_page'         => max( 1, min( 1000, $limit ) ),
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );

        $result = [ 'checked' => 0, 'changed' => 0, 'skipped' => 0, 'conflicts' => 0, 'dry_run' => $dry_run, 'rows' => [] ];
        foreach ( $ids as $post_id ) {
            $post_id = (int) $post_id;
            $result['checked']++;
            $post = get_post( $post_id );
            $source_url = self::source_url( $post_id );
            $source_slug = sanitize_title( basename( untrailingslashit( (string) wp_parse_url( $source_url, PHP_URL_PATH ) ) ) );
            $current_slug = $post instanceof \WP_Post ? (string) $post->post_name : '';
            if ( '' === $source_slug || '' === $current_slug || $source_slug === $current_slug ) {
                $result['skipped']++;
                continue;
            }

            $conflict = get_page_by_path( $source_slug, OBJECT, 'press-release' );
            $row = [
                'post_id'     => $post_id,
                'source_url'  => $source_url,
                'old_slug'    => $current_slug,
                'source_slug' => $source_slug,
                'conflict_id' => $conflict instanceof \WP_Post && (int) $conflict->ID !== $post_id ? (int) $conflict->ID : 0,
            ];
            if ( 0 < $row['conflict_id'] ) {
                $result['conflicts']++;
                $result['rows'][] = $row;
                continue;
            }

            if ( ! $dry_run ) {
                $old_slugs = get_post_meta( $post_id, '_wp_old_slug', false );
                if ( ! in_array( $current_slug, is_array( $old_slugs ) ? $old_slugs : [], true ) ) {
                    add_post_meta( $post_id, '_wp_old_slug', $current_slug );
                }
                $updated = wp_update_post( [ 'ID' => $post_id, 'post_name' => $source_slug ], true );
                if ( is_wp_error( $updated ) ) {
                    $row['error'] = $updated->get_error_message();
                    $result['conflicts']++;
                    $result['rows'][] = $row;
                    continue;
                }
                update_post_meta( $post_id, 'original_post_slug', $source_slug );
                clean_post_cache( $post_id );
            }

            $result['changed']++;
            $result['rows'][] = $row;
        }

        return $result + [ 'completed_gmt' => current_time( 'mysql', true ) ];
    }

    private static function source_url( int $post_id ): string {
        foreach ( [ '_hpr_canonical_source_url', 'original_post_url', 'echo_post_full_url', 'echo_post_url' ] as $key ) {
            $url = SourceIdentity::canonical_url( (string) get_post_meta( $post_id, $key, true ) );
            if ( '' !== $url ) {
                return $url;
            }
        }
        return '';
    }
}
