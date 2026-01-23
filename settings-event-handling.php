<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - Event Handling (AJAX)
 * 
 * Registers all AJAX handlers for the dashboard:
 * - Snippet toggle
 * - WP-Config modifications
 * - Function execution
 * - Plugin updates
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register AJAX actions
add_action( 'wp_ajax_hpr_distributor_toggle_snippet', __NAMESPACE__ . '\\ajax_toggle_snippet' );
add_action( 'wp_ajax_hpr_distributor_modify_wp_config', __NAMESPACE__ . '\\ajax_modify_wp_config_constants' );
add_action( 'wp_ajax_hpr_distributor_execute_function', __NAMESPACE__ . '\\ajax_execute_function' );

// Plugin info AJAX handlers
add_action( 'wp_ajax_hpr_download_plugin_zip', __NAMESPACE__ . '\\ajax_download_plugin_zip' );
add_action( 'wp_ajax_hpr_force_update_check', __NAMESPACE__ . '\\ajax_force_update_check' );
add_action( 'wp_ajax_hpr_direct_update_plugin', __NAMESPACE__ . '\\ajax_direct_update_plugin' );
add_action( 'wp_ajax_hpr_load_github_versions', __NAMESPACE__ . '\\ajax_load_github_versions' );
add_action( 'wp_ajax_hpr_download_specific_version', __NAMESPACE__ . '\\ajax_download_specific_version' );

// Dashboard action handlers
add_action( 'wp_ajax_hpr_create_user', __NAMESPACE__ . '\\ajax_create_user' );
add_action( 'wp_ajax_hpr_create_category', __NAMESPACE__ . '\\ajax_create_category' );
add_action( 'wp_ajax_hpr_schedule_cron', __NAMESPACE__ . '\\ajax_schedule_cron' );
add_action( 'wp_ajax_hpr_run_purge_now', __NAMESPACE__ . '\\ajax_run_purge_now' );

/**
 * AJAX: Create hexaprwire user
 */
function ajax_create_user() {
    if ( ! current_user_can( 'create_users' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    // Check if user already exists
    $existing = get_user_by( 'slug', 'hexaprwire' );
    if ( $existing ) {
        wp_send_json_error( 'User already exists' );
        return;
    }
    
    // Create user
    $user_data = [
        'user_login'   => 'hexaprwire',
        'user_pass'    => wp_generate_password( 24 ),
        'user_email'   => 'hexaprwire@' . parse_url( get_site_url(), PHP_URL_HOST ),
        'display_name' => 'Hexa PR Wire',
        'role'         => 'author',
    ];
    
    $user_id = wp_insert_user( $user_data );
    
    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( $user_id->get_error_message() );
        return;
    }
    
    wp_send_json_success([
        'message' => 'User created successfully',
        'user_id' => $user_id,
    ]);
}

/**
 * AJAX: Create press-release category
 */
function ajax_create_category() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    // Check if category already exists
    $existing = get_term_by( 'slug', 'press-release', 'category' );
    if ( $existing ) {
        wp_send_json_error( 'Category already exists' );
        return;
    }
    
    // Create category
    $result = wp_insert_term( 'Press Release', 'category', [
        'slug'        => 'press-release',
        'description' => 'Press releases from Hexa PR Wire',
    ]);
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
        return;
    }
    
    wp_send_json_success([
        'message' => 'Category created successfully',
        'term_id' => $result['term_id'],
    ]);
}

/**
 * AJAX: Schedule a cron job
 */
function ajax_schedule_cron() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $hook = isset( $_POST['hook'] ) ? sanitize_text_field( $_POST['hook'] ) : '';
    
    $allowed_hooks = [
        'hexaprwire_daily_purge_check',
        'hexaprwire_process_deletes',
    ];
    
    if ( ! in_array( $hook, $allowed_hooks ) ) {
        wp_send_json_error( 'Invalid cron hook' );
        return;
    }
    
    // Clear existing schedule
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
    
    // Schedule new event
    $scheduled = wp_schedule_event( time(), 'daily', $hook );
    
    if ( $scheduled === false ) {
        wp_send_json_error( 'Failed to schedule cron' );
        return;
    }
    
    wp_send_json_success([
        'message'  => 'Cron scheduled successfully',
        'next_run' => date( 'Y-m-d H:i:s', wp_next_scheduled( $hook ) ),
    ]);
}

/**
 * AJAX: Run purge check now
 */
function ajax_run_purge_now() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    // Try to run the purge function if it exists
    if ( function_exists( __NAMESPACE__ . '\\process_hexa_pr_wire_deletes' ) ) {
        $result = process_hexa_pr_wire_deletes();
        wp_send_json_success([
            'message' => 'Purge check completed',
            'result'  => $result,
        ]);
    } elseif ( function_exists( __NAMESPACE__ . '\\hexaprwire_process_deletes' ) ) {
        $result = hexaprwire_process_deletes();
        wp_send_json_success([
            'message' => 'Purge check completed',
            'result'  => $result,
        ]);
    } else {
        // Trigger the action
        do_action( 'hexaprwire_process_deletes' );
        wp_send_json_success([
            'message' => 'Purge action triggered',
        ]);
    }
}

/**
 * AJAX: Toggle a snippet on/off
 */
function ajax_toggle_snippet() {
    // Verify capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $snippet_id = isset( $_POST['snippet_id'] ) ? sanitize_text_field( $_POST['snippet_id'] ) : '';
    $enable = isset( $_POST['enable'] ) ? (bool) intval( $_POST['enable'] ) : false;
    
    if ( empty( $snippet_id ) ) {
        wp_send_json_error( 'Missing snippet ID' );
        return;
    }
    
    // Update the option
    update_option( $snippet_id, $enable );
    
    $status = $enable ? 'enabled' : 'disabled';
    wp_send_json_success( "Snippet '{$snippet_id}' has been {$status}. Refresh the page to apply changes." );
}

/**
 * AJAX: Modify wp-config.php constants
 */
function ajax_modify_wp_config_constants() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        return;
    }
    
    $constants = isset( $_POST['constants'] ) ? $_POST['constants'] : [];
    
    if ( empty( $constants ) || ! is_array( $constants ) ) {
        wp_send_json_error( [ 'message' => 'No constants provided' ] );
        return;
    }
    
    // Sanitize constants
    $sanitized = [];
    foreach ( $constants as $key => $value ) {
        $sanitized[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
    }
    
    // Try to modify wp-config.php
    if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
        $result = modify_wp_config_constants( $sanitized );
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Configuration updated successfully' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to update wp-config.php' ] );
        }
    } else {
        wp_send_json_error( [ 'message' => 'modify_wp_config_constants function not found' ] );
    }
}

/**
 * AJAX: Execute a function
 */
function ajax_execute_function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $function_name = isset( $_POST['function_name'] ) ? sanitize_text_field( $_POST['function_name'] ) : '';
    
    if ( empty( $function_name ) ) {
        wp_send_json_error( 'No function specified' );
        return;
    }
    
    // Namespace the function
    $full_function = __NAMESPACE__ . '\\' . $function_name;
    
    if ( function_exists( $full_function ) ) {
        $result = call_user_func( $full_function );
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( "Function '{$function_name}' not found" );
    }
}

/**
 * AJAX: Load available versions (tags) from GitHub
 */
function ajax_load_github_versions() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $github_repo = Config::$github_repo;
    
    $tags_url = 'https://api.github.com/repos/' . $github_repo . '/tags';
    $response = wp_remote_get( $tags_url, [
        'timeout' => 15,
        'headers' => [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        ],
    ]);
    
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Failed to fetch versions: ' . $response->get_error_message() );
        return;
    }
    
    $body = wp_remote_retrieve_body( $response );
    $tags = json_decode( $body, true );
    
    if ( ! is_array( $tags ) ) {
        wp_send_json_error( 'Invalid response from GitHub' );
        return;
    }
    
    $versions = [];
    foreach ( $tags as $tag ) {
        if ( isset( $tag['name'] ) ) {
            $versions[] = [
                'name'        => $tag['name'],
                'zipball_url' => $tag['zipball_url'],
            ];
        }
    }
    
    // Add main branch option
    array_unshift( $versions, [
        'name'        => Config::$github_branch . ' (latest)',
        'zipball_url' => 'https://github.com/' . $github_repo . '/archive/' . Config::$github_branch . '.zip',
    ]);
    
    wp_send_json_success( $versions );
}

/**
 * AJAX: Force update check
 */
function ajax_force_update_check() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    // Clear transients
    $slug = Config::get_plugin_basename();
    delete_site_transient( 'hpr_gu_version_' . md5( $slug ) );
    delete_site_transient( 'hpr_gu_repo_' . md5( $slug ) );
    delete_site_transient( 'update_plugins' );
    
    // Force update check
    wp_clean_update_cache();
    wp_update_plugins();
    
    // Get fresh version
    $github_version = hpr_get_github_version_fresh();
    $plugin_data = hpr_get_plugin_data();
    
    wp_send_json_success([
        'message'          => 'Update check completed',
        'current_version'  => $plugin_data['Version'],
        'github_version'   => $github_version,
        'update_available' => version_compare( $github_version, $plugin_data['Version'], '>' ),
    ]);
}

/**
 * AJAX: Direct update from GitHub
 */
function ajax_direct_update_plugin() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $zip_url = 'https://github.com/' . Config::$github_repo . '/archive/' . Config::$github_branch . '.zip';
    $tmp_file = download_url( $zip_url, 300 );
    
    if ( is_wp_error( $tmp_file ) ) {
        wp_send_json_error( 'Failed to download: ' . $tmp_file->get_error_message() );
        return;
    }
    
    $plugin_folder = Config::$plugin_folder_name;
    $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_folder;
    $backup_dir = WP_PLUGIN_DIR . '/' . $plugin_folder . '-backup-' . time();
    $temp_dir = WP_PLUGIN_DIR . '/hpr-update-temp-' . uniqid();
    
    wp_mkdir_p( $temp_dir );
    
    $unzip_result = unzip_file( $tmp_file, $temp_dir );
    @unlink( $tmp_file );
    
    if ( is_wp_error( $unzip_result ) ) {
        hpr_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to extract: ' . $unzip_result->get_error_message() );
        return;
    }
    
    $extracted_folders = glob( $temp_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        hpr_delete_directory( $temp_dir );
        wp_send_json_error( 'No folder found in archive' );
        return;
    }
    
    $extracted_folder = $extracted_folders[0];
    
    // Backup current plugin
    if ( is_dir( $plugin_dir ) ) {
        rename( $plugin_dir, $backup_dir );
    }
    
    // Move extracted folder
    $move_result = rename( $extracted_folder, $plugin_dir );
    
    if ( ! $move_result ) {
        if ( is_dir( $backup_dir ) ) {
            rename( $backup_dir, $plugin_dir );
        }
        hpr_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to install update' );
        return;
    }
    
    // Clean up
    hpr_delete_directory( $temp_dir );
    hpr_delete_directory( $backup_dir );
    
    // Reactivate plugin
    $plugin_file = Config::get_plugin_basename();
    activate_plugin( $plugin_file );
    
    delete_site_transient( 'update_plugins' );
    
    wp_send_json_success([
        'message' => 'Plugin updated successfully',
        'reload'  => true,
    ]);
}

/**
 * AJAX: Download current plugin as zip
 */
function ajax_download_plugin_zip() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $plugin_folder = Config::$plugin_folder_name;
    $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_folder;
    $plugin_data = hpr_get_plugin_data();
    $version = $plugin_data['Version'];
    
    if ( ! is_dir( $plugin_dir ) ) {
        wp_send_json_error( 'Plugin directory not found' );
        return;
    }
    
    $upload_dir = wp_upload_dir();
    $zip_filename = $plugin_folder . '-v' . $version . '.zip';
    $zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
    
    if ( file_exists( $zip_path ) ) {
        @unlink( $zip_path );
    }
    
    $zip = new \ZipArchive();
    if ( $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
        wp_send_json_error( 'Failed to create zip file' );
        return;
    }
    
    hpr_add_folder_to_zip( $plugin_dir, $zip, $plugin_folder );
    $zip->close();
    
    $download_url = $upload_dir['baseurl'] . '/' . $zip_filename;
    
    wp_send_json_success([
        'download_url' => $download_url,
        'filename'     => $zip_filename,
    ]);
}

/**
 * AJAX: Download specific version
 */
function ajax_download_specific_version() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
    $zip_url = isset( $_POST['zip_url'] ) ? esc_url_raw( $_POST['zip_url'] ) : '';
    
    if ( empty( $version ) || empty( $zip_url ) ) {
        wp_send_json_error( 'Missing version or URL' );
        return;
    }
    
    $tmp_file = download_url( $zip_url, 300 );
    
    if ( is_wp_error( $tmp_file ) ) {
        wp_send_json_error( 'Failed to download: ' . $tmp_file->get_error_message() );
        return;
    }
    
    $upload_dir = wp_upload_dir();
    $plugin_folder = Config::$plugin_folder_name;
    $clean_zip_path = $upload_dir['basedir'] . '/' . $plugin_folder . '-' . sanitize_file_name( $version ) . '.zip';
    
    $temp_dir = $upload_dir['basedir'] . '/hpr-temp-' . uniqid();
    wp_mkdir_p( $temp_dir );
    
    $unzip_result = unzip_file( $tmp_file, $temp_dir );
    @unlink( $tmp_file );
    
    if ( is_wp_error( $unzip_result ) ) {
        hpr_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to extract: ' . $unzip_result->get_error_message() );
        return;
    }
    
    $extracted_folders = glob( $temp_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        hpr_delete_directory( $temp_dir );
        wp_send_json_error( 'No folder found in archive' );
        return;
    }
    
    $extracted_folder = $extracted_folders[0];
    $correct_folder = $temp_dir . '/' . $plugin_folder;
    rename( $extracted_folder, $correct_folder );
    
    $zip = new \ZipArchive();
    if ( $zip->open( $clean_zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
        hpr_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to create zip file' );
        return;
    }
    
    hpr_add_folder_to_zip( $correct_folder, $zip, $plugin_folder );
    $zip->close();
    
    hpr_delete_directory( $temp_dir );
    
    $download_url = $upload_dir['baseurl'] . '/' . basename( $clean_zip_path );
    
    wp_send_json_success([
        'download_url' => $download_url,
        'filename'     => basename( $clean_zip_path ),
    ]);
}

/**
 * Helper: Get GitHub version without cache
 */
function hpr_get_github_version_fresh() {
    $raw_url = 'https://raw.githubusercontent.com/' . Config::$github_repo . '/' . Config::$github_branch . '/' . Config::$plugin_starter_file;
    
    $response = wp_remote_get( $raw_url, [
        'timeout'   => 15,
        'sslverify' => true,
    ]);
    
    if ( is_wp_error( $response ) ) {
        return 'Error';
    }
    
    $body = wp_remote_retrieve_body( $response );
    
    if ( preg_match( '/^[\s\*]*Version:\s*(.+)$/mi', $body, $matches ) ) {
        return trim( $matches[1] );
    }
    
    return 'Unknown';
}

/**
 * Helper: Get plugin data
 */
function hpr_get_plugin_data() {
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugin_file = WP_PLUGIN_DIR . '/' . Config::$plugin_folder_name . '/' . Config::$plugin_starter_file;
    return get_plugin_data( $plugin_file );
}

/**
 * Helper: Add folder to zip
 */
function hpr_add_folder_to_zip( $folder, $zip, $base_folder ) {
    $handle = opendir( $folder );
    while ( false !== ( $entry = readdir( $handle ) ) ) {
        if ( $entry === '.' || $entry === '..' || $entry === '.git' ) {
            continue;
        }
        
        $full_path = $folder . '/' . $entry;
        $zip_path = $base_folder . '/' . $entry;
        
        if ( is_dir( $full_path ) ) {
            $zip->addEmptyDir( $zip_path );
            hpr_add_folder_to_zip( $full_path, $zip, $zip_path );
        } else {
            $zip->addFile( $full_path, $zip_path );
        }
    }
    closedir( $handle );
}

/**
 * Helper: Delete directory recursively
 */
function hpr_delete_directory( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    
    $items = scandir( $dir );
    foreach ( $items as $item ) {
        if ( $item === '.' || $item === '..' ) {
            continue;
        }
        
        $path = $dir . '/' . $item;
        if ( is_dir( $path ) ) {
            hpr_delete_directory( $path );
        } else {
            @unlink( $path );
        }
    }
    @rmdir( $dir );
}
