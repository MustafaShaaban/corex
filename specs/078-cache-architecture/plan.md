# Implementation Plan: Cache Architecture and Performance Management

**Branch**: `spec/078-cache-architecture` | **Date**: 2026-07-27 | **Spec**: [spec.md](spec.md)

## Summary

A registry that says what every CoreX cache entry *is*, a store that reads and writes through one
contract, a CLI that reports what it actually did, and a section that shows what is really caching.

**The registry comes first and everything else asks it.** Not because layering is tidy, but because
`corex_throttle_*` and `corex_captcha_seen_*` are security controls stored as transients, and every
clear path in this feature has to be incapable of touching them. A guard applied in the CLI protects
the CLI. A guard in the registry protects everything that will ever clear a cache — including the
admin action, a future scheduled cleanup, and whatever spec 084 wants.

So: **nothing in this feature deletes by pattern.** A clear iterates declared entries and skips
those whose classification forbids removal. A key nobody declared is a key nothing clears.

## Technical Context

**Language/Version**: PHP 8.3, JavaScript (`@wordpress/scripts`), CSS

**Primary Dependencies**: WordPress 7.0+ (transients, object cache, `wp_cache_*`), the section
architecture from spec 077, `AdminDateTime` from 076. No new runtime dependency, no new npm package.

**Storage**: Existing transients and object-cache groups. No schema change. No stored value moves.

**Testing**: Pest (unit + integration), Jest, Playwright.

**Target Platform**: shared hosting first — no Redis, no Memcached, no CDN, no page cache.

**Constraints**: no control without a real operation behind it; no state shown that was not checked;
no secret in diagnostics; works with no persistent object cache.

**Scale/Scope**: 8 declared cache entries, 7 layers reported, 1 new admin section, 3 CLI commands.

## Constitution Check

- [x] **I. Theme is a skin** — plugin-side only.
- [x] **II. Plugins boot themselves** — the registry and store bind in the core provider.
- [x] **III. Thin controllers, fat services** — the REST controller validates and delegates; every
      decision about what may be cleared lives in the registry.
- [x] **IV. Everything injected** — store, registry and manager resolve through the container.
- [x] **V. Runtime tokens** — the section's CSS is tokens only.
- [x] **VI. Conditional assets** — the section reuses the Operations screen's existing bundle.
- [x] **VII. Declarative security** — the clear route declares nonce + capability; scopes are an
      allow-list, never a free string.
- [x] **VIII. RTL-first** — logical properties.
- [x] **IX. No optional dep is hard** — Redis, page cache and CDN are all absent by default and the
      product is complete without them. This is the principle this spec exercises hardest.
- [x] **X. Spec is source of truth** — traces to spec.md.
- [x] **Guard Gate + DoD** — `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.

**No violations.**

## Project Structure

```text
plugins/corex-core/src/Cache/
├── CacheClassification.php     # what a stored value IS — decides what may remove it
├── CacheEntry.php              # one declared entry: key/prefix, owner, class, TTL, invalidation
├── CacheRegistry.php           # the inventory. The only thing that knows what is clearable
├── CacheScope.php              # the allow-list of scopes, and what each covers
├── CacheStore.php              # the contract
├── WordPressCacheStore.php     # transients + object cache, persistent-aware
├── ArrayCacheStore.php         # deterministic, for tests
├── CacheManager.php            # get/put/forget/remember/forgetNamespace + clear(scope)
├── CacheOutcome.php            # cleared / skipped / unsupported / failed, with reasons
└── Status/
    ├── CacheLayer.php          # one reported layer
    ├── CacheLayerState.php     # active | available | not-detected | unsupported | degraded | error | unknown
    ├── CacheStatusReport.php   # every layer, assembled from real checks
    ├── ObjectCacheProbe.php    # is a persistent object cache actually in use
    ├── OpcacheProbe.php        # truthful, survives disabled inspection functions
    ├── PageCacheProbe.php      # detection only
    └── CdnProbe.php            # detection only

plugins/corex-config/src/Security/Sections/          # the 077 extraction, as a pure move
├── OverviewSection.php  EnvironmentSection.php  LoginProtectionSection.php
├── HardeningSection.php  ActivitySection.php
└── CacheSection.php                                  # NEW — the section this spec adds

plugins/corex-config/src/Cache/CacheController.php    # REST: status + scoped clear
packages/cli/src/CliServiceProvider.php               # MODIFIED: status, doctor, scoped clear
```

**Structure Decision**: the cache contract lives in `corex-core` because `ThrottleMiddleware` (core)
and `TokenReplayGuard` (an addon) both hold declared entries — the registry cannot live in
`corex-config` and still describe them. The admin surface lives in `corex-config` with the rest of
the screen.

## Approach

### 1. The registry, and why it is not a pattern match

Each entry declares its key or prefix, its owner, its classification, its lifetime and how it is
invalidated. The eight that exist today, from `evidence/before/cache-inventory.md`:

| Entry | Classification | Clearable |
|---|---|---|
| `corex_asset_manifest` | safe cache | yes |
| `corex_form_submission_counts` (group `corex`) | safe cache | yes |
| `corex_throttle_*` | **security state** | **never** |
| `corex_captcha_seen_*` | **security state** | **never** |
| data-mutation preview tokens | pending operation | only with warning |
| migration preview tokens | pending operation | only with warning |
| bulk preview tokens | pending operation | only with warning |
| import preview (per user) | pending operation | only with warning |

`CacheClassification::mayBeClearedRoutinely()` answers for each. `CacheRegistry::clearable( $scope )`
returns only entries that pass, and **clearing walks that list** — there is no code path in this
feature that deletes by pattern, so no future scope can accidentally widen into
`corex_throttle_*`.

That is worth stating as a rule rather than a habit: **a `DELETE ... LIKE 'corex_%'` is not
refactorable into this design**, which is the point.

### 2. The store

`CacheStore` offers get / put / has / forget / remember / forgetNamespace, with an explicit
`isPersistent()` so callers and the status report can tell what they are standing on.
`WordPressCacheStore` uses the object cache when `wp_using_ext_object_cache()` is true and transients
otherwise — both are WordPress mechanisms, so shared hosting is the normal path, not a fallback.

`remember()` takes a lock so two simultaneous misses do not both regenerate. The lock is
best-effort: on a site with no persistent cache the guarantee is weaker, and the method's contract
says so rather than implying otherwise.

Every failure returns a miss. A cache that throws is worse than a cache that is empty.

### 3. Scopes, and refusing to do what we cannot

| Scope | Does |
|---|---|
| `corex` (default) | clears the declared safe-cache entries and nothing else |
| `assets` | the build manifest and asset metadata |
| `runtime` | the request object cache for this process |
| `object` | `wp_cache_flush()` — **opt-in only**, because it hits every other plugin |
| `expired-transients` | removes only already-expired CoreX transients |
| `page` | delegates to a detected page-cache provider |
| `cdn` | delegates to a configured CDN provider |

`page` and `cdn` have Null providers by default that report `unsupported` with a reason. **No CDN
integration is written**, and no credential from Insights is reused — FR-023 exists because that
would be the tempting shortcut.

Every operation returns a `CacheOutcome` naming what was cleared, skipped, unsupported and failed.
"Success" is not a valid answer on its own.

### 4. Status, from real checks only

The trap FR-017 names is specific: a Redis container being present is not Redis being used.
`ObjectCacheProbe` asks `wp_using_ext_object_cache()` and inspects the drop-in — the question is
whether *WordPress* is using one, which is the only question that affects this site.

`OpcacheProbe` survives `opcache_get_status()` being disabled, which many hosts do: that returns
`unknown`, not `error` and not `off`. Reporting "off" for "not allowed to look" would be a confident
wrong answer of exactly the kind this project keeps removing.

The browser layer is reported truthfully and pointedly: CoreX **cannot** clear a visitor's cache, and
the panel says so next to the asset-version strategy that actually solves the problem people bring
to it.

### 5. The admin section and the 077 extraction

`CacheSection` joins the section list 077 built. While there, the five existing renderers move into
`Sections/` — 077 deferred that so the move could land in a diff containing only the move, and this
is that diff. The move is mechanical: no markup changes, no behaviour changes, and the existing
`OperationsSectionsTest` proves it by still passing untouched.

Actions post to a REST route with nonce and capability, an allow-listed scope, confirmation for
broad scopes, and an audit entry carrying actor, time, scope, environment, provider and outcome.

### 6. What is not built

A page-cache engine. A CDN client. A Redis client (WordPress drop-ins own that). Any control whose
provider is absent — those render disabled with the reason, which is the honest form of "not
available here".

## Complexity Tracking

No violations.

One judgement recorded: the registry could have been a simple array of key prefixes, and the
classification a comment. It is objects because FR-002 requires the classification to be *in code* —
the whole risk this spec addresses is a future contributor adding `corex_throttle_` to a list of
things to clear, and a comment does not stop that while a `mayBeClearedRoutinely()` that returns
false does.
