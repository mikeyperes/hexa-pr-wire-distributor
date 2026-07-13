<?php

namespace hpr_distributor;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function add_wp_admin_settings_page(): void {
    add_options_page(
        Config::$settings_page_name,
        Config::$settings_page_name,
        Config::$settings_page_capability,
        Config::$settings_page_slug,
        __NAMESPACE__ . "\\display_wp_admin_settings_page"
    );
}
add_action( "admin_menu", __NAMESPACE__ . "\\add_wp_admin_settings_page" );

function hpr_dashboard_tabs(): array {
    return apply_filters(
        "hpr_distributor_dashboard_tabs",
        [
            "overview"   => "Overview",
            "going-live" => "Going Live",
            "echo-rss"   => "Import & Sync",
            "snippets"   => "Content Rules",
            "ui-cleanup" => "Editor UI",
            "diagnostics"=> "Diagnostics",
        ]
    );
}

function hpr_dashboard_active_tab( array $tabs ): string {
    $requested = isset( $_GET["tab"] ) ? sanitize_key( wp_unslash( $_GET["tab"] ) ) : "overview";
    $aliases = [
        "system-checks" => "diagnostics",
        "plugins"       => "diagnostics",
        "plugin-info"   => "diagnostics",
    ];
    $requested = $aliases[ $requested ] ?? $requested;

    return isset( $tabs[ $requested ] ) ? $requested : "overview";
}

function display_wp_admin_settings_page(): void {
    if ( ! current_user_can( Config::$settings_page_capability ) ) {
        wp_die( esc_html__( "You do not have permission to access this page." ) );
    }

    $tabs = hpr_dashboard_tabs();
    $active_tab = hpr_dashboard_active_tab( $tabs );
    output_dashboard_styles();
    ?>
    <div class="wrap" id="hpr-dashboard">
        <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>

        <nav class="hpr-tabs-nav" aria-label="<?php echo esc_attr__( "Hexa PR Wire settings" ); ?>">
            <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                <?php
                $url = add_query_arg(
                    [
                        "page" => Config::$settings_page_slug,
                        "tab"  => $tab_id,
                    ],
                    admin_url( "options-general.php" )
                );
                ?>
                <a
                    class="hpr-tab-btn<?php echo $tab_id === $active_tab ? " active" : ""; ?>"
                    data-tab="<?php echo esc_attr( $tab_id ); ?>"
                    href="<?php echo esc_url( $url ); ?>"
                    <?php echo $tab_id === $active_tab ? 'aria-current="page"' : ""; ?>
                ><?php echo esc_html( $tab_label ); ?></a>
            <?php endforeach; ?>
        </nav>

        <script>
            window.hprNonce = <?php echo wp_json_encode( wp_create_nonce( Config::AJAX_NONCE ) ); ?>;
            window.hprNamespace = <?php echo wp_json_encode( __NAMESPACE__ ); ?>;
        </script>

        <section id="tab-<?php echo esc_attr( $active_tab ); ?>" class="hpr-tab-content active">
            <?php hpr_render_dashboard_tab( $active_tab ); ?>
        </section>
    </div>
    <?php
}

function hpr_render_dashboard_tab( string $tab_id ): void {
    $handled = (bool) apply_filters( "hpr_distributor_render_dashboard_tab", false, $tab_id );
    if ( $handled ) {
        return;
    }

    switch ( $tab_id ) {
        case "overview":
            if ( function_exists( __NAMESPACE__ . "\\display_settings_overview" ) ) {
                display_settings_overview();
            }
            break;

        case "going-live":
            if ( class_exists( \hpr_distributor\Admin\GoingLiveTab::class ) ) {
                \hpr_distributor\Admin\GoingLiveTab::render();
            }
            break;

        case "echo-rss":
            if ( function_exists( __NAMESPACE__ . "\\display_settings_echo_rss" ) ) {
                display_settings_echo_rss();
            }
            break;

        case "snippets":
            if ( function_exists( __NAMESPACE__ . "\\display_settings_snippets" ) ) {
                display_settings_snippets();
            }
            break;

        case "ui-cleanup":
            if ( function_exists( __NAMESPACE__ . "\\display_settings_ui_cleanup" ) ) {
                display_settings_ui_cleanup();
            }
            break;

        case "diagnostics":
            if ( function_exists( __NAMESPACE__ . "\\display_settings_system_checks" ) ) {
                display_settings_system_checks();
            }
            if ( function_exists( __NAMESPACE__ . "\\display_plugin_info" ) ) {
                display_plugin_info();
            }
            break;
    }
}
