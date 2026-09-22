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
    guard_ajax_request( "manage_options" );

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
    guard_ajax_request( "manage_options" );

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
    guard_ajax_request( "manage_options" );

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

    <section class="hpc-card hpr-section" id="hpr-seo-settings">
        <h3>SEO &amp; Sitemap</h3>
        <p class="hpr-section-intro">Primary settings are shown first. Category overrides and RankMath details remain visible as clear rows without side-by-side tables.</p>
        <div class="hpr-stack">
            <section class="hpr-section">
                <h4>Anchor Follow Status</h4>
                <p class="hpr-section-intro">Controls <code>rel="nofollow"</code> on links inside Press Release content. Post override wins first, then category override, then this global setting.</p>
                <div class="hpr-settings-list">
                    <div class="hpr-setting-row"><label class="hpr-check"><input type="radio" name="hpr_follow_status" value="dofollow" <?php checked( $follow_status, 'dofollow' ); ?>><span><strong>Do Follow</strong><small>Links pass authority. This is the default.</small></span></label></div>
                    <div class="hpr-setting-row"><label class="hpr-check"><input type="radio" name="hpr_follow_status" value="nofollow" <?php checked( $follow_status, 'nofollow' ); ?>><span><strong>No Follow</strong><small>Links do not pass authority.</small></span></label></div>
                    <div class="hpr-setting-row"><label class="hpr-check"><input type="radio" name="hpr_follow_status" value="default" <?php checked( $follow_status, 'default' ); ?>><span><strong>Use WordPress defaults</strong><small>Distributor does not modify link follow attributes.</small></span></label></div>
                </div>

                <h4>Category Follow Overrides</h4>
                <p class="hpr-section-intro">Add only categories that must differ from the global setting.</p>
                <div id="hpr-cat-follow-search" class="hpr-settings-list" style="margin-bottom:10px">
                    <div class="hpr-setting-row"><label class="hpc-field"><span>Category</span><input type="text" id="hpr-cat-follow-input" placeholder="Start typing a category name…" autocomplete="off"><div id="hpr-cat-follow-suggestions" class="hpr-autocomplete-dropdown"></div></label></div>
                    <div class="hpr-setting-row"><label class="hpc-field"><span>Follow status</span><select id="hpr-cat-follow-status-select"><option value="dofollow">Do Follow</option><option value="nofollow">No Follow</option></select></label><div class="hpr-button-row"><?php echo hpr_action_button( 'Add Category Override', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'id' => 'hpr-cat-follow-add-btn', 'disabled' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
                </div>
                <div class="hpr-record-list" id="hpr-cat-follow-list"<?php echo empty( $cat_follow ) ? ' hidden' : ''; ?>>
                    <?php foreach ( $cat_follow as $cf ) : ?>
                        <article class="hpr-record hpr-seo-override" data-id="<?php echo esc_attr( (string) $cf['id'] ); ?>" data-slug="<?php echo esc_attr( (string) $cf['slug'] ); ?>" data-name="<?php echo esc_attr( (string) $cf['name'] ); ?>" data-status="<?php echo esc_attr( (string) $cf['status'] ); ?>"><div class="hpr-record-head"><div><h4 class="hpr-record-title hpr-override-name"><?php echo esc_html( (string) $cf['name'] ); ?></h4><div class="hpr-record-summary"><code><?php echo esc_html( (string) $cf['slug'] ); ?></code> · <span class="hpr-override-status <?php echo 'nofollow' === $cf['status'] ? 'status-bad' : 'status-ok'; ?>"><?php echo 'nofollow' === $cf['status'] ? 'No Follow' : 'Do Follow'; ?></span></div></div><div class="hpr-record-actions"><button type="button" class="hpc-button danger hpr-cat-follow-remove">Remove</button></div></div></article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hpr-section">
                <h4>Sitemap Inclusion</h4>
                <p class="hpr-section-intro">Controls whether Press Releases appear in the XML sitemap. This does not affect RSS imports.</p>
                <div class="hpr-settings-list">
                    <div class="hpr-setting-row"><label class="hpr-check"><input type="radio" name="hpr_sitemap_status" value="include" <?php checked( $sitemap_status, 'include' ); ?>><span><strong>Include in Sitemap</strong><small>This is the default.</small></span></label></div>
                    <div class="hpr-setting-row"><label class="hpr-check"><input type="radio" name="hpr_sitemap_status" value="exclude" <?php checked( $sitemap_status, 'exclude' ); ?>><span><strong>Exclude from Sitemap</strong><small>Press Releases are omitted unless a post or category override includes them.</small></span></label></div>
                </div>

                <h4>Category Sitemap Overrides</h4>
                <p class="hpr-section-intro">Add only categories that must differ from the global sitemap setting.</p>
                <div id="hpr-cat-sitemap-search" class="hpr-settings-list" style="margin-bottom:10px">
                    <div class="hpr-setting-row"><label class="hpc-field"><span>Category</span><input type="text" id="hpr-cat-sitemap-input" placeholder="Start typing a category name…" autocomplete="off"><div id="hpr-cat-sitemap-suggestions" class="hpr-autocomplete-dropdown"></div></label></div>
                    <div class="hpr-setting-row"><label class="hpc-field"><span>Sitemap status</span><select id="hpr-cat-sitemap-status-select"><option value="include">Include</option><option value="exclude">Exclude</option></select></label><div class="hpr-button-row"><?php echo hpr_action_button( 'Add Category Override', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'id' => 'hpr-cat-sitemap-add-btn', 'disabled' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
                </div>
                <div class="hpr-record-list" id="hpr-cat-sitemap-list"<?php echo empty( $cat_sitemap ) ? ' hidden' : ''; ?>>
                    <?php foreach ( $cat_sitemap as $cs ) : ?>
                        <article class="hpr-record hpr-seo-override" data-id="<?php echo esc_attr( (string) $cs['id'] ); ?>" data-slug="<?php echo esc_attr( (string) $cs['slug'] ); ?>" data-name="<?php echo esc_attr( (string) $cs['name'] ); ?>" data-status="<?php echo esc_attr( (string) $cs['status'] ); ?>"><div class="hpr-record-head"><div><h4 class="hpr-record-title hpr-override-name"><?php echo esc_html( (string) $cs['name'] ); ?></h4><div class="hpr-record-summary"><code><?php echo esc_html( (string) $cs['slug'] ); ?></code> · <span class="hpr-override-status <?php echo 'exclude' === $cs['status'] ? 'status-bad' : 'status-ok'; ?>"><?php echo 'exclude' === $cs['status'] ? 'Exclude' : 'Include'; ?></span></div></div><div class="hpr-record-actions"><button type="button" class="hpc-button danger hpr-cat-sitemap-remove">Remove</button></div></div></article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hpr-section">
                <h4>RankMath Sitemap Status</h4>
                <div class="hpr-data-list">
                    <?php
                    echo hpr_data_row( 'RankMath', $rm_info['active'] ? '<span class="status-ok">Detected</span>' : '<span class="status-warn">Not detected</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'Sitemap module', $rm_info['module_active'] ? '<span class="status-ok">Active</span>' : '<span class="status-warn">Inactive</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    $sitemap_value = 'yes' === $rm_info['cpt_in_sitemap'] ? '<span class="status-ok">Included</span>' : ( 'no' === $rm_info['cpt_in_sitemap'] ? '<span class="status-bad">Excluded</span>' : '<span class="status-warn">Not configured</span>' );
                    echo hpr_data_row( 'Press Releases', $sitemap_value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    if ( ! empty( $rm_info['module_active'] ) ) {
                        echo hpr_data_row( 'Sitemap URL', '<a href="' . esc_url( (string) $rm_info['sitemap_url'] ) . '" target="_blank" rel="noopener noreferrer">Open Press Release sitemap</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>
                </div>
            </section>

            <div class="hpr-button-row">
                <?php echo hpr_action_button( 'Save SEO Settings', [ 'working_label' => 'Saving...', 'success_label' => 'Saved', 'error_label' => 'Save failed', 'attrs' => [ 'id' => 'hpr-save-seo-settings' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo hpr_action_button( 'Flush Permalinks & Purge Sitemap Cache', [ 'class' => 'hpc-button secondary', 'working_label' => 'Flushing...', 'success_label' => 'Flushed', 'error_label' => 'Flush failed', 'attrs' => [ 'id' => 'hpr-flush-permalinks-sitemap' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php echo hpr_secondary_result( 'hpr-seo-result', 'Save and cache details' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>

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
        function makeOverrideRecord(category,status,removeBtnClass,statusLabels) {
            var $record=$('<article class="hpr-record hpr-seo-override"><div class="hpr-record-head"><div><h4 class="hpr-record-title hpr-override-name"></h4><div class="hpr-record-summary"><code></code> · <span class="hpr-override-status"></span></div></div><div class="hpr-record-actions"><button type="button" class="hpc-button danger">Remove</button></div></div></article>');
            $record.attr({'data-id':category.id,'data-slug':category.slug,'data-name':category.name,'data-status':status});
            $record.find('.hpr-override-name').text(category.name);
            $record.find('code').text(category.slug);
            $record.find('.hpr-override-status').attr('class','hpr-override-status '+statusLabels[status].cls).text(statusLabels[status].label);
            $record.find('button').addClass(removeBtnClass);
            return $record;
        }

        function initAutocomplete(inputId, suggestionsId, addBtnId, listId, removeBtnClass, statusSelectId, statusLabels) {
            var $input      = $('#' + inputId);
            var $suggestions= $('#' + suggestionsId);
            var $addBtn     = $('#' + addBtnId);
            var $rows       = $('#' + listId);
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
                    $.get(ajaxurl, { action: 'hpr_search_categories', q: q, nonce: hprNonce }, function(resp) {
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
                var $row=$rows.find('.hpr-record[data-id="'+selectedCat.id+'"]');
                if ($row.length) {
                    $row.attr('data-status', status);
                    $row.find('.hpr-override-status').attr('class','hpr-override-status '+statusLabels[status].cls).text(statusLabels[status].label);
                } else {
                    $rows.append(makeOverrideRecord(selectedCat,status,removeBtnClass,statusLabels));
                }
                $rows.prop('hidden',false);
                $input.val('');
                selectedCat = null;
                $addBtn.prop('disabled', true);
            });

            // Remove row
            $rows.on('click', '.' + removeBtnClass, function() {
                $(this).closest('.hpr-record').remove();
                if (!$rows.find('.hpr-record').length) $rows.prop('hidden',true);
            });
        }

        /* ─── Init Follow autocomplete ─── */
        initAutocomplete(
            'hpr-cat-follow-input', 'hpr-cat-follow-suggestions', 'hpr-cat-follow-add-btn',
            'hpr-cat-follow-list', 'hpr-cat-follow-remove', 'hpr-cat-follow-status-select',
            {
                dofollow: { cls: 'status-ok', label: 'Do Follow' },
                nofollow: { cls: 'status-bad', label: 'No Follow' }
            }
        );

        /* ─── Init Sitemap autocomplete ─── */
        initAutocomplete(
            'hpr-cat-sitemap-input', 'hpr-cat-sitemap-suggestions', 'hpr-cat-sitemap-add-btn',
            'hpr-cat-sitemap-list', 'hpr-cat-sitemap-remove', 'hpr-cat-sitemap-status-select',
            {
                include: { cls: 'status-ok', label: 'Include' },
                exclude: { cls: 'status-bad', label: 'Exclude' }
            }
        );

        function collectOverrideData(listId) {
            var data = [];
            $('#' + listId + ' .hpr-record[data-id]').each(function() {
                data.push({
                    id:     $(this).attr('data-id'),
                    slug:   $(this).attr('data-slug'),
                    name:   $(this).attr('data-name'),
                    status: $(this).attr('data-status')
                });
            });
            return data;
        }

        function payload(response){return response&&response.data!==undefined?response.data:response}
        function notify(tone,title,message){if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-general-notice',{tone:tone,title:title,message:message});}
        function start(button){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);else $(button).prop('disabled',true)}
        function finish(button,ok,label){if(window.HexaWpCoreDynamicButton){if(ok)window.HexaWpCoreDynamicButton.success(button,label);else window.HexaWpCoreDynamicButton.error(button,'Failed');}else $(button).prop('disabled',false)}
        function report(response,ok){var data=payload(response);$('#hpr-seo-result').toggleClass('is-success',ok).toggleClass('is-error',!ok).text(JSON.stringify(data||response,null,2));if(!ok)$('#hpr-seo-result').closest('details').prop('open',true);return data}

        /* ─── Save SEO Settings ─── */
        $('#hpr-save-seo-settings').on('click', function() {
            var button=this;start(button);
            $.post(ajaxurl, {
                action: 'hpr_save_seo_settings',
                hpr_follow_status:          $('input[name="hpr_follow_status"]:checked').val(),
                hpr_cat_follow_overrides:   JSON.stringify(collectOverrideData('hpr-cat-follow-list')),
                hpr_sitemap_status:         $('input[name="hpr_sitemap_status"]:checked').val(),
                hpr_cat_sitemap_overrides:  JSON.stringify(collectOverrideData('hpr-cat-sitemap-list')),
                nonce: hprNonce
            }).done(function(resp){var data=report(resp,!!resp.success);finish(button,!!resp.success,'Saved');notify(resp.success?'success':'error',resp.success?'SEO settings saved':'SEO save failed',data&&data.message?data.message:'The request failed.');}).fail(function(xhr){var response=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},data=report(response,false);finish(button,false);notify('error','SEO save failed',data&&data.message?data.message:'Request failed.');});
        });

        /* ─── Flush Permalinks & Sitemap ─── */
        $('#hpr-flush-permalinks-sitemap').on('click', function() {
            var button=this;start(button);
            $.post(ajaxurl, {
                action: 'hpr_flush_permalinks_sitemap',
                nonce: hprNonce
            }).done(function(resp){var data=report(resp,!!resp.success);finish(button,!!resp.success,'Flushed');notify(resp.success?'success':'error',resp.success?'Permalinks and sitemap refreshed':'Refresh failed',data&&data.message?data.message:'The request failed.');}).fail(function(xhr){var response=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},data=report(response,false);finish(button,false);notify('error','Refresh failed',data&&data.message?data.message:'Request failed.');});
        });

    });
    </script>
    <?php
}
