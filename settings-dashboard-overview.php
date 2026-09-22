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
    $image_url = add_query_arg( 'tab', 'images', $settings_url );
    $diagnostics_url = add_query_arg( 'tab', 'diagnostics', $settings_url );
    ?>
    <div class="hpr-page-head">
        <div>
            <h2>Distributor Overview</h2>
            <p>Native Hexa PR Wire importing, remote images, recent releases and operational warnings in one place.</p>
        </div>
        <?php echo hpr_status_pill( $readiness['ready'] ? 'Ready' : 'Needs attention', $readiness['ready'] ? 'success' : 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <?php if ( ! $readiness['ready'] ) : ?>
        <div class="hpr-notice danger"><strong>Native importing is blocked.</strong> <?php echo esc_html( implode( ' ', (array) $readiness['errors'] ) ); ?></div>
    <?php endif; ?>

    <div class="hpr-metric-grid">
        <div class="hpr-metric"><strong><?php echo (int) $counts['publish']; ?></strong><span>Published releases</span></div>
        <div class="hpr-metric"><strong><?php echo (int) $images['allowed_remote']; ?></strong><span>Hexa-hosted images</span></div>
        <div class="hpr-metric"><strong><?php echo (int) $duplicate_groups; ?></strong><span>Duplicate groups</span></div>
        <div class="hpr-metric"><strong><?php echo esc_html( (string) $data['cursor']['offset'] ); ?></strong><span>Next feed offset</span></div>
        <div class="hpr-metric"><strong><?php echo esc_html( (string) ( $last['status'] ?? ( ! empty( $last['success'] ) ? 'success' : 'none' ) ) ); ?></strong><span>Last run</span></div>
    </div>

    <div class="hpc-grid two">
        <?php
        ob_start();
        ?>
        <table class="hpr-table"><tbody>
            <tr><th>Publication</th><td><code><?php echo esc_html( (string) $readiness['publication_slug'] ); ?></code></td></tr>
            <tr><th>Feed</th><td class="hpr-url"><a href="<?php echo esc_url( (string) $readiness['feed_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $readiness['feed_url'] ); ?></a></td></tr>
            <tr><th>Polling</th><td><?php echo $readiness['scheduled_polling']['scheduled'] ? esc_html( (string) $readiness['scheduled_polling']['interval'] ) : 'Not scheduled'; ?></td></tr>
            <tr><th>Echo RSS required</th><td>No</td></tr>
            <tr><th>FIFU required</th><td>No</td></tr>
        </tbody></table>
        <div class="hpr-button-row"><a class="hpc-button" href="<?php echo esc_url( $import_url ); ?>">Open Import &amp; Sync</a></div>
        <?php
        echo hpr_card( 'Distribution', (string) ob_get_clean(), hpr_status_pill( $readiness['ready'] ? 'Operational' : 'Blocked', $readiness['ready'] ? 'success' : 'danger' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        ob_start();
        ?>
        <table class="hpr-table"><tbody>
            <tr><th>Hexa-hosted</th><td><?php echo (int) $images['allowed_remote']; ?></td></tr>
            <tr><th>Other remote hosts</th><td><?php echo (int) $images['other_remote']; ?></td></tr>
            <tr><th>Local media</th><td><?php echo (int) $images['local']; ?></td></tr>
            <tr><th>No image</th><td><?php echo (int) $images['no_image']; ?></td></tr>
        </tbody></table>
        <div class="hpr-button-row"><a class="hpc-button secondary" href="<?php echo esc_url( $image_url ); ?>">Open Image Tests</a></div>
        <?php
        $image_ok = 0 === (int) $images['other_remote'];
        echo hpr_card( 'Images from URL', (string) ob_get_clean(), hpr_status_pill( $image_ok ? 'Host policy clear' : 'Review hosts', $image_ok ? 'success' : 'warning' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>

    <section class="hpc-card hpr-section">
        <div class="hpr-page-head"><div><h3>Recent Press Releases</h3><p>Latest destination articles with their source and image-host evidence.</p></div><a class="hpc-button secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=press-release' ) ); ?>">View all</a></div>
        <div class="hpr-table-wrap"><table class="hpr-table">
            <thead><tr><th>Article</th><th>Status</th><th>Source</th><th>Image host</th><th>Imported</th></tr></thead>
            <tbody>
            <?php if ( [] === $data['recent'] ) : ?>
                <tr><td colspan="5">No press releases found.</td></tr>
            <?php else : foreach ( $data['recent'] as $article ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $article['title'] ); ?></strong><br><a href="<?php echo esc_url( $article['view_url'] ); ?>" target="_blank" rel="noopener noreferrer">View</a> · <a href="<?php echo esc_url( $article['edit_url'] ); ?>">Edit</a></td>
                    <td><?php echo hpr_status_pill( ucfirst( $article['status'] ), 'publish' === $article['status'] ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td class="hpr-url"><?php if ( $article['source_url'] ) : ?><a href="<?php echo esc_url( $article['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">Source</a><?php else : ?>Missing<?php endif; ?></td>
                    <td><code><?php echo esc_html( $article['image']['host'] ?: $article['image']['type'] ); ?></code></td>
                    <td><?php echo esc_html( $article['imported_gmt'] ?: 'Unknown' ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="hpc-card hpr-section">
        <div class="hpr-page-head"><div><h3>Warnings</h3><p>Issues that can affect deterministic imports or presentation.</p></div><a class="hpc-button secondary" href="<?php echo esc_url( $diagnostics_url ); ?>">Run Diagnostics</a></div>
        <table class="hpr-table"><tbody>
            <tr><th>Duplicate source groups</th><td><?php echo (int) $duplicate_groups; ?> — collision imports fail closed.</td></tr>
            <tr><th>Other remote image hosts</th><td><?php echo (int) $images['other_remote']; ?></td></tr>
            <tr><th>Local image records</th><td><?php echo (int) $images['local']; ?></td></tr>
            <tr><th>Last import errors</th><td><?php echo (int) ( $last['counts']['failed'] ?? 0 ); ?></td></tr>
        </tbody></table>
    </section>
    <?php
}
