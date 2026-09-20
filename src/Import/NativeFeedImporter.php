<?php

namespace hpr_distributor\Import;

use hpr_distributor\Media\ExternalImageSizing;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class NativeFeedImporter {
    public const LAST_RUN_OPTION = "hpr_distributor_native_import_last_run";
    private const LOCK = "hpr_distributor_native_import_lock";
    private const MANUAL_ACTION = "hpr_distributor_run_native_import";
    private const META_IDENTITY = "_hpr_source_identity";
    private const META_SOURCE_ID = "_hpr_source_id";
    private const META_SOURCE_URL = "_hpr_canonical_source_url";
    private const META_SOURCE_GUID = "_hpr_source_guid";
    private const META_FEED_URL = "_hpr_source_feed_url";

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_action( "wp_ajax_" . self::MANUAL_ACTION, [ self::class, "ajax_run" ] );
        self::$registered = true;
    }

    public static function ajax_run(): void {
        check_ajax_referer( \hpr_distributor\Config::AJAX_NONCE, "nonce" );
        if ( ! current_user_can( "manage_options" ) ) {
            wp_send_json_error( [ "message" => "Insufficient permissions." ], 403 );
        }

        try {
            wp_send_json_success( self::run( [ "trigger" => "manual" ] ) );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( [ "message" => $throwable->getMessage() ], 500 );
        }
    }

    public static function run_scheduled(): void {
        try {
            self::run( [ "trigger" => "schedule" ] );
            self::migrate_legacy_posts( 50 );
        } catch ( \Throwable $throwable ) {
            update_option(
                self::LAST_RUN_OPTION,
                [
                    "success"   => false,
                    "trigger"   => "schedule",
                    "error"     => $throwable->getMessage(),
                    "ended_gmt" => current_time( "mysql", true ),
                ],
                false
            );
        }
    }

    public static function run( array $arguments = [] ): array {
        if ( get_transient( self::LOCK ) ) {
            throw new \RuntimeException( "A native Hexa PR Wire import is already running." );
        }

        $settings = NativeFeedSettings::get();
        $validation = NativeFeedSettings::validate( $settings );
        if ( ! $validation["valid"] || ! $settings["enabled"] ) {
            throw new \RuntimeException( implode( " ", $validation["errors"] ) ?: "Native importing is disabled." );
        }

        set_transient( self::LOCK, time(), 5 * MINUTE_IN_SECONDS );
        $started = microtime( true );
        $dry_run = ! empty( $arguments["dry_run"] );
        $targets = self::normalize_targets( $arguments["targets"] ?? [] );
        $feed_url = self::effective_feed_url( $settings["feed_url"], (string) ( $arguments["feed_action"] ?? "" ) );

        try {
            $items = self::fetch_items( $feed_url, $settings["allowed_host"] );
            if ( self::has_targets( $targets ) ) {
                $items = array_values(
                    array_filter(
                        $items,
                        static function ( array $item ) use ( $targets ): bool {
                            return in_array( $item["source_url"], $targets["source_urls"], true )
                                || in_array( $item["source_slug"], $targets["source_slugs"], true )
                                || in_array( $item["source_id"], $targets["source_ids"], true );
                        }
                    )
                );
            }

            $discovered = count( $items );
            $items = array_slice( $items, 0, $settings["max_items"] );
            $results = [];
            $counts = [ "created" => 0, "updated" => 0, "unchanged" => 0, "would_create" => 0, "would_update" => 0, "failed" => 0 ];

            foreach ( $items as $item ) {
                try {
                    $result = self::import_item( $item, $settings, $dry_run );
                    $results[] = $result;
                    $action = (string) ( $result["action"] ?? "failed" );
                    if ( isset( $counts[ $action ] ) ) {
                        $counts[ $action ]++;
                    }
                } catch ( \Throwable $throwable ) {
                    $counts["failed"]++;
                    $results[] = self::result_row( $item, 0, "failed", [ "error" => $throwable->getMessage() ] );
                }
            }

            $result = [
                "success"            => 0 === $counts["failed"],
                "contract_version"   => NativeFeedSettings::CONTRACT_VERSION,
                "trigger"            => sanitize_key( (string) ( $arguments["trigger"] ?? "direct" ) ),
                "dry_run"            => $dry_run,
                "feed_url"           => $feed_url,
                "publication_slug"   => $settings["publication_slug"],
                "items_discovered"   => $discovered,
                "items_processed"    => count( $items ),
                "bounded_max_items"  => $settings["max_items"],
                "counts"             => $counts,
                "items"              => $results,
                "duration_ms"        => (int) round( ( microtime( true ) - $started ) * 1000 ),
                "echo_rss_required"  => false,
                "fifu_required"      => false,
                "images_remote_only" => true,
                "ended_gmt"          => current_time( "mysql", true ),
            ];

            if ( ! $dry_run ) {
                update_option( self::LAST_RUN_OPTION, $result, false );
            }

            return $result;
        } finally {
            delete_transient( self::LOCK );
        }
    }

    public static function effective_feed_url( string $feed_url, string $feed_action = "" ): string {
        $parts = wp_parse_url( $feed_url );
        if ( ! is_array( $parts ) || empty( $parts["scheme"] ) || empty( $parts["host"] ) ) {
            throw new \InvalidArgumentException( "The native feed URL is invalid." );
        }

        $query = [];
        parse_str( (string) ( $parts["query"] ?? "" ), $query );
        if ( "" !== $feed_action ) {
            $query["action"] = sanitize_key( $feed_action );
        } else {
            unset( $query["action"] );
        }
        $query["v"] = (string) time();

        $base = $parts["scheme"] . "://" . $parts["host"] . ( isset( $parts["port"] ) ? ":" . (int) $parts["port"] : "" ) . ( $parts["path"] ?? "/" );
        return add_query_arg( $query, $base );
    }

    public static function fetch_items( string $feed_url, string $allowed_host ): array {
        if ( ! SourceIdentity::allowed_host( $feed_url, $allowed_host ) ) {
            throw new \RuntimeException( "The native feed host is not allowed." );
        }

        $response = wp_remote_get(
            $feed_url,
            [
                "timeout"     => 90,
                "redirection" => 3,
                "headers"     => [ "Accept" => "application/rss+xml, application/xml, text/xml;q=0.9" ],
            ]
        );
        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( "Feed request failed: " . $response->get_error_message() );
        }
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            throw new \RuntimeException( "Feed request returned HTTP " . $status . "." );
        }

        return self::parse_feed_xml( (string) wp_remote_retrieve_body( $response ), $allowed_host );
    }

    public static function parse_feed_xml( string $xml_body, string $allowed_host = "hexaprwire.com" ): array {
        if ( "" === trim( $xml_body ) || ! function_exists( "simplexml_load_string" ) ) {
            throw new \RuntimeException( "The feed response is empty or SimpleXML is unavailable." );
        }

        // The historical source feed can concatenate namespace declarations
        // (`.../"xmlns:media=...`). Repair only that deterministic XML defect.
        $xml_body = preg_replace( '/(["\x27])xmlns:/', '$1 xmlns:', $xml_body ) ?? $xml_body;

        $previous = libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_body, "SimpleXMLElement", LIBXML_NOCDATA | LIBXML_NONET );
        libxml_use_internal_errors( $previous );
        if ( false === $xml ) {
            libxml_clear_errors();
            throw new \RuntimeException( "The feed XML could not be parsed." );
        }

        $items = [];
        foreach ( $xml->channel->item ?? [] as $node ) {
            $source_url = SourceIdentity::canonical_url( (string) $node->post_url );
            if ( "" === $source_url ) {
                $source_url = SourceIdentity::canonical_url( (string) $node->link );
            }
            if ( "" === $source_url || ! SourceIdentity::allowed_host( $source_url, $allowed_host ) ) {
                continue;
            }

            $guid = trim( (string) $node->guid );
            $source_id = SourceIdentity::source_id( $guid, $source_url );
            $source_slug = sanitize_title( (string) $node->post_slug );
            if ( "" === $source_slug ) {
                $source_slug = sanitize_title( basename( (string) wp_parse_url( $source_url, PHP_URL_PATH ) ) );
            }

            $content_namespace = $node->children( "http://purl.org/rss/1.0/modules/content/" );
            $content = isset( $content_namespace->encoded ) ? (string) $content_namespace->encoded : "";
            $categories = [];
            foreach ( $node->category as $category ) {
                $attributes = $category->attributes();
                $slug = sanitize_title( (string) ( $attributes["nicename"] ?? $category ) );
                if ( "" !== $slug ) {
                    $categories[ $slug ] = wp_strip_all_tags( (string) $category ) ?: $slug;
                }
            }

            $image_url = self::extract_image_url( $node );
            if ( "" !== $image_url && ! SourceIdentity::allowed_host( $image_url, $allowed_host ) ) {
                $image_url = "";
            }

            $items[] = [
                "title"          => wp_strip_all_tags( (string) $node->title ),
                "content"        => wp_kses_post( $content ),
                "excerpt"        => wp_strip_all_tags( (string) $node->description ),
                "guid"           => $guid,
                "source_id"      => $source_id,
                "source_identity"=> SourceIdentity::identity( $guid, $source_url ),
                "source_url"     => $source_url,
                "source_slug"    => $source_slug,
                "published_gmt"  => self::normalize_date( (string) $node->pubDate ),
                "categories"     => $categories,
                "featured_image" => $image_url,
            ];
        }

        return $items;
    }

    public static function import_item( array $item, array $settings, bool $dry_run = false ): array {
        $dedupe = self::find_existing_post( $item );
        $post_id = (int) ( $dedupe["post_id"] ?? 0 );
        $post = $post_id > 0 ? get_post( $post_id ) : null;
        $content_hash = hash(
            "sha256",
            (string) $item["title"] . "\n"
            . (string) $item["content"] . "\n"
            . (string) $item["excerpt"] . "\n"
            . wp_json_encode( $item["categories"] ?? [] ) . "\n"
            . (string) ( $item["featured_image"] ?? "" )
        );
        $existing_hash = $post_id > 0 ? (string) get_post_meta( $post_id, "_hpr_source_content_hash", true ) : "";
        $action = $post_id > 0 ? ( $existing_hash === $content_hash ? "unchanged" : "updated" ) : "created";

        if ( $dry_run ) {
            return self::result_row( $item, $post_id, 0 === $post_id ? "would_create" : ( "unchanged" === $action ? "unchanged" : "would_update" ), [ "dedupe" => $dedupe ] );
        }

        $post_data = [
            "post_type"    => "press-release",
            "post_status"  => $settings["post_status"],
            "post_title"   => $item["title"],
            "post_name"    => $item["source_slug"],
            "post_content" => $item["content"],
            "post_excerpt" => $item["excerpt"],
            "post_author"  => self::resolve_author_id( $settings ),
        ];
        if ( "" !== $item["published_gmt"] ) {
            $post_data["post_date_gmt"] = $item["published_gmt"];
            $post_data["post_date"] = get_date_from_gmt( $item["published_gmt"] );
        }

        if ( $post_id > 0 && "unchanged" === $action ) {
            $saved = $post_id;
        } elseif ( $post_id > 0 ) {
            $post_data["ID"] = $post_id;
            $saved = wp_update_post( wp_slash( $post_data ), true );
        } else {
            $saved = wp_insert_post( wp_slash( $post_data ), true );
        }
        if ( is_wp_error( $saved ) ) {
            throw new \RuntimeException( $saved->get_error_message() );
        }
        $post_id = (int) $saved;

        update_post_meta( $post_id, self::META_IDENTITY, $item["source_identity"] );
        update_post_meta( $post_id, self::META_SOURCE_ID, $item["source_id"] );
        update_post_meta( $post_id, self::META_SOURCE_URL, $item["source_url"] );
        update_post_meta( $post_id, self::META_SOURCE_GUID, $item["guid"] );
        update_post_meta( $post_id, self::META_FEED_URL, $settings["feed_url"] );
        update_post_meta( $post_id, "_hpr_source_content_hash", $content_hash );
        update_post_meta( $post_id, "_hpr_last_imported_gmt", current_time( "mysql", true ) );
        update_post_meta( $post_id, "original_post_url", $item["source_url"] );
        update_post_meta( $post_id, "original_post_slug", $item["source_slug"] );

        self::assign_categories( $post_id, $item["categories"] );
        $image = [ "updated" => false, "image_url" => "", "attachment_id" => 0 ];
        if ( "" !== $item["featured_image"] ) {
            $image = ExternalImageSizing::sync_remote_featured_image( $post_id, $item["featured_image"], $item["title"] );
            if ( "unchanged" === $action && ! empty( $image["updated"] ) ) {
                $action = "updated";
            }
        }
        clean_post_cache( $post_id );

        return self::result_row(
            $item,
            $post_id,
            $action,
            [
                "dedupe"                 => $dedupe,
                "image_url"              => $image["image_url"] ?? "",
                "image_attachment_id"    => (int) ( $image["attachment_id"] ?? 0 ),
                "image_remains_on_source"=> true,
            ]
        );
    }

    public static function find_existing_post( array $item ): array {
        $matches = [];
        $preferred_id = 0;
        $lookups = [
            "source_identity" => [ self::META_IDENTITY, (string) ( $item["source_identity"] ?? "" ) ],
            "source_id"       => [ self::META_SOURCE_ID, (string) ( $item["source_id"] ?? "" ) ],
            "canonical_url"   => [ self::META_SOURCE_URL, (string) ( $item["source_url"] ?? "" ) ],
            "legacy_original_url" => [ "original_post_url", (string) ( $item["source_url"] ?? "" ) ],
            "legacy_echo_full_url" => [ "echo_post_full_url", (string) ( $item["source_url"] ?? "" ) ],
            "legacy_echo_url"      => [ "echo_post_url", (string) ( $item["source_url"] ?? "" ) ],
            "legacy_original_slug" => [ "original_post_slug", (string) ( $item["source_slug"] ?? "" ) ],
        ];

        foreach ( $lookups as $matched_by => [ $key, $value ] ) {
            if ( "" === $value ) {
                continue;
            }
            $ids = get_posts(
                [
                    "post_type"      => "press-release",
                    "post_status"    => [ "publish", "draft", "pending", "private", "future", "trash" ],
                    "posts_per_page" => 10,
                    "fields"         => "ids",
                    "orderby"        => "ID",
                    "order"          => "ASC",
                    "meta_key"       => $key,
                    "meta_value"     => $value,
                    "no_found_rows"  => true,
                ]
            );
            foreach ( $ids as $id ) {
                $matches[ (int) $id ][] = $matched_by;
            }
            if ( 0 === $preferred_id && ! empty( $ids ) ) {
                $preferred_id = (int) reset( $ids );
            }
        }

        $ids = array_keys( $matches );
        sort( $ids, SORT_NUMERIC );
        $post_id = $preferred_id > 0 ? $preferred_id : (int) ( $ids[0] ?? 0 );

        return [
            "matched"                  => $post_id > 0,
            "matched_by"               => $post_id > 0 ? array_values( array_unique( $matches[ $post_id ] ) ) : [],
            "post_id"                  => $post_id,
            "candidate_post_ids"       => $ids,
            "collision"                => count( $ids ) > 1,
            "reused_existing_post_id"  => $post_id > 0,
            "new_post_id_was_created"  => false,
        ];
    }

    public static function migrate_legacy_posts( int $limit = 100 ): array {
        $ids = get_posts(
            [
                "post_type"      => "press-release",
                "post_status"    => [ "publish", "draft", "pending", "private", "future", "trash" ],
                "posts_per_page" => max( 1, min( 500, $limit ) ),
                "fields"         => "ids",
                "orderby"        => "ID",
                "order"          => "ASC",
                "meta_query"     => [
                    "relation" => "AND",
                    [ "key" => self::META_IDENTITY, "compare" => "NOT EXISTS" ],
                    [ "key" => "_hpr_legacy_migration_checked", "compare" => "NOT EXISTS" ],
                    [
                        "relation" => "OR",
                        [ "key" => "original_post_url", "compare" => "EXISTS" ],
                        [ "key" => "echo_post_full_url", "compare" => "EXISTS" ],
                        [ "key" => "fifu_image_url", "compare" => "EXISTS" ],
                        [ "key" => "echo_featured_img", "compare" => "EXISTS" ],
                    ],
                ],
                "no_found_rows" => true,
            ]
        );

        $result = [ "checked" => 0, "migrated" => 0, "images_migrated" => 0, "post_ids" => [] ];
        $allowed_host = NativeFeedSettings::get()["allowed_host"];
        foreach ( $ids as $post_id ) {
            $post_id = (int) $post_id;
            $result["checked"]++;
            $source_url = "";
            foreach ( [ "original_post_url", "echo_post_full_url", "echo_post_url" ] as $key ) {
                $source_url = SourceIdentity::canonical_url( (string) get_post_meta( $post_id, $key, true ) );
                if ( "" !== $source_url ) {
                    break;
                }
            }
            $guid = (string) get_post_meta( $post_id, "echo_post_id", true );
            if ( "" !== $source_url ) {
                update_post_meta( $post_id, self::META_SOURCE_URL, $source_url );
                update_post_meta( $post_id, self::META_SOURCE_ID, SourceIdentity::source_id( $guid, $source_url ) );
                update_post_meta( $post_id, self::META_IDENTITY, SourceIdentity::identity( $guid, $source_url ) );
                if ( "" !== $guid ) {
                    update_post_meta( $post_id, self::META_SOURCE_GUID, $guid );
                }
                $result["migrated"]++;
                $result["post_ids"][] = $post_id;
            }

            $image_url = "";
            foreach ( [ "_hpr_remote_featured_image_url", "fifu_image_url", "echo_featured_img" ] as $key ) {
                $image_url = esc_url_raw( (string) get_post_meta( $post_id, $key, true ) );
                if ( "" !== $image_url ) {
                    break;
                }
            }
            if ( "" !== $image_url && SourceIdentity::allowed_host( $image_url, $allowed_host ) ) {
                ExternalImageSizing::sync_remote_featured_image( $post_id, $image_url, get_the_title( $post_id ) );
                $result["images_migrated"]++;
            }
            update_post_meta( $post_id, "_hpr_legacy_migration_checked", current_time( "mysql", true ) );
        }

        update_option( NativeFeedSettings::LEGACY_MIGRATION_OPTION, $result + [ "ran_gmt" => current_time( "mysql", true ) ], false );
        return $result;
    }

    public static function normalize_targets( $targets ): array {
        $targets = is_array( $targets ) ? $targets : [];
        return [
            "source_urls"  => array_values( array_unique( array_filter( array_map( [ SourceIdentity::class, "canonical_url" ], (array) ( $targets["source_urls"] ?? [] ) ) ) ) ),
            "source_slugs" => array_values( array_unique( array_filter( array_map( "sanitize_title", (array) ( $targets["source_slugs"] ?? [] ) ) ) ) ),
            "source_ids"   => array_values( array_unique( array_filter( array_map( "sanitize_text_field", (array) ( $targets["source_ids"] ?? [] ) ) ) ) ),
        ];
    }

    public static function has_targets( array $targets ): bool {
        return ! empty( $targets["source_urls"] ) || ! empty( $targets["source_slugs"] ) || ! empty( $targets["source_ids"] );
    }

    private static function extract_image_url( \SimpleXMLElement $node ): string {
        $media = $node->children( "http://search.yahoo.com/mrss/" );
        foreach ( $media->content ?? [] as $content ) {
            $attributes = $content->attributes();
            $url = esc_url_raw( (string) ( $attributes["url"] ?? "" ) );
            if ( self::is_image_url( $url ) ) {
                return $url;
            }
        }

        return "";
    }

    private static function is_image_url( string $url ): bool {
        return "" !== $url
            && false !== filter_var( $url, FILTER_VALIDATE_URL )
            && (bool) preg_match( "/\.(?:jpe?g|png|gif|webp|avif)$/i", (string) wp_parse_url( $url, PHP_URL_PATH ) );
    }

    private static function normalize_date( string $date ): string {
        $timestamp = strtotime( $date );
        return false === $timestamp ? "" : gmdate( "Y-m-d H:i:s", $timestamp );
    }

    private static function resolve_author_id( array $settings ): int {
        $author_id = absint( $settings["author_id"] ?? 0 );
        if ( $author_id > 0 && get_user_by( "id", $author_id ) ) {
            return $author_id;
        }
        $user = get_user_by( "login", "hexaprwire" );
        return $user instanceof \WP_User ? (int) $user->ID : get_current_user_id();
    }

	private static function assign_categories( int $post_id, array $categories ): void {
		$categories = [ "press-release" => "Press Release" ] + $categories;
		$term_ids = [];
        foreach ( $categories as $slug => $name ) {
            $term = term_exists( $slug, "category" );
            if ( ! $term ) {
                $term = wp_insert_term( $name ?: $slug, "category", [ "slug" => $slug ] );
            }
            if ( ! is_wp_error( $term ) ) {
                $term_ids[] = (int) ( is_array( $term ) ? $term["term_id"] : $term );
            }
        }
        if ( [] !== $term_ids ) {
            wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), "category", false );
        }
    }

    private static function result_row( array $item, int $post_id, string $action, array $extra = [] ): array {
        $dedupe = (array) ( $extra["dedupe"] ?? [] );
        if ( $post_id > 0 && "created" === $action ) {
            $dedupe["new_post_id_was_created"] = true;
            $dedupe["post_id"] = $post_id;
        }
        $extra["dedupe"] = $dedupe;

        return array_merge(
            [
                "action"              => $action,
                "source_identity"     => (string) ( $item["source_identity"] ?? "" ),
                "source_id"           => (string) ( $item["source_id"] ?? "" ),
                "source_url"          => (string) ( $item["source_url"] ?? "" ),
                "canonical_url"       => (string) ( $item["source_url"] ?? "" ),
                "source_slug"         => (string) ( $item["source_slug"] ?? "" ),
                "source_title"        => (string) ( $item["title"] ?? "" ),
                "source_content_sha256" => hash( "sha256", (string) ( $item["content"] ?? "" ) ),
                "destination_post_id" => $post_id,
                "destination_url"     => $post_id > 0 ? (string) get_permalink( $post_id ) : "",
                "image_source_url"    => (string) ( $item["featured_image"] ?? "" ),
                "image_url"           => (string) ( $item["featured_image"] ?? "" ),
            ],
            $extra
        );
    }
}
