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
  PACKAGE_HASH
  bootstrap.php
  src/
    AcfFieldFactory/    -> Hexa\PluginCore\AcfFieldFactory
    ActivityLog/        -> Hexa\PluginCore\ActivityLog
    BrandColors/        -> Hexa\PluginCore\BrandColors
    CoreBootstrap/      -> Hexa\PluginCore\CoreBootstrap
    CoreContracts/      -> Hexa\PluginCore\CoreContracts
    CorePackageUpdates/ -> Hexa\PluginCore\CorePackageUpdates
    CoreRuntime/        -> Hexa\PluginCore\CoreRuntime
    ContentCleanup/     -> Hexa\PluginCore\ContentCleanup
    CredentialVault/    -> Hexa\PluginCore\CredentialVault
    DatabaseCleanup/    -> Hexa\PluginCore\DatabaseCleanup
    FieldStructures/    -> Hexa\PluginCore\FieldStructures
    FaqSets/            -> Hexa\PluginCore\FaqSets
    GettingStartedChecklist/
                        -> Hexa\PluginCore\GettingStartedChecklist
    LogFiles/           -> Hexa\PluginCore\LogFiles
    ObjectCache/        -> Hexa\PluginCore\ObjectCache
    PluginChecks/       -> Hexa\PluginCore\PluginChecks
    PluginProvisioning/ -> Hexa\PluginCore\PluginProvisioning
    PluginUpdates/      -> Hexa\PluginCore\PluginUpdates
    SnippetRegistry/    -> Hexa\PluginCore\SnippetRegistry
    ShortcodeRegistry/  -> Hexa\PluginCore\ShortcodeRegistry
    SiteStructure/      -> Hexa\PluginCore\SiteStructure
    SchemaDetection/    -> Hexa\PluginCore\SchemaDetection
    SchemaTools/        -> Hexa\PluginCore\SchemaTools
    SmartSearch/        -> Hexa\PluginCore\SmartSearch
    SystemEnvironment/  -> Hexa\PluginCore\SystemEnvironment
    WpAdminUiCleanup/   -> Hexa\PluginCore\WpAdminUiCleanup
    WpAdminComponents/  -> Hexa\PluginCore\WpAdminComponents
    WpAdminAjax/        -> Hexa\PluginCore\WpAdminAjax
    WpAdminTabs/        -> Hexa\PluginCore\WpAdminTabs
    WpConfigFile/       -> Hexa\PluginCore\WpConfigFile
    WpCronTasks/        -> Hexa\PluginCore\WpCronTasks
```

## Schema Tools

Version 0.19.x adds reusable schema graph helpers and a generic schema dashboard renderer. Host plugins can build their own schema objects, expose debug JSON, show ideal-vs-actual graph examples, provide validator links, render collapsed shortcode cards, and pass plugin-specific schema action panels through HexaWP Core instead of duplicating dashboard UI.

Do not create `HWS\BaseTools\PluginCore`, `HexaWordPressPluginCore`, `Hexa\Core`, or plugin-specific namespaces inside this package. Consuming plugins may have their own namespaces, but this shared package always stays under `Hexa\PluginCore`.

## First Core Areas

- `AcfFieldFactory`: reusable ACF field array factories for host field-group registrations.
- `ActivityLog`: shared activity log records, storage modes, and expandable dark log renderer.
- `BrandColors`: shared HWS Base Tools brand color readers, hex normalization, RGB conversion, and color-control payloads.
- `CoreBootstrap`: consistent setup/init protocol for loading this core in a host plugin.
- `CoreContracts`: interfaces that host plugins and core modules must follow.
- `CorePackageUpdates`: compares and updates the vendored Hexa WordPress Plugin Core package.
- `CoreRuntime`: runtime value objects, plugin context, version metadata, and selected-package integrity diagnostics.
- `ContentCleanup`: old content detection, backup file detection/deletion, article/media cleanup, all-matching and all-except-latest-X batch deletion, guarded AJAX actions, collapsible service cards, human-readable rule and scan-location detail cards, AJAX table updates, and collapsed Hexa Core Log Type 1 cleanup activity UI.
- `CredentialVault`: encrypted API-key/secret storage, masking, and credential field examples.
- `DatabaseCleanup`: guarded provider-backed cleanup sessions, per-task cleanup, per-table optimization, pre/post provider state restoration, and live AJAX progress.
- `FieldStructures`: reusable displays and status checks for ACF groups, custom post types, taxonomies, and option-backed feature structures.
- `FaqSets`: shared FAQ set sanitizing, item normalization, primary-set resolution, safe answer links, FAQPage schema, and reusable list or accordion output.
- `GettingStartedChecklist`: reusable plugin startup/onboarding checklist UI, collapsible parent steps, typed step/subtask registration, guarded AJAX execution, sequential subtask processing, request metadata payloads, spinner/check/X states, callback result normalization, reusable destructive sample runner, deleted-post/deleted-file reports, image preview report assets, and collapsed dark technical activity logs.
- `LogFiles`: shared error-log source definitions, tail readers, classifiers, search/highlight UI, and renderers.
- `ObjectCache`: provider-specific object-cache status and activation adapters, including verified LiteSpeed Redis checks.
- `PluginChecks`: shared required-plugin definitions, status checks, reusable collapsible plugin inventory tables, presence-based green/red Font Awesome SVG title indicators, Required/Optional badges, AJAX install/activate/deactivate/delete actions, subtle secondary row controls, update-cache refresh, and activity-log UI.
- `PluginProvisioning`: shared plugin discovery, status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation.
- `PluginUpdates`: shared GitHub/update configuration objects and host plugin updater.
- `SnippetRegistry`: shared snippet definitions, option toggles, test rules, related snippets, related shortcodes, basic README rendering, generic AJAX handlers, and the canonical snippets table UI.
- `ShortcodeRegistry`: shortcode definition registry, dashboard display renderer, examples, live output, and test runner contracts.
- `SiteStructure`: reusable critical page blueprint management, assigned page storage, WordPress navigation menu creation, custom menu-item creation, add-all-assigned-pages actions, menu structure attachment, and page-to-menu-item tools.
- `SchemaDetection`: reusable JSON-LD URL scans, source detection, duplicate schema conflict checks, FAQ validation, and dark admin report rendering.
- `SmartSearch`: smart search/X-Search AJAX endpoint and reusable typeahead renderer.
- `SystemEnvironment`: safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting.
- `WpAdminUiCleanup`: shared admin UI cleanup definitions, AJAX toggles, target-screen CSS/JS, postbox hide/collapse behavior, and footer filters.
- `WpAdminComponents`: shared visual primitives such as cards, subcards, buttons, pills, tooltips, and collapsible sections with visible chevron indicators.
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

1. Require the vendored root `bootstrap.php` and register the package candidate.
2. Wait for the shared resolver to select one package on `plugins_loaded`.
3. Build a `PluginContext`.
4. Build a `CoreBootstrap` with that context.
5. Register modules with the bootstrap and call `boot()` once.

Example:

```php
$core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';
require_once $core_root . '/bootstrap.php';
\hexa_plugin_core_register_package( 'hws-base-tools', $core_root );

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
- `docs/content-cleanup.md`
- `docs/database-cleanup.md`
- `docs/object-cache.md`
- `docs/site-structure.md`
- `docs/schema-detection.md`
- `docs/field-structures.md`
- `docs/faq-sets.md`
- `docs/brand-colors.md`
- `docs/snippet-registry.md`
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

This panel compares the vendored `VERSION` in the host plugin with the public GitHub repository `VERSION`. The host plugin updater and the vendored core updater both render as default-open persistent collapse cards. Each card reports the Git repo, Git URL, Git branch, Git version, current version, current-vs-Git comparison, green/red status flag, check-for-updates action, normalized ZIP download, and live update activity log.

## Getting Started Checklist

Version 0.19.35 adds `show_type_badges` to `GettingStartedChecklistConfig`. Host plugins can hide the non-interactive request-type pill when a checklist is used as a simple action list.

Version 0.19.34 restores Getting Started Checklist rows to a single continuous list for simple actions. Only parent steps with actual subtasks render as expandable sections, so one-action checklist items keep their individual run button without a fake expand/collapse control.

Version 0.19.33 adds human-readable before/action/verified-after report summaries to Getting Started Checklist reports, including clearer wp-config and deleted-file report wording.

Version 0.19.32 adds optional image preview assets to checklist reports and renames wp-config report columns to `Target Value` and `Verified Value` so setup tasks can distinguish requested configuration from read-back proof.

Version 0.19.31 adds updater package hygiene. Core ZIP builders, direct plugin updates, vendored Core package updates, and GitHub plugin provisioning now exclude VCS metadata such as `.git`, `.svn`, `.hg`, and `.bzr`; native plugin updates purge ignored metadata before install and fail with a clear error if locked metadata remains. GitHub access tokens are no longer appended to package URLs and must travel only through request headers.

Version 0.19.30 adds an Activate action for installed-but-inactive forbidden plugin rows, so cleanup inventories can temporarily activate an unwanted plugin before taking other action when needed.

Version 0.19.29 adds `PluginRecommendationRegistry`, a reusable Hexa plugin recommendation registry for site inventory scans, aggregate recommendations, per-provider recommendations, and installed plugins that are not recommended by any registered Hexa plugin.

Version 0.19.28 adds subtle secondary Deactivate and Delete controls to reusable plugin inventory rows. Installed plugins can be deactivated or deleted through guarded AJAX actions while must-use and drop-in plugins remain blocked.

Version 0.19.27 adds `GettingStartedChecklist\DestructiveSampleRunner`, a reusable typed-confirmation sample that creates temporary posts/media, deletes only those temporary records, and returns Core deleted-post/deleted-file reports with permalinks, media URLs, file locations, and sizes.
Version 0.19.26 adds reusable Getting Started Checklist templates. Host plugins can register named templates such as `default` or `diamond_website`; Core renders the picker, loads the selected template's predefined step structure, sends the active template id through AJAX, and resolves callbacks against the selected template.

Version 0.19.25 adds reusable Getting Started Checklist result reports, typed destructive confirmation inputs, and a WpAdminUiCleanup adapter that turns registered cleanup options into checklist subtasks with their attributes listed directly in the checklist UI.

`Hexa\PluginCore\GettingStartedChecklist` provides the reusable setup checklist that every plugin can use for first-run checks, onboarding, or ordered setup tasks. Host plugins register the typed step list and callbacks; Core owns the UI, AJAX endpoint, sequential runner, collapsible parent steps, nested subtask processing, type badges, spinner/check/X states, and collapsed technical activity log.

```php
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;

$config = new GettingStartedChecklistConfig([
    'root_id'      => 'my-plugin-getting-started',
    'nonce_action' => 'my_plugin_getting_started',
    'run_action'   => 'my_plugin_getting_started_run_item',
    'steps'        => [
        [
            'id'          => 'environment',
            'label'       => 'Verify Environment',
            'type'        => 'status_check',
            'description' => 'Checks WordPress and PHP values.',
            'subtasks'    => [
                [
                    'id'       => 'wordpress',
                    'label'    => 'WordPress Runtime',
                    'type'     => 'status_check',
                    'callback' => 'my_plugin_check_wordpress_runtime',
                ],
            ],
        ],
    ],
]);

( new GettingStartedChecklistAjaxController( $config ) )->register();
( new GettingStartedChecklistRenderer( $config ) )->render();
```

Callbacks receive a single payload array containing `step`, `subtask`, `context`, `request`, `request_type`, `is_subtask`, and `item_id`. Use `type` values of `callback`, `status_check`, `setup_action`, `feature_toggle`, `config_mutation`, `ajax_request`, or `custom`. Return `true`, `false`, a string, `WP_Error`, or an array with `success`, `message`, `logs`, and optional `data`.

## Content Cleanup

`Hexa\PluginCore\ContentCleanup` provides reusable cleanup structures for wp-admin. Host plugins pass their own action names, allowed post types, backup locations, and deletion limits; Core renders each cleanup service as a separate collapsible card, with fixed reports, filters, result tables, edit links, row flags, destructive buttons, row loaders, and closed-by-default dark activity logs.

Version 0.19.22 keeps cleanup renderers compatible with sites where another plugin has already loaded an older `Hexa\PluginCore\WpAdminComponents\CoreUi` class before the current plugin renders.

Version 0.19.21 keeps cleanup detection criteria backend-only in the operator UI and removes fixed minimum table widths so cleanup reports stay contained inside their collapsible cards.

Version 0.19.20 changes Getting Started Checklist parent rows into visible collapsible sections and keeps the technical activity log collapsed by default so checklist pages do not read like a debug log dump.

Version 0.19.16 changes cleanup services to manual scan by default. Content Cleanup, Backup Cleanup, and Article/Media Cleanup render immediately with a clear "Press Scan" empty state and only start AJAX work when the user clicks a scan button. Host plugins can opt back into load-time scanning with `auto_scan => true`.

Version 0.19.17 makes Article/Media Cleanup batch deletion the primary visible workflow. The two main actions are delete all matching posts or delete matching posts except the latest X, each with its own associated-media toggle. Filters, preview, selected-row deletion, and row deletion remain available in a collapsed Advanced Filters & Preview card.

Version 0.19.15 adds reusable article/media batch deletion for "delete all matching posts" and "delete all matching except the latest X posts." Batch deletion ignores the preview limit, runs through repeated AJAX requests, logs every batch, and can delete associated featured/inline/gallery media when the visible media cleanup toggle is enabled.

Version 0.19.14 adds a subtle Core detail-card variant and uses it for cleanup descriptions, detection rules, and scan-location details so secondary context stays collapsed and visually quiet. Backup scans now show an active loading row and log the file patterns searched, folders inspected, directory entries looked at, matched files, and no-result state.

Version 0.19.13 adds reusable collapsed detail subcards and uses them in cleanup services to show human-readable detection rules, descriptions, and every configured backup scan location with resolved directory status.

Version 0.19.12 keeps Core toggle inputs clipped to a 1px focusable control so hidden checkbox fields cannot create horizontal page overflow.

```php
use Hexa\PluginCore\ContentCleanup\ContentCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ContentCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ContentCleanupRenderer;

$config = new ContentCleanupConfig([
    'root_id'                => 'example-cleanup',
    'title'                  => 'Cleanup',
    'nonce_action'           => 'example_cleanup',
    'scan_action'            => 'example_cleanup_scan',
    'trash_action'           => 'example_cleanup_trash',
    'delete_action'          => 'example_cleanup_delete',
    'post_types'             => [ 'page' => 'Pages' ],
    'default_published_days' => 365,
    'show_filters'           => false,
    'count_label'            => 'Reported',
    'detection_rules'        => [
        [
            'id'                 => 'home_not_front',
            'label'              => 'Home',
            'tone'               => 'warning',
            'terms'              => [ 'home' ],
            'fields'             => [ 'title', 'slug' ],
            'exclude_option_ids' => [ 'page_on_front' ],
        ],
    ],
]);

( new ContentCleanupAjaxController( $config ) )->register();
( new ContentCleanupRenderer( $config ) )->render();
```

Version 0.19.4 adds:

- `BackupCleanupConfig`, `BackupCleanupScanner`, `BackupCleanupAjaxController`, and `BackupCleanupRenderer` for configured backup-file roots and extension-limited AJAX deletion.
- `ArticleMediaCleanupConfig`, `ArticleMediaCleanupScanner`, `ArticleMediaCleanupAjaxController`, and `ArticleMediaCleanupRenderer` for filtering posts, previewing matches, selecting rows, deleting individual posts, deleting all matching posts in AJAX batches, deleting all matching except the latest X posts, and optionally deleting detected featured/inline/gallery media attachments.

## Brand Color Controls

`Hexa\PluginCore\BrandColors\BrandColorProvider` reads the HWS Base Tools Brand Assets primary and secondary color options and can read Elementor site-setting color/font tokens. `Hexa\PluginCore\WpAdminComponents\ColorControl` renders one reusable admin color control with picker, editable hex value, RGB value, swatch, copy button, and optional HWS primary import button hooks. `Hexa\PluginCore\WpAdminComponents\DetailedColorPicker` renders the paired primary/secondary control with optional Elementor import and optional font controls.

Host plugins should pass their own setting key and wire save/import AJAX while reusing this markup instead of recreating color pickers.

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\ColorControl;

$brand = BrandColorProvider::payload('#2d5277');

echo ColorControl::render([
    'key' => 'accent_color',
    'label' => 'Accent color',
    'value' => $settings['accent_color'] ?? $brand['primary_color'],
    'default' => $brand['primary_color'],
    'import_brand' => true,
]);
```

Detailed visual example:

```text
Detailed Color Picker
+----------------------+----------------------+
| Primary color        | Secondary color      |
| Picker Hex RGB Copy  | Picker Hex RGB Copy  |
| Swatch               | Swatch               |
+----------------------+----------------------+
[Import Elementor colors and fonts]
```

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

$brand = BrandColorProvider::payload('#2d5277');

echo DetailedColorPicker::render([
    'title' => 'Brand card colors',
    'description' => 'Use site design tokens or override this card.',
    'primary' => [
        'key' => 'primary_color',
        'value' => $settings['primary_color'] ?? $brand['primary_color'],
        'hex_input_class' => 'plugin-primary-color',
    ],
    'secondary' => [
        'key' => 'secondary_color',
        'value' => $settings['secondary_color'] ?? $brand['secondary_color'],
        'hex_input_class' => 'plugin-secondary-color',
    ],
    'show_primary' => true,
    'show_secondary' => true,
    'show_elementor_import' => true,
    'show_fonts' => true,
    'fonts' => [
        [
            'key' => 'primary_font_family',
            'token' => 'primary_font_family',
            'label' => 'Primary font family',
            'value' => $settings['primary_font_family'] ?? '',
        ],
        [
            'key' => 'secondary_font_family',
            'token' => 'secondary_font_family',
            'label' => 'Secondary font family',
            'value' => $settings['secondary_font_family'] ?? '',
        ],
    ],
]);
```

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
