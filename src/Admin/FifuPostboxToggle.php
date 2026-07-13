<?php

namespace hpr_distributor\Admin;

use hpr_distributor\Config;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class FifuPostboxToggle {
    private const HIDE_OPTION = "hpr_ui_cleanup_hide_fifu_featured_image_box";
    private const COLLAPSE_OPTION = "hpr_ui_cleanup_collapse_fifu_featured_image_box";
    private const ASSET_HANDLE = "hpr-fifu-postbox-toggle";

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_action( "admin_enqueue_scripts", [ self::class, "enqueue_assets" ], 1000 );
        self::$registered = true;
    }

    public static function enqueue_assets( string $hook_suffix ): void {
        if ( ! in_array( $hook_suffix, [ "post.php", "post-new.php" ], true ) || ! self::should_enable() ) {
            return;
        }

        $plugin_file = Config::get_plugin_dir() . "/" . Config::$plugin_starter_file;
        $version = Config::$plugin_version;

        wp_enqueue_style(
            self::ASSET_HANDLE,
            plugins_url( "assets/admin/fifu-postbox-toggle.css", $plugin_file ),
            [],
            $version
        );
        wp_enqueue_script(
            self::ASSET_HANDLE,
            plugins_url( "assets/admin/fifu-postbox-toggle.js", $plugin_file ),
            [],
            $version,
            true
        );
    }

    public static function should_enable(): bool {
        $hidden = (bool) get_option( self::HIDE_OPTION, false );
        $collapsed = (bool) get_option( self::COLLAPSE_OPTION, false );

        return ! $hidden && $collapsed;
    }
}
