<?php

namespace hpr_distributor;

use Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer;
use Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer;
use hpr_distributor\Core\CoreIntegration;

defined( 'ABSPATH' ) || exit;

function display_plugin_info(): void {
    if ( ! class_exists( UpdaterPanelRenderer::class ) || ! class_exists( CorePackagePanelRenderer::class ) ) {
        echo '<div class="notice notice-error"><p>The Hexa WP Core updater components are unavailable.</p></div>';
        return;
    }

    ( new UpdaterPanelRenderer( CoreIntegration::updater_config() ) )->render();
    ( new CorePackagePanelRenderer( CoreIntegration::core_package_config() ) )->render();
}
