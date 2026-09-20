# Hexa PR Wire Distributor

Press-release import, distribution, visibility, media, SEO, and management integration for the Hexa PR Wire network.

## Identity

- Repository: `mikeyperes/hexa-pr-wire-distributor`
- Plugin slug: `hexa-pr-wire-distributor`
- Namespace: `hpr_distributor`
- Version: `3.1.0`

## Ownership

Hexa PR Wire Distributor owns:

- The immutable `press-release` custom post type and its ACF/SEO field structures.
- Press-release imports, source mapping, asset reconciliation, and force-sync endpoints.
- Press-release loop visibility policies.
- Native feed polling, import scheduling, durable source identity, and deduplication.
- Authenticated onboarding inspection, planning, configuration, reconciliation, verification, and rollback.
- Distributor author setup, external image sizing, and press-release SEO status.

## Custom Post Type

The **Custom Post Types** tab uses `Hexa\PluginCore\ContentTypes` for:

- Press Release enable/disable state.
- Editable public rewrite slug.
- Editable singular and plural WordPress labels.
- ACF field-group toggles and detailed field breakdowns.

The underlying key remains `press-release` so existing imports, templates, queries, and relationships remain valid. New installations enable the type by default; existing legacy state is preserved.

## Visibility

Press releases are hidden from ordinary post loops by default on:

- Home/posts index.
- Author archives.
- Category archives.
- Tag archives.
- Related-content queries on single posts.

Each context has an independent setting. Query filtering is aggressively scoped to frontend, main/public query contexts and does not broadly attach an unrestricted `pre_get_posts` mutation.

Direct press-release URLs and explicitly requested press-release queries remain available.

## Distribution

The plugin provides:

- A protected token-based force-sync endpoint plus an administrator-authenticated onboarding Force Sync endpoint.
- Source URL/slug validation and repair.
- Existing-post update and legacy Echo/FIFU metadata migration without changing WordPress post IDs.
- First-party remote featured-image rendering while every image remains hosted on `hexaprwire.com`.
- Guaranteed assignment of the destination `press-release` category while preserving source categories.
- Hourly scheduled polling, manual import, and native Force Sync without Echo RSS.
- Cache purge hooks after successful synchronization.

## Dashboard

The dashboard uses Hexa WP Core tabs, collapsible sections, dynamic buttons, guarded AJAX, and activity logs. It includes overview, Going Live, Custom Post Types, content rules, native Import & Sync, plugin/Core update reporting, and technical status tools.

Plugin and Core update panels come directly from Hexa WP Core. The retired custom updater and direct filesystem installer code have been removed.

## Architecture

`hexa-pr-wire-distributor.php` is the canonical entry. `initialization.php` is retained for compatibility. Focused implementation lives under `src/`, with legacy distribution endpoints isolated in their existing files.

Reusable updater, CPT, ACF, dashboard, AJAX, checklist, activity-log, and UI infrastructure comes from Hexa WordPress Plugin Core 1.0.0. The root [HEXA_PLUGIN_CORE_LIBRARY.md](HEXA_PLUGIN_CORE_LIBRARY.md) matches the bundled canonical package.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 5.0 |
| PHP | 8.0 |
| Hexa WP Core bundle | 1.0.0 |

ACF Pro is required for press-release field groups. Echo RSS required: no. FIFU required: no.

## Installation

Install the repository as `wp-content/plugins/hexa-pr-wire-distributor`, activate `hexa-pr-wire-distributor.php`, and run the Going Live checklist before enabling production imports.

## Development

Run architecture and unit contracts with:

```bash
php tests/architecture.php
php tests/unit-modules.php
```

Live verification must exercise the visible settings controls, one representative import/sync path, direct press-release output, every enabled exclusion context, schema/SEO status, and plugin/Core updater reporting.

## Changelog

### 3.1.0

- Replaced Echo RSS with Distributor-native scheduled, manual, and Force Sync importing.
- Added durable source-ID and canonical-URL deduplication with in-place legacy post migration.
- Replaced FIFU runtime behavior with first-party remote featured-image rendering; source images remain on `hexaprwire.com`.
- Added the authenticated onboarding REST contract with inspect, plan, configure, reconcile, verify, and operation-scoped rollback routes.
- Updated Going Live and diagnostics to report `Echo RSS required: no` and `FIFU required: no`.

### 3.0.3

- Restored the scoped `hpr_press_release_archive` Elementor query hook so intentionally requested press-release Loop Grids can render on front pages without weakening ordinary home-loop exclusions.
- Preserved the explicit-CPT and sticky-post protections introduced in 3.0.1 while recovering queries that the earlier Elementor-args filter had already marked empty.

### 3.0.2

- Preserved FIFU and CDN-transformed image URLs while repairing external press-release image dimensions.
- Prevented medium and thumbnail Elementor requests from falling back to oversized original media URLs.

### 3.0.1

- Preserved explicitly requested press-release-only loops while ordinary mixed content loops continue to honor visibility exclusions.
- Prevented WordPress sticky posts from contaminating dedicated press-release Elementor queries.

### 3.0.0

- Established the stable major baseline for press-release distribution, visibility, CPT, ACF, updater, and admin workflows.
- Updated all shared runtime and UI infrastructure to the canonical Hexa WP Core 1.0.0 bundle.
- Preserved existing Press Release keys, labels, slugs, imports, loop exclusions, and stored configuration.

### 2.5.6

- Defers the plugin composition root until `plugins_loaded`, after the shared Hexa WP Core runtime resolves.
- Restores registration of the reusable Custom Post Types AJAX controller on every admin request.

### 2.5.5

- Registered the Press Release CPT and ACF structures through Hexa WP Core with editable labels and rewrite slug.
- Replaced custom plugin/Core update pages and direct install logic with shared Core panels/controllers.
- Preserved default-enabled Press Release registration and existing legacy state.
- Updated the bundled Hexa WordPress Plugin Core to 0.19.78.
- Consolidated repository documentation.

## Support

Report issues at <https://github.com/mikeyperes/hexa-pr-wire-distributor/issues>.

## License

Proprietary Hexa PR Wire software unless a source file states otherwise.
