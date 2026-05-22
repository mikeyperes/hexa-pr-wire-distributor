<?php
namespace hpr_distributor;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

add_filter( "rest_request_after_callbacks", __NAMESPACE__ . "\\hpr_force_sync_assets_after_callback", 10, 3 );

function hpr_force_sync_assets_after_callback( $response, array $handler, \WP_REST_Request $request ) {
    if ( "/hpr-distributor/v1/force-sync" !== $request->get_route() || is_wp_error( $response ) || ! $response instanceof \WP_REST_Response ) {
        return $response;
    }

    $data = $response->get_data();
    if ( empty( $data["success"] ) || ! empty( $data["dry_run"] ) || empty( $data["effective_feed_url"] ) || ! isset( $data["rule"]["id"] ) ) {
        return $response;
    }

    try {
        $feed_items = hpr_force_sync_assets_fetch_feed_items( $data["effective_feed_url"] );
        $targets    = isset( $data["requested_targets"] ) && is_array( $data["requested_targets"] ) ? $data["requested_targets"] : [];

        if ( function_exists( __NAMESPACE__ . "\\hpr_force_sync_filter_feed_items_by_targets" ) && function_exists( __NAMESPACE__ . "\\hpr_force_sync_has_targets" ) && hpr_force_sync_has_targets( $targets ) ) {
            $feed_items = hpr_force_sync_filter_feed_items_by_targets( $feed_items, $targets );
        }

        $post_map = hpr_force_sync_get_imported_post_map( (int) $data["rule"]["id"] );
        $data["asset_sync"]  = hpr_force_sync_assets_sync_feed_items( $post_map, $feed_items );
        $data["cache_purge"] = hpr_force_sync_assets_purge_feed_item_posts( $post_map, $feed_items );
    } catch ( \Throwable $throwable ) {
        $data["asset_sync"] = [
            "checked" => 0,
            "updated" => 0,
            "errors"  => [ $throwable->getMessage() ],
        ];
    }

    $response->set_data( $data );
    return $response;
}

function hpr_force_sync_assets_fetch_feed_items( $feed_url ) {
    $response = wp_remote_get(
        $feed_url,
        [
            "timeout"     => 90,
            "redirection" => 5,
            "headers"     => [
                "Accept" => "application/rss+xml, application/xml, text/xml;q=0.9",
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        throw new \RuntimeException( "Feed request failed: " . $response->get_error_message() );
    }

    if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        throw new \RuntimeException( "Feed request returned HTTP " . (int) wp_remote_retrieve_response_code( $response ) . "." );
    }

    $body = wp_remote_retrieve_body( $response );
    libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $body, "SimpleXMLElement", LIBXML_NOCDATA );
    if ( false === $xml || empty( $xml->channel->item ) ) {
        return [];
    }

    $items = [];
    foreach ( $xml->channel->item as $item ) {
        $source_url = function_exists( __NAMESPACE__ . "\\hpr_force_sync_normalize_url" ) ? hpr_force_sync_normalize_url( (string) $item->post_url ) : esc_url_raw( (string) $item->post_url );
        if ( empty( $source_url ) ) {
            $source_url = function_exists( __NAMESPACE__ . "\\hpr_force_sync_normalize_url" ) ? hpr_force_sync_normalize_url( (string) $item->link ) : esc_url_raw( (string) $item->link );
        }

        $source_slug = sanitize_title( (string) $item->post_slug );
        if ( empty( $source_slug ) && ! empty( $source_url ) ) {
            $source_slug = sanitize_title( basename( wp_parse_url( $source_url, PHP_URL_PATH ) ) );
        }

        $items[] = [
            "title"          => wp_strip_all_tags( (string) $item->title ),
            "source_url"     => $source_url,
            "source_slug"    => $source_slug,
            "featured_image" => hpr_force_sync_assets_extract_featured_image_url( $item ),
        ];
    }

    return $items;
}

function hpr_force_sync_assets_extract_featured_image_url( \SimpleXMLElement $item ) {
    $media = $item->children( "http://search.yahoo.com/mrss/" );
    if ( ! empty( $media->content ) ) {
        foreach ( $media->content as $content ) {
            $attributes = $content->attributes();
            $url        = isset( $attributes["url"] ) ? esc_url_raw( (string) $attributes["url"] ) : "";
            if ( hpr_force_sync_assets_is_image_url( $url ) ) {
                return $url;
            }
        }
    }

    foreach ( [ (string) $item->description, (string) $item->children( "http://purl.org/rss/1.0/modules/content/" )->encoded ] as $html ) {
        if ( preg_match( "#<img[^>]+src=[\\\"\\x27]([^\\\"\\x27]+)#i", $html, $matches ) ) {
            $url = esc_url_raw( html_entity_decode( $matches[1], ENT_QUOTES ) );
            if ( hpr_force_sync_assets_is_image_url( $url ) ) {
                return $url;
            }
        }
    }

    return "";
}

function hpr_force_sync_assets_is_image_url( $url ) {
    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return false;
    }
    return (bool) preg_match( "/\\.(?:jpe?g|png|gif|webp|avif)$/i", (string) wp_parse_url( $url, PHP_URL_PATH ) );
}

function hpr_force_sync_assets_sync_feed_items( array $post_map, array $feed_items ) {
    $result = [
        "checked"             => 0,
        "updated"             => 0,
        "unchanged"           => 0,
        "created_attachments" => 0,
        "reused_attachments"  => 0,
        "skipped"             => [],
        "changed"             => [],
        "errors"              => [],
    ];

    foreach ( $feed_items as $item ) {
        $source_url = $item["source_url"] ?? "";
        $image_url  = isset( $item["featured_image"] ) ? esc_url_raw( (string) $item["featured_image"] ) : "";

        if ( empty( $source_url ) || empty( $post_map["source_urls"][ $source_url ] ) ) {
            $result["skipped"][] = [ "source_url" => $source_url, "reason" => "local_post_not_found" ];
            continue;
        }

        if ( empty( $image_url ) ) {
            $result["skipped"][] = [ "source_url" => $source_url, "reason" => "no_feed_featured_image" ];
            continue;
        }

        $post_id = (int) $post_map["source_urls"][ $source_url ]["post_id"];
        $result["checked"]++;

        try {
            $sync = hpr_force_sync_assets_sync_post_featured_image( $post_id, $image_url, $item["title"] ?? "" );
            if ( ! empty( $sync["updated"] ) ) {
                $result["updated"]++;
                $result["changed"][] = array_merge( [ "post_id" => $post_id, "source_url" => $source_url, "live_url" => get_permalink( $post_id ) ], $sync );
            } else {
                $result["unchanged"]++;
            }
            if ( ! empty( $sync["created_attachment"] ) ) { $result["created_attachments"]++; }
            if ( ! empty( $sync["reused_attachment"] ) ) { $result["reused_attachments"]++; }
        } catch ( \Throwable $throwable ) {
            $result["errors"][] = [ "post_id" => $post_id, "source_url" => $source_url, "message" => $throwable->getMessage() ];
        }
    }

    return $result;
}

function hpr_force_sync_assets_sync_post_featured_image( $post_id, $image_url, $title = "" ) {
    $old_echo = (string) get_post_meta( $post_id, "echo_featured_img", true );
    $old_fifu = (string) get_post_meta( $post_id, "fifu_image_url", true );
    $thumb_id = (int) get_post_thumbnail_id( $post_id );
    $attachment_id = $thumb_id;
    $created_attachment = false;
    $reused_attachment = false;
    $attachment_old_file = "";

    if ( $attachment_id > 0 && "attachment" === get_post_type( $attachment_id ) ) {
        $reused_attachment = true;
        $attachment_old_file = (string) get_post_meta( $attachment_id, "_wp_attached_file", true );
    } else {
        $attachment_id = hpr_force_sync_assets_create_external_image_attachment( $post_id, $image_url, $title );
        $created_attachment = true;
    }

    $changed = false;
    if ( $old_echo !== $image_url ) { update_post_meta( $post_id, "echo_featured_img", $image_url ); $changed = true; }
    if ( $old_fifu !== $image_url ) { update_post_meta( $post_id, "fifu_image_url", $image_url ); update_post_meta( $post_id, "fifu_image_alt", wp_strip_all_tags( $title ) ); $changed = true; }

    if ( $attachment_id > 0 ) {
        if ( $attachment_old_file !== $image_url ) {
            update_post_meta( $attachment_id, "_wp_attached_file", $image_url );
            update_post_meta( $attachment_id, "_hpr_external_featured_image_url", $image_url );
            $changed = true;
        }
        wp_update_post( [ "ID" => $attachment_id, "post_parent" => $post_id, "post_title" => wp_strip_all_tags( $title ), "post_mime_type" => hpr_force_sync_assets_guess_image_mime_type( $image_url ), "guid" => $image_url ] );
        if ( $thumb_id !== $attachment_id ) { set_post_thumbnail( $post_id, $attachment_id ); $changed = true; }
    }

    return [ "updated" => $changed, "image_url" => $image_url, "old_echo_image" => $old_echo, "old_fifu_image" => $old_fifu, "attachment_id" => $attachment_id, "old_attachment_url" => $attachment_old_file, "created_attachment" => $created_attachment, "reused_attachment" => $reused_attachment ];
}

function hpr_force_sync_assets_create_external_image_attachment( $post_id, $image_url, $title = "" ) {
    $attachment_id = wp_insert_post( [ "post_title" => wp_strip_all_tags( $title ?: basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) ) ), "post_type" => "attachment", "post_status" => "inherit", "post_parent" => $post_id, "post_mime_type" => hpr_force_sync_assets_guess_image_mime_type( $image_url ), "guid" => $image_url ], true );
    if ( is_wp_error( $attachment_id ) ) { throw new \RuntimeException( "Attachment shell could not be created: " . $attachment_id->get_error_message() ); }
    update_post_meta( $attachment_id, "_wp_attached_file", $image_url );
    update_post_meta( $attachment_id, "_hpr_external_featured_image_url", $image_url );
    set_post_thumbnail( $post_id, $attachment_id );
    return (int) $attachment_id;
}

function hpr_force_sync_assets_guess_image_mime_type( $image_url ) {
    $path = strtolower( (string) wp_parse_url( $image_url, PHP_URL_PATH ) );
    if ( preg_match( "/\\.png$/", $path ) ) { return "image/png"; }
    if ( preg_match( "/\\.gif$/", $path ) ) { return "image/gif"; }
    if ( preg_match( "/\\.webp$/", $path ) ) { return "image/webp"; }
    if ( preg_match( "/\\.avif$/", $path ) ) { return "image/avif"; }
    return "image/jpeg";
}

function hpr_force_sync_assets_purge_feed_item_posts( array $post_map, array $feed_items ) {
    $result = [ "checked" => 0, "purged" => [], "skipped" => [] ];
    foreach ( $feed_items as $item ) {
        $source_url = $item["source_url"] ?? "";
        if ( empty( $source_url ) || empty( $post_map["source_urls"][ $source_url ] ) ) { $result["skipped"][] = $source_url; continue; }
        $post_id = (int) $post_map["source_urls"][ $source_url ]["post_id"];
        $url = get_permalink( $post_id );
        hpr_force_sync_assets_purge_post_cache( $post_id, $url );
        $result["checked"]++;
        $result["purged"][] = $url;
    }
    $result["purged"] = array_values( array_unique( array_filter( $result["purged"] ) ) );
    return $result;
}

function hpr_force_sync_assets_purge_post_cache( $post_id, $url = "" ) {
    clean_post_cache( $post_id );
    if ( has_action( "litespeed_purge_post" ) ) { do_action( "litespeed_purge_post", $post_id ); }
    if ( ! empty( $url ) && has_action( "litespeed_purge_url" ) ) { do_action( "litespeed_purge_url", $url ); }
    if ( class_exists( "\\LiteSpeed_Cache_API" ) ) {
        if ( method_exists( "\\LiteSpeed_Cache_API", "purge_post" ) ) { \LiteSpeed_Cache_API::purge_post( $post_id ); }
        if ( ! empty( $url ) && method_exists( "\\LiteSpeed_Cache_API", "purge_url" ) ) { \LiteSpeed_Cache_API::purge_url( $url ); }
    }
}
