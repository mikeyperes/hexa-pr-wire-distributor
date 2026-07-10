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
src/AcfFieldFactory/    Hexa\PluginCore\AcfFieldFactory
src/CoreBootstrap/      Hexa\PluginCore\CoreBootstrap
src/CoreContracts/      Hexa\PluginCore\CoreContracts
src/CorePackageUpdates/ Hexa\PluginCore\CorePackageUpdates
src/CoreRuntime/        Hexa\PluginCore\CoreRuntime
src/ContentCleanup/     Hexa\PluginCore\ContentCleanup
src/CredentialVault/    Hexa\PluginCore\CredentialVault
src/DatabaseCleanup/    Hexa\PluginCore\DatabaseCleanup
src/FieldStructures/    Hexa\PluginCore\FieldStructures
src/FaqSets/            Hexa\PluginCore\FaqSets
src/GettingStartedChecklist/
                        Hexa\PluginCore\GettingStartedChecklist
src/LogFiles/           Hexa\PluginCore\LogFiles
src/ObjectCache/        Hexa\PluginCore\ObjectCache
src/PluginChecks/       Hexa\PluginCore\PluginChecks
src/PluginProvisioning/ Hexa\PluginCore\PluginProvisioning
src/PluginUpdates/      Hexa\PluginCore\PluginUpdates
src/SnippetRegistry/    Hexa\PluginCore\SnippetRegistry
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

Use `CoreUi::collapsible()` for expandable cards. The shared component owns the native `<details>` structure, persistent open/closed state, and visible chevron SVG indicator, so users can tell the card expands.

Use `CoreUi::toggle()` for checkbox-style toggles. Core clips the hidden checkbox input to a 1px focusable control so the input never creates horizontal page overflow.

Use `CoreUi::detail_card()` for nested expandable/collapsible subcards inside a parent tool section. It is meant for descriptions, rule explanations, scan-location lists, and other supporting details that should not dominate the page on load.


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

## Content Cleanup

Namespace:

```text
Hexa\PluginCore\ContentCleanup
```

Use `ContentCleanupConfig` for host-specific action names, nonce settings, allowed post types, statuses, default age filters, fixed report mode, detection rules, limits, and protected IDs. Use `ContentCleanupAjaxController` to register scan/trash/delete actions. Use `ContentCleanupRenderer` for a separate collapsible service card with a collapsed description subcard, backend-only detection rules, filters or no-filter report UI, detected rows table, row flags, edit-new-tab links, red destructive buttons, and a closed-by-default Hexa Core Log Type 1 live activity log. Cleanup renderers must tolerate older already-loaded `CoreUi` classes because multiple plugins can vendor the core on the same site.

Cleanup services default to manual scan. Leave `auto_scan` unset or false when the page should open instantly. Set `auto_scan => true` only when a plugin intentionally wants the service to run its AJAX scan on page load.

Use `BackupCleanupConfig`, `BackupCleanupAjaxController`, and `BackupCleanupRenderer` when a plugin needs a reusable backup-file cleanup table. The host plugin supplies configured roots and allowed extensions; Core renders a collapsed description subcard, a visible scan-location subcard with configured paths/extensions/resolved directories, scans those locations, logs every configured location, returns row IDs, and deletes only files that still match the configured roots/extensions.

Use `ArticleMediaCleanupConfig`, `ArticleMediaCleanupAjaxController`, and `ArticleMediaCleanupRenderer` when a plugin needs reusable post cleanup. Core renders the primary batch actions first: delete all matching posts, or delete matching posts except the latest X posts. Each primary batch action has its own associated-media toggle. Advanced filters, preview rows, select-all/row selection, row-by-row AJAX deletion, and filtered selected-row deletion remain available in a collapsed Advanced Filters & Preview card. Media deletion is off by default and only runs when the visible checkbox for that action is enabled. Associated media includes featured images plus inline/gallery attachment IDs detected from post content.

Batch deletion is intentionally separate from the preview table. `limit` only controls the visible preview rows. The batch actions use `post_type`, `status`, `search`, and the selected mode across all matching posts. Use `batch_delete_action`, `default_batch_size`, and `max_batch_size` to configure the plugin-specific AJAX endpoint and per-request batch limits.

Core automatically protects the WordPress front page, posts page, and privacy policy page.

## Getting Started Checklist

Namespace:

```text
Hexa\PluginCore\GettingStartedChecklist
```

Use `GettingStartedChecklistConfig` for host-owned action names, nonce settings, capability, labels, ordered steps, semantic request types, and request metadata. Use `GettingStartedChecklistAjaxController` to register the guarded AJAX runner. Use `GettingStartedChecklistRenderer` to render the reusable checklist UI with simple action rows, collapsible parent steps only when real subtasks exist, nested subtasks, spinner/check/X states, request type badges, sequential AJAX execution, optional image preview assets in reports, and a collapsed dark technical activity log.

Set `show_type_badges` to `false` for checklist screens that are meant to read as simple action lists. Keep it enabled when request type labels help operators understand mixed setup, status-check, destructive, and configuration tasks.

Checklist reports can include `meta.preview_assets` as an array of `label`, `url`, `preview_url`, `format`, and `meta`. Core renders those as visible image preview cards above the report table. Reports can also include `meta.documentation` and `meta.summary_items` to show plain-English before/action/verified-after proof above the raw table. For `wp_config_changes`, Core uses `Before Action`, `Requested Value`, and `Verified After`; host plugins must decide success from the verified value, not just the writer return.

Required rules:

- Keep plugin-specific callbacks in the host plugin.
- Keep checklist UI, AJAX execution, status icons, subtask sequencing, and log rendering in Hexa Core.
- Simple one-action steps should render as plain list rows with individual run buttons. Do not add fake subtasks or fake accordions for simple steps.
- A parent step with subtasks must stay in the running state until each subtask has finished.
- Callback returns may be `true`, `false`, a string, `WP_Error`, or an array with `success`, `message`, `logs`, and optional `data`.
- Step and subtask `type` values should be one of `callback`, `status_check`, `setup_action`, `feature_toggle`, `config_mutation`, `ajax_request`, or `custom`.
- Use `request` for structured request metadata. Core passes raw request metadata to callbacks and redacts secret/token/password/nonce/key values in public output.
- Use `required_inputs` or `inputs` for operator-supplied values that must be typed before a checklist item can run. Core renders the fields, validates them in the browser, sends them through AJAX as `inputs[field_id]`, validates/sanitizes them server-side, and passes them to callbacks as `$payload["inputs"]`.
- Supported input types are `text`, `email`, `url`, `password`, `number`, `tel`, and `search`.
- Do not hardcode site-specific SMTP sender emails, alert emails, API keys, or approval text in reusable Core code. Define the required input and feed the typed value into the existing host callback.

Example:

```php
$config = new \Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig([
    'root_id'      => 'my-plugin-getting-started',
    'nonce_action' => 'my_plugin_getting_started',
    'run_action'   => 'my_plugin_getting_started_run_item',
    'steps'        => [
        [
            'id'          => 'environment',
            'label'       => 'Verify Environment',
            'type'        => 'status_check',
            'subtasks'    => [
                [
                    'id'       => 'wordpress',
                    'label'    => 'WordPress Runtime',
                    'type'     => 'status_check',
                    'callback' => 'my_plugin_check_wordpress_runtime',
                ],
            ],
        ],
        [
            'id'          => 'smtp_setup',
            'label'       => 'Apply SMTP Settings',
            'type'        => 'config_mutation',
            'callback'    => 'my_plugin_apply_smtp_settings',
            'required_inputs' => [
                [
                    'id'          => 'from_email',
                    'label'       => 'From email',
                    'type'        => 'email',
                    'required'    => true,
                    'placeholder' => '',
                    'description' => 'Passed to the callback as $payload["inputs"]["from_email"].',
                ],
            ],
        ],
    ],
]);

( new \Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController($config) )->register();
( new \Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer($config) )->render();
```

## Plugin Checks And Plugin Inventory

Namespace:

```text
Hexa\PluginCore\PluginChecks
```

Use `PluginCheckDefinition` arrays for host-owned plugin lists. Use `PluginCheckService` for installed/active/update/auto-update status. Use `PluginInventoryRenderer` when a plugin needs a reusable table UI for plugin status or a plugin library. Use `PluginInventoryAjaxController` for no-refresh refresh, install-and-activate, activate, deactivate, and delete actions. Forbidden rows show Deactivate when active, Activate when inactive, and Delete when removable.

Required rules:

- Keep plugin-specific catalog data in the host plugin.
- Keep table UI, collapsible cards, install/activate actions, and status rendering in Hexa Core.
- The green check or red X beside the plugin title is based on actual installed/present status.
- Use `required` to show the Required/Optional badge and to style missing required rows.
- Use `source => wordpress_org` with `wp_org_slug` for WordPress.org installs.
- Use `source => github` with `github_repo` for GitHub ZIP installs. Core normalizes extracted `repo-main` folders to the configured slug.
- Use `source => pro` or `manual` when a plugin requires a manual upload/download.
- Use `source => must_use` or `dropin` for MU plugins and WordPress drop-ins; Core treats installed/present as active and skips update/auto-update checks.
- Do not render a separate Installed column. Show installed/missing state as a Font Awesome SVG green check or red X beside the plugin title, with hover text explaining the state.
- The Status column prints the icon plus `Active` or `Inactive`.
- Keep Deactivate and Delete as subtle secondary row controls. They are available for installed normal plugins, require confirmation where destructive, and remain blocked for must-use plugins and drop-ins.
- Do not use emoji indicators in plugin inventory UIs.

Example:

```php
$definitions = [
    [
        'id'                   => 'classic-editor',
        'name'                 => 'Classic Editor',
        'plugin_file'          => 'classic-editor/classic-editor.php',
        'slug'                 => 'classic-editor',
        'source'               => 'wordpress_org',
        'wp_org_slug'          => 'classic-editor',
        'required'             => true,
        'recommended'          => true,
        'auto_update_expected' => true,
        'checks'               => [
            'installed'   => true,
            'active'      => true,
            'up_to_date'  => false,
            'auto_update' => true,
        ],
    ],
];

( new \Hexa\PluginCore\PluginChecks\PluginInventoryAjaxController(
    $definitions,
    [
        'capability'    => 'install_plugins',
        'nonce_action'  => 'my_plugin_admin',
        'nonce_field'   => 'nonce',
        'action_prefix' => 'my_plugin_inventory',
        'renderer_args' => [
            'title'       => 'Plugin Status',
            'persist_key' => 'my-plugin-status',
            'columns'     => [
                'auto_update' => true,
                'version'     => true,
                'source'      => true,
            ],
        ],
    ]
) )->register();

echo ( new \Hexa\PluginCore\PluginChecks\PluginInventoryRenderer() )->render(
    $definitions,
    [
        'title'         => 'Plugin Status',
        'description'   => 'Plugin health for this integration.',
        'nonce'         => wp_create_nonce( 'my_plugin_admin' ),
        'nonce_field'   => 'nonce',
        'action_prefix' => 'my_plugin_inventory',
        'persist_key'   => 'my-plugin-status',
        'open'          => true,
    ]
);
```

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
    'auto_scan'              => false,
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

Backup cleanup example:

```php
use Hexa\PluginCore\ContentCleanup\BackupCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\BackupCleanupConfig;
use Hexa\PluginCore\ContentCleanup\BackupCleanupRenderer;

$backup_config = new BackupCleanupConfig([
    'root_id'       => 'example-backup-cleanup',
    'nonce_action'  => 'example_cleanup',
    'scan_action'   => 'example_backup_scan',
    'delete_action' => 'example_backup_delete',
    'auto_scan'     => false,
    'locations'     => [
        'updraftplus' => [
            'name'       => 'UpdraftPlus',
            'path'       => WP_CONTENT_DIR . '/updraft/',
            'extensions' => [ 'zip', 'gz', 'sql' ],
        ],
    ],
]);

( new BackupCleanupAjaxController( $backup_config ) )->register();
( new BackupCleanupRenderer( $backup_config ) )->render();
```

Article cleanup example:

```php
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupRenderer;

$article_config = new ArticleMediaCleanupConfig([
    'root_id'             => 'example-article-cleanup',
    'nonce_action'        => 'example_cleanup',
    'scan_action'         => 'example_article_scan',
    'delete_action'       => 'example_article_delete',
    'batch_delete_action' => 'example_article_batch_delete',
    'auto_scan'           => false,
    'post_types'          => [ 'post' => 'Posts' ],
    'default_batch_size'  => 50,
    'max_batch_size'      => 100,
]);

( new ArticleMediaCleanupAjaxController( $article_config ) )->register();
( new ArticleMediaCleanupRenderer( $article_config ) )->render();
```

## WP Admin AJAX

Namespace:

```text
Hexa\PluginCore\WpAdminAjax
```

Use `AjaxActionRegistry` for host plugin admin-AJAX actions. Use `AjaxRequest` for sanitized request values. Use `AjaxFailure` for expected validation errors. `AjaxGuard` remains available for low-level nonce/capability checks.

## Snippet Registry

Namespace:

```text
Hexa\PluginCore\SnippetRegistry
```

Use `SnippetDefinition` and `SnippetRegistry` to normalize host plugin snippets. Use `SnippetRenderer` to render description, testing, snippets, shortcodes, and basic README components. Use `SnippetAjaxController` when a host plugin wants generic toggle/test AJAX handlers.

Required host responsibilities:

- Host plugins own snippet functions and option keys.
- Core owns the generic registry, UI, test rule evaluation, and AJAX response shape.
- Testing rules should include at least option-enabled and function-exists checks for each feature snippet.

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

1. Require the vendored root `bootstrap.php` and register its candidate.
2. Let the shared resolver select one package before referencing any Core class.
3. Create `Hexa\PluginCore\CoreRuntime\PluginContext`.
4. Create `Hexa\PluginCore\CoreBootstrap\CoreBootstrap`.
5. Add core modules and host adapter modules, then call `boot()` once.

```php
$core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';
require_once $core_root . '/bootstrap.php';
\hexa_plugin_core_register_package( 'example-plugin', $core_root );

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

Package hygiene rules:

- Never ship or install nested VCS metadata inside a plugin package. Core excludes `.git`, `.svn`, `.hg`, `.bzr`, `.DS_Store`, and `Thumbs.db` from ZIP builders, direct installs, vendored Core installs, and GitHub plugin provisioning.
- Native WordPress plugin updates call a Core pre-install purge for the current plugin folder before WordPress starts copying files. If locked metadata cannot be removed, Core returns a clear `WP_Error` instead of letting WordPress dump a long copy-failure list.
- Do not append GitHub tokens or API keys to package URLs. If a private GitHub request needs auth, pass the token through the HTTP `Authorization` header only.

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

The host plugin updater and vendored core updater must use the same report shape:

```text
Git repo
Git URL
Git branch
Git version
current installed version
current-vs-Git comparison
green Current flag
red Outdated flag
Check for Updates button
Pull from GitHub button
normalized ZIP download without the GitHub -main folder suffix
live activity log for update work
```

Wrap each updater in one default-open `CoreUi` expandable section with browser-persistent collapse memory.

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

## Database Cleanup

Namespace: `Hexa\PluginCore\DatabaseCleanup`

Use `DatabaseCleanupService`, `DatabaseCleanupAjaxController`, and `DatabaseCleanupRenderer` for WP-Optimize-backed cleanup sessions. Core owns provider activation, task/table iteration, live reporting, and restoring the provider to its pre-run activation state. Hosts provide capability, nonce, AJAX names, labels, and the provider plugin file.

## Object Cache

Namespace: `Hexa\PluginCore\ObjectCache`

Use `LiteSpeedRedisService` to distinguish configured Redis from a working object cache. A healthy `active` result requires LiteSpeed settings, a drop-in, direct Redis connectivity, and a successful WordPress cache round trip. Hosts own AJAX guards and placement, not duplicate connection logic.

## Support

Namespace:

```text
Hexa\PluginCore\CoreRuntime
```

Core classes:

```text
CorePackageRuntime
PluginContext
```

Use `PluginContext` for host plugin identity. Do not hard-code host plugin names inside shared core classes.

`CorePackageRuntime::report()` exposes the single selected package root, every registered vendored candidate, package fingerprint mismatches, incompatible constraints, and Core classes loaded outside the selected root. Every host must use root `bootstrap.php`; competing plugin-specific autoloaders are forbidden.

## Checklist Workflow Extensions

`GettingStartedChecklistRenderer` exposes a host-neutral browser API on `root.hexaChecklistApi`. It dispatches `hexa:checklist:ready` and `hexa:checklist:run` events. A host with a specialized live workflow may claim only its own step by setting `event.detail.handled = true` and assigning `event.detail.promise`.

The API provides input collection/validation, row state, reports, logs, and guarded arbitrary-action POST requests. Host-specific step IDs, input keys, AJAX actions, CSS, and result mapping stay in the host plugin. Number inputs support `min`, `max`, and `step` with browser and server enforcement.

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
