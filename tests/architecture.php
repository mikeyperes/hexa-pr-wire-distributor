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
$native_importer = (string) file_get_contents( $root . "/src/Import/NativeFeedImporter.php" );
$legacy_retirement = (string) file_get_contents( $root . "/src/Migration/LegacyDependencyRetirement.php" );
$dashboard_actions = (string) file_get_contents( $root . "/src/Admin/DashboardActions.php" );
$dashboard_components = (string) file_get_contents( $root . "/settings-dashboard-components.php" );
$import_dashboard = (string) file_get_contents( $root . "/settings-dashboard-import-sync.php" );
$images_dashboard = (string) file_get_contents( $root . "/settings-dashboard-images.php" );
$cron_dashboard = (string) file_get_contents( $root . "/src/Admin/CronRunsTab.php" );
$seo_settings = (string) file_get_contents( $root . "/seo-settings.php" );
$seo_status = (string) file_get_contents( $root . "/src/Admin/PressReleaseSeoStatus.php" );

TestCase::true( str_contains( $main, "* Version: 3.3.0" ), "Main plugin header must be 3.3.0." );
TestCase::true( str_contains( $main, "plugin_version        = '3.3.0'" ), "Runtime version must be 3.3.0." );
TestCase::true( str_contains( $legacy, "* Version: 3.3.0" ), "Legacy bootstrap version must match." );
TestCase::true( str_contains( $readme, "## 3.3.0" ), "README must document the release." );
TestCase::true(
    str_contains( $native_importer, "assign_press_release_category( \$post_id )" ),
    "The native importer must assign the destination Press Release category."
);
TestCase::false(
    str_contains( $native_importer, "assign_categories" ),
    "The native importer must not mirror feed categories into the destination taxonomy."
);
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

foreach ( [ "Overview", "Import & Sync", "Cron & Runs", "Images from URL", "Content Model & ACF", "General Settings", "Going Live", "Diagnostics", "Updates & Core" ] as $label ) {
    TestCase::true( str_contains( $dashboard, $label ), "Dashboard route label missing: " . $label );
}

TestCase::false( file_exists( $root . "/assets/admin/fifu-postbox-toggle.js" ), "The obsolete FIFU editor asset must remain removed." );
TestCase::false( file_exists( $root . "/src/Admin/FifuPostboxToggle.php" ), "The obsolete FIFU runtime module must remain removed." );
TestCase::false( file_exists( $root . "/src/Import/EchoRuleContract.php" ), "The obsolete Echo runtime contract must remain removed." );
TestCase::true( file_exists( $root . "/src/Import/NativeFeedImporter.php" ), "The native importer must ship." );
TestCase::true( file_exists( $root . "/src/Import/NativeFeedSettings.php" ), "The native importer settings service must ship." );
TestCase::true( file_exists( $root . "/src/Import/SourceIdentity.php" ), "The source identity service must ship." );
TestCase::true( file_exists( $root . "/src/Api/OnboardingContract.php" ), "The authenticated onboarding contract must ship." );
TestCase::true( file_exists( $root . "/src/Migration/LegacyDependencyRetirement.php" ), "Legacy dependency retirement must ship." );
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
foreach ( [ "Disable Matching Echo Job", "Disable Echo RSS Plugin", "Disable FIFU Plugin" ] as $legacy_action_label ) {
    TestCase::true( str_contains( $going_live, $legacy_action_label ), "Going Live must expose the explicit legacy action: " . $legacy_action_label );
}
TestCase::false( str_contains( $legacy_retirement, "public static function retire" ), "The blanket legacy-retirement operation must remain removed." );
TestCase::true( str_contains( $legacy_retirement, '"automatic_shutdown"          => false' ), "Legacy controls must report that automatic shutdown is disabled." );
TestCase::true( str_contains( $legacy_retirement, 'deactivate_plugins' ), "Explicit plugin-disable actions must use WordPress deactivation." );
TestCase::true( str_contains( $legacy_retirement, 'wp_clear_scheduled_hook' ), "Explicit plugin-disable actions must clear only the selected plugin hooks." );
TestCase::true( str_contains( $native_importer, 'Legacy import conflict:' ), "Native imports must fail closed while a legacy importer can still run." );
TestCase::true( str_contains( $contract, '"accepts_login_secrets" => false' ), "The onboarding contract must reject login-secret ownership." );
TestCase::true( str_contains( $contract, '"/onboarding/rollback"' ), "The onboarding contract must expose operation-scoped rollback." );
TestCase::true( str_contains( $contract, '"/onboarding/force-sync"' ), "The onboarding contract must expose administrator-authenticated Force Sync." );
TestCase::true( str_contains( $contract, '"permission_callback" => [ self::class, "authorize" ]' ), "Onboarding routes must use the manage_options authorization callback." );
TestCase::true( file_exists( $root . "/docs/ONBOARDING-CONTRACT.md" ), "The Publish adapter contract must be documented." );
TestCase::true( str_contains( $dashboard_components, "DynamicNotice" ), "Dashboard save feedback must use the shared Hexa WP Core notice component." );
TestCase::true( str_contains( $import_dashboard, "root.on('click','[data-hpr-save-import]'" ), "The Core dynamic Import save button must dispatch the AJAX form save." );
TestCase::true( str_contains( (string) file_get_contents( $root . "/settings-dashboard-general.php" ), "root.on('click','[data-hpr-save-general]'" ), "The Core dynamic General save button must dispatch the AJAX form save." );
TestCase::true( str_contains( $legacy_retirement, "PluginCheckService::deactivate" ), "Plugin deactivation must use the generic Hexa WP Core service." );
TestCase::true( str_contains( $import_dashboard, "data-hpr-legacy-action" ), "Import & Sync must expose explicit one-click legacy plugin controls." );
TestCase::true( str_contains( $images_dashboard, "data-hpr-disable-fifu" ), "Images from URL must expose a dynamic FIFU control." );
TestCase::true( str_contains( $import_dashboard, "Publication binding" ) && ! str_contains( $import_dashboard, 'name="publication_slug" required' ), "The publication binding must not be an ordinary editable field." );
TestCase::true( str_contains( $dashboard_actions, "\$bound_slug" ) && str_contains( $dashboard_actions, "\$author_warning" ), "Import saves must preserve onboarding binding and warn for a non-default author." );
TestCase::true( str_contains( $dashboard_actions, "'record_history' => false" ) && str_contains( $dashboard_actions, "'dry_run'        => true" ), "The import cron test must be read-only and must not alter run history." );
TestCase::true( str_contains( $cron_dashboard, "Last scheduled attempt" ) && str_contains( $cron_dashboard, "Last scheduled success" ), "Cron & Runs must distinguish the last attempt from the last successful run." );
TestCase::true( str_contains( $cron_dashboard, "Test Import Cron" ) && str_contains( $cron_dashboard, "Test Deletion Cron" ), "Cron & Runs must expose both safe cron tests." );
TestCase::true( str_contains( $images_dashboard, "hpr-record-thumb" ) && str_contains( $images_dashboard, "Open original image" ), "Image records must show an external thumbnail and direct image link." );
TestCase::false( str_contains( $import_dashboard . $images_dashboard . $seo_settings, "<table" ), "Primary Distributor settings must use vertical rows instead of data tables." );
TestCase::false( str_contains( $seo_status, "grid-template-columns:1fr 1fr" ), "The post SEO report must not use a side-by-side column layout." );
TestCase::false( str_contains( $import_dashboard, ">Blocked<" ), "Import readiness must name the paused behavior instead of showing an ambiguous Blocked badge." );

echo "PASS architecture (" . TestCase::count() . " assertions)\n";
