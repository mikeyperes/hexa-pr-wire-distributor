<?php
namespace hpr_distributor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', __NAMESPACE__ . '\\hpr_force_sync_maybe_initialize', 5 );
add_action( 'rest_api_init', __NAMESPACE__ . '\\hpr_force_sync_register_rest_routes' );

function hpr_force_sync_maybe_initialize() {
    $settings = get_option( 'hpr_force_sync_settings', [] );
    $settings = is_array( $settings ) ? $settings : [];
    $changed  = false;

    if ( empty( $settings['secret_token'] ) ) {
        $settings['secret_token'] = wp_generate_password( 64, false, false );
        $changed                  = true;
    }

    if ( empty( $settings['allowed_host'] ) ) {
        $settings['allowed_host'] = 'hexaprwire.com';
        $changed                  = true;
    }

    if ( $changed || null === get_option( 'hpr_force_sync_settings', null ) ) {
        update_option( 'hpr_force_sync_settings', $settings, false );
    }
}

function hpr_force_sync_get_settings() {
    hpr_force_sync_maybe_initialize();

    return wp_parse_args(
        get_option( 'hpr_force_sync_settings', [] ),
        [
            'secret_token' => '',
            'allowed_host' => 'hexaprwire.com',
        ]
    );
}

function hpr_force_sync_register_rest_routes() {
    register_rest_route(
        'hpr-distributor/v1',
        '/force-sync',
        [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => __NAMESPACE__ . '\\hpr_force_sync_rest_callback',
            'permission_callback' => '__return_true',
        ]
    );
}

function hpr_force_sync_rest_callback( \WP_REST_Request $request ) {
    nocache_headers();

    $settings = hpr_force_sync_get_settings();
    $token    = (string) $request->get_param( 'token' );

    if ( empty( $settings['secret_token'] ) || ! hash_equals( $settings['secret_token'], $token ) ) {
        return hpr_force_sync_rest_response(
            [
                'success' => false,
                'message' => 'Unauthorized.',
            ],
            403
        );
    }

    if ( get_transient( 'hpr_force_sync_running' ) ) {
        return hpr_force_sync_rest_response(
            [
                'success' => false,
                'message' => 'A force sync is already running on this site.',
            ],
            429
        );
    }

    set_transient( 'hpr_force_sync_running', time(), 5 * MINUTE_IN_SECONDS );

    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 300 );
    }

    $started_at = microtime( true );

    try {
        $rule = hpr_force_sync_discover_rule();
        if ( empty( $rule ) ) {
            throw new \RuntimeException( 'No active Hexa PR Wire Echo rule was detected on this site.' );
        }

        $before_map = hpr_force_sync_get_imported_post_map( $rule['id'] );
        $targets    = hpr_force_sync_resolve_targets( $request, $before_map );
        $dry_run    = hpr_force_sync_to_bool( $request->get_param( 'dry_run' ) );
        $feed_action = sanitize_text_field( (string) $request->get_param( 'feed_action' ) );

        if ( empty( $feed_action ) && hpr_force_sync_has_targets( $targets ) ) {
            $feed_action = 'reprocess-all';
        }

        $effective_feed_url = hpr_force_sync_build_effective_feed_url(
            $rule['feed_url'],
            $settings['allowed_host'],
            $feed_action
        );

        $feed_items     = hpr_force_sync_fetch_feed_items( $effective_feed_url );
        $target_feed_hits = hpr_force_sync_filter_feed_items_by_targets( $feed_items, $targets );
        $result_feed_items = hpr_force_sync_has_targets( $targets ) ? $target_feed_hits : $feed_items;

        if ( hpr_force_sync_has_targets( $targets ) && empty( $result_feed_items ) ) {
            return hpr_force_sync_rest_response(
                [
                    'success'         => false,
                    'message'         => 'No matching feed items were found for the requested target.',
                    'publication'     => home_url( '/' ),
                    'rule'            => $rule,
                    'requested_targets' => $targets,
                    'effective_feed_url' => $effective_feed_url,
                    'feed_items_discovered' => count( $feed_items ),
                    'matched_feed_items'    => 0,
                ],
                404
            );
        }

        $echo_baseline_result = null;
        $slug_repair_result    = null;

        if ( ! $dry_run && function_exists( __NAMESPACE__ . '\\hpr_echo_rss_apply_baseline' ) ) {
            $echo_baseline_result = hpr_echo_rss_apply_baseline( [ $rule['id'] ] );
        }

        if ( ! $dry_run ) {
            hpr_force_sync_run_echo_rule_with_feed_override( $rule['id'], $effective_feed_url );

            if ( function_exists( __NAMESPACE__ . '\\hpr_echo_rss_repair_source_slugs' ) ) {
                $slug_repair_result = hpr_echo_rss_repair_source_slugs( $rule['id'], 0, false );
            }
        }

        $after_map = $dry_run ? $before_map : hpr_force_sync_get_imported_post_map( $rule['id'] );
        $result    = hpr_force_sync_build_result( $before_map, $after_map, $result_feed_items, $targets, $dry_run );

        return hpr_force_sync_rest_response(
            [
                'success'               => true,
                'message'               => $dry_run ? 'Dry run completed successfully.' : 'Force syndication completed successfully.',
                'dry_run'               => $dry_run,
                'publication'           => home_url( '/' ),
                'rule'                  => $rule,
                'requested_targets'     => $targets,
                'feed_action'           => $feed_action,
                'effective_feed_url'    => $effective_feed_url,
                'feed_items_discovered' => count( $feed_items ),
                'matched_feed_items'    => count( $result_feed_items ),
                'duration_ms'           => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
                'echo_baseline'         => $echo_baseline_result,
                'slug_repair'           => $slug_repair_result,
                'result'                => $result,
            ]
        );
    } catch ( \Throwable $throwable ) {
        return hpr_force_sync_rest_response(
            [
                'success' => false,
                'message' => $throwable->getMessage(),
                'error'   => get_class( $throwable ),
            ],
            500
        );
    } finally {
        delete_transient( 'hpr_force_sync_running' );
    }
}

function hpr_force_sync_rest_response( array $body, $status = 200 ) {
    $response = new \WP_REST_Response( $body, $status );
    $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
    $response->header( 'Pragma', 'no-cache' );
    return $response;
}

function hpr_force_sync_discover_rule( $active_only = true ) {
    $rules = get_option( 'echo_rules_list', [] );
    if ( ! is_array( $rules ) ) {
        return [];
    }

    foreach ( $rules as $rule_id => $rule ) {
        if ( ! is_array( $rule ) ) {
            continue;
        }

        $feed_url  = isset( $rule[0] ) ? esc_url_raw( $rule[0] ) : '';
        $is_active = ! empty( $rule[1] );
        $post_type = isset( $rule[6] ) ? sanitize_key( $rule[6] ) : '';

        if ( $active_only && ! $is_active ) {
            continue;
        }

        if ( 'press-release' !== $post_type || empty( $feed_url ) ) {
            continue;
        }

        $parsed = wp_parse_url( $feed_url );
        if ( empty( $parsed['query'] ) ) {
            continue;
        }

        $query = [];
        parse_str( $parsed['query'], $query );

        if ( ( $query['feed'] ?? '' ) !== 'rss_publication' ) {
            continue;
        }

        return [
            'id'               => (int) $rule_id,
            'active'           => (bool) $is_active,
            'feed_url'         => $feed_url,
            'post_type'        => $post_type,
            'publication_slug' => sanitize_title( (string) ( $query['publication'] ?? '' ) ),
            'identity'         => isset( $rule[37] ) ? sanitize_text_field( (string) $rule[37] ) : '',
        ];
    }

    return [];
}

function hpr_force_sync_get_detected_publication_slug() {
    $rule = hpr_force_sync_discover_rule( false );
    return ! empty( $rule['publication_slug'] ) ? $rule['publication_slug'] : '';
}

function hpr_force_sync_get_detected_feed_url() {
    $rule = hpr_force_sync_discover_rule( false );
    return ! empty( $rule['feed_url'] ) ? $rule['feed_url'] : '';
}

function hpr_force_sync_get_endpoint_url() {
    return rest_url( 'hpr-distributor/v1/force-sync' );
}

function hpr_force_sync_get_signed_base_url() {
    $settings = hpr_force_sync_get_settings();
    return add_query_arg(
        [
            'token' => $settings['secret_token'],
        ],
        hpr_force_sync_get_endpoint_url()
    );
}

function hpr_force_sync_resolve_targets( \WP_REST_Request $request, array $before_map ) {
    $requested_slugs = hpr_force_sync_normalize_list_param( $request->get_param( 'slug' ) );
    $requested_slugs = array_merge( $requested_slugs, hpr_force_sync_normalize_list_param( $request->get_param( 'slugs' ) ) );

    $requested_source_urls = hpr_force_sync_normalize_list_param( $request->get_param( 'source_url' ) );
    $requested_source_urls = array_merge( $requested_source_urls, hpr_force_sync_normalize_list_param( $request->get_param( 'source_urls' ) ) );
    $requested_source_urls = array_map( __NAMESPACE__ . '\\hpr_force_sync_normalize_url', $requested_source_urls );

    $requested_post_ids = array_map( 'intval', hpr_force_sync_normalize_list_param( $request->get_param( 'post_id' ) ) );
    $requested_post_ids = array_merge( $requested_post_ids, array_map( 'intval', hpr_force_sync_normalize_list_param( $request->get_param( 'post_ids' ) ) ) );
    $requested_post_ids = array_values( array_filter( $requested_post_ids ) );

    $resolved_local_ids = [];

    foreach ( $requested_post_ids as $post_id ) {
        if ( empty( $before_map['post_ids'][ $post_id ] ) ) {
            continue;
        }

        $resolved_local_ids[] = $post_id;
        $row = $before_map['post_ids'][ $post_id ];

        if ( ! empty( $row['source_slug'] ) ) {
            $requested_slugs[] = $row['source_slug'];
        }

        if ( ! empty( $row['source_url'] ) ) {
            $requested_source_urls[] = $row['source_url'];
        }
    }

    return [
        'source_slugs'      => array_values( array_unique( array_filter( array_map( 'sanitize_title', $requested_slugs ) ) ) ),
        'source_urls'       => array_values( array_unique( array_filter( $requested_source_urls ) ) ),
        'local_post_ids'    => array_values( array_unique( $resolved_local_ids ) ),
        'requested_post_ids'=> array_values( array_unique( $requested_post_ids ) ),
    ];
}

function hpr_force_sync_build_effective_feed_url( $feed_url, $allowed_host, $feed_action ) {
    $parsed = wp_parse_url( $feed_url );
    if ( empty( $parsed['host'] ) ) {
        throw new \RuntimeException( 'The detected Echo feed URL is invalid.' );
    }

    if ( strtolower( $parsed['host'] ) !== strtolower( $allowed_host ) ) {
        throw new \RuntimeException( 'The detected Echo feed URL host is not allowed.' );
    }

    $query = [];
    if ( ! empty( $parsed['query'] ) ) {
        parse_str( $parsed['query'], $query );
    }

    if ( ! empty( $feed_action ) ) {
        $query['action'] = $feed_action;
    } else {
        unset( $query['action'] );
    }

    $query['v'] = (string) time();

    $rebuilt = $parsed['scheme'] . '://' . $parsed['host'];
    if ( ! empty( $parsed['port'] ) ) {
        $rebuilt .= ':' . $parsed['port'];
    }
    $rebuilt .= $parsed['path'] ?? '';

    return add_query_arg( $query, $rebuilt );
}

function hpr_force_sync_fetch_feed_items( $feed_url ) {
    $response = wp_remote_get(
        $feed_url,
        [
            'timeout'     => 90,
            'redirection' => 5,
            'headers'     => [
                'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        throw new \RuntimeException( 'Feed request failed: ' . $response->get_error_message() );
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    if ( 200 !== $status_code ) {
        throw new \RuntimeException( 'Feed request returned HTTP ' . $status_code . '.' );
    }

    $body = wp_remote_retrieve_body( $response );
    if ( '' === trim( $body ) ) {
        throw new \RuntimeException( 'Feed response was empty.' );
    }

    if ( ! function_exists( 'simplexml_load_string' ) ) {
        throw new \RuntimeException( 'SimpleXML is not available on this server.' );
    }

    libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );
    if ( false === $xml ) {
        $messages = [];
        foreach ( libxml_get_errors() as $error ) {
            $messages[] = trim( $error->message );
        }
        libxml_clear_errors();
        throw new \RuntimeException( 'Feed XML could not be parsed: ' . implode( '; ', $messages ) );
    }

    $items = [];
    if ( empty( $xml->channel->item ) ) {
        return $items;
    }

    foreach ( $xml->channel->item as $item ) {
        $source_url = hpr_force_sync_normalize_url( (string) $item->post_url );
        if ( empty( $source_url ) ) {
            $source_url = hpr_force_sync_normalize_url( (string) $item->link );
        }

        $source_slug = sanitize_title( (string) $item->post_slug );
        if ( empty( $source_slug ) && ! empty( $source_url ) ) {
            $source_slug = sanitize_title( basename( wp_parse_url( $source_url, PHP_URL_PATH ) ) );
        }

        $items[] = [
            'title'       => wp_strip_all_tags( (string) $item->title ),
            'source_url'  => $source_url,
            'source_slug' => $source_slug,
            'pub_date'    => (string) $item->pubDate,
        ];
    }

    return $items;
}

function hpr_force_sync_run_echo_rule_with_feed_override( $rule_id, $effective_feed_url ) {
    if ( ! function_exists( 'echo_run_rule' ) ) {
        throw new \RuntimeException( 'Echo RSS function echo_run_rule() is not available.' );
    }

    $rules    = get_option( 'echo_rules_list', [] );
    $original = isset( $rules[ $rule_id ][0] ) ? $rules[ $rule_id ][0] : '';

    if ( '' === $original ) {
        throw new \RuntimeException( 'Original Echo rule feed URL could not be read.' );
    }

    $restore = static function () use ( $rule_id, $original ) {
        $rules = get_option( 'echo_rules_list', [] );
        if ( isset( $rules[ $rule_id ] ) ) {
            $rules[ $rule_id ][0] = $original;
            update_option( 'echo_rules_list', $rules, false );
            wp_cache_delete( 'echo_rules_list', 'options' );
        }
    };

    try {
        $rules[ $rule_id ][0] = $effective_feed_url;
        update_option( 'echo_rules_list', $rules, false );
        wp_cache_delete( 'echo_rules_list', 'options' );
        echo_run_rule( $rule_id, 0 );
    } finally {
        $restore();
    }
}

function hpr_force_sync_get_imported_post_map( $rule_id ) {
    $post_ids = get_posts(
        [
            'post_type'              => 'press-release',
            'post_status'            => [ 'publish', 'draft', 'pending', 'trash' ],
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'   => 'echo_parent_rule',
                    'value' => (string) $rule_id,
                ],
            ],
        ]
    );

    $map = [
        'source_urls' => [],
        'post_ids'    => [],
    ];

    foreach ( $post_ids as $post_id ) {
        $source_url = hpr_force_sync_normalize_url( (string) get_post_meta( $post_id, 'original_post_url', true ) );
        if ( empty( $source_url ) ) {
            $source_url = hpr_force_sync_normalize_url( (string) get_post_meta( $post_id, 'echo_post_full_url', true ) );
        }

        $source_slug = sanitize_title( (string) get_post_meta( $post_id, 'original_post_slug', true ) );
        if ( empty( $source_slug ) && ! empty( $source_url ) ) {
            $source_slug = sanitize_title( basename( wp_parse_url( $source_url, PHP_URL_PATH ) ) );
        }

        $row = [
            'post_id'      => (int) $post_id,
            'post_title'   => get_the_title( $post_id ),
            'live_url'     => get_permalink( $post_id ),
            'source_url'   => $source_url,
            'source_slug'  => $source_slug,
            'modified_gmt' => (string) get_post_field( 'post_modified_gmt', $post_id ),
            'content_hash' => md5(
                (string) get_post_field( 'post_title', $post_id ) . "\n" .
                (string) get_post_field( 'post_content', $post_id ) . "\n" .
                (string) get_post_field( 'post_excerpt', $post_id )
            ),
        ];

        if ( ! empty( $source_url ) ) {
            $map['source_urls'][ $source_url ] = $row;
        }

        $map['post_ids'][ (int) $post_id ] = $row;
    }

    return $map;
}

function hpr_force_sync_build_result( array $before_map, array $after_map, array $feed_items, array $targets, $dry_run ) {
    $new_source_urls       = [];
    $new_live_urls         = [];
    $updated_source_urls   = [];
    $updated_live_urls     = [];
    $unchanged_source_urls = [];
    $missing_targets       = [];
    $not_imported_source_urls = [];

    foreach ( $feed_items as $item ) {
        $source_url = $item['source_url'];
        if ( empty( $source_url ) ) {
            continue;
        }

        if ( empty( $before_map['source_urls'][ $source_url ] ) ) {
            if ( ! empty( $after_map['source_urls'][ $source_url ] ) ) {
                $new_source_urls[] = $source_url;
                $new_live_urls[]   = $after_map['source_urls'][ $source_url ]['live_url'];
            } else {
                if ( $dry_run ) {
                    $new_source_urls[] = $source_url;
                } else {
                    $not_imported_source_urls[] = $source_url;
                }
            }
            continue;
        }

        if ( empty( $after_map['source_urls'][ $source_url ] ) ) {
            $not_imported_source_urls[] = $source_url;
            continue;
        }

        $before_row = $before_map['source_urls'][ $source_url ];
        $after_row  = $after_map['source_urls'][ $source_url ];

        if ( $before_row['content_hash'] !== $after_row['content_hash'] || $before_row['modified_gmt'] !== $after_row['modified_gmt'] ) {
            $updated_source_urls[] = $source_url;
            $updated_live_urls[]   = $after_row['live_url'];
        } else {
            $unchanged_source_urls[] = $source_url;
        }
    }

    $feed_source_urls = wp_list_pluck( $feed_items, 'source_url' );

    foreach ( $targets['source_urls'] as $source_url ) {
        if ( ! in_array( $source_url, $feed_source_urls, true ) ) {
            $missing_targets[] = $source_url;
        }
    }

    foreach ( $targets['source_slugs'] as $slug ) {
        $found = false;
        foreach ( $feed_items as $item ) {
            if ( $item['source_slug'] === $slug ) {
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            $missing_targets[] = $slug;
        }
    }

    return [
        'before_count'            => count( $before_map['source_urls'] ),
        'after_count'             => count( $after_map['source_urls'] ),
        'new_source_urls'         => array_values( array_unique( $new_source_urls ) ),
        'new_live_urls'           => array_values( array_unique( $new_live_urls ) ),
        'updated_source_urls'     => array_values( array_unique( $updated_source_urls ) ),
        'updated_live_urls'       => array_values( array_unique( $updated_live_urls ) ),
        'unchanged_source_urls'   => array_values( array_unique( $unchanged_source_urls ) ),
        'not_imported_source_urls'=> array_values( array_unique( $not_imported_source_urls ) ),
        'missing_targets'         => array_values( array_unique( array_filter( $missing_targets ) ) ),
        'last_url_processed'      => ! empty( $feed_items ) ? end( $feed_items )['source_url'] : '',
        'up_to_date'              => empty( $new_source_urls ) && empty( $updated_source_urls ) && empty( $not_imported_source_urls ),
    ];
}

function hpr_force_sync_filter_feed_items_by_targets( array $feed_items, array $targets ) {
    if ( ! hpr_force_sync_has_targets( $targets ) ) {
        return $feed_items;
    }

    $matched = [];
    foreach ( $feed_items as $item ) {
        if (
            ( ! empty( $targets['source_urls'] ) && in_array( $item['source_url'], $targets['source_urls'], true ) ) ||
            ( ! empty( $targets['source_slugs'] ) && in_array( $item['source_slug'], $targets['source_slugs'], true ) )
        ) {
            $matched[] = $item;
        }
    }

    return $matched;
}

function hpr_force_sync_has_targets( array $targets ) {
    return ! empty( $targets['source_slugs'] ) || ! empty( $targets['source_urls'] ) || ! empty( $targets['local_post_ids'] );
}

function hpr_force_sync_normalize_list_param( $value ) {
    if ( is_array( $value ) ) {
        return array_values(
            array_filter(
                array_map( 'sanitize_text_field', $value )
            )
        );
    }

    $value = (string) $value;
    if ( '' === trim( $value ) ) {
        return [];
    }

    $parts = preg_split( '/[\r\n,]+/', $value );
    if ( ! is_array( $parts ) ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map( 'sanitize_text_field', $parts )
        )
    );
}

function hpr_force_sync_normalize_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return '';
    }

    $parsed = wp_parse_url( $url );
    if ( empty( $parsed['host'] ) || empty( $parsed['scheme'] ) ) {
        return untrailingslashit( $url ) . '/';
    }

    $normalized = $parsed['scheme'] . '://' . $parsed['host'];
    if ( ! empty( $parsed['port'] ) ) {
        $normalized .= ':' . $parsed['port'];
    }

    $path = $parsed['path'] ?? '/';
    $normalized .= '/' === substr( $path, -1 ) ? $path : $path . '/';

    if ( ! empty( $parsed['query'] ) ) {
        $normalized .= '?' . $parsed['query'];
    }

    if ( ! empty( $parsed['fragment'] ) ) {
        $normalized .= '#' . $parsed['fragment'];
    }

    return $normalized;
}

function hpr_force_sync_to_bool( $value ) {
    if ( is_bool( $value ) ) {
        return $value;
    }

    return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
}
