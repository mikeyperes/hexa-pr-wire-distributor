<?php

namespace hpr_distributor;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;

defined( 'ABSPATH' ) || exit;

function add_wp_admin_settings_page(): void {
    add_options_page(
        Config::$settings_page_name,
        Config::$settings_page_name,
        Config::$settings_page_capability,
        Config::$settings_page_slug,
        __NAMESPACE__ . '\\display_wp_admin_settings_page'
    );
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_wp_admin_settings_page' );

/** @return array<string,string> */
function hpr_dashboard_tabs(): array {
    return apply_filters(
        'hpr_distributor_dashboard_tabs',
        [
            'overview'      => 'Overview',
            'going-live'    => 'Going Live',
            'echo-rss'      => 'Import & Sync',
            'content-types' => 'Custom Post Types',
            'snippets'      => 'Content Rules',
            'ui-cleanup'    => 'Editor UI',
            'diagnostics'   => 'Diagnostics',
        ]
    );
}

/** @param array<string,mixed> $tabs */
function hpr_dashboard_active_tab( array $tabs ): string {
    $requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
    $aliases = [
        'system-checks' => 'diagnostics',
        'plugins'       => 'diagnostics',
        'plugin-info'   => 'diagnostics',
    ];
    $requested = $aliases[ $requested ] ?? $requested;

    return isset( $tabs[ $requested ] ) ? $requested : 'overview';
}

function hpr_dashboard_registry(): TabRegistry {
    $registry = new TabRegistry();
    foreach ( hpr_dashboard_tabs() as $id => $label ) {
        $id = sanitize_key( (string) $id );
        if ( '' === $id ) {
            continue;
        }
        $registry->add(
            new TabDefinition(
                $id,
                (string) $label,
                static function () use ( $id ): void {
                    hpr_render_dashboard_tab( $id );
                },
                Config::$settings_page_capability
            )
        );
    }
    return $registry;
}

/** @param array<string,mixed> $tabs @return array<int,array{label:string,tabs:array<int,string>}> */
function hpr_dashboard_groups( array $tabs ): array {
    $groups = [
        [ 'label' => 'Overview', 'tabs' => [ 'overview', 'going-live' ] ],
        [ 'label' => 'Press Releases', 'tabs' => [ 'echo-rss', 'content-types', 'snippets' ] ],
        [ 'label' => 'Administration', 'tabs' => [ 'ui-cleanup', 'diagnostics', 'hexa-core' ] ],
    ];
    foreach ( $groups as &$group ) {
        $group['tabs'] = array_values( array_filter( $group['tabs'], static fn( string $id ): bool => isset( $tabs[ $id ] ) ) );
    }
    unset( $group );
    return array_values( array_filter( $groups, static fn( array $group ): bool => [] !== $group['tabs'] ) );
}

function hpr_render_registered_tab( TabRegistry $registry, string $tab_id ): void {
    $tab = $registry->get( $tab_id ) ?? $registry->get( 'overview' );
    if ( ! $tab instanceof TabDefinition ) {
        return;
    }
    if ( $tab->capability && ! current_user_can( $tab->capability ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this section.', 'hexa-pr-wire-distributor' ) . '</p></div>';
        return;
    }
    if ( is_callable( $tab->renderer ) ) {
        call_user_func( $tab->renderer );
    }
}

/** @return array{tab:string,label:string,html:string} */
function hpr_tab_fragment( string $requested ): array {
    $registry = hpr_dashboard_registry();
    $tabs = $registry->all();
    $_GET['tab'] = $requested;
    $active = hpr_dashboard_active_tab( $tabs );
    $tab = $registry->get( $active );
    ob_start();
    hpr_render_registered_tab( $registry, $active );
    return [
        'tab'   => $active,
        'label' => $tab instanceof TabDefinition ? $tab->label : $active,
        'html'  => (string) ob_get_clean(),
    ];
}

function hpr_register_dashboard_ajax(): void {
    static $registered = false;
    if ( $registered || ! class_exists( AjaxActionRegistry::class ) ) {
        return;
    }
    ( new AjaxActionRegistry(
        [
            'capability'   => Config::$settings_page_capability,
            'nonce_action' => Config::AJAX_NONCE,
            'nonce_field'  => 'nonce',
        ]
    ) )->register(
        [
            'hpr_load_tab' => [
                'callback' => static fn( AjaxRequest $request ): array => hpr_tab_fragment( $request->key( 'tab', 'overview', 'post' ) ),
            ],
        ]
    );
    $registered = true;
}
hpr_register_dashboard_ajax();

/** @return array<string,string> */
function hpr_sidebar_identity(): array {
    return [
        'plugin_name'     => Config::$plugin_name,
        'current_version' => Config::$plugin_version,
        'github_url'      => 'https://github.com/' . Config::$github_repo,
        'core_name'       => 'Hexa WP Core',
        'core_version'    => defined( 'HEXA_PLUGIN_CORE_SELECTED_VERSION' ) ? (string) HEXA_PLUGIN_CORE_SELECTED_VERSION : 'Unknown',
        'core_github_url' => 'https://github.com/mikeyperes/hexa-wordpress-plugin-core',
    ];
}

function display_wp_admin_settings_page(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'hexa-pr-wire-distributor' ) );
    }
    $registry = hpr_dashboard_registry();
    $tabs = $registry->all();
    $active = hpr_dashboard_active_tab( $tabs );
    output_dashboard_styles();
    ?>
    <div class="wrap" id="hpr-dashboard">
        <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
        <script>
            window.hprNonce = <?php echo wp_json_encode( wp_create_nonce( Config::AJAX_NONCE ) ); ?>;
            window.hprNamespace = <?php echo wp_json_encode( __NAMESPACE__ ); ?>;
        </script>
        <?php
        if ( class_exists( HostTabsRenderer::class ) ) {
            ( new HostTabsRenderer() )->render(
                [
                    'tabs'                => $tabs,
                    'active'              => $active,
                    'page_url'            => menu_page_url( Config::$settings_page_slug, false ),
                    'ajax_action'         => 'hpr_load_tab',
                    'nonce'               => wp_create_nonce( Config::AJAX_NONCE ),
                    'nonce_field'         => 'nonce',
                    'root_id'             => 'hpr-host-tabs',
                    'panel_id'            => 'hpr-host-tab-panel',
                    'label'               => Config::$settings_page_display_title,
                    'layout'              => 'sidebar',
                    'groups'              => hpr_dashboard_groups( $tabs ),
                    'sidebar_identity'    => hpr_sidebar_identity(),
                    'sidebar_collapsible' => true,
                    'sidebar_collapsed'   => false,
                    'sidebar_persist'     => true,
                    'render_callback'     => static function ( string $tab_id ) use ( $registry ): void {
                        hpr_render_registered_tab( $registry, $tab_id );
                    },
                ]
            );
        } else {
            hpr_render_registered_tab( $registry, $active );
        }
        ?>
    </div>
    <?php
}

function hpr_render_dashboard_tab( string $tab_id ): void {
    $handled = (bool) apply_filters( 'hpr_distributor_render_dashboard_tab', false, $tab_id );
    if ( $handled ) {
        return;
    }
    switch ( $tab_id ) {
        case 'overview':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_overview' ) ) display_settings_overview();
            break;
        case 'going-live':
            if ( class_exists( \hpr_distributor\Admin\GoingLiveTab::class ) ) \hpr_distributor\Admin\GoingLiveTab::render();
            break;
        case 'echo-rss':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_echo_rss' ) ) display_settings_echo_rss();
            break;
        case 'content-types':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_content_types' ) ) display_settings_content_types();
            break;
        case 'snippets':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_snippets' ) ) display_settings_snippets();
            break;
        case 'ui-cleanup':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_ui_cleanup' ) ) display_settings_ui_cleanup();
            break;
        case 'diagnostics':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_system_checks' ) ) display_settings_system_checks();
            if ( function_exists( __NAMESPACE__ . '\\display_plugin_info' ) ) display_plugin_info();
            break;
    }
}
