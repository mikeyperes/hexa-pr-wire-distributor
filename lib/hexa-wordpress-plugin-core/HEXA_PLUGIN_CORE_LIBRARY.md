# Hexa Plugin Core Library

Copy this file into every plugin that consumes `hexa/plugin-core`. Keep it updated when the core API changes.

This is the quick reference for developers and agents working in separate Codex or Claude chats.

## Fixed Identity

```text
Repository: hexa-wordpress-plugin-core
Composer package: hexa/plugin-core
Root namespace: Hexa\PluginCore\
Source root: src/
Version source: VERSION
```

Do not rename these.

## Folder And Namespace Map

```text
src/ActivityLog/        Hexa\PluginCore\ActivityLog
src/CoreBootstrap/      Hexa\PluginCore\CoreBootstrap
src/CoreContracts/      Hexa\PluginCore\CoreContracts
src/CorePackageUpdates/ Hexa\PluginCore\CorePackageUpdates
src/CoreRuntime/        Hexa\PluginCore\CoreRuntime
src/CredentialVault/    Hexa\PluginCore\CredentialVault
src/FieldStructures/    Hexa\PluginCore\FieldStructures
src/FaqSets/            Hexa\PluginCore\FaqSets
src/LogFiles/           Hexa\PluginCore\LogFiles
src/PluginProvisioning/ Hexa\PluginCore\PluginProvisioning
src/PluginUpdates/      Hexa\PluginCore\PluginUpdates
src/ShortcodeRegistry/  Hexa\PluginCore\ShortcodeRegistry
src/SiteStructure/      Hexa\PluginCore\SiteStructure
src/SchemaDetection/    Hexa\PluginCore\SchemaDetection
src/SmartSearch/        Hexa\PluginCore\SmartSearch
src/SystemEnvironment/  Hexa\PluginCore\SystemEnvironment
src/WpAdminUiCleanup/   Hexa\PluginCore\WpAdminUiCleanup
src/WpAdminAjax/        Hexa\PluginCore\WpAdminAjax
src/WpAdminComponents/  Hexa\PluginCore\WpAdminComponents
src/WpAdminTabs/        Hexa\PluginCore\WpAdminTabs
src/WpConfigFile/       Hexa\PluginCore\WpConfigFile
src/WpCronTasks/        Hexa\PluginCore\WpCronTasks
```

## UI Components

Namespace:

```text
Hexa\PluginCore\WpAdminComponents
```


## WP Admin UI Cleanup

Namespace:

```text
Hexa\PluginCore\WpAdminUiCleanup
```

Use `CleanupRegistry` to define admin cleanup options once, render toggle rows, save settings through AJAX, and apply behavior on the target admin screens.

Required rules:

- Register cleanup behavior admin-wide, not only inside the settings tab.
- Use plugin-specific option prefixes and AJAX action names.
- Use `css_hide` for direct selectors and `postbox_collapse` when the box should stay visible but load closed.
- Test through the visible admin UI and exact target screen with Puppeteer or Playwright.

Example definition shape:

```text
key: hide_post_editor_comments
label: Post Editor Comments
section: wordpress_editor
default: true
admin_pages: post.php, post-new.php
mode: css_hide
selectors: #commentsdiv, #commentsdiv-hide, label[for="commentsdiv-hide"]
```

## WP Admin AJAX

Namespace:

```text
Hexa\PluginCore\WpAdminAjax
```

Use `AjaxActionRegistry` for host plugin admin-AJAX actions. Use `AjaxRequest` for sanitized request values. Use `AjaxFailure` for expected validation errors. `AjaxGuard` remains available for low-level nonce/capability checks.

```php
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

( new AjaxActionRegistry(
    [
        'capability'   => 'manage_options',
        'nonce_action' => 'example_admin',
        'nonce_field'  => 'nonce',
    ]
) )->register(
    [
        'example_search' => [
            'callback' => static function ( AjaxRequest $request ): array {
                $term = $request->text( 'term', '' );

                if ( '' === $term ) {
                    throw AjaxFailure::bad_request( 'Search term is required.' );
                }

                return [ 'results' => [] ];
            },
        ],
    ]
);
```

## System Environment

Namespace:

```text
Hexa\PluginCore\SystemEnvironment
```

Use `SystemEnvironment` for safe constants/INI reads, shell wrappers, size parsing, cgroup-aware CPU/memory checks, and byte formatting.

```php
use Hexa\PluginCore\SystemEnvironment\SystemEnvironment;

$memory = SystemEnvironment::get_memory_info();
$cpu = SystemEnvironment::get_cpu_info();
$bytes = SystemEnvironment::parse_size( ini_get( 'memory_limit' ) );
```

## Plugin Provisioning

Namespace:

```text
Hexa\PluginCore\PluginProvisioning
```

Use `PluginProvisioner` for reusable plugin discovery, status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation. Host plugins provide the catalog data; core performs the mechanics.

```php
use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

$status = PluginProvisioner::plugin_status_by_folder( 'smp-verified-profiles' );
$plugin_file = PluginProvisioner::find_plugin_file_by_folder( 'smp-verified-profiles' );
$result = PluginProvisioner::ensure_github_plugin_active(
    'smp-verified-profiles',
    'mikeyperes/smp-verified-profiles',
    [ 'branch' => 'main' ]
);
```

## WP Config File

Namespace:

```text
Hexa\PluginCore\WpConfigFile
```

Use `WpConfigFile` for safe `wp-config.php` constant and `ini_set()` reads/writes. Host plugins should not write direct regex file mutation code.

```php
use Hexa\PluginCore\WpConfigFile\WpConfigFile;

$result = WpConfigFile::modify_constants([
    'WP_DEBUG' => 'false',
    'WP_MEMORY_LIMIT' => '4096M',
    'ini_display_errors' => [
        'type' => 'ini',
        'value' => '0',
    ],
]);

$memory = WpConfigFile::constant_status( 'WP_MEMORY_LIMIT' );
$ini = WpConfigFile::get_php_ini_value( 'display_errors' );
```

## WP Cron Tasks

Namespace:

```text
Hexa\PluginCore\WpCronTasks
```

Use `WpCronTask` for reusable WP-Cron interval registration, scheduling, unscheduling, event inspection, and status payloads.

```php
use Hexa\PluginCore\WpCronTasks\WpCronTask;

WpCronTask::schedule_interval(
    'my_plugin_cleanup_cron',
    'my_plugin_cleanup_interval',
    5 * DAY_IN_SECONDS,
    'Every 5 days'
);

$status = WpCronTask::status(
    'my_plugin_cleanup_cron',
    [
        'callback' => 'my_plugin_cleanup_callback',
        'schedule_key' => 'my_plugin_cleanup_interval',
    ]
);
```

## Site Structure

Namespace:

```text
Hexa\PluginCore\SiteStructure
```

Use `PageStructureManager` for critical page blueprints, callback-backed assigned page storage, starter/template content, page details, managed page create/delete protection, WordPress navigation menu creation, custom menu items, add-all-pages menu actions, menu blueprint attachment, and page-to-menu-item attachment. Use `SiteStructureAjaxController` to keep host-specific AJAX action names while sharing nonce, capability, and request handling. Use `SiteStructureRenderer` for the admin UI. The renderer accepts `show_pages` and `show_menus` so hosts can split page assignment and menu building into separate tabs without duplicating menu code.

```php
use Hexa\PluginCore\SiteStructure\PageStructureManager;
use Hexa\PluginCore\SiteStructure\SiteStructureAjaxController;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;

$manager = new PageStructureManager([
    'option_prefix' => 'my_plugin_page_',
    'template_option_prefix' => 'my_plugin_page_template_',
    'managed_meta_key' => '_my_plugin_managed_page',
    'managed_key_meta_key' => '_my_plugin_page_key',
    'created_page_status' => 'draft',
    'select_post_statuses' => ['publish', 'draft', 'private'],
    'assignment_statuses' => ['publish', 'draft', 'private'],
    'reuse_existing_pages' => true,
    'pages' => [
        'about' => ['title' => 'About', 'slug' => 'about', 'template' => true, 'children' => []],
    ],
    'default_templates' => [
        'about' => '<h2>About</h2>',
    ],
    'menu_structures' => [
        'header' => ['title' => 'Header', 'page_keys' => ['about']],
    ],
]);

(new SiteStructureAjaxController($manager, [
    'nonce_action' => 'my_plugin_ajax',
    'actions' => [
        'assign_page' => 'my_plugin_assign_page',
        'create_page' => 'my_plugin_create_page',
        'delete_page' => 'my_plugin_delete_page',
        'create_navigation_menu' => 'my_plugin_create_navigation_menu',
        'delete_navigation_menu' => 'my_plugin_delete_navigation_menu',
        'create_menu_item' => 'my_plugin_create_menu_item',
        'attach_page_to_menu_item' => 'my_plugin_attach_page_to_menu_item',
        'attach_menu_structure' => 'my_plugin_attach_menu_structure',
        'menu_inventory' => 'my_plugin_menu_inventory',
        'save_template' => 'my_plugin_save_template',
        'apply_template' => 'my_plugin_apply_template',
        'page_details' => 'my_plugin_page_details',
        'update_page_slug' => 'my_plugin_update_page_slug',
    ],
]))->register();

echo (new SiteStructureRenderer($manager, [
    'nonce' => wp_create_nonce('my_plugin_ajax'),
    'enable_template_editors' => true,
    'show_page_details' => true,
    'actions' => [
        'assign_page' => 'my_plugin_assign_page',
        'create_page' => 'my_plugin_create_page',
        'delete_page' => 'my_plugin_delete_page',
        'create_navigation_menu' => 'my_plugin_create_navigation_menu',
        'delete_navigation_menu' => 'my_plugin_delete_navigation_menu',
        'create_menu_item' => 'my_plugin_create_menu_item',
        'attach_page_to_menu_item' => 'my_plugin_attach_page_to_menu_item',
        'attach_menu_structure' => 'my_plugin_attach_menu_structure',
        'menu_inventory' => 'my_plugin_menu_inventory',
        'save_template' => 'my_plugin_save_template',
        'apply_template' => 'my_plugin_apply_template',
        'page_details' => 'my_plugin_page_details',
        'update_page_slug' => 'my_plugin_update_page_slug',
    ],
]))->render();
```

Host plugins can use plain option prefixes or callback storage. Use callbacks when assignments and starter templates live inside an existing settings array instead of one option per page. Keep action names plugin-specific; the core maps those names to generic handlers.

Generic SiteStructure AJAX actions:

```text
assign_page, create_page, delete_page, create_navigation_menu, delete_navigation_menu,
create_menu_item, attach_page_to_menu_item, attach_menu_structure, menu_inventory,
save_template, apply_template, page_details, update_page_slug
```

Class:

```text
CoreUi
```

Use this for:

```text
cards
subcards
buttons
pills
tooltips
collapsible sections
copy buttons
shared admin styles
```

Never rebuild those patterns directly in host plugins when `CoreUi` can render them.

## Credentials / API Keys

Namespace:

```text
Hexa\PluginCore\CredentialVault
```

Classes:

```text
CredentialStore
CredentialFieldRenderer
```

Use this for API keys, tokens, secrets, and passwords.

Rules:

- Store by slug and key name.
- Option key pattern is `hpc_cred_{slug}_{keyName}`.
- Never show raw values in admin UI.
- Use `get_masked()` for display.
- Every credential implementation needs setup steps, Save, Test, and Delete actions.

Example:

```php
$store = new \Hexa\PluginCore\CredentialVault\CredentialStore();
$store->store( 'openai', 'api_key', $raw_key );
$masked = $store->get_masked( 'openai', 'api_key' );
```

## Smart Search / X-Search

Namespace:

```text
Hexa\PluginCore\SmartSearch
```

Classes:

```text
SmartSearchAjaxController
SmartSearchRenderer
```

Use this for generic AJAX typeahead search. This is the WordPress equivalent of Laravel `<x-hexa-smart-search>`.

Rules:

- Default source searches WordPress posts, pages, and custom post types.
- Extend results with `hexa_plugin_core_smart_search_results`.
- Results should include `id`, `value`, `name`, and `subtitle`.

Example:

```php
( new \Hexa\PluginCore\SmartSearch\SmartSearchRenderer() )->render([
    'id'        => 'content-search',
    'label'     => 'Find content',
    'source'    => 'posts',
    'post_type' => 'any',
]);
```

## Field Structures

Namespace: Hexa\PluginCore\FieldStructures

Classes: FieldStructureManager, FieldStructureRenderer

Use this for admin displays that explain and test ACF field groups, custom post types, taxonomies, and option-backed structures. Host plugins provide definitions; Hexa Core normalizes them, renders one row per structure, shows enabled and registered status, exposes setting toggles through the host save AJAX action, and keeps fields, dependencies, code examples, test reports, and activity notes in a consistent layout.

Definition keys: id, label, type, setting_key, enabled, registered, acf_group_key, object_name, location, fields, dependencies, instructions, code_example, test_report, activity, edit_url. The registered and test_report values may be callbacks. Do not move plugin-specific ACF registration arrays into core; core owns the display and status model only.

Example use: create a FieldStructureRenderer, pass an array of structure definitions, and pass save_action plus nonce when toggles should save through AJAX.

## Error Logs

Namespace:

```text
Hexa\PluginCore\LogFiles
```

Classes:

```text
ErrorLogSource
ErrorLogClassifier
ErrorLogReader
ErrorLogPanelRenderer
```

Use this for reusable log viewing:

```php
( new ErrorLogPanelRenderer() )->render(
    [
        new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ),
        new ErrorLogSource( 'error', 'error_log', ABSPATH . 'error_log', true, 'delete-error-log' ),
        new ErrorLogSource( 'admin-error', 'wp-admin/error_log', ABSPATH . 'wp-admin/error_log' ),
    ]
);
```

## README Locations

When this core is vendored into a host plugin, the important files are:

```text
lib/hexa-wordpress-plugin-core/README.md
lib/hexa-wordpress-plugin-core/HEXA_PLUGIN_CORE_LIBRARY.md
HEXA_PLUGIN_CORE_LIBRARY.md
```

The host root copy of `HEXA_PLUGIN_CORE_LIBRARY.md` exists so agents can read the rules without opening the vendored package first.

## Activity Log Component

Namespace:

```text
Hexa\PluginCore\ActivityLog
```

Classes:

```text
ActivityLogConfig
ActivityLogEntry
ActivityLogger
ActivityLogRenderer
```

Storage modes:

```text
page       render-only, removed on refresh
transient  stored with set_transient
permanent  stored with update_option
```

Example:

```php
$config = new ActivityLogConfig(
    [
        'id'          => 'example-activity-log',
        'title'       => 'Example Activity Log',
        'storage'     => ActivityLogConfig::STORAGE_TRANSIENT,
        'storage_key' => 'example_activity_log',
    ]
);

$logger = new ActivityLogger( $config );
$logger->add( new ActivityLogEntry( 'Update started.', [], 'admin', 'updater', null, 'info' ) );

( new ActivityLogRenderer( $config ) )->render( $logger->all() );
```

## Required Host Plugin Boot

Every consuming plugin should:

1. Load Composer or the vendored core autoloader.
2. Create `Hexa\PluginCore\CoreRuntime\PluginContext`.
3. Create `Hexa\PluginCore\CoreBootstrap\CoreBootstrap`.
4. Add core modules and host adapter modules.
5. Call `boot()` once.

```php
use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'example-plugin',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '1.0.0',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'owner/example-plugin',
        'admin_page'  => 'example-plugin',
        'capability'  => 'manage_options',
    ]
);

( new CoreBootstrap( $context ) )
    ->add_module( $module )
    ->boot();
```

## Plugin Updates

Namespace:

```text
Hexa\PluginCore\PluginUpdates
```

Purpose:

- GitHub version checks
- WordPress plugin update transient injection
- GitHub archive folder normalization
- HWS-style plugin update status panel
- Force update check
- Direct update from GitHub
- Normalized plugin ZIP downloads
- Version history ZIP downloads
- Transient-backed update activity log

### Required Updater Config

The updater can be initialized from a plugin file and a GitHub URL/repo:

```php
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer;

$updater_config = UpdaterConfig::from_plugin_file(
    __FILE__,
    'https://github.com/owner/example-plugin',
    [
        'plugin_slug'          => 'example-plugin',
        'plugin_starter_file'  => 'example-plugin.php',
        'github_branch'        => 'main',
        'nonce_action'         => 'example_plugin_nonce',
        'ajax_action_prefix'   => 'example_plugin_updater',
        'capability'           => 'update_plugins',
        'download_capability'  => 'manage_options',
    ]
);

( new GitHubPluginUpdater( $updater_config ) )->register();
( new UpdaterAjaxController( $updater_config ) )->register();
```

Render the panel in an admin page:

```php
( new UpdaterPanelRenderer( $updater_config ) )->render();
```

## Automatic Core Tab

Namespace:

```text
Hexa\PluginCore\WpAdminTabs
```

Classes:

```text
CoreTabConfig
CoreTabModule
CoreTabRenderer
```

The host dashboard must expose:

```text
one filter that returns the tab list
one filter that renders a selected tab and returns true when rendered
```

Then register:

```php
( new CoreTabModule(
    new CoreTabConfig(
        [
            'tabs_filter'   => 'example_dashboard_tabs',
            'render_filter' => 'example_dashboard_render_tab',
            'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
            'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
            'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
        ]
    )
) )->register();
```

The default tab is:

```text
ID: hexa-core
Label: Hexa WordPress Plugin Core
```

If the caller only has a plugin folder slug and a GitHub URL, use:

```php
$updater_config = UpdaterConfig::from_slug_and_github_url(
    'example-plugin',
    'https://github.com/owner/example-plugin',
    [
        'plugin_starter_file' => 'example-plugin.php',
        'plugin_name'         => 'Example Plugin',
        'version'             => '1.0.0',
    ]
);
```

### Plugin Update Classes

```text
UpdaterConfig
GitHubVersionClient
GitHubPluginUpdater
PluginUpdateStatus
UpdateProgressStore
DirectPluginInstaller
PluginZipBuilder
UpdaterAjaxController
UpdaterPanelRenderer
UpdaterFilesystem
```

### Vendored Core Package Update Classes

Namespace:

```text
Hexa\PluginCore\CorePackageUpdates
```

Classes:

```text
CorePackageConfig
CorePackageVersionClient
CorePackageStatus
CorePackageInstaller
CorePackageAjaxController
CorePackagePanelRenderer
```

### Vendored Core Package Updater

The Hexa WordPress Plugin Core is a library, not a WordPress plugin. Its version is stored in `VERSION`.

Host plugins that vendor the core should place a core status panel directly under their plugin updater panel:

```php
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CorePackageUpdates\CorePackageConfig;
use Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer;

$core_config = CorePackageConfig::from_core_root(
    __DIR__ . '/lib/hexa-wordpress-plugin-core',
    [
        'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
        'github_branch'      => 'main',
        'nonce_action'       => 'example_plugin_nonce',
        'ajax_action_prefix' => 'example_plugin_core_package',
    ]
);

( new CorePackageAjaxController( $core_config ) )->register();
( new CorePackagePanelRenderer( $core_config ) )->render();
```

The panel compares:

```text
vendored VERSION in the host plugin
public GitHub VERSION from mikeyperes/hexa-wordpress-plugin-core
```

Do not use the WordPress plugin header updater for the core library.

### Updater Input Terms

Use these terms consistently:

```text
plugin_slug: folder slug, e.g. hws-base-tools
plugin_basename: folder/main-file.php, e.g. hws-base-tools/hws-base-tools.php
plugin_starter_file: main plugin file, e.g. hws-base-tools.php
github_repo: owner/repo, e.g. mikeyperes/hws-base-tools
github_url: full GitHub URL, normalized internally to owner/repo
github_branch: branch to check and download, usually main
```

### Updater Behavior

`GitHubPluginUpdater` registers:

```text
pre_set_site_transient_update_plugins
plugins_api
upgrader_source_selection
upgrader_post_install
http_request_timeout
http_request_args
```

`DirectPluginInstaller` performs:

```text
download GitHub ZIP
extract into wp-content/upgrade
find plugin folder
rename repo-main to the canonical plugin slug
verify the starter file exists
back up the current canonical folder
install the new folder
remove duplicate slug-* folders
repoint active_plugins to canonical basename
clear update caches
write progress steps to a transient
```

## Shortcodes

Namespace:

```text
Hexa\PluginCore\ShortcodeRegistry
```

Purpose:

- define shortcode metadata
- collect shortcodes in a registry
- render shortcode admin rows with real output
- prepare one shortcode test at a time
- support admin UIs that show shortcode, description, real output value, examples with parameters, test method, and source

Core classes:

```text
ShortcodeDefinition
ShortcodeRegistry
ShortcodeDisplayRenderer
ShortcodeTestResult
ShortcodeTester
```

Example:

```php
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDefinition;
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeRegistry;

$registry = ( new ShortcodeRegistry() )
    ->add(
        new ShortcodeDefinition(
            'display_year',
            'Current Year',
            '[display_year]',
            'Outputs the current four-digit year.',
            'Runs without input and checks for non-empty output.'
        )
    );
```

Display example:

```php
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer;

echo ( new ShortcodeDisplayRenderer() )->render(
    [
        [
            'label'       => 'Publication Name',
            'shortcode'   => '[smp_publication_field field="legal_name" format="text"]',
            'description' => 'Outputs the publication legal name.',
            'source'      => 'publication option: legal_name',
            'examples'    => [
                [
                    'label'      => 'Text output',
                    'shortcode'  => '[smp_publication_field field="legal_name" format="text"]',
                    'parameters' => [ 'field' => 'legal_name', 'format' => 'text' ],
                ],
            ],
        ],
    ],
    [ 'title' => 'Plugin Shortcodes' ]
);
```

## Tabs

Namespace:

```text
Hexa\PluginCore\WpAdminTabs
```

Purpose:

- define admin tab IDs and labels
- keep tab registration consistent
- avoid scattered tab arrays across plugins

Core classes:

```text
TabDefinition
TabRegistry
```

Tab IDs must be lowercase slugs, not labels:

```text
overview
shortcodes
update-center
brand-assets
```

## Activity

Namespace:

```text
Hexa\PluginCore\ActivityLog
```

Purpose:

- standardize activity log entries
- record admin actions, updater actions, and tests

Core classes:

```text
ActivityLogEntry
ActivityLogger
```

Activity logs must not include secrets, tokens, private keys, or raw request payloads.

## Contracts

Namespace:

```text
Hexa\PluginCore\CoreContracts
```

Core interfaces:

```text
ModuleInterface
PluginContextInterface
```

Modules must register hooks only from:

```php
public function register(): void;
```

Do not execute feature behavior at include time.

## Support

Namespace:

```text
Hexa\PluginCore\CoreRuntime
```

Core classes:

```text
PluginContext
```

Use `PluginContext` for host plugin identity. Do not hard-code host plugin names inside shared core classes.

## Agent Checklist

Before changing a plugin that consumes this core:

1. Identify whether the change belongs in the shared core or the host plugin.
2. If it is reusable across plugins, put it in `Hexa\PluginCore`.
3. If it is plugin-specific, keep it in that plugin's namespace.
4. Use the exact folder/namespace map above.
5. Do not invent new names for existing concepts.
6. Update this file when adding public core behavior.
7. For new plugin audits, start with `docs/new-plugin-master-checklist.md`.


## Host Dashboard Tabs

Namespace: `Hexa\PluginCore\WpAdminTabs`

Use `HostTabsRenderer` for the visible host plugin tab shell. It owns the shared Hexa tab bar, AJAX tab loading, status text, history updates, and load events. Host plugins provide the tab array, active tab, admin page URL, AJAX action, nonce, and first-render callback.

```php
( new \Hexa\PluginCore\WpAdminTabs\HostTabsRenderer() )->render(
    [
        "tabs"            => $tabs,
        "active"          => $active,
        "page_url"        => admin_url( "options-general.php?page=example-plugin" ),
        "ajax_action"     => "example_load_tab",
        "nonce"           => $nonce,
        "render_callback" => [ $dashboard, "tab" ],
    ]
);
```

## System Checks

`Hexa\PluginCore\SystemChecks\SystemChecksRenderer` renders grouped pass/fail/warn/info checklists from a flat item array. Use it for launch readiness, plugin health, schema audits, and environment checks instead of duplicating checklist HTML in host plugins. See `docs/system-checks.md`.

## Schema Detection

Namespace:

```text
Hexa\PluginCore\SchemaDetection
```

Use schema detection for plugin pages that fetch frontend URLs and inspect JSON-LD output. Host plugins own the list of URLs and expected schema types. The core owns fetching, JSON-LD extraction, source labels, duplicate-type conflicts, FAQPage validation, and the dark report UI.

Primary classes:

```text
SchemaPageScanner
SchemaScanRenderer
```

```php
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;

$scanner = new SchemaPageScanner();
$scan = $scanner->scanUrl(
    home_url( "/" ),
    [
        "title" => "Homepage",
        "cache_bust" => true,
    ]
);

echo ( new SchemaScanRenderer() )->renderReport(
    [ $scan ],
    [
        "title" => "Schema Detection Results: HOMEPAGE",
        "expected" => [ "Expected: Person" ],
    ]
);
```

## FAQ Sets

Namespace:

```text
Hexa\PluginCore\FaqSets
```

Use FAQ sets for repeatable question and answer collections that need shortcode output and FAQPage schema.

Primary class:

```text
FaqSetManager
```

```php
use Hexa\PluginCore\FaqSets\FaqSetManager;

$manager = new FaqSetManager();
$sets = $manager->sanitizeSets( $raw_sets );
$set = $manager->resolveSet(
    $sets,
    "primary",
    $primary_slug
);

echo $manager->renderFaqs(
    $set,
    [
        "style" => "accordion",
        "inject_schema" => true,
    ]
);
```

Core owns:

- sanitizing FAQ set arrays
- normalizing question and answer item arrays
- resolving primary sets
- safe answer link attributes
- FAQPage schema arrays and JSON-LD script output
- reusable list and accordion output

Host plugins own option names, shortcode names, and any plugin-specific source of truth messaging.
