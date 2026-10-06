<?php

namespace hpr_distributor\Remote;

use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Lifecycle\DeletionSync;
use hpr_distributor\Migration\LegacyDependencyRetirement;
use hpr_distributor\Setup\HexaPrWireAuthor;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The outlet's remote command list. Each command is one handler reached by
 * either access path in RemoteAccess (shared token or Application Password).
 *
 *   POST /hpr-distributor/v1/pull              import now (optionally one release: slug / source_id)
 *   POST /hpr-distributor/v1/deletions/sync    apply hexaprwire.com's deletion list now
 *   POST /hpr-distributor/v1/author/refresh    re-apply the Hexa PR Wire author profile from hexaprwire.com
 *   GET  /hpr-distributor/v1/health            setup, legacy plugins, last pull, recent posts, recent errors
 *   GET  /hpr-distributor/v1/plugins           Hexa plugin family: installed and latest versions
 *   POST /hpr-distributor/v1/plugins/update    update one family plugin (only while Remote plugin updates is on)
 *   POST /hpr-distributor/v1/fifu/release     remove FIFU data from press releases now (FIFU stays active)
 *   POST /hpr-distributor/v1/echo/disable      switch off only Echo RSS jobs importing Hexa PR Wire (Echo and other feeds untouched)
 *   POST /hpr-distributor/v1/force-sync        existing targeted pull (force-syndication.php)
 */
final class RemoteCommands {
    public const NAMESPACE = "hpr-distributor/v1";

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_action( "rest_api_init", [ self::class, "routes" ] );
        add_filter( "rest_post_dispatch", [ self::class, "no_cache" ], 10, 3 );
    }

    /**
     * Distributor's routes answer per caller (token or administrator), so no
     * page cache may keep a copy: a cached authorized reply would be served to
     * anyone, and a cached 404 would hide the routes after an update.
     */
    public static function no_cache( mixed $response, mixed $server, \WP_REST_Request $request ): mixed {
        if ( $response instanceof \WP_REST_Response && str_starts_with( $request->get_route(), "/" . self::NAMESPACE ) ) {
            $response->header( "Cache-Control", "no-store, no-cache, must-revalidate, private" );
            do_action( "litespeed_control_set_nocache", "Hexa PR Wire Distributor remote route" );
        }
        return $response;
    }

    public static function routes(): void {
        $auth = [ RemoteAccess::class, "authorize" ];
        register_rest_route( self::NAMESPACE, "/pull", [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "pull" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/deletions/sync", [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "deletions" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/author/refresh", [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "author" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/health", [ "methods" => \WP_REST_Server::READABLE, "callback" => [ self::class, "health" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/plugins", [ "methods" => \WP_REST_Server::READABLE, "callback" => [ self::class, "plugins" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/plugins/update", [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "update_plugin" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/fifu/release", [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "release_fifu" ], "permission_callback" => $auth ] );
        register_rest_route( self::NAMESPACE, "/echo/disable", [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "disable_echo" ], "permission_callback" => $auth ] );
    }

    public static function pull( \WP_REST_Request $request ): \WP_REST_Response {
        $targets = NativeFeedImporter::normalize_targets(
            [
                "source_slugs" => self::list( $request->get_param( "slug" ) ),
                "source_ids"   => self::list( $request->get_param( "source_id" ) ),
                "source_urls"  => self::list( $request->get_param( "source_url" ) ),
            ]
        );
        return self::respond( static fn(): array => NativeFeedImporter::run( [ "trigger" => "remote-" . RemoteAccess::path(), "targets" => $targets ] ) );
    }

    public static function deletions(): \WP_REST_Response {
        return self::respond( static fn(): array => DeletionSync::execute( "", "remote-" . RemoteAccess::path() ) );
    }

    public static function author(): \WP_REST_Response {
        return self::respond(
            static function (): array {
                $result = HexaPrWireAuthor::refresh();
                if ( is_wp_error( $result ) ) {
                    throw new \RuntimeException( $result->get_error_message() );
                }
                return $result;
            }
        );
    }

    public static function plugins( \WP_REST_Request $request ): \WP_REST_Response {
        return self::respond( static fn(): array => RemotePluginUpdates::status( (bool) $request->get_param( "refresh" ) ) );
    }

    public static function update_plugin( \WP_REST_Request $request ): \WP_REST_Response {
        return self::respond( static fn(): array => RemotePluginUpdates::update( sanitize_key( (string) $request->get_param( "plugin" ) ) ) );
    }

    public static function release_fifu(): \WP_REST_Response {
        return self::respond( static function (): array {
            $released = 0;
            for ( $i = 0; $i < 20 && \hpr_distributor\Media\FifuCoexistence::pending() > 0; $i++ ) {
                $released += \hpr_distributor\Media\FifuCoexistence::release_existing( 100 );
            }
            return [ "released" => $released, "remaining" => \hpr_distributor\Media\FifuCoexistence::pending() ];
        } );
    }

    public static function disable_echo(): \WP_REST_Response {
        return self::respond( static function (): array {
            $receipt = LegacyDependencyRetirement::apply( LegacyDependencyRetirement::ACTION_DISABLE_ECHO_JOB );
            return [ "disabled_jobs" => (int) $receipt["disabled_echo_rules"], "remaining_jobs" => (int) $receipt["after"]["enabled_matching_echo_rules"] ];
        } );
    }

    public static function health(): \WP_REST_Response {
        return new \WP_REST_Response( self::health_report(), 200 );
    }

    /** @return array<string,mixed> */
    public static function health_report(): array {
        if ( ! function_exists( "is_plugin_active" ) ) {
            require_once ABSPATH . "wp-admin/includes/plugin.php";
        }
        $settings = NativeFeedSettings::get();
        $last_run = get_option( NativeFeedImporter::LAST_RUN_OPTION, [] );
        $history = get_option( NativeFeedImporter::RUN_HISTORY_OPTION, [] );
        $recent = get_posts( [ "post_type" => "press-release", "post_status" => "publish", "numberposts" => 5, "orderby" => "date", "order" => "DESC", "meta_key" => "_hpr_source_id" ] );
        $latest = $recent[0] ?? null;
        $errors = [];
        foreach ( is_array( $history ) ? $history : [] as $run ) {
            if ( is_array( $run ) && ! empty( $run["error"] ) ) {
                $errors[] = [ "time_gmt" => (string) ( $run["ended_gmt"] ?? "" ), "trigger" => (string) ( $run["trigger"] ?? "" ), "error" => (string) $run["error"] ];
            }
        }
        $legacy = LegacyDependencyRetirement::state();
        return [
            "site"        => home_url( "/" ),
            "versions"    => [ "distributor" => \hpr_distributor\Config::$plugin_version, "wordpress" => get_bloginfo( "version" ), "hexa_core" => defined( "HEXA_PLUGIN_CORE_SELECTED_VERSION" ) ? HEXA_PLUGIN_CORE_SELECTED_VERSION : "" ],
            "feed"        => [ "url" => $settings["feed_url"], "outlet" => $settings["publication_slug"], "enabled" => $settings["enabled"], "interval" => $settings["interval"], "category" => $settings["category"] ],
            "next_pull"   => (int) wp_next_scheduled( NativeFeedSettings::CRON_HOOK ),
            "last_pull"   => is_array( $last_run ) ? array_intersect_key( $last_run, array_flip( [ "status", "success", "trigger", "counts", "error", "started_gmt", "ended_gmt" ] ) ) : [],
            "last_post"   => $latest instanceof \WP_Post ? [ "title" => get_the_title( $latest ), "url" => get_permalink( $latest ), "date_gmt" => $latest->post_date_gmt, "source_id" => (string) get_post_meta( $latest->ID, "_hpr_source_id", true ), "source_url" => (string) get_post_meta( $latest->ID, "_hpr_canonical_source_url", true ) ] : null,
            "recent_posts" => array_map( static fn( \WP_Post $post ): array => [ "title" => get_the_title( $post ), "url" => get_permalink( $post ), "date_gmt" => $post->post_date_gmt ], $recent ),
            "author"      => HexaPrWireAuthor::status(),
            "legacy"      => [
                "fifu" => [ "installed" => file_exists( WP_PLUGIN_DIR . "/featured-image-from-url/featured-image-from-url.php" ), "active" => is_plugin_active( "featured-image-from-url/featured-image-from-url.php" ), "press_releases_to_clean" => \hpr_distributor\Media\FifuCoexistence::pending() ],
                "echo" => [ "installed" => file_exists( WP_PLUGIN_DIR . "/rss-feed-post-generator-echo/rss-feed-post-generator-echo.php" ), "active" => (bool) $legacy["echo_rss_active"], "hexa_pr_wire_jobs" => (int) $legacy["enabled_matching_echo_rules"] ],
                "imports_native" => true,
                "warnings" => $legacy["warnings"],
            ],
            "deletions"   => get_option( DeletionSync::RECEIPT_OPTION, [] ),
            "errors"      => array_slice( $errors, 0, 10 ),
        ];
    }

    private static function respond( callable $command ): \WP_REST_Response {
        try {
            return new \WP_REST_Response( [ "success" => true, "path" => RemoteAccess::path(), "result" => $command() ], 200 );
        } catch ( \Throwable $throwable ) {
            return new \WP_REST_Response( [ "success" => false, "path" => RemoteAccess::path(), "message" => $throwable->getMessage() ], 409 );
        }
    }

    /** @return array<int,string> */
    private static function list( mixed $value ): array {
        $items = is_array( $value ) ? $value : preg_split( "/[\r\n,]+/", (string) $value );
        return array_values( array_filter( array_map( "sanitize_text_field", is_array( $items ) ? $items : [] ) ) );
    }
}
