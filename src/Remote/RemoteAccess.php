<?php

namespace hpr_distributor\Remote;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Who may call this outlet's Hexa PR Wire commands.
 *
 * Two independent paths reach the same command handlers:
 * - plugin path: the shared Hexa PR Wire token (`X-HPR-Token` header);
 * - REST path: a WordPress user with manage_options, e.g. through an
 *   Application Password.
 *
 * Every token command only makes this site pull from hexaprwire.com (import,
 * deletions, author profile), so the shared token cannot publish, change or
 * delete anything that hexaprwire.com did not publish.
 */
final class RemoteAccess {
    /** The one token shared by every outlet and by Hexa PR Wire Core. */
    public const SHARED_TOKEN = "16dc68d8a5c6119213e61aac5662d7f5c182aed2f5d329e0c89de18fcf0abe59";

    public const SETTINGS_OPTION = "hpr_force_sync_settings";

    public static function token(): string {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        if ( is_array( $settings ) && "custom" === ( $settings["token_mode"] ?? "" ) && "" !== trim( (string) ( $settings["secret_token"] ?? "" ) ) ) {
            return trim( (string) $settings["secret_token"] );
        }
        return self::SHARED_TOKEN;
    }

    public static function request_token( \WP_REST_Request $request ): string {
        $header = trim( (string) $request->get_header( "x-hpr-token" ) );
        if ( "" !== $header ) {
            return $header;
        }
        foreach ( [ "token", "sync_key", "key" ] as $param ) {
            $value = trim( (string) $request->get_param( $param ) );
            if ( "" !== $value ) {
                return $value;
            }
        }
        return "";
    }

    /** @return true|\WP_Error */
    public static function authorize( \WP_REST_Request $request ) {
        if ( current_user_can( "manage_options" ) ) {
            return true;
        }
        $token = self::request_token( $request );
        if ( "" !== $token && hash_equals( self::token(), $token ) ) {
            return true;
        }
        return new \WP_Error( "hpr_forbidden", "Unauthorized.", [ "status" => 403 ] );
    }

    /** `rest` when an authenticated administrator called, otherwise `plugin`. */
    public static function path(): string {
        return current_user_can( "manage_options" ) ? "rest" : "plugin";
    }
}
