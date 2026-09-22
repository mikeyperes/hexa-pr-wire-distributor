<?php

define( "ABSPATH", __DIR__ . "/" );
define( "OBJECT", "OBJECT" );

$GLOBALS["hpr_test_options"] = [];
$GLOBALS["hpr_test_context"] = [];
$GLOBALS["hpr_test_hooks"] = [];
$GLOBALS["hpr_test_active_plugins"] = [];
$GLOBALS["hpr_test_network_plugins"] = [];

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS["hpr_test_hooks"]["action"][ $hook ][] = [ $callback, $priority, $accepted_args ];
}

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS["hpr_test_hooks"]["filter"][ $hook ][] = [ $callback, $priority, $accepted_args ];
}

function add_options_page(): void {}

function apply_filters( string $hook, $value ) {
    return $value;
}

function get_option( string $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS["hpr_test_options"] )
        ? $GLOBALS["hpr_test_options"][ $name ]
        : $default;
}

function update_option( string $name, $value, bool $autoload = false ): bool {
    unset( $autoload );
    $GLOBALS["hpr_test_options"][ $name ] = $value;
    return true;
}

function wp_parse_args( $args, array $defaults = [] ): array {
    return array_merge( $defaults, is_array( $args ) ? $args : [] );
}

function absint( $value ): int {
    return abs( (int) $value );
}

function sanitize_key( string $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) );
}

function sanitize_title( string $value ): string {
    $value = strtolower( wp_strip_all_tags( $value ) );
    return trim( preg_replace( "/[^a-z0-9]+/", "-", $value ), "-" );
}

function sanitize_text_field( $value ): string {
    return trim( wp_strip_all_tags( (string) $value ) );
}

function wp_strip_all_tags( string $value ): string { return trim( strip_tags( $value ) ); }
function wp_kses_post( string $value ): string { return $value; }
function wp_parse_url( string $url, int $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $value ): string { return (string) json_encode( $value ); }
function add_query_arg( array $query, string $url ): string { return $url . ( str_contains( $url, "?" ) ? "&" : "?" ) . http_build_query( $query ); }
function rest_url( string $path = "" ): string { return "https://publication.example/wp-json/" . ltrim( $path, "/" ); }
function current_time( string $type, bool $gmt = false ): string { unset( $type, $gmt ); return "2026-09-20 05:00:00"; }
function get_current_user_id(): int { return 7; }
function wp_next_scheduled( string $hook ) { return $GLOBALS["hpr_test_cron"][ $hook ]["timestamp"] ?? false; }
function wp_clear_scheduled_hook( string $hook ): int { unset( $GLOBALS["hpr_test_cron"][ $hook ] ); return 1; }
function wp_schedule_event( int $timestamp, string $recurrence, string $hook ): bool { $GLOBALS["hpr_test_cron"][ $hook ] = [ "timestamp" => $timestamp, "schedule" => $recurrence ]; return true; }
function wp_get_scheduled_event( string $hook ) { return isset( $GLOBALS["hpr_test_cron"][ $hook ] ) ? (object) $GLOBALS["hpr_test_cron"][ $hook ] : false; }
function is_plugin_active( string $plugin ): bool { return in_array( $plugin, $GLOBALS["hpr_test_active_plugins"], true ); }
function is_plugin_active_for_network( string $plugin ): bool { return in_array( $plugin, $GLOBALS["hpr_test_network_plugins"], true ); }
function deactivate_plugins( $plugins, bool $silent = false, ?bool $network_wide = null ): void {
    unset( $silent );
    $plugins = (array) $plugins;
    $target = $network_wide ? "hpr_test_network_plugins" : "hpr_test_active_plugins";
    $GLOBALS[ $target ] = array_values( array_diff( $GLOBALS[ $target ], $plugins ) );
}

function wp_unslash( $value ) {
    return $value;
}

function is_admin(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["admin"] );
}

function wp_doing_ajax(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["ajax"] );
}

function wp_is_json_request(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["json"] );
}

function is_front_page(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["front"] );
}

function is_home(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["home"] );
}

function is_author(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["author"] );
}

function is_category(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["category"] );
}

function is_tag(): bool {
    return ! empty( $GLOBALS["hpr_test_context"]["tag"] );
}

function is_singular( string $post_type = "" ): bool {
    $singular = $GLOBALS["hpr_test_context"]["singular"] ?? "";

    return "" === $post_type ? "" !== $singular : $post_type === $singular;
}

function get_post_types(): array {
    return [ "post" => "post", "page" => "page", "press-release" => "press-release" ];
}

function get_post_type( $post ): string {
    if ( is_object( $post ) ) {
        return (string) ( $post->post_type ?? "" );
    }

    if ( is_numeric( $post ) && isset( $GLOBALS["hpr_test_posts"][ (int) $post ] ) ) {
        return (string) $GLOBALS["hpr_test_posts"][ (int) $post ]->post_type;
    }

    return (string) $post;
}

function get_post( int $post_id ) {
    return $GLOBALS["hpr_test_posts"][ $post_id ] ?? null;
}

function get_post_meta( int $post_id, string $key, bool $single = false ) {
    $value = $GLOBALS["hpr_test_post_meta"][ $post_id ][ $key ] ?? "";

    return $single ? $value : [ $value ];
}

function update_post_meta( int $post_id, string $key, $value ): bool {
    $GLOBALS["hpr_test_post_meta"][ $post_id ][ $key ] = $value;
    return true;
}

function get_post_thumbnail_id( int $post_id ): int {
    return (int) ( $GLOBALS["hpr_test_thumbnails"][ $post_id ] ?? 0 );
}

function wp_insert_post( array $data, bool $wp_error = false ) {
    unset( $wp_error );
    $post_id = (int) ( $GLOBALS["hpr_test_next_post_id"] ?? 1000 );
    $GLOBALS["hpr_test_next_post_id"] = $post_id + 1;
    $post = new WP_Post( (string) ( $data["post_type"] ?? "post" ) );
    $post->ID = $post_id;
    $post->post_parent = (int) ( $data["post_parent"] ?? 0 );
    $GLOBALS["hpr_test_posts"][ $post_id ] = $post;
    return $post_id;
}

function wp_update_post( array $data, bool $wp_error = false ) {
    unset( $wp_error );
    $post_id = (int) ( $data["ID"] ?? 0 );
    if ( isset( $GLOBALS["hpr_test_posts"][ $post_id ] ) ) {
        $GLOBALS["hpr_test_posts"][ $post_id ]->post_parent = (int) ( $data["post_parent"] ?? $GLOBALS["hpr_test_posts"][ $post_id ]->post_parent );
    }
    return $post_id;
}

function set_post_thumbnail( int $post_id, int $attachment_id ): bool {
    $GLOBALS["hpr_test_thumbnails"][ $post_id ] = $attachment_id;
    return true;
}

function is_wp_error( $value ): bool { return $value instanceof WP_Error; }

function esc_url_raw( string $url ): string {
    return $url;
}

function wp_get_registered_image_subsizes(): array {
    return [
        "thumbnail" => [ "width" => 150, "height" => 150, "crop" => true ],
        "wide"      => [ "width" => 1200, "height" => 0, "crop" => false ],
    ];
}

class WP_Post {
    public int $ID = 0;
    public string $post_type;
    public int $post_parent = 0;

    public function __construct( string $post_type ) {
        $this->post_type = $post_type;
    }
}

class WP_Error {
    private string $code;
    private string $message;
    private $data;
    public function __construct( string $code, string $message, $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data() { return $this->data; }
}

class WP_REST_Request {
    private array $payload;
    public function __construct( array $payload = [] ) { $this->payload = $payload; }
    public function get_json_params(): array { return $this->payload; }
    public function get_body_params(): array { return $this->payload; }
}

function get_posts( array $args ): array {
    $key = (string) ( $args["meta_key"] ?? "" ) . "|" . (string) ( $args["meta_value"] ?? "" );
    return $GLOBALS["hpr_test_get_posts"][ $key ] ?? [];
}

function get_page_by_path( string $path, $output, string $post_type ) {
    unset( $output, $post_type );
    return $GLOBALS["hpr_test_pages"][ $path ] ?? null;
}

function get_permalink( int $post_id ): string { return "https://publication.example/press-release/" . $post_id . "/"; }

class WP_Query {
    public array $query_vars;
    private array $flags;

    public function __construct( array $query_vars = [], array $flags = [] ) {
        $this->query_vars = $query_vars;
        $this->flags = $flags;
    }

    public function get( string $key ) {
        return $this->query_vars[ $key ] ?? "";
    }

    public function set( string $key, $value ): void {
        $this->query_vars[ $key ] = $value;
    }

    public function is_feed(): bool { return ! empty( $this->flags["feed"] ); }
    public function is_search(): bool { return ! empty( $this->flags["search"] ); }
    public function is_preview(): bool { return ! empty( $this->flags["preview"] ); }
    public function is_main_query(): bool { return ! empty( $this->flags["main"] ); }
    public function is_singular(): bool { return ! empty( $this->flags["singular"] ); }
    public function is_home(): bool { return ! empty( $this->flags["home"] ); }
    public function is_author(): bool { return ! empty( $this->flags["author"] ); }
    public function is_category(): bool { return ! empty( $this->flags["category"] ); }
    public function is_tag(): bool { return ! empty( $this->flags["tag"] ); }
}

$GLOBALS["wpdb"] = new class {
    public string $posts = "wp_posts";

    public function prepare( string $query, string $value ): string {
        return str_replace( "%s", "'" . addslashes( $value ) . "'", $query );
    }
};

eval(
    'namespace hpr_distributor; final class Config {' .
    'public static $settings_page_name = "HPR";' .
    'public static $settings_page_capability = "manage_options";' .
    'public static $settings_page_slug = "hpr-distributor";' .
    'public static $settings_page_display_title = "HPR";' .
    'public static $plugin_starter_file = "hexa-pr-wire-distributor.php";' .
    'public static $plugin_version = "test";' .
    'public static function get_plugin_dir(): string { return dirname(__DIR__); }' .
    '}'
);

require_once __DIR__ . "/TestCase.php";
require_once dirname( __DIR__ ) . "/src/Import/SourceIdentity.php";
require_once dirname( __DIR__ ) . "/src/Migration/LegacyDependencyRetirement.php";
require_once dirname( __DIR__ ) . "/src/Import/NativeFeedSettings.php";
require_once dirname( __DIR__ ) . "/src/Import/NativeFeedImporter.php";
require_once dirname( __DIR__ ) . "/src/Api/OnboardingContract.php";
require_once dirname( __DIR__ ) . "/src/Content/PressReleaseLoopExclusion.php";
require_once dirname( __DIR__ ) . "/src/Media/ExternalImageSizing.php";
require_once dirname( __DIR__ ) . "/src/Setup/HexaPrWireAuthor.php";
require_once dirname( __DIR__ ) . "/src/ContentTypes/PressReleaseFieldGroups.php";
require_once dirname( __DIR__ ) . "/src/Admin/DashboardData.php";
require_once dirname( __DIR__ ) . "/src/Admin/DistributorActivity.php";
require_once dirname( __DIR__ ) . "/src/Diagnostics/DistributorDiagnostics.php";
require_once dirname( __DIR__ ) . "/src/Lifecycle/DeletionSync.php";
require_once dirname( __DIR__ ) . "/src/Migration/SourceSlugRepair.php";
require_once dirname( __DIR__ ) . "/src/Admin/DashboardActions.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-components.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-overview.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-import-sync.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-images.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-content-types.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-general.php";
require_once dirname( __DIR__ ) . "/settings-dashboard-system-checks.php";
require_once dirname( __DIR__ ) . "/settings-dashboard.php";

use hpr_distributor\Content\PressReleaseLoopExclusion;
use hpr_distributor\Api\OnboardingContract;
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Import\SourceIdentity;
use hpr_distributor\Media\ExternalImageSizing;
use hpr_distributor\Migration\LegacyDependencyRetirement;
use hpr_distributor\Setup\HexaPrWireAuthor;
use hpr_distributor\Tests\TestCase;

$canonical = SourceIdentity::canonical_url( "HTTPS://HexaPRWire.com/story/?utm_source=test&b=2&a=1#fragment" );
TestCase::same( "https://hexaprwire.com/story/?a=1&b=2", $canonical, "Canonical URLs must drop tracking and sort durable query values." );
TestCase::same( "post:328228", SourceIdentity::source_id( "https://hexaprwire.com/?p=328228", $canonical ), "WordPress GUID post IDs must become durable source IDs." );
TestCase::true( SourceIdentity::allowed_host( "https://cdn.hexaprwire.com/photo.jpg", "hexaprwire.com" ), "Hexa PR Wire subdomains must be accepted." );
TestCase::false( SourceIdentity::allowed_host( "https://example.com/photo.jpg", "hexaprwire.com" ), "Third-party image hosts must be rejected." );

$native_settings = NativeFeedSettings::validate(
    [ "feed_url" => "https://hexaprwire.com/?feed=rss_publication&publication=her-forward" ]
);
TestCase::true( $native_settings["valid"], "A Hexa PR Wire publication feed must pass native validation." );
TestCase::same( "her-forward", $native_settings["settings"]["publication_slug"], "The publication slug must be derived from the feed." );
TestCase::true( $native_settings["settings"]["update_existing"], "Existing destination releases must update by default." );
TestCase::same( 20, $native_settings["settings"]["run_history_limit"], "Run history must have a bounded default." );

$GLOBALS["hpr_test_active_plugins"] = [
    "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php",
    "featured-image-from-url/featured-image-from-url.php",
];
$GLOBALS["hpr_test_options"]["echo_rules_list"] = [
    [ "https://hexaprwire.com/?feed=rss_publication&publication=her-forward", "24", "1", "", "10", "publish", "press-release" ],
    [ "https://readwrite.com/feed/", "24", "1", "", "10", "publish", "post" ],
];
$GLOBALS["hpr_test_cron"]["echoaction"] = [ "timestamp" => time() + 300, "schedule" => "hourly" ];
$GLOBALS["hpr_test_cron"]["fifu_db2_orphan_gc_cron"] = [ "timestamp" => time() + 300, "schedule" => "hourly" ];
$legacy_before = LegacyDependencyRetirement::state();
TestCase::false( $legacy_before["ready"], "Legacy importer activity must block native readiness." );
TestCase::same( 1, $legacy_before["enabled_matching_echo_rules"], "Only enabled Hexa PR Wire press-release rules are conflicts." );
$echo_job_action = LegacyDependencyRetirement::apply( LegacyDependencyRetirement::ACTION_DISABLE_ECHO_JOB );
TestCase::true( $echo_job_action["action_success"], "The matching Echo job action must complete." );
TestCase::same( "0", $GLOBALS["hpr_test_options"]["echo_rules_list"][0][2], "The matching Echo rule must be disabled." );
TestCase::same( "1", $GLOBALS["hpr_test_options"]["echo_rules_list"][1][2], "Unrelated stored Echo rules must remain unchanged." );
TestCase::same(
    [ "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php", "featured-image-from-url/featured-image-from-url.php" ],
    $GLOBALS["hpr_test_active_plugins"],
    "Disabling the matching Echo job must not deactivate either plugin."
);
TestCase::true( isset( $GLOBALS["hpr_test_cron"]["echoaction"] ), "Disabling one Echo job must preserve Echo scheduling for unrelated jobs." );
TestCase::true( isset( $GLOBALS["hpr_test_cron"]["fifu_db2_orphan_gc_cron"] ), "Disabling one Echo job must not alter FIFU scheduling." );
TestCase::false( LegacyDependencyRetirement::state()["ready"], "FIFU must remain an explicit conflict after only the Echo job is disabled." );

$fifu_action = LegacyDependencyRetirement::apply( LegacyDependencyRetirement::ACTION_DISABLE_FIFU_PLUGIN );
TestCase::true( $fifu_action["action_success"], "The explicit FIFU action must complete." );
TestCase::same(
    [ "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php" ],
    $GLOBALS["hpr_test_active_plugins"],
    "The FIFU action must not deactivate Echo RSS."
);
TestCase::true( isset( $GLOBALS["hpr_test_cron"]["echoaction"] ), "The FIFU action must preserve Echo scheduling." );
TestCase::false( isset( $GLOBALS["hpr_test_cron"]["fifu_db2_orphan_gc_cron"] ), "The FIFU action must clear only FIFU background work." );
TestCase::true( LegacyDependencyRetirement::state()["ready"], "The importer must be ready when FIFU is inactive and the matching Echo job is disabled." );

$GLOBALS["hpr_test_active_plugins"] = [
    "rss-feed-post-generator-echo/rss-feed-post-generator-echo.php",
    "featured-image-from-url/featured-image-from-url.php",
];
$GLOBALS["hpr_test_options"]["echo_rules_list"][0][2] = "1";
$GLOBALS["hpr_test_cron"]["echoaction"] = [ "timestamp" => time() + 300, "schedule" => "hourly" ];
$GLOBALS["hpr_test_cron"]["fifu_db2_orphan_gc_cron"] = [ "timestamp" => time() + 300, "schedule" => "hourly" ];
$echo_plugin_action = LegacyDependencyRetirement::apply( LegacyDependencyRetirement::ACTION_DISABLE_ECHO_PLUGIN );
TestCase::true( $echo_plugin_action["action_success"], "The explicit Echo plugin action must complete." );
TestCase::same(
    [ "featured-image-from-url/featured-image-from-url.php" ],
    $GLOBALS["hpr_test_active_plugins"],
    "The Echo plugin action must not deactivate FIFU."
);
TestCase::false( isset( $GLOBALS["hpr_test_cron"]["echoaction"] ), "The Echo plugin action must clear only Echo scheduling." );
TestCase::true( isset( $GLOBALS["hpr_test_cron"]["fifu_db2_orphan_gc_cron"] ), "The Echo plugin action must preserve FIFU scheduling." );

$onboarding_contract = OnboardingContract::contract();
TestCase::same(
    "https://publication.example/wp-json/hpr-distributor/v1/onboarding/force-sync",
    $onboarding_contract["routes"]["force_sync"],
    "Publish onboarding must use the administrator-authenticated Force Sync route."
);
TestCase::same(
    "WordPress authenticated user with manage_options",
    $onboarding_contract["authentication"],
    "The onboarding contract must declare ordinary WordPress administrator authentication."
);

$operation_payload = [
    "operation_id" => "outlet-her-forward-20260920",
    "settings" => [
        "feed_url" => "https://hexaprwire.com/?feed=rss_publication&publication=her-forward",
        "publication_slug" => "her-forward",
        "enabled" => true,
        "schedule_enabled" => true,
        "interval" => "hourly",
        "author_id" => 21,
        "post_status" => "publish",
        "max_items" => 100,
    ],
];
$configured = OnboardingContract::configure( new WP_REST_Request( $operation_payload ) );
TestCase::true( ! is_wp_error( $configured ) && $configured["success"], "The authenticated onboarding contract must apply valid native settings." );
TestCase::false( $configured["idempotent"], "The first operation application must not be reported as a replay." );
$replayed = OnboardingContract::configure( new WP_REST_Request( $operation_payload ) );
TestCase::true( $replayed["idempotent"], "Replaying the same operation and settings must be idempotent." );
$conflicting_payload = $operation_payload;
$conflicting_payload["settings"]["publication_slug"] = "another-outlet";
$conflict = OnboardingContract::configure( new WP_REST_Request( $conflicting_payload ) );
TestCase::true( is_wp_error( $conflict ) && "hpr_operation_id_conflict" === $conflict->get_error_code(), "An operation ID must reject different settings." );
$secret_rejection = OnboardingContract::plan( new WP_REST_Request( [ "operation_id" => "outlet-secret-check", "password" => "not-accepted" ] ) );
TestCase::true( is_wp_error( $secret_rejection ) && "hpr_secrets_not_accepted" === $secret_rejection->get_error_code(), "The onboarding payload must reject login secrets." );
$rolled_back = OnboardingContract::rollback( new WP_REST_Request( [ "operation_id" => $operation_payload["operation_id"] ] ) );
TestCase::true( ! is_wp_error( $rolled_back ) && $rolled_back["success"], "Rollback must restore only the selected operation's settings snapshot." );
TestCase::same( "", NativeFeedSettings::get()["feed_url"], "Rollback must restore the pre-operation feed setting." );

$feed_xml = '<?xml version="1.0"?><rss xmlns:content="http://purl.org/rss/1.0/modules/content/"xmlns:media="http://search.yahoo.com/mrss/"><channel><item><title>Native Import</title><link>https://hexaprwire.com/native-import/</link><guid>https://hexaprwire.com/?p=77</guid><post_slug>native-import</post_slug><description>Summary</description><content:encoded><![CDATA[<p>Body</p>]]></content:encoded><category nicename="press-release">Press Release</category><media:content url="https://hexaprwire.com/wp-content/uploads/logo.png" medium="image" /></item></channel></rss>';
$feed_items = NativeFeedImporter::parse_feed_xml( $feed_xml );
TestCase::same( 1, count( $feed_items ), "The native feed parser must return the item." );
TestCase::same( "post:77", $feed_items[0]["source_id"], "The parsed item must expose its durable source ID." );
TestCase::same( "https://hexaprwire.com/wp-content/uploads/logo.png", $feed_items[0]["featured_image"], "The parser must preserve the source-hosted image." );

$GLOBALS["hpr_test_get_posts"]["_hpr_source_identity|hexaprwire:post:77"] = [ 901 ];
$dedupe = NativeFeedImporter::find_existing_post( $feed_items[0] );
TestCase::same( 901, $dedupe["post_id"], "Native deduplication must reuse the matching WordPress post ID." );
TestCase::contains( "source_identity", $dedupe["matched_by"], "Dedupe evidence must report the matching key." );
$deduplication_proof = OnboardingContract::deduplication_proof( $feed_items[0], 901 );
TestCase::true( $deduplication_proof["proven"], "Post-import deduplication must prove the source identity resolves uniquely to the destination post." );
TestCase::same( 901, $deduplication_proof["post_id"], "Post-import deduplication must return the verified destination post ID." );
TestCase::false( $deduplication_proof["collision"], "Post-import deduplication must reject collisions." );

$GLOBALS["hpr_test_get_posts"]["_hpr_source_id|post:77"] = [ 902 ];
$collision_failed_closed = false;
try {
    NativeFeedImporter::import_item( $feed_items[0], $native_settings["settings"], true );
} catch ( RuntimeException $exception ) {
    $collision_failed_closed = str_starts_with( $exception->getMessage(), "Source identity collision:" );
}
TestCase::true( $collision_failed_closed, "An ambiguous source identity must fail closed instead of selecting the first post." );
$GLOBALS["hpr_test_get_posts"]["_hpr_source_id|post:77"] = [];

$compact = NativeFeedImporter::compact_result(
    [
        "items_processed" => 2,
        "items" => [
            [ "action" => "created", "source_title" => "One", "source_url" => "https://hexaprwire.com/one/", "dedupe" => [] ],
            [ "action" => "updated", "source_title" => "Two", "source_url" => "https://hexaprwire.com/two/", "dedupe" => [] ],
        ],
    ],
    1
);
TestCase::same( 1, count( $compact["items"] ), "Stored run results must retain only the configured item-row limit." );
TestCase::same( 1, $compact["items_truncated"], "Stored run results must disclose truncated item rows." );

PressReleaseLoopExclusion::register();
TestCase::true(
    isset( $GLOBALS["hpr_test_hooks"]["action"]["pre_get_posts"] ),
    "The content service must register its query hook."
);
TestCase::true(
    isset( $GLOBALS["hpr_test_hooks"]["action"]["elementor/query/hpr_press_release_archive"] ),
    "The content service must register its scoped Elementor press-release query hook."
);
TestCase::same(
    2,
    $GLOBALS["hpr_test_hooks"]["action"]["elementor/query/hpr_press_release_archive"][0][2],
    "The scoped Elementor query hook must accept Elementor's query and widget arguments."
);
$secondary_cpt_query = new WP_Query( [ "post_type" => "press-release" ], [ "home" => true ] );
PressReleaseLoopExclusion::filter_query( $secondary_cpt_query );
TestCase::same( "", $secondary_cpt_query->get( "hpr_force_hide_press_release" ), "A secondary CPT query's internal home flag must not impersonate the site home context." );
TestCase::same( "", $secondary_cpt_query->get( "post__in" ), "A secondary CPT query on a normal page must not be emptied." );
TestCase::true( (bool) $secondary_cpt_query->get( "ignore_sticky_posts" ), "A dedicated press-release query must not prepend ordinary sticky posts." );

$GLOBALS["hpr_test_context"]["front"] = true;
$front_page_elementor_args = PressReleaseLoopExclusion::filter_elementor_args( [ "post_type" => "press-release" ] );
TestCase::same(
    [ 0 ],
    $front_page_elementor_args["post__in"],
    "Ordinary front-page Elementor query filtering must remain fail-closed before the scoped allow hook runs."
);
$scoped_front_page_query = new WP_Query( $front_page_elementor_args, [ "home" => true ] );
PressReleaseLoopExclusion::allow_elementor_press_release_archive( $scoped_front_page_query );
PressReleaseLoopExclusion::filter_query( $scoped_front_page_query );
TestCase::true(
    (bool) $scoped_front_page_query->get( "hpr_allow_press_release_loop" ),
    "The scoped Elementor archive query must carry its explicit allow marker."
);
TestCase::same(
    [],
    $scoped_front_page_query->get( "post__in" ),
    "The scoped Elementor archive query must recover from the generic front-page empty guard."
);
TestCase::same(
    "press-release",
    $scoped_front_page_query->get( "post_type" ),
    "The scoped Elementor archive query must retain the press-release CPT."
);
TestCase::true(
    (bool) $scoped_front_page_query->get( "ignore_sticky_posts" ),
    "The scoped Elementor archive query must suppress ordinary sticky posts."
);
TestCase::same(
    " WHERE 1=1",
    PressReleaseLoopExclusion::filter_where( " WHERE 1=1", $scoped_front_page_query ),
    "The scoped Elementor archive query must bypass SQL-level press-release exclusion."
);
TestCase::same(
    1,
    count( PressReleaseLoopExclusion::filter_posts( [ new WP_Post( "press-release" ) ], $scoped_front_page_query ) ),
    "The scoped Elementor archive query must retain press-release results."
);

$ordinary_front_page_query = new WP_Query( [ "post_type" => "post", "post__in" => [ 42 ] ], [ "home" => true ] );
PressReleaseLoopExclusion::allow_elementor_press_release_archive( $ordinary_front_page_query );
TestCase::same(
    "",
    $ordinary_front_page_query->get( "hpr_allow_press_release_loop" ),
    "The scoped allow hook must ignore queries that do not explicitly request press releases."
);
TestCase::same(
    [ 42 ],
    $ordinary_front_page_query->get( "post__in" ),
    "The scoped allow hook must not rewrite ordinary post queries."
);

$front_page_cpt_query = new WP_Query( [ "post_type" => "press-release" ], [ "home" => true ] );
PressReleaseLoopExclusion::filter_query( $front_page_cpt_query );
TestCase::same( "", $front_page_cpt_query->get( "post__in" ), "An explicitly requested press-release-only query must remain available on every page." );
TestCase::true( (bool) $front_page_cpt_query->get( "ignore_sticky_posts" ), "An explicit front-page press-release query must still suppress ordinary sticky posts." );
$GLOBALS["hpr_test_context"]["front"] = false;

$GLOBALS["hpr_test_options"]["hide_press_release_from_author_loop"] = true;
$author_query = new WP_Query(
    [ "post_type" => [ "post", "press-release" ], "author_name" => "staff-one" ],
    [ "main" => true, "author" => true ]
);
PressReleaseLoopExclusion::filter_query( $author_query );
TestCase::same( [ "post" ], $author_query->get( "post_type" ), "Author queries must exclude press releases." );
TestCase::true( (bool) $author_query->get( "hpr_force_hide_press_release" ), "Filtered queries must carry the exclusion marker." );

$feed_query = new WP_Query( [ "post_type" => "any" ], [ "main" => true, "author" => true, "feed" => true ] );
PressReleaseLoopExclusion::filter_query( $feed_query );
TestCase::same( "any", $feed_query->get( "post_type" ), "RSS feeds must remain untouched." );

$only_press_releases = PressReleaseLoopExclusion::exclude_from_args( [ "post_type" => "press-release" ], true );
TestCase::same( [ 0 ], $only_press_releases["post__in"], "Press-release-only loop requests must become empty." );

$any_types = PressReleaseLoopExclusion::exclude_from_args( [ "post_type" => "any" ], true );
TestCase::false( in_array( "press-release", $any_types["post_type"], true ), "The any-post-type expansion must omit press releases." );

$posts = [ new WP_Post( "post" ), new WP_Post( "press-release" ), new WP_Post( "page" ) ];
$filtered_posts = PressReleaseLoopExclusion::filter_posts( $posts, $author_query );
TestCase::same( 2, count( $filtered_posts ), "The result fallback must remove press releases." );

TestCase::same(
    [ 300, 158 ],
    ExternalImageSizing::target_dimensions( [ 300, 300 ], 1200, 630 ),
    "Square thumbnail requests must preserve the source aspect ratio."
);
TestCase::same(
    [ 150, 79 ],
    ExternalImageSizing::target_dimensions( "thumbnail", 1200, 630 ),
    "Named square sizes must preserve the source aspect ratio."
);
TestCase::same(
    [ 1200, 630 ],
    ExternalImageSizing::target_dimensions( "full", 1200, 630 ),
    "Full images must retain their original dimensions."
);

$external_url = "https://hexaprwire.com/wp-content/uploads/source.jpg";
$photon_url = "https://i0.wp.com/hexaprwire.com/wp-content/uploads/source.jpg?resize=150,79&ssl=1";
$external_attachment = new WP_Post( "attachment" );
$external_attachment->post_parent = 901;
$GLOBALS["hpr_test_posts"][900] = $external_attachment;
$GLOBALS["hpr_test_posts"][901] = new WP_Post( "press-release" );
$GLOBALS["hpr_test_post_meta"][900]["_hpr_remote_featured_image_url"] = $external_url;
$GLOBALS["hpr_test_options"]["hpr_remote_image_dimensions"][ md5( $external_url ) ] = [
    "w" => 1200,
    "h" => 630,
];
$filtered_external_image = ExternalImageSizing::filter_image_src(
    [ $photon_url, 150, 79, true ],
    900,
    "thumbnail",
    false
);
TestCase::same( $external_url, $filtered_external_image[0], "External sizing must keep the rendered image on the Hexa PR Wire source host." );
TestCase::same( [ 150, 79 ], array_slice( $filtered_external_image, 1, 2 ), "External sizing must retain corrected dimensions." );
TestCase::same( $external_url, ExternalImageSizing::filter_attachment_url( "https://publication.example/local.jpg", 900 ), "The first-party attachment URL filter must return the source-hosted image." );

$remote_post = new WP_Post( "press-release" );
$remote_post->ID = 910;
$GLOBALS["hpr_test_posts"][910] = $remote_post;
$source_image = "https://hexaprwire.com/wp-content/uploads/logo.png";
$GLOBALS["hpr_test_options"]["hpr_remote_image_dimensions"][ md5( $source_image ) ] = [ "w" => 600, "h" => 300 ];
$remote_sync = ExternalImageSizing::sync_remote_featured_image( 910, $source_image, "Publication logo" );
TestCase::true( $remote_sync["created"], "Native remote rendering must create an attachment shell when one does not exist." );
TestCase::same( $source_image, $GLOBALS["hpr_test_post_meta"][ $remote_sync["attachment_id"] ]["_hpr_remote_featured_image_url"], "The attachment shell must retain the Hexa PR Wire source URL." );
TestCase::false( isset( $GLOBALS["hpr_test_post_meta"][ $remote_sync["attachment_id"] ]["_wp_attached_file"] ), "Native remote rendering must not create a local attached-file path." );
TestCase::same( $remote_sync["attachment_id"], get_post_thumbnail_id( 910 ), "The remote attachment shell must become the featured image without changing the press-release post ID." );

$profile = HexaPrWireAuthor::profile();
TestCase::same( "info@hexaprwire.com", HexaPrWireAuthor::EMAIL, "The canonical author email must not drift." );
TestCase::same( "https://www.facebook.com/hexaprwire/", $profile["urls"]["facebook"], "Facebook URL must match the source profile." );
TestCase::same( "https://www.linkedin.com/company/hexaprwire/", $profile["urls"]["linkedin"], "LinkedIn URL must match the source profile." );

$tabs = hpr_distributor\hpr_dashboard_tabs();
$_GET["tab"] = "system-checks";
TestCase::same( "diagnostics", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "Legacy system-check routes must alias to Diagnostics." );
$_GET["tab"] = "content-types";
TestCase::same( "content-model", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "The legacy content-types route must alias to Content Model & ACF." );
$_GET["tab"] = "snippets";
TestCase::same( "general", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "The legacy snippets route must alias to General Settings." );
TestCase::true( isset( $tabs["images"] ), "The dashboard must expose a dedicated Images from URL tab." );
$_GET["tab"] = "going-live";
TestCase::same( "going-live", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "Going Live must have an exact route." );
$_GET["tab"] = "unknown";
TestCase::same( "overview", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "Unknown routes must fall back to Overview." );

$GLOBALS["hpr_test_options"][ NativeFeedSettings::OPTION ] = NativeFeedSettings::validate(
    [ "feed_url" => "https://hexaprwire.com/?feed=rss_publication&publication=her-forward" ]
)["settings"];
$GLOBALS["hpr_test_options"][ NativeFeedImporter::RUN_HISTORY_OPTION ] = [
    [ "run_id" => "scheduled-failure", "trigger" => "schedule", "success" => false, "ended_gmt" => "2026-09-22 05:00:00" ],
    [ "run_id" => "manual-success", "trigger" => "admin-run", "success" => true, "ended_gmt" => "2026-09-22 04:30:00" ],
    [ "run_id" => "scheduled-success", "trigger" => "schedule", "success" => true, "ended_gmt" => "2026-09-22 04:00:00" ],
];
$GLOBALS["hpr_test_options"][ NativeFeedImporter::LAST_RUN_OPTION ] = $GLOBALS["hpr_test_options"][ NativeFeedImporter::RUN_HISTORY_OPTION ][0];
$GLOBALS["hpr_test_options"][ hpr_distributor\Lifecycle\DeletionSync::RECEIPT_OPTION ] = [ "status" => "failed", "completed_gmt" => "2026-09-22 03:00:00" ];
$GLOBALS["hpr_test_options"][ hpr_distributor\Lifecycle\DeletionSync::LAST_SUCCESS_OPTION ] = [ "status" => "success", "completed_gmt" => "2026-09-22 02:00:00" ];
$GLOBALS["hpr_test_cron"][ NativeFeedSettings::CRON_HOOK ] = [ "timestamp" => time() + 300, "schedule" => "hourly" ];
$cron_report = hpr_distributor\Admin\DashboardData::cron_report();
TestCase::same( "scheduled-failure", $cron_report["import"]["last_scheduled"]["run_id"], "Cron reporting must show the latest scheduled attempt even when it failed." );
TestCase::same( "scheduled-success", $cron_report["import"]["last_scheduled_success"]["run_id"], "Cron reporting must separately show the latest successful scheduled run." );
TestCase::same( "failed", $cron_report["deletion"]["last_run"]["status"], "Deletion reporting must retain the last attempt." );
TestCase::same( "success", $cron_report["deletion"]["last_success"]["status"], "Deletion reporting must retain the last successful run." );

echo "PASS unit-modules (" . TestCase::count() . " assertions)\n";
