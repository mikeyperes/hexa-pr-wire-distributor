<?php

namespace hpr_distributor\Content;

use WP_Query;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PressReleaseLoopExclusion {
    public const POST_TYPE = "press-release";

    private const FILTER_PRIORITY = PHP_INT_MAX - 10;
    private const ELEMENTOR_PRIORITY = 999;
    private const MISSING_OPTION = "__hpr_missing_loop_option__";

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_action( "pre_get_posts", [ self::class, "filter_query" ], self::FILTER_PRIORITY );
        add_filter( "posts_where", [ self::class, "filter_where" ], self::FILTER_PRIORITY, 2 );
        add_filter( "the_posts", [ self::class, "filter_posts" ], self::FILTER_PRIORITY, 2 );

        add_filter( "elementor/query/get_query_args/current_query", [ self::class, "filter_elementor_args" ], self::ELEMENTOR_PRIORITY );
        add_filter( "elementor_pro/query_control/get_query_args/current_query", [ self::class, "filter_elementor_args" ], self::ELEMENTOR_PRIORITY );
        add_filter( "elementor/query/query_args", [ self::class, "filter_elementor_args" ], self::ELEMENTOR_PRIORITY, 2 );
        add_filter( "elementor/query/fallback_query_args", [ self::class, "filter_elementor_args" ], self::ELEMENTOR_PRIORITY, 2 );

        self::$registered = true;
    }

    public static function filter_query( WP_Query $query ): void {
        if ( self::matches_enabled_context( $query ) ) {
            self::exclude_from_query( $query );
        }
    }

    public static function filter_where( string $where, WP_Query $query ): string {
        if ( ! self::matches_enabled_context( $query ) ) {
            return $where;
        }

        global $wpdb;

        return $where . $wpdb->prepare(
            " AND {$wpdb->posts}.post_type <> %s",
            self::POST_TYPE
        );
    }

    public static function filter_posts( array $posts, WP_Query $query ): array {
        if ( [] === $posts || ! self::matches_enabled_context( $query ) ) {
            return $posts;
        }

        return array_values(
            array_filter(
                $posts,
                static fn ( $post ): bool => self::POST_TYPE !== get_post_type( $post )
            )
        );
    }

    public static function filter_elementor_args( array $query_args, $widget = null ): array {
        unset( $widget );

        if ( ! self::matches_enabled_context() ) {
            return $query_args;
        }

        return self::exclude_from_args( $query_args, true );
    }

    public static function matches_enabled_context( ?WP_Query $query = null ): bool {
        if ( ! self::is_frontend_request() ) {
            return false;
        }

        if ( $query instanceof WP_Query ) {
            if ( $query->is_feed() || $query->is_search() || $query->is_preview() ) {
                return false;
            }

            if ( $query->is_main_query() && $query->is_singular() ) {
                return false;
            }

            if ( $query->get( "hpr_force_hide_press_release" ) ) {
                return true;
            }
        }

        if ( is_singular( self::POST_TYPE ) ) {
            return false;
        }

        if (
            self::option_enabled( "hide_press_release_from_home_loop" )
            && ( is_front_page() || is_home() || ( $query instanceof WP_Query && $query->is_home() ) )
        ) {
            return true;
        }

        if ( self::option_enabled( "hide_press_release_from_author_loop" ) ) {
            $query_is_author = $query instanceof WP_Query
                && (
                    $query->is_author()
                    || absint( $query->get( "author" ) ) > 0
                    || "" !== trim( (string) $query->get( "author_name" ) )
                );

            if ( is_author() || $query_is_author ) {
                return true;
            }
        }

        if (
            self::option_enabled( "hide_press_release_from_category_loop" )
            && ( is_category() || ( $query instanceof WP_Query && $query->is_category() ) )
        ) {
            return true;
        }

        if (
            self::option_enabled( "hide_press_release_from_tag_loop" )
            && ( is_tag() || ( $query instanceof WP_Query && $query->is_tag() ) )
        ) {
            return true;
        }

        return self::option_enabled( "hide_press_release_from_related_single_loop" )
            && is_singular( "post" )
            && ( ! $query instanceof WP_Query || ! $query->is_main_query() );
    }

    public static function exclude_from_args( array $query_args, bool $force_default_post_type = false ): array {
        $post_type = $query_args["post_type"] ?? "";

        if ( "" === $post_type || null === $post_type ) {
            if ( $force_default_post_type ) {
                $query_args["post_type"] = "post";
            }

            return $query_args;
        }

        if ( "any" === $post_type ) {
            $query_args["post_type"] = self::public_post_types();
            return $query_args;
        }

        $post_types = is_array( $post_type ) ? array_values( $post_type ) : [ $post_type ];
        if ( ! in_array( self::POST_TYPE, $post_types, true ) ) {
            return $query_args;
        }

        $post_types = array_values( array_diff( $post_types, [ self::POST_TYPE ] ) );
        if ( [] === $post_types ) {
            $query_args["post__in"] = [ 0 ];
            return $query_args;
        }

        $query_args["post_type"] = is_array( $post_type ) ? $post_types : $post_types[0];

        return $query_args;
    }

    public static function public_post_types(): array {
        $post_types = get_post_types( [ "public" => true ], "names" );
        $post_types = is_array( $post_types )
            ? array_values( array_diff( $post_types, [ self::POST_TYPE ] ) )
            : [];

        return [] !== $post_types ? $post_types : [ "post" ];
    }

    private static function exclude_from_query( WP_Query $query ): void {
        $query->set( "hpr_force_hide_press_release", true );

        $original = $query->query_vars;
        $filtered = self::exclude_from_args( $original, true );

        foreach ( [ "post_type", "post__in" ] as $key ) {
            if (
                array_key_exists( $key, $filtered )
                && ( ! array_key_exists( $key, $original ) || $filtered[ $key ] !== $original[ $key ] )
            ) {
                $query->set( $key, $filtered[ $key ] );
            }
        }
    }

    private static function option_enabled( string $option ): bool {
        $value = get_option( $option, self::MISSING_OPTION );

        if ( self::MISSING_OPTION === $value ) {
            return true;
        }

        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    private static function is_frontend_request(): bool {
        if ( is_admin() ) {
            return false;
        }

        if ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) {
            return false;
        }

        if ( defined( "REST_REQUEST" ) && REST_REQUEST ) {
            return false;
        }

        return ! function_exists( "wp_is_json_request" ) || ! wp_is_json_request();
    }
}
