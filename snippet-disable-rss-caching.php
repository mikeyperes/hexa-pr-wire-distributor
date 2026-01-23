<?php
namespace hpr_distributor;

/**
 * Snippet: Disable RSS Feed Caching
 * 
 * Prevents LiteSpeed Cache and WordPress from caching RSS feeds.
 * This ensures that RSS feeds always return fresh data, which is critical
 * for press release distribution where timing matters.
 * 
 * What this does:
 * 1. Adds no-cache headers to RSS feeds
 * 2. Tells LiteSpeed Cache to exclude RSS feeds
 * 3. Disables WordPress built-in feed caching
 * 4. Adds cache-control headers for CDNs
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main function to disable RSS caching
 * Called when the snippet is enabled
 */
function disable_rss_caching() {
    // Hook into feed generation
    add_action( 'rss_head', __NAMESPACE__ . '\\add_rss_nocache_headers' );
    add_action( 'rss2_head', __NAMESPACE__ . '\\add_rss_nocache_headers' );
    add_action( 'atom_head', __NAMESPACE__ . '\\add_rss_nocache_headers' );
    add_action( 'rdf_head', __NAMESPACE__ . '\\add_rss_nocache_headers' );
    
    // Send headers before feed output
    add_action( 'do_feed_rss', __NAMESPACE__ . '\\send_nocache_headers_early', 1 );
    add_action( 'do_feed_rss2', __NAMESPACE__ . '\\send_nocache_headers_early', 1 );
    add_action( 'do_feed_atom', __NAMESPACE__ . '\\send_nocache_headers_early', 1 );
    add_action( 'do_feed_rdf', __NAMESPACE__ . '\\send_nocache_headers_early', 1 );
    
    // LiteSpeed Cache exclusion
    add_filter( 'litespeed_cache_exclude', __NAMESPACE__ . '\\exclude_feeds_from_litespeed' );
    add_action( 'template_redirect', __NAMESPACE__ . '\\litespeed_feed_nocache', 1 );
    
    // Disable WordPress built-in feed caching
    add_filter( 'wp_feed_cache_transient_lifetime', __NAMESPACE__ . '\\disable_feed_cache_lifetime' );
    
    // Custom RSS feed support
    add_action( 'init', __NAMESPACE__ . '\\setup_custom_feed_nocache' );
}

/**
 * Add no-cache comment to RSS head (visible in feed)
 */
function add_rss_nocache_headers() {
    echo "<!-- Cache-Control: no-cache, no-store, must-revalidate -->\n";
    echo "<!-- Pragma: no-cache -->\n";
    echo "<!-- Expires: 0 -->\n";
}

/**
 * Send HTTP headers before feed output
 */
function send_nocache_headers_early() {
    if ( ! headers_sent() ) {
        header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        header( 'X-Accel-Expires: 0' ); // Nginx
        header( 'X-LiteSpeed-Cache-Control: no-cache' ); // LiteSpeed
        header( 'Surrogate-Control: no-store' ); // CDN
    }
}

/**
 * Exclude feeds from LiteSpeed Cache
 * 
 * @param array $excludes Current exclusion patterns
 * @return array Modified exclusion patterns
 */
function exclude_feeds_from_litespeed( $excludes ) {
    if ( ! is_array( $excludes ) ) {
        $excludes = [];
    }
    
    // Add feed URLs to exclusion list
    $excludes[] = '/feed';
    $excludes[] = '/feed/';
    $excludes[] = '/feed/*';
    $excludes[] = '*/feed';
    $excludes[] = '*/feed/';
    $excludes[] = '/rss';
    $excludes[] = '/rss/';
    $excludes[] = '/atom';
    $excludes[] = '/atom/';
    $excludes[] = '/rdf';
    $excludes[] = '/rdf/';
    $excludes[] = '/internal-rss';
    $excludes[] = '/internal-rss/';
    
    return array_unique( $excludes );
}

/**
 * Tell LiteSpeed not to cache this request if it's a feed
 */
function litespeed_feed_nocache() {
    if ( is_feed() ) {
        // Set LiteSpeed no-cache headers
        if ( ! headers_sent() ) {
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
        }
        
        // Use LiteSpeed's API if available
        if ( class_exists( '\LiteSpeed\Core' ) ) {
            do_action( 'litespeed_control_set_nocache', 'RSS feed - caching disabled by Hexa PR Wire' );
        }
        
        // Alternative: Define constant that LiteSpeed checks
        if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
            define( 'LSCACHE_NO_CACHE', true );
        }
    }
}

/**
 * Disable WordPress built-in feed caching
 * 
 * @param int $lifetime Cache lifetime in seconds
 * @return int Modified lifetime (0 = no cache)
 */
function disable_feed_cache_lifetime( $lifetime ) {
    return 0; // No caching
}

/**
 * Setup no-cache for custom feeds (like internal-rss)
 */
function setup_custom_feed_nocache() {
    // Hook into custom feed registration
    add_action( 'do_feed_internal-rss', __NAMESPACE__ . '\\send_nocache_headers_early', 1 );
    
    // For any feed request, send headers
    add_action( 'pre_get_posts', function( $query ) {
        if ( $query->is_feed() ) {
            send_nocache_headers_early();
        }
    });
}

/**
 * Get status of RSS caching
 * Used by system checks
 * 
 * @return array Status array with status bool and message
 */
function check_rss_caching_status() {
    $is_enabled = get_option( 'disable_rss_caching', false );
    
    if ( $is_enabled ) {
        return [
            'status'    => true,
            'raw_value' => 'RSS feed caching is disabled - feeds will always be fresh',
        ];
    }
    
    // Check if LiteSpeed is active and might be caching feeds
    $litespeed_active = is_plugin_active( 'litespeed-cache/litespeed-cache.php' );
    
    if ( $litespeed_active ) {
        return [
            'status'    => false,
            'raw_value' => 'LiteSpeed Cache is active and may be caching RSS feeds. Enable "Disable RSS Feed Caching" snippet.',
        ];
    }
    
    return [
        'status'    => false,
        'raw_value' => 'RSS feed caching may be active. Enable "Disable RSS Feed Caching" snippet for fresh feeds.',
    ];
}
