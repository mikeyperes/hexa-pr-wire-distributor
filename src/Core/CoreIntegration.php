<?php

namespace hpr_distributor\Core;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CorePackageUpdates\CorePackageConfig;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;
use hpr_distributor\Config;
use hpr_distributor\ContentTypes\PressReleaseStructures;

defined( 'ABSPATH' ) || exit;

final class CoreIntegration {
    private static ?CoreBootstrap $bootstrap = null;
    private static ?UpdaterConfig $updater = null;
    private static ?CorePackageConfig $core_package = null;

    public static function boot(): void {
        if ( self::$bootstrap instanceof CoreBootstrap || ! self::classes_available() ) {
            return;
        }

        $plugin_file = dirname( __DIR__, 2 ) . '/' . Config::$plugin_starter_file;
        $plugin_root = dirname( $plugin_file );
        $plugin_basename = plugin_basename( $plugin_file );
        $context = new PluginContext(
            [
                'slug'        => Config::$plugin_folder_name,
                'basename'    => $plugin_basename,
                'version'     => Config::$plugin_version,
                'path'        => $plugin_root,
                'url'         => plugin_dir_url( $plugin_file ),
                'github_repo' => Config::$github_repo,
                'admin_page'  => admin_url( 'options-general.php?page=' . Config::$settings_page_slug ),
                'capability'  => Config::$settings_page_capability,
            ]
        );

        $bootstrap = new CoreBootstrap( $context );
        $bootstrap
            ->add_module( new GitHubPluginUpdater( self::updater_config() ) )
            ->add_module( new UpdaterAjaxController( self::updater_config() ) )
            ->add_module( PressReleaseStructures::registry() );

        if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
            $bootstrap
                ->add_module( new CorePackageAjaxController( self::core_package_config() ) )
                ->add_module(
                    new CoreTabModule(
                        new CoreTabConfig(
                            [
                                'tabs_filter'   => 'hpr_distributor_dashboard_tabs',
                                'render_filter' => 'hpr_distributor_render_dashboard_tab',
                                'capability'    => Config::$settings_page_capability,
                                'core_root'     => $plugin_root . '/lib/hexa-wordpress-plugin-core',
                                'readme_path'   => $plugin_root . '/lib/hexa-wordpress-plugin-core/README.md',
                                'library_path'  => $plugin_root . '/lib/hexa-wordpress-plugin-core/HEXA_PLUGIN_CORE_LIBRARY.md',
                            ]
                        )
                    )
                );
        }

        $bootstrap->boot();
        self::$bootstrap = $bootstrap;
        do_action( 'hpr_distributor_core_booted', $context, $bootstrap );
    }

    public static function updater_config(): UpdaterConfig {
        if ( self::$updater instanceof UpdaterConfig ) {
            return self::$updater;
        }

        $plugin_file = dirname( __DIR__, 2 ) . '/' . Config::$plugin_starter_file;
        $plugin_basename = plugin_basename( $plugin_file );
        self::$updater = UpdaterConfig::from_plugin_file(
            $plugin_file,
            Config::$github_repo,
            [
                'plugin_slug'               => Config::$plugin_folder_name,
                'proper_folder_name'        => Config::$plugin_folder_name,
                'runtime_folder_name'       => dirname( $plugin_basename ),
                'plugin_basename'           => $plugin_basename,
                'canonical_plugin_basename' => Config::$plugin_folder_name . '/' . Config::$plugin_starter_file,
                'plugin_starter_file'       => Config::$plugin_starter_file,
                'github_branch'             => Config::$github_branch,
                'requires'                  => '5.8',
                'tested'                    => get_bloginfo( 'version' ),
                'requires_php'              => '8.0',
                'nonce_action'              => 'hpr_core_updater',
                'nonce_param'               => 'nonce',
                'ajax_action_prefix'        => 'hpr_core_updater',
                'progress_key'              => 'hpr_core_update_progress',
            ]
        );
        return self::$updater;
    }

    public static function core_package_config(): CorePackageConfig {
        if ( self::$core_package instanceof CorePackageConfig ) {
            return self::$core_package;
        }

        self::$core_package = CorePackageConfig::from_core_root(
            dirname( __DIR__, 2 ) . '/lib/hexa-wordpress-plugin-core',
            [
                'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
                'github_branch'      => 'main',
                'nonce_action'       => 'hpr_core_package',
                'nonce_param'        => 'nonce',
                'ajax_action_prefix' => 'hpr_core_package',
                'cache_key'          => 'hpr_hexa_plugin_core_package',
                'progress_key'       => 'hpr_hexa_core_update_progress',
            ]
        );
        return self::$core_package;
    }

    private static function classes_available(): bool {
        return class_exists( CoreBootstrap::class )
            && class_exists( PluginContext::class )
            && class_exists( UpdaterConfig::class )
            && class_exists( CorePackageConfig::class );
    }
}
