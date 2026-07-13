<?php

namespace hpr_distributor\Import;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class EchoRuleContract {
    public const REQUIRED_MAPPING = "original_post_slug=>%%custom_post_slug%%, original_post_url=>%%custom_post_url%%, author_slug=>%%custom_author_slug%%, author_id=>%%custom_author_id%%, author_url=>%%custom_author_url%%";

    private const FEED_URL_INDEX = 0;
    private const ACTIVE_INDEX = 1;
    private const POST_TYPE_INDEX = 6;
    private const AUTHOR_INDEX = 7;
    private const FIELD_MAP_INDEX = 46;
    private const UPDATE_EXISTING_INDEX = 67;
    private const COPY_SLUG_INDEX = 82;

    public static function apply( array $rules, int $author_id ): array {
        $changes = [];
        $matched = 0;

        foreach ( $rules as $rule_id => $rule ) {
            if ( ! is_array( $rule ) || ! self::is_hexa_rule( $rule ) ) {
                continue;
            }

            $matched++;
            $before = self::contract_values( $rule );

            $rules[ $rule_id ][ self::AUTHOR_INDEX ] = (string) $author_id;
            $rules[ $rule_id ][ self::FIELD_MAP_INDEX ] = self::REQUIRED_MAPPING;
            $rules[ $rule_id ][ self::UPDATE_EXISTING_INDEX ] = "1";
            $rules[ $rule_id ][ self::COPY_SLUG_INDEX ] = "1";

            $after = self::contract_values( $rules[ $rule_id ] );
            if ( $before !== $after ) {
                $changes[] = [
                    "rule_id" => $rule_id,
                    "before"  => $before,
                    "after"   => $after,
                ];
            }
        }

        return [
            "rules"   => $rules,
            "matched" => $matched,
            "changes" => $changes,
        ];
    }

    public static function inspect( array $rules, int $author_id ): array {
        $checks = [];

        foreach ( self::matching_rules( $rules ) as $rule_id => $rule ) {
            $checks[] = [
                "rule_id"         => $rule_id,
                "feed_url"        => (string) ( $rule[ self::FEED_URL_INDEX ] ?? "" ),
                "active"          => ! empty( $rule[ self::ACTIVE_INDEX ] ),
                "author_matches"  => (string) $author_id === (string) ( $rule[ self::AUTHOR_INDEX ] ?? "" ),
                "mapping_ready"   => self::mapping_ready( (string) ( $rule[ self::FIELD_MAP_INDEX ] ?? "" ) ),
                "update_existing" => "1" === (string) ( $rule[ self::UPDATE_EXISTING_INDEX ] ?? "" ),
                "copy_slug"       => "1" === (string) ( $rule[ self::COPY_SLUG_INDEX ] ?? "" ),
            ];
        }

        $passed = [] !== $checks;
        foreach ( $checks as $check ) {
            if (
                ! $check["active"]
                || ! $check["author_matches"]
                || ! $check["mapping_ready"]
                || ! $check["update_existing"]
                || ! $check["copy_slug"]
            ) {
                $passed = false;
                break;
            }
        }

        return [
            "passed" => $passed,
            "rules"  => $checks,
        ];
    }

    public static function matching_rules( array $rules ): array {
        $matching = [];

        foreach ( $rules as $rule_id => $rule ) {
            if ( is_array( $rule ) && self::is_hexa_rule( $rule ) ) {
                $matching[ $rule_id ] = $rule;
            }
        }

        return $matching;
    }

    public static function is_hexa_rule( array $rule ): bool {
        $feed_url = trim( (string) ( $rule[ self::FEED_URL_INDEX ] ?? "" ) );
        $post_type = strtolower( trim( (string) ( $rule[ self::POST_TYPE_INDEX ] ?? "" ) ) );
        $host = strtolower( (string) parse_url( $feed_url, PHP_URL_HOST ) );
        $query_string = html_entity_decode(
            (string) parse_url( $feed_url, PHP_URL_QUERY ),
            ENT_QUOTES | ENT_HTML5,
            "UTF-8"
        );
        parse_str( $query_string, $query );

        return "press-release" === $post_type
            && in_array( $host, [ "hexaprwire.com", "www.hexaprwire.com" ], true )
            && "rss_publication" === (string) ( $query["feed"] ?? "" );
    }

    public static function mapping_ready( string $mapping ): bool {
        $actual = [];

        foreach ( explode( ",", $mapping ) as $pair ) {
            $parts = array_map( "trim", explode( "=>", $pair, 2 ) );
            if ( 2 === count( $parts ) && "" !== $parts[0] ) {
                $actual[ $parts[0] ] = $parts[1];
            }
        }

        $required = [
            "original_post_slug" => "%%custom_post_slug%%",
            "original_post_url"  => "%%custom_post_url%%",
            "author_slug"        => "%%custom_author_slug%%",
            "author_id"          => "%%custom_author_id%%",
            "author_url"         => "%%custom_author_url%%",
        ];

        foreach ( $required as $field => $placeholder ) {
            if ( ( $actual[ $field ] ?? null ) !== $placeholder ) {
                return false;
            }
        }

        return true;
    }

    private static function contract_values( array $rule ): array {
        return [
            "author"          => (string) ( $rule[ self::AUTHOR_INDEX ] ?? "" ),
            "field_map"       => (string) ( $rule[ self::FIELD_MAP_INDEX ] ?? "" ),
            "update_existing" => (string) ( $rule[ self::UPDATE_EXISTING_INDEX ] ?? "" ),
            "copy_slug"       => (string) ( $rule[ self::COPY_SLUG_INDEX ] ?? "" ),
        ];
    }
}
