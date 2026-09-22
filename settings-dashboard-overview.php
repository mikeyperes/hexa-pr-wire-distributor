<?php

namespace hpr_distributor;

use hpr_distributor\Admin\DashboardData;
use hpr_distributor\Import\NativeFeedSettings;

defined( 'ABSPATH' ) || exit;

function get_publication_slug(): string {
    $slug = (string) NativeFeedSettings::get()['publication_slug'];
    if ( '' !== $slug ) {
        return $slug;
    }
    $host = preg_replace( '/^www\./', '', (string) wp_parse_url( get_site_url(), PHP_URL_HOST ) );
    $parts = explode( '.', $host );
    if ( 1 < count( $parts ) ) {
        array_pop( $parts );
    }
    return sanitize_title( implode( '-', $parts ) );
}

function get_hexa_rss_url(): string {
    $url = (string) NativeFeedSettings::get()['feed_url'];
    return '' !== $url ? $url : 'https://hexaprwire.com/?feed=rss_publication&publication=' . rawurlencode( get_publication_slug() );
}

function check_hexaprwire_user(): array {
    $user = get_user_by( 'slug', 'hexaprwire' );
    return [ 'exists' => false !== $user, 'user' => $user ];
}

function check_press_release_category(): array {
    $category = get_term_by( 'slug', 'press-release', 'category' );
    return [ 'exists' => false !== $category, 'category' => $category ];
}

function check_press_release_cpt(): bool {
    return post_type_exists( 'press-release' );
}

function get_press_release_stats(): array {
    $data = DashboardData::overview();
    return [
        'total'        => (int) $data['counts']['publish'],
        'draft'        => (int) $data['counts']['draft'],
        'remote_stats' => $data['images']['totals'],
    ];
}

function get_cron_status(): array {
    $rows = [];
    foreach ( DashboardData::cron_status() as $cron ) {
        $rows[ $cron['hook'] ] = [
            'name'        => $cron['label'],
            'description' => $cron['hook'],
            'scheduled'   => $cron['scheduled'],
            'next_run'    => $cron['next_run'] ? wp_date( 'Y-m-d H:i:s T', $cron['next_run'] ) : null,
        ];
    }
    return $rows;
}

function display_settings_overview(): void {
    $data = DashboardData::overview();
    $readiness = $data['readiness'];
    $last = $data['last_run'];
    $counts = $data['counts'];
    $images = $data['images']['totals'];
    $duplicate_groups = array_sum( array_map( static fn( array $row ): int => (int) $row['group_count'], $data['duplicates'] ) );
    $settings_url = menu_page_url( Config::$settings_page_slug, false );
    $import_url = add_query_arg( 'tab', 'import-sync', $settings_url );
    $cron_url = add_query_arg( 'tab', 'cron-runs', $settings_url );
    $image_url = add_query_arg( 'tab', 'images', $settings_url );
    $diagnostics_url = add_query_arg( 'tab', 'diagnostics', $settings_url );
    $last_status = (string) ( $last['status'] ?? ( ! empty( $last['success'] ) ? 'success' : 'none' ) );
    ?>
    <div class="hpr-page-head">
        <div><h2>Distributor Overview</h2><p>The most important import, article, image and warning data. Detailed run reports live under Cron &amp; Runs.</p></div>
        <?php echo hpr_status_pill( $readiness['ready'] ? 'Live import ready' : 'Live import paused', $readiness['ready'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <?php if ( ! $readiness['ready'] ) : ?>
        <div class="hpr-notice warning"><strong>Live import paused:</strong> <?php echo esc_html( (string) ( $readiness['errors'][0] ?? 'The importer needs attention.' ) ); ?><details class="hpr-secondary"><summary>All reasons</summary><div class="hpr-secondary-body"><ul class="hpc-list"><?php foreach ( (array) $readiness['errors'] as $error ) : ?><li><?php echo esc_html( (string) $error ); ?></li><?php endforeach; ?></ul></div></details></div>
    <?php endif; ?>

    <div class="hpr-stack">
        <section class="hpc-card hpr-section">
            <h3>At a Glance</h3>
            <div class="hpr-data-list">
                <?php
                echo hpr_data_row( 'Published releases', esc_html( (string) $counts['publish'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Hexa-hosted images', esc_html( (string) $images['allowed_remote'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Duplicate source groups', esc_html( (string) $duplicate_groups ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Next feed offset', esc_html( (string) $data['cursor']['offset'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Last import run', hpr_status_pill( ucfirst( $last_status ), 'success' === $last_status ? 'success' : ( 'partial' === $last_status ? 'warning' : '' ) ), (string) ( $last['ended_gmt'] ?? 'No live run recorded' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </section>

        <section class="hpc-card hpr-section">
            <div class="hpr-page-head"><div><h3>Distribution</h3><p>Source binding and polling status.</p></div><div class="hpr-inline-actions"><a class="hpc-button" href="<?php echo esc_url( $import_url ); ?>">Open Import &amp; Sync</a><a class="hpc-button secondary" href="<?php echo esc_url( $cron_url ); ?>">Open Cron &amp; Runs</a></div></div>
            <div class="hpr-data-list">
                <?php
                echo hpr_data_row( 'Publication', '<code>' . esc_html( (string) $readiness['publication_slug'] ) . '</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Feed', '<a href="' . esc_url( (string) $readiness['feed_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $readiness['feed_url'] ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Polling', $readiness['scheduled_polling']['scheduled'] ? esc_html( (string) $readiness['scheduled_polling']['interval'] ) : 'Not scheduled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Echo RSS required', 'No' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'FIFU required', 'No' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </section>

        <section class="hpc-card hpr-section">
            <div class="hpr-page-head"><div><h3>Images from URL</h3><p>Image-host policy and records.</p></div><a class="hpc-button secondary" href="<?php echo esc_url( $image_url ); ?>">Open Image Tests</a></div>
            <div class="hpr-data-list">
                <?php
                echo hpr_data_row( 'Hexa-hosted', esc_html( (string) $images['allowed_remote'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Other remote hosts', esc_html( (string) $images['other_remote'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Local media', esc_html( (string) $images['local'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'No image', esc_html( (string) $images['no_image'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </section>

        <section class="hpc-card hpr-section">
            <div class="hpr-page-head"><div><h3>Recent Press Releases</h3><p>Each row shows the post, source, and actual external image.</p></div><a class="hpc-button secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=press-release' ) ); ?>">View all</a></div>
            <div class="hpr-record-list">
                <?php if ( [] === $data['recent'] ) : ?><p class="hpr-empty">No press releases found.</p><?php else : foreach ( $data['recent'] as $article ) : ?>
                    <?php
                    $image = (array) $article['image'];
                    $image_url = (string) ( $image['url'] ?? '' );
                    $summary = hpr_status_pill( ucfirst( (string) $article['status'] ), 'publish' === $article['status'] ? 'success' : 'warning' )
                        . ' <a href="' . esc_url( (string) $article['view_url'] ) . '" target="_blank" rel="noopener noreferrer">View post</a> · <a href="' . esc_url( (string) $article['edit_url'] ) . '">Edit</a>';
                    if ( '' !== $image_url ) {
                        $summary .= '<div class="hpr-record-media"><a href="' . esc_url( $image_url ) . '" target="_blank" rel="noopener noreferrer"><img class="hpr-record-thumb" src="' . esc_url( $image_url ) . '" alt="" loading="lazy"></a><div class="hpr-record-meta"><strong>External image</strong><br><a href="' . esc_url( $image_url ) . '" target="_blank" rel="noopener noreferrer">Open image</a><br><code>' . esc_html( (string) ( $image['host'] ?? '' ) ) . '</code></div></div>';
                    }
                    $secondary = '<div class="hpr-data-list">'
                        . hpr_data_row( 'Source', ! empty( $article['source_url'] ) ? '<a href="' . esc_url( (string) $article['source_url'] ) . '" target="_blank" rel="noopener noreferrer">Open Hexa PR Wire source</a>' : 'Missing' )
                        . hpr_data_row( 'Imported', esc_html( (string) ( $article['imported_gmt'] ?: 'Unknown' ) ) )
                        . '</div>';
                    echo hpr_record_row( (string) $article['title'], $summary, '', $secondary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <section class="hpc-card hpr-section">
            <div class="hpr-page-head"><div><h3>Warnings</h3><p>Only conditions that can change import behavior or presentation.</p></div><a class="hpc-button secondary" href="<?php echo esc_url( $diagnostics_url ); ?>">Run Diagnostics</a></div>
            <div class="hpr-data-list">
                <?php
                echo hpr_data_row( 'Duplicate source groups', esc_html( (string) $duplicate_groups ), 'Colliding imports stop before overwriting a destination.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Other remote image hosts', esc_html( (string) $images['other_remote'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Local image records', esc_html( (string) $images['local'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hpr_data_row( 'Last import errors', esc_html( (string) ( $last['counts']['failed'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </section>
    </div>
    <?php
}
