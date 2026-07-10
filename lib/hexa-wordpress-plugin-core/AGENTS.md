# Hexa WordPress Plugin Core Agent Instructions

These instructions are for Codex, Claude, and any other implementation agent modifying or consuming `hexa-wordpress-plugin-core`.

## Non-Negotiable Names

Use these exact names:

- Repository folder: `hexa-wordpress-plugin-core`
- Composer package: `hexa/plugin-core`
- Root PHP namespace: `Hexa\PluginCore\`
- Source folder: `src/`
- Version file: `VERSION`
- Package fingerprint: `PACKAGE_HASH`

Do not invent alternatives. Do not use host plugin names inside this package.

Forbidden shared-core namespaces:

- `HWS\BaseTools\PluginCore`
- `hws_base_tools`
- `HexaWordPressPluginCore`
- `Hexa\Core`
- `HexaWP`
- `HexaTiger`

Host plugin namespaces may exist only inside the host plugin repository. Shared code in this package must remain `Hexa\PluginCore`.

## Folder Contract

Each sub-namespace must have a matching folder:

```text
src/ActivityLog/        Hexa\PluginCore\ActivityLog
src/AcfFieldFactory/    Hexa\PluginCore\AcfFieldFactory
src/CoreBootstrap/      Hexa\PluginCore\CoreBootstrap
src/CoreContracts/      Hexa\PluginCore\CoreContracts
src/CorePackageUpdates/ Hexa\PluginCore\CorePackageUpdates
src/CoreRuntime/        Hexa\PluginCore\CoreRuntime
src/CredentialVault/    Hexa\PluginCore\CredentialVault
src/DatabaseCleanup/    Hexa\PluginCore\DatabaseCleanup
src/LogFiles/           Hexa\PluginCore\LogFiles
src/ObjectCache/        Hexa\PluginCore\ObjectCache
src/PluginProvisioning/ Hexa\PluginCore\PluginProvisioning
src/PluginUpdates/      Hexa\PluginCore\PluginUpdates
src/SnippetRegistry/    Hexa\PluginCore\SnippetRegistry
src/ShortcodeRegistry/  Hexa\PluginCore\ShortcodeRegistry
src/SiteStructure/      Hexa\PluginCore\SiteStructure
src/SmartSearch/        Hexa\PluginCore\SmartSearch
src/SystemEnvironment/  Hexa\PluginCore\SystemEnvironment
src/WpAdminAjax/        Hexa\PluginCore\WpAdminAjax
src/WpAdminComponents/  Hexa\PluginCore\WpAdminComponents
src/WpAdminTabs/        Hexa\PluginCore\WpAdminTabs
src/WpConfigFile/       Hexa\PluginCore\WpConfigFile
src/WpCronTasks/        Hexa\PluginCore\WpCronTasks
```

If you add a namespace, add it to `README.md`, `docs/folder-map.md`, and this file in the same change.

## Setup Protocol

Every host plugin must initialize the core in the same order:

1. Require root `bootstrap.php` and register the host package candidate.
2. Let the shared resolver select one package before any Core class is referenced.
3. Create `Hexa\PluginCore\CoreRuntime\PluginContext`.
4. Create `Hexa\PluginCore\CoreBootstrap\CoreBootstrap`.
5. Add modules and call `boot()` once.

Never make a module boot itself at file include time. Modules register hooks from their `register()` method only.

## Implementation Rules

- Put interfaces in `src/CoreContracts`.
- Put reusable ACF field array factories in `src/AcfFieldFactory`.
- Put runtime value objects and version metadata in `src/CoreRuntime`.
- Put reusable database cleanup sessions, table optimization, provider activation, and live row reporting in `src/DatabaseCleanup`.
- Put reusable object-cache provider status and activation adapters in `src/ObjectCache`.
- Put admin tab abstractions in `src/WpAdminTabs`.
- Put reusable visual primitives in `src/WpAdminComponents`.
- Put reusable error-log viewer/read/classification features in `src/LogFiles`.
- Put reusable plugin discovery, install, activation, GitHub ZIP provisioning, and folder-normalization helpers in `src/PluginProvisioning`.
- Put reusable snippet definitions, option toggles, test rules, related snippets, related shortcodes, basic README rendering, and AJAX handlers in `src/SnippetRegistry`.
- Put reusable API-key/secret storage, masking, and credential setup UI in `src/CredentialVault`.
- Put reusable smart search/X-Search endpoint and typeahead UI in `src/SmartSearch`.
- Put reusable critical page blueprints, assigned page storage, navigation menu creation, menu structure attachment, and page-to-menu-item tools in `src/SiteStructure`.
- Put activity log abstractions, storage modes, and the shared dark renderer in `src/ActivityLog`.
- Put shortcode registries, definitions, display renderers, examples, live output, and testing tools in `src/ShortcodeRegistry`.
- Put safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting in `src/SystemEnvironment`.
- Put host plugin GitHub/update configuration and updater abstractions in `src/PluginUpdates`.
- Put vendored core package version checks and core package update UI in `src/CorePackageUpdates`; do not treat the shared core as a WordPress plugin.
- Put WordPress admin-AJAX nonce/capability/request parsing/action registry/handler guards in `src/WpAdminAjax`.
- Put bootstrap/lifecycle orchestration in `src/CoreBootstrap`.
- Put safe `wp-config.php` constant and `ini_set()` reads/writes in `src/WpConfigFile`.
- Put reusable WP-Cron task scheduling, unscheduling, interval registration, and status inspection in `src/WpCronTasks`.

## WordPress Rules

- Core code may call WordPress functions only where the class is explicitly WordPress-facing.
- Generic value objects should not call WordPress functions.
- Do not hard-code `manage_options`; read capability from the context unless a host explicitly overrides it.
- Do not hard-code plugin slugs, GitHub repos, admin page slugs, paths, URLs, or versions.
- Do not duplicate credential/API-key storage or masking in host plugins.
- Do not duplicate typeahead search UI in host plugins.

## Documentation Rule

Any new public class or protocol must be documented in `docs/`.

Minimum documentation for a new implementation:

- namespace
- folder
- purpose
- host plugin responsibilities
- example usage
- testing method

For new plugin audits, start with `docs/new-plugin-master-checklist.md`. Do not invent a new audit checklist inside a host plugin.

Also update `HEXA_PLUGIN_CORE_LIBRARY.md` whenever a public namespace, class, setup protocol, or host integration pattern changes. That file is intended to be copied into every plugin that consumes the core.

The automatic core tab must remain host-neutral. Host plugins provide hook names; the implementation stays in `Hexa\PluginCore\WpAdminTabs`.

## Commit Hygiene

Do not create backup files. Do not commit generated vendor files unless explicitly requested. Keep implementation and docs in the same commit when they define a new public pattern.
