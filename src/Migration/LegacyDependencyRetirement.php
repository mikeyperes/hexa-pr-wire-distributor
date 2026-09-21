<?php

namespace hpr_distributor\Migration;

use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class LegacyDependencyRetirement {
    public const RECEIPT_OPTION = "hpr_distributor_legacy_dependency_retirement";

    private const ECHO_PLUGIN = "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php";
    private const FIFU_PLUGIN = "featured-image-from-url/featured-image-from-url.php";

    private const ECHO_CRON_HOOKS = [
        "echoaction",
        "echoactiondelete",
        "echoactionclear",
        "puc_cron_check_updates-rss-feed-post-generator-echo",
    ];

    private const FIFU_CRON_HOOKS = [
        "fifu_db2_orphan_gc_cron",
        "fifu_migration_cron",
        "fifu_create_cloud_upload_auto_event",
        "fifu_create_cloud_delete_auto_event",
    ];

    public static function state(): array {
        self::load_plugin_functions();

        $echo_active = self::plugin_active( self::ECHO_PLUGIN );
        $fifu_active = self::plugin_active( self::FIFU_PLUGIN );
        $echo_cron = self::scheduled_hooks( self::ECHO_CRON_HOOKS );
        $fifu_cron = self::scheduled_hooks( self::FIFU_CRON_HOOKS );
        $echo_rules = self::matching_echo_rules();
        $conflicts = [];

        if ( $echo_active ) {
            $conflicts[] = "Echo RSS is active and can duplicate native Distributor imports.";
        }
        if ( $fifu_active ) {
            $conflicts[] = "FIFU is active and can override Distributor-owned remote featured images.";
        }
        if ( [] !== $echo_cron ) {
            $conflicts[] = "Echo RSS polling is still scheduled.";
        }
        if ( [] !== $fifu_cron ) {
            $conflicts[] = "FIFU background work is still scheduled.";
        }
        if ( 0 < $echo_rules["enabled"] ) {
            $conflicts[] = "An enabled Hexa PR Wire Echo import rule is still present.";
        }

        return [
            "ready"                       => [] === $conflicts,
            "conflicts"                   => $conflicts,
            "echo_rss_required"           => false,
            "fifu_required"               => false,
            "echo_rss_active"             => $echo_active,
            "fifu_active"                 => $fifu_active,
            "echo_rss_scheduled_hooks"    => $echo_cron,
            "fifu_scheduled_hooks"        => $fifu_cron,
            "matching_echo_rules"         => $echo_rules["matching"],
            "enabled_matching_echo_rules" => $echo_rules["enabled"],
            "stored_data_preserved"       => true,
        ];
    }

    public static function retire(): array {
        $before = self::state();
        $metadata = NativeFeedImporter::migrate_legacy_posts( 250 );
        $rules = self::disable_matching_echo_rules();

        foreach ( array_merge( self::ECHO_CRON_HOOKS, self::FIFU_CRON_HOOKS ) as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }

        self::deactivate_plugin( self::ECHO_PLUGIN );
        self::deactivate_plugin( self::FIFU_PLUGIN );

        foreach ( array_merge( self::ECHO_CRON_HOOKS, self::FIFU_CRON_HOOKS ) as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }

        $after = self::state();
        $receipt = [
            "success"               => (bool) $after["ready"],
            "completed_gmt"         => current_time( "mysql", true ),
            "plugin_version"        => \hpr_distributor\Config::$plugin_version,
            "before"                => $before,
            "after"                 => $after,
            "disabled_echo_rules"   => $rules["disabled"],
            "legacy_metadata"       => $metadata,
            "plugins_deleted"       => false,
            "stored_data_preserved" => true,
            "post_ids_preserved"    => true,
        ];
        update_option( self::RECEIPT_OPTION, $receipt, false );

        return $receipt;
    }

    private static function matching_echo_rules(): array {
        $settings = NativeFeedSettings::get();
        $rules = get_option( "echo_rules_list", [] );
        $matching = 0;
        $enabled = 0;

        foreach ( is_array( $rules ) ? $rules : [] as $rule ) {
            if ( ! self::is_matching_echo_rule( $rule, $settings ) ) {
                continue;
            }
            $matching++;
            if ( "1" === (string) ( $rule[2] ?? "0" ) ) {
                $enabled++;
            }
        }

        return [ "matching" => $matching, "enabled" => $enabled ];
    }

    private static function disable_matching_echo_rules(): array {
        $settings = NativeFeedSettings::get();
        $rules = get_option( "echo_rules_list", [] );
        if ( ! is_array( $rules ) ) {
            return [ "matching" => 0, "disabled" => 0 ];
        }

        $matching = 0;
        $disabled = 0;
        foreach ( $rules as $index => $rule ) {
            if ( ! self::is_matching_echo_rule( $rule, $settings ) ) {
                continue;
            }
            $matching++;
            if ( "1" === (string) ( $rule[2] ?? "0" ) ) {
                $rules[ $index ][2] = "0";
                $disabled++;
            }
        }

        if ( 0 < $disabled ) {
            update_option( "echo_rules_list", $rules, false );
        }

        return [ "matching" => $matching, "disabled" => $disabled ];
    }

    private static function is_matching_echo_rule( $rule, array $settings ): bool {
        if ( ! is_array( $rule ) || "press-release" !== sanitize_key( (string) ( $rule[6] ?? "" ) ) ) {
            return false;
        }

        $feed_url = (string) ( $rule[0] ?? "" );
        if ( ! SourceIdentity::allowed_host( $feed_url, (string) $settings["allowed_host"] ) ) {
            return false;
        }

        $query = [];
        parse_str( (string) wp_parse_url( $feed_url, PHP_URL_QUERY ), $query );
        return "rss_publication" === sanitize_key( (string) ( $query["feed"] ?? "" ) );
    }

    private static function scheduled_hooks( array $hooks ): array {
        return array_values(
            array_filter(
                $hooks,
                static fn( string $hook ): bool => false !== wp_next_scheduled( $hook )
            )
        );
    }

    private static function plugin_active( string $plugin ): bool {
        $active = function_exists( "is_plugin_active" ) && is_plugin_active( $plugin );
        $network_active = function_exists( "is_plugin_active_for_network" ) && is_plugin_active_for_network( $plugin );
        return $active || $network_active;
    }

    private static function deactivate_plugin( string $plugin ): void {
        if ( function_exists( "is_plugin_active_for_network" ) && is_plugin_active_for_network( $plugin ) ) {
            deactivate_plugins( $plugin, true, true );
        }
        if ( function_exists( "is_plugin_active" ) && is_plugin_active( $plugin ) ) {
            deactivate_plugins( $plugin, true, false );
        }
    }

    private static function load_plugin_functions(): void {
        if ( function_exists( "is_plugin_active" ) && function_exists( "deactivate_plugins" ) ) {
            return;
        }

        $plugin_functions = ABSPATH . "wp-admin/includes/plugin.php";
        if ( is_readable( $plugin_functions ) ) {
            require_once $plugin_functions;
        }
    }
}
