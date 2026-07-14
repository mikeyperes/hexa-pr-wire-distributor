<?php

require_once __DIR__ . "/TestCase.php";

use hpr_distributor\Tests\TestCase;

$root = dirname( __DIR__ );
$root_php = glob( $root . "/*.php" );
TestCase::true( is_array( $root_php ), "The root PHP inventory must be readable." );
TestCase::true( count( $root_php ) <= 25, "The refactor must not increase the flat root PHP surface." );

$removed = [
    "register-acf-fields.php",
    "settings-action-create-hexa-pr-wire-user.php",
    "settings-dashboard-plugin-checks.php",
    "settings-system-checks.php",
];
foreach ( $removed as $file ) {
    TestCase::false( file_exists( $root . "/" . $file ), "Dead file must remain removed: " . $file );
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root . "/src", FilesystemIterator::SKIP_DOTS )
);
$src_files = [];
foreach ( $iterator as $file ) {
    if ( $file->isFile() && "php" === $file->getExtension() ) {
        $src_files[] = $file->getPathname();
    }
}
sort( $src_files );
TestCase::true( count( $src_files ) >= 7, "The namespaced module tree must contain the extracted services." );

foreach ( $src_files as $path ) {
    $source = (string) file_get_contents( $path );
    $relative = substr( $path, strlen( $root . "/src/" ) );
    $directory = dirname( $relative );
    $expected_namespace = "." === $directory
        ? "hpr_distributor"
        : "hpr_distributor\\" . str_replace( "/", "\\", $directory );

    TestCase::true(
        str_contains( $source, "namespace " . $expected_namespace . ";" ),
        "Namespace must match the src path: " . $relative
    );
    TestCase::true(
        (bool) preg_match( "/^final class /m", $source ),
        "Each extracted module must expose one final class: " . $relative
    );
    TestCase::false(
        (bool) preg_match( "/^function\s+[a-zA-Z_]/m", $source ),
        "Extracted modules must not add procedural functions: " . $relative
    );
}

$main = (string) file_get_contents( $root . "/hexa-pr-wire-distributor.php" );
$legacy = (string) file_get_contents( $root . "/initialization.php" );
$readme = (string) file_get_contents( $root . "/README.md" );
$events = (string) file_get_contents( $root . "/settings-event-handling.php" );
$dashboard = (string) file_get_contents( $root . "/settings-dashboard.php" );
$going_live = (string) file_get_contents( $root . "/src/Admin/GoingLiveTab.php" );
$author = (string) file_get_contents( $root . "/src/Setup/HexaPrWireAuthor.php" );

TestCase::true( str_contains( $main, "* Version: 2.5.4" ), "Main plugin header must be 2.5.4." );
TestCase::true( str_contains( $main, "plugin_version        = '2.5.4'" ), "Runtime version must be 2.5.4." );
TestCase::true( str_contains( $legacy, "* Version: 2.5.4" ), "Legacy bootstrap version must match." );
TestCase::true( str_contains( $readme, "## 2.5.4" ), "README must document the release." );
TestCase::true( str_contains( $main, "spl_autoload_register" ), "The plugin must register its class autoloader." );
TestCase::true( str_contains( $main, "Plugin::boot();" ), "The composition root must boot the modules." );
TestCase::true( str_contains( $going_live, "secret_token" ), "Going Live must inspect the stored Force Sync secret token." );
TestCase::true( str_contains( $going_live, "ExternalImageSizing::filter_metadata" ), "Going Live must invoke the external image metadata repair path." );
TestCase::false( str_contains( $going_live, "shared_token" ), "Going Live must not inspect a nonexistent shared token key." );
TestCase::true(
    str_contains( $author, '"post_author" => $user_id' ),
    "Canonical avatar media must be reassigned to the canonical author."
);
TestCase::true(
    str_contains( $author, '"avatar_owned"' ),
    "Going Live must verify canonical avatar ownership."
);
TestCase::true(
    str_contains( $author, '$attachment_id = $current_id;' ),
    "Existing custom avatars must pass through ownership normalization."
);
TestCase::true(
    str_contains( $author, 'is_file( $file )' ),
    "Avatar readiness must verify that the physical media file exists."
);
TestCase::true(
    str_contains( $author, '"posts_per_page" => -1' ),
    "Avatar source lookup must skip unusable historical attachments."
);

foreach (
    [
        "wp_ajax_hpr_distributor_execute_function",
        "wp_ajax_hpr_distributor_modify_wp_config",
        "hws_ct_force_update_check",
        "ajax_create_user",
    ] as $forbidden
) {
    TestCase::false(
        str_contains( $main . $events . $dashboard, $forbidden ),
        "Removed generic endpoint must not return: " . $forbidden
    );
}

foreach ( [ "Overview", "Going Live", "Import & Sync", "Content Rules", "Editor UI", "Diagnostics" ] as $label ) {
    TestCase::true( str_contains( $dashboard, $label ), "Dashboard route label missing: " . $label );
}

TestCase::true(
    file_exists( $root . "/assets/admin/fifu-postbox-toggle.js" )
        && file_exists( $root . "/assets/admin/fifu-postbox-toggle.css" ),
    "FIFU behavior must use separate assets."
);
TestCase::true(
    file_exists( $root . "/docs/ARCHITECTURE-AUDIT.md" ),
    "The staged architecture audit must ship with the release."
);

$reference = json_decode(
    (string) file_get_contents( $root . "/docs/reference/herforward-echo-rss-contract.json" ),
    true
);
TestCase::true( is_array( $reference ), "The HerForward reference export must be valid JSON." );
TestCase::same( "press-release", $reference["post_type"] ?? "", "The reference importer must target press-release." );
TestCase::same( "1", $reference["update_existing"] ?? "", "The reference importer must update existing posts." );
TestCase::same( "1", $reference["copy_slug"] ?? "", "The reference importer must copy source slugs." );

echo "PASS architecture (" . TestCase::count() . " assertions)\n";
