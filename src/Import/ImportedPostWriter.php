<?php
namespace hpr_distributor\Import;

if ( ! defined( "ABSPATH" ) ) { exit; }

/**
 * Images are optional for syndicated releases, never for unrelated editorial saves.
 *
 * The importer owns the text it delivers: its title, body and excerpt were
 * sanitized once when the feed was parsed, so no other plugin's save filter
 * may rewrite them. The last `wp_insert_post_data` filter restores them, and
 * the stored row is read back and compared before the save counts.
 */
final class ImportedPostWriter {
    const LOCKED_FIELDS = [ "post_title", "post_content", "post_excerpt" ];

    public static function save( array $data ): int {
        if ( "press-release" !== ( $data["post_type"] ?? "" ) ) {
            throw new \InvalidArgumentException( "Imported delivery requires a press release." );
        }
        $smpi = static function ( $types, $feature ) {
            return "featured_image_required" === $feature ? array_values( array_diff( (array) $types, [ "press-release" ] ) ) : $types;
        };
        $base_tools = static fn( $types ) => array_values( array_diff( (array) $types, [ "press-release" ] ) );
        $locked = array_intersect_key( $data, array_flip( self::LOCKED_FIELDS ) );
        $lock = static function ( $post_data ) use ( $locked ) {
            return is_array( $post_data ) && "press-release" === ( $post_data["post_type"] ?? "" )
                ? array_merge( $post_data, $locked )
                : $post_data;
        };
        add_filter( "smpi_article_feature_post_types", $smpi, PHP_INT_MAX, 2 );
        add_filter( "option_hws_required_featured_image_post_types", $base_tools, PHP_INT_MAX );
        add_filter( "wp_insert_post_data", $lock, PHP_INT_MAX );
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
            self::verify_stored( $saved, $locked );
            return $saved;
        } finally {
            remove_filter( "smpi_article_feature_post_types", $smpi, PHP_INT_MAX );
            remove_filter( "option_hws_required_featured_image_post_types", $base_tools, PHP_INT_MAX );
            remove_filter( "wp_insert_post_data", $lock, PHP_INT_MAX );
        }
    }

    private static function verify_stored( int $post_id, array $locked ): void {
        foreach ( $locked as $field => $expected ) {
            if ( (string) get_post_field( $field, $post_id, "raw" ) !== (string) $expected ) {
                throw new \RuntimeException( "Another plugin changed the imported " . str_replace( "post_", "", $field ) . " after it was saved; the release was not delivered as sent." );
            }
        }
    }
}
