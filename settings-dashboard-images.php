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
    ?>
    <div id="hpr-images">
        <div class="hpr-page-head">
            <div><h2>Images from URL</h2><p>Featured images remain on Hexa PR Wire. Distributor stores only a WordPress attachment shell and renders the external JPEG, PNG, WebP, GIF, or AVIF.</p></div>
            <?php echo hpr_status_pill( 0 === (int) $totals['other_remote'] ? 'Host policy clear' : 'Review image hosts', 0 === (int) $totals['other_remote'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php echo hpr_dynamic_notice( 'hpr-images-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div class="hpr-stack">
            <section class="hpc-card hpr-section">
                <h3>Image Status</h3>
                <div class="hpr-data-list">
                    <?php
                    echo hpr_data_row( 'Hexa-hosted remote images', esc_html( (string) $totals['allowed_remote'] ), (string) $settings['allowed_host'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'Other remote hosts', esc_html( (string) $totals['other_remote'] ), 'Review these records because the normal source host is hexaprwire.com.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'Local image records', esc_html( (string) $totals['local'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'Posts with no image', esc_html( (string) $totals['no_image'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            </section>

            <section class="hpc-card hpr-section" id="hpr-fifu-control">
                <h3>FIFU Conflict</h3>
                <?php if ( $legacy['fifu_active'] || [] !== $legacy['fifu_scheduled_hooks'] ) : ?>
                    <div class="hpr-notice warning"><strong>Remote image imports are paused:</strong> FIFU is active or still has scheduled background work and can overwrite Distributor-owned remote images.</div>
                    <div class="hpr-record-list">
                        <?php echo hpr_record_row( 'Featured Image from URL (FIFU)', '<span class="hpr-legacy-state">Active competing image handler.</span>', $legacy['fifu_active'] ? hpr_action_button( 'Disable FIFU', [ 'class' => 'hpc-button danger', 'working_label' => 'Disabling...', 'success_label' => 'Disabled', 'error_label' => 'Failed', 'attrs' => [ 'data-hpr-disable-fifu' => LegacyDependencyRetirement::ACTION_DISABLE_FIFU_PLUGIN ] ] ) : '', '<div class="hpr-data-list">' . hpr_data_row( 'Scheduled hooks', [] === $legacy['fifu_scheduled_hooks'] ? 'None' : '<code>' . esc_html( implode( ', ', (array) $legacy['fifu_scheduled_hooks'] ) ) . '</code>' ) . hpr_data_row( 'Automatic shutdown', 'No' ) . '</div>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php else : ?>
                    <div class="hpr-notice success"><strong>No FIFU conflict.</strong> Distributor owns remote featured-image rendering.</div>
                <?php endif; ?>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Test and Repair</h3>
                <p class="hpr-section-intro">These are separate steps. Test a URL first; repair one post only when its saved image binding needs reconstruction.</p>
                <div class="hpr-step-list">
                    <?php ob_start(); ?>
                    <label class="hpc-field"><span>Image URL</span><input id="hpr-image-test-url" type="url" placeholder="https://hexaprwire.com/wp-content/uploads/example.jpg"></label>
                    <div class="hpr-button-row"><?php echo hpr_action_button( 'Test Image URL', [ 'working_label' => 'Testing...', 'success_label' => 'Valid image', 'error_label' => 'Test failed', 'attrs' => [ 'id' => 'hpr-image-test' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php echo hpr_secondary_result( 'hpr-image-test-result', 'Image test details' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo hpr_step_row( 1, 'Test a remote URL', 'Checks HTTPS, the required host, reachability, MIME type and real dimensions.', (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                    <?php ob_start(); ?>
                    <label class="hpc-field"><span>Press Release post ID</span><input id="hpr-image-repair-post" type="number" min="1" placeholder="123"></label>
                    <div class="hpr-button-row"><?php echo hpr_action_button( 'Preview Repair', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-image-repair' => 'preview' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo hpr_action_button( 'Repair Image Binding', [ 'attrs' => [ 'data-hpr-image-repair' => 'run' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php echo hpr_secondary_result( 'hpr-image-repair-result', 'Repair details' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo hpr_step_row( 2, 'Repair one post binding', 'Rebuilds only the Distributor attachment shell and featured-image metadata. The source file is never downloaded.', (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Image Policy</h3>
                <div class="hpr-data-list">
                    <?php
                    echo hpr_data_row( 'Required host', '<code>' . esc_html( (string) $settings['allowed_host'] ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'HTTPS', 'Required' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'Local sideload', 'Disabled', 'The original image remains on Hexa PR Wire.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'FIFU required', 'No' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_data_row( 'Attachment shell', 'WordPress metadata only', 'The image URL stays external.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
                <details class="hpr-secondary"><summary>Host inventory</summary><div class="hpr-secondary-body"><div class="hpr-data-list">
                    <?php if ( [] === $inventory['by_host'] ) : ?><p class="hpr-empty">No image URLs found.</p><?php else : foreach ( $inventory['by_host'] as $host => $count ) : ?><?php echo hpr_data_row( (string) $host, esc_html( (string) $count ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; endif; ?>
                </div></div></details>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Article Image Records</h3>
                <p class="hpr-section-intro">Each row pairs the post with the actual external image. Open either link in a new tab.</p>
                <div class="hpr-record-list">
                    <?php if ( [] === $inventory['rows'] ) : ?>
                        <p class="hpr-empty">No Press Release records found.</p>
                    <?php else : foreach ( $inventory['rows'] as $row ) : ?>
                        <?php
                        $image = (array) $row['image'];
                        $url = (string) ( $image['url'] ?? '' );
                        $media = '';
                        if ( '' !== $url ) {
                            $media = '<div class="hpr-record-media"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"><img class="hpr-record-thumb" src="' . esc_url( $url ) . '" alt="" loading="lazy"></a><div class="hpr-record-meta"><strong>External image</strong><br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Open original image</a><br><code>' . esc_html( (string) ( $image['host'] ?? '' ) ) . '</code> · ' . esc_html( ucfirst( (string) ( $image['type'] ?? 'none' ) ) ) . '</div></div>';
                        }
                        $summary = '<a href="' . esc_url( (string) $row['view_url'] ) . '" target="_blank" rel="noopener noreferrer">Open post</a> <code>#' . (int) $row['post_id'] . '</code>' . ( '' !== $media ? $media : '<span class="hpr-data-description">No image URL is recorded.</span>' );
                        echo hpr_record_row( (string) $row['title'], $summary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    <?php endforeach; endif; ?>
                </div>
            </section>
        </div>
    </div>
    <script>
    (function($){var root=$('#hpr-images');if(!root.length||root.data('ready'))return;root.data('ready',1);
        function body(res){return res&&res.data!==undefined?res.data:res}
        function notice(tone,title,message){if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-images-notice',{tone:tone,title:title,message:message});}
        function start(button){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button)}
        function done(button,ok,label){if(!window.HexaWpCoreDynamicButton)return;if(ok)window.HexaWpCoreDynamicButton.success(button,label||'Done');else window.HexaWpCoreDynamicButton.error(button,'Failed')}
        function request(data,button,result,title){start(button);data.nonce=window.hprNonce;$.post(ajaxurl,data).done(function(res){var value=body(res),ok=!!res.success;$(result).toggleClass('is-success',ok).toggleClass('is-error',!ok).text(JSON.stringify(value||res,null,2));done(button,ok);notice(ok?'success':'error',ok?title+' completed':title+' failed',value&&value.message?value.message:(ok?'Action completed.':'Action failed.'));}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},value=body(res);$(result).removeClass('is-success').addClass('is-error').text(JSON.stringify(value||res,null,2)).closest('details').prop('open',true);done(button,false);notice('error',title+' failed',value&&value.message?value.message:'Request failed.');});}
        root.on('click','#hpr-image-test',function(){request({action:'hpr_test_remote_image',image_url:$('#hpr-image-test-url').val()},this,'#hpr-image-test-result','Image test');});
        root.on('click','[data-hpr-image-repair]',function(){var preview=$(this).data('hpr-image-repair')==='preview';request({action:'hpr_repair_remote_image',post_id:$('#hpr-image-repair-post').val(),dry_run:preview?'1':'0'},this,'#hpr-image-repair-result',preview?'Repair preview':'Image repair');});
        root.on('click','[data-hpr-disable-fifu]',function(){var button=this;start(button);$.post(ajaxurl,{action:'hpr_apply_legacy_action',nonce:window.hprNonce,legacy_action:$(button).data('hpr-disable-fifu')}).done(function(res){var value=body(res),ok=!!res.success;done(button,ok,'Disabled');if(ok){$('#hpr-fifu-control .hpr-notice').removeClass('warning').addClass('success').html('<strong>FIFU disabled.</strong> Distributor now owns remote featured-image rendering.');$(button).closest('.hpr-record').find('.hpr-legacy-state').text('Inactive.');$(button).remove();}notice(ok?'success':'error',ok?'FIFU disabled':'FIFU action failed',value&&value.message?value.message:'Action completed.');}).fail(function(xhr){var value=body(xhr.responseJSON);done(button,false);notice('error','FIFU action failed',value&&value.message?value.message:'Request failed.');});});
    })(jQuery);
    </script>
    <?php
}
