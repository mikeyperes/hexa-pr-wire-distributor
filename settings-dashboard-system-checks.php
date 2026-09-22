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
    $crons = DashboardData::cron_status();
    ?>
    <div id="hpr-diagnostics">
        <div class="hpr-page-head"><div><h2>Distributor Diagnostics</h2><p>Local configuration checks plus live feed/XML testing on demand.</p></div><?php echo hpr_status_pill( $report['success'] ? 'All checks passed' : $report['failed'] . ' need attention', $report['success'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <div class="hpr-button-row"><button type="button" class="hpc-button" id="hpr-run-all-diagnostics">Run All Tests</button><a class="hpc-button secondary" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">WordPress Site Health</a><span class="spinner"></span></div>
        <div id="hpr-diagnostics-result" class="hpr-result"></div>

        <section class="hpc-card hpr-section">
            <div class="hpr-table-wrap"><table class="hpr-table"><thead><tr><th>Check</th><th>Status</th><th>Details</th><th></th></tr></thead><tbody>
                <?php foreach ( $report['checks'] as $check ) : ?><tr data-check-id="<?php echo esc_attr( $check['id'] ); ?>"><th><?php echo esc_html( $check['label'] ); ?></th><td><?php echo hpr_status_pill( $check['success'] ? 'Pass' : 'Needs attention', $check['success'] ? 'success' : 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo esc_html( $check['detail'] ); ?></td><td><button type="button" class="hpc-button secondary hpr-run-diagnostic" data-check="<?php echo esc_attr( $check['id'] ); ?>">Retest</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>

        <div class="hpc-grid two">
            <section class="hpc-card hpr-section"><h3>Import Cron</h3><table class="hpr-table"><thead><tr><th>Task</th><th>Schedule</th><th>Next run</th></tr></thead><tbody><?php foreach ( $crons as $cron ) : ?><tr><td><?php echo esc_html( $cron['label'] ); ?><br><code><?php echo esc_html( $cron['hook'] ); ?></code></td><td><?php echo $cron['scheduled'] ? esc_html( $cron['interval'] ?: 'single' ) : 'Not scheduled'; ?></td><td><?php echo $cron['next_run'] ? esc_html( wp_date( 'Y-m-d H:i:s T', $cron['next_run'] ) ) : '—'; ?></td></tr><?php endforeach; ?></tbody></table></section>
            <section class="hpc-card hpr-section"><h3>Duplicate Source Metadata</h3><table class="hpr-table"><thead><tr><th>Identity</th><th>Groups</th><th>Affected rows</th></tr></thead><tbody><?php foreach ( $duplicates as $key => $row ) : ?><tr><td><code><?php echo esc_html( $key ); ?></code></td><td><?php echo (int) $row['group_count']; ?></td><td><?php echo (int) $row['affected_rows']; ?></td></tr><?php endforeach; ?></tbody></table><p class="hpr-muted">Collision imports fail closed and list their candidate destination post IDs.</p></section>
        </div>
        <?php DistributorActivity::render(); ?>
    </div>
    <script>
    (function($){var root=$('#hpr-diagnostics');if(!root.length||root.data('ready'))return;root.data('ready',1);function run($b,id){var $s=$b.closest('.hpr-button-row').find('.spinner');if(!$s.length)$s=root.find('.hpr-button-row .spinner');var $r=$('#hpr-diagnostics-result');$b.prop('disabled',true);$s.addClass('is-active');$.post(ajaxurl,{action:'hpr_run_diagnostics',nonce:window.hprNonce,check_id:id||''}).done(function(res){$r.toggleClass('is-success',!!res.success&&!!res.data.success).toggleClass('is-error',!res.success||!res.data.success).text(JSON.stringify(res.data||res,null,2));}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}};$r.removeClass('is-success').addClass('is-error').text(JSON.stringify(res.data||res,null,2));}).always(function(){$b.prop('disabled',false);$s.removeClass('is-active');});}root.on('click','#hpr-run-all-diagnostics',function(){run($(this),'');});root.on('click','.hpr-run-diagnostic',function(){run($(this),$(this).data('check'));});})(jQuery);
    </script>
    <?php
}
