<?php
/**
 * Plugin Name: Hexa PR Wire - Distributor
 * Description: Press release distribution and management for Hexa PR Wire network.
 * Author: Michael Peres
 * Plugin URI: https://github.com/mikeyperes/hexa-pr-wire-distributor
 * Version: 2.5.3
 * Author URI: https://michaelperes.com
 * GitHub Plugin URI: https://github.com/mikeyperes/hexa-pr-wire-distributor/
 * GitHub Branch: main
 * Requires PHP: 8.0
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
    public static $plugin_version        = '2.5.3';
    public static $plugin_slug           = 'hpr-distributor';
    public static $plugin_folder_name    = 'hexa-pr-wire-distributor';
    public static $plugin_starter_file   = 'hexa-pr-wire-distributor.php';
    
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
            'tested'             => '7.0',
            'readme'             => 'README.md',
            'access_token'       => '',
        ];
    }
}

$hexa_plugin_core_root = __DIR__ . "/lib/hexa-wordpress-plugin-core";
require_once $hexa_plugin_core_root . "/bootstrap.php";
\hexa_plugin_core_register_package( "hexa-pr-wire-distributor", $hexa_plugin_core_root );

function guard_ajax_request( string $capability = "manage_options" ): void {
    \Hexa\PluginCore\WpAdminAjax\AjaxGuard::require_capability_or_error( $capability );
    \Hexa\PluginCore\WpAdminAjax\AjaxGuard::require_nonce_or_error( Config::AJAX_NONCE );
}

function migrate_legacy_plugin_basename(): void {
    $canonical = Config::$plugin_folder_name . "/" . Config::$plugin_starter_file;
    $legacy    = Config::$plugin_folder_name . "/initialization.php";

    if ( $canonical === $legacy ) {
        return;
    }

    $active_plugins = (array) get_option( "active_plugins", [] );
    $changed = false;
    foreach ( $active_plugins as $index => $plugin ) {
        if ( $legacy === $plugin ) {
            $active_plugins[ $index ] = $canonical;
            $changed = true;
        }
    }

    if ( $changed ) {
        update_option( "active_plugins", array_values( array_unique( $active_plugins ) ), false );
    }

    if ( is_multisite() ) {
        $network_plugins = (array) get_site_option( "active_sitewide_plugins", [] );
        if ( isset( $network_plugins[ $legacy ] ) ) {
            $network_plugins[ $canonical ] = $network_plugins[ $legacy ];
            unset( $network_plugins[ $legacy ] );
            update_site_option( "active_sitewide_plugins", $network_plugins );
        }
    }
}
add_action( "plugins_loaded", __NAMESPACE__ . "\\migrate_legacy_plugin_basename", 1 );

// Include core files
include_once 'generic-functions.php';
include_once 'GitHub_Updater.php';
include_once 'force-syndication.php';
include_once 'force-sync-assets.php';

function autoload_plugin_class( string $class_name ): void {
    $prefix = __NAMESPACE__ . "\\";
    if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
        return;
    }

    $relative = substr( $class_name, strlen( $prefix ) );
    $file = __DIR__ . "/src/" . str_replace( "\\", "/", $relative ) . ".php";

    if ( is_readable( $file ) ) {
        require_once $file;
    }
}
spl_autoload_register( __NAMESPACE__ . "\\autoload_plugin_class" );

if ( is_admin() ) {
    $updater = new WP_GitHub_Updater( Config::get_github_config() );
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

Plugin::boot();

/**
 * Get all available snippets
 * Returns array of snippet configurations
 */
function get_settings_snippets() {
    $snippets = [
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
            'id'          => 'hide_press_release_from_home_loop',
            'name'        => 'Hide Press Release From Home Page Loop',
            'description' => 'Remove press-release posts from regular post loops on the front page or posts index. Uses guarded frontend query filters only.',
            'function'    => 'hide_press_release_from_home_loop',
            'category'    => 'hide_press_release',
            'default'     => true,
        ],
        [
            'id'          => 'hide_press_release_from_author_loop',
            'name'        => 'Hide Press Release From Author Page Loop',
            'description' => 'Remove press-release posts from regular post loops on author archive pages only.',
            'function'    => 'hide_press_release_from_author_loop',
            'category'    => 'hide_press_release',
            'default'     => true,
        ],
        [
            'id'          => 'hide_press_release_from_category_loop',
            'name'        => 'Hide Press Release From Category Page Loop',
            'description' => 'Remove press-release posts from regular post loops on category archive pages only.',
            'function'    => 'hide_press_release_from_category_loop',
            'category'    => 'hide_press_release',
            'default'     => true,
        ],
        [
            'id'          => 'hide_press_release_from_tag_loop',
            'name'        => 'Hide Press Release From Tag Page Loop',
            'description' => 'Remove press-release posts from regular post loops on tag archive pages only.',
            'function'    => 'hide_press_release_from_tag_loop',
            'category'    => 'hide_press_release',
            'default'     => true,
        ],
        [
            'id'          => 'hide_press_release_from_related_single_loop',
            'name'        => 'Hide Press Release From Related Content Loop',
            'description' => 'Remove press-release posts from frontend related-content loops on single post pages only.',
            'function'    => 'hide_press_release_from_related_single_loop',
            'category'    => 'hide_press_release',
            'default'     => true,
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

function is_settings_snippet_enabled( array $snippet ): bool {
    $missing = '__hpr_missing_snippet_option__';
    $value = get_option( $snippet['id'], $missing );

    if ( $missing === $value ) {
        return ! empty( $snippet['default'] );
    }

    return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
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
    include_once 'register-acf-seo-fields.php';
    
    // Snippets
    include_once 'snippet-add-press-release-post-to-author.php';
    include_once 'snippet-add-press-release-to-archive.php';
    include_once 'snippet-hide-press-release-loops.php';
    include_once 'snippet-auto-delete.php';
    include_once 'snippet-disable-rss-caching.php';
    
    // SEO Settings (admin UI + frontend logic)
    include_once 'seo-settings.php';
    include_once 'seo-frontend.php';
    
    // Dashboard components
    include_once 'settings-dashboard-components.php';
    include_once 'settings-dashboard-overview.php';
    include_once 'settings-dashboard-system-checks.php';
    include_once 'settings-dashboard-snippets.php';
    include_once 'settings-dashboard-plugin-info.php';
    include_once 'settings-dashboard-echo-rss.php';
    include_once 'settings-dashboard-ui-cleanup.php';
        include_once 'settings-dashboard.php';
    
    // Event handling (AJAX)
    include_once 'settings-event-handling.php';
    
    // Activate enabled snippets
    include_once 'activate-snippets.php';
});
