<?php

namespace hpr_distributor;

use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Migration\LegacyDependencyRetirement;

defined( 'ABSPATH' ) || exit;

function display_settings_import_sync(): void {
    $settings = NativeFeedSettings::get();
    $readiness = NativeFeedSettings::readiness();
    $history = NativeFeedImporter::history();
    $cursor = NativeFeedImporter::cursor( (string) $settings['feed_url'] );
    $legacy = LegacyDependencyRetirement::state();
    $going_live_url = add_query_arg( 'tab', 'going-live', menu_page_url( Config::$settings_page_slug, false ) );
    $users = get_users( [ 'fields' => [ 'ID', 'display_name', 'user_login' ], 'orderby' => 'display_name' ] );
    ?>
    <div id="hpr-import-sync">
        <div class="hpr-page-head">
            <div><h2>Import &amp; Sync</h2><p>Configure the native publication feed, test it safely, preview imports, run the next cursor batch, or pull one exact source article.</p></div>
            <?php echo hpr_status_pill( $readiness['ready'] ? 'Ready' : 'Blocked', $readiness['ready'] ? 'success' : 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <?php if ( ! $legacy['ready'] ) : ?>
            <div class="hpr-notice danger"><strong>Competing import/image work detected.</strong> <?php echo esc_html( implode( ' ', (array) $legacy['conflicts'] ) ); ?> <a href="<?php echo esc_url( $going_live_url ); ?>">Choose an explicit Going Live action</a>. Feed tests and dry runs remain available; live imports fail closed.</div>
        <?php endif; ?>

        <section class="hpc-card hpr-section">
            <h3>RSS Import Settings</h3>
            <form id="hpr-import-settings-form">
                <div class="hpr-form-grid">
                    <label class="hpc-field wide"><span>Hexa PR Wire feed URL</span><input type="url" name="feed_url" value="<?php echo esc_attr( $settings['feed_url'] ); ?>" required></label>
                    <label class="hpc-field"><span>Publication slug</span><input type="text" name="publication_slug" value="<?php echo esc_attr( $settings['publication_slug'] ); ?>" required></label>
                    <label class="hpc-field"><span>Author</span><select name="author_id"><option value="0">Hexa PR Wire user / current user fallback</option><?php foreach ( $users as $user ) : ?><option value="<?php echo (int) $user->ID; ?>" <?php selected( (int) $settings['author_id'], (int) $user->ID ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option><?php endforeach; ?></select></label>
                    <label class="hpc-field"><span>Post status</span><select name="post_status"><?php foreach ( [ 'publish' => 'Publish', 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private' ] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['post_status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label class="hpc-field"><span>Schedule interval</span><select name="interval"><?php foreach ( [ 'hourly' => 'Hourly', 'twicedaily' => 'Twice daily', 'daily' => 'Daily' ] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['interval'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label class="hpc-field"><span>Items per batch</span><input type="number" name="max_items" min="1" max="250" value="<?php echo (int) $settings['max_items']; ?>"></label>
                    <label class="hpc-field"><span>Run history records</span><input type="number" name="run_history_limit" min="5" max="50" value="<?php echo (int) $settings['run_history_limit']; ?>"></label>
                    <label class="hpc-field"><span>Item rows retained per run</span><input type="number" name="item_history_limit" min="10" max="100" value="<?php echo (int) $settings['item_history_limit']; ?>"></label>
                </div>
                <div class="hpc-grid two">
                    <label class="hpr-check"><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>><span><strong>Enable native importer</strong><small>Master switch for live import writes.</small></span></label>
                    <label class="hpr-check"><input type="checkbox" name="schedule_enabled" value="1" <?php checked( $settings['schedule_enabled'] ); ?>><span><strong>Enable scheduled polling</strong><small>Uses the interval selected above.</small></span></label>
                    <label class="hpr-check"><input type="checkbox" name="update_existing" value="1" <?php checked( $settings['update_existing'] ); ?>><span><strong>Update matching releases</strong><small>Turn off to preserve existing destination content.</small></span></label>
                    <label class="hpr-check"><input type="checkbox" name="cache_bust" value="1" <?php checked( $settings['cache_bust'] ); ?>><span><strong>Bypass feed caches</strong><small>Adds a request-specific cache key.</small></span></label>
                </div>
                <div class="hpr-button-row"><button class="hpc-button" type="submit">Save Import Settings</button><span class="spinner"></span></div>
            </form>
            <div id="hpr-import-settings-result" class="hpr-result"></div>
        </section>

        <div class="hpc-grid two">
            <section class="hpc-card hpr-section">
                <h3>Feed and Batch Testing</h3>
                <p>Test XML without importing, preview the current cursor batch, or run the next live batch.</p>
                <table class="hpr-table"><tbody>
                    <tr><th>Cursor offset</th><td><?php echo (int) $cursor['offset']; ?></td></tr>
                    <tr><th>Completed cycles</th><td><?php echo (int) $cursor['cycle']; ?></td></tr>
                    <tr><th>Cursor updated</th><td><?php echo esc_html( $cursor['updated_gmt'] ?: 'Never' ); ?></td></tr>
                    <tr><th>Next scheduled run</th><td><?php echo $readiness['scheduled_polling']['next_run'] ? esc_html( wp_date( 'Y-m-d H:i:s T', (int) $readiness['scheduled_polling']['next_run'] ) ) : 'Not scheduled'; ?></td></tr>
                </tbody></table>
                <div class="hpr-button-row">
                    <button type="button" class="hpc-button secondary" data-hpr-import-action="test-feed">Test Feed</button>
                    <button type="button" class="hpc-button secondary" data-hpr-import-action="dry-run">Dry Run</button>
                    <button type="button" class="hpc-button" data-hpr-import-action="run">Run Now</button>
                    <button type="button" class="hpc-button secondary" data-hpr-import-action="reset-cursor">Reset Cursor</button>
                    <span class="spinner"></span>
                </div>
                <div id="hpr-import-action-result" class="hpr-result"></div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Force Pull One Release</h3>
                <p>Enter one Hexa PR Wire source URL, numeric/post source ID, or source slug. Preview is read-only; Pull Now writes through the same deduplication rules as scheduled imports.</p>
                <label class="hpc-field"><span>Source URL, source ID, or slug</span><input id="hpr-force-pull-identifier" type="text" placeholder="https://hexaprwire.com/example-release/"></label>
                <div class="hpr-button-row">
                    <button type="button" class="hpc-button secondary" data-hpr-force-pull="preview">Preview</button>
                    <button type="button" class="hpc-button" data-hpr-force-pull="run">Pull Now</button>
                    <span class="spinner"></span>
                </div>
                <div id="hpr-force-pull-result" class="hpr-result"></div>
            </section>
        </div>

        <section class="hpc-card hpr-section">
            <h3>Source Slug Reconciliation</h3>
            <p>Compare destination slugs with canonical Hexa PR Wire source URLs. Preview first; conflicting destination slugs are never overwritten.</p>
            <div class="hpr-button-row"><button type="button" class="hpc-button secondary" data-hpr-slug-repair="preview">Preview Slug Repairs</button><button type="button" class="hpc-button" data-hpr-slug-repair="run">Repair Safe Slugs</button><span class="spinner"></span></div>
            <div id="hpr-slug-repair-result" class="hpr-result"></div>
        </section>

        <section class="hpc-card hpr-section">
            <h3>Run History</h3>
            <div class="hpr-table-wrap"><table class="hpr-table">
                <thead><tr><th>Finished</th><th>Trigger</th><th>Status</th><th>Discovered</th><th>Processed</th><th>Created</th><th>Updated</th><th>Failed</th><th>Cursor</th></tr></thead>
                <tbody>
                <?php if ( [] === $history ) : ?><tr><td colspan="9">No native runs recorded yet.</td></tr><?php else : foreach ( $history as $run ) : ?>
                    <?php $status = (string) ( $run['status'] ?? ( ! empty( $run['success'] ) ? 'success' : 'failed' ) ); ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $run['ended_gmt'] ?? 'Unknown' ) ); ?></td>
                        <td><code><?php echo esc_html( (string) ( $run['trigger'] ?? '' ) ); ?></code><?php echo ! empty( $run['dry_run'] ) ? ' (dry)' : ''; ?></td>
                        <td><?php echo hpr_status_pill( ucfirst( $status ), 'success' === $status ? 'success' : ( 'partial' === $status ? 'warning' : 'danger' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td><?php echo (int) ( $run['items_discovered'] ?? 0 ); ?></td>
                        <td><?php echo (int) ( $run['items_processed'] ?? 0 ); ?></td>
                        <td><?php echo (int) ( $run['counts']['created'] ?? 0 ); ?></td>
                        <td><?php echo (int) ( $run['counts']['updated'] ?? 0 ); ?></td>
                        <td><?php echo (int) ( $run['counts']['failed'] ?? 0 ); ?></td>
                        <td><?php echo isset( $run['cursor']['offset'] ) ? (int) $run['cursor']['offset'] . ' → ' . (int) $run['cursor']['next_offset'] : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
            <?php $latest_items = isset( $history[0]['items'] ) && is_array( $history[0]['items'] ) ? $history[0]['items'] : []; ?>
            <?php if ( [] !== $latest_items ) : ?>
                <h4>Latest run item results</h4>
                <div class="hpr-table-wrap"><table class="hpr-table"><thead><tr><th>Action</th><th>Source</th><th>Destination</th><th>Result</th></tr></thead><tbody>
                    <?php foreach ( $latest_items as $item ) : ?><tr><td><?php echo esc_html( (string) ( $item['action'] ?? '' ) ); ?></td><td><?php if ( ! empty( $item['source_url'] ) ) : ?><a href="<?php echo esc_url( $item['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $item['source_title'] ?? $item['source_id'] ?? 'Source' ) ); ?></a><?php else : ?><?php echo esc_html( (string) ( $item['source_title'] ?? '—' ) ); ?><?php endif; ?></td><td><?php if ( ! empty( $item['destination_url'] ) ) : ?><a href="<?php echo esc_url( $item['destination_url'] ); ?>" target="_blank" rel="noopener noreferrer">View</a> <code>#<?php echo (int) ( $item['destination_post_id'] ?? 0 ); ?></code><?php else : ?>—<?php endif; ?></td><td><?php echo esc_html( (string) ( $item['error'] ?? '' ) ?: 'OK' ); ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php if ( ! empty( $history[0]['items_truncated'] ) ) : ?><p class="hpr-muted"><?php echo (int) $history[0]['items_truncated']; ?> additional item rows were not retained.</p><?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
    <script>
    (function($){
        var root = $('#hpr-import-sync'); if (!root.length || root.data('ready')) return; root.data('ready', 1);
        function show($el, payload, ok){ var data = payload && payload.data !== undefined ? payload.data : payload; $el.toggleClass('is-success', !!ok).toggleClass('is-error', !ok).text(typeof data === 'string' ? data : JSON.stringify(data, null, 2)); }
        function request(data, $button, $result){ var $spinner = $button.closest('.hpr-button-row').find('.spinner'); $button.prop('disabled', true); $spinner.addClass('is-active'); data.nonce = window.hprNonce; return $.post(ajaxurl, data).done(function(r){ show($result, r, !!r.success); }).fail(function(xhr){ var r=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}}; show($result,r,false); }).always(function(){ $button.prop('disabled', false); $spinner.removeClass('is-active'); }); }
        root.on('submit', '#hpr-import-settings-form', function(e){ e.preventDefault(); var $form=$(this), data={action:'hpr_save_import_settings'}; $.each($form.serializeArray(),function(_,item){data[item.name]=item.value;}); $form.find('input[type=checkbox]').each(function(){data[this.name]=this.checked?'1':'0';}); request(data,$form.find('button[type=submit]'),$('#hpr-import-settings-result'));
        });
        root.on('click','[data-hpr-import-action]',function(){ var $b=$(this), op=$b.data('hpr-import-action'), data={}; if(op==='test-feed') data={action:'hpr_test_feed'}; else if(op==='reset-cursor') data={action:'hpr_reset_import_cursor'}; else data={action:'hpr_import_operation',operation:op}; request(data,$b,$('#hpr-import-action-result')); });
        root.on('click','[data-hpr-force-pull]',function(){ var $b=$(this), identifier=$('#hpr-force-pull-identifier').val(); request({action:'hpr_force_pull',identifier:identifier,dry_run:$b.data('hpr-force-pull')==='preview'?'1':'0'},$b,$('#hpr-force-pull-result')); });
        root.on('click','[data-hpr-slug-repair]',function(){var $b=$(this),preview=$b.data('hpr-slug-repair')==='preview';if(!preview&&!window.confirm('Repair every non-conflicting destination slug shown by the current source data?'))return;request({action:'hpr_repair_source_slugs',dry_run:preview?'1':'0'},$b,$('#hpr-slug-repair-result'));});
    })(jQuery);
    </script>
    <?php
}
