<?php

namespace hpr_distributor;

use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Migration\LegacyDependencyRetirement;
use hpr_distributor\Setup\HexaPrWireAuthor;

defined( 'ABSPATH' ) || exit;

function display_settings_import_sync(): void {
    $settings = NativeFeedSettings::get();
    $readiness = NativeFeedSettings::readiness();
    $legacy = LegacyDependencyRetirement::state();
    $users = get_users( [ 'fields' => [ 'ID', 'display_name', 'user_login' ], 'orderby' => 'display_name' ] );
    $hexa_author = HexaPrWireAuthor::find();
    $hexa_author_id = $hexa_author instanceof \WP_User ? (int) $hexa_author->ID : 0;
    $selected_author_id = 0 < (int) $settings['author_id'] ? (int) $settings['author_id'] : $hexa_author_id;
    $author_is_default = 0 === $hexa_author_id || $selected_author_id === $hexa_author_id;
    $primary_conflict = (string) ( $legacy['conflicts'][0] ?? '' );
    ?>
    <div id="hpr-import-sync" data-recommended-author="<?php echo (int) $hexa_author_id; ?>">
        <div class="hpr-page-head">
            <div><h2>Import &amp; Sync</h2><p>Configure the source feed, test it safely, preview the next batch, run an import, or pull one exact release.</p></div>
            <span id="hpr-import-readiness-pill"><?php echo hpr_status_pill( $readiness['ready'] ? 'Live import ready' : 'Live import paused', $readiness['ready'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        </div>

        <?php echo hpr_dynamic_notice( 'hpr-import-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <?php if ( ! $readiness['ready'] ) : ?>
            <div class="hpr-notice warning" id="hpr-import-readiness-warning">
                <strong>Live import paused:</strong> <?php echo esc_html( '' !== $primary_conflict ? $primary_conflict : (string) ( $readiness['errors'][0] ?? 'The importer settings need attention.' ) ); ?>
                <span class="hpr-data-description">Feed testing and dry runs are still available. Only live imports are paused.</span>
                <?php if ( 1 < count( (array) $readiness['errors'] ) ) : ?>
                    <details class="hpr-secondary"><summary>Other reasons</summary><div class="hpr-secondary-body"><ul class="hpc-list"><?php foreach ( array_slice( (array) $readiness['errors'], 1 ) as $error ) : ?><li><?php echo esc_html( (string) $error ); ?></li><?php endforeach; ?></ul></div></details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="hpr-stack">
            <section class="hpc-card hpr-section">
                <h3>RSS Import Settings</h3>
                <p class="hpr-section-intro">The publication binding is established during onboarding and is not changed by an ordinary settings save.</p>
                <form id="hpr-import-settings-form">
                    <div class="hpr-data-list" style="margin-bottom:14px">
                        <?php echo hpr_data_row( 'Publication binding', '<code>' . esc_html( (string) $settings['publication_slug'] ) . '</code>', 'To rebind this outlet, use a separate reviewed onboarding action.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <input type="hidden" name="publication_slug" value="<?php echo esc_attr( (string) $settings['publication_slug'] ); ?>">
                    <div class="hpr-form-grid">
                        <label class="hpc-field"><span>Hexa PR Wire feed URL</span><input type="url" name="feed_url" value="<?php echo esc_attr( (string) $settings['feed_url'] ); ?>" required></label>
                        <label class="hpc-field"><span>Author</span><select id="hpr-import-author" name="author_id"><option value="0">Hexa PR Wire user / current user fallback</option><?php foreach ( $users as $user ) : ?><option value="<?php echo (int) $user->ID; ?>" <?php selected( $selected_author_id, (int) $user->ID ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' . ( (int) $user->ID === $hexa_author_id ? ' — recommended' : '' ) ); ?></option><?php endforeach; ?></select><small class="hpr-data-description">Hexa PR Wire is the recommended default. Another author is allowed, but the page will warn before and after saving.</small></label>
                        <div id="hpr-author-warning" class="hpr-notice warning"<?php echo $author_is_default ? ' hidden' : ''; ?>><strong>Non-default author selected.</strong> Imported releases will be assigned to this author instead of Hexa PR Wire.</div>
                        <label class="hpc-field"><span>Post status</span><select name="post_status"><?php foreach ( [ 'publish' => 'Publish', 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private' ] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['post_status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label class="hpc-field"><span>Schedule interval</span><select name="interval"><?php foreach ( [ 'hourly' => 'Hourly', 'twicedaily' => 'Twice daily', 'daily' => 'Daily' ] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['interval'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label class="hpc-field"><span>Items per batch</span><input type="number" name="max_items" min="1" max="250" value="<?php echo (int) $settings['max_items']; ?>"></label>
                        <label class="hpc-field"><span>Run history records</span><input type="number" name="run_history_limit" min="5" max="50" value="<?php echo (int) $settings['run_history_limit']; ?>"></label>
                        <label class="hpc-field"><span>Item rows retained per run</span><input type="number" name="item_history_limit" min="10" max="100" value="<?php echo (int) $settings['item_history_limit']; ?>"></label>
                    </div>
                    <div class="hpr-settings-list">
                        <?php foreach ( [
                            'enabled' => [ 'Enable native importer', 'Master switch for live import writes.' ],
                            'schedule_enabled' => [ 'Enable scheduled polling', 'Uses the interval selected above.' ],
                            'update_existing' => [ 'Update matching releases', 'Turn off to preserve existing destination content.' ],
                            'cache_bust' => [ 'Bypass feed caches', 'Adds a request-specific cache key.' ],
                        ] as $option => $copy ) : ?>
                            <div class="hpr-setting-row"><label class="hpr-check"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( ! empty( $settings[ $option ] ) ); ?>><span><strong><?php echo esc_html( $copy[0] ); ?></strong><small><?php echo esc_html( $copy[1] ); ?></small></span></label></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hpr-button-row"><?php echo hpr_action_button( 'Save Import Settings', [ 'working_label' => 'Saving...', 'success_label' => 'Saved', 'error_label' => 'Save failed', 'attrs' => [ 'data-hpr-save-import' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </form>
                <?php echo hpr_secondary_result( 'hpr-import-settings-result', 'Saved values and readiness details' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Test and Import</h3>
                <p class="hpr-section-intro">Use the steps in order when checking a new or changed feed. Each action stays on this page and reports its result at the top.</p>
                <div class="hpr-step-list">
                    <?php
                    echo hpr_step_row( 1, 'Test the feed', 'Fetches and validates the XML. Nothing is imported.', '<div class="hpr-button-row">' . hpr_action_button( 'Test Feed', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-import-action' => 'test-feed' ] ] ) . '</div>' . hpr_secondary_result( 'hpr-test-feed-result' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_step_row( 2, 'Preview the next batch', 'Runs the importer in dry-run mode without publishing or moving the live cursor.', '<div class="hpr-button-row">' . hpr_action_button( 'Dry Run', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-import-action' => 'dry-run' ] ] ) . '</div>' . hpr_secondary_result( 'hpr-dry-run-result' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_step_row( 3, 'Run the next live batch', 'Creates or updates eligible releases and advances the live cursor.', '<div class="hpr-button-row">' . hpr_action_button( 'Run Next Batch', [ 'attrs' => [ 'data-hpr-import-action' => 'run' ] ] ) . '</div>' . hpr_secondary_result( 'hpr-run-result' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ob_start();
                    ?>
                    <label class="hpc-field"><span>Source URL, source ID, or slug</span><input id="hpr-force-pull-identifier" type="text" placeholder="https://hexaprwire.com/example-release/"></label>
                    <div class="hpr-button-row"><?php echo hpr_action_button( 'Preview Release', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-force-pull' => 'preview' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo hpr_action_button( 'Pull Release Now', [ 'attrs' => [ 'data-hpr-force-pull' => 'run' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php echo hpr_secondary_result( 'hpr-force-pull-result' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php
                    echo hpr_step_row( 4, 'Force pull one release', 'Targets one exact Hexa PR Wire source item through the same deduplication rules.', (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo hpr_step_row( 5, 'Reconcile source slugs', 'Previews or repairs only safe destination slugs. Conflicts are never overwritten.', '<div class="hpr-button-row">' . hpr_action_button( 'Preview Slug Repairs', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-slug-repair' => 'preview' ] ] ) . hpr_action_button( 'Repair Safe Slugs', [ 'attrs' => [ 'data-hpr-slug-repair' => 'run' ] ] ) . '</div>' . hpr_secondary_result( 'hpr-slug-repair-result' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Competing Plugin Controls</h3>
                <p class="hpr-section-intro">Nothing is disabled automatically. These are explicit one-click actions and each preserves stored posts and plugin data.</p>
                <div class="hpr-record-list">
                    <?php
                    $echo_job_actions = 0 < (int) $legacy['enabled_matching_echo_rules']
                        ? hpr_action_button( 'Disable Matching Echo Job', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-legacy-action' => LegacyDependencyRetirement::ACTION_DISABLE_ECHO_JOB ] ] )
                        : '';
                    echo hpr_record_row( 'Matching Echo RSS job', '<span class="hpr-legacy-state">' . ( 0 < (int) $legacy['enabled_matching_echo_rules'] ? (int) $legacy['enabled_matching_echo_rules'] . ' enabled job(s) can import the same feed.' : 'No enabled matching job.' ) . '</span>', $echo_job_actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    $echo_actions = $legacy['echo_rss_active']
                        ? hpr_action_button( 'Disable Echo RSS', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-legacy-action' => LegacyDependencyRetirement::ACTION_DISABLE_ECHO_PLUGIN ] ] )
                        : '';
                    echo hpr_record_row( 'Echo RSS plugin', '<span class="hpr-legacy-state">' . ( $legacy['echo_rss_active'] ? 'Active. It is not required by Distributor.' : 'Inactive.' ) . '</span>', $echo_actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    $fifu_actions = $legacy['fifu_active']
                        ? hpr_action_button( 'Disable FIFU', [ 'class' => 'hpc-button danger', 'attrs' => [ 'data-hpr-legacy-action' => LegacyDependencyRetirement::ACTION_DISABLE_FIFU_PLUGIN ] ] )
                        : '';
                    echo hpr_record_row( 'FIFU plugin', '<span class="hpr-legacy-state">' . ( $legacy['fifu_active'] ? 'Active. It can overwrite Distributor-owned remote featured images.' : 'Inactive.' ) . '</span>', $fifu_actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            </section>
        </div>
    </div>
    <script>
    (function($){
        var root=$('#hpr-import-sync');if(!root.length||root.data('ready'))return;root.data('ready',1);
        function payload(res){return res&&res.data!==undefined?res.data:res}
        function message(data,fallback){return data&&data.message?data.message:fallback}
        function notice(tone,title,text){if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-import-notice',{tone:tone,title:title,message:text});}
        function buttonStart(button){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button)}
        function buttonDone(button,ok){if(!window.HexaWpCoreDynamicButton)return;if(ok)window.HexaWpCoreDynamicButton.success(button,'Done');else window.HexaWpCoreDynamicButton.error(button,'Failed')}
        function technical(selector,res,ok){var el=$(selector),data=payload(res);el.toggleClass('is-success',!!ok).toggleClass('is-error',!ok).text(JSON.stringify(data||res,null,2));if(!ok)el.closest('details').prop('open',true)}
        function updateReadiness(state){if(!state)return;var $pill=$('#hpr-import-readiness-pill .hpc-pill'),$warning=$('#hpr-import-readiness-warning');if(state.ready){$pill.attr('class','hpc-pill success').text('Live import ready');$warning.prop('hidden',true);return;}$pill.attr('class','hpc-pill warning').text('Live import paused');var reasons=state.conflicts||state.errors||[],reason=reasons.length?reasons[0]:'The native importer is not ready.';if(!$warning.length){$warning=$('<div class="hpr-notice warning" id="hpr-import-readiness-warning"></div>').insertAfter('#hpr-import-notice');}$warning.html($('<div>').append($('<strong>').text('Live import paused: ')).append(document.createTextNode(reason)).html()).prop('hidden',false);}
        function request(data,button,result,title){buttonStart(button);data.nonce=window.hprNonce;return $.post(ajaxurl,data).done(function(res){var body=payload(res);technical(result,res,!!res.success);buttonDone(button,!!res.success);if(body&&body.readiness)updateReadiness(body.readiness);if(res.success)notice(body&&body.notice?body.notice.tone:'success',body&&body.notice?body.notice.title:title,message(body,title+' completed.'));else notice('error',title+' failed',message(body,'The request failed.'));}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}};technical(result,res,false);buttonDone(button,false);notice('error',title+' failed',message(payload(res),'Request failed.'));});}
        root.on('change','#hpr-import-author',function(){var recommended=parseInt(root.data('recommended-author'),10)||0,current=parseInt(this.value,10)||0;$('#hpr-author-warning').prop('hidden',!recommended||current===recommended);});
        root.on('submit','#hpr-import-settings-form',function(e){e.preventDefault();var form=this,button=$(form).find('[data-hpr-save-import]')[0],data={action:'hpr_save_import_settings'};$.each($(form).serializeArray(),function(_,item){data[item.name]=item.value;});$(form).find('input[type=checkbox]').each(function(){data[this.name]=this.checked?'1':'0';});request(data,button,'#hpr-import-settings-result','Import settings');});
        root.on('click','[data-hpr-save-import]',function(){var form=this.form||document.getElementById('hpr-import-settings-form');if(form)$(form).trigger('submit');});
        root.on('click','[data-hpr-import-action]',function(){var button=this,op=$(this).data('hpr-import-action'),data,result,title;if(op==='test-feed'){data={action:'hpr_test_feed'};result='#hpr-test-feed-result';title='Feed test';}else if(op==='dry-run'){data={action:'hpr_import_operation',operation:op};result='#hpr-dry-run-result';title='Dry run';}else{data={action:'hpr_import_operation',operation:op};result='#hpr-run-result';title='Live import';}request(data,button,result,title);});
        root.on('click','[data-hpr-force-pull]',function(){var button=this,preview=$(this).data('hpr-force-pull')==='preview';request({action:'hpr_force_pull',identifier:$('#hpr-force-pull-identifier').val(),dry_run:preview?'1':'0'},button,'#hpr-force-pull-result',preview?'Force Pull preview':'Force Pull');});
        root.on('click','[data-hpr-slug-repair]',function(){var button=this,preview=$(this).data('hpr-slug-repair')==='preview';if(!preview&&!window.confirm('Repair every non-conflicting destination slug shown by the current source data?'))return;request({action:'hpr_repair_source_slugs',dry_run:preview?'1':'0'},button,'#hpr-slug-repair-result',preview?'Slug repair preview':'Slug repair');});
        root.on('click','[data-hpr-legacy-action]',function(){var button=this,action=$(this).data('hpr-legacy-action');buttonStart(button);$.post(ajaxurl,{action:'hpr_apply_legacy_action',nonce:window.hprNonce,legacy_action:action}).done(function(res){var body=payload(res);buttonDone(button,!!res.success);if(res.success){$(button).closest('.hpr-record').find('.hpr-legacy-state').text(action==='disable_echo_job'?'No enabled matching job.':'Inactive.');$(button).remove();updateReadiness(body&&body.state);notice('success','Plugin conflict updated',message(body,'The selected action completed.'));}else notice('error','Plugin action failed',message(body,'The selected action failed.'));}).fail(function(xhr){buttonDone(button,false);notice('error','Plugin action failed',message(payload(xhr.responseJSON),'Request failed.'));});});
    })(jQuery);
    </script>
    <?php
}
