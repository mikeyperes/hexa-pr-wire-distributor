<?php

namespace hpr_distributor\Import;

use hpr_distributor\Migration\LegacyDependencyRetirement;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class NativeFeedSettings {
    public const OPTION = "hpr_distributor_import_settings";
    public const LEGACY_MIGRATION_OPTION = "hpr_distributor_legacy_metadata_migration";
    public const CRON_HOOK = "hpr_distributor_poll_feed";
    public const CONTRACT_VERSION = "1.0";

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_action( "init", [ self::class, "initialize" ], 8 );
        add_action( self::CRON_HOOK, [ NativeFeedImporter::class, "run_scheduled" ] );
        self::$registered = true;
    }

    public static function defaults(): array {
        return [
            "feed_url"         => "",
            "publication_slug" => "",
            "enabled"          => true,
            "schedule_enabled" => true,
            "interval"         => "hourly",
            "author_id"        => 0,
            "post_status"      => "publish",
            "max_items"        => 100,
            "allowed_host"     => "hexaprwire.com",
            "configured_by"    => "native",
        ];
    }

    public static function get(): array {
        $stored = get_option( self::OPTION, [] );
        return self::normalize( is_array( $stored ) ? $stored : [] );
    }

    public static function initialize(): void {
        self::migrate_legacy_echo_rule();
        self::reconcile_schedule();
    }

    public static function normalize( array $settings ): array {
        $settings = wp_parse_args( $settings, self::defaults() );
        $feed_url = esc_url_raw( trim( (string) $settings["feed_url"] ) );
        $publication_slug = sanitize_title( (string) $settings["publication_slug"] );

        if ( "" !== $feed_url ) {
            $query = [];
            parse_str( (string) wp_parse_url( $feed_url, PHP_URL_QUERY ), $query );
            if ( "" === $publication_slug ) {
                $publication_slug = sanitize_title( (string) ( $query["publication"] ?? "" ) );
            }
        }

        $interval = sanitize_key( (string) $settings["interval"] );
        if ( ! in_array( $interval, [ "hourly", "twicedaily", "daily" ], true ) ) {
            $interval = "hourly";
        }

        $post_status = sanitize_key( (string) $settings["post_status"] );
        if ( ! in_array( $post_status, [ "publish", "draft", "pending", "private" ], true ) ) {
            $post_status = "publish";
        }

        return [
            "feed_url"         => $feed_url,
            "publication_slug" => $publication_slug,
            "enabled"          => (bool) $settings["enabled"],
            "schedule_enabled" => (bool) $settings["schedule_enabled"],
            "interval"         => $interval,
            "author_id"        => absint( $settings["author_id"] ),
            "post_status"      => $post_status,
            "max_items"        => max( 1, min( 250, absint( $settings["max_items"] ) ) ),
            "allowed_host"     => strtolower( sanitize_text_field( (string) $settings["allowed_host"] ) ),
            "configured_by"    => sanitize_key( (string) $settings["configured_by"] ),
        ];
    }

    public static function validate( array $settings ): array {
        $settings = self::normalize( $settings );
        $errors = [];

        if ( "" === $settings["feed_url"] ) {
            $errors[] = "A Hexa PR Wire publication feed URL is required.";
        } elseif ( ! SourceIdentity::allowed_host( $settings["feed_url"], $settings["allowed_host"] ) ) {
            $errors[] = "The feed URL must be hosted by " . $settings["allowed_host"] . ".";
        }

		$query = [];
		parse_str( (string) wp_parse_url( $settings["feed_url"], PHP_URL_QUERY ), $query );
		$path = trim( (string) wp_parse_url( $settings["feed_url"], PHP_URL_PATH ), "/" );
		$feed_name = sanitize_key( (string) ( $query["feed"] ?? basename( $path ) ) );
		if ( "https" !== strtolower( (string) wp_parse_url( $settings["feed_url"], PHP_URL_SCHEME ) ) ) {
			$errors[] = "The feed URL must use HTTPS.";
		}
		if ( "rss_publication" !== $feed_name ) {
			$errors[] = "The feed URL must use the rss_publication feed.";
		}
        if ( "" === $settings["publication_slug"] ) {
            $errors[] = "A publication slug is required.";
        }

        return [ "valid" => [] === $errors, "errors" => $errors, "settings" => $settings ];
    }

    public static function save( array $settings ): array {
        $validation = self::validate( $settings );
        if ( ! $validation["valid"] ) {
            throw new \InvalidArgumentException( implode( " ", $validation["errors"] ) );
        }

        update_option( self::OPTION, $validation["settings"], false );
        self::reconcile_schedule( $validation["settings"] );
        return $validation["settings"];
    }

    public static function migrate_legacy_echo_rule(): array {
        $existing = get_option( self::OPTION, null );
        if ( is_array( $existing ) && "" !== (string) ( $existing["feed_url"] ?? "" ) ) {
            return [ "migrated" => false, "reason" => "native_settings_exist" ];
        }

        $rules = get_option( "echo_rules_list", [] );
        if ( ! is_array( $rules ) ) {
            return [ "migrated" => false, "reason" => "no_legacy_rules" ];
        }

        foreach ( $rules as $rule_id => $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $candidate = self::normalize(
                [
                    "feed_url"      => (string) ( $rule[0] ?? "" ),
                    "enabled"       => "1" === (string) ( $rule[2] ?? "0" ),
                    "author_id"     => (int) ( $rule[7] ?? 0 ),
                    "post_status"   => (string) ( $rule[5] ?? "publish" ),
                    "configured_by" => "echo-migration",
                ]
            );
            $validation = self::validate( $candidate );
            if ( "press-release" !== sanitize_key( (string) ( $rule[6] ?? "" ) ) || ! $validation["valid"] ) {
                continue;
            }

            update_option( self::OPTION, $validation["settings"], false );
            update_option(
                "hpr_distributor_echo_migration_receipt",
                [
                    "rule_id"      => (int) $rule_id,
                    "migrated_gmt" => current_time( "mysql", true ),
                    "feed_url"     => $validation["settings"]["feed_url"],
                ],
                false
            );
            return [ "migrated" => true, "rule_id" => (int) $rule_id, "settings" => $validation["settings"] ];
        }

        return [ "migrated" => false, "reason" => "no_matching_legacy_rule" ];
    }

    public static function reconcile_schedule( ?array $settings = null ): array {
        $settings = null === $settings ? self::get() : self::normalize( $settings );
        $next = wp_next_scheduled( self::CRON_HOOK );
        $should_schedule = $settings["enabled"] && $settings["schedule_enabled"] && "" !== $settings["feed_url"];

        if ( ! $should_schedule && false !== $next ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return [ "scheduled" => false, "changed" => true, "next_run" => 0 ];
        }

        if ( $should_schedule ) {
            $event = false !== $next && function_exists( "wp_get_scheduled_event" ) ? wp_get_scheduled_event( self::CRON_HOOK ) : null;
            $current_interval = is_object( $event ) ? (string) ( $event->schedule ?? "" ) : "";
            if ( false === $next || $current_interval !== $settings["interval"] ) {
                wp_clear_scheduled_hook( self::CRON_HOOK );
                wp_schedule_event( time() + 60, $settings["interval"], self::CRON_HOOK );
                $next = wp_next_scheduled( self::CRON_HOOK );
                return [ "scheduled" => true, "changed" => true, "next_run" => (int) $next ];
            }
        }

        return [ "scheduled" => $should_schedule, "changed" => false, "next_run" => false === $next ? 0 : (int) $next ];
    }

    public static function readiness(): array {
        $settings = self::get();
        $validation = self::validate( $settings );
        $legacy = LegacyDependencyRetirement::state();
        $next = wp_next_scheduled( self::CRON_HOOK );
        $event = false !== $next && function_exists( "wp_get_scheduled_event" ) ? wp_get_scheduled_event( self::CRON_HOOK ) : null;
        $schedule = [
            "scheduled" => false !== $next,
            "interval"  => is_object( $event ) ? (string) ( $event->schedule ?? "" ) : "",
            "next_run"  => false === $next ? 0 : (int) $next,
        ];
        $schedule_ready = ! $settings["schedule_enabled"]
            || ( $schedule["scheduled"] && $settings["interval"] === $schedule["interval"] );

        $errors = array_merge( $validation["errors"], (array) $legacy["conflicts"] );

        return [
            "ready"                  => $validation["valid"] && $settings["enabled"] && $schedule_ready && $legacy["ready"],
            "contract_version"       => self::CONTRACT_VERSION,
            "plugin_version"         => \hpr_distributor\Config::$plugin_version,
            "feed_url"               => $settings["feed_url"],
            "publication_slug"       => $settings["publication_slug"],
            "native_import_enabled"  => $settings["enabled"],
            "scheduled_polling"      => $schedule,
            "echo_rss_required"      => false,
            "fifu_required"          => false,
            "images_remain_on_source"=> true,
            "errors"                 => $errors,
            "schedule_ready"         => $schedule_ready,
            "legacy_dependencies"    => $legacy,
        ];
    }
}
