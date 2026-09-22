<?php

namespace hpr_distributor\Admin;

use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Lifecycle\DeletionSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CronRunsTab {
    public static function render(): void {
        $report = DashboardData::cron_report();
        $tasks = [];
        foreach ( (array) $report['tasks'] as $task ) {
            $tasks[ (string) $task['hook'] ] = $task;
        }
        $import_task = $tasks[ NativeFeedSettings::CRON_HOOK ] ?? [];
        $deletion_task = $tasks[ DeletionSync::CRON_HOOK ] ?? [];
        $import = (array) $report['import'];
        $deletion = (array) $report['deletion'];
        $last_run = (array) ( $import['last_run'] ?? [] );
        $last_scheduled = (array) ( $import['last_scheduled'] ?? [] );
        $last_scheduled_success = (array) ( $import['last_scheduled_success'] ?? [] );
        $deletion_last = (array) ( $deletion['last_run'] ?? [] );
        $deletion_success = (array) ( $deletion['last_success'] ?? [] );
        ?>
        <div id="hpr-cron-runs">
            <div class="hpr-page-head"><div><h2>Cron &amp; Runs</h2><p>Schedules, next-run timing, last attempts, last successful runs, safe tests, cursor state, counts, and bounded reports.</p></div></div>
            <?php echo \hpr_distributor\hpr_dynamic_notice( 'hpr-cron-notice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <div class="hpr-stack">
                <section class="hpc-card hpr-section">
                    <h3>Native Import Cron</h3>
                    <p class="hpr-section-intro">Primary schedule state is shown first. Technical identifiers and full run details remain available below without dominating the page.</p>
                    <div class="hpr-data-list">
                        <?php
                        echo \hpr_distributor\hpr_data_row( 'Status', \hpr_distributor\hpr_status_pill( ! empty( $import_task['scheduled'] ) ? 'Scheduled' : 'Not scheduled', ! empty( $import_task['scheduled'] ) ? 'success' : 'warning' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Schedule', ! empty( $import_task['scheduled'] ) ? esc_html( (string) ( $import_task['interval'] ?: 'single' ) ) : 'Disabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Next run', esc_html( self::time( (int) ( $import_task['next_run'] ?? 0 ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Last scheduled attempt', esc_html( self::run_time( $last_scheduled ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Last scheduled success', esc_html( self::run_time( $last_scheduled_success ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                    <div class="hpr-button-row"><?php echo \hpr_distributor\hpr_action_button( 'Test Import Cron', [ 'working_label' => 'Testing...', 'success_label' => 'Test passed', 'error_label' => 'Test failed', 'attrs' => [ 'data-hpr-test-cron' => 'import' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <p class="hpr-muted">Read-only test: fetches and evaluates the next batch without publishing, updating posts, moving the live cursor, or changing the saved run history.</p>
                    <?php echo \hpr_distributor\hpr_secondary_result( 'hpr-import-cron-test-result', 'Test report' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details class="hpr-secondary"><summary>Technical schedule details</summary><div class="hpr-secondary-body"><div class="hpr-data-list">
                        <?php echo \hpr_distributor\hpr_data_row( 'Hook', '<code>' . esc_html( NativeFeedSettings::CRON_HOOK ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo \hpr_distributor\hpr_data_row( 'Publication', '<code>' . esc_html( (string) ( $import['settings']['publication_slug'] ?? '' ) ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo \hpr_distributor\hpr_data_row( 'Cursor', esc_html( (string) ( $import['cursor']['offset'] ?? 0 ) . ' · cycle ' . (string) ( $import['cursor']['cycle'] ?? 0 ) ), (string) ( $import['cursor']['updated_gmt'] ?? 'Never updated' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div></div></details>
                </section>

                <section class="hpc-card hpr-section">
                    <h3>Latest Import Report</h3>
                    <?php self::render_import_report( $last_run ); ?>
                </section>

                <section class="hpc-card hpr-section">
                    <h3>Import Run History</h3>
                    <p class="hpr-section-intro">Each row shows the result that matters. Open More details for IDs, cursor movement and item-level results.</p>
                    <div class="hpr-record-list">
                        <?php if ( [] === (array) ( $import['history'] ?? [] ) ) : ?>
                            <p class="hpr-empty">No import runs have been recorded.</p>
                        <?php else : foreach ( (array) $import['history'] as $run ) : ?>
                            <?php self::render_history_row( (array) $run ); ?>
                        <?php endforeach; endif; ?>
                    </div>
                </section>

                <section class="hpc-card hpr-section">
                    <h3>Deletion Synchronization Cron</h3>
                    <div class="hpr-data-list">
                        <?php
                        echo \hpr_distributor\hpr_data_row( 'Status', \hpr_distributor\hpr_status_pill( ! empty( $deletion_task['scheduled'] ) ? 'Scheduled' : ( ! empty( $deletion['enabled'] ) ? 'Needs attention' : 'Disabled' ), ! empty( $deletion_task['scheduled'] ) ? 'success' : 'warning' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Schedule', ! empty( $deletion_task['scheduled'] ) ? esc_html( (string) ( $deletion_task['interval'] ?: 'single' ) ) : 'Disabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Next run', esc_html( self::time( (int) ( $deletion_task['next_run'] ?? 0 ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Last attempt', esc_html( self::receipt_time( $deletion_last ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo \hpr_distributor\hpr_data_row( 'Last success', esc_html( self::receipt_time( $deletion_success ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                    <div class="hpr-button-row"><?php echo \hpr_distributor\hpr_action_button( 'Test Deletion Cron', [ 'working_label' => 'Testing...', 'success_label' => 'Test passed', 'error_label' => 'Test failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-hpr-test-cron' => 'deletion' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <p class="hpr-muted">Read-only test: fetches the current purge list and reports matches. It does not move any post to Trash.</p>
                    <?php echo \hpr_distributor\hpr_secondary_result( 'hpr-deletion-cron-test-result', 'Test report' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details class="hpr-secondary"><summary>Latest deletion report</summary><div class="hpr-secondary-body"><?php self::render_deletion_report( $deletion_last ); ?></div></details>
                    <details class="hpr-secondary"><summary>Technical schedule details</summary><div class="hpr-secondary-body"><div class="hpr-data-list"><?php echo \hpr_distributor\hpr_data_row( 'Hook', '<code>' . esc_html( DeletionSync::CRON_HOOK ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div></details>
                </section>
            </div>
        </div>
        <script>
        (function($){var root=$('#hpr-cron-runs');if(!root.length||root.data('ready'))return;root.data('ready',1);
            function body(res){return res&&res.data!==undefined?res.data:res}
            function showNotice(tone,title,message){if(window.HexaWpCoreDynamicNotice)window.HexaWpCoreDynamicNotice.show('#hpr-cron-notice',{tone:tone,title:title,message:message});}
            root.on('click','[data-hpr-test-cron]',function(){var button=this,type=$(this).data('hpr-test-cron'),selector=type==='import'?'#hpr-import-cron-test-result':'#hpr-deletion-cron-test-result',action=type==='import'?'hpr_test_import_cron':'hpr_test_deletion_cron';if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);$.post(ajaxurl,{action:action,nonce:window.hprNonce}).done(function(res){var data=body(res),ok=!!res.success;$(selector).toggleClass('is-success',ok).toggleClass('is-error',!ok).text(JSON.stringify(data||res,null,2));if(window.HexaWpCoreDynamicButton){if(ok)window.HexaWpCoreDynamicButton.success(button,'Test passed');else window.HexaWpCoreDynamicButton.error(button,'Test failed');}showNotice(ok?'success':'error',type==='import'?'Import cron test':'Deletion cron test',data&&data.message?data.message:'Test completed.');}).fail(function(xhr){var res=xhr.responseJSON||{data:{message:'Request failed: '+xhr.status}},data=body(res);$(selector).removeClass('is-success').addClass('is-error').text(JSON.stringify(data||res,null,2)).closest('details').prop('open',true);if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(button,'Test failed');showNotice('error','Cron test failed',data&&data.message?data.message:'Request failed.');});});
        })(jQuery);
        </script>
        <?php
    }

    private static function render_import_report( array $run ): void {
        if ( [] === $run ) {
            echo '<p class="hpr-empty">No completed live import run has been recorded.</p>';
            return;
        }
        $status = (string) ( $run['status'] ?? ( ! empty( $run['success'] ) ? 'success' : 'failed' ) );
        $summary = sprintf(
            '%d processed · %d created · %d updated · %d failed · %d ms',
            (int) ( $run['items_processed'] ?? 0 ),
            (int) ( $run['counts']['created'] ?? 0 ),
            (int) ( $run['counts']['updated'] ?? 0 ),
            (int) ( $run['counts']['failed'] ?? 0 ),
            (int) ( $run['duration_ms'] ?? 0 )
        );
        $reason = self::failure_reason( $run );
        $primary = \hpr_distributor\hpr_status_pill( ucfirst( $status ), self::tone( $status ) ) . ' ' . esc_html( $summary );
        if ( '' !== $reason ) {
            $primary .= '<span class="hpr-data-description"><strong>Why:</strong> ' . esc_html( $reason ) . '</span>';
        }
        $secondary = '<div class="hpr-data-list">'
            . \hpr_distributor\hpr_data_row( 'Run ID', '<code>' . esc_html( (string) ( $run['run_id'] ?? 'Unknown' ) ) . '</code>' )
            . \hpr_distributor\hpr_data_row( 'Trigger', '<code>' . esc_html( (string) ( $run['trigger'] ?? 'Unknown' ) ) . '</code>' )
            . ( '' !== (string) ( $run['error'] ?? '' ) ? \hpr_distributor\hpr_data_row( 'Recorded error', esc_html( (string) $run['error'] ) ) : '' )
            . \hpr_distributor\hpr_data_row( 'Cursor', esc_html( self::cursor_summary( (array) ( $run['cursor'] ?? [] ) ) ) )
            . '</div>';
        echo \hpr_distributor\hpr_record_row( self::run_time( $run ), $primary, '', $secondary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function render_history_row( array $run ): void {
        $status = (string) ( $run['status'] ?? ( ! empty( $run['success'] ) ? 'success' : 'failed' ) );
        $title = self::run_time( $run ) . ' — ' . (string) ( $run['trigger'] ?? 'run' );
        $summary = \hpr_distributor\hpr_status_pill( ucfirst( $status ), self::tone( $status ) ) . ' '
            . esc_html( (int) ( $run['items_processed'] ?? 0 ) . ' processed · ' . (int) ( $run['counts']['failed'] ?? 0 ) . ' failed' );
        $reason = self::failure_reason( $run );
        if ( '' !== $reason ) {
            $summary .= '<span class="hpr-data-description"><strong>Why:</strong> ' . esc_html( $reason ) . '</span>';
        }
        $secondary = '<div class="hpr-data-list">'
            . \hpr_distributor\hpr_data_row( 'Run ID', '<code>' . esc_html( (string) ( $run['run_id'] ?? 'Unknown' ) ) . '</code>' )
            . \hpr_distributor\hpr_data_row( 'Duration', esc_html( (string) ( $run['duration_ms'] ?? 0 ) . ' ms' ) )
            . \hpr_distributor\hpr_data_row( 'Counts', esc_html( wp_json_encode( (array) ( $run['counts'] ?? [] ) ) ) )
            . ( '' !== (string) ( $run['error'] ?? '' ) ? \hpr_distributor\hpr_data_row( 'Recorded error', esc_html( (string) $run['error'] ) ) : '' )
            . \hpr_distributor\hpr_data_row( 'Cursor', esc_html( self::cursor_summary( (array) ( $run['cursor'] ?? [] ) ) ) )
            . '</div>'
            . self::item_rows( (array) ( $run['items'] ?? [] ) );
        echo \hpr_distributor\hpr_record_row( $title, $summary, '', $secondary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function render_deletion_report( array $receipt ): void {
        if ( [] === $receipt ) {
            echo '<p class="hpr-empty">No deletion synchronization run has been recorded.</p>';
            return;
        }
        $status = (string) ( $receipt['status'] ?? ( ! empty( $receipt['success'] ) ? 'success' : 'failed' ) );
        echo '<div class="hpr-data-list">';
        echo \hpr_distributor\hpr_data_row( 'Result', \hpr_distributor\hpr_status_pill( ucfirst( $status ), self::tone( $status ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \hpr_distributor\hpr_data_row( 'Completed', esc_html( self::receipt_time( $receipt ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \hpr_distributor\hpr_data_row( 'Duration', esc_html( (string) ( $receipt['duration_ms'] ?? 0 ) . ' ms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \hpr_distributor\hpr_data_row( 'Source items', esc_html( (string) ( $receipt['source_count'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \hpr_distributor\hpr_data_row( 'Posts trashed', esc_html( (string) ( $receipt['trashed_count'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo \hpr_distributor\hpr_data_row( 'Failures', esc_html( (string) count( (array) ( $receipt['failed_ids'] ?? [] ) ) ), (string) ( $receipt['error'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    private static function item_rows( array $items ): string {
        if ( [] === $items ) {
            return '';
        }
        $html = '<div class="hpr-record-list" style="margin-top:12px">';
        foreach ( $items as $item ) {
            $source = ! empty( $item['source_url'] )
                ? '<a href="' . esc_url( (string) $item['source_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) ( $item['source_title'] ?? 'Source' ) ) . '</a>'
                : esc_html( (string) ( $item['source_title'] ?? 'Source item' ) );
            $destination = ! empty( $item['destination_url'] )
                ? ' · <a href="' . esc_url( (string) $item['destination_url'] ) . '" target="_blank" rel="noopener noreferrer">View destination</a>'
                : '';
            $html .= \hpr_distributor\hpr_record_row( ucfirst( (string) ( $item['action'] ?? 'item' ) ), $source . $destination . ( ! empty( $item['error'] ) ? '<span class="hpr-data-description">' . esc_html( (string) $item['error'] ) . '</span>' : '' ) );
        }
        return $html . '</div>';
    }

    private static function cursor_summary( array $cursor ): string {
        if ( [] === $cursor ) {
            return 'No cursor data';
        }
        return (int) ( $cursor['offset'] ?? 0 ) . ' → ' . (int) ( $cursor['next_offset'] ?? 0 )
            . ( ! empty( $cursor['cycle_complete'] ) ? ' · cycle complete' : '' );
    }

    private static function failure_reason( array $run ): string {
        $reason = trim( wp_strip_all_tags( (string) ( $run['error'] ?? '' ) ) );
        if ( '' === $reason ) {
            return '';
        }

        return trim( (string) preg_replace( '/^Legacy import conflict:\s*/i', '', $reason ) );
    }

    private static function run_time( array $run ): string {
        return (string) ( $run['ended_gmt'] ?? $run['started_gmt'] ?? 'Never' );
    }

    private static function receipt_time( array $receipt ): string {
        return (string) ( $receipt['completed_gmt'] ?? $receipt['started_gmt'] ?? 'Never' );
    }

    private static function time( int $timestamp ): string {
        return 0 < $timestamp ? wp_date( 'Y-m-d H:i:s T', $timestamp ) : 'Not scheduled';
    }

    private static function tone( string $status ): string {
        return 'success' === $status ? 'success' : ( 'partial' === $status ? 'warning' : 'danger' );
    }
}
