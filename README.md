# hpr-hexa-pr-wire-distributor-integration
WordPress Plugin: Distributor integration for Hexa PR Wire

## Force Syndication URL

Version `2.2` adds a public force-syndication endpoint directly inside the existing distributor plugin.

- Base endpoint: `/wp-json/hpr-distributor/v1/force-sync?token=YOUR_SECRET`
- Target one article: append `&slug=your-source-slug`
- Target by source URL: append `&source_url=https://hexaprwire.com/your-post/`
- Dry run: append `&dry_run=1`

Behavior:

- The plugin auto-detects the active Echo `rss_publication` rule for the `press-release` post type.
- Targeted requests automatically switch to `action=reprocess-all` so older source items can be imported immediately.
- The response is JSON and includes `new_source_urls`, `new_live_urls`, `updated_source_urls`, `updated_live_urls`, `missing_targets`, `last_url_processed`, and `up_to_date`.
- The long random token is generated automatically and displayed in the plugin dashboard.


## Echo RSS Settings

Version `2.3.0` adds an `Echo RSS Settings` tab to the distributor dashboard. It detects the Hexa PR Wire Echo rule, enforces `update_existing=1` and `copy_slug=1`, previews and repairs imported press-release slug mismatches, logs Echo RSS modifications, and exposes controls to check or run the Echo RSS plugin update through WordPress normal upgrader.

The force-syndication endpoint now applies the Echo baseline before live runs and repairs imported slugs after Echo finishes, so source URLs like `echo_post_full_url` stay aligned with local `post_name` values.
