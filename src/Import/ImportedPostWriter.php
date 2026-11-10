<?php
namespace hpr_distributor\Import;

if ( ! defined( "ABSPATH" ) ) { exit; }

/** Images are optional for syndicated releases, never for unrelated editorial saves. */
final class ImportedPostWriter {
    public static function save( array $data ): int {
        if ( "press-release" !== ( $data["post_type"] ?? "" ) ) {
            throw new \InvalidArgumentException( "Imported delivery requires a press release." );
        }
        $smpi = static function ( $types, $feature ) {
            return "featured_image_required" === $feature ? array_values( array_diff( (array) $types, [ "press-release" ] ) ) : $types;
        };
        $base_tools = static fn( $types ) => array_values( array_diff( (array) $types, [ "press-release" ] ) );
        add_filter( "smpi_article_feature_post_types", $smpi, PHP_INT_MAX, 2 );
        add_filter( "option_hws_required_featured_image_post_types", $base_tools, PHP_INT_MAX );
        try {
            $saved = empty( $data["ID"] )
                ? wp_insert_post( wp_slash( $data ), true )
                : wp_update_post( wp_slash( $data ), true );
            if ( is_wp_error( $saved ) ) {
                throw new \RuntimeException( $saved->get_error_message() );
            }
            $saved = (int) $saved;
            clean_post_cache( $saved );
            if ( $saved < 1 || get_post_status( $saved ) !== $data["post_status"] ) {
                throw new \RuntimeException( "Destination did not save the requested publication status." );
            }
            return $saved;
        } finally {
            remove_filter( "smpi_article_feature_post_types", $smpi, PHP_INT_MAX );
            remove_filter( "option_hws_required_featured_image_post_types", $base_tools, PHP_INT_MAX );
        }
    }
}
