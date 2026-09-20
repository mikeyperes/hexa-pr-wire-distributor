# Distributor onboarding API

Contract: `hexa-pr-wire-distributor-onboarding` version `1.0`.

All onboarding routes require a WordPress-authenticated user with
`manage_options`. WordPress Application Password authentication is supported by
WordPress itself. The contract rejects password, token, cookie, authorization,
or secret fields and never stores login credentials.

## Routes

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/wp-json/hpr-distributor/v1/contract` | Contract/version and capability discovery |
| `GET` | `/wp-json/hpr-distributor/v1/onboarding/inspect` | Safe settings, readiness, migration, and last-run readback |
| `POST` | `/wp-json/hpr-distributor/v1/onboarding/plan` | Validate a desired configuration and return an exact diff without writes |
| `POST` | `/wp-json/hpr-distributor/v1/onboarding/configure` | Apply settings with an idempotent operation ID |
| `POST` | `/wp-json/hpr-distributor/v1/onboarding/reconcile` | Apply/replay settings, reconcile cron, migrate bounded legacy metadata, and verify |
| `GET` or `POST` | `/wp-json/hpr-distributor/v1/onboarding/verify` | Readiness and explicit dependency readback |
| `POST` | `/wp-json/hpr-distributor/v1/onboarding/force-sync` | Import exactly one reviewed existing source release and return destination evidence |
| `POST` | `/wp-json/hpr-distributor/v1/onboarding/rollback` | Restore only the settings snapshot owned by one operation |

## Configuration payload

Mutating calls require a stable 8-128 character `operation_id`. Supported
settings are `feed_url`, `publication_slug`, `enabled`, `schedule_enabled`,
`interval`, `author_id`, `post_status`, and `max_items`.

```json
{
  "operation_id": "outlet-her-forward-20260920",
  "settings": {
    "feed_url": "https://hexaprwire.com/?feed=rss_publication&publication=her-forward",
    "publication_slug": "her-forward",
    "enabled": true,
    "schedule_enabled": true,
    "interval": "hourly",
    "author_id": 21,
    "post_status": "publish",
    "max_items": 100
  }
}
```

Reusing an operation ID with identical settings returns `idempotent: true`.
Reusing it with different settings returns HTTP 409. Rollback is accepted only
when current settings still match that operation's applied snapshot, preventing
one run from overwriting a later run.

Readiness always reports `echo_rss_required: false`, `fifu_required: false`, and
`images_remain_on_source: true`.

## Onboarding Force Sync

The Publish adapter uses the administrator-authenticated onboarding route; it
does not receive or send the Distributor's private shared token. Supply one
stable `operation_id` and at least one source selector. If multiple selectors
are supplied, every selector must identify the same feed item.

```json
{
  "operation_id": "outlet-her-forward-sync-20260920",
  "source": {
    "source_id": "post:328228",
    "source_url": "https://hexaprwire.com/example-release/"
  }
}
```

The route requires exactly one matching feed item and returns the source
identity, destination post and URL, canonical link, required category, content
hash/structure comparison, source image host, deduplication evidence, native
import action, and explicit Echo RSS/FIFU dependency readback.
