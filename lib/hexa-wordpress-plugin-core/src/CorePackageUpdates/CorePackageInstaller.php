<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use WP_Error;

final class CorePackageInstaller {
    private CorePackageConfig $config;

    public function __construct( CorePackageConfig $config ) {
        $this->config = $config;
    }

    public function run(): array|WP_Error {
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'unzip_file' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'copy_dir' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        $tmp_zip = download_url( $this->config->zip_url(), (int) $this->config->get( 'timeout', 15 ) );
        if ( is_wp_error( $tmp_zip ) ) {
            return $tmp_zip;
        }

        $extract_to = trailingslashit( get_temp_dir() ) . 'hexa-plugin-core-' . wp_generate_uuid4();
        wp_mkdir_p( $extract_to );

        $unzipped = unzip_file( $tmp_zip, $extract_to );
        wp_delete_file( $tmp_zip );

        if ( is_wp_error( $unzipped ) ) {
            return $unzipped;
        }

        $source = $this->locate_source( $extract_to );
        if ( ! $source ) {
            $this->delete_dir( $extract_to );

            return new WP_Error( 'hexa_core_source_missing', 'Downloaded core package did not contain VERSION and src.' );
        }

        $result = copy_dir( trailingslashit( $source ), trailingslashit( $this->config->core_root() ) );
        $this->delete_dir( $extract_to );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        ( new CorePackageVersionClient( $this->config ) )->clear_cache();

        return [
            'message'     => 'Hexa WordPress Plugin Core updated.',
            'new_version' => $this->config->current_version(),
            'core_root'   => $this->config->core_root(),
        ];
    }

    private function locate_source( string $extract_to ): string|false {
        $entries = glob( trailingslashit( $extract_to ) . '*' );
        if ( ! $entries ) {
            return false;
        }

        foreach ( $entries as $entry ) {
            if (
                is_dir( $entry )
                && is_readable( trailingslashit( $entry ) . $this->config->version_file() )
                && is_dir( trailingslashit( $entry ) . 'src' )
            ) {
                return $entry;
            }
        }

        return false;
    }

    private function delete_dir( string $path ): void {
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            $wp_filesystem->delete( $path, true );
        }
    }
}
