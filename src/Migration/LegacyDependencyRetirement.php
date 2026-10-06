<?php

namespace hpr_distributor\Migration;

use Hexa\PluginCore\PluginChecks\PluginCheckDefinition;
use Hexa\PluginCore\PluginChecks\PluginCheckService;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class LegacyDependencyRetirement {
    public const RECEIPT_OPTION = "hpr_distributor_legacy_dependency_retirement";
    public const ACTION_DISABLE_ECHO_JOB = "disable_echo_job";

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
        // Neither plugin blocks imports. FIFU keeps working for other posts while
        // Distributor removes FIFU data from press releases (FifuCoexistence);
        // an Echo job for Hexa PR Wire only duplicates work, so it is a warning
        // with a one-click fix that switches off just that job.
        $warnings = [];
        if ( $echo_active && 0 < $echo_rules["enabled"] ) {
            $warnings[] = "Echo RSS is still importing the Hexa PR Wire feed (" . $echo_rules["enabled"] . " job" . ( 1 === $echo_rules["enabled"] ? "" : "s" ) . "). Distributor imports it too; switch the Echo job off.";
        }
        if ( $fifu_active ) {
            $warnings[] = "FIFU is active. Distributor manages press-release images and keeps FIFU data off them; FIFU keeps working for other posts.";
        }

        $echo_resolution = ! $echo_active
            ? "plugin_inactive"
            : ( 0 === $echo_rules["enabled"] ? "matching_job_disabled" : "conflict" );

        return [
            "ready"                       => true,
            "conflicts"                   => [],
            "warnings"                    => $warnings,
            "echo_rss_required"           => false,
            "fifu_required"               => false,
            "echo_rss_active"             => $echo_active,
            "fifu_active"                 => $fifu_active,
            "echo_rss_scheduled_hooks"    => $echo_cron,
            "fifu_scheduled_hooks"        => $fifu_cron,
            "matching_echo_rules"         => $echo_rules["matching"],
            "enabled_matching_echo_rules" => $echo_rules["enabled"],
            "echo_resolution"             => $echo_resolution,
            "available_actions"           => [ self::ACTION_DISABLE_ECHO_JOB ],
            "automatic_shutdown"          => false,
            "stored_data_preserved"       => true,
        ];
    }

    public static function apply( string $action ): array {
        if ( ! in_array( $action, self::actions(), true ) ) {
            throw new \InvalidArgumentException( "Unknown legacy dependency action." );
        }

        $before = self::state();
        $rules = [ "matching" => $before["matching_echo_rules"], "disabled" => 0 ];

        if ( self::ACTION_DISABLE_ECHO_JOB === $action ) {
            $rules = self::disable_matching_echo_rules();
        }

        $after = self::state();
        $action_success = self::action_succeeded( $action, $after );
        $receipt = [
            "success"               => $action_success,
            "action"                => $action,
            "action_success"        => $action_success,
            "ready"                 => (bool) $after["ready"],
            "completed_gmt"         => current_time( "mysql", true ),
            "plugin_version"        => \hpr_distributor\Config::$plugin_version,
            "before"                => $before,
            "after"                 => $after,
            "disabled_echo_rules"   => $rules["disabled"],
            "plugins_deleted"       => false,
            "automatic_shutdown"    => false,
            "stored_data_preserved" => true,
            "post_ids_preserved"    => true,
        ];
        update_option( self::RECEIPT_OPTION, $receipt, false );

        return $receipt;
    }

    /** Only the Hexa PR Wire Echo job can be switched off; Echo RSS and FIFU themselves are never switched off. */
    public static function actions(): array {
        return [ self::ACTION_DISABLE_ECHO_JOB ];
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

    /** An Echo job is Hexa PR Wire's when its feed comes from the Hexa PR Wire host; other feeds are never touched. */
    private static function is_matching_echo_rule( $rule, array $settings ): bool {
        return is_array( $rule ) && SourceIdentity::allowed_host( (string) ( $rule[0] ?? "" ), (string) $settings["allowed_host"] );
    }

    private static function scheduled_hooks( array $hooks ): array {
        return array_values(
            array_filter(
                $hooks,
                static fn( string $hook ): bool => false !== wp_next_scheduled( $hook )
            )
        );
    }


    private static function action_succeeded( string $action, array $after ): bool {
        return self::ACTION_DISABLE_ECHO_JOB === $action && 0 === (int) $after["enabled_matching_echo_rules"];
    }

    private static function plugin_active( string $plugin ): bool {
        $active = function_exists( "is_plugin_active" ) && is_plugin_active( $plugin );
        $network_active = function_exists( "is_plugin_active_for_network" ) && is_plugin_active_for_network( $plugin );
        return $active || $network_active;
    }

    private static function load_plugin_functions(): void {
        if ( function_exists( "is_plugin_active" ) ) {
            return;
        }

        $plugin_functions = ABSPATH . "wp-admin/includes/plugin.php";
        if ( is_readable( $plugin_functions ) ) {
            require_once $plugin_functions;
        }
    }
}
