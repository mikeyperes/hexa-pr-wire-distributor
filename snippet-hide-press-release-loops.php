<?php
namespace hpr_distributor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HPR_PRESS_RELEASE_POST_TYPE = 'press-release';
const HPR_HIDE_PRESS_RELEASE_PRIORITY = 10000;

function hide_press_release_from_home_loop(): void {
    hpr_register_press_release_loop_exclusion( 'home' );
}

function hide_press_release_from_author_loop(): void {
    hpr_register_press_release_loop_exclusion( 'author' );
}

function hide_press_release_from_category_loop(): void {
    hpr_register_press_release_loop_exclusion( 'category' );
}

function hide_press_release_from_tag_loop(): void {
    hpr_register_press_release_loop_exclusion( 'tag' );
}

function hide_press_release_from_related_single_loop(): void {
    hpr_register_press_release_loop_exclusion( 'related_single' );
}

function hpr_register_press_release_loop_exclusion( string $context ): void {
    static $contexts = [];
    static $main_hook_registered = false;
    static $elementor_hooks_registered = false;
    static $posts_results_hook_registered = false;

    $context = sanitize_key( $context );
    if ( '' === $context || isset( $contexts[ $context ] ) ) {
        return;
    }

    $contexts[ $context ] = true;

    if ( ! $main_hook_registered && in_array( $context, [ 'home', 'author', 'category', 'tag', 'related_single' ], true ) ) {
        add_action( 'pre_get_posts', __NAMESPACE__ . '\\hpr_hide_press_release_from_main_query', HPR_HIDE_PRESS_RELEASE_PRIORITY );
        $main_hook_registered = true;
    }

    if ( ! $elementor_hooks_registered && in_array( $context, [ 'home', 'author', 'category', 'tag', 'related_single' ], true ) ) {
        add_filter( 'elementor/query/query_args', __NAMESPACE__ . '\\hpr_hide_press_release_from_elementor_query_args', HPR_HIDE_PRESS_RELEASE_PRIORITY, 2 );
        add_filter( 'elementor/query/fallback_query_args', __NAMESPACE__ . '\\hpr_hide_press_release_from_elementor_query_args', HPR_HIDE_PRESS_RELEASE_PRIORITY, 2 );
        $elementor_hooks_registered = true;
    }

    if ( ! $posts_results_hook_registered && in_array( $context, [ 'home', 'author', 'category', 'tag', 'related_single' ], true ) ) {
        add_filter( 'the_posts', __NAMESPACE__ . '\\hpr_hide_press_release_from_posts_results', HPR_HIDE_PRESS_RELEASE_PRIORITY, 2 );
        $posts_results_hook_registered = true;
    }
}

function hpr_hide_press_release_from_main_query( \WP_Query $query ): void {
    if ( ! hpr_can_filter_frontend_query() ) {
        return;
    }

    $feed_query = $query->is_feed() || '' !== (string) $query->get( 'feed' );
    if ( $feed_query || $query->is_search() || $query->is_preview() || $query->is_singular() ) {
        return;
    }

    if ( ! $query->is_main_query() ) {
        if ( get_option( 'hide_press_release_from_related_single_loop', false ) && is_singular( 'post' ) ) {
            hpr_remove_press_release_from_wp_query( $query );
        }

        return;
    }

    $matched_context = null;
    if ( get_option( 'hide_press_release_from_home_loop', false ) && $query->is_home() ) {
        $matched_context = 'home';
    } elseif ( get_option( 'hide_press_release_from_author_loop', false ) && $query->is_author() ) {
        $matched_context = 'author';
    } elseif ( get_option( 'hide_press_release_from_category_loop', false ) && $query->is_category() ) {
        $matched_context = 'category';
    } elseif ( get_option( 'hide_press_release_from_tag_loop', false ) && $query->is_tag() ) {
        $matched_context = 'tag';
    }

    if ( null === $matched_context ) {
        return;
    }

    hpr_remove_press_release_from_wp_query( $query );
}

function hpr_hide_press_release_from_elementor_query_args( array $query_args, $widget ): array {
    if ( ! hpr_can_filter_frontend_query() ) {
        return $query_args;
    }

    if ( hpr_elementor_query_matches_enabled_context( $widget ) ) {
        return hpr_remove_press_release_from_query_args( $query_args, true );
    }

    return $query_args;
}

function hpr_elementor_query_matches_enabled_context( $widget ): bool {
    if ( get_option( 'hide_press_release_from_related_single_loop', false ) && is_singular( 'post' ) && hpr_elementor_widget_is_content_loop( $widget ) ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_home_loop', false ) && ( is_front_page() || is_home() ) ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_author_loop', false ) && is_author() ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_category_loop', false ) && is_category() ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_tag_loop', false ) && is_tag() ) {
        return true;
    }

    return false;
}

function hpr_elementor_widget_is_content_loop( $widget ): bool {
    if ( hpr_elementor_widget_uses_related_query( $widget ) ) {
        return true;
    }

    if ( ! is_object( $widget ) ) {
        return false;
    }

    if ( method_exists( $widget, 'get_name' ) ) {
        $name = (string) $widget->get_name();
        if ( in_array( $name, [ 'loop-grid', 'posts', 'archive-posts' ], true ) ) {
            return true;
        }
    }

    if ( ! method_exists( $widget, 'get_settings' ) ) {
        return false;
    }

    $settings = $widget->get_settings();
    if ( ! is_array( $settings ) ) {
        return false;
    }

    return isset( $settings['template_id'] )
        || isset( $settings['posts_per_page'] )
        || isset( $settings['post_query_exclude'] )
        || isset( $settings['post_query_include'] );
}

function hpr_elementor_widget_uses_related_query( $widget ): bool {
    if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_settings' ) ) {
        return false;
    }

    $settings = $widget->get_settings();
    if ( ! is_array( $settings ) ) {
        return false;
    }

    foreach ( $settings as $key => $value ) {
        if ( is_string( $key ) && substr( $key, -10 ) === '_post_type' && 'related' === $value ) {
            return true;
        }
    }

    return false;
}

function hpr_remove_press_release_from_wp_query( \WP_Query $query ): void {
    $query_args = $query->query_vars;
    $filtered = hpr_remove_press_release_from_query_args( $query_args, true );

    foreach ( [ 'post_type', 'post__in' ] as $key ) {
        if ( array_key_exists( $key, $filtered ) && ( ! array_key_exists( $key, $query_args ) || $filtered[ $key ] !== $query_args[ $key ] ) ) {
            $query->set( $key, $filtered[ $key ] );
        }
    }
}

function hpr_hide_press_release_from_posts_results( array $posts, \WP_Query $query ): array {
    if ( [] === $posts || ! hpr_can_filter_frontend_query() || $query->is_main_query() ) {
        return $posts;
    }

    $feed_query = $query->is_feed() || '' !== (string) $query->get( 'feed' );
    if ( $feed_query || $query->is_search() || $query->is_preview() ) {
        return $posts;
    }

    if ( ! hpr_request_matches_enabled_loop_context() ) {
        return $posts;
    }

    $filtered = array_values(
        array_filter(
            $posts,
            static function ( $post ): bool {
                return HPR_PRESS_RELEASE_POST_TYPE !== get_post_type( $post );
            }
        )
    );

    return count( $filtered ) === count( $posts ) ? $posts : $filtered;
}

function hpr_request_matches_enabled_loop_context(): bool {
    if ( get_option( 'hide_press_release_from_related_single_loop', false ) && is_singular( 'post' ) ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_home_loop', false ) && ( is_front_page() || is_home() ) ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_author_loop', false ) && is_author() ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_category_loop', false ) && is_category() ) {
        return true;
    }

    if ( get_option( 'hide_press_release_from_tag_loop', false ) && is_tag() ) {
        return true;
    }

    return false;
}

function hpr_remove_press_release_from_query_args( array $query_args, bool $force_default_post_type = false ): array {
    $post_type = $query_args['post_type'] ?? '';

    if ( '' === $post_type || null === $post_type ) {
        if ( $force_default_post_type ) {
            $query_args['post_type'] = 'post';
        }

        return $query_args;
    }

    if ( 'any' === $post_type ) {
        $query_args['post_type'] = hpr_public_post_types_without_press_release();
        return $query_args;
    }

    $post_types = is_array( $post_type ) ? array_values( $post_type ) : [ $post_type ];
    if ( ! in_array( HPR_PRESS_RELEASE_POST_TYPE, $post_types, true ) ) {
        return $query_args;
    }

    $post_types = array_values( array_diff( $post_types, [ HPR_PRESS_RELEASE_POST_TYPE ] ) );

    if ( [] === $post_types ) {
        $query_args['post_type'] = HPR_PRESS_RELEASE_POST_TYPE;
        $query_args['post__in'] = [ 0 ];
        return $query_args;
    }

    $query_args['post_type'] = is_array( $post_type ) ? $post_types : $post_types[0];
    return $query_args;
}

function hpr_public_post_types_without_press_release(): array {
    static $post_types = null;

    if ( null !== $post_types ) {
        return $post_types;
    }

    $post_types = array_values( array_diff( get_post_types( [ 'public' => true ], 'names' ), [ HPR_PRESS_RELEASE_POST_TYPE ] ) );
    if ( [] === $post_types ) {
        $post_types = [ 'post' ];
    }

    return $post_types;
}

function hpr_can_filter_frontend_query(): bool {
    if ( is_admin() ) {
        return false;
    }

    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
        return false;
    }

    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return false;
    }

    if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
        return false;
    }

    return true;
}
