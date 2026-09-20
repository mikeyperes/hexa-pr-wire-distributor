<?php

namespace hpr_distributor\Media;

use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ExternalImageSizing {
    private const CACHE_OPTION = "hpr_remote_image_dimensions";
    private const LEGACY_CACHE_OPTION = "hpr_fifu_external_image_dimensions";
    private const URL_META = "_hpr_remote_featured_image_url";
    private const LEGACY_URL_META = "_hpr_external_featured_image_url";
    private const CACHE_LIMIT = 500;
    private const FAILURE_TTL = 900;
    private const MAX_RESPONSE_BYTES = 196608;

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_filter( "wp_get_attachment_metadata", [ self::class, "filter_metadata" ], 99, 2 );
        add_filter( "wp_get_attachment_url", [ self::class, "filter_attachment_url" ], 99, 2 );
        add_filter( "image_downsize", [ self::class, "filter_image_downsize" ], 999, 3 );
        add_filter( "wp_get_attachment_image_src", [ self::class, "filter_image_src" ], 999, 4 );
        add_action( "save_post_press-release", [ self::class, "repair_post" ], 99 );

        self::$registered = true;
    }

    public static function attachment_url( int $attachment_id ): string {
        $attachment = get_post( $attachment_id );
        if (
            ! $attachment instanceof \WP_Post
            || "attachment" !== $attachment->post_type
            || empty( $attachment->post_parent )
            || "press-release" !== get_post_type( (int) $attachment->post_parent )
        ) {
            return "";
        }

        $url = "";
        foreach ( [ self::URL_META, self::LEGACY_URL_META, "_wp_attached_file" ] as $key ) {
            $candidate = trim( (string) get_post_meta( $attachment_id, $key, true ) );
            if ( preg_match( "#^https?://#i", $candidate ) ) {
                $url = $candidate;
                break;
            }
        }

        if ( "" === $url ) {
            foreach ( [ "fifu_image_url", "echo_featured_img" ] as $key ) {
                $candidate = trim( (string) get_post_meta( (int) $attachment->post_parent, $key, true ) );
                if ( preg_match( "#^https?://#i", $candidate ) ) {
                    $url = $candidate;
                    break;
                }
            }
        }

		$allowed_host = (string) NativeFeedSettings::get()["allowed_host"];
		return preg_match( "#^https://#i", $url ) && SourceIdentity::allowed_host( $url, $allowed_host ) ? esc_url_raw( $url ) : "";
    }

    public static function dimensions( string $url ): array {
        $cache = get_option( self::CACHE_OPTION, [] );
        $cache = is_array( $cache ) ? $cache : [];
        $key = md5( $url );
        $cached = $cache[ $key ] ?? [];

        if ( ! self::valid_dimensions( $cached ) ) {
            $legacy_cache = get_option( self::LEGACY_CACHE_OPTION, [] );
            $legacy_cache = is_array( $legacy_cache ) ? $legacy_cache : [];
            $cached = $legacy_cache[ $key ] ?? $cached;
        }

        if ( self::valid_dimensions( $cached ) ) {
            return self::normalize_dimensions( $cached );
        }

        if (
            is_array( $cached )
            && ! empty( $cached["failed"] )
            && (int) ( $cached["checked"] ?? 0 ) > time() - self::FAILURE_TTL
        ) {
            return [];
        }

        $response = wp_safe_remote_get(
            $url,
            [
                "timeout"             => 8,
                "redirection"         => 3,
                "limit_response_size" => self::MAX_RESPONSE_BYTES,
                "headers"             => [
                    "Range" => "bytes=0-" . ( self::MAX_RESPONSE_BYTES - 1 ),
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            self::cache_result( $cache, $key, [ "failed" => true, "url" => $url ] );
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! is_string( $body ) || "" === $body || ! function_exists( "getimagesizefromstring" ) ) {
            self::cache_result( $cache, $key, [ "failed" => true, "url" => $url ] );
            return [];
        }

        $info = @getimagesizefromstring( $body );
        if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
            self::cache_result( $cache, $key, [ "failed" => true, "url" => $url ] );
            return [];
        }

        $dimensions = [
            "w"    => (int) $info[0],
            "h"    => (int) $info[1],
            "mime" => (string) ( $info["mime"] ?? "" ),
        ];

        self::cache_result( $cache, $key, $dimensions + [ "url" => $url ] );

        return $dimensions;
    }

    public static function metadata( int $attachment_id ): array {
        $url = self::attachment_url( $attachment_id );
        if ( "" === $url ) {
            return [];
        }

        $dimensions = self::dimensions( $url );
        if ( ! self::valid_dimensions( $dimensions ) ) {
            return [];
        }

        return [
            "width"      => (int) $dimensions["w"],
            "height"     => (int) $dimensions["h"],
            "file"       => $url,
            "sizes"      => [],
            "image_meta" => [],
        ];
    }

    public static function target_dimensions( $size, int $original_width, int $original_height ): array {
        if ( $original_width <= 0 || $original_height <= 0 ) {
            return [ 0, 0 ];
        }

        if ( "full" === $size ) {
            return [ $original_width, $original_height ];
        }

        [ $max_width, $max_height ] = self::requested_dimensions( $size );

        if ( $max_width <= 0 && $max_height <= 0 ) {
            return [ $original_width, $original_height ];
        }

        if ( $max_width <= 0 ) {
            $ratio = min( 1, $max_height / $original_height );
        } elseif ( $max_height <= 0 ) {
            $ratio = min( 1, $max_width / $original_width );
        } else {
            $ratio = min( 1, $max_width / $original_width, $max_height / $original_height );
        }

        return [
            max( 1, (int) round( $original_width * $ratio ) ),
            max( 1, (int) round( $original_height * $ratio ) ),
        ];
    }

    public static function filter_metadata( $metadata, $attachment_id ) {
        if ( is_array( $metadata ) && ! empty( $metadata["width"] ) && ! empty( $metadata["height"] ) ) {
            return $metadata;
        }

        $repaired = self::metadata( (int) $attachment_id );
        if ( [] === $repaired ) {
            return $metadata;
        }

        update_post_meta( (int) $attachment_id, "_wp_attachment_metadata", $repaired );

        return $repaired;
    }

    public static function filter_attachment_url( $url, $attachment_id ) {
        $remote_url = self::attachment_url( (int) $attachment_id );
        return "" === $remote_url ? $url : $remote_url;
    }

    public static function filter_image_downsize( $downsize, $attachment_id, $size ) {
        $image = self::image_tuple( (int) $attachment_id, $size );

        return null === $image ? $downsize : $image;
    }

    public static function filter_image_src( $image, $attachment_id, $size, $icon ) {
        unset( $icon );

        $repaired = self::image_tuple( (int) $attachment_id, $size );

		return null === $repaired ? $image : $repaired;
    }

    public static function repair_post( int $post_id ): void {
        if ( "press-release" !== get_post_type( $post_id ) ) {
            return;
        }

        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        if ( $attachment_id < 1 ) {
            return;
        }

        $metadata = self::metadata( $attachment_id );
        if ( [] !== $metadata ) {
            update_post_meta( $attachment_id, "_wp_attachment_metadata", $metadata );
        }
    }

    public static function sync_remote_featured_image( int $post_id, string $image_url, string $title = "" ): array {
        $image_url = esc_url_raw( trim( $image_url ) );
        $allowed_host = NativeFeedSettings::get()["allowed_host"];
        if ( "" === $image_url || ! SourceIdentity::allowed_host( $image_url, $allowed_host ) ) {
            throw new \InvalidArgumentException( "The featured image must remain hosted by " . $allowed_host . "." );
        }

        $old_url = trim( (string) get_post_meta( $post_id, self::URL_META, true ) );
        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        $reused = $attachment_id > 0 && "attachment" === get_post_type( $attachment_id );
        if ( $reused ) {
            $attachment_url = self::attachment_url( $attachment_id );
            $is_managed = "" !== (string) get_post_meta( $attachment_id, self::URL_META, true )
                || "" !== (string) get_post_meta( $attachment_id, self::LEGACY_URL_META, true )
                || preg_match( "#^https?://#i", (string) get_post_meta( $attachment_id, "_wp_attached_file", true ) );
            if ( ! $is_managed ) {
                $attachment_id = 0;
                $reused = false;
            } elseif ( $attachment_url === $image_url && $old_url === $image_url ) {
                return [
                    "updated"       => false,
                    "image_url"     => $image_url,
                    "attachment_id" => $attachment_id,
                    "created"       => false,
                    "reused"        => true,
                ];
            }
        }

        if ( $attachment_id < 1 ) {
            $attachment_id = wp_insert_post(
                [
                    "post_title"     => wp_strip_all_tags( $title ?: basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) ) ),
                    "post_type"      => "attachment",
                    "post_status"    => "inherit",
                    "post_parent"    => $post_id,
                    "post_mime_type" => self::guess_mime_type( $image_url ),
                    "guid"           => $image_url,
                ],
                true
            );
            if ( is_wp_error( $attachment_id ) ) {
                throw new \RuntimeException( "Remote image attachment could not be created: " . $attachment_id->get_error_message() );
            }
        }

        $attachment_id = (int) $attachment_id;
        update_post_meta( $attachment_id, self::URL_META, $image_url );
        update_post_meta( $attachment_id, self::LEGACY_URL_META, $image_url );
        update_post_meta( $attachment_id, "_wp_attachment_image_alt", wp_strip_all_tags( $title ) );
        update_post_meta( $post_id, self::URL_META, $image_url );
        wp_update_post(
            [
                "ID"             => $attachment_id,
                "post_parent"    => $post_id,
                "post_title"     => wp_strip_all_tags( $title ),
                "post_mime_type" => self::guess_mime_type( $image_url ),
                "guid"           => $image_url,
            ]
        );
        set_post_thumbnail( $post_id, $attachment_id );

        $metadata = self::metadata( $attachment_id );
        if ( [] !== $metadata ) {
            update_post_meta( $attachment_id, "_wp_attachment_metadata", $metadata );
        }

        return [
            "updated"       => true,
            "image_url"     => $image_url,
            "attachment_id" => $attachment_id,
            "created"       => ! $reused,
            "reused"        => $reused,
            "previous_url"  => $old_url,
        ];
    }

    public static function guess_mime_type( string $image_url ): string {
        $path = strtolower( (string) wp_parse_url( $image_url, PHP_URL_PATH ) );
        if ( str_ends_with( $path, ".png" ) ) {
            return "image/png";
        }
        if ( str_ends_with( $path, ".gif" ) ) {
            return "image/gif";
        }
        if ( str_ends_with( $path, ".webp" ) ) {
            return "image/webp";
        }
        if ( str_ends_with( $path, ".avif" ) ) {
            return "image/avif";
        }
        return "image/jpeg";
    }

    private static function image_tuple( int $attachment_id, $size ): ?array {
        $url = self::attachment_url( $attachment_id );
        if ( "" === $url ) {
            return null;
        }

        $dimensions = self::dimensions( $url );
        if ( ! self::valid_dimensions( $dimensions ) ) {
            return null;
        }

        [ $width, $height ] = self::target_dimensions(
            $size,
            (int) $dimensions["w"],
            (int) $dimensions["h"]
        );

        if ( $width <= 0 || $height <= 0 ) {
            return null;
        }

        return [ $url, $width, $height, "full" !== $size ];
    }

    private static function requested_dimensions( $size ): array {
        if ( is_array( $size ) ) {
            return [
                isset( $size[0] ) ? absint( $size[0] ) : 0,
                isset( $size[1] ) ? absint( $size[1] ) : 0,
            ];
        }

        if ( ! is_string( $size ) ) {
            return [ 0, 0 ];
        }

        $registered = function_exists( "wp_get_registered_image_subsizes" )
            ? wp_get_registered_image_subsizes()
            : [];
        if ( isset( $registered[ $size ] ) ) {
            return [
                absint( $registered[ $size ]["width"] ?? 0 ),
                absint( $registered[ $size ]["height"] ?? 0 ),
            ];
        }

        if ( preg_match( "#(\d+)x(\d+)#", $size, $matches ) ) {
            return [ absint( $matches[1] ), absint( $matches[2] ) ];
        }

        return [ 0, 0 ];
    }

    private static function cache_result( array $cache, string $key, array $result ): void {
        $cache[ $key ] = $result + [ "checked" => time() ];

        if ( count( $cache ) > self::CACHE_LIMIT ) {
            uasort(
                $cache,
                static fn ( array $left, array $right ): int =>
                    (int) ( $left["checked"] ?? 0 ) <=> (int) ( $right["checked"] ?? 0 )
            );
            $cache = array_slice( $cache, -self::CACHE_LIMIT, null, true );
        }

        update_option( self::CACHE_OPTION, $cache, false );
    }

    private static function valid_dimensions( $dimensions ): bool {
        return is_array( $dimensions )
            && (int) ( $dimensions["w"] ?? 0 ) > 0
            && (int) ( $dimensions["h"] ?? 0 ) > 0;
    }

    private static function normalize_dimensions( array $dimensions ): array {
        return [
            "w"    => (int) $dimensions["w"],
            "h"    => (int) $dimensions["h"],
            "mime" => (string) ( $dimensions["mime"] ?? "" ),
        ];
    }
}
