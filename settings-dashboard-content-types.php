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

        <?php if ( class_exists( ContentTypeRenderer::class ) ) : ?>
            <?php echo ( new ContentTypeRenderer() )->render( PressReleaseStructures::registry(), [ 'title' => 'Press Release Post Type', 'description' => 'The internal key remains press-release so existing URLs, imports and relationships are preserved.', 'persist_prefix' => 'hpr-content-model' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <div class="hpr-notice danger">The Hexa WP Core content-type component is unavailable.</div>
        <?php endif; ?>

        <div class="hpc-grid two">
            <?php foreach ( $acf['groups'] as $group ) : ?>
                <section class="hpc-card hpr-section">
                    <div class="hpr-page-head"><div><h3><?php echo esc_html( $group['title'] ); ?></h3><p><code><?php echo esc_html( $group['key'] ); ?></code></p></div><?php echo hpr_status_pill( $group['registered'] && $group['active'] ? 'Registered' : 'Unavailable', $group['registered'] && $group['active'] ? 'success' : 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <div class="hpr-table-wrap"><table class="hpr-table"><thead><tr><th>Label</th><th>Name</th><th>Type</th></tr></thead><tbody>
                    <?php foreach ( $group['fields'] as $field ) : ?><tr><td><?php echo esc_html( $field['label'] ); ?></td><td><code><?php echo esc_html( $field['name'] ); ?></code></td><td><?php echo esc_html( $field['type'] ); ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </section>
            <?php endforeach; ?>
        </div>

        <section class="hpc-card hpr-section">
            <h3>Test Stored ACF Values</h3>
            <p>Inspect the registered Distributor fields and their raw stored values on one Press Release without changing the post.</p>
            <div class="hpr-form-grid"><label class="hpc-field"><span>Press Release post ID</span><input id="hpr-acf-post-id" type="number" min="1" placeholder="123"></label></div>
            <div class="hpr-button-row"><button class="hpc-button" type="button" id="hpr-acf-test">Run ACF Test</button><span class="spinner"></span></div>
            <div id="hpr-acf-test-result" class="hpr-result"></div>
        </section>
    </div>
    <script>
    (function($){var root=$('#hpr-content-model');if(!root.length||root.data('ready'))return;root.data('ready',1);root.on('click','#hpr-acf-test',function(){var $b=$(this),$s=$b.closest('.hpr-button-row').find('.spinner'),$r=$('#hpr-acf-test-result');$b.prop('disabled',true);$s.addClass('is-active');$.post(ajaxurl,{action:'hpr_inspect_acf_post',nonce:window.hprNonce,post_id:$('#hpr-acf-post-id').val()}).done(function(res){$r.toggleClass('is-success',!!res.success).toggleClass('is-error',!res.success).text(JSON.stringify(res.data||res,null,2));}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}};$r.removeClass('is-success').addClass('is-error').text(JSON.stringify(res.data||res,null,2));}).always(function(){$b.prop('disabled',false);$s.removeClass('is-active');});});})(jQuery);
    </script>
    <?php
}
