<?php

namespace hpr_distributor\Media;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Lets FIFU (Featured Image From URL) stay active while Distributor owns
 * press-release images.
 *
 * FIFU only changes a post's image when the post carries FIFU data. Legacy
 * Echo imports wrote that data onto press releases, so a release whose image
 * later changes could keep showing FIFU's stale image. Distributor removes
 * FIFU data from press releases only (through FIFU's own deleter when FIFU is
 * active, so its lookup tables stay consistent); every other post keeps FIFU.
 */
final class FifuCoexistence {
    private const FIELDS = [ "fifu_image_url", "fifu_image_alt" ];

    public static function fifu_active(): bool {
        return defined( "FIFU_PLUGIN_DIR" ) || function_exists( "fifu_dev_set_image" );
    }

    /** Remove FIFU data from one press release; true when something was removed. */
    public static function release( int $post_id ): bool {
        if ( "press-release" !== get_post_type( $post_id ) ) {
            return false;
        }
        $removed = false;
        foreach ( self::FIELDS as $field ) {
            if ( "" === (string) get_post_meta( $post_id, $field, true ) ) {
                continue;
            }
            if ( class_exists( "FIFU_Post_Meta_Updater" ) ) {
                \FIFU_Post_Meta_Updater::instance()->delete( $post_id, $field );
            } elseif ( function_exists( "fifu_delete_post_meta" ) ) {
                fifu_delete_post_meta( $post_id, $field );
            }
            delete_post_meta( $post_id, $field );
            $removed = true;
        }
        return $removed;
    }

    /** Release a batch of existing press releases that still carry FIFU data. */
    public static function release_existing( int $limit = 100 ): int {
        $ids = get_posts( [
            "post_type"      => "press-release",
            "post_status"    => "any",
            "posts_per_page" => max( 1, $limit ),
            "fields"         => "ids",
            "no_found_rows"  => true,
            "meta_query"     => [ [ "key" => "fifu_image_url", "compare" => "EXISTS" ] ],
        ] );
        $released = 0;
        foreach ( $ids as $id ) {
            $released += self::release( (int) $id ) ? 1 : 0;
        }
        return $released;
    }

    /** Press releases still carrying FIFU data. */
    public static function pending(): int {
        $query = new \WP_Query( [
            "post_type"      => "press-release",
            "post_status"    => "any",
            "posts_per_page" => 1,
            "fields"         => "ids",
            "meta_query"     => [ [ "key" => "fifu_image_url", "compare" => "EXISTS" ] ],
        ] );
        return (int) $query->found_posts;
    }
}
