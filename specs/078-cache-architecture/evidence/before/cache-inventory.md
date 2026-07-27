# Every cached value in CoreX, before Spec 078

Read from the source on 2026-07-27, at `f32de72`. Nothing changed to produce it.

## `wp corex cache:clear` in full

```php
WP_CLI::add_command( 'corex cache:clear', static function (): void {
    delete_transient( 'corex_asset_manifest' );
    WP_CLI::success( 'Corex asset cache cleared.' );
} );
```

One key. The success message says "asset cache", which is honest about what it did — but the
command is named `cache:clear`, and an operator reaching for it after a confusing page has every
reason to believe it cleared CoreX's caches.

## What it does not touch

Thirteen call sites across seven files, all prefixed `corex_`:

| Key | Where | Holds | Kind |
|---|---|---|---|
| `corex_asset_manifest` | `AssetManager` | build manifest | **safe cache** |
| `corex_form_submission_counts` (group `corex`) | `WpSubmissionCounts` | counts, 1-minute TTL | **safe cache** |
| `corex_throttle_<md5>` | `ThrottleMiddleware` | request counts per window | **security state** |
| `corex_captcha_seen_<hmac>` | `TokenReplayGuard` | spent captcha tokens, 150s | **security state** |
| preview token | `WpDataMutationPreviewStore` | a data change awaiting confirmation | **pending operation** |
| preview token | `WpMigrationPreviewStore` | a migration awaiting confirmation | **pending operation** |
| preview token | `WpSubmissionBulkPreviewStore` | a bulk action awaiting confirmation | **pending operation** |
| per-user key | `DataModelsImportController` | an import awaiting confirmation | **pending operation** |

## Why this matters more than tidiness

The obvious implementation of "clear CoreX's caches" is a sweep of `corex_*` transients. On this
codebase that sweep would:

- **delete every rate-limit counter** (`corex_throttle_*`), resetting the protection at the exact
  moment an operator is most likely to be clicking it — while something is going wrong;
- **delete every spent-captcha record** (`corex_captcha_seen_*`), re-opening the replay window that
  guard exists to close;
- **invalidate every pending confirmation token**, so an operator mid-way through confirming a bulk
  action, an import or a migration finds their token gone and has to start again.

Three of those eight entries are safe to clear. Two are security controls wearing a transient's
clothes, and three are work in progress. Classification is not bookkeeping here — it is the thing
that keeps a cache button from becoming a security hole.

## What exists beyond transients

- **Docker Redis**: present as an optional development service.
- **OPcache**: available, configuration-dependent, not surfaced anywhere in the product.
- **Asset versioning**: `AssetManager::version()` with a build manifest — the mechanism that makes
  browsers fetch updated files. Real, working, and the thing most often confused with "clearing the
  browser cache", which CoreX cannot do.
- **No page cache, no CDN integration, no object-cache status surface** anywhere in the admin.
