# Data Model: Junction/Symlink-Safe Block Asset URLs

No persistence. These are the in-memory value shapes the feature computes at block-registration time and
during a health run. All "pure" entities are WordPress-independent and unit-testable headlessly.

## Mount map

The reference frame for normalization, produced once per request by the WP boundary (`PluginMountMap`).

| Field | Type | Description |
|---|---|---|
| `pluginsDir` | `string` | `WP_PLUGIN_DIR`, forward-slash normalized, no trailing slash. The mount root. |
| `mounts` | `array<string,string>` | `realTargetPath => mountEntryName`. One entry per immediate child of `WP_PLUGIN_DIR`, where `realTargetPath = realpath(WP_PLUGIN_DIR/<entry>)` (resolves the junction/symlink to its real on-disk location). Real targets are forward-slash normalized. |

- Built lazily and memoized; rebuilt only per request (cheap: one `scandir` + `realpath` per entry).
- On a copied/CI mount with no links, `realTargetPath === WP_PLUGIN_DIR/<entry>` and every block dir is
  already-under → all no-ops.

## Block path resolution (pure — `BlockPathResolver`)

**Input**: `(string $blockDir, string $pluginsDir, array<string,string> $mounts)`
**Output**: `string` — the block dir expressed under `$pluginsDir`.

Rules (in order):
1. Normalize `$blockDir` to forward slashes (and lowercase drive letter on Windows).
2. If `$blockDir` is already prefixed by `$pluginsDir` → **return unchanged** (the junction/no-op case; FR-005).
3. Else, for each `realTargetPath => entry` in `$mounts`, if `$blockDir` starts with `realTargetPath . '/'`
   (or equals it) → **return** `$pluginsDir . '/' . $entry . <remainder>` where `<remainder>` is the part of
   `$blockDir` after `realTargetPath`.
4. If no mount matches → **return the original `$blockDir`** unchanged (un-mappable; the probe will flag any
   resulting bad URL). Never fabricate.

Validation / invariants:
- Output, when a mapping applied, MUST be a child of `$pluginsDir`.
- Idempotent: `resolve(resolve(x)) === resolve(x)`.
- Trailing-slash neutral: input with/without a trailing slash yields the matching output form.

## Asset-URL health predicate (pure — `AssetUrlHealth`)

**Input**: `(string $url, string $pluginsUrlBase)` where `$pluginsUrlBase` is e.g. `http://site/wp-content/plugins`.
**Output**: `bool` — `true` when the URL is a well-formed public asset URL under the plugins base.

Healthy iff ALL hold:
- `$url` is non-empty and begins with `$pluginsUrlBase . '/'`.
- The remainder after the base contains **no** drive-letter segment matching `~/[A-Za-z]:~`.
- The remainder contains no `://` and no `..` path segment.

(Empty/absent handle URLs are handled by the probe as "nothing to load" → not unhealthy.)

## Block-assets health input (collected by the boundary)

| Field | Type | Description |
|---|---|---|
| `blockName` | `string` | e.g. `corex/posts`. Only `corex/*` blocks are inspected. |
| `urls` | `list<string>` | All editor-script, view-script, script, editor-style, and style handle `src` URLs for that block (empty handles dropped). |

A `list<{blockName, urls}>` is passed to the probe.

## Block-assets health result (`BlockAssetsProbe → ProbeResult`)

Reuses the existing `Corex\Health\ProbeResult` value object (spec 036) unchanged.

| Field | Value |
|---|---|
| `status` | `HealthStatus::Critical` if any inspected URL fails `AssetUrlHealth::isHealthy`; else `HealthStatus::Good`. |
| `id` | `block_assets` |
| `label` | i18n: "Block assets" |
| `description` | Good: "All Corex block scripts and styles resolve correctly." / Critical: names the offending block(s). |
| `actions` | Critical: a hint that the plugin mount/path resolution is producing filesystem-path URLs and how to check (re-link the plugin under wp-content/plugins). |

## Relationships

```
HealthModule (WP boundary)
  ├─ PluginMountMap ──► mount map ─┐
  │                                ▼
  ├─ collects corex/* block URLs   BlockPathResolver (pure)  ◄── injected into DynamicBlockRegistrar
  │           │                                                   (used at register time, not in health)
  │           ▼
  └─ BlockAssetsProbe(urls, base) ──► AssetUrlHealth (pure) ──► ProbeResult ──► Site Health + `wp corex doctor`
```
