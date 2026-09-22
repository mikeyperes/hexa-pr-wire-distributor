<?php

namespace hpr_distributor\Diagnostics;

use hpr_distributor\Admin\DashboardData;
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Migration\LegacyDependencyRetirement;

defined( 'ABSPATH' ) || exit;

final class DistributorDiagnostics {
    public static function run( bool $include_remote = false ): array {
        $settings = NativeFeedSettings::get();
        $readiness = NativeFeedSettings::readiness();
        $legacy = LegacyDependencyRetirement::state();
        $duplicates = DashboardData::duplicate_report();
        $images = DashboardData::image_inventory( 0 );
        $acf = DashboardData::acf_report();
        $checks = [];

        self::add( $checks, 'native-settings', 'Native importer settings', (bool) NativeFeedSettings::validate( $settings )['valid'], implode( ' ', (array) NativeFeedSettings::validate( $settings )['errors'] ) ?: 'Feed URL, publication and import settings are valid.' );
        self::add( $checks, 'native-enabled', 'Native importer enabled', (bool) $settings['enabled'], $settings['enabled'] ? 'Native importing is enabled.' : 'Native importing is disabled.' );
        self::add( $checks, 'cron', 'Native polling cron', (bool) $readiness['schedule_ready'], $readiness['schedule_ready'] ? 'The configured polling schedule is registered.' : 'The polling schedule is missing or uses the wrong interval.' );
        self::add( $checks, 'force-sync-route', 'Force Pull REST route', self::force_sync_route_registered(), 'Expected POST route: /hpr-distributor/v1/force-sync.' );
        self::add( $checks, 'legacy-conflicts', 'Echo RSS and FIFU isolation', (bool) $legacy['ready'], $legacy['ready'] ? 'No matching Echo job or FIFU background process can compete with the native importer.' : implode( ' ', (array) $legacy['conflicts'] ) );

        $collision_groups = 0;
        $collision_rows = 0;
        foreach ( $duplicates as $report ) {
            $collision_groups += (int) $report['group_count'];
            $collision_rows += (int) $report['affected_rows'];
        }
        self::add( $checks, 'source-collisions', 'Source identity collisions', 0 === $collision_groups, 0 === $collision_groups ? 'No duplicate canonical URL, source ID or source identity groups were found.' : sprintf( '%d duplicate groups affect %d rows. Importing those identities fails closed.', $collision_groups, $collision_rows ) );

        $image_totals = $images['totals'];
        $image_ready = 0 === (int) $image_totals['other_remote'];
        self::add( $checks, 'images', 'Remote image host policy', $image_ready, sprintf( '%d Hexa-hosted, %d other remote, %d local, %d without images.', $image_totals['allowed_remote'], $image_totals['other_remote'], $image_totals['local'], $image_totals['no_image'] ) );

        $acf_ready = (bool) $acf['acf_active'];
        foreach ( $acf['groups'] as $group ) {
            $acf_ready = $acf_ready && ! empty( $group['registered'] ) && ! empty( $group['active'] );
        }
        self::add( $checks, 'acf', 'ACF field groups', $acf_ready, $acf_ready ? 'Source Metadata and SEO Overrides are active and registered.' : 'ACF Pro or one of the Distributor field groups is unavailable.' );

        $core_version = defined( 'HEXA_PLUGIN_CORE_SELECTED_VERSION' )
            ? (string) HEXA_PLUGIN_CORE_SELECTED_VERSION
            : self::bundled_core_version();
        $core_ready = 'Unknown' !== $core_version && version_compare( $core_version, '1.0.0', '>=' );
        self::add( $checks, 'core', 'Hexa WP Core compatibility', $core_ready, 'Selected Core version: ' . $core_version . '; minimum supported: 1.0.0.' );

        $last = DashboardData::last_run();
        $last_size = strlen( maybe_serialize( $last ) );
        self::add( $checks, 'run-storage', 'Bounded run storage', $last_size < 131072, sprintf( 'The current last-run record is %.1f KB; new runs are compacted and bounded.', $last_size / 1024 ) );

        if ( $include_remote ) {
            try {
                $feed_url = NativeFeedImporter::effective_feed_url( (string) $settings['feed_url'], '', true );
                $items = NativeFeedImporter::fetch_items( $feed_url, (string) $settings['allowed_host'] );
                self::add( $checks, 'feed-live', 'Live feed and XML', 0 < count( $items ), sprintf( 'HTTP/XML test passed and returned %d valid publication items.', count( $items ) ) );
            } catch ( \Throwable $throwable ) {
                self::add( $checks, 'feed-live', 'Live feed and XML', false, $throwable->getMessage() );
            }
        }

        $passed = count( array_filter( $checks, static fn( array $check ): bool => (bool) $check['success'] ) );
        return [
            'success'       => $passed === count( $checks ),
            'passed'        => $passed,
            'failed'        => count( $checks ) - $passed,
            'checks'        => $checks,
            'generated_gmt' => current_time( 'mysql', true ),
        ];
    }

    private static function force_sync_route_registered(): bool {
        if ( function_exists( 'has_action' ) && false !== has_action( 'rest_api_init', 'hpr_distributor\\hpr_force_sync_register_rest_routes' ) ) {
            return true;
        }
        if ( ! function_exists( 'rest_get_server' ) ) {
            return false;
        }
        $routes = rest_get_server()->get_routes();
        return isset( $routes['/hpr-distributor/v1/force-sync'] );
    }

    private static function bundled_core_version(): string {
        $path = dirname( __DIR__, 2 ) . '/lib/hexa-wordpress-plugin-core/VERSION';
        return is_readable( $path ) ? trim( (string) file_get_contents( $path ) ) : 'Unknown';
    }

    private static function add( array &$checks, string $id, string $label, bool $success, string $detail ): void {
        $checks[] = [
            'id'      => $id,
            'label'   => $label,
            'success' => $success,
            'detail'  => $detail,
        ];
    }
}
