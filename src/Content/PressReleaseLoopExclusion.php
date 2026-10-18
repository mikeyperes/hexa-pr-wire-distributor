<?php

namespace hpr_distributor\Content;

use WP_Query;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Where press releases show in the site's ordinary loops. Each context has one
 * switch (stored as its `hide_press_release_from_*` option; DEFAULTS applies
 * until a site saves its own choice): the home page hides them by default,
 * category, tag and author pages show them. A shown context adds press
 * releases to that page's main query; a hidden one removes them from every
 * loop on the page (through each query's post types, so it holds even for
 * loops that skip SQL filters). PLACED_META records the category Hexa PR Wire
 * placed a release in; with category pages shown it appears there.
 */
final class PressReleaseLoopExclusion {
    public const POST_TYPE = "press-release";
    public const PLACED_META = "_hpr_outlet_category";

    private const FILTER_PRIORITY = PHP_INT_MAX - 10;
    private const ELEMENTOR_PRIORITY = 999;
    private const ELEMENTOR_ARCHIVE_QUERY_ID = "hpr_press_release_archive";
    private const ALLOW_QUERY_VAR = "hpr_allow_press_release_loop";
    private const MISSING_OPTION = "__hpr_missing_loop_option__";

    /** The "Show press releases on" switches: hide option => [label, note]. */
    public const VISIBILITY = [
        "hide_press_release_from_home_loop"     => [ "Home page", "The latest-posts list on the home page or posts page. Off by default." ],
        "hide_press_release_from_category_loop" => [ "Category pages", "Press releases appear in the categories they are in. On by default." ],
        "hide_press_release_from_author_loop"   => [ "Author pages", "Press releases appear on their author's page. On by default." ],
        "hide_press_release_from_tag_loop"      => [ "Tag pages", "Press releases appear on their tags' pages. On by default." ],
    ];

    /** Whether press releases show in this context (the inverse of its hide option). */
    public static function shown( string $hide_option ): bool {
        return ! self::option_enabled( $hide_option );
    }

    /** true = hidden in that context until the site saves its own choice. */
    public const DEFAULTS = [
        "hide_press_release_from_home_loop"           => true,
        "hide_press_release_from_category_loop"       => false,
        "hide_press_release_from_author_loop"         => false,
        "hide_press_release_from_tag_loop"            => false,
        "hide_press_release_from_related_single_loop" => true,
    ];

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
        add_action( "elementor/query/" . self::ELEMENTOR_ARCHIVE_QUERY_ID, [ self::class, "allow_elementor_press_release_archive" ], 10, 2 );
        self::$registered = true;
    }

    public static function filter_query( WP_Query $query ): void {
        if ( self::is_shown_archive( $query ) ) {
            // An unset post type on a category or tag page already lists every
            // public type in that taxonomy, press releases included.
            $unset = in_array( $query->get( "post_type" ), [ "", null ], true );
            if ( ! ( $unset && ( $query->is_category() || $query->is_tag() ) ) ) {
                $query->set( "post_type", self::with_press_release( $query->get( "post_type" ) ) );
            }
            return;
        }

        if ( self::is_frontend_request() && self::is_press_release_only_query( $query ) ) {
            $query->set( "ignore_sticky_posts", true );
            return;
        }

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

    /**
     * The page's own main query, for a home, category, tag or author page whose
     * switch shows press releases, listing ordinary posts (alone or with other
     * types a theme added) but not press releases yet.
     */
    private static function is_shown_archive( WP_Query $query ): bool {
        if ( ! self::is_frontend_request() || ! $query->is_main_query() || $query->is_feed() || $query->is_search() || $query->is_preview() || $query->is_singular() ) {
            return false;
        }
        $post_type = $query->get( "post_type" );
        if ( "" !== $post_type && null !== $post_type ) {
            $types = (array) $post_type;
            if ( ! in_array( "post", $types, true ) || in_array( self::POST_TYPE, $types, true ) ) {
                return false;
            }
        }
        if ( $query->is_home() ) {
            return ! self::option_enabled( "hide_press_release_from_home_loop" );
        }
        if ( $query->is_category() ) {
            return ! self::option_enabled( "hide_press_release_from_category_loop" );
        }
        if ( $query->is_tag() ) {
            return ! self::option_enabled( "hide_press_release_from_tag_loop" );
        }
        if ( $query->is_author() ) {
            return ! self::option_enabled( "hide_press_release_from_author_loop" );
        }
        return false;
    }

    /** @return array<int,string> The query's post types plus press releases. */
    private static function with_press_release( mixed $post_type ): array {
        if ( "" === $post_type || null === $post_type || "any" === $post_type ) {
            $types = [ "post" ];
        } else {
            $types = array_values( array_filter( (array) $post_type, "is_string" ) );
        }
        $types[] = self::POST_TYPE;
        return array_values( array_unique( $types ) );
    }

    public static function allow_elementor_press_release_archive( WP_Query $query, $widget = null ): void {
        unset( $widget );

        $post_type = $query->get( "post_type" );
        $post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
        if ( ! in_array( self::POST_TYPE, $post_types, true ) ) {
            return;
        }

        $query->set( self::ALLOW_QUERY_VAR, true );
        $query->set( "hpr_force_hide_press_release", false );
        $query->set( "ignore_sticky_posts", true );

        if ( [ 0 ] === $query->get( "post__in" ) ) {
            $query->set( "post__in", [] );
        }
    }

    public static function matches_enabled_context( ?WP_Query $query = null ): bool {
        if ( ! self::is_frontend_request() ) {
            return false;
        }

        if ( $query instanceof WP_Query ) {
            if ( $query->get( self::ALLOW_QUERY_VAR ) ) {
                return false;
            }

            if ( $query->is_feed() || $query->is_search() || $query->is_preview() ) {
                return false;
            }

            if ( $query->is_main_query() && $query->is_singular() ) {
                return false;
            }

            if ( $query->get( "hpr_force_hide_press_release" ) ) {
                return true;
            }

            // A dedicated CPT query is intentional, even if WordPress marks the
            // secondary query as home before Elementor resolves its controls.
            if ( self::is_press_release_only_query( $query ) ) {
                return false;
            }
        }

        if ( is_singular( self::POST_TYPE ) ) {
            return false;
        }

        if (
            self::option_enabled( "hide_press_release_from_home_loop" )
            && (
                is_front_page()
                || is_home()
                || ( $query instanceof WP_Query && $query->is_main_query() && $query->is_home() )
            )
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

    private static function is_press_release_only_query( WP_Query $query ): bool {
        $post_type = $query->get( "post_type" );
        $post_types = is_array( $post_type ) ? array_values( $post_type ) : [ $post_type ];
        $post_types = array_values( array_filter( $post_types, "is_string" ) );

        return [] !== $post_types
            && [] === array_diff( $post_types, [ self::POST_TYPE ] );
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
            return self::DEFAULTS[ $option ] ?? true;
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
