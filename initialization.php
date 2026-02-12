<?php
/**
 * Plugin Name: Hexa PR Wire - Distributor
 * Description: Press release distribution and management for Hexa PR Wire network.
 * Author: Michael Peres
 * Plugin URI: https://github.com/mikeyperes/hexa-pr-wire-distributor
 * Version: 2.1
 * Author URI: https://michaelperes.com
 * GitHub Plugin URI: https://github.com/mikeyperes/hexa-pr-wire-distributor/
 * GitHub Branch: main
 */
namespace hpr_distributor;

// Guard: don't bootstrap during Elementor's internal AJAX
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
    $ajax_action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
    if ( $ajax_action === 'elementor_ajax' ) {
        return;
    }
}

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Plugin Configuration Class
 * All configurable values in one place for easy customization
 */
class Config {
    // Plugin Identity
    public static $plugin_name           = 'Hexa PR Wire - Distributor';
    public static $plugin_version        = '2.1';
    public static $plugin_slug           = 'hpr-distributor';
    public static $plugin_folder_name    = 'hexa-pr-wire-distributor';
    public static $plugin_starter_file   = 'initialization.php';
    
    // Settings Page
    public static $settings_page_name         = 'Hexa PR Wire - Settings';
    public static $settings_page_capability   = 'manage_options';
    public static $settings_page_slug         = 'hpr-distributor';
    public static $settings_page_display_title = 'Hexa PR Wire - Settings';
    
    // GitHub Repository
    public static $github_repo   = 'mikeyperes/hexa-pr-wire-distributor';
    public static $github_branch = 'main';
    
    // AJAX Nonce
    const AJAX_NONCE = 'hpr_ajax_nonce';
    
    /**
     * Get plugin basename
     */
    public static function get_plugin_basename() {
        return self::$plugin_folder_name . '/' . self::$plugin_starter_file;
    }
    
    /**
     * Get plugin directory path
     */
    public static function get_plugin_dir() {
        return WP_PLUGIN_DIR . '/' . self::$plugin_folder_name;
    }
    
    /**
     * Get GitHub config array (for updater compatibility)
     */
    public static function get_github_config() {
        return [
            'slug'               => plugin_basename( __FILE__ ),
            'proper_folder_name' => self::$plugin_folder_name,
            'api_url'            => 'https://api.github.com/repos/' . self::$github_repo,
            'raw_url'            => 'https://raw.github.com/' . self::$github_repo . '/' . self::$github_branch,
            'github_url'         => 'https://github.com/' . self::$github_repo,
            'zip_url'            => 'https://github.com/' . self::$github_repo . '/archive/' . self::$github_branch . '.zip',
            'sslverify'          => true,
            'requires'           => '5.0',
            'tested'             => '6.4',
            'readme'             => 'README.md',
            'access_token'       => '',
        ];
    }
}

// Include core files
include_once 'generic-functions.php';
include_once 'GitHub_Updater.php';

// Initialize GitHub Updater
if ( is_admin() ) {
    $updater = new WP_GitHub_Updater( Config::get_github_config() );
    
    // Force update check handler
    add_action( 'init', function() {
        if ( is_admin() && isset( $_GET['force-update-check'] ) ) {
            wp_clean_update_cache();
            set_site_transient( 'update_plugins', null );
            wp_update_plugins();
        }
    });
}

// Check for ACF dependency
$plugins_to_check = [
    'advanced-custom-fields-pro/acf.php',
    'advanced-custom-fields-pro-temp/acf.php',
];

$acf_active = false;
foreach ( $plugins_to_check as $plugin ) {
    list( $installed, $active ) = check_plugin_status( $plugin );
    if ( $active ) {
        $acf_active = true;
        break;
    }
}

if ( ! $acf_active ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>' . esc_html( Config::$plugin_name ) . '</strong>: Advanced Custom Fields Pro is required. Please activate ACF Pro.</p></div>';
    });
    return;
}

/**
 * Get all available snippets
 * Returns array of snippet configurations
 */
function get_settings_snippets() {
    $snippets = [
        [
            'id'          => 'register_user_custom_fields',
            'name'        => 'Enable Author Social ACFs',
            'description' => 'Enable social media fields in author profiles.',
            'function'    => 'register_user_custom_fields',
            'category'    => 'acf',
        ],
        [
            'id'          => 'add_press_release_to_category_archives',
            'name'        => 'Add Press Release to Category Archives',
            'description' => 'Include press-release post type in category archive pages.',
            'function'    => 'add_press_release_to_category_archives',
            'category'    => 'display',
        ],
        [
            'id'          => 'add_press_release_to_author_page',
            'name'        => 'Add Press Release to Author Page',
            'description' => 'Include press-release post type on author archive pages.',
            'function'    => 'add_press_release_to_author_page',
            'category'    => 'display',
        ],
        [
            'id'          => 'enable_hpr_auto_deletes',
            'name'        => 'Enable Auto Delete Functionality',
            'description' => 'Automatically delete press releases based on Hexa PR Wire purge list.',
            'function'    => 'enable_hpr_auto_deletes',
            'category'    => 'automation',
        ],
        [
            'id'          => 'enable_press_release_category_on_new_post',
            'name'        => 'Enable Press Release Category on New Post',
            'description' => 'Automatically assign press-release category to new posts.',
            'function'    => 'enable_press_release_category_on_new_post',
            'category'    => 'automation',
        ],
        [
            'id'          => 'register_press_release_post_type',
            'name'        => 'Enable Press Release Post Type',
            'description' => 'Register the press-release custom post type.',
            'function'    => 'register_press_release_post_type',
            'category'    => 'core',
        ],
        [
            'id'          => 'register_press_release_custom_fields',
            'name'        => 'Enable Press Release Custom Fields',
            'description' => 'Register ACF fields for press releases.',
            'function'    => 'register_press_release_custom_fields',
            'category'    => 'acf',
        ],
        [
            'id'          => 'disable_rss_caching',
            'name'        => 'Disable RSS Feed Caching',
            'description' => 'Disable LiteSpeed and WordPress caching on RSS feeds to ensure fresh data.',
            'function'    => 'disable_rss_caching',
            'category'    => 'performance',
        ],
    ];
    
    return $snippets;
}

/**
 * Initialize plugin on ACF ready
 */
add_action( 'acf/init', function() {
    // Set default options on first run
    if ( get_option( 'hpr_defaults_set' ) !== 'yes' ) {
        // Enable RSS caching disable by default
        update_option( 'disable_rss_caching', true );
        // SEO defaults
        update_option( 'hpr_seo_follow_status', 'dofollow' );
        update_option( 'hpr_seo_sitemap_status', 'include' );
        update_option( 'hpr_defaults_set', 'yes' );
    }
    
    // Ensure SEO defaults exist (for upgrades from < 2.1)
    if ( get_option( 'hpr_seo_follow_status' ) === false ) {
        update_option( 'hpr_seo_follow_status', 'dofollow' );
    }
    if ( get_option( 'hpr_seo_sitemap_status' ) === false ) {
        update_option( 'hpr_seo_sitemap_status', 'include' );
    }
    
    // Register ACF Fields
    include_once 'register-acf-press-release.php';
    include_once 'register-acf-user.php';
    include_once 'register-acf-seo-fields.php';
    
    // Snippets
    include_once 'snippet-add-press-release-post-to-author.php';
    include_once 'snippet-add-press-release-to-archive.php';
    include_once 'snippet-auto-delete.php';
    include_once 'snippet-disable-rss-caching.php';
    
    // SEO Settings (admin UI + frontend logic)
    include_once 'seo-settings.php';
    include_once 'seo-frontend.php';
    
    // Dashboard components
    include_once 'settings-dashboard-components.php';
    include_once 'settings-dashboard-overview.php';
    include_once 'settings-dashboard-system-checks.php';
    include_once 'settings-dashboard-plugin-checks.php';
    include_once 'settings-dashboard-snippets.php';
    include_once 'settings-dashboard-plugin-info.php';
    include_once 'settings-dashboard.php';
    
    // Event handling (AJAX)
    include_once 'settings-event-handling.php';
    
    // Actions
    include_once 'settings-action-create-hexa-pr-wire-user.php';
    
    // Activate enabled snippets
    include_once 'activate-snippets.php';
});
