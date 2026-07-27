<?php

namespace hpr_distributor\ContentTypes;

defined( 'ABSPATH' ) || exit;

final class PressReleaseFieldGroups {
    /** @return array<string,mixed> */
    public static function source_metadata(): array {
        return [
            'key'      => 'group_658a07665ddf5',
            'title'    => 'press-release',
            'fields'   => [
                [
                    'key'               => 'field_658a0794c78bb',
                    'label'             => 'Original Post',
                    'name'              => 'original_post',
                    'aria-label'        => '',
                    'type'              => 'group',
                    'instructions'      => '',
                    'required'          => 0,
                    'conditional_logic' => 0,
                    'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
                    'layout'            => 'block',
                    'sub_fields'        => [
                        self::text_field( 'field_658a07de9760c', 'slug', 'slug' ),
                        self::text_field( 'field_658a07e39760d', 'URL', 'url' ),
                    ],
                ],
                [
                    'key'               => 'field_658a079ec78bc',
                    'label'             => 'Author',
                    'name'              => 'author',
                    'aria-label'        => '',
                    'type'              => 'group',
                    'instructions'      => '',
                    'required'          => 0,
                    'conditional_logic' => 0,
                    'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
                    'layout'            => 'block',
                    'sub_fields'        => [
                        self::text_field( 'field_658a07a9c78bd', 'slug', 'slug' ),
                        self::text_field( 'field_658a07b9c78be', 'URL', 'url' ),
                        self::text_field( 'field_658a07ed9760e', 'ID', 'id' ),
                    ],
                ],
            ],
            'location' => [
                [
                    [ 'param' => 'post_type', 'operator' => '==', 'value' => '@post_type' ],
                ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen'        => '',
            'active'                => true,
            'description'           => '',
            'show_in_rest'          => 0,
        ];
    }

    /** @return array<string,mixed> */
    public static function seo_overrides(): array {
        return [
            'key'      => 'group_hpr_seo_overrides',
            'title'    => 'SEO Settings - Hexa PR Wire',
            'fields'   => [
                [
                    'key'           => 'field_hpr_seo_follow_override',
                    'label'         => 'Anchor Follow Status Override',
                    'name'          => 'hpr_seo_follow_override',
                    'type'          => 'radio',
                    'instructions'  => 'Override the global/category follow setting for this specific press release.',
                    'required'      => 0,
                    'choices'       => [
                        'inherit'  => 'Inherit (use category/global setting)',
                        'dofollow' => 'Force Do Follow',
                        'nofollow' => 'Force No Follow',
                    ],
                    'default_value' => 'inherit',
                    'layout'        => 'horizontal',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_hpr_seo_sitemap_override',
                    'label'         => 'Sitemap Inclusion Override',
                    'name'          => 'hpr_seo_sitemap_override',
                    'type'          => 'radio',
                    'instructions'  => 'Override sitemap inclusion for this specific press release. Does not affect RSS feeds.',
                    'required'      => 0,
                    'choices'       => [
                        'inherit' => 'Inherit (use category/global setting)',
                        'include' => 'Force Include in Sitemap',
                        'exclude' => 'Force Exclude from Sitemap',
                    ],
                    'default_value' => 'inherit',
                    'layout'        => 'horizontal',
                    'wrapper'       => [ 'width' => '50' ],
                ],
            ],
            'location' => [
                [
                    [ 'param' => 'post_type', 'operator' => '==', 'value' => '@post_type' ],
                ],
            ],
            'menu_order'            => 90,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
            'description'           => 'Per-post SEO overrides for follow status and sitemap inclusion.',
        ];
    }

    /** @return array<string,mixed> */
    private static function text_field( string $key, string $label, string $name ): array {
        return [
            'key'               => $key,
            'label'             => $label,
            'name'              => $name,
            'aria-label'        => '',
            'type'              => 'text',
            'instructions'      => '',
            'required'          => 0,
            'conditional_logic' => 0,
            'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
            'default_value'     => '',
            'maxlength'         => '',
            'placeholder'       => '',
            'prepend'           => '',
            'append'            => '',
        ];
    }
}
