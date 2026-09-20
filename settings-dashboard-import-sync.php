<?php

namespace hpr_distributor;

use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function display_settings_import_sync(): void {
    $settings = NativeFeedSettings::get();
    $readiness = NativeFeedSettings::readiness();
    $last_run = get_option( NativeFeedImporter::LAST_RUN_OPTION, [] );
    $last_run = is_array( $last_run ) ? $last_run : [];
    $migration = get_option( NativeFeedSettings::LEGACY_MIGRATION_OPTION, [] );
    $migration = is_array( $migration ) ? $migration : [];
    ?>
    <div class="hpr-panel">
        <div class="hpr-panel-header">Native Import &amp; Sync</div>
        <div class="hpr-panel-body">
            <p>The Distributor polls the Hexa PR Wire publication feed itself. Echo RSS required: <strong>No</strong>. FIFU required: <strong>No</strong>.</p>

            <table class="hpr-table">
                <tbody>
                    <tr><th scope="row">Ready</th><td><?php echo $readiness["ready"] ? "Yes" : "No"; ?></td></tr>
                    <tr><th scope="row">Publication</th><td><code><?php echo esc_html( $settings["publication_slug"] ?: "Not configured" ); ?></code></td></tr>
                    <tr><th scope="row">Source feed</th><td style="word-break:break-all;"><code><?php echo esc_html( $settings["feed_url"] ?: "Not configured" ); ?></code></td></tr>
                    <tr><th scope="row">Schedule</th><td><?php echo $settings["schedule_enabled"] ? esc_html( $settings["interval"] ) : "Disabled"; ?></td></tr>
                    <tr><th scope="row">Import limit</th><td><?php echo (int) $settings["max_items"]; ?> items per run</td></tr>
                    <tr><th scope="row">Images</th><td>Rendered remotely from <code>hexaprwire.com</code>; no local image download.</td></tr>
                    <tr><th scope="row">Contract</th><td><code><?php echo esc_html( NativeFeedSettings::CONTRACT_VERSION ); ?></code></td></tr>
                </tbody>
            </table>

            <?php if ( ! empty( $readiness["errors"] ) ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( implode( " ", $readiness["errors"] ) ); ?></p></div>
            <?php endif; ?>

            <p>
                <button type="button" class="hpr-btn hpr-btn-primary" id="hpr-native-import-run">Run Native Import Now</button>
                <span id="hpr-native-import-status" style="margin-left:10px;"></span>
            </p>

            <h3>Last Run</h3>
            <?php if ( [] === $last_run ) : ?>
                <p>No native import has run yet.</p>
            <?php else : ?>
                <p>
                    <strong>Status:</strong> <?php echo ! empty( $last_run["success"] ) ? "Success" : "Failed"; ?> |
                    <strong>Processed:</strong> <?php echo (int) ( $last_run["items_processed"] ?? 0 ); ?> |
                    <strong>Finished:</strong> <?php echo esc_html( (string) ( $last_run["ended_gmt"] ?? "Unknown" ) ); ?> GMT
                </p>
            <?php endif; ?>

            <h3>Legacy Metadata Migration</h3>
            <p>
                Existing Echo/FIFU records are read only as migration input. Existing WordPress post IDs are preserved.
                <strong>Migrated posts:</strong> <?php echo (int) ( $migration["migrated"] ?? 0 ); ?> |
                <strong>Remote images:</strong> <?php echo (int) ( $migration["images_migrated"] ?? 0 ); ?>
            </p>
        </div>
    </div>
    <script>
    jQuery(function($) {
        $("#hpr-native-import-run").on("click", function() {
            var $button = $(this);
            var $status = $("#hpr-native-import-status");
            $button.prop("disabled", true);
            $status.text("Running...").css("color", "#666");
            $.post(ajaxurl, { action: "hpr_distributor_run_native_import", nonce: hprNonce })
                .done(function(response) {
                    if (response && response.success) {
                        $status.text("Completed. Reloading...").css("color", "#00a32a");
                        setTimeout(function() { window.location.reload(); }, 900);
                        return;
                    }
                    var message = response && response.data && response.data.message ? response.data.message : "Import failed.";
                    $status.text(message).css("color", "#d63638");
                })
                .fail(function(xhr) { $status.text("Request failed: " + xhr.status).css("color", "#d63638"); })
                .always(function() { $button.prop("disabled", false); });
        });
    });
    </script>
    <?php
}
