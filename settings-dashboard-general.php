<?php

namespace hpr_distributor;

use hpr_distributor\Migration\LegacyDependencyRetirement;

defined( 'ABSPATH' ) || exit;

function display_settings_general(): void {
    $legacy = LegacyDependencyRetirement::state();
    $settings_url = menu_page_url( Config::$settings_page_slug, false );
    $cron_url = add_query_arg( 'tab', 'cron-runs', $settings_url );
    $import_url = add_query_arg( 'tab', 'import-sync', $settings_url );
    $images_url = add_query_arg( 'tab', 'images', $settings_url );
    $options = [
        'hide_press_release_from_home_loop'           => [ 'Hide from home/posts loops', 'Direct Press Release URLs remain available.', true ],
        'hide_press_release_from_author_loop'         => [ 'Hide from author loops', 'Prevents ordinary author archives from mixing press releases.', true ],
        'hide_press_release_from_category_loop'       => [ 'Hide from category loops', 'Dedicated Press Release queries remain available.', true ],
        'hide_press_release_from_tag_loop'            => [ 'Hide from tag loops', 'Keeps ordinary tag archives clean.', true ],
        'hide_press_release_from_related_single_loop' => [ 'Hide from related-content loops', 'Prevents unrelated post widgets from mixing the CPT.', true ],
        'add_press_release_to_author_page'            => [ 'Include in author archives', 'Compatibility option; conflicts with Hide from author loops.', false ],
        'add_press_release_to_category_archives'      => [ 'Include in category archives', 'Compatibility option; conflicts with Hide from category loops.', false ],
        'enable_press_release_category_on_new_post'   => [ 'Assign Press Release category to new posts', 'Applies the destination category automatically.', false ],
        'disable_rss_caching'                         => [ 'Disable local RSS caching', 'Keeps Distributor-owned feed output current.', true ],
        'enable_hpr_auto_deletes'                     => [ 'Enable deletion synchronization', 'Hourly checks move exact purge-list matches to Trash after the setting is enabled.', false ],
    ];
    ?>
    <div id="hpr-general-settings">
        <div class="hpr-page-head"><div><h2>General Settings</h2><p>Visibility, lifecycle, taxonomy, local feed caching and SEO controls.</p></div></div>
        <?php echo hpr_dynamic_notice( 'hpr-general-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div class="hpr-stack">
            <section class="hpc-card hpr-section">
                <h3>Content &amp; Lifecycle</h3>
                <form id="hpr-general-settings-form">
                    <div class="hpr-settings-list">
                        <?php foreach ( $options as $option => $details ) : ?>
                            <div class="hpr-setting-row"><label class="hpr-check"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( (bool) get_option( $option, (bool) $details[2] ) ); ?>><span><strong><?php echo esc_html( $details[0] ); ?></strong><small><?php echo esc_html( $details[1] ); ?></small></span></label></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hpr-button-row"><?php echo hpr_action_button( 'Save General Settings', [ 'working_label' => 'Saving...', 'success_label' => 'Saved', 'error_label' => 'Save failed', 'attrs' => [ 'data-hpr-save-general' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </form>
                <?php echo hpr_secondary_result( 'hpr-general-result', 'Saved values and schedule reconciliation' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>

            <section class="hpc-card hpr-section">
                <div class="hpr-page-head"><div><h3>Deletion Synchronization</h3><p>Preview the current purge list before moving any destination post to Trash.</p></div><a class="hpc-button secondary" href="<?php echo esc_url( $cron_url ); ?>">Open Cron &amp; Runs</a></div>
                <div class="hpr-step-list">
                    <?php echo hpr_step_row( 1, 'Preview the purge list', 'Fetches the source list and shows exact matching destination posts. Nothing changes.', '<div class="hpr-button-row">' . hpr_action_button( 'Preview Purge', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'id' => 'hpr-purge-preview' ] ] ) . '</div>' . hpr_secondary_result( 'hpr-purge-result', 'Purge preview and execution report' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo hpr_step_row( 2, 'Move the reviewed matches to Trash', 'This button unlocks only after a current preview and requires confirmation.', '<div class="hpr-button-row"><button class="hpc-button danger" type="button" id="hpr-purge-run" disabled>Move Previewed Posts to Trash</button></div>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Legacy Integration Safety</h3>
                <div class="hpr-record-list">
                    <?php
                    echo hpr_record_row( 'Matching Echo RSS jobs', (int) $legacy['enabled_matching_echo_rules'] . ' enabled', '<a class="hpc-button secondary" href="' . esc_url( $import_url ) . '">Open Import Controls</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_record_row( 'Echo RSS plugin', $legacy['echo_rss_active'] ? 'Active. Distributor does not require it.' : 'Inactive.', '<a class="hpc-button secondary" href="' . esc_url( $import_url ) . '">Review Echo</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_record_row( 'FIFU plugin', $legacy['fifu_active'] ? 'Active. It can compete with Distributor remote images.' : 'Inactive.', '<a class="hpc-button secondary" href="' . esc_url( $images_url ) . '">Review FIFU</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            </section>

            <?php if ( function_exists( __NAMESPACE__ . '\\display_seo_settings' ) ) display_seo_settings(); ?>
        </div>
    </div>
    <script>
    (function($){var root=$('#hpr-general-settings');if(!root.length||root.data('ready'))return;root.data('ready',1);var purgeSignature='';
        function body(res){return res&&res.data!==undefined?res.data:res}
        function notice(tone,title,message){if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-general-notice',{tone:tone,title:title,message:message});}
        function start(button){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);else $(button).prop('disabled',true)}
        function done(button,ok,label){if(window.HexaWpCoreDynamicButton){if(ok)window.HexaWpCoreDynamicButton.success(button,label||'Done');else window.HexaWpCoreDynamicButton.error(button,'Failed');}else $(button).prop('disabled',false)}
        function request(data,button,result,title,callback){start(button);data.nonce=window.hprNonce;$.post(ajaxurl,data).done(function(res){var value=body(res),ok=!!res.success;$(result).toggleClass('is-success',ok).toggleClass('is-error',!ok).text(JSON.stringify(value||res,null,2));done(button,ok);notice(ok?'success':'error',ok?title+' completed':title+' failed',value&&value.message?value.message:(ok?'Action completed.':'Action failed.'));if(callback)callback(res);}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},value=body(res);$(result).removeClass('is-success').addClass('is-error').text(JSON.stringify(value||res,null,2)).closest('details').prop('open',true);done(button,false);notice('error',title+' failed',value&&value.message?value.message:'Request failed.');});}
        root.on('submit','#hpr-general-settings-form',function(e){e.preventDefault();var form=this,button=$(form).find('[data-hpr-save-general]')[0],data={action:'hpr_save_general_settings'};$(form).find('input[type=checkbox]').each(function(){data[this.name]=this.checked?'1':'0';});request(data,button,'#hpr-general-result','General settings');});
        root.on('click','[data-hpr-save-general]',function(){var form=this.form||document.getElementById('hpr-general-settings-form');if(form)$(form).trigger('submit');});
        root.on('click','#hpr-purge-preview',function(){var button=this;request({action:'hpr_preview_purge'},button,'#hpr-purge-result','Purge preview',function(res){var value=body(res);purgeSignature=res.success&&value?value.signature:'';$('#hpr-purge-run').prop('disabled',!purgeSignature||!value.found_count);});});
        root.on('click','#hpr-purge-run',function(){if(!purgeSignature||!window.confirm('Move every post in the current preview to Trash?'))return;var button=this;request({action:'hpr_execute_purge',signature:purgeSignature},button,'#hpr-purge-result','Deletion synchronization',function(){purgeSignature='';window.setTimeout(function(){$('#hpr-purge-run').prop('disabled',true);},0);});});
    })(jQuery);
    </script>
    <?php
}
