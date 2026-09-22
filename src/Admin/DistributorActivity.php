<?php

namespace hpr_distributor\Admin;

use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogger;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;

defined( 'ABSPATH' ) || exit;

final class DistributorActivity {
    public static function record( string $message, array $context = [], string $level = 'info', string $source = 'Distributor admin' ): void {
        self::logger()->add(
            new ActivityLogEntry(
                $message,
                $context,
                self::actor(),
                $source,
                gmdate( 'c' ),
                $level
            )
        );
    }

    public static function render(): void {
        $config = self::config();
        ( new ActivityLogRenderer( $config ) )->render( self::logger()->all() );
    }

    private static function logger(): ActivityLogger {
        return new ActivityLogger( self::config() );
    }

    private static function config(): ActivityLogConfig {
        return new ActivityLogConfig(
            [
                'id'          => 'hpr-distributor-activity',
                'title'       => 'Distributor Activity',
                'storage'     => ActivityLogConfig::STORAGE_PERMANENT,
                'storage_key' => 'hpr_distributor_activity_log',
                'max_entries' => 100,
                'collapsed'   => true,
                'dark'        => true,
            ]
        );
    }

    private static function actor(): string {
        $user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
        if ( $user instanceof \WP_User && 0 < (int) $user->ID ) {
            return (string) ( $user->user_login ?: 'user-' . $user->ID );
        }
        return 'system';
    }
}
