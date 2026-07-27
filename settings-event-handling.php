<?php

namespace hpr_distributor;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hpr_distributor_toggle_snippet', __NAMESPACE__ . '\\ajax_toggle_snippet' );
add_action( 'wp_ajax_hpr_create_user', [ \hpr_distributor\Setup\HexaPrWireAuthor::class, 'ajax_provision' ] );
add_action( 'wp_ajax_hpr_create_category', __NAMESPACE__ . '\\ajax_create_category' );
add_action( 'wp_ajax_hpr_schedule_cron', __NAMESPACE__ . '\\ajax_schedule_cron' );
add_action( 'wp_ajax_hpr_run_purge_now', __NAMESPACE__ . '\\ajax_run_purge_now' );

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

function ajax_schedule_cron(): void {
    guard_ajax_request( 'manage_options' );
    $hook = isset( $_POST['hook'] ) ? sanitize_key( (string) wp_unslash( $_POST['hook'] ) ) : '';
    $allowed_hooks = [ 'hexaprwire_daily_purge_check', 'hexaprwire_process_deletes' ];
    if ( ! in_array( $hook, $allowed_hooks, true ) ) {
        wp_send_json_error( 'Invalid cron hook' );
    }

    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
    if ( false === wp_schedule_event( time(), 'daily', $hook ) ) {
        wp_send_json_error( 'Failed to schedule cron' );
    }
    wp_send_json_success( [ 'message' => 'Cron scheduled successfully', 'next_run' => wp_date( 'Y-m-d H:i:s', (int) wp_next_scheduled( $hook ) ) ] );
}

function ajax_run_purge_now(): void {
    guard_ajax_request( 'manage_options' );
    if ( function_exists( __NAMESPACE__ . '\\process_hexa_pr_wire_deletes' ) ) {
        wp_send_json_success( [ 'message' => 'Purge check completed', 'result' => process_hexa_pr_wire_deletes() ] );
    }
    if ( function_exists( __NAMESPACE__ . '\\hexaprwire_process_deletes' ) ) {
        wp_send_json_success( [ 'message' => 'Purge check completed', 'result' => hexaprwire_process_deletes() ] );
    }
    do_action( 'hexaprwire_process_deletes' );
    wp_send_json_success( [ 'message' => 'Purge action triggered' ] );
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
