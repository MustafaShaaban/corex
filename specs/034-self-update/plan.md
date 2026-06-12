# Implementation Plan: Self-update mechanism + distribution (034)
**Branch**: `feature/034-self-update` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
A pure `UpdateChecker` decides if an update is available (semver compare of a fetched manifest vs the current
version) and builds the WP update object. An `UpdateService` (corex-core) hooks `pre_set_site_transient_update_
plugins` + `plugins_api`, fetches the manifest from a configured endpoint (`wp_remote_get`), and injects the
update — a missing/malformed source is a safe no-op. The Corex plugin declares an **Update URI** so WordPress
routes the check to Corex (not wordpress.org). Updates replace only framework files; `corex-app/`, `brand.json`,
content, and data are never touched (documented safe-edit boundary). A distribution guide documents publishing a
manifest + package.

## Technical Context
PHP 8.3. Deps: spec-001 Config (`updates.endpoint`), WP plugin update API. Tests: Pest (UpdateChecker pure;
UpdateService transient injection with stubs). Constraints: fail-safe (no source → no-op); checking needs no
secret; only the framework plugin's files change.

## Constitution Check (v1.2.1)
- [x] III/IV — `UpdateChecker` pure; `UpdateService` the WP boundary; bound in CoreServiceProvider.
- [x] VII — `wp_remote_get` (not curl); no secret to check; the package install is WP's own (signed flow). Fail-safe.
- [x] IX — the update source is optional config; absent → no-op (never a hard dependency).
- [x] X — implements spec 034.
- [x] Guard Gate/DoD — wp-guard (remote get, transient), clean-code, test-guard; Pest checker + service; docs +
  docs-app (distribution + safe-edit boundary).

**Gate**: PASS.

## Design (in `plugins/corex-core/src/Update/`)
- `UpdateChecker` (pure): `check(string $current, array $manifest): ?array` → `{new_version,package,url}` iff
  `version_compare(manifest.version, current, '>')`.
- `UpdateService`: ctor `(UpdateChecker, string $pluginFile, string $currentVersion, ConfigInterface)`;
  `register()` adds the two filters; `checkUpdates($transient)` fetches the manifest + injects;
  `details($res,$action,$args)` for plugins_api; `fetchManifest()` via `wp_remote_get` (json), fail-safe.
- `corex-core.php`: add `Update URI: https://corex.dev` header (routes the check to Corex).
- Config default `config/app.php` (or features): `updates.endpoint` (empty default → no-op).
- Docs: a distribution guide (manifest shape + GitHub Releases) + the safe-edit boundary
  (`corex-app/`/`brand.json`/content/data are yours; framework plugins update).

## FR → component map
| FR | Built in |
|---|---|
| FR-001 checker | `Update/UpdateChecker.php` |
| FR-002 hooks + fetch + fail-safe | `Update/UpdateService.php` |
| FR-003 Update URI | `plugins/corex-core/corex-core.php` header |
| FR-004 safe boundary | docs (distribution guide) + by design (only framework files) |
| FR-005 endpoint config | `config/app.php` `updates.endpoint` |
| FR-006 distribution guide | `docs/en/04-team-workflow/` or `08-contributing/` + docs-app |

## Project Structure
```text
plugins/corex-core/src/Update/{UpdateChecker,UpdateService}.php
plugins/corex-core/src/Foundation/CoreServiceProvider.php (bind + boot UpdateService)
plugins/corex-core/corex-core.php (Update URI header)
plugins/corex-core/config/app.php (updates.endpoint)
tests/Unit/Update/{UpdateCheckerTest,UpdateServiceTest}.php
docs/en/05-deployment/updates-and-distribution.md
```

## Complexity Tracking
The checker/service split keeps the version logic pure + testable; WP installs the package. Live update-from-admin
is env-gated (needs a published manifest + browser).
