<?php

namespace hpr_distributor\ContentTypes;

use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;

defined( 'ABSPATH' ) || exit;

final class PressReleaseStructures {
    private static ?ContentTypeRegistry $registry = null;

    public static function register(): void {
        self::registry()->register();
    }

    public static function registry(): ContentTypeRegistry {
        if ( self::$registry instanceof ContentTypeRegistry ) {
            return self::$registry;
        }

        self::$registry = new ContentTypeRegistry(
            [
                'option_name'   => 'hpr_content_type_settings',
                'capability'    => 'manage_options',
                'ajax_action'   => 'hpr_save_content_type',
                'nonce_action'  => \hpr_distributor\Config::AJAX_NONCE,
                'nonce_field'   => 'nonce',
                'hook_priority' => 4,
            ]
        );

        self::$registry->add(
            [
                'id'                    => 'press-release',
                'owner'                 => 'Hexa PR Wire',
                'description'           => 'Press releases distributed through the Hexa PR Wire network.',
                'enabled_default'       => true,
                'legacy_enabled_option' => 'register_press_release_post_type',
                'post_type'             => [
                    'key'          => 'press-release',
                    'singular'     => 'Press Release',
                    'plural'       => 'Press Releases',
                    'rewrite_slug' => 'press-release',
                    'args'         => [
                        'description'      => 'Post type for Hexa PR Wire press releases.',
                        'public'           => true,
                        'show_in_rest'     => true,
                        'supports'         => [
                            'title',
                            'author',
                            'trackbacks',
                            'editor',
                            'excerpt',
                            'revisions',
                            'page-attributes',
                            'thumbnail',
                            'custom-fields',
                            'post-formats',
                        ],
                        'taxonomies'       => [ 'category', 'post_tag' ],
                        'delete_with_user' => false,
                    ],
                ],
                'field_groups'            => [
                    [
                        'id'              => 'source-metadata',
                        'label'           => 'Source and Author Metadata',
                        'description'     => 'Stores the original source and author identifiers received during distribution.',
                        'group_key'       => 'group_658a07665ddf5',
                        'enabled_default' => false,
                        'legacy_option'   => 'register_press_release_custom_fields',
                        'definition'      => [ PressReleaseFieldGroups::class, 'source_metadata' ],
                        'fields'          => [
                            'Original Post: slug and URL',
                            'Author: slug, URL, and ID',
                        ],
                        'dependencies'    => [ 'Advanced Custom Fields Pro' ],
                    ],
                    [
                        'id'              => 'seo-overrides',
                        'label'           => 'SEO Overrides',
                        'description'     => 'Per-release follow and sitemap overrides with global and category inheritance.',
                        'group_key'       => 'group_hpr_seo_overrides',
                        'enabled_default' => true,
                        'definition'      => [ PressReleaseFieldGroups::class, 'seo_overrides' ],
                        'fields'          => [
                            'Anchor Follow Status Override',
                            'Sitemap Inclusion Override',
                        ],
                        'dependencies'    => [ 'Advanced Custom Fields Pro' ],
                    ],
                ],
            ]
        );

        return self::$registry;
    }
}
