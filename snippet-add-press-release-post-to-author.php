<?php
namespace hpr_distributor;

/**
 * Include 'press-release' CPT in front end author archives only.
 */
function add_press_release_to_author_archives( \WP_Query $query ) {

    // Only touch the main front-end query.
    if ( ! $query->is_main_query() ) {
        return;
    }

    // Never run in admin, including admin-ajax.
    if ( \is_admin() ) {
        return;
    }

    // Extra guard: do not affect REST or AJAX requests at all.
    if ( ( \function_exists( 'wp_doing_ajax' ) && \wp_doing_ajax() )
        || ( \defined( 'REST_REQUEST' ) && REST_REQUEST )
    ) {
        return;
    }

    // Only author archives.
    if ( ! $query->is_author() ) {
        return;
    }

    // Do not touch feeds.
    if ( $query->is_feed() ) {
        return;
    }

    // Only extend the default "post" query.
    $post_type = $query->get( 'post_type' );

    $is_default_post_query =
        empty( $post_type ) ||
        $post_type === 'post' ||
        ( \is_array( $post_type ) && $post_type === array( 'post' ) );

    if ( ! $is_default_post_query ) {
        // Someone explicitly set post_type – leave it alone.
        return;
    }

    // Finally: add 'press-release' alongside 'post'.
    $query->set( 'post_type', array( 'post', 'press-release' ) );
}

// Register the pre_get_posts callback (namespaced).
\add_action(
    'pre_get_posts',
    __NAMESPACE__ . '\\add_press_release_to_author_archives',
    20
);
