<?php

namespace hpr_distributor\Api;

use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class OnboardingContract {
    private const ROUTE_NAMESPACE = "hpr-distributor/v1";
    private const OPERATIONS_OPTION = "hpr_distributor_onboarding_operations";
    private const MAX_OPERATIONS = 50;
    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        add_action( "rest_api_init", [ self::class, "register_routes" ] );
        self::$registered = true;
    }

    public static function register_routes(): void {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/contract",
            [ "methods" => \WP_REST_Server::READABLE, "callback" => [ self::class, "contract" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/inspect",
            [ "methods" => \WP_REST_Server::READABLE, "callback" => [ self::class, "inspect" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/plan",
            [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "plan" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/configure",
            [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "configure" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/reconcile",
            [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "reconcile" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/verify",
            [ "methods" => [ \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ], "callback" => [ self::class, "verify" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/force-sync",
            [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "force_sync" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
        register_rest_route(
            self::ROUTE_NAMESPACE,
            "/onboarding/rollback",
            [ "methods" => \WP_REST_Server::CREATABLE, "callback" => [ self::class, "rollback" ], "permission_callback" => [ self::class, "authorize" ] ]
        );
    }

    public static function authorize(): bool {
        return current_user_can( "manage_options" );
    }

    public static function contract(): array {
        return [
            "contract"              => "hexa-pr-wire-distributor-onboarding",
            "contract_version"      => NativeFeedSettings::CONTRACT_VERSION,
            "plugin_version"        => \hpr_distributor\Config::$plugin_version,
            "authentication"        => "WordPress authenticated user with manage_options",
            "accepts_login_secrets" => false,
            "echo_rss_required"     => false,
            "fifu_required"         => false,
            "images_remain_on_source" => true,
            "routes"                => [
                "inspect"   => rest_url( self::ROUTE_NAMESPACE . "/onboarding/inspect" ),
                "plan"      => rest_url( self::ROUTE_NAMESPACE . "/onboarding/plan" ),
                "configure" => rest_url( self::ROUTE_NAMESPACE . "/onboarding/configure" ),
                "reconcile" => rest_url( self::ROUTE_NAMESPACE . "/onboarding/reconcile" ),
                "verify"    => rest_url( self::ROUTE_NAMESPACE . "/onboarding/verify" ),
                "rollback"  => rest_url( self::ROUTE_NAMESPACE . "/onboarding/rollback" ),
                "force_sync"=> rest_url( self::ROUTE_NAMESPACE . "/onboarding/force-sync" ),
            ],
            "force_sync_result_fields" => [
                "source_identity",
                "source_id",
                "source_url",
                "destination_post_id",
                "destination_url",
                "canonical_url",
                "image_source_url",
                "image_url",
                "dedupe",
                "deduplication",
            ],
        ];
    }

    public static function inspect(): array {
        $settings = NativeFeedSettings::get();
        $force_sync_settings = get_option( "hpr_force_sync_settings", [] );
        $force_sync_settings = is_array( $force_sync_settings ) ? $force_sync_settings : [];
        return [
            "contract"   => self::contract(),
            "settings"   => self::public_settings( $settings ),
            "readiness"  => NativeFeedSettings::readiness(),
            "last_run"   => get_option( NativeFeedImporter::LAST_RUN_OPTION, [] ),
            "migration"  => get_option( NativeFeedSettings::LEGACY_MIGRATION_OPTION, [] ),
            "force_sync" => [
                "endpoint"              => rest_url( self::ROUTE_NAMESPACE . "/onboarding/force-sync" ),
                "authentication"        => "WordPress authenticated user with manage_options",
                "legacy_token_endpoint" => rest_url( self::ROUTE_NAMESPACE . "/force-sync" ),
                "legacy_token_ready"    => "" !== trim( (string) ( $force_sync_settings["secret_token"] ?? "" ) ),
            ],
        ];
    }

    public static function plan( \WP_REST_Request $request ) {
        $payload = self::payload( $request );
        $guard = self::reject_sensitive_payload( $payload );
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $operation_id = self::operation_id( $payload, false );
        if ( is_wp_error( $operation_id ) ) {
            return $operation_id;
        }

        $current = NativeFeedSettings::get();
        $desired = NativeFeedSettings::normalize( array_merge( $current, self::settings_from_payload( $payload ) ) );
        $validation = NativeFeedSettings::validate( $desired );

        return [
            "contract_version" => NativeFeedSettings::CONTRACT_VERSION,
            "operation_id"     => $operation_id,
            "safe_to_apply"    => $validation["valid"],
            "current"          => self::public_settings( $current ),
            "desired"          => self::public_settings( $desired ),
            "changes"          => self::diff( $current, $desired ),
            "errors"           => $validation["errors"],
            "side_effects"     => [ "settings_write", "cron_reconciliation", "no_content_import" ],
        ];
    }

    public static function configure( \WP_REST_Request $request ) {
        return self::apply( $request, false );
    }

    public static function reconcile( \WP_REST_Request $request ) {
        $result = self::apply( $request, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $result["reconciliation"] = [
            "schedule"  => NativeFeedSettings::reconcile_schedule(),
            "migration" => NativeFeedImporter::migrate_legacy_posts( 100 ),
        ];
        $result["verification"] = NativeFeedSettings::readiness();
        return $result;
    }

    public static function verify(): array {
        return [
            "contract_version" => NativeFeedSettings::CONTRACT_VERSION,
            "readiness"        => NativeFeedSettings::readiness(),
            "requirements"     => [ "echo_rss_required" => false, "fifu_required" => false ],
            "last_run"         => get_option( NativeFeedImporter::LAST_RUN_OPTION, [] ),
            "migration"        => get_option( NativeFeedSettings::LEGACY_MIGRATION_OPTION, [] ),
        ];
    }

    public static function force_sync( \WP_REST_Request $request ) {
        $payload = self::payload( $request );
        $guard = self::reject_sensitive_payload( $payload );
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $operation_id = self::operation_id( $payload, true );
        if ( is_wp_error( $operation_id ) ) {
            return $operation_id;
        }

        $source = isset( $payload["source"] ) && is_array( $payload["source"] ) ? $payload["source"] : $payload;
        $source_id = sanitize_text_field( (string) ( $source["source_id"] ?? "" ) );
        $source_url = SourceIdentity::canonical_url( (string) ( $source["source_url"] ?? "" ) );
        $source_slug = sanitize_title( (string) ( $source["source_slug"] ?? "" ) );
        if ( "" === $source_id && "" === $source_url && "" === $source_slug ) {
            return new \WP_Error( "hpr_force_sync_source_required", "One reviewed source release ID, canonical URL, or slug is required.", [ "status" => 422 ] );
        }

        $settings = NativeFeedSettings::get();
        if ( "" !== $source_url && ! SourceIdentity::allowed_host( $source_url, (string) $settings["allowed_host"] ) ) {
            return new \WP_Error( "hpr_force_sync_source_host_invalid", "The source release URL must remain on the configured Hexa PR Wire host.", [ "status" => 422 ] );
        }

        try {
            $result = NativeFeedImporter::run(
                [
                    "trigger" => "onboarding-force-sync",
                    "targets" => [
                        "source_ids"   => array_filter( [ $source_id ] ),
                        "source_urls"  => array_filter( [ $source_url ] ),
                        "source_slugs" => array_filter( [ $source_slug ] ),
                    ],
                ]
            );
        } catch ( \Throwable $throwable ) {
            return new \WP_Error( "hpr_onboarding_force_sync_failed", $throwable->getMessage(), [ "status" => 502 ] );
        }

        $items = array_values( (array) ( $result["items"] ?? [] ) );
        if ( 1 !== (int) ( $result["items_processed"] ?? 0 ) || 1 !== count( $items ) ) {
            return new \WP_Error(
                "hpr_force_sync_source_not_unique",
                "The reviewed source release did not resolve to exactly one feed item.",
                [ "status" => 409, "items_processed" => (int) ( $result["items_processed"] ?? 0 ) ]
            );
        }

        $item = $items[0];
        $selector_matches = ( "" === $source_id || $source_id === (string) ( $item["source_id"] ?? "" ) )
            && ( "" === $source_url || $source_url === SourceIdentity::canonical_url( (string) ( $item["source_url"] ?? "" ) ) )
            && ( "" === $source_slug || $source_slug === sanitize_title( (string) ( $item["source_slug"] ?? "" ) ) );
        if ( ! $selector_matches ) {
            return new \WP_Error( "hpr_force_sync_source_mismatch", "The imported feed item does not match every supplied source selector.", [ "status" => 409 ] );
        }
        if ( empty( $result["success"] ) || "failed" === (string) ( $item["action"] ?? "" ) ) {
            return new \WP_Error( "hpr_onboarding_force_sync_failed", (string) ( $item["error"] ?? "The native importer reported a failure." ), [ "status" => 502 ] );
        }

        $post_id = absint( $item["destination_post_id"] ?? 0 );
        $post = $post_id > 0 ? get_post( $post_id ) : null;
        if ( ! $post instanceof \WP_Post || "press-release" !== $post->post_type ) {
            return new \WP_Error( "hpr_force_sync_destination_missing", "The destination press release could not be read back.", [ "status" => 500 ] );
        }

        $content = (string) get_post_field( "post_content", $post_id );
        $title = (string) get_post_field( "post_title", $post_id );
        $source_content_hash = (string) ( $item["source_content_sha256"] ?? "" );
        $content_hash = hash( "sha256", $content );
        $category_slugs = wp_get_post_terms( $post_id, "category", [ "fields" => "slugs" ] );
        $category_slugs = is_wp_error( $category_slugs ) ? [] : array_values( array_map( "strval", (array) $category_slugs ) );
        $image_url = esc_url_raw( (string) ( $item["image_url"] ?? "" ) );
        $image_host = strtolower( (string) wp_parse_url( $image_url, PHP_URL_HOST ) );
        $deduplication = self::deduplication_proof( $item, $post_id );
        if ( ! $deduplication["proven"] ) {
            return new \WP_Error(
                "hpr_force_sync_deduplication_unproven",
                "The imported source identity did not resolve uniquely to the returned destination post.",
                [ "status" => 409, "deduplication" => $deduplication ]
            );
        }

        return [
            "success"          => true,
            "operation_id"     => $operation_id,
            "contract_version" => NativeFeedSettings::CONTRACT_VERSION,
            "source"           => [
                "identity"      => (string) ( $item["source_identity"] ?? "" ),
                "id"            => (string) ( $item["source_id"] ?? "" ),
                "url"           => (string) ( $item["source_url"] ?? "" ),
                "slug"          => (string) ( $item["source_slug"] ?? "" ),
                "content_sha256"=> $source_content_hash,
            ],
            "destination"      => [
                "post_id"       => $post_id,
                "url"           => (string) get_permalink( $post_id ),
                "post_type"     => $post->post_type,
                "status"        => (string) get_post_status( $post_id ),
                "canonical_url" => (string) ( $item["canonical_url"] ?? "" ),
            ],
            "category"         => [
                "required_slug" => "press-release",
                "assigned"      => in_array( "press-release", $category_slugs, true ),
                "slugs"         => $category_slugs,
            ],
            "structure"        => [
                "title"                  => $title,
                "title_matches_source"   => $title === (string) ( $item["source_title"] ?? "" ),
                "content_sha256"         => $content_hash,
                "content_matches_source" => "" !== $source_content_hash && hash_equals( $source_content_hash, $content_hash ),
                "content_bytes"          => strlen( $content ),
                "headings"               => self::tag_count( $content, "h[1-6]" ),
                "lists"                  => self::tag_count( $content, "(?:ul|ol)" ),
                "blockquotes"            => self::tag_count( $content, "blockquote" ),
                "links"                  => self::tag_count( $content, "a" ),
            ],
            "image"            => [
                "present"       => "" !== $image_url,
                "url"           => $image_url,
                "host"          => $image_host,
                "source_hosted" => "" === $image_url || SourceIdentity::allowed_host( $image_url, (string) $settings["allowed_host"] ),
            ],
            "deduplication"    => $deduplication,
            "import"           => [
                "action"          => (string) ( $item["action"] ?? "" ),
                "items_processed" => (int) ( $result["items_processed"] ?? 0 ),
                "counts"          => (array) ( $result["counts"] ?? [] ),
            ],
            "requirements"     => [
                "echo_rss_required" => false,
                "fifu_required"     => false,
                "images_remote_only"=> true,
            ],
        ];
    }

    /** @return array{proven:bool,matched:bool,post_id:int,collision:bool,matched_by:array,candidate_post_ids:array} */
    public static function deduplication_proof( array $item, int $destination_post_id ): array {
        $readback = NativeFeedImporter::find_existing_post( $item );
        $matched = (bool) ( $readback["matched"] ?? false );
        $post_id = absint( $readback["post_id"] ?? 0 );
        $collision = (bool) ( $readback["collision"] ?? false );

        return [
            "proven"             => $matched && $post_id === $destination_post_id && ! $collision,
            "matched"            => $matched,
            "post_id"            => $post_id,
            "collision"          => $collision,
            "matched_by"         => array_values( (array) ( $readback["matched_by"] ?? [] ) ),
            "candidate_post_ids" => array_values( array_map( "absint", (array) ( $readback["candidate_post_ids"] ?? [] ) ) ),
        ];
    }

    public static function rollback( \WP_REST_Request $request ) {
        $payload = self::payload( $request );
        $guard = self::reject_sensitive_payload( $payload );
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $operation_id = self::operation_id( $payload, true );
        if ( is_wp_error( $operation_id ) ) {
            return $operation_id;
        }

        $operations = self::operations();
        if ( ! isset( $operations[ $operation_id ] ) ) {
            return new \WP_Error( "hpr_operation_not_found", "The operation ID was not found.", [ "status" => 404 ] );
        }
        $operation = $operations[ $operation_id ];
        if ( "rolled_back" === (string) ( $operation["status"] ?? "" ) ) {
            return [ "success" => true, "idempotent" => true, "operation" => $operation, "settings" => self::public_settings( NativeFeedSettings::get() ) ];
        }

        $current = NativeFeedSettings::get();
        if ( self::fingerprint( $current ) !== (string) ( $operation["after_fingerprint"] ?? "" ) ) {
            return new \WP_Error( "hpr_rollback_conflict", "Current settings changed after this operation; rollback would overwrite another run.", [ "status" => 409 ] );
        }

        $before = NativeFeedSettings::normalize( (array) ( $operation["before"] ?? [] ) );
        update_option( NativeFeedSettings::OPTION, $before, false );
        NativeFeedSettings::reconcile_schedule( $before );
        $operation["status"] = "rolled_back";
        $operation["rolled_back_gmt"] = current_time( "mysql", true );
        $operations[ $operation_id ] = $operation;
        self::save_operations( $operations );

        return [ "success" => true, "idempotent" => false, "operation" => $operation, "settings" => self::public_settings( $before ), "verification" => NativeFeedSettings::readiness() ];
    }

    private static function apply( \WP_REST_Request $request, bool $reconcile ) {
        $payload = self::payload( $request );
        $guard = self::reject_sensitive_payload( $payload );
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $operation_id = self::operation_id( $payload, true );
        if ( is_wp_error( $operation_id ) ) {
            return $operation_id;
        }

        $current = NativeFeedSettings::get();
        $desired = NativeFeedSettings::normalize( array_merge( $current, self::settings_from_payload( $payload ) ) );
        $validation = NativeFeedSettings::validate( $desired );
        if ( ! $validation["valid"] ) {
            return new \WP_Error( "hpr_invalid_onboarding_settings", implode( " ", $validation["errors"] ), [ "status" => 422, "errors" => $validation["errors"] ] );
        }

        $input_fingerprint = self::fingerprint( self::public_settings( $desired ) );
        $operations = self::operations();
        if ( isset( $operations[ $operation_id ] ) ) {
            $operation = $operations[ $operation_id ];
            if ( (string) ( $operation["input_fingerprint"] ?? "" ) !== $input_fingerprint ) {
                return new \WP_Error( "hpr_operation_id_conflict", "The operation ID is already bound to different settings.", [ "status" => 409 ] );
            }
            return [
                "success"      => true,
                "idempotent"   => true,
                "reconciled"   => $reconcile,
                "operation"    => $operation,
                "settings"     => self::public_settings( NativeFeedSettings::get() ),
                "verification" => NativeFeedSettings::readiness(),
            ];
        }

        $saved = NativeFeedSettings::save( $desired );
        $operation = [
            "operation_id"      => $operation_id,
            "status"            => "applied",
            "input_fingerprint" => $input_fingerprint,
            "before"            => $current,
            "after"             => $saved,
            "after_fingerprint" => self::fingerprint( $saved ),
            "changes"           => self::diff( $current, $saved ),
            "applied_gmt"       => current_time( "mysql", true ),
            "user_id"           => get_current_user_id(),
        ];
        $operations[ $operation_id ] = $operation;
        self::save_operations( $operations );

        return [
            "success"      => true,
            "idempotent"   => false,
            "reconciled"   => $reconcile,
            "operation"    => $operation,
            "settings"     => self::public_settings( $saved ),
            "verification" => NativeFeedSettings::readiness(),
        ];
    }

    private static function payload( \WP_REST_Request $request ): array {
        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            $payload = $request->get_body_params();
        }
        return is_array( $payload ) ? $payload : [];
    }

    private static function settings_from_payload( array $payload ): array {
        $settings = isset( $payload["settings"] ) && is_array( $payload["settings"] ) ? $payload["settings"] : $payload;
        $allowed = [ "feed_url", "publication_slug", "enabled", "schedule_enabled", "interval", "author_id", "post_status", "max_items" ];
        return array_intersect_key( $settings, array_flip( $allowed ) );
    }

    private static function operation_id( array $payload, bool $required ) {
        $operation_id = sanitize_text_field( (string) ( $payload["operation_id"] ?? "" ) );
        if ( "" === $operation_id && ! $required ) {
            return "plan-" . substr( hash( "sha256", wp_json_encode( self::settings_from_payload( $payload ) ) ), 0, 20 );
        }
        if ( ! preg_match( "/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/", $operation_id ) ) {
            return new \WP_Error( "hpr_invalid_operation_id", "A stable 8-128 character operation_id is required.", [ "status" => 422 ] );
        }
        return $operation_id;
    }

    private static function reject_sensitive_payload( array $payload ) {
        $blocked = [ "password", "passwd", "secret", "token", "cookie", "authorization", "application_password", "login_password" ];
        $walk = static function ( array $values ) use ( &$walk, $blocked ): array {
            $found = [];
            foreach ( $values as $key => $value ) {
                $normalized = strtolower( str_replace( [ "-", " " ], "_", (string) $key ) );
                foreach ( $blocked as $needle ) {
                    if ( str_contains( $normalized, $needle ) ) {
                        $found[] = (string) $key;
                        break;
                    }
                }
                if ( is_array( $value ) ) {
                    $found = array_merge( $found, $walk( $value ) );
                }
            }
            return $found;
        };
        $found = array_values( array_unique( $walk( $payload ) ) );
        return [] === $found ? true : new \WP_Error( "hpr_secrets_not_accepted", "Login credentials and secrets must not be sent to or stored by the Distributor contract.", [ "status" => 422, "blocked_fields" => $found ] );
    }

    private static function public_settings( array $settings ): array {
        return array_intersect_key( NativeFeedSettings::normalize( $settings ), array_flip( [ "feed_url", "publication_slug", "enabled", "schedule_enabled", "interval", "author_id", "post_status", "max_items", "allowed_host", "configured_by" ] ) );
    }

    private static function diff( array $before, array $after ): array {
        $changes = [];
        foreach ( self::public_settings( $after ) as $key => $value ) {
            $old = self::public_settings( $before )[ $key ] ?? null;
            if ( $old !== $value ) {
                $changes[ $key ] = [ "before" => $old, "after" => $value ];
            }
        }
        return $changes;
    }

    private static function fingerprint( array $value ): string {
        ksort( $value );
        return hash( "sha256", wp_json_encode( $value ) );
    }

    private static function tag_count( string $html, string $tag_pattern ): int {
        $matched = preg_match_all( "#<" . $tag_pattern . "\\b#i", $html );
        return false === $matched ? 0 : $matched;
    }

    private static function operations(): array {
        $operations = get_option( self::OPERATIONS_OPTION, [] );
        return is_array( $operations ) ? $operations : [];
    }

    private static function save_operations( array $operations ): void {
        if ( count( $operations ) > self::MAX_OPERATIONS ) {
            $operations = array_slice( $operations, -self::MAX_OPERATIONS, null, true );
        }
        update_option( self::OPERATIONS_OPTION, $operations, false );
    }
}
