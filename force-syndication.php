<?php

namespace hpr_distributor;

use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

add_action( "init", __NAMESPACE__ . "\\hpr_force_sync_maybe_initialize", 5 );
add_action( "rest_api_init", __NAMESPACE__ . "\\hpr_force_sync_register_rest_routes" );

function hpr_force_sync_get_shared_token(): string {
    return (string) hpr_force_sync_get_settings()["secret_token"];
}

function hpr_force_sync_maybe_initialize(): void {
    $settings = get_option( "hpr_force_sync_settings", [] );
    $settings = is_array( $settings ) ? $settings : [];
    $changed = false;

    if ( empty( $settings["secret_token"] ) ) {
        $settings["secret_token"] = wp_generate_password( 64, false, false );
        $settings["token_mode"] = "generated-option";
        $changed = true;
    } elseif ( empty( $settings["token_mode"] ) || "shared-hardcoded" === $settings["token_mode"] ) {
        $settings["token_mode"] = "stored-option";
        $changed = true;
    }
    if ( empty( $settings["allowed_host"] ) ) {
        $settings["allowed_host"] = "hexaprwire.com";
        $changed = true;
    }
    if ( $changed || null === get_option( "hpr_force_sync_settings", null ) ) {
        update_option( "hpr_force_sync_settings", $settings, false );
    }
}

function hpr_force_sync_get_settings(): array {
    hpr_force_sync_maybe_initialize();
    return wp_parse_args(
        get_option( "hpr_force_sync_settings", [] ),
        [ "secret_token" => "", "allowed_host" => "hexaprwire.com", "token_mode" => "generated-option" ]
    );
}

function hpr_force_sync_register_rest_routes(): void {
    register_rest_route(
        "hpr-distributor/v1",
        "/force-sync",
        [
            "methods"             => \WP_REST_Server::CREATABLE,
            "callback"            => __NAMESPACE__ . "\\hpr_force_sync_rest_callback",
            "permission_callback" => __NAMESPACE__ . "\\hpr_force_sync_rest_permission",
        ]
    );
}

function hpr_force_sync_rest_permission( \WP_REST_Request $request ) {
    $token = hpr_force_sync_get_request_token( $request );
    $expected = (string) hpr_force_sync_get_settings()["secret_token"];
    if ( "" !== $expected && hash_equals( $expected, $token ) ) {
        return true;
    }
    return new \WP_Error( "hpr_force_sync_forbidden", "Unauthorized.", [ "status" => 403 ] );
}

function hpr_force_sync_rest_callback( \WP_REST_Request $request ) {
    nocache_headers();
    if ( ! defined( "DONOTCACHEPAGE" ) ) {
        define( "DONOTCACHEPAGE", true );
    }
    do_action( "litespeed_control_set_nocache", "hpr-force-sync" );

    $expected = (string) hpr_force_sync_get_settings()["secret_token"];
    if ( "" === $expected || ! hash_equals( $expected, hpr_force_sync_get_request_token( $request ) ) ) {
        return hpr_force_sync_rest_response( [ "success" => false, "message" => "Unauthorized.", "error" => "invalid_force_sync_key" ], 403 );
    }

    try {
        $targets = hpr_force_sync_resolve_targets( $request, hpr_force_sync_get_imported_post_map() );
        $result = NativeFeedImporter::run(
            [
                "trigger"     => "force-sync",
                "dry_run"     => hpr_force_sync_to_bool( $request->get_param( "dry_run" ) ),
                "feed_action" => sanitize_key( (string) $request->get_param( "feed_action" ) ),
                "targets"     => $targets,
            ]
        );

        if ( NativeFeedImporter::has_targets( $targets ) && 0 === (int) $result["items_processed"] ) {
            return hpr_force_sync_rest_response(
                [
                    "success"               => false,
                    "message"               => "No matching source item was found for the requested target.",
                    "contract_version"      => NativeFeedSettings::CONTRACT_VERSION,
                    "requested_targets"     => $targets,
                    "effective_feed_url"    => $result["feed_url"],
                    "feed_items_discovered" => $result["items_discovered"],
                    "matched_feed_items"    => 0,
                ],
                404
            );
        }

        $result["message"] = ! empty( $result["dry_run"] ) ? "Native import dry run completed." : "Native force sync completed.";
        $result["publication"] = home_url( "/" );
        $result["requested_targets"] = $targets;
        $result["effective_feed_url"] = $result["feed_url"];
        $result["feed_items_discovered"] = $result["items_discovered"];
        $result["matched_feed_items"] = $result["items_processed"];
        $result["importer"] = "hexa-pr-wire-distributor-native";
        return hpr_force_sync_rest_response( $result, $result["success"] ? 200 : 207 );
    } catch ( \Throwable $throwable ) {
        return hpr_force_sync_rest_response(
            [ "success" => false, "contract_version" => NativeFeedSettings::CONTRACT_VERSION, "message" => $throwable->getMessage(), "error" => get_class( $throwable ) ],
            500
        );
    }
}

function hpr_force_sync_rest_response( array $body, int $status = 200 ): \WP_REST_Response {
    $response = new \WP_REST_Response( $body, $status );
    $response->header( "Cache-Control", "no-store, no-cache, must-revalidate, max-age=0" );
    $response->header( "Pragma", "no-cache" );
    $response->header( "Expires", "Wed, 11 Jan 1984 05:00:00 GMT" );
    $response->header( "X-LiteSpeed-Cache-Control", "no-cache" );
    return $response;
}

function hpr_force_sync_discover_rule( bool $active_only = true ): array {
    unset( $active_only );
    $settings = NativeFeedSettings::get();
    if ( "" === $settings["feed_url"] ) {
        return [];
    }
    return [
        "id"               => "native",
        "active"           => (bool) $settings["enabled"],
        "feed_url"         => $settings["feed_url"],
        "post_type"        => "press-release",
        "publication_slug" => $settings["publication_slug"],
        "identity"         => "_hpr_source_identity",
        "importer"         => "native",
    ];
}

function hpr_force_sync_get_detected_publication_slug(): string {
    return (string) NativeFeedSettings::get()["publication_slug"];
}

function hpr_force_sync_get_detected_feed_url(): string {
    return (string) NativeFeedSettings::get()["feed_url"];
}

function hpr_force_sync_get_endpoint_url(): string {
    return rest_url( "hpr-distributor/v1/force-sync" );
}

function hpr_force_sync_get_signed_base_url(): string {
    return hpr_force_sync_get_endpoint_url();
}

function hpr_force_sync_get_request_token( \WP_REST_Request $request ): string {
    $header_token = trim( (string) $request->get_header( "x-hpr-token" ) );
    if ( "" !== $header_token ) {
        return $header_token;
    }
    foreach ( [ "token", "sync_key", "key" ] as $param ) {
        $value = trim( (string) $request->get_param( $param ) );
        if ( "" !== $value ) {
            return $value;
        }
    }
    return "";
}

function hpr_force_sync_resolve_targets( \WP_REST_Request $request, array $before_map = [] ): array {
    $source_slugs = array_merge( hpr_force_sync_normalize_list_param( $request->get_param( "slug" ) ), hpr_force_sync_normalize_list_param( $request->get_param( "slugs" ) ) );
    $source_urls = array_merge( hpr_force_sync_normalize_list_param( $request->get_param( "source_url" ) ), hpr_force_sync_normalize_list_param( $request->get_param( "source_urls" ) ) );
    $source_ids = array_merge( hpr_force_sync_normalize_list_param( $request->get_param( "source_id" ) ), hpr_force_sync_normalize_list_param( $request->get_param( "source_ids" ) ) );
    $post_ids = array_map( "intval", array_merge( hpr_force_sync_normalize_list_param( $request->get_param( "post_id" ) ), hpr_force_sync_normalize_list_param( $request->get_param( "post_ids" ) ) ) );

    foreach ( $post_ids as $post_id ) {
        $row = $before_map["post_ids"][ $post_id ] ?? [];
        if ( ! empty( $row["source_slug"] ) ) {
            $source_slugs[] = $row["source_slug"];
        }
        if ( ! empty( $row["source_url"] ) ) {
            $source_urls[] = $row["source_url"];
        }
        if ( ! empty( $row["source_id"] ) ) {
            $source_ids[] = $row["source_id"];
        }
    }

    return NativeFeedImporter::normalize_targets( [ "source_slugs" => $source_slugs, "source_urls" => $source_urls, "source_ids" => $source_ids ] )
        + [ "local_post_ids" => array_values( array_unique( array_filter( $post_ids ) ) ) ];
}

function hpr_force_sync_get_imported_post_map( $legacy_rule_id = null ): array {
    unset( $legacy_rule_id );
    $ids = get_posts(
        [
            "post_type"              => "press-release",
            "post_status"            => [ "publish", "draft", "pending", "private", "future", "trash" ],
            "posts_per_page"         => -1,
            "fields"                 => "ids",
            "no_found_rows"          => true,
            "update_post_meta_cache" => false,
            "update_post_term_cache" => false,
        ]
    );
    $map = [ "source_urls" => [], "source_ids" => [], "post_ids" => [] ];
    foreach ( $ids as $post_id ) {
        $post_id = (int) $post_id;
        $source_url = "";
        foreach ( [ "_hpr_canonical_source_url", "original_post_url", "echo_post_full_url", "echo_post_url" ] as $key ) {
            $source_url = SourceIdentity::canonical_url( (string) get_post_meta( $post_id, $key, true ) );
            if ( "" !== $source_url ) {
                break;
            }
        }
        $source_id = (string) get_post_meta( $post_id, "_hpr_source_id", true );
        if ( "" === $source_id && "" !== $source_url ) {
            $source_id = SourceIdentity::source_id( (string) get_post_meta( $post_id, "_hpr_source_guid", true ), $source_url );
        }
        $source_slug = sanitize_title( (string) get_post_meta( $post_id, "original_post_slug", true ) );
        if ( "" === $source_slug && "" !== $source_url ) {
            $source_slug = sanitize_title( basename( (string) wp_parse_url( $source_url, PHP_URL_PATH ) ) );
        }
        $row = [
            "post_id"      => $post_id,
            "post_title"   => get_the_title( $post_id ),
            "live_url"     => get_permalink( $post_id ),
            "source_url"   => $source_url,
            "source_id"    => $source_id,
            "source_slug"  => $source_slug,
            "modified_gmt" => (string) get_post_field( "post_modified_gmt", $post_id ),
        ];
        $map["post_ids"][ $post_id ] = $row;
        if ( "" !== $source_url ) {
            $map["source_urls"][ $source_url ] = $row;
        }
        if ( "" !== $source_id ) {
            $map["source_ids"][ $source_id ] = $row;
        }
    }
    return $map;
}

function hpr_force_sync_normalize_list_param( $value ): array {
    if ( is_array( $value ) ) {
        return array_values( array_filter( array_map( "sanitize_text_field", $value ) ) );
    }
    if ( "" === trim( (string) $value ) ) {
        return [];
    }
    $parts = preg_split( "/[\r\n,]+/", (string) $value );
    return is_array( $parts ) ? array_values( array_filter( array_map( "sanitize_text_field", $parts ) ) ) : [];
}

function hpr_force_sync_normalize_url( $url ): string {
    return SourceIdentity::canonical_url( (string) $url );
}

function hpr_force_sync_to_bool( $value ): bool {
    return is_bool( $value ) ? $value : in_array( strtolower( (string) $value ), [ "1", "true", "yes", "on" ], true );
}
