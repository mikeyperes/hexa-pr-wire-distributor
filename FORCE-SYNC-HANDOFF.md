# Distributor Force Sync contract

Hexa PR Wire Distributor imports directly from the configured
`https://hexaprwire.com/?feed=rss_publication&publication=<slug>` feed. Echo RSS
and FIFU are not required.

## Endpoint

`POST /wp-json/hpr-distributor/v1/force-sync`

Authenticate with the locally generated Distributor credential in the
`X-HPR-Token` header. The credential is generated and stored by the destination
site; callers must not include it in onboarding configuration payloads.

Publish onboarding uses the separate
`POST /wp-json/hpr-distributor/v1/onboarding/force-sync` route with ordinary
WordPress administrator authentication. That route never accepts the shared
token and imports exactly one reviewed existing release.

Optional targeting fields:

- `slug` or `slugs`
- `source_url` or `source_urls`
- `source_id` or `source_ids`
- `post_id` or `post_ids`
- `dry_run=1`
- `feed_action=force`

The importer is bounded by `max_items` (default 100, maximum 250). A targeted
request that does not match a feed item returns HTTP 404. A concurrent run
returns an error without starting a second importer.

## Identity and deduplication

Every imported item receives Distributor-owned metadata:

- `_hpr_source_identity`
- `_hpr_source_id`
- `_hpr_canonical_source_url`
- `_hpr_source_guid`
- `_hpr_source_feed_url`
- `_hpr_source_content_hash`

Matching is attempted in that order, followed by legacy `original_post_url`,
`echo_post_full_url`, `echo_post_url`, and legacy `original_post_slug`.
Existing WordPress post IDs are updated in place. Each item result includes the
selected destination ID, every dedupe candidate, the matching evidence, and a
collision flag.

## Remote featured images

Images are never downloaded to a receiving publication. Distributor creates or
reuses a WordPress attachment shell, stores the source URL in
`_hpr_remote_featured_image_url`, and renders it through WordPress attachment
filters. Only `hexaprwire.com` (or its subdomains) is accepted. Legacy Echo and
FIFU metadata remains readable for migration, but neither plugin is called.

## Result

The top-level response includes `contract_version`, `plugin_version` (through
the contract endpoint), bounded counts, run duration, and `items`. Every item
returns:

- `source_identity`, `source_id`, `source_url`, `canonical_url`
- `source_title`, `source_content_sha256`
- `destination_post_id`, `destination_url`
- `image_source_url`, `image_url`, `image_attachment_id`
- `dedupe.matched_by`, `dedupe.candidate_post_ids`, and `dedupe.collision`

See [docs/ONBOARDING-CONTRACT.md](docs/ONBOARDING-CONTRACT.md) for the
authenticated configuration, verification, reconciliation, and rollback API.
