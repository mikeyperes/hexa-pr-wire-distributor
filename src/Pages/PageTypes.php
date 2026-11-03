<?php
declare( strict_types=1 );

namespace hpr_distributor\Pages;

use hpr_distributor\ContentTypes\PressReleaseStructures;
use Hexa\PluginCore\SiteStructure\PageStructureManager;

defined( 'ABSPATH' ) || exit;

/** Existing release layouts, never ordinary-page creation or new public output. */
final class PageTypes {
    public static function definitions(): array {
        $registry = PressReleaseStructures::registry();
        $definition = $registry->definition( 'press-release' );
        $settings = $registry->store()->resolve( $definition );
        $type = $settings['post_type'];
        $key = $type['key'];
        $object = get_post_type_object( $key );
        global $wp_rewrite;
        $structure = $wp_rewrite instanceof \WP_Rewrite ? (string) $wp_rewrite->get_extra_permastruct( $key ) : '';
        $example = '';
        if ( $object ) {
            $ids = get_posts( [ 'post_type' => $key, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ] );
            $example = $ids ? (string) get_permalink( $ids[0] ) : '';
        }
        $pages = [
            'single_press_release' => [
                'title' => 'Single ' . $type['singular'],
                'slug' => trim( str_replace( '%' . $key . '%', '<release>', $structure ), '/' ),
                'kind' => 'layout',
                'content_source' => 'PressReleaseStructures registry and distributed press-release records',
                'slug_source' => 'WordPress permalink permastruct and get_permalink() filters',
                'example_url' => $example,
            ],
        ];
        $archive = $object ? get_post_type_archive_link( $key ) : false;
        if ( $archive ) {
            $pages['press_releases_archive'] = [
                'title' => $type['plural'],
                'slug' => trim( (string) wp_parse_url( $archive, PHP_URL_PATH ), '/' ),
                'url' => $archive,
                'kind' => 'layout',
                'content_source' => 'WordPress press-release archive',
                'slug_source' => 'Registered press-release archive settings',
            ];
            $pages['single_press_release']['parent'] = 'press_releases_archive';
        }
        foreach ( $pages as &$page ) {
            $page += [ 'slugs' => [], 'parent' => '', 'post_type' => $key, 'design' => 'custom', 'enabled_by_default' => (bool) ( $settings['enabled'] ?? $definition['enabled_default'] ), 'conditions' => [ 'hpr_content_type_settings.press-release.enabled' ], 'title_source' => 'PressReleaseStructures content-type settings' ];
        }
        unset( $page );
        return $pages;
    }

    public static function pages(): array {
        return array_filter( self::definitions(), static fn( array $page ): bool => 'page' === $page['kind'] );
    }

    /** Query layouts never enter the ordinary-page manager. */
    public static function manager(): PageStructureManager {
        return new PageStructureManager( [ 'pages' => self::pages(), 'option_prefix' => 'hpr_distributor_page_', 'reuse_existing_pages' => true, 'created_page_status' => 'draft' ] );
    }
}
