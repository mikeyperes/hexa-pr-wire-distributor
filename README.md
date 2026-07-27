# Hexa PR Wire Distributor

Press-release import, distribution, visibility, media, SEO, and management integration for the Hexa PR Wire network.

## Identity

- Repository: `mikeyperes/hexa-pr-wire-distributor`
- Plugin slug: `hexa-pr-wire-distributor`
- Namespace: `hpr_distributor`
- Version: `2.5.5`

## Ownership

Hexa PR Wire Distributor owns:

- The immutable `press-release` custom post type and its ACF/SEO field structures.
- Press-release imports, source mapping, asset reconciliation, and force-sync endpoints.
- Press-release loop visibility policies.
- Echo RSS rule checks and repair tools.
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

- A protected distributor force-sync REST endpoint.
- Source URL/slug validation and repair.
- Existing-post update and asset reconciliation.
- External featured-image and FIFU metadata maintenance.
- Echo RSS importer rule detection and enforcement.
- Cache purge hooks after successful synchronization.

## Dashboard

The dashboard uses Hexa WP Core tabs, collapsible sections, dynamic buttons, guarded AJAX, and activity logs. It includes overview, Going Live, Custom Post Types, snippets, Echo RSS settings, plugin/Core update reporting, and technical status tools.

Plugin and Core update panels come directly from Hexa WP Core. The retired custom updater and direct filesystem installer code have been removed.

## Architecture

`hexa-pr-wire-distributor.php` is the canonical entry. `initialization.php` is retained for compatibility. Focused implementation lives under `src/`, with legacy distribution endpoints isolated in their existing files.

Reusable updater, CPT, ACF, dashboard, AJAX, checklist, activity-log, and UI infrastructure comes from Hexa WordPress Plugin Core 0.19.78. The root [HEXA_PLUGIN_CORE_LIBRARY.md](HEXA_PLUGIN_CORE_LIBRARY.md) matches the bundled canonical package.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 5.0 |
| PHP | 8.0 |
| Hexa WP Core bundle | 0.19.78 |

ACF Pro is required for press-release field groups. Echo RSS and FIFU integrations are conditional on those plugins being active.

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
