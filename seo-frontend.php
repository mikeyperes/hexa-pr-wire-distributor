<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - SEO Frontend Logic
 * 
 * Executes on the frontend to:
 * 1. Filter press-release post content to add/remove rel="nofollow" on anchors
 * 2. Filter RankMath sitemap to include/exclude individual press-release posts
 * 
 * Priority hierarchy (strongest → weakest):
 *   1. Per-post ACF field override  (hpr_seo_follow_override / hpr_seo_sitemap_override)
 *   2. Category-level override       (from settings page)
 *   3. Global setting                 (from settings page)
 * 
 * @since 2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ═══════════════════════════════════════════════
 * FOLLOW STATUS — content filter
 * ═══════════════════════════════════════════════ */

add_filter( 'the_content', __NAMESPACE__ . '\\hpr_filter_press_release_links', 50 );

/**
 * Filter content of press-release posts to enforce follow/nofollow
 */
function hpr_filter_press_release_links( $content ) {
    // Only apply to press-release CPT on frontend
    if ( is_admin() || get_post_type() !== 'press-release' ) {
        return $content;
    }

    // Skip if content has no links
    if ( stripos( $content, '<a ' ) === false ) {
        return $content;
    }

    $resolved = hpr_resolve_follow_status( get_the_ID() );

    // 'default' means do not modify anything
    if ( $resolved === 'default' ) {
        return $content;
    }

    // Use DOMDocument to safely manipulate links
    if ( ! class_exists( 'DOMDocument' ) ) {
        // Fallback regex approach
        return hpr_filter_links_regex( $content, $resolved );
    }

    // Suppress libxml errors for malformed HTML
    $prev = libxml_use_internal_errors( true );

    $doc = new \DOMDocument();
    // Wrap in div to avoid auto-wrapping issues; use UTF-8
    $wrapped = '<div>' . mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) . '</div>';
    $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $wrapped . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

    $links = $doc->getElementsByTagName( 'a' );

    foreach ( $links as $link ) {
        $rel = $link->getAttribute( 'rel' );
        $rel_parts = $rel ? array_filter( array_map( 'trim', explode( ' ', $rel ) ) ) : [];

        if ( $resolved === 'nofollow' ) {
            // Ensure nofollow is present
            if ( ! in_array( 'nofollow', $rel_parts, true ) ) {
                $rel_parts[] = 'nofollow';
            }
        } elseif ( $resolved === 'dofollow' ) {
            // Remove nofollow if present
            $rel_parts = array_filter( $rel_parts, function( $v ) {
                return $v !== 'nofollow';
            });
        }

        if ( ! empty( $rel_parts ) ) {
            $link->setAttribute( 'rel', implode( ' ', $rel_parts ) );
        } else {
            $link->removeAttribute( 'rel' );
        }
    }

    // Extract the inner content of our wrapper div
    $body = $doc->getElementsByTagName( 'body' )->item( 0 );
    $div  = $body ? $body->firstChild : null;

    $output = '';
    if ( $div ) {
        foreach ( $div->childNodes as $child ) {
            $output .= $doc->saveHTML( $child );
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    return $output ?: $content;
}

/**
 * Regex fallback for follow/nofollow when DOMDocument is unavailable
 */
function hpr_filter_links_regex( $content, $resolved ) {
    if ( $resolved === 'nofollow' ) {
        // Add nofollow to links that don't already have it
        $content = preg_replace_callback( '/<a\s([^>]*?)>/i', function( $m ) {
            $attrs = $m[1];
            if ( preg_match( '/rel\s*=\s*["\']([^"\']*)["\']/', $attrs, $rel_match ) ) {
                $rels = array_filter( array_map( 'trim', explode( ' ', $rel_match[1] ) ) );
                if ( ! in_array( 'nofollow', $rels, true ) ) {
                    $rels[] = 'nofollow';
                    $attrs = preg_replace( '/rel\s*=\s*["\'][^"\']*["\']/', 'rel="' . implode( ' ', $rels ) . '"', $attrs );
                }
            } else {
                $attrs .= ' rel="nofollow"';
            }
            return '<a ' . $attrs . '>';
        }, $content );
    } elseif ( $resolved === 'dofollow' ) {
        // Remove nofollow from all links
        $content = preg_replace_callback( '/<a\s([^>]*?)>/i', function( $m ) {
            $attrs = $m[1];
            if ( preg_match( '/rel\s*=\s*["\']([^"\']*)["\']/', $attrs, $rel_match ) ) {
                $rels = array_filter( array_map( 'trim', explode( ' ', $rel_match[1] ) ), function( $v ) {
                    return $v !== 'nofollow';
                });
                if ( empty( $rels ) ) {
                    $attrs = preg_replace( '/\s*rel\s*=\s*["\'][^"\']*["\']/', '', $attrs );
                } else {
                    $attrs = preg_replace( '/rel\s*=\s*["\'][^"\']*["\']/', 'rel="' . implode( ' ', $rels ) . '"', $attrs );
                }
            }
            return '<a ' . $attrs . '>';
        }, $content );
    }

    return $content;
}

/**
 * Resolve the effective follow status for a given press-release post.
 * 
 * Priority: post ACF → category override → global
 * 
 * @param int $post_id
 * @return string  'dofollow' | 'nofollow' | 'default'
 */
function hpr_resolve_follow_status( $post_id ) {
    // 1. Per-post ACF override
    if ( function_exists( 'get_field' ) ) {
        $post_override = get_field( 'hpr_seo_follow_override', $post_id );
        if ( $post_override && $post_override !== 'inherit' && $post_override !== '' ) {
            return $post_override; // 'dofollow' | 'nofollow'
        }
    }

    // 2. Category-level override
    $cat_overrides = get_option( 'hpr_seo_cat_follow_overrides', [] );
    if ( ! empty( $cat_overrides ) && is_array( $cat_overrides ) ) {
        $post_cats = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
        foreach ( $cat_overrides as $override ) {
            if ( in_array( (int) $override['id'], $post_cats, true ) ) {
                return $override['status']; // 'dofollow' | 'nofollow'
            }
        }
    }

    // 3. Global setting
    return get_option( 'hpr_seo_follow_status', 'dofollow' );
}


/* ═══════════════════════════════════════════════
 * SITEMAP — RankMath per-post/per-category filter
 * ═══════════════════════════════════════════════ */

/**
 * Filter RankMath's robots meta for individual press-release posts.
 * This controls whether RankMath includes a post in sitemap via noindex.
 * We use the `rank_math/sitemap/entry` filter to exclude specific posts.
 */
add_filter( 'rank_math/sitemap/entry', __NAMESPACE__ . '\\hpr_filter_sitemap_entry', 10, 3 );

/**
 * Filter individual sitemap entries for press-release posts.
 * Return false to exclude, return $entry to include.
 */
function hpr_filter_sitemap_entry( $entry, $type, $post ) {
    // Only filter press-release posts in the post_type sitemap
    if ( ! is_object( $post ) || get_post_type( $post ) !== 'press-release' ) {
        return $entry;
    }

    $resolved = hpr_resolve_sitemap_status( $post->ID );

    if ( $resolved === 'exclude' ) {
        return false; // Remove from sitemap
    }

    return $entry; // Keep in sitemap
}

/**
 * Also hook into rank_math/sitemap/urlimages to handle the URL-level filter
 * for press-release posts — RankMath sometimes uses this entry point.
 */
add_filter( 'rank_math/sitemap/url', __NAMESPACE__ . '\\hpr_filter_sitemap_url', 10, 2 );

function hpr_filter_sitemap_url( $url, $post ) {
    if ( ! is_object( $post ) || get_post_type( $post ) !== 'press-release' ) {
        return $url;
    }

    $resolved = hpr_resolve_sitemap_status( $post->ID );

    if ( $resolved === 'exclude' ) {
        return false;
    }

    return $url;
}

/**
 * Resolve the effective sitemap status for a given press-release post.
 * 
 * Priority: post ACF → category override → global
 * 
 * @param int $post_id
 * @return string 'include' | 'exclude'
 */
function hpr_resolve_sitemap_status( $post_id ) {
    // 1. Per-post ACF override
    if ( function_exists( 'get_field' ) ) {
        $post_override = get_field( 'hpr_seo_sitemap_override', $post_id );
        if ( $post_override && $post_override !== 'inherit' && $post_override !== '' ) {
            return $post_override; // 'include' | 'exclude'
        }
    }

    // 2. Category-level override
    $cat_overrides = get_option( 'hpr_seo_cat_sitemap_overrides', [] );
    if ( ! empty( $cat_overrides ) && is_array( $cat_overrides ) ) {
        $post_cats = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
        foreach ( $cat_overrides as $override ) {
            if ( in_array( (int) $override['id'], $post_cats, true ) ) {
                return $override['status']; // 'include' | 'exclude'
            }
        }
    }

    // 3. Global setting
    return get_option( 'hpr_seo_sitemap_status', 'include' );
}

/**
 * RSS feeds should NOT be affected by sitemap exclusions.
 * No filtering on RSS hooks — by design.
 */
