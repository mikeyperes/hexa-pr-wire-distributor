<?php
namespace hpr_distributor;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

const HPR_ECHO_PLUGIN_FILE = "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php";
const HPR_ECHO_LOG_OPTION = "hpr_echo_rss_modification_log";

add_action( "wp_ajax_hpr_echo_apply_baseline", __NAMESPACE__ . "\\ajax_hpr_echo_apply_baseline" );
add_action( "wp_ajax_hpr_echo_repair_slugs", __NAMESPACE__ . "\\ajax_hpr_echo_repair_slugs" );
add_action( "wp_ajax_hpr_echo_force_update_check", __NAMESPACE__ . "\\ajax_hpr_echo_force_update_check" );
add_action( "wp_ajax_hpr_echo_update_plugin", __NAMESPACE__ . "\\ajax_hpr_echo_update_plugin" );

function hpr_echo_rss_get_rules() {
    $rules = get_option( "echo_rules_list", [] );
    return is_array( $rules ) ? $rules : [];
}

function hpr_echo_rss_is_hexa_rule( array $rule ) {
    $feed_url  = isset( $rule[0] ) ? (string) $rule[0] : "";
    $post_type = isset( $rule[6] ) ? sanitize_key( (string) $rule[6] ) : "";

    if ( "press-release" !== $post_type || "" === $feed_url ) {
        return false;
    }

    $parsed = wp_parse_url( $feed_url );
    if ( empty( $parsed["host"] ) || strtolower( $parsed["host"] ) !== "hexaprwire.com" || empty( $parsed["query"] ) ) {
        return false;
    }

    $query = [];
    parse_str( $parsed["query"], $query );
    return ( $query["feed"] ?? "" ) === "rss_publication";
}

function hpr_echo_rss_get_hexa_rules() {
    $rules = hpr_echo_rss_get_rules();
    $found = [];

    foreach ( $rules as $rule_id => $rule ) {
        if ( is_array( $rule ) && hpr_echo_rss_is_hexa_rule( $rule ) ) {
            $found[ (int) $rule_id ] = $rule;
        }
    }

    return $found;
}

function hpr_echo_rss_get_rule_summary() {
    $summaries = [];

    foreach ( hpr_echo_rss_get_hexa_rules() as $rule_id => $rule ) {
        $summaries[] = [
            "id"              => (int) $rule_id,
            "feed_url"        => isset( $rule[0] ) ? (string) $rule[0] : "",
            "active"          => ! empty( $rule[1] ),
            "last_run"        => isset( $rule[3] ) ? (string) $rule[3] : "",
            "post_status"     => isset( $rule[5] ) ? (string) $rule[5] : "",
            "post_type"       => isset( $rule[6] ) ? (string) $rule[6] : "",
            "update_existing" => isset( $rule[67] ) ? (string) $rule[67] : "",
            "copy_slug"       => isset( $rule[82] ) ? (string) $rule[82] : "",
            "identity"        => isset( $rule[37] ) ? (string) $rule[37] : "",
        ];
    }

    return $summaries;
}

function hpr_echo_rss_log( $action, $message, array $context = [] ) {
    $log = get_option( HPR_ECHO_LOG_OPTION, [] );
    $log = is_array( $log ) ? $log : [];

    array_unshift(
        $log,
        [
            "time_gmt" => current_time( "mysql", true ),
            "user_id"  => get_current_user_id(),
            "action"   => sanitize_key( $action ),
            "message"  => sanitize_text_field( $message ),
            "context"  => $context,
        ]
    );

    update_option( HPR_ECHO_LOG_OPTION, array_slice( $log, 0, 100 ), false );
}

function hpr_echo_rss_apply_baseline( $rule_ids = null ) {
    $rules   = hpr_echo_rss_get_rules();
    $targets = [];
    $changes = [];

    if ( is_array( $rule_ids ) && ! empty( $rule_ids ) ) {
        foreach ( $rule_ids as $rule_id ) {
            $rule_id = (int) $rule_id;
            if ( isset( $rules[ $rule_id ] ) && is_array( $rules[ $rule_id ] ) && hpr_echo_rss_is_hexa_rule( $rules[ $rule_id ] ) ) {
                $targets[ $rule_id ] = $rules[ $rule_id ];
            }
        }
    } else {
        $targets = hpr_echo_rss_get_hexa_rules();
    }

    foreach ( $targets as $rule_id => $rule ) {
        $before = [
            "update_existing" => isset( $rules[ $rule_id ][67] ) ? (string) $rules[ $rule_id ][67] : "",
            "copy_slug"       => isset( $rules[ $rule_id ][82] ) ? (string) $rules[ $rule_id ][82] : "",
        ];

        $rules[ $rule_id ][67] = "1";
        $rules[ $rule_id ][82] = "1";

        $after = [ "update_existing" => "1", "copy_slug" => "1" ];

        if ( $before !== $after ) {
            $changes[] = [ "rule_id" => (int) $rule_id, "before" => $before, "after" => $after ];
        }
    }

    if ( ! empty( $changes ) ) {
        update_option( "echo_rules_list", $rules, false );
        wp_cache_delete( "echo_rules_list", "options" );
        hpr_echo_rss_log( "apply_baseline", "Applied Echo RSS baseline settings.", [ "changes" => $changes ] );
    }

    return [ "rules_checked" => count( $targets ), "changed" => count( $changes ), "changes" => $changes ];
}

function hpr_echo_rss_source_slug_from_url( $source_url ) {
    $path = wp_parse_url( (string) $source_url, PHP_URL_PATH );
    return empty( $path ) ? "" : sanitize_title( basename( untrailingslashit( $path ) ) );
}

function hpr_echo_rss_get_source_url_for_post( $post_id ) {
    foreach ( [ "original_post_url", "echo_post_full_url", "echo_post_url" ] as $meta_key ) {
        $source_url = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
        if ( "" !== $source_url ) {
            return $source_url;
        }
    }

    return "";
}

function hpr_echo_rss_repair_source_slugs( $rule_id = null, $limit = 0, $dry_run = false ) {
    $query_args = [
        "post_type"              => "press-release",
        "post_status"            => [ "publish", "draft", "pending", "private", "future", "trash" ],
        "posts_per_page"         => $limit > 0 ? (int) $limit : -1,
        "fields"                 => "ids",
        "no_found_rows"          => true,
        "update_post_meta_cache" => false,
        "update_post_term_cache" => false,
    ];

    if ( null !== $rule_id ) {
        $query_args["meta_query"] = [ [ "key" => "echo_parent_rule", "value" => (string) (int) $rule_id ] ];
    }

    $result = [ "checked" => 0, "repaired" => 0, "skipped" => 0, "conflicts" => [], "changed" => [], "dry_run" => (bool) $dry_run ];

    foreach ( get_posts( $query_args ) as $post_id ) {
        $result["checked"]++;
        $source_url  = hpr_echo_rss_get_source_url_for_post( $post_id );
        $source_slug = hpr_echo_rss_source_slug_from_url( $source_url );
        $current     = get_post( $post_id );

        if ( ! $current || "" === $source_slug || $current->post_name === $source_slug ) {
            $result["skipped"]++;
            continue;
        }

        $conflict = get_page_by_path( $source_slug, OBJECT, "press-release" );
        if ( $conflict && (int) $conflict->ID !== (int) $post_id ) {
            $result["conflicts"][] = [ "post_id" => (int) $post_id, "conflict_id" => (int) $conflict->ID, "source_slug" => $source_slug ];
            continue;
        }

        $change = [ "post_id" => (int) $post_id, "old_slug" => $current->post_name, "source_slug" => $source_slug, "source_url" => $source_url ];

        if ( ! $dry_run ) {
            if ( ! in_array( $current->post_name, get_post_meta( $post_id, "_wp_old_slug", false ), true ) ) {
                add_post_meta( $post_id, "_wp_old_slug", $current->post_name );
            }

            $updated = wp_update_post( [ "ID" => (int) $post_id, "post_name" => $source_slug ], true );
            if ( is_wp_error( $updated ) ) {
                $change["error"] = $updated->get_error_message();
                $result["conflicts"][] = $change;
                continue;
            }

            clean_post_cache( $post_id );
            $after = get_post( $post_id );
            $change["final_slug"] = $after ? $after->post_name : "";
        }

        $result["changed"][] = $change;
        $result["repaired"]++;
    }

    if ( ! $dry_run && $result["repaired"] > 0 ) {
        hpr_echo_rss_log( "repair_slugs", "Repaired Echo RSS source slugs.", $result );
    }

    return $result;
}

function hpr_echo_rss_get_plugin_status() {
    if ( ! function_exists( "get_plugins" ) ) {
        require_once ABSPATH . "wp-admin/includes/plugin.php";
    }

    $plugins = get_plugins();
    $installed = isset( $plugins[ HPR_ECHO_PLUGIN_FILE ] );
    $update_plugins = get_site_transient( "update_plugins" );
    $update = $update_plugins && isset( $update_plugins->response[ HPR_ECHO_PLUGIN_FILE ] ) ? $update_plugins->response[ HPR_ECHO_PLUGIN_FILE ] : null;

    return [
        "installed"        => $installed,
        "active"           => $installed ? is_plugin_active( HPR_ECHO_PLUGIN_FILE ) : false,
        "version"          => $installed ? ( $plugins[ HPR_ECHO_PLUGIN_FILE ]["Version"] ?? "" ) : "",
        "update_available" => (bool) $update,
        "new_version"      => $update ? ( $update->new_version ?? "" ) : "",
    ];
}

function hpr_echo_rss_ajax_guard( $capability = "manage_options" ) {
    check_ajax_referer( Config::AJAX_NONCE, "nonce" );

    if ( ! current_user_can( $capability ) ) {
        wp_send_json_error( [ "message" => "Insufficient permissions." ], 403 );
    }
}

function ajax_hpr_echo_apply_baseline() {
    hpr_echo_rss_ajax_guard( "manage_options" );
    wp_send_json_success( hpr_echo_rss_apply_baseline() );
}

function ajax_hpr_echo_repair_slugs() {
    hpr_echo_rss_ajax_guard( "manage_options" );
    $dry_run = ! empty( $_POST["dry_run"] );
    $rule_id = isset( $_POST["rule_id"] ) && "" !== $_POST["rule_id"] ? (int) $_POST["rule_id"] : null;
    wp_send_json_success( hpr_echo_rss_repair_source_slugs( $rule_id, 0, $dry_run ) );
}

function ajax_hpr_echo_force_update_check() {
    hpr_echo_rss_ajax_guard( "update_plugins" );
    wp_clean_update_cache();
    delete_site_transient( "update_plugins" );
    wp_update_plugins();
    hpr_echo_rss_log( "echo_update_check", "Forced Echo RSS plugin update check." );
    wp_send_json_success( hpr_echo_rss_get_plugin_status() );
}

function ajax_hpr_echo_update_plugin() {
    hpr_echo_rss_ajax_guard( "update_plugins" );
    require_once ABSPATH . "wp-admin/includes/class-wp-upgrader.php";
    require_once ABSPATH . "wp-admin/includes/plugin.php";

    wp_clean_update_cache();
    wp_update_plugins();

    $upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
    $result = $upgrader->upgrade( HPR_ECHO_PLUGIN_FILE );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ "message" => $result->get_error_message() ] );
    }

    hpr_echo_rss_log( "echo_plugin_update", "Requested Echo RSS plugin update.", [ "result" => $result ] );
    wp_send_json_success( hpr_echo_rss_get_plugin_status() );
}

function display_settings_echo_rss() {
    $rules = hpr_echo_rss_get_rule_summary();
    $plugin_status = hpr_echo_rss_get_plugin_status();
    $logs = get_option( HPR_ECHO_LOG_OPTION, [] );
    $logs = is_array( $logs ) ? array_slice( $logs, 0, 20 ) : [];
    $preview = hpr_echo_rss_repair_source_slugs( null, 200, true );
    ?>
    <div class="hpr-panel">
        <div class="hpr-panel-header">Echo RSS Settings</div>
        <div class="hpr-panel-body">
            <p>Controls the Echo RSS rules used by Hexa PR Wire Distributor. Required baseline: <code>update_existing=1</code> and <code>copy_slug=1</code>.</p>

            <h3>Detected Hexa PR Wire Rules</h3>
            <table class="hpr-table">
                <thead><tr><th>Rule</th><th>Active</th><th>Post Type</th><th>Last Run</th><th>Update Existing</th><th>Copy Slug</th><th>Feed</th></tr></thead>
                <tbody>
                    <?php if ( empty( $rules ) ) : ?>
                        <tr><td colspan="7">No Hexa PR Wire Echo rule detected.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $rules as $rule ) : ?>
                            <tr>
                                <td><code>#<?php echo (int) $rule["id"]; ?></code></td>
                                <td><?php echo $rule["active"] ? "Yes" : "No"; ?></td>
                                <td><code><?php echo esc_html( $rule["post_type"] ); ?></code></td>
                                <td><?php echo esc_html( $rule["last_run"] ); ?></td>
                                <td><?php echo "1" === $rule["update_existing"] ? "On" : "Off"; ?></td>
                                <td><?php echo "1" === $rule["copy_slug"] ? "On" : "Off"; ?></td>
                                <td style="word-break:break-all;"><code><?php echo esc_html( $rule["feed_url"] ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="hpr-btn hpr-btn-primary" id="hpr-echo-apply-baseline">Apply Echo Baseline</button>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-echo-repair-slugs">Repair Source Slugs</button>
                <span id="hpr-echo-action-status" style="margin-left:10px;"></span>
            </p>

            <h3>Slug Repair Preview</h3>
            <p><strong>Checked:</strong> <?php echo (int) $preview["checked"]; ?> | <strong>Mismatches:</strong> <?php echo (int) $preview["repaired"]; ?> | <strong>Conflicts:</strong> <?php echo count( $preview["conflicts"] ); ?></p>
            <?php if ( ! empty( $preview["changed"] ) ) : ?>
                <table class="hpr-table">
                    <thead><tr><th>Post ID</th><th>Current Slug</th><th>Source Slug</th></tr></thead>
                    <tbody>
                        <?php foreach ( array_slice( $preview["changed"], 0, 25 ) as $change ) : ?>
                            <tr><td><?php echo (int) $change["post_id"]; ?></td><td><code><?php echo esc_html( $change["old_slug"] ); ?></code></td><td><code><?php echo esc_html( $change["source_slug"] ); ?></code></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3>Echo RSS Plugin Update</h3>
            <p><strong>Installed:</strong> <?php echo $plugin_status["installed"] ? "Yes" : "No"; ?> | <strong>Active:</strong> <?php echo $plugin_status["active"] ? "Yes" : "No"; ?> | <strong>Version:</strong> <code><?php echo esc_html( $plugin_status["version"] ); ?></code> | <strong>Available:</strong> <code><?php echo esc_html( $plugin_status["new_version"] ?: "None detected" ); ?></code></p>
            <p>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-echo-check-update">Check Echo Update</button>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-echo-update-plugin">Update Echo RSS Plugin</button>
                <span id="hpr-echo-update-status" style="margin-left:10px;"></span>
            </p>

            <h3>Modification Log</h3>
            <table class="hpr-table">
                <thead><tr><th>Time GMT</th><th>User</th><th>Action</th><th>Message</th></tr></thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="4">No Echo RSS modifications logged yet.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $entry ) : ?>
                            <tr><td><?php echo esc_html( $entry["time_gmt"] ?? "" ); ?></td><td><?php echo (int) ( $entry["user_id"] ?? 0 ); ?></td><td><code><?php echo esc_html( $entry["action"] ?? "" ); ?></code></td><td><?php echo esc_html( $entry["message"] ?? "" ); ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    jQuery(function($) {
        function hprEchoPost(action, data, $status) {
            $status.text("Running...").css("color", "#666");
            return $.post(ajaxurl, $.extend({ action: action, nonce: hprNonce }, data || {}))
                .done(function(response) {
                    if (response && response.success) {
                        $status.text("Done. Reloading...").css("color", "#00a32a");
                        setTimeout(function() { window.location.reload(); }, 900);
                    } else {
                        var msg = response && response.data && response.data.message ? response.data.message : "Request failed.";
                        $status.text(msg).css("color", "#d63638");
                    }
                })
                .fail(function(xhr) { $status.text("Request failed: " + xhr.status).css("color", "#d63638"); });
        }
        $("#hpr-echo-apply-baseline").on("click", function() { hprEchoPost("hpr_echo_apply_baseline", {}, $("#hpr-echo-action-status")); });
        $("#hpr-echo-repair-slugs").on("click", function() { hprEchoPost("hpr_echo_repair_slugs", {}, $("#hpr-echo-action-status")); });
        $("#hpr-echo-check-update").on("click", function() { hprEchoPost("hpr_echo_force_update_check", {}, $("#hpr-echo-update-status")); });
        $("#hpr-echo-update-plugin").on("click", function() { hprEchoPost("hpr_echo_update_plugin", {}, $("#hpr-echo-update-status")); });
    });
    </script>
    <?php
}
