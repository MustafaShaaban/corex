# Implementation Plan: Junction/Symlink-Safe Block Asset URLs

**Branch**: `feature/040-block-asset-urls` | **Date**: 2026-06-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/040-block-asset-urls/spec.md`

## Summary

Block registration today hands `register_block_type()` the on-disk directory of each block,
computed by every provider as `dirname(__DIR__) . '/build/blocks'`. WordPress derives the asset
URLs from that path via `plugins_url()`, which only works when the path sits under `WP_PLUGIN_DIR`.
Under the current Windows-junction mount it does, so all 33 asset URLs are correct — but if that
path is ever realpath-resolved to the real monorepo location outside `WP_PLUGIN_DIR` (POSIX symlink
mounts, a `realpath()` call, or the PHP realpath cache), `plugins_url()` cannot strip the prefix and
emits a malformed URL embedding an absolute filesystem path, 404-ing the asset and silently breaking
the block in the editor.

**Approach.** Insert one normalization step at the single chokepoint every provider already routes
through — `DynamicBlockRegistrar::register()` — that maps a discovered block directory back to its
`WP_PLUGIN_DIR`-relative mount location before calling `register_block_type()`. The mapping logic is a
pure, headless-testable `BlockPathResolver` (string prefix arithmetic over a mount map); the thin WP
boundary builds the mount map by `realpath()`-scanning the plugin-dir entries. Already-correct paths
return unchanged (byte-for-byte no-regression). Add a `BlockAssetsProbe` (pure judgement) wired into
the existing spec-036 `HealthModule` so a non-resolving asset URL surfaces in **Site Health** and
`wp corex doctor` instead of failing silently. No build/`block.json`/discovery-contract change; no new
dependency.

## Technical Context

**Language/Version**: PHP 8.3 (framework); no JS/build change.

**Primary Dependencies**: WordPress ≥ 7.0 (`register_block_type`, `plugins_url`, `WP_PLUGIN_DIR`,
`WP_Block_Type_Registry`, Site Health `site_status_tests`); Corex PSR-11 container. No new dependency.

**Storage**: N/A (no persistence; pure path/URL computation at registration + read-only health probe).

**Testing**: Pest (unit) — `BlockPathResolver` and `BlockAssetsProbe` are pure and tested headlessly
with Brain Monkey for the WP-boundary touches; existing block-registration integration stays green.

**Target Platform**: WordPress on any mount style — Windows junction, POSIX symlink, copied/CI checkout,
Docker/wp-env. Behaviour must be mount-independent.

**Project Type**: WordPress framework monorepo (`corex-blocks` plugin + `corex-core` health seam;
add-on providers consume the fixed chokepoint with no change).

**Performance Goals**: Negligible — the mount map is built once per request at block-registration time
(`init`); the resolver is string arithmetic. The probe runs only on Site Health / `doctor` invocation.

**Constraints**: Behaviour-preserving for the working junction setup (FR-005); no new runtime/build
dependency (FR-008); pure core stays WordPress-independent (Principle IV, testability).

**Scale/Scope**: ~12 registered `corex/*` blocks across 5 providers; one resolver class, one probe
class (+ a small pure helper for URL judging), one injection point, one HealthModule probe-list line.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design — still PASS.*

Derived from `.specify/memory/constitution.md` (v1.2.1).

- [x] **I. Theme is a skin** — N/A. No theme code; this is plugin-side block registration + a health probe.
- [x] **II. Plugins boot themselves** — PASS. The fix lives in `corex-blocks` registration (runs on `init`)
  and `corex-core` health (on `plugins_loaded`/`site_status_tests`); no theme dependency, works in CLI
  (`wp corex doctor`), admin (Site Health), REST, cron.
- [x] **III. Thin controllers, fat services** — PASS. `DynamicBlockRegistrar` stays thin (delegates mapping
  to the injected `BlockPathResolver`); judgement logic lives in the pure resolver + probe, not inline.
- [x] **IV. Everything injected** — PASS. `BlockPathResolver` is resolved from the container and injected
  into `DynamicBlockRegistrar`; the probe is built in the `HealthModule` boundary (mirrors `BrandPresentProbe`).
  No `new` of a dependency inside a method.
- [x] **V. Runtime tokens** — N/A. No styling introduced.
- [x] **VI. Conditional assets** — PASS (preserved). `block.json` still declares assets; only the URL *base*
  is corrected, so blocks still load CSS/JS conditionally exactly as before.
- [x] **VII. Declarative security** — N/A for routes (none added). The probe is read-only; its Site Health
  output is escaped in the existing `HealthModule::toSiteHealthTest()` (`esc_html`), and the CLI is local.
- [x] **VIII. RTL-first** — N/A. No UI beyond probe label/description strings (i18n via `corex` domain).
- [x] **IX. No optional dep is hard** — N/A. Uses only WordPress core APIs; no optional plugin involved.
- [x] **X. Spec is source of truth** — PASS. This plan traces to the approved `specs/040-block-asset-urls/spec.md`.
- [x] **Guard Gate + Definition of Done** — acknowledged. Diffs run `clean-code-guard` + `wp-guard`
  (registration/health touch WP APIs) + `test-guard` (Pest) + `docs-guard` (READMEs/docs-app). Unit tests,
  i18n strings, no UI (WCAG N/A beyond Site Health's own markup), docs + PROGRESS/DECISIONS updated.

**Result: PASS — no violations. Complexity Tracking not required.**

## Project Structure

### Documentation (this feature)

```text
specs/040-block-asset-urls/
├── plan.md              # This file
├── spec.md              # Feature spec (/speckit-specify)
├── research.md          # Phase 0 — decisions (mount-map mapping, URL-health rule)
├── data-model.md        # Phase 1 — entities (mount map, resolver I/O, asset-URL health result)
├── quickstart.md        # Phase 1 — how to validate (synthetic realpath path + live doctor run)
├── contracts/
│   └── block-path-resolver.md   # The pure resolver + probe contracts
├── checklists/
│   └── requirements.md  # Spec quality checklist (done)
└── tasks.md             # /speckit-tasks output (NOT created here)
```

### Source Code (repository root)

```text
plugins/corex-blocks/
├── src/
│   ├── BlockPathResolver.php          # NEW — pure: map a block dir to its WP_PLUGIN_DIR-relative path
│   ├── PluginMountMap.php             # NEW — thin WP boundary: realpath-scan plugin dir → mount map
│   ├── DynamicBlockRegistrar.php      # EDIT — normalize $block['dir'] via the resolver before register_block_type
│   └── BlocksServiceProvider.php      # EDIT — bind BlockPathResolver + PluginMountMap in the container
└── tests/ (Pest) → repo-root tests/Unit/Blocks/
    ├── BlockPathResolverTest.php      # NEW — realpath-resolved-outside-plugins maps back; no-op when already under
    └── BlockAssetsProbeTest.php       # NEW — healthy set passes; a malformed URL fails + names the block

plugins/corex-core/
├── src/Health/
│   ├── Probes/BlockAssetsProbe.php    # NEW — pure: judge a collected map of block→asset-URLs
│   ├── AssetUrlHealth.php             # NEW — pure helper: is one URL a well-formed under-plugins public URL?
│   └── HealthModule.php               # EDIT — collect registered corex/* block asset URLs, add BlockAssetsProbe
```

**Structure Decision**: Single-chokepoint fix in `corex-blocks` (every provider already calls
`DynamicBlockRegistrar::register()`, so no provider file changes — satisfies FR-003 uniformly with one
edit). The observability probe lives in `corex-core`'s existing Health namespace and is appended to the
`HealthModule::report()` probe list, so it flows into both Site Health and `wp corex doctor` with no new
wiring (spec-036 seam reuse). Pure classes (`BlockPathResolver`, `AssetUrlHealth`, `BlockAssetsProbe`) are
WordPress-independent and unit-tested headlessly; `PluginMountMap` + the URL collection in `HealthModule`
are the only WP-touching boundaries.

## Complexity Tracking

> No Constitution violations — section intentionally empty.
