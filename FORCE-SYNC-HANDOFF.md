# Hexa PR Wire Distributor Force Sync Handoff

This document is intentionally safe for Git. It explains how the force-sync API works, but it does not include the live shared network key.

## Purpose

The force-sync API lets Hexa PR Wire or a batch runner tell any publication site to immediately re-check its Hexa PR Wire Echo RSS feed instead of waiting for the normal hourly Echo RSS schedule.

The endpoint can:

- Run the detected Hexa PR Wire Echo RSS rule immediately.
- Target one source article by Hexa PR Wire slug, source URL, or local post ID.
- Repair imported post slugs after Echo finishes.
- Reconcile featured image metadata from the RSS item without repeatedly uploading media files.
- Purge WordPress and LiteSpeed cache for matched posts.
- Return machine-readable JSON for success, no-match, busy, unauthorized, and server-error cases.

## Shared Network Key

All publication sites use the same shared network key. The key is set in code by `hpr_force_sync_get_shared_token()` and synchronized into the WordPress option `hpr_force_sync_settings.secret_token` during plugin initialization.

Use one of these methods to get the key from a live site:

```bash
wp eval 'echo \hpr_distributor\hpr_force_sync_get_shared_token();' --allow-root
```

```bash
wp option get hpr_force_sync_settings --format=json --allow-root
```

The WordPress admin also displays it under:

```text
Hexa PR Wire -> Overview -> Force Syndication URL -> Shared Network Key
```

Do not paste the live key into Git documentation, issue trackers, screenshots, or public logs.

## Endpoint

Base endpoint:

```text
https://PUBLICATION_DOMAIN/wp-json/hpr-distributor/v1/force-sync
```

Preferred auth parameter:

```text
key=SHARED_NETWORK_KEY
```

Backward-compatible auth aliases:

```text
token=SHARED_NETWORK_KEY
sync_key=SHARED_NETWORK_KEY
```

## Common URLs

Force one source slug:

```text
https://PUBLICATION_DOMAIN/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY&slug=SOURCE_SLUG&feed_action=force
```

Force one source URL:

```text
https://PUBLICATION_DOMAIN/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY&source_url=https://hexaprwire.com/source-post/&feed_action=force
```

Force several slugs:

```text
https://PUBLICATION_DOMAIN/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY&slugs=slug-one,slug-two&feed_action=force
```

Force a local post ID:

```text
https://PUBLICATION_DOMAIN/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY&post_id=12345&feed_action=force
```

Dry run:

```text
https://PUBLICATION_DOMAIN/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY&slug=SOURCE_SLUG&dry_run=1
```

## Request Parameters

- `key`: Preferred shared network key parameter.
- `token`: Backward-compatible alias for `key`.
- `sync_key`: Backward-compatible alias for `key`.
- `slug`: One source slug from Hexa PR Wire.
- `slugs`: Comma-separated or newline-separated source slugs.
- `source_url`: One source URL from Hexa PR Wire.
- `source_urls`: Comma-separated or newline-separated source URLs.
- `post_id`: One local publication post ID.
- `post_ids`: Comma-separated or newline-separated local publication post IDs.
- `feed_action`: Usually `force`. Any non-empty value is passed to the Hexa PR Wire feed as `action=VALUE`.
- `dry_run`: Use `1`, `true`, `yes`, or `on` to inspect without running Echo or changing posts.

## Success Response

HTTP status: `200`

```json
{
  "success": true,
  "message": "Force syndication completed successfully.",
  "dry_run": false,
  "publication": "https://publication-domain.com/",
  "rule": {
    "id": 10,
    "active": true,
    "feed_url": "https://hexaprwire.com/?feed=rss_publication&publication=publication-slug&v=12312",
    "post_type": "press-release",
    "publication_slug": "publication-slug",
    "identity": "echo-rule-identity"
  },
  "requested_targets": {
    "source_slugs": ["source-slug"],
    "source_urls": [],
    "local_post_ids": [],
    "requested_post_ids": []
  },
  "feed_action": "force",
  "effective_feed_url": "https://hexaprwire.com/?feed=rss_publication&publication=publication-slug&v=TIMESTAMP&action=force",
  "feed_items_discovered": 142,
  "matched_feed_items": 1,
  "duration_ms": 8123,
  "echo_baseline": {
    "rules_checked": 1,
    "changed": 0,
    "changes": []
  },
  "slug_repair": {
    "checked": 353,
    "repaired": 0,
    "skipped": 353,
    "conflicts": [],
    "changed": [],
    "dry_run": false
  },
  "result": {
    "before_count": 353,
    "after_count": 353,
    "new_source_urls": [],
    "new_live_urls": [],
    "updated_source_urls": [],
    "updated_live_urls": [],
    "unchanged_source_urls": ["https://hexaprwire.com/source-post/"],
    "not_imported_source_urls": [],
    "missing_targets": [],
    "last_url_processed": "https://hexaprwire.com/source-post/",
    "up_to_date": true
  },
  "asset_sync": {
    "checked": 1,
    "updated": 0,
    "unchanged": 1,
    "created_attachments": 0,
    "reused_attachments": 1,
    "skipped": [],
    "changed": [],
    "errors": []
  },
  "cache_purge": {
    "checked": 1,
    "purged": ["https://publication-domain.com/press-release/source-slug/"],
    "skipped": []
  }
}
```

Important success fields:

- `matched_feed_items`: How many RSS items matched the request.
- `result.new_live_urls`: Newly imported live URLs.
- `result.updated_live_urls`: Existing live URLs whose title/content/excerpt changed.
- `result.unchanged_source_urls`: Matched items already present and unchanged.
- `result.not_imported_source_urls`: Items found in RSS but not imported after Echo ran.
- `result.missing_targets`: Requested slugs or source URLs not found in the RSS response.
- `result.up_to_date`: `true` only when there are no new, updated, or failed imports.
- `asset_sync.created_attachments`: Should normally stay `0` for existing posts.
- `asset_sync.reused_attachments`: Existing attachment shells reused and updated in place.
- `asset_sync.changed`: Detailed image metadata updates when a featured image changes.

## Dry Run Success Response

HTTP status: `200`

Dry runs return the same top-level success structure, but:

- `dry_run` is `true`.
- Echo RSS is not run.
- Slug repair is not applied.
- Featured image reconciliation is skipped.
- `asset_sync` and `cache_purge` are normally absent.

## Unauthorized Response

HTTP status: `403`

```json
{
  "success": false,
  "message": "Unauthorized.",
  "error": "invalid_force_sync_key",
  "token_mode": "shared-hardcoded"
}
```

This means the request did not include the shared key, or used the wrong value.

## Already Running Response

HTTP status: `429`

```json
{
  "success": false,
  "message": "A force sync is already running on this site."
}
```

The plugin sets a five-minute transient lock while a force sync is running.

## No Matching Feed Item Response

HTTP status: `404`

```json
{
  "success": false,
  "message": "No matching feed items were found for the requested target.",
  "publication": "https://publication-domain.com/",
  "rule": {
    "id": 10,
    "active": true,
    "feed_url": "https://hexaprwire.com/?feed=rss_publication&publication=publication-slug&v=12312",
    "post_type": "press-release",
    "publication_slug": "publication-slug",
    "identity": "echo-rule-identity"
  },
  "requested_targets": {
    "source_slugs": ["missing-source-slug"],
    "source_urls": [],
    "local_post_ids": [],
    "requested_post_ids": []
  },
  "effective_feed_url": "https://hexaprwire.com/?feed=rss_publication&publication=publication-slug&v=TIMESTAMP&action=force",
  "feed_items_discovered": 142,
  "matched_feed_items": 0
}
```

This means the publication endpoint is working, but the Hexa PR Wire RSS feed did not include the requested slug or URL.

## Server Error Response

HTTP status: `500`

```json
{
  "success": false,
  "message": "No active Hexa PR Wire Echo rule was detected on this site.",
  "error": "RuntimeException"
}
```

Common causes:

- Echo RSS is missing or inactive.
- No active Echo rule uses the `press-release` post type.
- The detected Echo rule feed URL is not a Hexa PR Wire `rss_publication` feed.
- The Hexa PR Wire feed request failed or returned invalid XML.
- The Echo RSS function `echo_run_rule()` is unavailable.

## Featured Image Behavior

The plugin reads the matched RSS item image in this order:

- First `media:content` image URL.
- Then the first image in RSS `description`.
- Then the first image in RSS `content:encoded`.

For existing posts, it updates metadata and the existing external attachment pointer in place:

- `echo_featured_img`
- `fifu_image_url`
- `fifu_image_alt`
- `_thumbnail_id`
- Attachment `_wp_attached_file`
- Attachment `_hpr_external_featured_image_url`

It does not download and re-upload the image every time. A new attachment shell is created only when the local post does not already have a usable thumbnail attachment.

## Batch Runner Pattern

For a publication list, build each URL like this:

```text
https://DOMAIN/wp-json/hpr-distributor/v1/force-sync?key=SHARED_NETWORK_KEY&slug=SOURCE_SLUG&feed_action=force
```

Treat the request as successful when:

- HTTP status is `200`.
- `success` is `true`.
- `matched_feed_items` is at least `1` for targeted runs.
- `asset_sync.errors` is empty.
- `result.not_imported_source_urls` is empty for required targets.

If public DNS does not point to the server being tested, use a forced DNS resolution in the batch runner and report that separately.
