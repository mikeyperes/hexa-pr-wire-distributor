<?php

namespace Hexa\PluginCore\PluginUpdates;

final class UpdaterFilesystem {
    public static function delete_directory( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = array_diff( (array) scandir( $dir ), [ '.', '..' ] );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            is_dir( $path ) ? self::delete_directory( $path ) : @unlink( $path );
        }

        @rmdir( $dir );
    }

    public static function add_folder_to_zip( \ZipArchive $zip, string $folder, string $base_path ): void {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $folder ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                continue;
            }

            $file_path     = $file->getRealPath();
            $relative_path = $base_path . '/' . substr( $file_path, strlen( $folder ) + 1 );
            $zip->addFile( $file_path, $relative_path );
        }
    }
}
