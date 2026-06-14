# Implementation Plan: Asset manager & environments

**Branch**: `feature/047-asset-manager` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/047-asset-manager/spec.md`

## Summary

A formal asset layer in corex-core: pure resolution cores — `AssetEnvironment` (local/staging/production from
config), `BuildManifest` (source → hashed file + hash, read once + cached), and `AssetVersion` (the per-environment
version strategy: filemtime in local, manifest/content hash in production, framework/site version fallback) — wrapped
by a thin `AssetManager` boundary providing `url()`/`path()`/`version()` (junction/symlink-safe via the spec-040
normalisation; `plugins_url` + `filemtime` at the boundary). `wp corex assets:doctor` + `cache:clear` are WP-CLI-gated
commands over a pure `AssetReport`. No traversal outside the base; no public source maps in production unless enabled;
works with or without a build/manifest/WP-CLI.

## Technical Context

**Language/Version**: PHP 8.3. The version/manifest/environment cores are pure; URL/filemtime/CLI are a thin boundary.

**Primary Dependencies**: existing only — spec-040 `BlockPathResolver`/`PluginMountMap` (URL normalisation), spec-036
`COREX_*_VERSION` + Config, spec-018 build (which can emit a manifest), spec-003 generator/CLI pattern. No new dep.

**Storage**: none new — versions/manifest are computed/read from files; an optional transient caches the manifest.

**Testing**: Pest — `AssetEnvironment` (config → mode), `BuildManifest` (lookup/malformed/absent), `AssetVersion`
(per-env strategy + fallback + traversal guard). Live enqueue URLs/source-maps env-gated.

**Target Platform**: any Corex context (front-end enqueue, admin, CLI).

**Project Type**: WordPress framework monorepo — corex-core (`Corex\Assets\`) + a CLI command.

**Performance Goals**: manifest read once + cached; version is a filemtime/hash lookup; no per-request reparse.

**Constraints**: deterministic within a request; no `../` escape (FR-004); no public source map in prod by default
(FR-007); no secret in a manifest/diagnostic (FR-012); pure core (Principle III/IX).

**Scale/Scope**: 3 pure classes + 1 boundary manager + 1 `AssetReport` + the `assets:doctor`/`cache:clear` commands.

## Constitution Check

*GATE: pass before Phase 0; re-check after Phase 1.* (Corex Constitution v1.2.1.)

- [x] **I. Theme is a skin** — N/A (engine layer; the theme consumes it).
- [x] **II. Plugins boot themselves** — PASS. `Corex\Assets` lives in corex-core; bound by a provider; no theme dep.
- [x] **III. Thin controllers, fat services** — PASS. Version/manifest/environment are **pure**; `AssetManager` is a
  thin URL/filemtime boundary; the CLI command is a thin gated wrapper over a pure `AssetReport`.
- [x] **IV. Everything injected** — PASS. AssetManager + cores are container-wired; pure pieces are value objects.
- [x] **V. Runtime tokens** — N/A (assets are files; design tokens unaffected).
- [x] **VI. Conditional assets** — PASS (supportive): the manager gives correct versioned URLs to the existing
  conditional enqueues; it adds no global asset.
- [x] **VII. Declarative security** — PASS. A relative path is confined to its base (no `../` escape — FR-004); no
  public source map in production by default; no secret in a manifest/diagnostic (FR-012).
- [x] **VIII. RTL-first** — N/A.
- [x] **IX. No optional dep is hard** — PASS. Works with or without a build/manifest/WP-CLI; production-safe default
  when env is unset.
- [x] **X. Spec is source of truth** — PASS. Traces to spec 047; reuses 040/036/018/003 without re-speccing.
- [x] **Guard Gate + DoD** — clean-code (pure cores, thin boundary), wp-guard (traversal guard, escaped URLs,
  gated CLI, no secret), test-guard (Pest), docs-guard (an asset guide); PROGRESS/DECISIONS; NEXT STEP.

**Result: PASS — no violations.**

## Project Structure

```text
plugins/corex-core/src/Assets/
├── AssetEnvironment.php   # NEW — pure: config value → local|staging|production (production-safe default)
├── BuildManifest.php      # NEW — pure: manifest array → lookup(source): ?{file,hash}; malformed/absent → empty
├── AssetVersion.php       # NEW — pure: version(relative, mtime, ?manifestHash, env, fallbackVersion) → token + traversal guard
├── AssetManager.php       # NEW — boundary: url()/path()/version() (plugins_url + spec-040 normalise + filemtime)
├── AssetReport.php        # NEW — pure: the assets:doctor report (env, manifest present, samples, source-map exposure)
└── AssetsServiceProvider.php  # NEW — binds AssetManager; wires the manifest/env from Config

packages/cli/src/Commands/AssetsCommand.php   # NEW — assets:doctor + cache:clear (WP-CLI-gated) over AssetReport
docs-app/.../guides/assets.md                  # NEW — the asset helpers + environments + cache-busting
tests/Unit/Assets/ (Pest)                      # NEW — AssetEnvironment, BuildManifest, AssetVersion, AssetReport
```

**Structure Decision**: Follow the **spec-003/036 pattern** — a pure core (environment/manifest/version/report) +
a thin boundary (`AssetManager` for URL/filemtime, a `class_exists('WP_CLI')`-gated command). URL normalisation
reuses the spec-040 chokepoint so asset URLs are junction/symlink-safe. Nothing new is persisted beyond an optional
manifest transient.

## Complexity Tracking

> No Constitution Check violations — section intentionally empty.
