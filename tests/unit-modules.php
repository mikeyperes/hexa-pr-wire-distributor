<?php

define( "ABSPATH", __DIR__ . "/" );

$GLOBALS["hpr_test_options"] = [];
$GLOBALS["hpr_test_context"] = [];
$GLOBALS["hpr_test_hooks"] = [];

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

function absint( $value ): int {
    return abs( (int) $value );
}

function sanitize_key( string $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) );
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
    public string $post_type;
    public int $post_parent = 0;

    public function __construct( string $post_type ) {
        $this->post_type = $post_type;
    }
}

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
require_once dirname( __DIR__ ) . "/src/Import/EchoRuleContract.php";
require_once dirname( __DIR__ ) . "/src/Content/PressReleaseLoopExclusion.php";
require_once dirname( __DIR__ ) . "/src/Media/ExternalImageSizing.php";
require_once dirname( __DIR__ ) . "/src/Admin/FifuPostboxToggle.php";
require_once dirname( __DIR__ ) . "/src/Setup/HexaPrWireAuthor.php";
require_once dirname( __DIR__ ) . "/settings-dashboard.php";

use hpr_distributor\Admin\FifuPostboxToggle;
use hpr_distributor\Content\PressReleaseLoopExclusion;
use hpr_distributor\Import\EchoRuleContract;
use hpr_distributor\Media\ExternalImageSizing;
use hpr_distributor\Setup\HexaPrWireAuthor;
use hpr_distributor\Tests\TestCase;

$rule = array_fill( 0, 83, "" );
$rule[0] = "https://hexaprwire.com/?feed=rss_publication&publication=financial-tech-times";
$rule[1] = "1";
$rule[6] = "press-release";
$rules = [ 11 => $rule ];

$application = EchoRuleContract::apply( $rules, 42 );
TestCase::same( 1, $application["matched"], "The HexaPRWire rule must be detected." );
TestCase::same( "42", $application["rules"][11][7], "The canonical author must be assigned." );
TestCase::same( "1", $application["rules"][11][67], "Update-existing must be enabled." );
TestCase::same( "1", $application["rules"][11][82], "Copy-slug must be enabled." );
TestCase::same(
    $rule[0],
    $application["rules"][11][0],
    "Applying the contract must preserve the complete destination feed URL."
);
TestCase::true(
    EchoRuleContract::mapping_ready( $application["rules"][11][46] ),
    "The HerForward field mapping must validate."
);
TestCase::true(
    EchoRuleContract::inspect( $application["rules"], 42 )["passed"],
    "A configured active rule must pass inspection."
);
TestCase::false(
    EchoRuleContract::mapping_ready( "author_id=>wrong" ),
    "Incorrect placeholders must fail mapping inspection."
);

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

$external_url = "https://media.example.test/source.jpg";
$photon_url = "https://i0.wp.com/media.example.test/source.jpg?resize=150,79&ssl=1";
$external_attachment = new WP_Post( "attachment" );
$external_attachment->post_parent = 901;
$GLOBALS["hpr_test_posts"][900] = $external_attachment;
$GLOBALS["hpr_test_posts"][901] = new WP_Post( "press-release" );
$GLOBALS["hpr_test_post_meta"][900]["_wp_attached_file"] = $external_url;
$GLOBALS["hpr_test_options"]["hpr_fifu_external_image_dimensions"][ md5( $external_url ) ] = [
    "w" => 1200,
    "h" => 630,
];
$filtered_external_image = ExternalImageSizing::filter_image_src(
    [ $photon_url, 150, 79, true ],
    900,
    "thumbnail",
    false
);
TestCase::same( $photon_url, $filtered_external_image[0], "External sizing must preserve FIFU's transformed CDN URL." );
TestCase::same( [ 150, 79 ], array_slice( $filtered_external_image, 1, 2 ), "External sizing must retain corrected dimensions." );

$GLOBALS["hpr_test_options"]["hpr_ui_cleanup_hide_fifu_featured_image_box"] = false;
$GLOBALS["hpr_test_options"]["hpr_ui_cleanup_collapse_fifu_featured_image_box"] = true;
TestCase::true( FifuPostboxToggle::should_enable(), "FIFU repair must run for collapsed-but-visible state." );
$GLOBALS["hpr_test_options"]["hpr_ui_cleanup_hide_fifu_featured_image_box"] = true;
TestCase::false( FifuPostboxToggle::should_enable(), "FIFU repair must not run when the box is hidden." );

$profile = HexaPrWireAuthor::profile();
TestCase::same( "info@hexaprwire.com", HexaPrWireAuthor::EMAIL, "The canonical author email must not drift." );
TestCase::same( "https://www.facebook.com/hexaprwire/", $profile["urls"]["facebook"], "Facebook URL must match the source profile." );
TestCase::same( "https://www.linkedin.com/company/hexaprwire/", $profile["urls"]["linkedin"], "LinkedIn URL must match the source profile." );

$tabs = hpr_distributor\hpr_dashboard_tabs();
$_GET["tab"] = "system-checks";
TestCase::same( "diagnostics", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "Legacy system-check routes must alias to Diagnostics." );
$_GET["tab"] = "going-live";
TestCase::same( "going-live", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "Going Live must have an exact route." );
$_GET["tab"] = "unknown";
TestCase::same( "overview", hpr_distributor\hpr_dashboard_active_tab( $tabs ), "Unknown routes must fall back to Overview." );

echo "PASS unit-modules (" . TestCase::count() . " assertions)\n";
