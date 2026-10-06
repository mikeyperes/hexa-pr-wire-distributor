<?php

namespace hpr_distributor\Remote;

use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\GitHubVersionClient;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Remote status and updates for the Hexa plugin family installed on this site.
 *
 * Any plugin in CATALOG that is installed here is reported with its installed
 * and latest GitHub version, and can be updated through WordPress's own plugin
 * upgrader fed by the plugin's Hexa GitHub updater. Updates run only while the
 * site's "Remote plugin updates" switch is on (off by default); status is
 * always readable by an authorized caller. Kept free of Distributor specifics
 * so it can move into HexaWP Core unchanged.
 */
final class RemotePluginUpdates {
    public const ENABLED_OPTION = "hpr_remote_plugin_updates";

    /** Plugin folder => GitHub repository. */
    public const CATALOG = [
        "hexa-pr-wire-distributor"    => "mikeyperes/hexa-pr-wire-distributor",
        "hws-base-tools"              => "mikeyperes/hws-base-tools",
        "smp-publication-integration" => "mikeyperes/smp-publication-integration",
        "smp-verified-profiles"       => "mikeyperes/smp-verified-profiles",
    ];

    public static function enabled(): bool {
        return "1" === (string) get_option( self::ENABLED_OPTION, "0" );
    }

    public static function set_enabled( bool $enabled ): void {
        update_option( self::ENABLED_OPTION, $enabled ? "1" : "0", false );
    }

    /** @return array{remote_updates:bool,plugins:array<int,array<string,mixed>>} */
    public static function status( bool $refresh = false ): array {
        self::load_admin_includes();
        $installed = self::installed();
        if ( $refresh ) {
            self::refresh_update_data( array_keys( $installed ) );
        }
        $updates = get_site_transient( "update_plugins" );
        $plugins = [];
        foreach ( $installed as $slug => $file ) {
            $data = get_plugin_data( WP_PLUGIN_DIR . "/" . $file, false, false );
            $offer = self::offer( $updates, $file );
            $plugins[] = [
                "slug"             => $slug,
                "name"             => (string) $data["Name"],
                "file"             => $file,
                "active"           => is_plugin_active( $file ),
                "version"          => (string) $data["Version"],
                "latest"           => null !== $offer ? (string) $offer->new_version : "",
                "update_available" => null !== $offer && version_compare( (string) $offer->new_version, (string) $data["Version"], ">" ),
            ];
        }
        return [ "remote_updates" => self::enabled(), "plugins" => $plugins ];
    }

    /** @return array<string,mixed> */
    public static function update( string $slug ): array {
        if ( ! self::enabled() ) {
            throw new \RuntimeException( "Remote plugin updates are turned off on this site." );
        }
        self::load_admin_includes();
        $installed = self::installed();
        if ( ! isset( $installed[ $slug ] ) ) {
            throw new \RuntimeException( "This plugin is not installed or not in the Hexa plugin family." );
        }
        $file = $installed[ $slug ];
        $before = (string) get_plugin_data( WP_PLUGIN_DIR . "/" . $file, false, false )["Version"];
        self::refresh_update_data( [ $slug ] );
        $offer = self::offer( get_site_transient( "update_plugins" ), $file );
        if ( null === $offer || ! version_compare( (string) $offer->new_version, $before, ">" ) ) {
            return [ "slug" => $slug, "updated" => false, "from" => $before, "to" => $before, "message" => "Already on the latest version." ] + self::homepage();
        }

        require_once ABSPATH . "wp-admin/includes/class-wp-upgrader.php";
        $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
        $result = $upgrader->upgrade( $file );
        if ( is_wp_error( $result ) || true !== $result ) {
            $errors = $upgrader->skin->get_errors();
            $message = is_wp_error( $result ) ? $result->get_error_message() : ( $errors->has_errors() ? $errors->get_error_message() : "WordPress did not complete the update." );
            throw new \RuntimeException( $message );
        }

        wp_clean_plugins_cache( false );
        $after = (string) get_plugin_data( WP_PLUGIN_DIR . "/" . $file, false, false )["Version"];
        return [ "slug" => $slug, "updated" => $after !== $before, "from" => $before, "to" => $after, "active" => is_plugin_active( $file ), "message" => "Updated " . $before . " → " . $after . "." ] + self::homepage();
    }

    /** @return array<string,string> slug => plugin file, for installed catalog plugins */
    private static function installed(): array {
        $found = [];
        foreach ( get_plugins() as $file => $data ) {
            $slug = dirname( (string) $file );
            if ( isset( self::CATALOG[ $slug ] ) ) {
                $found[ $slug ] = (string) $file;
            }
        }
        return $found;
    }

    /**
     * Re-ask GitHub for the latest versions. A plugin that did not load its own
     * Hexa updater in this request (some only do in wp-admin) gets one here.
     *
     * @param array<int,string> $slugs
     */
    private static function refresh_update_data( array $slugs ): void {
        $installed = self::installed();
        foreach ( $slugs as $slug ) {
            if ( ! isset( $installed[ $slug ] ) ) {
                continue;
            }
            $config = UpdaterConfig::from_plugin_file( WP_PLUGIN_DIR . "/" . $installed[ $slug ], self::CATALOG[ $slug ], [ "proper_folder_name" => $slug ] );
            ( new GitHubVersionClient( $config ) )->clear_cache();
            if ( ! self::has_updater( $installed[ $slug ] ) ) {
                ( new GitHubPluginUpdater( $config ) )->register();
            }
        }
        delete_site_transient( "update_plugins" );
        wp_update_plugins();
        // wp_update_plugins() stores nothing when wordpress.org cannot be reached;
        // re-saving runs the Hexa updaters either way.
        $updates = get_site_transient( "update_plugins" );
        set_site_transient( "update_plugins", is_object( $updates ) ? $updates : (object) [ "last_checked" => time(), "checked" => [], "response" => [], "no_update" => [] ] );
    }

    /** Whether a Hexa updater already answers for this plugin in this request. */
    private static function has_updater( string $file ): bool {
        $probe = apply_filters( "pre_set_site_transient_update_plugins", (object) [ "checked" => [], "response" => [], "no_update" => [] ] );
        return is_object( $probe ) && ( isset( $probe->response[ $file ] ) || isset( $probe->no_update[ $file ] ) );
    }

    private static function offer( mixed $updates, string $file ): ?object {
        foreach ( [ "response", "no_update" ] as $list ) {
            if ( is_object( $updates ) && isset( $updates->{$list}[ $file ] ) && is_object( $updates->{$list}[ $file ] ) ) {
                return $updates->{$list}[ $file ];
            }
        }
        return null;
    }

    /** @return array{homepage_http:int} */
    private static function homepage(): array {
        $response = wp_remote_get( home_url( "/" ), [ "timeout" => 20, "redirection" => 3, "sslverify" => false ] );
        return [ "homepage_http" => is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ) ];
    }

    private static function load_admin_includes(): void {
        require_once ABSPATH . "wp-admin/includes/plugin.php";
        require_once ABSPATH . "wp-admin/includes/update.php";
        require_once ABSPATH . "wp-admin/includes/file.php";
        require_once ABSPATH . "wp-admin/includes/misc.php";
    }
}
