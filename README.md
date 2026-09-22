# Hexa PR Wire Distributor

Press-release import, distribution, visibility, media, SEO, and management integration for the Hexa PR Wire network.

## Identity

- Repository: `mikeyperes/hexa-pr-wire-distributor`
- Plugin slug: `hexa-pr-wire-distributor`
- Namespace: `hpr_distributor`
- Version: `3.3.1`

## Ownership

Hexa PR Wire Distributor owns:

- The immutable `press-release` custom post type and its ACF/SEO field structures.
- Press-release imports, source mapping, asset reconciliation, and force-sync endpoints.
- Press-release loop visibility policies.
- Native feed polling, import scheduling, durable source identity, and deduplication.
- Authenticated onboarding inspection, planning, configuration, reconciliation, verification, and rollback.
- Distributor author setup, external image sizing, and press-release SEO status.

## Custom Post Type

The **Content Model & ACF** tab uses `Hexa\PluginCore\ContentTypes` for:

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
- Exclusive assignment of the destination `press-release` category; source-feed categories are never copied into the publication taxonomy.
- Hourly scheduled polling, manual import, and native Force Sync without Echo RSS.
- Fail-closed conflict detection when a matching Hexa PR Wire Echo job can duplicate imports or FIFU can alter remote featured images.
- Three explicit Going Live actions: disable only the matching Echo job, disable only Echo RSS, or disable only FIFU. The Distributor never shuts them down automatically.
- Cache purge hooks after successful synchronization.

## Dashboard

The dashboard uses the Hexa WP Core sidebar, cards, controls, guarded AJAX, and activity log. Its grouped layout is:

- Dashboard: Overview with live totals, warnings, recent releases, source URLs, destination URLs, and image hosts.
- Distribution: Import & Sync, Cron & Runs, and Images from URL, including feed tests, dry runs, cursor batches, Force Pull, run history, cron attempts/successes, image inventory, URL testing, and repair previews.
- Settings: Content Model & ACF plus General Settings for fields, visibility, lifecycle, caching, SEO, sitemaps, and deletion previews.
- System: Going Live, Diagnostics, and Updates & Core.

Full imports use a durable source-identity cursor instead of repeatedly processing the first batch. Duplicate source identities fail closed, run storage is bounded, and dry runs remain available while a legacy dependency conflict blocks live writes.

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

### 3.3.1

- Made failed cron reports state the concise reason directly in the primary row while preserving the complete recorded error inside collapsed details.

### 3.3.0

- Rebuilt every Distributor settings surface as a single-column row flow with clear sections, ordered steps, concise primary results, and collapsed secondary details.
- Added dynamic Hexa WP Core save notices and explicit one-click controls for the matching Echo job, Echo RSS, and FIFU without automatic deactivation.
- Added a dedicated Cron & Runs tab with schedules, next runs, last attempts, last successes, bounded history, cursor details, and safe read-only tests.
- Made the publication binding read-only during ordinary saves and added before/after warnings when a non-default author is selected.
- Added post-and-image rows with external thumbnails and direct source-image links.

### 3.2.0

- Rebuilt the complete Distributor admin around the Hexa WP Core grouped sidebar and UI components.
- Added editable native RSS settings, live feed testing, dry runs, durable cursor batches, Force Pull, source-slug reconciliation, item results, and bounded run history.
- Added full-site remote-image inventory, Hexa-host validation, URL/dimension testing, and preview-first image repair.
- Added Content Model & ACF registration/value tests, consolidated General Settings, preview-first deletion synchronization, and Core activity logging.
- Expanded diagnostics for feed XML, REST, cron, duplicate source identities, image policy, ACF, legacy dependencies, bounded storage, and semantic Core compatibility.
- Changed source collisions to fail closed and report partial/failed runs instead of silently selecting a destination post.

### 3.1.4

- Replaced the blanket legacy-retirement action with three explicit, independently verified choices: disable the matching Echo job, disable Echo RSS, or disable FIFU.
- Echo RSS may remain active for unrelated work when the matching Hexa PR Wire job is disabled.
- Removed automatic multi-plugin shutdown; each action changes only the selected plugin or matching job and preserves stored data.

### 3.1.3

- Added explicit Echo RSS/FIFU conflict detection to readiness, diagnostics, Import & Sync, and the authenticated onboarding contract.
- Native imports now fail closed while either legacy plugin or its background work can run.
- Added a stored-data-preserving Going Live action that disables matching Hexa PR Wire Echo rules, clears legacy cron hooks, and deactivates Echo RSS and FIFU.

### 3.1.2

- Stopped mirroring source-feed categories into publication category taxonomies.
- Imported press releases now receive only the destination `Press Release` category, including during unchanged-item reconciliation.

### 3.1.1

- Added a post-import deduplication readback that proves the reviewed source identity resolves uniquely to the returned destination post.
- Force Sync now fails closed when the destination post cannot be uniquely verified after import.

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
