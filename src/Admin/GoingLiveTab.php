<?php

namespace hpr_distributor\Admin;

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Media\ExternalImageSizing;
use hpr_distributor\Migration\LegacyDependencyRetirement;
use hpr_distributor\Setup\HexaPrWireAuthor;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class GoingLiveTab {
    private const NONCE_ACTION = "hpr_distributor_going_live";
    private const AJAX_ACTION = "hpr_distributor_going_live_run_item";

    public static function register(): void {
        add_action( "init", [ self::class, "register_ajax" ], 30 );
    }

    public static function register_ajax(): void {
        static $registered = false;

        if ( $registered ) {
            return;
        }

        ( new GettingStartedChecklistAjaxController( self::config() ) )->register();
        $registered = true;
    }

    public static function render(): void {
        self::register_ajax();
        ( new GettingStartedChecklistRenderer( self::config() ) )->render();
    }

    private static function config(): GettingStartedChecklistConfig {
        return new GettingStartedChecklistConfig(
            [
                "root_id"          => "hpr-going-live-checklist",
                "title"            => "Going Live",
                "description"      => "Runs the distributor launch checks and setup actions in dependency order. Every action reports its verified result.",
                "capability"       => "manage_options",
                "nonce_action"     => self::NONCE_ACTION,
                "nonce_field"      => "nonce",
                "run_action"       => self::AJAX_ACTION,
                "empty_message"    => "No distributor launch checks are registered.",
                "show_type_badges" => false,
                "steps"            => self::steps(),
            ]
        );
    }

    private static function steps(): array {
        return [
            [
                "id"          => "runtime",
                "label"       => "Runtime Readiness",
                "type"        => "status_check",
                "description" => "Checks the Distributor and its one required content-model dependency.",
                "subtasks"    => [
                    self::task( "plugins", "Required Plugins", "status_check", "Verifies ACF Pro and the Distributor. Echo RSS and FIFU are not required.", "check_plugins" ),
                    self::task( "force_sync", "Protected Force Sync", "status_check", "Verifies that a protected distributor force-sync endpoint is configured.", "check_force_sync" ),
                ],
            ],
            [
                "id"          => "identity",
                "label"       => "Importer Identity",
                "type"        => "setup_action",
                "description" => "Creates or repairs the canonical Hexa PR Wire author and its profile.",
                "subtasks"    => [
                    [
                        "id"          => "author",
                        "label"       => "Provision Hexa PR Wire Author",
                        "type"        => "setup_action",
                        "description" => "Ensures hexaprwire / info@hexaprwire.com, profile URLs, author metadata, and the canonical avatar.",
                        "callback"    => [ HexaPrWireAuthor::class, "checklist_provision" ],
                    ],
                    self::task( "category", "Ensure Press Release Category", "setup_action", "Creates the press-release category when it is missing.", "ensure_category" ),
                ],
            ],
            [
                "id"          => "importer",
                "label"       => "Native Importer",
                "type"        => "config_mutation",
                "description" => "Configures the Distributor-owned feed poller and migrates legacy importer metadata without changing destination post IDs.",
                "subtasks"    => [
                    self::task( "native_contract", "Apply Native Import Contract", "config_mutation", "Binds the source feed, canonical author, schedule, and durable source identity fields.", "configure_native_import" ),
                    self::task( "legacy_metadata", "Migrate Legacy Metadata", "config_mutation", "Copies legacy Echo/FIFU source identity and remote-image values into Distributor-owned fields while preserving post IDs.", "migrate_legacy_metadata" ),
                    self::task( "disable_echo_job", "Disable Matching Echo Job", "config_mutation", "Disables only the Hexa PR Wire Echo import rule. Echo RSS and unrelated Echo jobs remain untouched.", "disable_echo_job" ),
                    self::task( "disable_echo_plugin", "Disable Echo RSS Plugin", "config_mutation", "Deactivates only Echo RSS and clears only its scheduled hooks. FIFU remains untouched.", "disable_echo_plugin" ),
                    self::task( "disable_fifu_plugin", "Disable FIFU Plugin", "config_mutation", "Deactivates only FIFU and clears only its scheduled hooks. Echo RSS remains untouched.", "disable_fifu_plugin" ),
                    self::task( "native_status", "Verify Native Importer", "status_check", "Checks the source feed, scheduler, and exclusive dependency-free readiness contract.", "check_native_import" ),
                ],
            ],
            [
                "id"          => "presentation",
                "label"       => "Presentation Rules",
                "type"        => "feature_toggle",
                "description" => "Applies the destination visibility, editor, and featured-image behavior.",
                "subtasks"    => [
                    self::task( "visibility", "Apply Press Release Visibility", "feature_toggle", "Hides press releases from home, author, category, tag, and related loops while leaving direct press-release URLs available.", "configure_visibility" ),
                    self::task( "images", "Verify Remote Featured Images", "status_check", "Checks recent imported press releases for first-party remote image rendering and usable dimensions.", "check_images" ),
                ],
            ],
        ];
    }

    private static function task( string $id, string $label, string $type, string $description, string $method ): array {
        return [
            "id"          => $id,
            "label"       => $label,
            "type"        => $type,
            "description" => $description,
            "callback"    => [ self::class, $method ],
        ];
    }

    public static function check_plugins(): array {
        if ( ! function_exists( "is_plugin_active" ) ) {
            require_once ABSPATH . "wp-admin/includes/plugin.php";
        }

        $required = [
            "advanced-custom-fields-pro/acf.php" => "Advanced Custom Fields Pro",
            \hpr_distributor\Config::get_plugin_basename() => "Hexa PR Wire Distributor",
        ];

        $missing = [];
        foreach ( $required as $plugin_file => $label ) {
            if ( ! is_plugin_active( $plugin_file ) ) {
                $missing[] = $label;
            }
        }

        return self::result(
            [] === $missing,
            [] === $missing ? "All required plugins are active. Legacy importer conflicts are checked separately." : "Missing active plugins: " . implode( ", ", $missing ) . ".",
            [
                "plugin_version" => \hpr_distributor\Config::$plugin_version,
                "missing"        => $missing,
                "echo_rss_required" => false,
                "fifu_required"     => false,
            ]
        );
    }

    public static function check_force_sync(): array {
        $settings = function_exists( "hpr_distributor\\hpr_force_sync_get_settings" )
            ? \hpr_distributor\hpr_force_sync_get_settings()
            : [];
        $token = is_array( $settings ) ? trim( (string) ( $settings["secret_token"] ?? "" ) ) : "";
        $endpoint = function_exists( "hpr_distributor\\hpr_force_sync_get_endpoint_url" )
            ? \hpr_distributor\hpr_force_sync_get_endpoint_url()
            : rest_url( "hpr-distributor/v1/force-sync" );

        return self::result(
            "" !== $token,
            "" !== $token ? "The protected force-sync endpoint is configured." : "The force-sync credential is missing.",
            [
                "endpoint"         => $endpoint,
                "credential_ready" => "" !== $token,
                "method"           => "POST",
            ]
        );
    }

    public static function ensure_category(): array {
        $term = get_term_by( "slug", "press-release", "category" );
        $created = false;

        if ( ! $term instanceof \WP_Term ) {
            $result = wp_insert_term(
                "Press Release",
                "category",
                [
                    "slug"        => "press-release",
                    "description" => "Press releases distributed by Hexa PR Wire.",
                ]
            );

            if ( is_wp_error( $result ) ) {
                return self::result( false, $result->get_error_message(), [ "code" => $result->get_error_code() ] );
            }

            $term = get_term( (int) $result["term_id"], "category" );
            $created = true;
        }

        return self::result(
            $term instanceof \WP_Term,
            $created ? "The press-release category was created." : "The press-release category already exists.",
            [
                "term_id" => $term instanceof \WP_Term ? (int) $term->term_id : 0,
                "created" => $created,
            ]
        );
    }

    public static function configure_native_import(): array {
        NativeFeedSettings::migrate_legacy_echo_rule();
        $settings = NativeFeedSettings::get();
        $user = HexaPrWireAuthor::find();

        if ( ! $user instanceof \WP_User ) {
            return self::result( false, "Provision the Hexa PR Wire author before configuring native imports." );
        }
        if ( "" === $settings["feed_url"] ) {
            return self::result( false, "Configure the Hexa PR Wire publication feed through the authenticated onboarding contract." );
        }
        $settings["author_id"] = (int) $user->ID;
        $saved = NativeFeedSettings::save( $settings );
        return self::result( true, "The native Distributor import contract is configured.", [ "settings" => $saved ] );
    }

    public static function check_native_import(): array {
        $readiness = NativeFeedSettings::readiness();
        return self::result(
            (bool) $readiness["ready"],
            $readiness["ready"]
                ? "The native importer is ready. Echo RSS required: no. FIFU required: no."
                : "The native importer is not ready: " . implode( " ", $readiness["errors"] ),
            $readiness
        );
    }

    public static function migrate_legacy_metadata(): array {
        $migration = NativeFeedImporter::migrate_legacy_posts( 250 );
        return self::result(
            true,
            sprintf( "Legacy metadata migration checked %d posts and migrated %d without changing post IDs.", $migration["checked"], $migration["migrated"] ),
            $migration
        );
    }

    public static function disable_echo_job(): array {
        return self::apply_legacy_action(
            LegacyDependencyRetirement::ACTION_DISABLE_ECHO_JOB,
            "The matching Hexa PR Wire Echo job is disabled. Echo RSS and unrelated jobs were not changed."
        );
    }

    public static function disable_echo_plugin(): array {
        return self::apply_legacy_action(
            LegacyDependencyRetirement::ACTION_DISABLE_ECHO_PLUGIN,
            "Echo RSS is inactive and its scheduled hooks are cleared. FIFU was not changed."
        );
    }

    public static function disable_fifu_plugin(): array {
        return self::apply_legacy_action(
            LegacyDependencyRetirement::ACTION_DISABLE_FIFU_PLUGIN,
            "FIFU is inactive and its scheduled hooks are cleared. Echo RSS was not changed."
        );
    }

    private static function apply_legacy_action( string $action, string $success_message ): array {
        $retirement = LegacyDependencyRetirement::apply( $action );
        return self::result(
            (bool) $retirement["action_success"],
            $retirement["action_success"]
                ? $success_message
                : "The selected legacy action did not complete: " . implode( " ", (array) ( $retirement["after"]["conflicts"] ?? [] ) ),
            $retirement
        );
    }

    public static function configure_visibility(): array {
        $enabled = [
            "hide_press_release_from_home_loop",
            "hide_press_release_from_author_loop",
            "hide_press_release_from_category_loop",
            "hide_press_release_from_tag_loop",
            "hide_press_release_from_related_single_loop",
        ];

        foreach ( $enabled as $option ) {
            update_option( $option, true, false );
        }

        update_option( "add_press_release_to_author_page", false, false );
        update_option( "add_press_release_to_category_archives", false, false );

        $verified = true;
        foreach ( $enabled as $option ) {
            $verified = $verified && (bool) get_option( $option, false );
        }

        $verified = $verified
            && ! get_option( "add_press_release_to_author_page", false )
            && ! get_option( "add_press_release_to_category_archives", false );

        return self::result(
            $verified,
            $verified ? "Press release loop exclusions are enabled without conflicting archive inclusion rules." : "One or more press release visibility settings did not persist.",
            [ "enabled" => $enabled ]
        );
    }

    public static function check_images(): array {
        $posts = get_posts(
            [
                "post_type"      => "press-release",
                "post_status"    => "publish",
                "posts_per_page" => 10,
                "orderby"        => "date",
                "order"          => "DESC",
            ]
        );

        $rows = [];
        $failed = 0;
        foreach ( $posts as $post ) {
            $attachment_id = (int) get_post_thumbnail_id( $post->ID );
            $metadata = $attachment_id > 0 ? ExternalImageSizing::filter_metadata( false, $attachment_id ) : [];
            $width = is_array( $metadata ) ? (int) ( $metadata["width"] ?? 0 ) : 0;
            $height = is_array( $metadata ) ? (int) ( $metadata["height"] ?? 0 ) : 0;
            $external = $attachment_id > 0 && "" !== ExternalImageSizing::attachment_url( $attachment_id );
            $ready = $external && $width > 0 && $height > 0;
            if ( ! $ready ) {
                $failed++;
            }

            $rows[] = [
                "post_id"       => (int) $post->ID,
                "attachment_id" => $attachment_id,
                "external"      => $external,
                "width"         => $width,
                "height"        => $height,
                "ready"         => $ready,
            ];
        }

        return self::result(
            [] !== $rows && 0 === $failed,
            [] === $rows ? "No published press releases were available to inspect." : ( 0 === $failed ? "Recent press release images expose their real external dimensions." : $failed . " recent press release images are missing external dimensions." ),
            [ "posts" => $rows, "failed" => $failed ]
        );
    }


    private static function result( bool $success, string $message, array $context = [] ): array {
        return [
            "success" => $success,
            "message" => $message,
            "logs"    => [
                [
                    "level"   => $success ? "success" : "error",
                    "message" => $message,
                    "context" => $context,
                ],
            ],
            "data"    => [ "verification" => $context ],
        ];
    }
}
