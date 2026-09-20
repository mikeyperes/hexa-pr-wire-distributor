<?php

namespace hpr_distributor\Import;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class SourceIdentity {
    public static function canonical_url( string $url ): string {
        $url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5 ) );
        if ( "" === $url ) {
            return "";
        }

        $parsed = parse_url( $url );
        if ( ! is_array( $parsed ) || empty( $parsed["scheme"] ) || empty( $parsed["host"] ) ) {
            return "";
        }

        $scheme = strtolower( (string) $parsed["scheme"] );
        $host   = strtolower( rtrim( (string) $parsed["host"], "." ) );
        if ( ! in_array( $scheme, [ "http", "https" ], true ) ) {
            return "";
        }

        $port = isset( $parsed["port"] ) ? (int) $parsed["port"] : 0;
        $authority = $scheme . "://" . $host;
        if ( $port > 0 && ! ( 80 === $port && "http" === $scheme ) && ! ( 443 === $port && "https" === $scheme ) ) {
            $authority .= ":" . $port;
        }

        $path = isset( $parsed["path"] ) && "" !== $parsed["path"] ? (string) $parsed["path"] : "/";
        $path = "/" . ltrim( preg_replace( "#/+#", "/", $path ), "/" );
        if ( "/" !== $path ) {
            $path = rtrim( $path, "/" ) . "/";
        }

        $query = [];
        if ( ! empty( $parsed["query"] ) ) {
            parse_str( (string) $parsed["query"], $query );
            foreach ( array_keys( $query ) as $key ) {
                $normalized_key = strtolower( (string) $key );
                if ( str_starts_with( $normalized_key, "utm_" ) || in_array( $normalized_key, [ "fbclid", "gclid", "mc_cid", "mc_eid" ], true ) ) {
                    unset( $query[ $key ] );
                }
            }
            ksort( $query );
        }

        return $authority . $path . ( [] === $query ? "" : "?" . http_build_query( $query, "", "&", PHP_QUERY_RFC3986 ) );
    }

    public static function source_id( string $guid, string $source_url ): string {
        $guid = trim( html_entity_decode( $guid, ENT_QUOTES | ENT_HTML5 ) );
        if ( "" !== $guid ) {
            $parts = parse_url( $guid );
            if ( is_array( $parts ) && ! empty( $parts["query"] ) ) {
                $query = [];
                parse_str( (string) $parts["query"], $query );
                if ( isset( $query["p"] ) && ctype_digit( (string) $query["p"] ) ) {
                    return "post:" . (int) $query["p"];
                }
            }

            return "guid:" . hash( "sha256", $guid );
        }

        $canonical = self::canonical_url( $source_url );
        return "" === $canonical ? "" : "url:" . hash( "sha256", $canonical );
    }

    public static function identity( string $guid, string $source_url ): string {
        $source_id = self::source_id( $guid, $source_url );
        return "" === $source_id ? "" : "hexaprwire:" . $source_id;
    }

    public static function allowed_host( string $url, string $allowed_host ): bool {
        $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
        $allowed_host = strtolower( trim( $allowed_host, ". " ) );

        return "" !== $host
            && "" !== $allowed_host
            && ( $host === $allowed_host || str_ends_with( $host, "." . $allowed_host ) );
    }
}
