<?php

namespace Hexa\PluginCore\CorePackageUpdates;

final class CorePackagePanelRenderer {
    private CorePackageConfig $config;

    public function __construct( CorePackageConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        $status     = ( new CorePackageStatus( $this->config ) )->get();
        $controller = new CorePackageAjaxController( $this->config );
        $actions    = $controller->actions();
        $nonce      = wp_create_nonce( $this->config->nonce_action() );
        $dom        = 'hexa-plugin-core-package-' . md5( $this->config->core_root() );
        ?>
        <div id="<?php echo esc_attr( $dom ); ?>" class="hexa-plugin-core-package-updater">
            <style>
                #<?php echo esc_attr( $dom ); ?> .hexa-core-card{border:1px solid #dcdcde;border-radius:8px;background:#fff;margin:0 0 16px;padding:18px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-title{font-size:18px;font-weight:700;margin:0 0 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-version{align-items:center;display:grid;grid-template-columns:1fr auto 1fr;gap:16px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-version-side{background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:18px;text-align:center}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-version-num{display:block;font-size:34px;font-weight:800;line-height:1.1}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-label{color:#646970;display:block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-badge{border-radius:999px;display:inline-block;font-weight:700;padding:6px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-badge.is-current{background:#edfaef;color:#1f7a3a}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-badge.is-update{background:#fcf0f1;color:#b32d2e}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-actions{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}
                #<?php echo esc_attr( $dom ); ?> code{word-break:break-all}
                @media(max-width:900px){#<?php echo esc_attr( $dom ); ?> .hexa-core-version{grid-template-columns:1fr}}
            </style>

            <div class="hexa-core-card">
                <div class="hexa-core-title"><?php echo esc_html( $this->config->package_name() ); ?></div>
                <p>This is the vendored Hexa WordPress plugin core library, not the host plugin updater.</p>
                <p>
                    <span class="hexa-core-badge <?php echo esc_attr( $status['update_available'] ? 'is-update' : 'is-current' ); ?>" data-role="badge">
                        <?php echo esc_html( $status['update_available'] ? 'Core update available' : 'Core up to date' ); ?>
                    </span>
                </p>
                <p>Repository: <a href="<?php echo esc_url( $this->config->github_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $this->config->github_repo() ); ?></a> · <?php echo esc_html( $this->config->github_branch() ); ?></p>
                <p>Installed path: <code><?php echo esc_html( $this->config->core_root() ); ?></code></p>
            </div>

            <div class="hexa-core-card">
                <div class="hexa-core-title">Core Version Status</div>
                <div class="hexa-core-version">
                    <div class="hexa-core-version-side">
                        <span class="hexa-core-label">In this plugin</span>
                        <span class="hexa-core-version-num" data-role="current-version"><?php echo esc_html( $status['current_version'] ); ?></span>
                        <span>Vendored core</span>
                    </div>
                    <span aria-hidden="true">&rarr;</span>
                    <div class="hexa-core-version-side">
                        <span class="hexa-core-label">In the Git repo</span>
                        <span class="hexa-core-version-num" data-role="latest-version"><?php echo esc_html( $status['latest_version'] ); ?></span>
                        <span><?php echo esc_html( $this->config->github_repo() ); ?></span>
                    </div>
                </div>
                <div class="hexa-core-actions">
                    <button type="button" class="button button-secondary" data-role="force-check">Force Core Check</button>
                    <button type="button" class="button button-primary" data-role="update-core" <?php disabled( ! $status['update_available'] ); ?>>Update Vendored Core</button>
                </div>
                <div data-role="status"></div>
            </div>

            <script>
            (function($){
                var root = $('#<?php echo esc_js( $dom ); ?>');
                var nonce = '<?php echo esc_js( $nonce ); ?>';
                var actions = <?php echo wp_json_encode( $actions ); ?>;
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
                function sync(data){
                    var current = data.current_version || root.find('[data-role=current-version]').text();
                    var latest = data.latest_version || 'Unknown';
                    root.find('[data-role=current-version]').text(current);
                    root.find('[data-role=latest-version]').text(latest);
                    var needs = latest !== 'Unknown' && cmp(latest, current) > 0;
                    root.find('[data-role=badge]').removeClass('is-current is-update').addClass(needs ? 'is-update' : 'is-current').text(needs ? 'Core update available' : 'Core up to date');
                    root.find('[data-role=update-core]').prop('disabled', !needs);
                }
                root.on('click','[data-role=force-check]',function(){
                    var btn=$(this), status=root.find('[data-role=status]');
                    btn.prop('disabled',true).text('Checking...');
                    status.text('Checking Hexa WordPress Plugin Core Git repository.');
                    post(actions.force_check).done(function(resp){
                        if(resp&&resp.success){sync(resp.data);status.text('Core check complete. Git repo: v'+resp.data.latest_version+'.');}
                        else{status.text(resp&&resp.data?resp.data:'Core check failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Force Core Check');});
                });
                root.on('click','[data-role=update-core]',function(){
                    if(!window.confirm('This will update the vendored Hexa WordPress Plugin Core files in this plugin. Continue?'))return;
                    var btn=$(this), status=root.find('[data-role=status]');
                    btn.prop('disabled',true).text('Updating...');
                    status.text('Downloading and copying core package.');
                    post(actions.update_core,{},120000).done(function(resp){
                        if(resp&&resp.success){root.find('[data-role=current-version]').text(resp.data.new_version);status.html('Updated core to v'+resp.data.new_version+'. <a href="'+window.location.href+'">Reload this page</a>');sync({current_version:resp.data.new_version,latest_version:resp.data.new_version});}
                        else{status.text(resp&&resp.data?resp.data:'Core update failed.');btn.prop('disabled',false).text('Update Vendored Core');}
                    }).fail(function(){status.text('Connection error during core update. Run Force Core Check to confirm result.');btn.prop('disabled',false).text('Update Vendored Core');});
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}
