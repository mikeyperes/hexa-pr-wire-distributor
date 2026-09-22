<?php

namespace hpr_distributor;

use hpr_distributor\Admin\DashboardData;
use hpr_distributor\Admin\DistributorActivity;
use hpr_distributor\Diagnostics\DistributorDiagnostics;

defined( 'ABSPATH' ) || exit;

function hpr_distributor_diagnostic_checks(): array {
    return DistributorDiagnostics::run( false )['checks'];
}

function display_settings_system_checks(): void {
    $report = DistributorDiagnostics::run( false );
    $duplicates = DashboardData::duplicate_report();
    $cron_url = add_query_arg( 'tab', 'cron-runs', menu_page_url( Config::$settings_page_slug, false ) );
    ?>
    <div id="hpr-diagnostics">
        <div class="hpr-page-head"><div><h2>Distributor Diagnostics</h2><p>Local configuration checks plus live feed and XML testing on demand.</p></div><?php echo hpr_status_pill( $report['success'] ? 'All checks passed' : $report['failed'] . ' need attention', $report['success'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php echo hpr_dynamic_notice( 'hpr-diagnostics-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="hpr-button-row"><?php echo hpr_action_button( 'Run All Tests', [ 'working_label' => 'Testing...', 'success_label' => 'Tests complete', 'error_label' => 'Tests failed', 'attrs' => [ 'id' => 'hpr-run-all-diagnostics' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a class="hpc-button secondary" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">WordPress Site Health</a><a class="hpc-button secondary" href="<?php echo esc_url( $cron_url ); ?>">Cron &amp; Runs</a></div>
        <?php echo hpr_secondary_result( 'hpr-diagnostics-result', 'Full diagnostic response' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div class="hpr-stack" style="margin-top:16px">
            <section class="hpc-card hpr-section">
                <h3>Checks</h3>
                <div class="hpr-record-list">
                    <?php foreach ( $report['checks'] as $check ) : ?>
                        <?php
                        $summary = hpr_status_pill( $check['success'] ? 'Pass' : 'Needs attention', $check['success'] ? 'success' : 'danger' ) . ' ' . esc_html( (string) $check['detail'] );
                        $action = hpr_action_button( 'Retest', [ 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-run-diagnostic' => (string) $check['id'] ] ] );
                        echo hpr_record_row( (string) $check['label'], $summary, $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hpc-card hpr-section">
                <h3>Duplicate Source Metadata</h3>
                <p class="hpr-section-intro">Collision imports stop before overwriting a destination and report their candidate post IDs.</p>
                <div class="hpr-data-list">
                    <?php foreach ( $duplicates as $key => $row ) : ?>
                        <?php echo hpr_data_row( (string) $key, (int) $row['group_count'] . ' duplicate group(s)', (int) $row['affected_rows'] . ' affected row(s)' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php DistributorActivity::render(); ?>
        </div>
    </div>
    <script>
    (function($){var root=$('#hpr-diagnostics');if(!root.length||root.data('ready'))return;root.data('ready',1);
        function body(res){return res&&res.data!==undefined?res.data:res}
        function run(button,id){var result=$('#hpr-diagnostics-result');if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);$.post(ajaxurl,{action:'hpr_run_diagnostics',nonce:window.hprNonce,check_id:id||''}).done(function(res){var data=body(res),ok=!!res.success&&!!data.success;result.toggleClass('is-success',ok).toggleClass('is-error',!ok).text(JSON.stringify(data||res,null,2));if(window.HexaWpCoreDynamicButton){if(ok)window.HexaWpCoreDynamicButton.success(button,'Passed');else window.HexaWpCoreDynamicButton.error(button,'Needs attention');}if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-diagnostics-notice',{tone:ok?'success':'warning',title:ok?'Diagnostic passed':'Diagnostic needs attention',message:data&&data.failed?data.failed+' check(s) need attention.':'All selected checks passed.'});}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},data=body(res);result.removeClass('is-success').addClass('is-error').text(JSON.stringify(data||res,null,2)).closest('details').prop('open',true);if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(button,'Failed');if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.error('#hpr-diagnostics-notice','Diagnostic request failed',data&&data.message?data.message:'Request failed.');});}
        root.on('click','#hpr-run-all-diagnostics',function(){run(this,'');});
        root.on('click','[data-hpr-run-diagnostic]',function(){run(this,$(this).data('hpr-run-diagnostic'));});
    })(jQuery);
    </script>
    <?php
}
