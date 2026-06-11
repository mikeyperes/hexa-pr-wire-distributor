<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - Main Settings Dashboard
 * 
 * Tabbed dashboard similar to HWS Base Tools structure:
 * - Overview tab with RSS info and quick links
 * - System Checks tab
 * - Plugin Checks tab
 * - Snippets tab with toggle switches
 * - Plugin Info tab
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register settings page in admin menu
 */
function add_wp_admin_settings_page() {
    add_options_page(
        Config::$settings_page_name,
        Config::$settings_page_name,
        Config::$settings_page_capability,
        Config::$settings_page_slug,
        __NAMESPACE__ . '\\display_wp_admin_settings_page'
    );
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_wp_admin_settings_page' );

/**
 * Display the main settings page
 */
function display_wp_admin_settings_page() {
    if ( ob_get_level() == 0 ) {
        ob_start();
    }
    
    // Define tabs
    $tabs = [
        'overview'      => '📊 Overview',
        'system-checks' => '🔍 System Checks',
        'plugins'       => '🔌 Plugin Checks',
        'echo-rss'      => 'Echo RSS Settings',
        'force-sync'    => 'Force Sync',
        'snippets'      => '✂️ Snippets',
    ];
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
    if ( ! isset( $tabs[ $active_tab ] ) ) {
        $active_tab = 'overview';
    }
    
    // Output dashboard styles
    output_dashboard_styles();
    
    ?>
    <div class="wrap" id="hpr-dashboard">
        <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
        
        <!-- Tab Navigation -->
        <div class="hpr-tabs-nav">
            <?php
            foreach ( $tabs as $tab_id => $tab_label ) :
                $active = $tab_id === $active_tab ? ' active' : '';
            ?>
                <button type="button" class="hpr-tab-btn<?php echo $active; ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </button>
            <?php
            endforeach;
            ?>
        </div>
        
        <!-- Tab Contents -->
        <?php
        foreach ( $tabs as $tab_id => $tab_label ) :
            $active = $tab_id === $active_tab ? ' active' : '';
        ?>
            <div id="tab-<?php echo esc_attr( $tab_id ); ?>" class="hpr-tab-content<?php echo $active; ?>">
                <?php
                switch ( $tab_id ) {
                    case 'overview':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_overview' ) ) {
                            display_settings_overview();
                        }
                        // Plugin info at bottom of overview
                        if ( function_exists( __NAMESPACE__ . '\\display_plugin_info' ) ) {
                            display_plugin_info();
                        }
                        break;
                    case 'system-checks':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_system_checks' ) ) {
                            display_settings_system_checks();
                        }
                        break;
                    case 'plugins':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_check_plugins' ) ) {
                            display_settings_check_plugins();
                        }
                        break;
                    case 'echo-rss':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_echo_rss' ) ) {
                            display_settings_echo_rss();
                        }
                        break;
                    case 'force-sync':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_force_sync' ) ) {
                            display_settings_force_sync();
                        }
                        break;
                    case 'snippets':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_snippets' ) ) {
                            display_settings_snippets();
                        }
                        break;
                }
                ?>
            </div>
        <?php
        endforeach;
        ?>
    </div>
    
    <script>
    // Global nonce for all AJAX calls
    var hprNonce = '<?php echo wp_create_nonce( Config::AJAX_NONCE ); ?>';
    var hprNamespace = '<?php echo __NAMESPACE__; ?>';
    
    jQuery(document).ready(function($) {
        // Tab switching (no page refresh)
        $('.hpr-tab-btn').on('click', function() {
            var tabId = $(this).data('tab');
            $('.hpr-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.hpr-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            if (window.history && window.history.replaceState && typeof window.URL === 'function') {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url.toString());
            }
        });
    });
    </script>
    
    <?php
    if ( ob_get_level() != 0 ) {
        echo ob_get_clean();
    }
}
