# Hexa WordPress Plugin Core

Shared WordPress plugin core for Hexa plugins.

This package exists to stop each plugin from re-implementing the same admin tabs, activity logs, updater wiring, shortcode lists, and setup patterns differently.

## Package Identity

These names are fixed. Do not rename them in plugin implementations.

| Item | Value |
| --- | --- |
| Repository folder | `hexa-wordpress-plugin-core` |
| Composer package | `hexa/plugin-core` |
| Root namespace | `Hexa\PluginCore\` |
| Autoload path | `src/` |
| Version source | `VERSION` |

## Required Folder Map

Every sub-namespace has its own folder under `src/`.

```text
hexa-wordpress-plugin-core/
  VERSION
  src/
    ActivityLog/        -> Hexa\PluginCore\ActivityLog
    CoreBootstrap/      -> Hexa\PluginCore\CoreBootstrap
    CoreContracts/      -> Hexa\PluginCore\CoreContracts
    CorePackageUpdates/ -> Hexa\PluginCore\CorePackageUpdates
    CoreRuntime/        -> Hexa\PluginCore\CoreRuntime
    CredentialVault/    -> Hexa\PluginCore\CredentialVault
    FieldStructures/    -> Hexa\PluginCore\FieldStructures
    FaqSets/            -> Hexa\PluginCore\FaqSets
    LogFiles/           -> Hexa\PluginCore\LogFiles
    PluginProvisioning/ -> Hexa\PluginCore\PluginProvisioning
    PluginUpdates/      -> Hexa\PluginCore\PluginUpdates
    ShortcodeRegistry/  -> Hexa\PluginCore\ShortcodeRegistry
    SiteStructure/      -> Hexa\PluginCore\SiteStructure
    SchemaDetection/    -> Hexa\PluginCore\SchemaDetection
    SmartSearch/        -> Hexa\PluginCore\SmartSearch
    SystemEnvironment/  -> Hexa\PluginCore\SystemEnvironment
    WpAdminUiCleanup/   -> Hexa\PluginCore\WpAdminUiCleanup
    WpAdminComponents/  -> Hexa\PluginCore\WpAdminComponents
    WpAdminAjax/        -> Hexa\PluginCore\WpAdminAjax
    WpAdminTabs/        -> Hexa\PluginCore\WpAdminTabs
    WpConfigFile/       -> Hexa\PluginCore\WpConfigFile
    WpCronTasks/        -> Hexa\PluginCore\WpCronTasks
```

Do not create `HWS\BaseTools\PluginCore`, `HexaWordPressPluginCore`, `Hexa\Core`, or plugin-specific namespaces inside this package. Consuming plugins may have their own namespaces, but this shared package always stays under `Hexa\PluginCore`.

## First Core Areas

- `ActivityLog`: shared activity log records, storage modes, and expandable dark log renderer.
- `CoreBootstrap`: consistent setup/init protocol for loading this core in a host plugin.
- `CoreContracts`: interfaces that host plugins and core modules must follow.
- `CorePackageUpdates`: compares and updates the vendored Hexa WordPress Plugin Core package.
- `CoreRuntime`: runtime value objects such as plugin context and core version metadata.
- `CredentialVault`: encrypted API-key/secret storage, masking, and credential field examples.
- `FieldStructures`: reusable displays and status checks for ACF groups, custom post types, taxonomies, and option-backed feature structures.
- `FaqSets`: shared FAQ set sanitizing, item normalization, primary-set resolution, safe answer links, FAQPage schema, and reusable list or accordion output.
- `LogFiles`: shared error-log source definitions, tail readers, classifiers, search/highlight UI, and renderers.
- `PluginProvisioning`: shared plugin discovery, status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation.
- `PluginUpdates`: shared GitHub/update configuration objects and host plugin updater.
- `ShortcodeRegistry`: shortcode definition registry, dashboard display renderer, examples, live output, and test runner contracts.
- `SiteStructure`: reusable critical page blueprint management, assigned page storage, WordPress navigation menu creation, custom menu-item creation, add-all-assigned-pages actions, menu structure attachment, and page-to-menu-item tools.
- `SchemaDetection`: reusable JSON-LD URL scans, source detection, duplicate schema conflict checks, FAQ validation, and dark admin report rendering.
- `SmartSearch`: smart search/X-Search AJAX endpoint and reusable typeahead renderer.
- `SystemEnvironment`: safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting.
- `WpAdminUiCleanup`: shared admin UI cleanup definitions, AJAX toggles, target-screen CSS/JS, postbox hide/collapse behavior, and footer filters.
- `WpAdminComponents`: shared visual primitives such as cards, subcards, buttons, pills, tooltips, and collapsible sections.
- `WpAdminAjax`: WordPress admin-AJAX nonce, capability, request parsing, action registration, and handler guards.
- `WpAdminTabs`: admin tab definitions, registry, host hook integration, and the automatic Hexa core documentation tab.
- `WpConfigFile`: safe `wp-config.php` constant and `ini_set()` reads/writes with validation and rollback backup handling.
- `WpCronTasks`: reusable WP-Cron interval registration, scheduling, unscheduling, event inspection, and health status payloads.

## Host Plugin Integration Rule

A plugin using this package must provide a host context. The host context is the only place plugin-specific values belong.

Examples of host-specific values:

- plugin slug
- plugin basename
- plugin version
- plugin root path
- plugin root URL
- GitHub repository
- admin page slug
- WordPress capability

Core classes must read those values from `PluginContextInterface`. They must not hard-code a host plugin name.

## Required Setup Protocol

Every plugin that uses this core follows the same sequence:

1. Load Composer autoload or the vendored core autoloader.
2. Build a `PluginContext`.
3. Build a `CoreBootstrap` with that context.
4. Register modules with the bootstrap.
5. Call `boot()` once.

Example:

```php
use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'hws-base-tools',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '10.18.27',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'mikeyperes/hws-base-tools',
        'admin_page'  => 'hws-core-tools',
        'capability'  => 'manage_options',
    ]
);

( new CoreBootstrap( $context ) )
    ->add_module( $shortcodes_module )
    ->add_module( $tabs_module )
    ->add_module( $updater_module )
    ->boot();
```

## Agent Rule

Before adding implementations in another Codex or Claude chat, read:

- `AGENTS.md`
- `HEXA_PLUGIN_CORE_LIBRARY.md`
- `docs/folder-map.md`
- `docs/setup-protocol.md`
- `docs/implementation-checklist.md`
- `docs/new-plugin-master-checklist.md`
- `docs/site-structure.md`
- `docs/schema-detection.md`
- `docs/field-structures.md`
- `docs/faq-sets.md`
- the namespace-specific doc for the folder being changed

If a new feature does not fit an existing namespace, document the proposed namespace first before adding code.

## Core Package Versioning

The shared core is a library, not a WordPress plugin. Its current version is stored in the repository root `VERSION` file.

Host plugins that vendor this package should render a separate core-package status panel under the host plugin updater:

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

This panel compares the vendored `VERSION` in the host plugin with the public GitHub repository `VERSION`.

## SiteStructure Section Rendering

`Hexa\PluginCore\SiteStructure\SiteStructureRenderer` can render page assignments and menu tools together or separately. Use `show_pages => false` for a menu-only tab, and `show_menus => false` when a plugin keeps navigation tools somewhere else. This keeps menu building generic while host plugins provide their own page blueprint and action names.

## Activity Log Component

Use the activity component for updater progress, imports, tests, maintenance tasks, and any admin workflow that benefits from a collapsible dark monitor.

Storage modes:

| Mode | Storage | Lifetime |
| --- | --- | --- |
| `page` | Rendered only | Removed on page refresh |
| `transient` | WordPress transient | Removed after TTL or clear |
| `permanent` | WordPress option | Kept until clear |

```php
use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogger;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;

$config = new ActivityLogConfig(
    [
        'id'          => 'example-activity-log',
        'title'       => 'Example Activity Log',
        'storage'     => ActivityLogConfig::STORAGE_TRANSIENT,
        'storage_key' => 'example_activity_log',
        'collapsed'   => false,
    ]
);

$logger = new ActivityLogger( $config );
$logger->add( new ActivityLogEntry( 'Started import.', [ 'batch' => 12 ], 'admin', 'importer', null, 'info' ) );

( new ActivityLogRenderer( $config ) )->render( $logger->all() );
```

## Automatic Core Tab

Host dashboards expose a tab-list filter and tab-render filter. The core registers itself through those hooks:

```php
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;

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

## UI Primitives

Use `Hexa\PluginCore\WpAdminComponents\CoreUi` for reusable admin UI pieces.

```php
use Hexa\PluginCore\WpAdminComponents\CoreUi;

CoreUi::render_assets();

echo CoreUi::card(
    [
        'title'     => 'System Status',
        'body_html' => '<p>Everything is healthy.</p>',
        'meta_html' => CoreUi::pill( 'Healthy', 'success' ),
    ]
);

echo CoreUi::collapsible(
    [
        'title'     => 'Advanced details',
        'body_html' => '<p>Hidden until expanded.</p>',
    ]
);
```

## Credentials / API Keys

Use `Hexa\PluginCore\CredentialVault` for API-key and secret storage.

```php
$store = new \Hexa\PluginCore\CredentialVault\CredentialStore();
$store->store( 'openai', 'api_key', $raw_key );
$key = $store->get( 'openai', 'api_key' );
$masked = $store->get_masked( 'openai', 'api_key' );
$exists = $store->exists( 'openai', 'api_key' );
```

The storage key pattern is:

```text
hpc_cred_{slug}_{keyName}
```

## Smart Search / X-Search

Use `Hexa\PluginCore\SmartSearch` for reusable typeahead search. This is the WordPress equivalent of Laravel `<x-hexa-smart-search>`.

```php
( new \Hexa\PluginCore\SmartSearch\SmartSearchRenderer() )->render(
    [
        'id'        => 'plugin-content-search',
        'label'     => 'Find content',
        'source'    => 'posts',
        'post_type' => 'any',
    ]
);
```

The core module registers:

```text
wp_ajax_hexa_plugin_core_smart_search
```

## WP Admin AJAX Registry

Use `Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry` for host plugin admin-AJAX actions. Host plugins provide action names and callbacks; core performs capability checks, nonce checks, request normalization, exception handling, and JSON responses.

```php
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

( new AjaxActionRegistry(
    [
        'capability'   => 'manage_options',
        'nonce_action' => 'example_admin',
        'nonce_field'  => 'nonce',
    ]
) )->register(
    [
        'example_load_tab' => [
            'callback' => static function ( AjaxRequest $request ): array {
                return [ 'tab' => $request->key( 'tab', 'overview' ) ];
            },
        ],
    ]
);
```

## Shortcode Display Renderer

Use `Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer` for shortcode admin lists. Every row should show the shortcode, description, real output value, and examples with parameters.

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
                    'label'      => 'Text value',
                    'shortcode'  => '[smp_publication_field field="legal_name" format="text"]',
                    'parameters' => [ 'field' => 'legal_name', 'format' => 'text' ],
                ],
            ],
        ],
    ],
    [
        'title'       => 'Shortcodes',
        'description' => 'Copy examples or inspect live output.',
    ]
);
```

## Error Log Viewer

Use `Hexa\PluginCore\LogFiles` for reusable error-log monitoring.

```php
use Hexa\PluginCore\LogFiles\ErrorLogPanelRenderer;
use Hexa\PluginCore\LogFiles\ErrorLogSource;

( new ErrorLogPanelRenderer() )->render(
    [
        new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ),
        new ErrorLogSource( 'error', 'error_log', ABSPATH . 'error_log', true, 'delete-error-log' ),
    ]
);
```


## Host Dashboard Tabs

Use `Hexa\PluginCore\WpAdminTabs\HostTabsRenderer` when the host dashboard itself needs the shared Hexa tab bar, AJAX loader, loading status, and browser-history behavior.

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

`Hexa\PluginCore\SchemaDetection\SchemaPageScanner` fetches public URLs and extracts JSON-LD schema blocks into structured payloads. `Hexa\PluginCore\SchemaDetection\SchemaScanRenderer` renders those payloads as a dark admin report with source labels, duplicate-type conflict warnings, invalid JSON rows, and FAQPage validation. Host plugins keep their own expectations and pass those expected rows into the renderer. See `docs/schema-detection.md`.

```php
echo ( new \Hexa\PluginCore\SchemaDetection\SchemaScanRenderer() )->renderReport( [ $scan ], [ "title" => "Schema Detection Results" ] );
```

## FAQ Sets

`Hexa\PluginCore\FaqSets\FaqSetManager` sanitizes repeatable FAQ set data, normalizes question and answer items, resolves a `primary` set, adds safe link attributes to answer HTML, generates FAQPage schema, and renders reusable list or accordion output. Host plugins keep their own option names and shortcodes. See `docs/faq-sets.md`.

```php
$manager = new \Hexa\PluginCore\FaqSets\FaqSetManager();
$set = $manager->resolveSet( $sets, "primary", $primary_slug );
echo $manager->renderFaqs(
    $set,
    [
        "style" => "accordion",
        "inject_schema" => true,
    ]
);
```
