<?php

namespace hpr_distributor;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hpr_distributor_toggle_snippet', __NAMESPACE__ . '\\ajax_toggle_snippet' );
add_action( 'wp_ajax_hpr_create_user', [ \hpr_distributor\Setup\HexaPrWireAuthor::class, 'ajax_provision' ] );
add_action( 'wp_ajax_hpr_create_category', __NAMESPACE__ . '\\ajax_create_category' );

function ajax_create_category(): void {
    guard_ajax_request( 'manage_categories' );
    $existing = get_term_by( 'slug', 'press-release', 'category' );
    if ( $existing ) {
        wp_send_json_error( 'Category already exists' );
    }

    $result = wp_insert_term(
        'Press Release',
        'category',
        [
            'slug'        => 'press-release',
            'description' => 'Press releases from Hexa PR Wire',
        ]
    );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    wp_send_json_success( [ 'message' => 'Category created successfully', 'term_id' => $result['term_id'] ] );
}

function ajax_toggle_snippet(): void {
    guard_ajax_request( 'manage_options' );
    $snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_key( (string) wp_unslash( $_POST['snippet_id'] ) ) : '';
    $enable = ! empty( $_POST['enable'] );
    $allowed_snippets = array_column( get_settings_snippets(), 'id' );
    if ( '' === $snippet_id || ! in_array( $snippet_id, $allowed_snippets, true ) ) {
        wp_send_json_error( 'Invalid snippet ID.', 400 );
    }
    update_option( $snippet_id, $enable, false );
    wp_send_json_success( "Snippet '{$snippet_id}' has been " . ( $enable ? 'enabled' : 'disabled' ) . '. Refresh the page to apply changes.' );
}
