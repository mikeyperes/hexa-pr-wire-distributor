<?php

namespace hpr_distributor;

use hpr_distributor\Admin\GoingLiveTab;
use hpr_distributor\Setup\HexaPrWireAuthor;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function hpr_distributor_diagnostic_checks(): array {
    $plugins = GoingLiveTab::check_plugins();
    $force_sync = GoingLiveTab::check_force_sync();
    $echo = GoingLiveTab::check_echo_rule();

    $author = HexaPrWireAuthor::status();
    $author_ready = ! empty( $author["exists"] )
        && ! empty( $author["profile_correct"] )
        && ! empty( $author["avatar_exists"] )
        && ! empty( $author["urls_complete"] );

    $visibility_options = [
        "hide_press_release_from_home_loop",
        "hide_press_release_from_author_loop",
        "hide_press_release_from_category_loop",
        "hide_press_release_from_tag_loop",
        "hide_press_release_from_related_single_loop",
    ];
    $visibility_ready = true;
    foreach ( $visibility_options as $option ) {
        $visibility_ready = $visibility_ready && (bool) get_option( $option, false );
    }
    $visibility_ready = $visibility_ready
        && ! get_option( "add_press_release_to_author_page", false )
        && ! get_option( "add_press_release_to_category_archives", false );

    $fifu_hidden = (bool) get_option( "hpr_ui_cleanup_hide_fifu_featured_image_box", false );
    $fifu_collapsed = (bool) get_option( "hpr_ui_cleanup_collapse_fifu_featured_image_box", false );

    $core_version_file = __DIR__ . "/lib/hexa-wordpress-plugin-core/VERSION";
    $core_version = is_readable( $core_version_file )
        ? trim( (string) file_get_contents( $core_version_file ) )
        : "Unknown";

    return [
        [
            "label"   => "Required plugins",
            "success" => ! empty( $plugins["success"] ),
            "detail"  => (string) ( $plugins["message"] ?? "Plugin status unavailable." ),
        ],
        [
            "label"   => "Protected Force Sync",
            "success" => ! empty( $force_sync["success"] ),
            "detail"  => (string) ( $force_sync["message"] ?? "Force Sync status unavailable." ),
        ],
        [
            "label"   => "Hexa PR Wire author",
            "success" => $author_ready,
            "detail"  => $author_ready
                ? "Canonical login, email, role, profile URLs, and avatar are ready."
                : "Run the author action on Going Live.",
        ],
        [
            "label"   => "Echo RSS importer",
            "success" => ! empty( $echo["success"] ),
            "detail"  => (string) ( $echo["message"] ?? "Echo RSS status unavailable." ),
        ],
        [
            "label"   => "Press release content model",
            "success" => post_type_exists( "press-release" ) && (bool) get_term_by( "slug", "press-release", "category" ),
            "detail"  => "Checks the press-release post type and category.",
        ],
        [
            "label"   => "Frontend visibility",
            "success" => $visibility_ready,
            "detail"  => $visibility_ready
                ? "Press releases are excluded from home, author, taxonomy, and related loops."
                : "Visibility options conflict or are incomplete.",
        ],
        [
            "label"   => "FIFU editor",
            "success" => ! $fifu_hidden && $fifu_collapsed,
            "detail"  => ! $fifu_hidden && $fifu_collapsed
                ? "The image URL box starts collapsed and remains available."
                : "Set hide off and collapse on.",
        ],
        [
            "label"   => "Hexa WordPress Plugin Core",
            "success" => "0.19.39" === $core_version,
            "detail"  => "Bundled package version: " . $core_version . ".",
        ],
    ];
}

function display_settings_system_checks(): void {
    $checks = hpr_distributor_diagnostic_checks();
    ?>
    <div class="hpr-diagnostics">
        <div class="hpr-diagnostics__heading">
            <h2>Distributor Diagnostics</h2>
            <a href="<?php echo esc_url( admin_url( "site-health.php" ) ); ?>">WordPress Site Health</a>
        </div>

        <table class="widefat striped hpr-diagnostics__table">
            <thead>
                <tr>
                    <th scope="col">Check</th>
                    <th scope="col">Status</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $checks as $check ) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $check["label"] ); ?></th>
                        <td>
                            <span class="hpr-diagnostics__status <?php echo $check["success"] ? "is-pass" : "is-fail"; ?>">
                                <?php echo $check["success"] ? "Pass" : "Needs attention"; ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $check["detail"] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <style>
        .hpr-diagnostics__heading {
            align-items: baseline;
            display: flex;
            justify-content: space-between;
            margin: 20px 0 12px;
        }
        .hpr-diagnostics__heading h2 {
            margin: 0;
        }
        .hpr-diagnostics__table {
            table-layout: fixed;
        }
        .hpr-diagnostics__table th:first-child {
            width: 24%;
        }
        .hpr-diagnostics__table th:nth-child(2) {
            width: 15%;
        }
        .hpr-diagnostics__status {
            font-weight: 600;
        }
        .hpr-diagnostics__status.is-pass {
            color: #08783f;
        }
        .hpr-diagnostics__status.is-fail {
            color: #b32d2e;
        }
        @media (max-width: 782px) {
            .hpr-diagnostics__heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }
            .hpr-diagnostics__table {
                table-layout: auto;
            }
        }
    </style>
    <?php
}
