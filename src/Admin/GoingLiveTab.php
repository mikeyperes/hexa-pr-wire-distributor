<?php

namespace hpr_distributor\Admin;

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use hpr_distributor\Import\EchoRuleContract;
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
                "description" => "Checks the distributor and the plugins required by the importer.",
                "subtasks"    => [
                    self::task( "plugins", "Required Plugins", "status_check", "Verifies ACF Pro, Echo RSS, FIFU, and the distributor.", "check_plugins" ),
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
                "label"       => "Echo RSS Importer",
                "type"        => "config_mutation",
                "description" => "Applies the working HerForward importer contract without replacing the destination publication feed.",
                "subtasks"    => [
                    self::task( "echo_contract", "Apply Importer Contract", "config_mutation", "Enables update existing and copy slug, assigns the canonical author, and installs the source identity field map.", "configure_echo_rule" ),
                    self::task( "echo_status", "Verify Importer Rule", "status_check", "Checks the active HexaPRWire press-release rule against the working importer contract.", "check_echo_rule" ),
                ],
            ],
            [
                "id"          => "presentation",
                "label"       => "Presentation Rules",
                "type"        => "feature_toggle",
                "description" => "Applies the destination visibility, editor, and featured-image behavior.",
                "subtasks"    => [
                    self::task( "visibility", "Apply Press Release Visibility", "feature_toggle", "Hides press releases from home, author, category, tag, and related loops while leaving direct press-release URLs available.", "configure_visibility" ),
                    self::task( "fifu_editor", "Keep FIFU Box Collapsible", "feature_toggle", "Disables the hide rule and keeps the FIFU editor box collapsed but expandable.", "configure_fifu_editor" ),
                    self::task( "images", "Verify External Image Dimensions", "status_check", "Checks recent imported press releases for usable external featured-image dimensions.", "check_images" ),
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
            "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php" => "Echo RSS",
            "featured-image-from-url/featured-image-from-url.php" => "Featured Image from URL",
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
            [] === $missing ? "All required importer plugins are active." : "Missing active plugins: " . implode( ", ", $missing ) . ".",
            [
                "plugin_version" => \hpr_distributor\Config::$plugin_version,
                "missing"        => $missing,
            ]
        );
    }

    public static function check_force_sync(): array {
        $settings = function_exists( "hpr_distributor\\hpr_force_sync_get_settings" )
            ? \hpr_distributor\hpr_force_sync_get_settings()
            : [];
        $token = is_array( $settings ) ? trim( (string) ( $settings["shared_token"] ?? "" ) ) : "";
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

    public static function configure_echo_rule(): array {
        $rules = get_option( "echo_rules_list", [] );
        $rules = is_array( $rules ) ? $rules : [];
        $user = HexaPrWireAuthor::find();

        if ( ! $user instanceof \WP_User ) {
            return self::result( false, "Provision the Hexa PR Wire author before configuring Echo RSS." );
        }

        $application = EchoRuleContract::apply( $rules, (int) $user->ID );
        if ( $application["matched"] < 1 ) {
            return self::result( false, "No HexaPRWire press-release Echo RSS rule exists on this site." );
        }

        if ( [] !== $application["changes"] ) {
            update_option( "echo_rules_list", $application["rules"], false );
            wp_cache_delete( "echo_rules_list", "options" );
        }

        return self::result(
            true,
            [] === $application["changes"]
                ? "The Echo RSS importer contract was already correct."
                : "The Echo RSS importer contract was applied.",
            [
                "rules_changed" => count( $application["changes"] ),
                "changes"       => $application["changes"],
            ]
        );
    }

    public static function check_echo_rule(): array {
        $rules = get_option( "echo_rules_list", [] );
        $rules = is_array( $rules ) ? $rules : [];
        $user = HexaPrWireAuthor::find();
        $inspection = EchoRuleContract::inspect(
            $rules,
            $user instanceof \WP_User ? (int) $user->ID : 0
        );
        $passed = $user instanceof \WP_User && $inspection["passed"];

        return self::result(
            $passed,
            $passed
                ? "The active Echo RSS rule matches the working importer contract."
                : "The Echo RSS rule is missing or does not match the importer contract.",
            [ "rules" => $inspection["rules"] ]
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

    public static function configure_fifu_editor(): array {
        update_option( "hpr_ui_cleanup_hide_fifu_featured_image_box", false, false );
        update_option( "hpr_ui_cleanup_collapse_fifu_featured_image_box", true, false );

        $hide = (bool) get_option( "hpr_ui_cleanup_hide_fifu_featured_image_box", false );
        $collapse = (bool) get_option( "hpr_ui_cleanup_collapse_fifu_featured_image_box", false );
        $passed = ! $hide && $collapse;

        return self::result(
            $passed,
            $passed ? "The FIFU editor box remains available and starts collapsed." : "The FIFU editor cleanup state is still conflicting.",
            [
                "hidden"    => $hide,
                "collapsed" => $collapse,
            ]
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
            $metadata = $attachment_id > 0 ? wp_get_attachment_metadata( $attachment_id ) : [];
            $width = is_array( $metadata ) ? (int) ( $metadata["width"] ?? 0 ) : 0;
            $height = is_array( $metadata ) ? (int) ( $metadata["height"] ?? 0 ) : 0;
            $external = $attachment_id > 0 && (bool) preg_match( "#^https?://#i", (string) get_post_meta( $attachment_id, "_wp_attached_file", true ) );
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
