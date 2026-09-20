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
    "force-sync-assets.php",
    "settings-dashboard-ui-cleanup.php",
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

TestCase::true( str_contains( $main, "* Version: 3.1.1" ), "Main plugin header must be 3.1.1." );
TestCase::true( str_contains( $main, "plugin_version        = '3.1.1'" ), "Runtime version must be 3.1.1." );
TestCase::true( str_contains( $legacy, "* Version: 3.1.1" ), "Legacy bootstrap version must match." );
TestCase::true( str_contains( $readme, "## 3.1.1" ), "README must document the release." );
TestCase::true( str_contains( $main, "spl_autoload_register" ), "The plugin must register its class autoloader." );
TestCase::true(
    str_contains( $main, 'add_action( "plugins_loaded", [ Plugin::class, "boot" ], 20 );' ),
    "The composition root must boot modules after the shared Core runtime resolves."
);
TestCase::false( str_contains( $main, "Plugin::boot();" ), "The composition root must not boot Core integrations before plugins_loaded." );
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

foreach ( [ "Overview", "Going Live", "Import & Sync", "Content Rules", "Diagnostics" ] as $label ) {
    TestCase::true( str_contains( $dashboard, $label ), "Dashboard route label missing: " . $label );
}

TestCase::false( file_exists( $root . "/assets/admin/fifu-postbox-toggle.js" ), "The obsolete FIFU editor asset must remain removed." );
TestCase::false( file_exists( $root . "/src/Admin/FifuPostboxToggle.php" ), "The obsolete FIFU runtime module must remain removed." );
TestCase::false( file_exists( $root . "/src/Import/EchoRuleContract.php" ), "The obsolete Echo runtime contract must remain removed." );
TestCase::true( file_exists( $root . "/src/Import/NativeFeedImporter.php" ), "The native importer must ship." );
TestCase::true( file_exists( $root . "/src/Import/NativeFeedSettings.php" ), "The native importer settings service must ship." );
TestCase::true( file_exists( $root . "/src/Import/SourceIdentity.php" ), "The source identity service must ship." );
TestCase::true( file_exists( $root . "/src/Api/OnboardingContract.php" ), "The authenticated onboarding contract must ship." );
TestCase::true(
    file_exists( $root . "/docs/ARCHITECTURE-AUDIT.md" ),
    "The staged architecture audit must ship with the release."
);

$force_sync = (string) file_get_contents( $root . "/force-syndication.php" );
$plugin = (string) file_get_contents( $root . "/src/Plugin.php" );
$contract = (string) file_get_contents( $root . "/src/Api/OnboardingContract.php" );
TestCase::false( str_contains( $force_sync, "echo_run_rule" ), "Force Sync must not call Echo RSS." );
TestCase::false( str_contains( $plugin, "FifuPostboxToggle" ), "The composition root must not boot FIFU behavior." );
TestCase::false( str_contains( $main . $plugin . $going_live, "rss-feed-post-generator-echo" ), "Runtime readiness must not require the Echo RSS plugin." );
TestCase::false( str_contains( $main . $plugin . $going_live, "featured-image-from-url" ), "Runtime readiness must not require FIFU." );
TestCase::true( str_contains( $going_live, '"echo_rss_required" => false' ), "Going Live must report Echo RSS required: no." );
TestCase::true( str_contains( $going_live, '"fifu_required"     => false' ), "Going Live must report FIFU required: no." );
TestCase::true( str_contains( $contract, '"accepts_login_secrets" => false' ), "The onboarding contract must reject login-secret ownership." );
TestCase::true( str_contains( $contract, '"/onboarding/rollback"' ), "The onboarding contract must expose operation-scoped rollback." );
TestCase::true( str_contains( $contract, '"/onboarding/force-sync"' ), "The onboarding contract must expose administrator-authenticated Force Sync." );
TestCase::true( str_contains( $contract, '"permission_callback" => [ self::class, "authorize" ]' ), "Onboarding routes must use the manage_options authorization callback." );
TestCase::true( file_exists( $root . "/docs/ONBOARDING-CONTRACT.md" ), "The Publish adapter contract must be documented." );

echo "PASS architecture (" . TestCase::count() . " assertions)\n";
