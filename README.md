# hpr-hexa-pr-wire-distributor-integration
WordPress Plugin: Distributor integration for Hexa PR Wire

## Force Syndication URL

Version `2.4.2` removes the user-profile ACF group from the distributor plugin. The press-release ACF group remains registered.

Version `2.4.1` removes the source-site Force Sync admin UI from this distributed plugin. Source-site force-sync controls belong in site-specific snippets on `hexaprwire.com`; this plugin keeps the target-site Distributor REST endpoint.

Version `2.2` adds a public force-syndication endpoint directly inside the existing distributor plugin.

- Base endpoint: `/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY`
- Target one article: append `&slug=your-source-slug`
- Target by source URL: append `&source_url=https://hexaprwire.com/your-post/`
- Dry run: append `&dry_run=1`

Behavior:

- The plugin auto-detects the active Echo `rss_publication` rule for the `press-release` post type.
- Targeted requests automatically switch to `action=reprocess-all` so older source items can be imported immediately.
- The response is JSON and includes `new_source_urls`, `new_live_urls`, `updated_source_urls`, `updated_live_urls`, `missing_targets`, `last_url_processed`, and `up_to_date`.
- Version `2.3.5` uses one shared network key across every publication. The key is displayed in the plugin dashboard and can be read with `wp eval 'echo \hpr_distributor\hpr_force_sync_get_shared_token();' --allow-root`.
- Version `2.3.6` rejects Echo placeholder values like `%%custom_post_url%%` and `%%custom_post_slug%%` when mapping or repairing imported posts, so slug repair falls back to the real Hexa PR Wire source URL.
- Version `2.3.7` disables target-specific Rank Math redirects that point a valid imported source slug to `/press-release/custom_post_url/`, then clears the matching redirect cache during force-sync.
- Full API handoff and JSON response documentation lives in `FORCE-SYNC-HANDOFF.md`.


## Echo RSS Settings

Version `2.3.2` adds an `Echo RSS Settings` tab to the distributor dashboard. It detects the Hexa PR Wire Echo rule, enforces `update_existing=1` and `copy_slug=1`, previews and repairs imported press-release slug mismatches, logs Echo RSS modifications, and exposes controls to check or run the Echo RSS plugin update through WordPress normal upgrader.

The force-syndication endpoint now applies the Echo baseline before live runs and repairs imported slugs after Echo finishes, so source URLs like `echo_post_full_url` stay aligned with local `post_name` values.
Version 2.3.2 also adds post-force-sync asset reconciliation. The endpoint reads each matched RSS item media image, updates the existing external featured-image attachment and FIFU/Echo image metadata in place instead of uploading a new media file, then purges the matched post URL through WordPress and LiteSpeed hooks.


## 2.4.3
- Added Hexa WP Core 0.17.4 and the UI Cleanup tab for PR Wire editor metabox cleanup.

## 2.4.4
- Vendored Hexa WP Core 0.17.6 for the UI Cleanup save feedback fix.

## 2.4.5
- Added Snippets controls to hide press-release posts from home, author, category, tag, and related single-post loops with tightly scoped frontend query filters.

## 2.4.6
- Default-enabled the press-release loop hiding snippets and applied the enabled defaults to the live site.
