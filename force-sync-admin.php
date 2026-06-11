<?php
namespace hpr_distributor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HPR_FORCE_SYNC_ADMIN_META = '_hws_hpr_force_sync_results';
const HPR_FORCE_SYNC_ADMIN_LOG_OPTION = 'hws_hpr_force_sync_log';

add_action( 'add_meta_boxes', __NAMESPACE__ . '\\hpr_force_sync_admin_register_metabox' );
add_action( 'wp_ajax_hpr_force_sync_publication', __NAMESPACE__ . '\\hpr_force_sync_admin_ajax_publication' );

function hpr_force_sync_admin_enabled(): bool {
    return is_admin() && post_type_exists( 'publication' );
}

function hpr_force_sync_admin_get_token(): string {
    return function_exists( __NAMESPACE__ . '\\hpr_force_sync_get_shared_token' )
        ? (string) hpr_force_sync_get_shared_token()
        : '';
}

function hpr_force_sync_admin_register_metabox(): void {
    if ( ! hpr_force_sync_admin_enabled() ) {
        return;
    }

    add_meta_box(
        'hpr-force-sync',
        'Hexa PR Wire Force Sync',
        __NAMESPACE__ . '\\hpr_force_sync_admin_render_metabox',
        'post',
        'normal',
        'high'
    );
}

function hpr_force_sync_admin_get_publications(): array {
    if ( ! post_type_exists( 'publication' ) ) {
        return [];
    }

    $posts = get_posts(
        [
            'post_type'      => 'publication',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
            'no_found_rows'  => true,
        ]
    );

    $publications = [];
    foreach ( $posts as $post ) {
        $prefix = trim( (string) get_post_meta( $post->ID, 'url_press_release_prefix', true ) );
        $domain = trim( (string) get_post_meta( $post->ID, 'url_nice', true ) );
        $url    = trim( (string) get_post_meta( $post->ID, 'url', true ) );

        if ( '' === $domain && '' !== $prefix ) {
            $domain = (string) wp_parse_url( $prefix, PHP_URL_HOST );
        }

        if ( '' === $domain && '' !== $url ) {
            $domain = (string) wp_parse_url( $url, PHP_URL_HOST );
        }

        $domain = preg_replace( '#^www\.#i', '', strtolower( $domain ) );

        if ( '' === $prefix && '' !== $domain ) {
            $prefix = 'https://' . $domain . '/press-release/';
        }

        if ( '' === $domain || '' === $prefix ) {
            continue;
        }

        $publications[] = [
            'id'     => (int) $post->ID,
            'title'  => get_the_title( $post ),
            'slug'   => $post->post_name,
            'domain' => $domain,
            'prefix' => trailingslashit( $prefix ),
        ];
    }

    return $publications;
}

function hpr_force_sync_admin_get_recent_posts( int $limit = 30 ): array {
    return get_posts(
        [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => 'link_output',
                    'compare' => 'EXISTS',
                ],
            ],
        ]
    );
}

function hpr_force_sync_admin_build_live_url( array $publication, \WP_Post $post ): string {
    return trailingslashit( $publication['prefix'] ) . $post->post_name . '/';
}

function hpr_force_sync_admin_build_endpoint( array $publication, \WP_Post $post ): string {
    return add_query_arg(
        [
            'key'         => hpr_force_sync_admin_get_token(),
            'slug'        => $post->post_name,
            'feed_action' => 'force',
        ],
        'https://' . $publication['domain'] . '/wp-json/hpr-distributor/v1/force-sync'
    );
}

function hpr_force_sync_admin_check_live_url( string $live_url, string $expected_title ): array {
    $response = wp_remote_get(
        $live_url,
        [
            'timeout'     => 30,
            'redirection' => 8,
            'sslverify'   => false,
            'headers'     => [
                'Accept'     => 'text/html',
                'User-Agent' => 'HexaPRWireForceSync/1.0',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return [
            'ok'          => false,
            'status_code' => 0,
            'title_found' => false,
            'message'     => $response->get_error_message(),
        ];
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $body_text   = wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) );
    $title_found = '' !== $expected_title && false !== stripos( $body_text, $expected_title );

    return [
        'ok'          => 200 === $status_code && $title_found,
        'status_code' => $status_code,
        'title_found' => $title_found,
        'message'     => 200 === $status_code ? ( $title_found ? 'Live URL verified.' : 'Live URL loaded but title was not found.' ) : 'Live URL returned HTTP ' . $status_code . '.',
    ];
}

function hpr_force_sync_admin_log_result( array $entry ): void {
    $log = get_option( HPR_FORCE_SYNC_ADMIN_LOG_OPTION, [] );
    $log = is_array( $log ) ? $log : [];

    array_unshift( $log, $entry );
    update_option( HPR_FORCE_SYNC_ADMIN_LOG_OPTION, array_slice( $log, 0, 200 ), false );
}

function hpr_force_sync_admin_store_result( int $post_id, int $publication_id, array $result ): void {
    $stored = get_post_meta( $post_id, HPR_FORCE_SYNC_ADMIN_META, true );
    $stored = is_array( $stored ) ? $stored : [];
    $stored[ $publication_id ] = $result;
    update_post_meta( $post_id, HPR_FORCE_SYNC_ADMIN_META, $stored );
}

function hpr_force_sync_admin_run_publication( int $post_id, int $publication_id, string $mode = 'force' ): array {
    $post = get_post( $post_id );
    if ( ! $post || 'post' !== $post->post_type ) {
        return [ 'ok' => false, 'message' => 'Invalid press release post.' ];
    }

    $publication = null;
    foreach ( hpr_force_sync_admin_get_publications() as $candidate ) {
        if ( (int) $candidate['id'] === $publication_id ) {
            $publication = $candidate;
            break;
        }
    }

    if ( ! $publication ) {
        return [ 'ok' => false, 'message' => 'Invalid publication.' ];
    }

    $mode        = 'check' === $mode ? 'check' : 'force';
    $live_url    = hpr_force_sync_admin_build_live_url( $publication, $post );
    $endpoint_ok = true;
    $endpoint    = null;
    $http_code   = null;
    $payload     = null;
    $error       = '';

    if ( 'force' === $mode ) {
        $endpoint = hpr_force_sync_admin_build_endpoint( $publication, $post );
        $response = wp_remote_get(
            $endpoint,
            [
                'timeout'     => 180,
                'redirection' => 5,
                'sslverify'   => false,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'HexaPRWireForceSync/1.0',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            $endpoint_ok = false;
            $error       = $response->get_error_message();
        } else {
            $http_code = (int) wp_remote_retrieve_response_code( $response );
            $payload   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( 200 !== $http_code || ! is_array( $payload ) || empty( $payload['success'] ) ) {
                $endpoint_ok = false;
                $error       = is_array( $payload ) && ! empty( $payload['message'] ) ? (string) $payload['message'] : 'Distributor endpoint failed.';
            }
        }
    }

    $live_check = hpr_force_sync_admin_check_live_url( $live_url, get_the_title( $post ) );
    $summary    = [
        'time_gmt'       => current_time( 'mysql', true ),
        'mode'           => $mode,
        'post_id'        => $post_id,
        'post_title'     => get_the_title( $post ),
        'source_url'     => get_permalink( $post ),
        'publication_id' => $publication_id,
        'publication'    => $publication['title'],
        'domain'         => $publication['domain'],
        'live_url'       => $live_url,
        'endpoint_http'  => $http_code,
        'endpoint_ok'    => $endpoint_ok,
        'endpoint'       => $endpoint ? remove_query_arg( 'key', $endpoint ) : '',
        'matched'        => is_array( $payload ) ? (int) ( $payload['matched_feed_items'] ?? 0 ) : null,
        'new_count'      => is_array( $payload ) && isset( $payload['result']['new_live_urls'] ) ? count( (array) $payload['result']['new_live_urls'] ) : 0,
        'updated_count'  => is_array( $payload ) && isset( $payload['result']['updated_live_urls'] ) ? count( (array) $payload['result']['updated_live_urls'] ) : 0,
        'missing_count'  => is_array( $payload ) && isset( $payload['result']['missing_targets'] ) ? count( (array) $payload['result']['missing_targets'] ) : 0,
        'redirects_disabled' => is_array( $payload ) && isset( $payload['redirect_cleanup']['disabled'] ) ? (int) $payload['redirect_cleanup']['disabled'] : 0,
        'public_status'  => (int) $live_check['status_code'],
        'public_ok'      => (bool) $live_check['ok'],
        'title_found'    => (bool) $live_check['title_found'],
        'ok'             => $endpoint_ok && (bool) $live_check['ok'],
        'message'        => $endpoint_ok ? (string) $live_check['message'] : $error,
    ];

    hpr_force_sync_admin_store_result( $post_id, $publication_id, $summary );
    hpr_force_sync_admin_log_result( $summary );

    return $summary;
}

function hpr_force_sync_admin_ajax_publication(): void {
    check_ajax_referer( Config::AJAX_NONCE, 'nonce' );

    $post_id        = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    $publication_id = isset( $_POST['publication_id'] ) ? (int) $_POST['publication_id'] : 0;
    $mode           = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'force';

    if ( ! current_user_can( 'edit_post', $post_id ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
    }

    $result = hpr_force_sync_admin_run_publication( $post_id, $publication_id, $mode );
    if ( empty( $result['ok'] ) ) {
        wp_send_json_error( $result );
    }

    wp_send_json_success( $result );
}

function hpr_force_sync_admin_status_label( ?array $result = null ): string {
    if ( empty( $result ) ) {
        return 'Not checked';
    }

    return sprintf(
        '%s - %s GMT - HTTP %s - %s',
        ! empty( $result['ok'] ) ? 'OK' : 'Issue',
        esc_html( (string) ( $result['time_gmt'] ?? '' ) ),
        esc_html( (string) ( $result['public_status'] ?? '' ) ),
        esc_html( (string) ( $result['message'] ?? '' ) )
    );
}

function hpr_force_sync_admin_render_publication_rows( int $post_id ): void {
    $post         = get_post( $post_id );
    $publications = hpr_force_sync_admin_get_publications();
    $stored       = get_post_meta( $post_id, HPR_FORCE_SYNC_ADMIN_META, true );
    $stored       = is_array( $stored ) ? $stored : [];

    foreach ( $publications as $publication ) {
        $publication_id = (int) $publication['id'];
        $live_url       = $post ? hpr_force_sync_admin_build_live_url( $publication, $post ) : '';
        $result         = isset( $stored[ $publication_id ] ) && is_array( $stored[ $publication_id ] ) ? $stored[ $publication_id ] : null;
        ?>
        <tr data-hpr-fs-row data-publication-id="<?php echo esc_attr( $publication_id ); ?>">
            <td><input type="checkbox" class="hpr-fs-publication-check" value="<?php echo esc_attr( $publication_id ); ?>" checked></td>
            <td><strong><?php echo esc_html( $publication['title'] ); ?></strong><br><code><?php echo esc_html( $publication['domain'] ); ?></code></td>
            <td><a href="<?php echo esc_url( $live_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $live_url ); ?></a></td>
            <td class="hpr-fs-row-status"><?php echo esc_html( hpr_force_sync_admin_status_label( $result ) ); ?></td>
        </tr>
        <?php
    }
}

function hpr_force_sync_admin_render_metabox( \WP_Post $post ): void {
    if ( ! hpr_force_sync_admin_enabled() ) {
        echo '<p>Hexa PR Wire force sync is not available on this site.</p>';
        return;
    }

    $nonce = wp_create_nonce( Config::AJAX_NONCE );
    ?>
    <div class="hpr-fs-panel" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <p>Force selected publication sites to pull this press release through their Hexa PR Wire Distributor endpoint, then verify the public URL contains this post title.</p>
        <p>
            <button type="button" class="button button-primary hpr-fs-run" data-mode="force">Force selected links</button>
            <button type="button" class="button hpr-fs-run" data-mode="check">Check selected links only</button>
            <button type="button" class="button hpr-fs-select-all">Select all</button>
            <button type="button" class="button hpr-fs-select-none">Select none</button>
            <span class="hpr-fs-progress" style="margin-left:10px;"></span>
        </p>
        <table class="widefat striped hpr-fs-publication-table">
            <thead><tr><th style="width:40px;"></th><th style="width:220px;">Publication</th><th>Expected URL</th><th style="width:320px;">Status</th></tr></thead>
            <tbody><?php hpr_force_sync_admin_render_publication_rows( $post->ID ); ?></tbody>
        </table>
    </div>
    <?php hpr_force_sync_admin_print_assets(); ?>
    <?php
}

function hpr_force_sync_admin_print_assets(): void {
    static $printed = false;
    if ( $printed ) {
        return;
    }
    $printed = true;
    ?>
    <style>
        .hpr-fs-panel .hpr-fs-row-status { font-size: 12px; }
        .hpr-fs-panel tr.hpr-fs-ok .hpr-fs-row-status { color: #008a20; font-weight: 600; }
        .hpr-fs-panel tr.hpr-fs-error .hpr-fs-row-status { color: #b32d2e; font-weight: 600; }
        .hpr-fs-panel tr.hpr-fs-running .hpr-fs-row-status { color: #996800; font-weight: 600; }
    </style>
    <script>
    jQuery(function($) {
        window.hprForceSyncStatusText = function(data) {
            if (!data) return 'No response data.';
            var parts = [];
            parts.push(data.ok ? 'OK' : 'Issue');
            if (data.mode) parts.push('mode=' + data.mode);
            if (data.matched !== null && typeof data.matched !== 'undefined') parts.push('matched=' + data.matched);
            if (data.new_count) parts.push('new=' + data.new_count);
            if (data.updated_count) parts.push('updated=' + data.updated_count);
            if (data.redirects_disabled) parts.push('redirects disabled=' + data.redirects_disabled);
            if (data.public_status) parts.push('public HTTP=' + data.public_status);
            if (data.message) parts.push(data.message);
            return parts.join(' | ');
        };

        window.hprForceSyncRunRow = function($panel, $row, mode) {
            var postId = $panel.data('post-id');
            var nonce = $panel.data('nonce') || window.hprNonce;
            var publicationId = $row.data('publication-id');
            $row.removeClass('hpr-fs-ok hpr-fs-error').addClass('hpr-fs-running');
            $row.find('.hpr-fs-row-status').text('Running...');

            return $.post(ajaxurl, {
                action: 'hpr_force_sync_publication',
                nonce: nonce,
                post_id: postId,
                publication_id: publicationId,
                mode: mode
            }).done(function(response) {
                var data = response && response.data ? response.data : {};
                if (response && response.success) {
                    $row.removeClass('hpr-fs-running hpr-fs-error').addClass('hpr-fs-ok');
                } else {
                    $row.removeClass('hpr-fs-running hpr-fs-ok').addClass('hpr-fs-error');
                }
                $row.find('.hpr-fs-row-status').text(window.hprForceSyncStatusText(data));
            }).fail(function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'AJAX request failed.';
                $row.removeClass('hpr-fs-running hpr-fs-ok').addClass('hpr-fs-error');
                $row.find('.hpr-fs-row-status').text(message);
            });
        };

        function runPanel($panel, mode) {
            var $rows = $panel.find('[data-hpr-fs-row]').filter(function() {
                return $(this).find('.hpr-fs-publication-check').is(':checked');
            });
            var total = $rows.length;
            var index = 0;
            var $progress = $panel.find('.hpr-fs-progress');

            if (!total) {
                $progress.text('No rows selected.');
                return;
            }

            function next() {
                if (index >= total) {
                    $progress.text('Complete: ' + total + '/' + total);
                    return;
                }

                var $row = $rows.eq(index);
                $progress.text('Running ' + (index + 1) + '/' + total + '...');
                window.hprForceSyncRunRow($panel, $row, mode).always(function() {
                    index++;
                    next();
                });
            }

            next();
        }

        $(document).on('click', '.hpr-fs-run', function() {
            runPanel($(this).closest('.hpr-fs-panel'), $(this).data('mode') || 'force');
        });

        $(document).on('click', '.hpr-fs-select-all', function() {
            $(this).closest('.hpr-fs-panel').find('.hpr-fs-publication-check').prop('checked', true);
        });

        $(document).on('click', '.hpr-fs-select-none', function() {
            $(this).closest('.hpr-fs-panel').find('.hpr-fs-publication-check').prop('checked', false);
        });
    });
    </script>
    <?php
}

function display_settings_force_sync(): void {
    if ( ! hpr_force_sync_admin_enabled() ) {
        echo '<div class="hpr-panel"><div class="hpr-panel-body"><p>Hexa PR Wire force sync is only available on the source site where the <code>publication</code> post type exists.</p></div></div>';
        return;
    }

    $posts        = hpr_force_sync_admin_get_recent_posts( 30 );
    $publications = hpr_force_sync_admin_get_publications();
    $logs         = get_option( HPR_FORCE_SYNC_ADMIN_LOG_OPTION, [] );
    $logs         = is_array( $logs ) ? array_slice( $logs, 0, 25 ) : [];
    ?>
    <div class="hpr-panel">
        <div class="hpr-panel-header">Hexa PR Wire Force Sync</div>
        <div class="hpr-panel-body">
            <p>This panel belongs to the Hexa PR Wire plugin. It calls each publication site's Distributor force-sync endpoint and then verifies the public URL.</p>
            <div style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px 14px;margin:0 0 18px;">
                <p><strong>How this works:</strong> each selected publication receives a server-side request to <code>/wp-json/hpr-distributor/v1/force-sync</code> with the current Hexa PR Wire post slug and <code>feed_action=force</code>. The request key is injected server-side and intentionally hidden from this screen.</p>
                <p><strong>Returned data tracked here:</strong> endpoint HTTP status, matched feed item count, new URL count, updated URL count, missing target count, Rank Math redirects disabled, public URL HTTP status, whether the public page contains the post title, and the final success message.</p>
            </div>
            <div class="hpr-fs-panel" data-post-id="" data-nonce="<?php echo esc_attr( wp_create_nonce( Config::AJAX_NONCE ) ); ?>">
                <h3>Recent Press Releases</h3>
                <p>
                    <button type="button" class="button hpr-fs-master-select-posts">Select all posts</button>
                    <button type="button" class="button hpr-fs-master-clear-posts">Clear posts</button>
                </p>
                <div style="max-height:220px;overflow:auto;border:1px solid #ccd0d4;padding:10px;background:#fff;">
                    <?php if ( empty( $posts ) ) : ?>
                        <p>No recent press releases with <code>link_output</code> found.</p>
                    <?php else : ?>
                        <?php foreach ( $posts as $post ) : ?>
                            <label style="display:block;margin:0 0 6px;">
                                <input type="checkbox" class="hpr-fs-master-post" value="<?php echo esc_attr( $post->ID ); ?>">
                                <strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
                                <code><?php echo esc_html( $post->post_name ); ?></code>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h3>Publication Checklist</h3>
                <p>
                    <button type="button" class="button hpr-fs-select-all">Select all publications</button>
                    <button type="button" class="button hpr-fs-select-none">Clear publications</button>
                </p>
                <table class="widefat striped hpr-fs-publication-table">
                    <thead><tr><th style="width:40px;"></th><th>Publication</th><th>Domain</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ( $publications as $publication ) : ?>
                            <tr data-hpr-fs-row data-publication-id="<?php echo esc_attr( $publication['id'] ); ?>">
                                <td><input type="checkbox" class="hpr-fs-publication-check" value="<?php echo esc_attr( $publication['id'] ); ?>" checked></td>
                                <td><strong><?php echo esc_html( $publication['title'] ); ?></strong></td>
                                <td><code><?php echo esc_html( $publication['domain'] ); ?></code></td>
                                <td class="hpr-fs-row-status">Waiting</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:15px;">
                    <button type="button" class="button button-primary hpr-fs-master-run" data-mode="force">Force selected posts to selected publications</button>
                    <button type="button" class="button hpr-fs-master-run" data-mode="check">Check selected URLs only</button>
                    <span class="hpr-fs-progress" style="margin-left:10px;"></span>
                </p>
                <table class="widefat striped" id="hpr-fs-master-log">
                    <thead><tr><th>Time</th><th>Post</th><th>Publication</th><th>Result</th><th>URL</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="hpr-panel">
        <div class="hpr-panel-header">Recent Force Sync Log</div>
        <div class="hpr-panel-body">
            <table class="widefat striped">
                <thead><tr><th>Time GMT</th><th>Post</th><th>Publication</th><th>Result</th><th>Live URL</th></tr></thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="5">No force sync runs logged yet.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( (string) ( $entry['time_gmt'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $entry['post_title'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $entry['publication'] ?? '' ) ); ?></td>
                                <td><?php echo ! empty( $entry['ok'] ) ? '<span class="status-ok">OK</span>' : '<span class="status-bad">Issue</span>'; ?> <?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
                                <td><a href="<?php echo esc_url( (string) ( $entry['live_url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $entry['live_url'] ?? '' ) ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    jQuery(function($) {
        $('.hpr-fs-master-select-posts').on('click', function() {
            $('.hpr-fs-master-post').prop('checked', true);
        });
        $('.hpr-fs-master-clear-posts').on('click', function() {
            $('.hpr-fs-master-post').prop('checked', false);
        });
        $('.hpr-fs-master-run').on('click', function() {
            var mode = $(this).data('mode') || 'force';
            var $panel = $(this).closest('.hpr-fs-panel');
            var postIds = $('.hpr-fs-master-post:checked').map(function() { return $(this).val(); }).get();
            var $pubRows = $panel.find('[data-hpr-fs-row]').filter(function() {
                return $(this).find('.hpr-fs-publication-check').is(':checked');
            });
            var tasks = [];
            postIds.forEach(function(postId) {
                $pubRows.each(function() {
                    tasks.push({ postId: postId, row: $(this) });
                });
            });

            var total = tasks.length;
            var index = 0;
            var $progress = $panel.find('.hpr-fs-progress');
            var $log = $('#hpr-fs-master-log tbody');

            if (!total) {
                $progress.text('Select at least one post and one publication.');
                return;
            }

            function addTextCell($row, text) {
                $('<td>').text(text || '').appendTo($row);
            }

            function addLog(data) {
                data = data || {};
                var $row = $('<tr>');
                addTextCell($row, data.time_gmt);
                addTextCell($row, data.post_title);
                addTextCell($row, data.publication);
                addTextCell($row, (data.ok ? 'OK' : 'Issue') + ' - ' + (data.message || ''));
                $('<td>').append($('<a>').attr({ href: data.live_url || '#', target: '_blank', rel: 'noopener noreferrer' }).text(data.live_url || '')).appendTo($row);
                $log.prepend($row);
            }

            function runNext() {
                if (index >= total) {
                    $progress.text('Complete: ' + total + '/' + total);
                    return;
                }

                var task = tasks[index];
                $panel.attr('data-post-id', task.postId).data('post-id', task.postId);
                $progress.text('Running ' + (index + 1) + '/' + total + '...');
                window.hprForceSyncRunRow($panel, task.row, mode).always(function(xhr) {
                    var response = xhr && xhr.responseJSON ? xhr.responseJSON : xhr;
                    if (response && response.data) {
                        addLog(response.data);
                    }
                    index++;
                    runNext();
                });
            }

            runNext();
        });
    });
    </script>
    <?php hpr_force_sync_admin_print_assets(); ?>
    <?php
}
