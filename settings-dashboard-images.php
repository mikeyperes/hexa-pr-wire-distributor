<?php

namespace hpr_distributor;

use hpr_distributor\Admin\DashboardData;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Migration\LegacyDependencyRetirement;

defined( 'ABSPATH' ) || exit;

function display_settings_images(): void {
    $settings = NativeFeedSettings::get();
    $inventory = DashboardData::image_inventory( 30 );
    $totals = $inventory['totals'];
    $legacy = LegacyDependencyRetirement::state();
    $going_live_url = add_query_arg( 'tab', 'going-live', menu_page_url( Config::$settings_page_slug, false ) );
    ?>
    <div id="hpr-images">
        <div class="hpr-page-head">
            <div><h2>Images from URL</h2><p>The Distributor renders featured images remotely. Files remain on Hexa PR Wire and are never sideloaded into the receiving publication.</p></div>
            <?php echo hpr_status_pill( 0 === (int) $totals['other_remote'] ? 'Host policy clear' : 'Review hosts', 0 === (int) $totals['other_remote'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <div class="hpr-metric-grid">
            <div class="hpr-metric"><strong><?php echo (int) $totals['allowed_remote']; ?></strong><span><?php echo esc_html( $settings['allowed_host'] ); ?></span></div>
            <div class="hpr-metric"><strong><?php echo (int) $totals['other_remote']; ?></strong><span>Other remote hosts</span></div>
            <div class="hpr-metric"><strong><?php echo (int) $totals['local']; ?></strong><span>Local records</span></div>
            <div class="hpr-metric"><strong><?php echo (int) $totals['no_image']; ?></strong><span>No image</span></div>
        </div>

        <?php if ( $legacy['fifu_active'] || [] !== $legacy['fifu_scheduled_hooks'] ) : ?>
            <div class="hpr-notice danger"><strong>FIFU can compete with Distributor image rendering.</strong> It is never disabled automatically. <a href="<?php echo esc_url( $going_live_url ); ?>">Review the explicit FIFU action</a>.</div>
        <?php else : ?>
            <div class="hpr-notice success"><strong>FIFU dependency:</strong> none. Distributor remote-image rendering owns this workflow.</div>
        <?php endif; ?>

        <div class="hpc-grid two">
            <section class="hpc-card hpr-section">
                <h3>Remote URL Test</h3>
                <p>Checks HTTPS, the required host, reachability, MIME type and real image dimensions.</p>
                <label class="hpc-field"><span>Image URL</span><input id="hpr-image-test-url" type="url" placeholder="https://hexaprwire.com/wp-content/uploads/example.jpg"></label>
                <div class="hpr-button-row"><button type="button" class="hpc-button" id="hpr-image-test">Test Image</button><span class="spinner"></span></div>
                <div id="hpr-image-test-result" class="hpr-result"></div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Post Rendering Repair</h3>
                <p>Preview or rebuild the Distributor-owned attachment shell and featured-image metadata for one existing Press Release. The source file is not downloaded.</p>
                <label class="hpc-field"><span>Press Release post ID</span><input id="hpr-image-repair-post" type="number" min="1" placeholder="123"></label>
                <div class="hpr-button-row"><button type="button" class="hpc-button secondary" data-hpr-image-repair="preview">Preview</button><button type="button" class="hpc-button" data-hpr-image-repair="run">Repair</button><span class="spinner"></span></div>
                <div id="hpr-image-repair-result" class="hpr-result"></div>
            </section>
        </div>

        <div class="hpc-grid two">
            <section class="hpc-card hpr-section">
                <h3>Host Inventory</h3>
                <table class="hpr-table"><thead><tr><th>Host</th><th>Records</th></tr></thead><tbody>
                    <?php if ( [] === $inventory['by_host'] ) : ?><tr><td colspan="2">No image URLs found.</td></tr><?php else : foreach ( $inventory['by_host'] as $host => $count ) : ?><tr><td><code><?php echo esc_html( $host ); ?></code></td><td><?php echo (int) $count; ?></td></tr><?php endforeach; endif; ?>
                </tbody></table>
            </section>
            <section class="hpc-card hpr-section">
                <h3>Image Policy</h3>
                <table class="hpr-table"><tbody>
                    <tr><th>Required host</th><td><code><?php echo esc_html( $settings['allowed_host'] ); ?></code></td></tr>
                    <tr><th>HTTPS</th><td>Required</td></tr>
                    <tr><th>Local sideload</th><td>Disabled</td></tr>
                    <tr><th>FIFU required</th><td>No</td></tr>
                    <tr><th>Attachment shell</th><td>WordPress metadata only; URL stays remote</td></tr>
                </tbody></table>
            </section>
        </div>

        <section class="hpc-card hpr-section">
            <h3>Article Image Records</h3>
            <div class="hpr-table-wrap"><table class="hpr-table"><thead><tr><th>Post</th><th>Type</th><th>Host</th><th>Image URL</th></tr></thead><tbody>
                <?php if ( [] === $inventory['rows'] ) : ?><tr><td colspan="4">No Press Release records found.</td></tr><?php else : foreach ( $inventory['rows'] as $row ) : ?>
                    <tr><td><a href="<?php echo esc_url( $row['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['title'] ); ?></a><br><code>#<?php echo (int) $row['post_id']; ?></code></td><td><?php echo esc_html( ucfirst( $row['image']['type'] ) ); ?></td><td><code><?php echo esc_html( $row['image']['host'] ); ?></code></td><td class="hpr-url"><?php if ( $row['image']['url'] ) : ?><a href="<?php echo esc_url( $row['image']['url'] ); ?>" target="_blank" rel="noopener noreferrer">View image</a><?php else : ?>—<?php endif; ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
    <script>
    (function($){ var root=$('#hpr-images'); if(!root.length||root.data('ready'))return; root.data('ready',1);
        function request(data,$button,$result){var $spinner=$button.closest('.hpr-button-row').find('.spinner');$button.prop('disabled',true);$spinner.addClass('is-active');data.nonce=window.hprNonce;$.post(ajaxurl,data).done(function(r){$result.toggleClass('is-success',!!r.success).toggleClass('is-error',!r.success).text(JSON.stringify(r.data||r,null,2));}).fail(function(xhr){var r=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}};$result.removeClass('is-success').addClass('is-error').text(JSON.stringify(r.data||r,null,2));}).always(function(){$button.prop('disabled',false);$spinner.removeClass('is-active');});}
        root.on('click','#hpr-image-test',function(){request({action:'hpr_test_remote_image',image_url:$('#hpr-image-test-url').val()},$(this),$('#hpr-image-test-result'));});
        root.on('click','[data-hpr-image-repair]',function(){var $b=$(this);request({action:'hpr_repair_remote_image',post_id:$('#hpr-image-repair-post').val(),dry_run:$b.data('hpr-image-repair')==='preview'?'1':'0'},$b,$('#hpr-image-repair-result'));});
    })(jQuery);
    </script>
    <?php
}
