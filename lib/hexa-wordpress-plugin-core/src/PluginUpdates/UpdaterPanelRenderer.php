<?php

namespace Hexa\PluginCore\PluginUpdates;

final class UpdaterPanelRenderer {
    private UpdaterConfig $config;

    public function __construct( UpdaterConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        $status     = ( new PluginUpdateStatus( $this->config ) )->get();
        $controller = new UpdaterAjaxController( $this->config );
        $actions    = $controller->actions();
        $nonce      = wp_create_nonce( $this->config->nonce_action() );
        $dom        = 'hexa-updater-' . preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( $this->config->plugin_slug() ) );
        ?>
        <div id="<?php echo esc_attr( $dom ); ?>" class="hexa-plugin-core-updater">
            <style>
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-card{border:1px solid #dcdcde;border-radius:8px;background:#fff;margin:0 0 16px;padding:18px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-title{font-size:18px;font-weight:700;margin:0 0 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-version{align-items:center;display:grid;grid-template-columns:1fr auto 1fr;gap:16px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-version-side{background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:18px;text-align:center}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-version-num{display:block;font-size:34px;font-weight:800;line-height:1.1}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-label{color:#646970;display:block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-badge{border-radius:999px;display:inline-block;font-weight:700;padding:6px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-badge.is-current{background:#edfaef;color:#1f7a3a}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-badge.is-stale{background:#fff8e5;color:#8a5a00}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-badge.is-update{background:#fcf0f1;color:#b32d2e}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-actions{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-status{margin-top:10px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log{border:1px solid #dcdcde;border-radius:8px;display:none;margin-top:12px;overflow:hidden}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-head{align-items:center;background:#f6f7f7;display:flex;font-weight:700;justify-content:space-between;padding:10px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-body{list-style:none;margin:0;max-height:260px;overflow:auto;padding:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row{display:grid;grid-template-columns:24px 1fr auto;gap:8px;margin:0;padding:8px 12px;border-top:1px solid #f0f0f1}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row.is-error{color:#b32d2e}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row.is-warn{color:#8a5a00}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row.is-done{color:#1f7a3a}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-foot{display:none;padding:10px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-spin{animation:hexaUpdaterSpin .7s linear infinite;border:2px solid rgba(34,113,177,.25);border-radius:50%;border-top-color:#2271b1;display:inline-block;height:14px;width:14px}
                @keyframes hexaUpdaterSpin{to{transform:rotate(360deg)}}
                @media(max-width:900px){#<?php echo esc_attr( $dom ); ?> .hexa-updater-grid,#<?php echo esc_attr( $dom ); ?> .hexa-updater-version{grid-template-columns:1fr}}
            </style>

            <div class="hexa-updater-card">
                <div class="hexa-updater-title"><?php echo esc_html( $this->config->plugin_name() ); ?> Plugin Info</div>
                <div class="hexa-updater-grid">
                    <dl>
                        <dt>Name</dt><dd><?php echo esc_html( $this->config->plugin_name() ); ?></dd>
                        <dt>Installed folder</dt><dd><code><?php echo esc_html( $this->config->runtime_folder_name() ); ?></code></dd>
                        <dt>Repository</dt><dd><a href="<?php echo esc_url( $this->config->github_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $this->config->github_repo() ); ?></a> · <?php echo esc_html( $this->config->github_branch() ); ?></dd>
                    </dl>
                    <div>
                        <span class="hexa-updater-badge <?php echo esc_attr( $status['update_available'] ? ( $status['core_detected'] ? 'is-update' : 'is-stale' ) : 'is-current' ); ?>" data-role="badge">
                            <?php echo esc_html( $status['update_available'] ? ( $status['core_detected'] ? 'Update available (WP sees it)' : 'Update available' ) : 'Up to date' ); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="hexa-updater-card">
                <div class="hexa-updater-title">Version Status</div>
                <div class="hexa-updater-version">
                    <div class="hexa-updater-version-side">
                        <span class="hexa-updater-label">On this site</span>
                        <span class="hexa-updater-version-num" data-role="current-version"><?php echo esc_html( $status['current_version'] ); ?></span>
                        <span>Currently installed</span>
                    </div>
                    <span aria-hidden="true">&rarr;</span>
                    <div class="hexa-updater-version-side">
                        <span class="hexa-updater-label">In the Git repo</span>
                        <span class="hexa-updater-version-num" data-role="latest-version"><?php echo esc_html( $status['latest_version'] ); ?></span>
                        <span><?php echo esc_html( $this->config->github_repo() ); ?> · <?php echo esc_html( $this->config->github_branch() ); ?></span>
                    </div>
                </div>
            </div>

            <div class="hexa-updater-card">
                <div class="hexa-updater-title">Update Actions</div>
                <p>Re-check GitHub, rerun WordPress core update detection, or install the latest GitHub build directly.</p>
                <div class="hexa-updater-actions">
                    <button type="button" class="button button-secondary" data-role="force-check">Force Update Check</button>
                    <button type="button" class="button button-primary" data-role="direct-update" <?php disabled( ! $status['update_available'] ); ?>>Update Now from GitHub</button>
                    <a href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>" class="button button-secondary" target="_blank" rel="noopener">WP Update Page</a>
                </div>
                <div class="hexa-updater-status" data-role="update-status"></div>
                <div class="hexa-updater-log" data-role="update-log">
                    <div class="hexa-updater-log-head"><span>Update Activity</span><span class="hexa-updater-spin" data-role="log-spin"></span></div>
                    <ul class="hexa-updater-log-body" data-role="log-body"></ul>
                    <div class="hexa-updater-log-foot" data-role="log-foot"></div>
                </div>
            </div>

            <div class="hexa-updater-card">
                <div class="hexa-updater-title">Download Plugin ZIP</div>
                <p>Download the current GitHub build with the correct plugin folder name, without the GitHub branch suffix.</p>
                <button type="button" class="button button-secondary" data-role="download-current">Download <?php echo esc_html( $this->config->proper_folder_name() ); ?>.zip</button>
                <span data-role="download-status"></span>
            </div>

            <div class="hexa-updater-card">
                <div class="hexa-updater-title">Version History</div>
                <p>Load recent GitHub commits and download a normalized ZIP for a selected commit.</p>
                <select data-role="version-select"><option value="">Load versions first</option></select>
                <button type="button" class="button button-secondary" data-role="load-versions">Load Versions</button>
                <button type="button" class="button button-secondary" data-role="download-version" disabled>Download Selected Version</button>
                <div class="hexa-updater-status" data-role="version-status"></div>
            </div>

            <script>
            (function($){
                var root = $('#<?php echo esc_js( $dom ); ?>');
                var nonce = '<?php echo esc_js( $nonce ); ?>';
                var actions = <?php echo wp_json_encode( $actions ); ?>;
                var versionData = {};
                var poll = null;
                var renderedSteps = 0;

                function post(action, data, timeout) {
                    return $.ajax({
                        url: window.ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        timeout: timeout || 30000,
                        data: $.extend({ action: action, <?php echo esc_js( $this->config->nonce_param() ); ?>: nonce }, data || {})
                    });
                }

                function cmp(a,b){var pa=String(a||'').split('.').map(Number),pb=String(b||'').split('.').map(Number);for(var i=0;i<Math.max(pa.length,pb.length);i++){var x=pa[i]||0,y=pb[i]||0;if(x>y)return 1;if(x<y)return-1;}return 0;}
                function sync(current, latest, coreDetected){
                    root.find('[data-role=current-version]').text(current);
                    root.find('[data-role=latest-version]').text(latest);
                    var stale = latest && cmp(latest,current)>0;
                    var badge = root.find('[data-role=badge]').removeClass('is-current is-stale is-update');
                    if(stale && coreDetected){badge.addClass('is-update').text('Update available (WP sees it)');root.find('[data-role=direct-update]').prop('disabled',false);}
                    else if(stale){badge.addClass('is-stale').text('Update available');root.find('[data-role=direct-update]').prop('disabled',false);}
                    else{badge.addClass('is-current').text('Up to date');root.find('[data-role=direct-update]').prop('disabled',true);}
                }
                function time(t){var d=t?new Date(t*1000):new Date(),p=function(n){return(n<10?'0':'')+n;};return p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds());}
                function icon(s){if(s==='done')return '✓'; if(s==='warn')return '▲'; if(s==='error')return '✕'; return '<span class="hexa-updater-spin"></span>';}
                function renderLog(data){
                    if(!data || !data.steps)return;
                    var body=root.find('[data-role=log-body]');
                    if(data.steps.length<renderedSteps){body.empty();renderedSteps=0;}
                    for(var i=renderedSteps;i<data.steps.length;i++){
                        var step=data.steps[i];
                        body.append('<li class="hexa-updater-log-row is-'+step.status+'"><span>'+icon(step.status)+'</span><span></span><span>'+time(step.t)+'</span></li>');
                        body.children().last().children().eq(1).text(step.message);
                    }
                    renderedSteps=data.steps.length;
                    if(data.state==='done'||data.state==='error'){
                        root.find('[data-role=log-spin]').hide();
                        root.find('[data-role=log-foot]').text(data.message || '').show();
                        if(poll){clearInterval(poll);poll=null;}
                    }
                }
                function pollLog(){post(actions.get_update_progress,{},8000).done(function(resp){if(resp&&resp.success)renderLog(resp.data);});}

                root.on('click','[data-role=force-check]',function(){
                    var btn=$(this), status=root.find('[data-role=update-status]');
                    btn.prop('disabled',true).text('Checking...');
                    status.text('Clearing caches and re-checking GitHub.');
                    post(actions.force_update_check).done(function(resp){
                        if(resp&&resp.success){sync(root.find('[data-role=current-version]').text(), resp.data.new_version, !!resp.data.core_detected);status.text('Check complete. GitHub: v'+resp.data.new_version+'.');}
                        else{status.text(resp&&resp.data?resp.data:'Check failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Force Update Check');});
                });
                root.on('click','[data-role=direct-update]',function(){
                    if(!window.confirm('This will download the latest build from GitHub and replace this plugin in place. Continue?'))return;
                    var btn=$(this), status=root.find('[data-role=update-status]');
                    btn.prop('disabled',true).text('Updating...');
                    status.text('Starting update.');
                    renderedSteps=0;
                    root.find('[data-role=log-body]').empty();
                    root.find('[data-role=log-foot]').hide().text('');
                    root.find('[data-role=log-spin]').show();
                    root.find('[data-role=update-log]').show();
                    pollLog();
                    poll=setInterval(pollLog,700);
                    post(actions.direct_update_plugin,{},200000).done(function(resp){
                        pollLog();
                        if(resp&&resp.success){sync(resp.data.new_version,resp.data.new_version,false);status.html('Updated to v'+resp.data.new_version+'. <a href="'+window.location.href+'">Reload this page</a>');btn.text('Updated').prop('disabled',true);}
                        else{status.text(resp&&resp.data?resp.data:'Update failed.');btn.prop('disabled',false).text('Update Now from GitHub');}
                    }).fail(function(){status.text('Connection error during update. Use Force Update Check to confirm result.');btn.prop('disabled',false).text('Update Now from GitHub');});
                });
                root.on('click','[data-role=download-current]',function(){
                    var btn=$(this), status=root.find('[data-role=download-status]');
                    btn.prop('disabled',true).text('Preparing...');
                    post(actions.download_plugin_zip,{},60000).done(function(resp){
                        if(resp&&resp.success){status.html('<a href="'+resp.data.url+'" target="_blank" rel="noopener">Download ready</a>');window.location.href=resp.data.url;}
                        else{status.text(resp&&resp.data?resp.data:'Download failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Download <?php echo esc_js( $this->config->proper_folder_name() ); ?>.zip');});
                });
                root.on('click','[data-role=load-versions]',function(){
                    var btn=$(this), select=root.find('[data-role=version-select]'), status=root.find('[data-role=version-status]');
                    btn.prop('disabled',true).text('Loading...');
                    post(actions.load_github_versions,{},60000).done(function(resp){
                        if(resp&&resp.success){select.empty();versionData={};select.append('<option value="">Select Version ('+resp.data.count+' commits)</option>');$.each(resp.data.versions,function(i,v){versionData[v.name]=v.sha;select.append('<option value="'+v.name+'">'+v.name+'</option>');});root.find('[data-role=download-version]').prop('disabled',false);status.text('Loaded '+resp.data.count+' commits.');}
                        else{status.text(resp&&resp.data?resp.data:'Load failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Load Versions');});
                });
                root.on('click','[data-role=download-version]',function(){
                    var btn=$(this), select=root.find('[data-role=version-select]'), status=root.find('[data-role=version-status]'), version=select.val(), sha=versionData[version]||'';
                    if(!version){status.text('Select a version first.');return;}
                    btn.prop('disabled',true).text('Preparing...');
                    post(actions.download_specific_version,{version:version,sha:sha},60000).done(function(resp){
                        if(resp&&resp.success){status.html('<a href="'+resp.data.url+'" target="_blank" rel="noopener">'+resp.data.filename+' ready</a>');window.location.href=resp.data.url;}
                        else{status.text(resp&&resp.data?resp.data:'Download failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Download Selected Version');});
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}
