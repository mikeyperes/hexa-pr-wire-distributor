<?php

namespace hpr_distributor\Content;

use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDefinition;
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeRegistry;
use hpr_distributor\Import\NativeFeedSettings;

defined( 'ABSPATH' ) || exit;

/** Opt-in, unstyled linked category labels for release templates and loops. */
final class PressReleaseCategoryLabel {
    public const SHORTCODE = 'hpr_press_release_categories';

    public static function register(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    public static function registry(): ShortcodeRegistry {
        return ( new ShortcodeRegistry() )->add( new ShortcodeDefinition(
            self::SHORTCODE,
            'Press release categories',
            '[' . self::SHORTCODE . ']',
            'Linked categories excluding the generic press-release category; empty when none remain.',
            'render'
        ) );
    }

    public static function render( $attributes = [] ): string {
        $attributes = shortcode_atts( [ 'post_id' => 0, 'separator' => ', ' ], (array) $attributes, self::SHORTCODE );
        $post_id = absint( $attributes['post_id'] ) ?: get_the_ID();
        if ( ! $post_id || 'press-release' !== get_post_type( $post_id ) ) {
            return '';
        }
        $terms = get_the_terms( $post_id, 'category' );
        if ( ! is_array( $terms ) ) {
            return '';
        }
        $generic_slug = NativeFeedSettings::get()['category'];
        $links = [];
        foreach ( $terms as $term ) {
            if ( in_array( $term->slug, [ 'press-release', $generic_slug ], true )
                || 0 === strcasecmp( trim( $term->name ), 'Press Release' ) ) {
                continue;
            }
            $url = get_term_link( $term );
            if ( is_wp_error( $url ) ) {
                continue;
            }
            $links[] = '<a class="hpr-press-release-category" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
        }
        return implode( esc_html( (string) $attributes['separator'] ), $links );
    }
}
