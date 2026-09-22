<?php

namespace hpr_distributor;

use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use hpr_distributor\Admin\DashboardData;
use hpr_distributor\ContentTypes\PressReleaseStructures;

defined( 'ABSPATH' ) || exit;

function display_settings_content_types(): void {
    $acf = DashboardData::acf_report();
    ?>
    <div id="hpr-content-model">
        <div class="hpr-page-head"><div><h2>Content Model &amp; ACF</h2><p>Press Release post-type registration, field groups, field inventory and stored-value testing.</p></div><?php echo hpr_status_pill( $acf['acf_active'] ? 'ACF active' : 'ACF missing', $acf['acf_active'] ? 'success' : 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php echo hpr_dynamic_notice( 'hpr-acf-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <?php if ( class_exists( ContentTypeRenderer::class ) ) : ?>
            <?php echo ( new ContentTypeRenderer() )->render( PressReleaseStructures::registry(), [ 'title' => 'Press Release Post Type', 'description' => 'The internal key remains press-release so existing URLs, imports and relationships are preserved.', 'persist_prefix' => 'hpr-content-model' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <div class="hpr-notice danger">The Hexa WP Core content-type component is unavailable.</div>
        <?php endif; ?>

        <div class="hpr-stack">
            <?php foreach ( $acf['groups'] as $group ) : ?>
                <section class="hpc-card hpr-section">
                    <div class="hpr-page-head"><div><h3><?php echo esc_html( $group['title'] ); ?></h3><p><code><?php echo esc_html( $group['key'] ); ?></code></p></div><?php echo hpr_status_pill( $group['registered'] && $group['active'] ? 'Registered' : 'Unavailable', $group['registered'] && $group['active'] ? 'success' : 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <div class="hpr-record-list">
                    <?php foreach ( $group['fields'] as $field ) : ?><?php echo hpr_record_row( (string) $field['label'], '<code>' . esc_html( (string) $field['name'] ) . '</code> · ' . esc_html( (string) $field['type'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <section class="hpc-card hpr-section">
            <h3>Test Stored ACF Values</h3>
            <p>Inspect the registered Distributor fields and their raw stored values on one Press Release without changing the post.</p>
            <div class="hpr-form-grid"><label class="hpc-field"><span>Press Release post ID</span><input id="hpr-acf-post-id" type="number" min="1" placeholder="123"></label></div>
            <div class="hpr-button-row"><?php echo hpr_action_button( 'Run ACF Test', [ 'working_label' => 'Testing...', 'success_label' => 'Test complete', 'error_label' => 'Test failed', 'attrs' => [ 'id' => 'hpr-acf-test' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php echo hpr_secondary_result( 'hpr-acf-test-result', 'Stored-field report' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
    </div>
    <script>
    (function($){var root=$('#hpr-content-model');if(!root.length||root.data('ready'))return;root.data('ready',1);root.on('click','#hpr-acf-test',function(){var button=this,$r=$('#hpr-acf-test-result');if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);$.post(ajaxurl,{action:'hpr_inspect_acf_post',nonce:window.hprNonce,post_id:$('#hpr-acf-post-id').val()}).done(function(res){var ok=!!res.success,data=res.data||res;$r.toggleClass('is-success',ok).toggleClass('is-error',!ok).text(JSON.stringify(data,null,2));if(window.HexaWpCoreDynamicButton){if(ok)window.HexaWpCoreDynamicButton.success(button,'Test complete');else window.HexaWpCoreDynamicButton.error(button,'Test failed');}if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-acf-notice',{tone:ok?'success':'error',title:ok?'ACF test completed':'ACF test failed',message:data&&data.message?data.message:(ok?'Stored field values were loaded.':'The request failed.')});}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},data=res.data||res;$r.removeClass('is-success').addClass('is-error').text(JSON.stringify(data,null,2)).closest('details').prop('open',true);if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(button,'Test failed');if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.error('#hpr-acf-notice','ACF test failed',data&&data.message?data.message:'Request failed.');});});})(jQuery);
    </script>
    <?php
}
