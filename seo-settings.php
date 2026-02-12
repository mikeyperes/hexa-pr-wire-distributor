<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - SEO Settings
 * 
 * Settings for controlling:
 * - Anchor follow status (dofollow / nofollow / default) globally
 * - Per-category follow overrides (autocomplete search)
 * - Sitemap inclusion/exclusion for press-release CPT (RankMath integration)
 * - Per-category sitemap overrides (autocomplete search)
 * - Flush permalinks & purge sitemap cache
 * - RankMath sitemap status confirmation
 * 
 * Priority order (strongest → weakest):
 *   1. Single post ACF override
 *   2. Category-level override (from settings page)
 *   3. Global setting
 * 
 * @since 2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ─── AJAX handlers ─── */

// Category autocomplete search
add_action( 'wp_ajax_hpr_search_categories', __NAMESPACE__ . '\\ajax_search_categories' );

// Save SEO settings
add_action( 'wp_ajax_hpr_save_seo_settings', __NAMESPACE__ . '\\ajax_save_seo_settings' );

// Flush permalinks & sitemap cache
add_action( 'wp_ajax_hpr_flush_permalinks_sitemap', __NAMESPACE__ . '\\ajax_flush_permalinks_sitemap' );

/**
 * AJAX: Search categories for press-release CPT (autocomplete)
 */
function ajax_search_categories() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $search = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';
    if ( strlen( $search ) < 2 ) {
        wp_send_json_success( [] );
    }

    $terms = get_terms([
        'taxonomy'   => 'category',
        'search'     => $search,
        'hide_empty' => false,
        'number'     => 20,
    ]);

    $results = [];
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $t ) {
            $results[] = [
                'id'   => $t->term_id,
                'slug' => $t->slug,
                'name' => $t->name,
            ];
        }
    }

    wp_send_json_success( $results );
}

/**
 * AJAX: Save SEO settings
 */
function ajax_save_seo_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    // --- Follow settings ---
    $follow_status = isset( $_POST['hpr_follow_status'] ) ? sanitize_text_field( $_POST['hpr_follow_status'] ) : 'dofollow';
    update_option( 'hpr_seo_follow_status', $follow_status );

    // Category follow overrides – stored as JSON array [ { id, slug, name, status } ]
    $cat_follow_raw = isset( $_POST['hpr_cat_follow_overrides'] ) ? wp_unslash( $_POST['hpr_cat_follow_overrides'] ) : '[]';
    $cat_follow = json_decode( $cat_follow_raw, true );
    if ( ! is_array( $cat_follow ) ) $cat_follow = [];
    // Sanitize
    $cat_follow_clean = [];
    foreach ( $cat_follow as $item ) {
        $cat_follow_clean[] = [
            'id'     => absint( $item['id'] ?? 0 ),
            'slug'   => sanitize_text_field( $item['slug'] ?? '' ),
            'name'   => sanitize_text_field( $item['name'] ?? '' ),
            'status' => in_array( $item['status'] ?? '', [ 'dofollow', 'nofollow' ], true ) ? $item['status'] : 'dofollow',
        ];
    }
    update_option( 'hpr_seo_cat_follow_overrides', $cat_follow_clean );

    // --- Sitemap settings ---
    $sitemap_status = isset( $_POST['hpr_sitemap_status'] ) ? sanitize_text_field( $_POST['hpr_sitemap_status'] ) : 'include';
    update_option( 'hpr_seo_sitemap_status', $sitemap_status );

    // Category sitemap overrides
    $cat_sitemap_raw = isset( $_POST['hpr_cat_sitemap_overrides'] ) ? wp_unslash( $_POST['hpr_cat_sitemap_overrides'] ) : '[]';
    $cat_sitemap = json_decode( $cat_sitemap_raw, true );
    if ( ! is_array( $cat_sitemap ) ) $cat_sitemap = [];
    $cat_sitemap_clean = [];
    foreach ( $cat_sitemap as $item ) {
        $cat_sitemap_clean[] = [
            'id'     => absint( $item['id'] ?? 0 ),
            'slug'   => sanitize_text_field( $item['slug'] ?? '' ),
            'name'   => sanitize_text_field( $item['name'] ?? '' ),
            'status' => in_array( $item['status'] ?? '', [ 'include', 'exclude' ], true ) ? $item['status'] : 'include',
        ];
    }
    update_option( 'hpr_seo_cat_sitemap_overrides', $cat_sitemap_clean );

    // --- Apply sitemap setting to RankMath if active ---
    hpr_sync_rankmath_sitemap_setting( $sitemap_status );

    wp_send_json_success( [ 'message' => 'SEO settings saved.' ] );
}

/**
 * AJAX: Flush permalinks + purge sitemap cache
 */
function ajax_flush_permalinks_sitemap() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    // Flush rewrite rules
    flush_rewrite_rules( true );

    // Purge RankMath sitemap cache if available
    $rm_purged = false;
    if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) {
        \RankMath\Sitemap\Cache::invalidate_storage();
        $rm_purged = true;
    }
    // Also try the helper if available
    if ( function_exists( 'rank_math' ) ) {
        delete_transient( 'rank_math_sitemap_cache_status' );
    }

    wp_send_json_success([
        'message'       => 'Permalinks flushed.' . ( $rm_purged ? ' RankMath sitemap cache purged.' : '' ),
        'rankmath_purged' => $rm_purged,
    ]);
}

/* ─── RankMath Integration Helpers ─── */

/**
 * Sync sitemap on/off for press-release CPT with RankMath settings.
 * Works WITH RankMath — reads its options and merges our setting.
 */
function hpr_sync_rankmath_sitemap_setting( $status ) {
    if ( ! class_exists( '\\RankMath\\Helper' ) ) return;

    // RankMath stores sitemap post types in option `rank_math_modules`
    // and the actual toggle in `rank-math-options-sitemap`
    $sitemap_opts = get_option( 'rank-math-options-sitemap', [] );
    if ( ! is_array( $sitemap_opts ) ) $sitemap_opts = [];

    if ( $status === 'include' ) {
        $sitemap_opts['pt_press-release_sitemap'] = 'on';
    } elseif ( $status === 'exclude' ) {
        $sitemap_opts['pt_press-release_sitemap'] = 'off';
    }
    // 'default' → leave RankMath's own setting untouched

    update_option( 'rank-math-options-sitemap', $sitemap_opts );

    // Invalidate sitemap cache
    if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }
}

/**
 * Get current RankMath sitemap status for press-release CPT
 */
function hpr_get_rankmath_sitemap_info() {
    $info = [
        'active'          => false,
        'cpt_in_sitemap'  => 'unknown',
        'sitemap_url'     => '',
        'module_active'   => false,
    ];

    if ( ! class_exists( '\\RankMath\\Helper' ) ) {
        return $info;
    }

    $info['active'] = true;

    // Check if sitemap module is active
    $modules = (array) get_option( 'rank_math_modules', [] );
    $info['module_active'] = in_array( 'sitemap', $modules, true );

    // Check CPT setting
    $sitemap_opts = get_option( 'rank-math-options-sitemap', [] );
    if ( is_array( $sitemap_opts ) && isset( $sitemap_opts['pt_press-release_sitemap'] ) ) {
        $info['cpt_in_sitemap'] = $sitemap_opts['pt_press-release_sitemap'] === 'on' ? 'yes' : 'no';
    }

    // Build sitemap URL
    $info['sitemap_url'] = home_url( '/press-release-sitemap.xml' );

    return $info;
}

/* ─── Display Function ─── */

/**
 * Display SEO Settings panel inside Overview tab
 */
function display_seo_settings() {
    // Current values
    $follow_status   = get_option( 'hpr_seo_follow_status', 'dofollow' );
    $cat_follow      = get_option( 'hpr_seo_cat_follow_overrides', [] );
    $sitemap_status  = get_option( 'hpr_seo_sitemap_status', 'include' );
    $cat_sitemap     = get_option( 'hpr_seo_cat_sitemap_overrides', [] );
    $rm_info         = hpr_get_rankmath_sitemap_info();

    if ( ! is_array( $cat_follow ) )  $cat_follow  = [];
    if ( ! is_array( $cat_sitemap ) ) $cat_sitemap = [];
    ?>

    <!-- ═══════════════ SEO SETTINGS ═══════════════ -->
    <div class="hpr-panel" id="hpr-seo-settings">
        <div class="hpr-panel-header">🔍 SEO Settings</div>
        <div class="hpr-panel-body">

            <!-- ── Follow Status ── -->
            <h3 style="margin-top:0;">Anchor Follow Status</h3>
            <p style="color:#646970;margin-bottom:12px;">
                Controls <code>rel="nofollow"</code> on all links inside <strong>press-release</strong> post content.
                Priority: <strong>Post override → Category override → This global setting</strong>.
            </p>

            <div style="margin-bottom:18px;">
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="hpr_follow_status" value="dofollow" <?php checked( $follow_status, 'dofollow' ); ?>>
                    <strong>Do Follow</strong> — links pass authority <em>(default)</em>
                </label>
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="hpr_follow_status" value="nofollow" <?php checked( $follow_status, 'nofollow' ); ?>>
                    <strong>No Follow</strong> — links do not pass authority
                </label>
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="hpr_follow_status" value="default" <?php checked( $follow_status, 'default' ); ?>>
                    <strong>Use default settings</strong> — do not modify links
                </label>
            </div>

            <!-- Category Follow Overrides -->
            <h4>Category Follow Overrides</h4>
            <p style="color:#646970;font-size:13px;">Force a specific follow status for press releases in certain categories. These override the global setting above.</p>

            <div id="hpr-cat-follow-search" style="margin-bottom:8px;">
                <input type="text" id="hpr-cat-follow-input" placeholder="Start typing a category name…" autocomplete="off" class="regular-text" style="width:300px;">
                <select id="hpr-cat-follow-status-select" style="vertical-align:middle;">
                    <option value="dofollow">Do Follow</option>
                    <option value="nofollow">No Follow</option>
                </select>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-cat-follow-add-btn" disabled>+ Add</button>
                <div id="hpr-cat-follow-suggestions" class="hpr-autocomplete-dropdown"></div>
            </div>

            <table class="hpr-table" id="hpr-cat-follow-table" style="margin-bottom:20px;<?php echo empty($cat_follow) ? 'display:none;' : ''; ?>">
                <thead><tr><th>Category</th><th>Status</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    <?php foreach ( $cat_follow as $cf ) : ?>
                    <tr data-id="<?php echo esc_attr($cf['id']); ?>" data-slug="<?php echo esc_attr($cf['slug']); ?>" data-status="<?php echo esc_attr($cf['status']); ?>">
                        <td><?php echo esc_html($cf['name']); ?> <code style="font-size:11px;color:#888;"><?php echo esc_html($cf['slug']); ?></code></td>
                        <td><span class="<?php echo $cf['status'] === 'nofollow' ? 'status-bad' : 'status-ok'; ?>"><?php echo $cf['status'] === 'nofollow' ? 'No Follow' : 'Do Follow'; ?></span></td>
                        <td><button type="button" class="hpr-btn hpr-btn-danger hpr-cat-follow-remove" style="padding:4px 10px;font-size:11px;">✗</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr style="margin:28px 0;">

            <!-- ── Sitemap Settings ── -->
            <h3>Sitemap Settings</h3>
            <p style="color:#646970;margin-bottom:12px;">
                Controls whether the <strong>press-release</strong> CPT is included in the XML sitemap.
                Works with <strong>RankMath</strong> — reads and sets RankMath's sitemap options.
                <strong>Does not affect RSS feeds.</strong>
                Priority: <strong>Post override → Category override → This global setting</strong>.
            </p>

            <div style="margin-bottom:18px;">
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="hpr_sitemap_status" value="include" <?php checked( $sitemap_status, 'include' ); ?>>
                    <strong>Include in Sitemap</strong> <em>(default)</em>
                </label>
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="hpr_sitemap_status" value="exclude" <?php checked( $sitemap_status, 'exclude' ); ?>>
                    <strong>Exclude from Sitemap</strong>
                </label>
            </div>

            <!-- Category Sitemap Overrides -->
            <h4>Category Sitemap Overrides</h4>
            <p style="color:#646970;font-size:13px;">Force include or exclude press releases in certain categories from the sitemap. Useful for excluding the CPT globally but including a subsection, or vice versa.</p>

            <div id="hpr-cat-sitemap-search" style="margin-bottom:8px;">
                <input type="text" id="hpr-cat-sitemap-input" placeholder="Start typing a category name…" autocomplete="off" class="regular-text" style="width:300px;">
                <select id="hpr-cat-sitemap-status-select" style="vertical-align:middle;">
                    <option value="include">Include</option>
                    <option value="exclude">Exclude</option>
                </select>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-cat-sitemap-add-btn" disabled>+ Add</button>
                <div id="hpr-cat-sitemap-suggestions" class="hpr-autocomplete-dropdown"></div>
            </div>

            <table class="hpr-table" id="hpr-cat-sitemap-table" style="margin-bottom:20px;<?php echo empty($cat_sitemap) ? 'display:none;' : ''; ?>">
                <thead><tr><th>Category</th><th>Status</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    <?php foreach ( $cat_sitemap as $cs ) : ?>
                    <tr data-id="<?php echo esc_attr($cs['id']); ?>" data-slug="<?php echo esc_attr($cs['slug']); ?>" data-status="<?php echo esc_attr($cs['status']); ?>">
                        <td><?php echo esc_html($cs['name']); ?> <code style="font-size:11px;color:#888;"><?php echo esc_html($cs['slug']); ?></code></td>
                        <td><span class="<?php echo $cs['status'] === 'exclude' ? 'status-bad' : 'status-ok'; ?>"><?php echo $cs['status'] === 'exclude' ? 'Exclude' : 'Include'; ?></span></td>
                        <td><button type="button" class="hpr-btn hpr-btn-danger hpr-cat-sitemap-remove" style="padding:4px 10px;font-size:11px;">✗</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr style="margin:28px 0;">

            <!-- ── RankMath Status ── -->
            <h4>RankMath Sitemap Status</h4>
            <?php if ( $rm_info['active'] ) : ?>
                <div class="hpr-info-box <?php echo $rm_info['module_active'] ? 'success' : 'warning'; ?>">
                    <strong>RankMath:</strong> <span class="status-ok">✓ Detected</span><br>
                    <strong>Sitemap Module:</strong>
                    <?php if ( $rm_info['module_active'] ) : ?>
                        <span class="status-ok">✓ Active</span>
                    <?php else : ?>
                        <span class="status-warn">⚠ Inactive</span>
                    <?php endif; ?>
                    <br>
                    <strong>Press-Release in Sitemap:</strong>
                    <?php
                    if ( $rm_info['cpt_in_sitemap'] === 'yes' ) {
                        echo '<span class="status-ok">✓ Included</span>';
                    } elseif ( $rm_info['cpt_in_sitemap'] === 'no' ) {
                        echo '<span class="status-bad">✗ Excluded</span>';
                    } else {
                        echo '<span class="status-warn">⚠ Not configured</span>';
                    }
                    ?>
                    <?php if ( $rm_info['module_active'] ) : ?>
                    <br><strong>Sitemap URL:</strong>
                    <a href="<?php echo esc_url( $rm_info['sitemap_url'] ); ?>" target="_blank" style="word-break:break-all;"><?php echo esc_url( $rm_info['sitemap_url'] ); ?></a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="hpr-info-box warning">
                    <strong>RankMath:</strong> <span class="status-warn">⚠ Not detected</span> — Install and activate RankMath for sitemap integration.
                </div>
            <?php endif; ?>

            <hr style="margin:28px 0;">

            <!-- ── Action Buttons ── -->
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="button" class="hpr-btn hpr-btn-primary" id="hpr-save-seo-settings">💾 Save SEO Settings</button>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-flush-permalinks-sitemap">🔄 Flush Permalinks &amp; Purge Sitemap Cache</button>
                <span id="hpr-seo-save-status" style="margin-left:8px;"></span>
            </div>

        </div><!-- .hpr-panel-body -->
    </div><!-- .hpr-panel -->

    <style>
        /* Autocomplete dropdown */
        .hpr-autocomplete-dropdown {
            position: relative;
        }
        .hpr-autocomplete-dropdown .hpr-ac-list {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1000;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            display: none;
        }
        .hpr-ac-list .hpr-ac-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f1;
        }
        .hpr-ac-list .hpr-ac-item:hover {
            background: #f0f6fc;
        }
        .hpr-ac-list .hpr-ac-item code {
            font-size: 11px;
            color: #888;
            margin-left: 4px;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {

        /* ═══ Autocomplete helper factory ═══ */
        function initAutocomplete(inputId, suggestionsId, addBtnId, tableId, removeBtnClass, statusSelectId, statusLabels) {
            var $input      = $('#' + inputId);
            var $suggestions= $('#' + suggestionsId);
            var $addBtn     = $('#' + addBtnId);
            var $table      = $('#' + tableId);
            var $statusSel  = $('#' + statusSelectId);
            var selectedCat = null;
            var debounce    = null;

            // Ensure dropdown container
            if (!$suggestions.find('.hpr-ac-list').length) {
                $suggestions.append('<div class="hpr-ac-list"></div>');
            }
            var $list = $suggestions.find('.hpr-ac-list');

            $input.on('input', function() {
                var q = $(this).val();
                clearTimeout(debounce);
                selectedCat = null;
                $addBtn.prop('disabled', true);
                if (q.length < 2) { $list.hide(); return; }
                debounce = setTimeout(function() {
                    $.get(ajaxurl, { action: 'hpr_search_categories', q: q }, function(resp) {
                        if (!resp.success) return;
                        $list.empty();
                        if (resp.data.length === 0) {
                            $list.append('<div class="hpr-ac-item" style="color:#888;">No categories found</div>');
                        } else {
                            resp.data.forEach(function(cat) {
                                $list.append('<div class="hpr-ac-item" data-id="'+cat.id+'" data-slug="'+cat.slug+'" data-name="'+cat.name+'">'+cat.name+' <code>'+cat.slug+'</code></div>');
                            });
                        }
                        $list.show();
                    });
                }, 250);
            });

            // Select from dropdown
            $suggestions.on('click', '.hpr-ac-item[data-id]', function() {
                selectedCat = {
                    id:   $(this).data('id'),
                    slug: $(this).data('slug'),
                    name: $(this).data('name')
                };
                $input.val(selectedCat.name);
                $list.hide();
                $addBtn.prop('disabled', false);
            });

            // Close on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#' + suggestionsId + ', #' + inputId).length) {
                    $list.hide();
                }
            });

            // Add row
            $addBtn.on('click', function() {
                if (!selectedCat) return;
                var status = $statusSel.val();
                // Prevent duplicates
                if ($table.find('tr[data-id="'+selectedCat.id+'"]').length) {
                    // Update status instead
                    var $row = $table.find('tr[data-id="'+selectedCat.id+'"]');
                    $row.attr('data-status', status);
                    $row.find('td:eq(1) span').attr('class', statusLabels[status].cls).text(statusLabels[status].label);
                } else {
                    var $tr = $('<tr data-id="'+selectedCat.id+'" data-slug="'+selectedCat.slug+'" data-status="'+status+'">' +
                        '<td>'+selectedCat.name+' <code style="font-size:11px;color:#888;">'+selectedCat.slug+'</code></td>' +
                        '<td><span class="'+statusLabels[status].cls+'">'+statusLabels[status].label+'</span></td>' +
                        '<td><button type="button" class="hpr-btn hpr-btn-danger '+removeBtnClass+'" style="padding:4px 10px;font-size:11px;">✗</button></td>' +
                    '</tr>');
                    $table.find('tbody').append($tr);
                }
                $table.show();
                $input.val('');
                selectedCat = null;
                $addBtn.prop('disabled', true);
            });

            // Remove row
            $table.on('click', '.' + removeBtnClass, function() {
                $(this).closest('tr').remove();
                if ($table.find('tbody tr').length === 0) $table.hide();
            });
        }

        /* ─── Init Follow autocomplete ─── */
        initAutocomplete(
            'hpr-cat-follow-input', 'hpr-cat-follow-suggestions', 'hpr-cat-follow-add-btn',
            'hpr-cat-follow-table', 'hpr-cat-follow-remove', 'hpr-cat-follow-status-select',
            {
                dofollow: { cls: 'status-ok', label: 'Do Follow' },
                nofollow: { cls: 'status-bad', label: 'No Follow' }
            }
        );

        /* ─── Init Sitemap autocomplete ─── */
        initAutocomplete(
            'hpr-cat-sitemap-input', 'hpr-cat-sitemap-suggestions', 'hpr-cat-sitemap-add-btn',
            'hpr-cat-sitemap-table', 'hpr-cat-sitemap-remove', 'hpr-cat-sitemap-status-select',
            {
                include: { cls: 'status-ok', label: 'Include' },
                exclude: { cls: 'status-bad', label: 'Exclude' }
            }
        );

        /* ─── Collect table data helper ─── */
        function collectTableData(tableId) {
            var data = [];
            $('#' + tableId + ' tbody tr').each(function() {
                data.push({
                    id:     $(this).data('id'),
                    slug:   $(this).data('slug'),
                    name:   $(this).find('td:first').text().trim().replace(/\s+/g, ' '),
                    status: $(this).data('status')
                });
            });
            return data;
        }

        /* ─── Save SEO Settings ─── */
        $('#hpr-save-seo-settings').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-seo-save-status');
            $btn.prop('disabled', true);
            $status.text('Saving…').css('color', '#666');

            $.post(ajaxurl, {
                action: 'hpr_save_seo_settings',
                hpr_follow_status:          $('input[name="hpr_follow_status"]:checked').val(),
                hpr_cat_follow_overrides:   JSON.stringify(collectTableData('hpr-cat-follow-table')),
                hpr_sitemap_status:         $('input[name="hpr_sitemap_status"]:checked').val(),
                hpr_cat_sitemap_overrides:  JSON.stringify(collectTableData('hpr-cat-sitemap-table')),
                nonce: hprNonce
            }, function(resp) {
                if (resp.success) {
                    $status.text('✓ ' + resp.data.message).css('color', '#00a32a');
                } else {
                    $status.text('✗ ' + (resp.data || 'Error')).css('color', '#d63638');
                }
                $btn.prop('disabled', false);
                setTimeout(function(){ $status.text(''); }, 4000);
            });
        });

        /* ─── Flush Permalinks & Sitemap ─── */
        $('#hpr-flush-permalinks-sitemap').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-seo-save-status');
            $btn.prop('disabled', true);
            $status.text('Flushing…').css('color', '#666');

            $.post(ajaxurl, {
                action: 'hpr_flush_permalinks_sitemap',
                nonce: hprNonce
            }, function(resp) {
                if (resp.success) {
                    $status.text('✓ ' + resp.data.message).css('color', '#00a32a');
                } else {
                    $status.text('✗ ' + (resp.data || 'Error')).css('color', '#d63638');
                }
                $btn.prop('disabled', false);
                setTimeout(function(){ $status.text(''); }, 5000);
            });
        });

    });
    </script>
    <?php
}
