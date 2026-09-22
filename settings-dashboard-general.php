<?php

namespace hpr_distributor;

use hpr_distributor\Lifecycle\DeletionSync;
use hpr_distributor\Migration\LegacyDependencyRetirement;

defined( 'ABSPATH' ) || exit;

function display_settings_general(): void {
    $legacy = LegacyDependencyRetirement::state();
    $deletion_receipt = get_option( DeletionSync::RECEIPT_OPTION, [] );
    $deletion_receipt = is_array( $deletion_receipt ) ? $deletion_receipt : [];
    $going_live_url = add_query_arg( 'tab', 'going-live', menu_page_url( Config::$settings_page_slug, false ) );
    $options = [
        'hide_press_release_from_home_loop'           => [ 'Hide from home/posts loops', 'Direct Press Release URLs remain available.', true ],
        'hide_press_release_from_author_loop'         => [ 'Hide from author loops', 'Prevents ordinary author archives from mixing press releases.', true ],
        'hide_press_release_from_category_loop'       => [ 'Hide from category loops', 'Dedicated Press Release queries remain available.', true ],
        'hide_press_release_from_tag_loop'            => [ 'Hide from tag loops', 'Keeps ordinary tag archives clean.', true ],
        'hide_press_release_from_related_single_loop' => [ 'Hide from related-content loops', 'Prevents unrelated post widgets from mixing the CPT.', true ],
        'add_press_release_to_author_page'             => [ 'Include in author archives', 'Compatibility option; conflicts with Hide from author loops.', false ],
        'add_press_release_to_category_archives'       => [ 'Include in category archives', 'Compatibility option; conflicts with Hide from category loops.', false ],
        'enable_press_release_category_on_new_post'   => [ 'Assign Press Release category to new posts', 'Applies the destination category automatically.', false ],
        'disable_rss_caching'                          => [ 'Disable local RSS caching', 'Keeps Distributor-owned feed output current.', true ],
        'enable_hpr_auto_deletes'                      => [ 'Enable deletion synchronization', 'Hourly checks move matching releases to Trash; preview is available below.', false ],
    ];
    ?>
    <div id="hpr-general-settings">
        <div class="hpr-page-head"><div><h2>General Settings</h2><p>Visibility, lifecycle, taxonomy, local feed caching and SEO controls.</p></div></div>

        <section class="hpc-card hpr-section">
            <h3>Content &amp; Lifecycle</h3>
            <form id="hpr-general-settings-form">
                <div class="hpc-grid two">
                    <?php foreach ( $options as $option => $details ) : ?>
                        <label class="hpr-check"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( (bool) get_option( $option, (bool) $details[2] ) ); ?>><span><strong><?php echo esc_html( $details[0] ); ?></strong><small><?php echo esc_html( $details[1] ); ?></small></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="hpr-button-row"><button class="hpc-button" type="submit">Save General Settings</button><span class="spinner"></span></div>
            </form>
            <div id="hpr-general-result" class="hpr-result"></div>
        </section>

        <div class="hpc-grid two">
            <section class="hpc-card hpr-section">
                <h3>Deletion Synchronization</h3>
                <p>Preview the current source purge list before moving any destination posts to Trash.</p>
                <table class="hpr-table"><tbody>
                    <tr><th>Enabled</th><td><?php echo get_option( 'enable_hpr_auto_deletes', false ) ? 'Yes' : 'No'; ?></td></tr>
                    <tr><th>Next run</th><td><?php $next = wp_next_scheduled( DeletionSync::CRON_HOOK ); echo $next ? esc_html( wp_date( 'Y-m-d H:i:s T', (int) $next ) ) : 'Not scheduled'; ?></td></tr>
                    <tr><th>Last completed</th><td><?php echo esc_html( (string) ( $deletion_receipt['completed_gmt'] ?? 'Never' ) ); ?></td></tr>
                    <tr><th>Last trashed</th><td><?php echo (int) ( $deletion_receipt['trashed_count'] ?? 0 ); ?></td></tr>
                </tbody></table>
                <div class="hpr-button-row"><button class="hpc-button secondary" type="button" id="hpr-purge-preview">Preview Purge</button><button class="hpc-button danger" type="button" id="hpr-purge-run" disabled>Move Previewed Posts to Trash</button><span class="spinner"></span></div>
                <div id="hpr-purge-result" class="hpr-result"></div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Legacy Integration Safety</h3>
                <table class="hpr-table"><tbody>
                    <tr><th>Echo RSS plugin</th><td><?php echo $legacy['echo_rss_active'] ? 'Active' : 'Inactive'; ?></td></tr>
                    <tr><th>Matching Echo jobs</th><td><?php echo (int) $legacy['enabled_matching_echo_rules']; ?> enabled</td></tr>
                    <tr><th>FIFU plugin</th><td><?php echo $legacy['fifu_active'] ? 'Active' : 'Inactive'; ?></td></tr>
                    <tr><th>Automatic shutdown</th><td>No</td></tr>
                </tbody></table>
                <div class="hpr-button-row"><a class="hpc-button secondary" href="<?php echo esc_url( $going_live_url ); ?>">Review Explicit Disable Actions</a></div>
            </section>
        </div>

        <?php if ( function_exists( __NAMESPACE__ . '\\display_seo_settings' ) ) display_seo_settings(); ?>
    </div>
    <script>
    (function($){var root=$('#hpr-general-settings');if(!root.length||root.data('ready'))return;root.data('ready',1);var purgeSignature='';
        function show($el,res,ok){$el.toggleClass('is-success',!!ok).toggleClass('is-error',!ok).text(JSON.stringify(res&&res.data!==undefined?res.data:res,null,2));}
        function request(data,$b,$r,done){var $s=$b.closest('.hpr-button-row').find('.spinner');$b.prop('disabled',true);$s.addClass('is-active');data.nonce=window.hprNonce;$.post(ajaxurl,data).done(function(res){show($r,res,!!res.success);if(done)done(res);}).fail(function(xhr){show($r,xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},false);}).always(function(){$b.prop('disabled',false);$s.removeClass('is-active');});}
        root.on('submit','#hpr-general-settings-form',function(e){e.preventDefault();var $f=$(this),data={action:'hpr_save_general_settings'};$f.find('input[type=checkbox]').each(function(){data[this.name]=this.checked?'1':'0';});request(data,$f.find('button[type=submit]'),$('#hpr-general-result'));});
        root.on('click','#hpr-purge-preview',function(){var $b=$(this);request({action:'hpr_preview_purge'},$b,$('#hpr-purge-result'),function(res){purgeSignature=res.success&&res.data?res.data.signature:'';$('#hpr-purge-run').prop('disabled',!purgeSignature||!res.data.found_count);});});
        root.on('click','#hpr-purge-run',function(){if(!purgeSignature)return;if(!window.confirm('Move every post in the current preview to Trash?'))return;var $b=$(this);request({action:'hpr_execute_purge',signature:purgeSignature},$b,$('#hpr-purge-result'),function(){purgeSignature='';window.setTimeout(function(){$b.prop('disabled',true);},0);});});
    })(jQuery);
    </script>
    <?php
}
