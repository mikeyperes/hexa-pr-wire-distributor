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
