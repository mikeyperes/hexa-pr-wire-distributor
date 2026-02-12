<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - SEO ACF Fields for Press Releases
 * 
 * Registers ACF fields on the press-release CPT:
 * - Follow override (dofollow / nofollow / inherit)
 * - Sitemap override (include / exclude / inherit)
 * 
 * Also renders a reporting panel below showing the resolved
 * effective status based on global → category → post hierarchy.
 * 
 * @since 2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register ACF fields for SEO overrides on press-release CPT
 */
function register_seo_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group([
        'key'      => 'group_hpr_seo_overrides',
        'title'    => 'SEO Settings — Hexa PR Wire',
        'fields'   => [
            [
                'key'           => 'field_hpr_seo_follow_override',
                'label'         => 'Anchor Follow Status Override',
                'name'          => 'hpr_seo_follow_override',
                'type'          => 'radio',
                'instructions'  => 'Override the global/category follow setting for this specific press release.',
                'required'      => 0,
                'choices'       => [
                    'inherit'   => 'Inherit (use category/global setting)',
                    'dofollow'  => 'Force Do Follow',
                    'nofollow'  => 'Force No Follow',
                ],
                'default_value' => 'inherit',
                'layout'        => 'horizontal',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_hpr_seo_sitemap_override',
                'label'         => 'Sitemap Inclusion Override',
                'name'          => 'hpr_seo_sitemap_override',
                'type'          => 'radio',
                'instructions'  => 'Override sitemap inclusion for this specific press release. Does not affect RSS feeds.',
                'required'      => 0,
                'choices'       => [
                    'inherit'  => 'Inherit (use category/global setting)',
                    'include'  => 'Force Include in Sitemap',
                    'exclude'  => 'Force Exclude from Sitemap',
                ],
                'default_value' => 'inherit',
                'layout'        => 'horizontal',
                'wrapper'       => [ 'width' => '50' ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'press-release',
                ],
            ],
        ],
        'menu_order'            => 90,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Per-post SEO overrides for follow status and sitemap inclusion.',
    ]);
}

add_action( 'acf/init', __NAMESPACE__ . '\\register_seo_acf_fields', 20 );

/**
 * Add a meta box below the ACF fields showing the resolved SEO status
 */
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\add_seo_status_meta_box' );

function add_seo_status_meta_box() {
    add_meta_box(
        'hpr_seo_status_report',
        '📊 SEO Status Report — Hexa PR Wire',
        __NAMESPACE__ . '\\render_seo_status_meta_box',
        'press-release',
        'normal',
        'default'
    );
}

/**
 * Render the SEO status reporting meta box
 */
function render_seo_status_meta_box( $post ) {
    // Get global settings
    $global_follow  = get_option( 'hpr_seo_follow_status', 'dofollow' );
    $global_sitemap = get_option( 'hpr_seo_sitemap_status', 'include' );
    $cat_follow_overrides  = get_option( 'hpr_seo_cat_follow_overrides', [] );
    $cat_sitemap_overrides = get_option( 'hpr_seo_cat_sitemap_overrides', [] );

    // Get post-level overrides
    $post_follow  = function_exists( 'get_field' ) ? get_field( 'hpr_seo_follow_override', $post->ID ) : 'inherit';
    $post_sitemap = function_exists( 'get_field' ) ? get_field( 'hpr_seo_sitemap_override', $post->ID ) : 'inherit';
    if ( ! $post_follow )  $post_follow  = 'inherit';
    if ( ! $post_sitemap ) $post_sitemap = 'inherit';

    // Get post categories
    $post_cats = wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
    $post_cat_ids = wp_list_pluck( $post_cats, 'term_id' );

    // Resolve category-level overrides
    $cat_follow_match  = null;
    $cat_sitemap_match = null;
    if ( is_array( $cat_follow_overrides ) ) {
        foreach ( $cat_follow_overrides as $o ) {
            if ( in_array( (int) $o['id'], $post_cat_ids, true ) ) {
                $cat_follow_match = $o;
                break;
            }
        }
    }
    if ( is_array( $cat_sitemap_overrides ) ) {
        foreach ( $cat_sitemap_overrides as $o ) {
            if ( in_array( (int) $o['id'], $post_cat_ids, true ) ) {
                $cat_sitemap_match = $o;
                break;
            }
        }
    }

    // Resolve effective values
    $effective_follow  = hpr_resolve_follow_for_report( $post_follow, $cat_follow_match, $global_follow );
    $effective_sitemap = hpr_resolve_sitemap_for_report( $post_sitemap, $cat_sitemap_match, $global_sitemap );

    // Labels
    $follow_labels = [
        'dofollow' => [ 'Do Follow', 'status-ok' ],
        'nofollow' => [ 'No Follow', 'status-bad' ],
        'default'  => [ 'Default (no modification)', 'status-warn' ],
    ];
    $sitemap_labels = [
        'include' => [ 'Included in Sitemap', 'status-ok' ],
        'exclude' => [ 'Excluded from Sitemap', 'status-bad' ],
    ];

    $fl = $follow_labels[ $effective_follow['value'] ] ?? [ $effective_follow['value'], '' ];
    $sl = $sitemap_labels[ $effective_sitemap['value'] ] ?? [ $effective_sitemap['value'], '' ];

    ?>
    <style>
        .hpr-seo-report { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .hpr-seo-report-card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px; }
        .hpr-seo-report-card h4 { margin: 0 0 10px; font-size: 14px; }
        .hpr-seo-report-card .effective { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
        .hpr-seo-report-card .breakdown { font-size: 12px; color: #666; line-height: 1.7; }
        .hpr-seo-report-card .breakdown .level { display: flex; justify-content: space-between; padding: 2px 0; border-bottom: 1px solid #eee; }
        .hpr-seo-report-card .breakdown .level.active { font-weight: 600; color: #1d2327; }
        .hpr-seo-report-card .breakdown .arrow { color: #2271b1; font-weight: bold; }
        .hpr-seo-report-card .status-ok { color: #00a32a; }
        .hpr-seo-report-card .status-bad { color: #d63638; }
        .hpr-seo-report-card .status-warn { color: #dba617; }
        @media (max-width: 782px) { .hpr-seo-report { grid-template-columns: 1fr; } }
    </style>

    <div class="hpr-seo-report">
        <!-- Follow Status -->
        <div class="hpr-seo-report-card">
            <h4>🔗 Anchor Follow Status</h4>
            <div class="effective">
                <span class="<?php echo esc_attr( $fl[1] ); ?>"><?php echo esc_html( $fl[0] ); ?></span>
                <small style="font-weight:normal;color:#888;font-size:12px;"> — determined by <?php echo esc_html( $effective_follow['source'] ); ?></small>
            </div>
            <div class="breakdown">
                <div class="level <?php echo $effective_follow['source'] === 'post override' ? 'active' : ''; ?>">
                    <span>① Post Override</span>
                    <span><?php echo $post_follow === 'inherit' ? '<em>Inherit</em>' : esc_html( $post_follow ); ?></span>
                </div>
                <div class="level <?php echo $effective_follow['source'] === 'category override' ? 'active' : ''; ?>">
                    <span>② Category Override</span>
                    <span><?php echo $cat_follow_match ? esc_html( $cat_follow_match['name'] . ': ' . $cat_follow_match['status'] ) : '<em>None</em>'; ?></span>
                </div>
                <div class="level <?php echo $effective_follow['source'] === 'global setting' ? 'active' : ''; ?>">
                    <span>③ Global Setting</span>
                    <span><?php echo esc_html( $global_follow ); ?></span>
                </div>
            </div>
            <p style="margin:10px 0 0;font-size:11px;color:#888;">
                <a href="<?php echo admin_url( 'options-general.php?page=hpr-distributor' ); ?>">Manage in Settings → SEO Settings</a>
            </p>
        </div>

        <!-- Sitemap Status -->
        <div class="hpr-seo-report-card">
            <h4>🗺️ Sitemap Inclusion</h4>
            <div class="effective">
                <span class="<?php echo esc_attr( $sl[1] ); ?>"><?php echo esc_html( $sl[0] ); ?></span>
                <small style="font-weight:normal;color:#888;font-size:12px;"> — determined by <?php echo esc_html( $effective_sitemap['source'] ); ?></small>
            </div>
            <div class="breakdown">
                <div class="level <?php echo $effective_sitemap['source'] === 'post override' ? 'active' : ''; ?>">
                    <span>① Post Override</span>
                    <span><?php echo $post_sitemap === 'inherit' ? '<em>Inherit</em>' : esc_html( $post_sitemap ); ?></span>
                </div>
                <div class="level <?php echo $effective_sitemap['source'] === 'category override' ? 'active' : ''; ?>">
                    <span>② Category Override</span>
                    <span><?php echo $cat_sitemap_match ? esc_html( $cat_sitemap_match['name'] . ': ' . $cat_sitemap_match['status'] ) : '<em>None</em>'; ?></span>
                </div>
                <div class="level <?php echo $effective_sitemap['source'] === 'global setting' ? 'active' : ''; ?>">
                    <span>③ Global Setting</span>
                    <span><?php echo esc_html( $global_sitemap ); ?></span>
                </div>
            </div>
            <p style="margin:10px 0 0;font-size:11px;color:#888;">
                Does not affect RSS feeds.
                <a href="<?php echo admin_url( 'options-general.php?page=hpr-distributor' ); ?>">Manage in Settings → SEO Settings</a>
            </p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Live-update the report when ACF radio fields change
        function refreshReport() {
            // We could do a mini AJAX refresh here, but for now just note that
            // the report reflects saved values. Show a hint to save + refresh.
            var $report = $('.hpr-seo-report');
            if (!$report.find('.hpr-live-hint').length) {
                $report.after('<p class="hpr-live-hint" style="color:#dba617;font-size:12px;margin-top:8px;">ℹ Save/Update the post to see the report reflect your changes.</p>');
            }
        }
        $('input[name="acf[field_hpr_seo_follow_override]"], input[name="acf[field_hpr_seo_sitemap_override]"]').on('change', refreshReport);
    });
    </script>
    <?php
}

/**
 * Resolve follow status for the report display (with source tracking)
 */
function hpr_resolve_follow_for_report( $post_override, $cat_match, $global ) {
    if ( $post_override && $post_override !== 'inherit' && $post_override !== '' ) {
        return [ 'value' => $post_override, 'source' => 'post override' ];
    }
    if ( $cat_match ) {
        return [ 'value' => $cat_match['status'], 'source' => 'category override' ];
    }
    return [ 'value' => $global, 'source' => 'global setting' ];
}

/**
 * Resolve sitemap status for the report display (with source tracking)
 */
function hpr_resolve_sitemap_for_report( $post_override, $cat_match, $global ) {
    if ( $post_override && $post_override !== 'inherit' && $post_override !== '' ) {
        return [ 'value' => $post_override, 'source' => 'post override' ];
    }
    if ( $cat_match ) {
        return [ 'value' => $cat_match['status'], 'source' => 'category override' ];
    }
    return [ 'value' => $global, 'source' => 'global setting' ];
}
