<?php
namespace hpr_distributor;

/**
 * Bootstrap: called by hexa-pr-wire-distributor snippet loader with NO arguments.
 * It just hooks the real pre_get_posts callback.
 */
function add_press_release_to_category_archives() {
    // Confirm bootstrap runs.
    write_log( "HPR bootstrap: add_press_release_to_category_archives()", true );

    \add_action(
        'pre_get_posts',
        __NAMESPACE__ . '\\add_press_release_to_category_archives_query',
        999 // very late so we override others
    );
}

/**
 * Actual pre_get_posts callback. WordPress passes \WP_Query $query here.
 */
function add_press_release_to_category_archives_query( \WP_Query $query ) {

    // Log every time pre_get_posts fires on front end.
    write_log(
        "HPR pre_get_posts: is_main_query=" . ( $query->is_main_query() ? 'yes' : 'no' ) .
        " is_category=" . ( $query->is_category() ? 'yes' : 'no' ) .
        " post_type=" . print_r( $query->get( 'post_type' ), true )
    );

    // Never run in admin, including admin-ajax.
    if ( \is_admin() ) {
        return;
    }

    // Extra guard: do not affect REST or AJAX requests.
    if ( ( \function_exists( 'wp_doing_ajax' ) && \wp_doing_ajax() )
        || ( \defined( 'REST_REQUEST' ) && REST_REQUEST )
    ) {
        return;
    }

    // Only category archives.
    if ( ! $query->is_category() ) {
        return;
    }

    // Do not touch feeds.
    if ( $query->is_feed() ) {
        return;
    }

    // Only extend queries that are currently just for 'post' (or default).
    $post_type = $query->get( 'post_type' );

    $is_default_post_query =
        empty( $post_type ) ||
        $post_type === 'post' ||
        ( \is_array( $post_type ) && $post_type === array( 'post' ) );

    if ( ! $is_default_post_query ) {
        // Some other plugin or template set a custom post_type, leave it alone.
        return;
    }

    write_log( "HPR pre_get_posts: MODIFYING query on category archive", true );

    // Force both types in this category archive.
    $query->set( 'post_type', array( 'post', 'press-release' ) );

    write_log(
        "HPR pre_get_posts: post_type AFTER = " .
        print_r( $query->get( 'post_type' ), true ),
        true
    );
}
