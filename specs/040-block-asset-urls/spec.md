# Feature Specification: Junction/Symlink-Safe Block Asset URLs

**Feature Branch**: `feature/040-block-asset-urls`

**Created**: 2026-06-13

**Status**: Draft

**Input**: User description: "Harden Corex's dynamic-block registration so every block's editor/view/style asset URL always resolves correctly regardless of how the plugin/add-on is mounted into wp-content/plugins (Windows junction, POSIX symlink, or a realpath-resolved/CI mount)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Blocks work no matter how the project is mounted (Priority: P1)

A developer sets up Corex on their machine or in CI. Depending on the operating system and tooling, the framework's plugins and add-ons are linked into WordPress's plugin directory in different ways — a Windows junction, a POSIX symlink, or a copied/CI checkout. In every case, when they open the block editor, every `corex/*` block loads its editor script, front-end view script, and stylesheet correctly: the blocks render in the inserter, are editable on the canvas, and apply their styles. No block silently fails because its JavaScript or CSS returned a 404.

**Why this priority**: This is the entire point of the feature. A block whose editor asset 404s shows "block not supported" / a blank panel and is unusable — yet nothing in the PHP error log flags it, so it fails silently. The framework promises blocks that "just work" across the documented dev setups (Windows WAMP/XAMPP, Linux, macOS, Docker, wp-env); a path-resolution quirk on any one of them breaks that promise. Today it works only because the addons happen to be mounted as junctions; the framework must not depend on that accident.

**Independent Test**: Take a block directory path that has been resolved to its real on-disk location *outside* `wp-content/plugins` (the failure shape that symlink mounts and realpath resolution produce) and confirm the framework still registers the block such that its computed asset URL is rooted under the site's `wp-content/plugins/<plugin>/…` — a URL that actually resolves over HTTP — rather than a malformed URL that embeds an absolute filesystem path.

**Acceptance Scenarios**:

1. **Given** an add-on whose block directory is reported at its real monorepo location outside `wp-content/plugins`, **When** the framework registers that block, **Then** the block's editor-script, view-script, and style URLs are all rooted at `…/wp-content/plugins/<plugin>/…` and return 200, not 404.
2. **Given** an add-on mounted as a Windows junction (the current setup), **When** the framework registers its blocks, **Then** the asset URLs are identical to before this change — no regression for the setup that already works.
3. **Given** a block directory that genuinely lives outside any plugin/mu-plugin/theme location (cannot be mapped to a public URL), **When** the framework attempts to register it, **Then** the framework registers it without producing a malformed URL and the situation is surfaced (see User Story 2) rather than emitting a broken asset link.

---

### User Story 2 - A broken asset URL becomes visible instead of silent (Priority: P2)

A site owner or developer opens **Tools → Site Health** (or runs the Corex doctor CLI). If any `corex/*` block's computed asset URL does not resolve under the site URL — for any reason, on any environment — a Site Health check reports it by name, so a misconfigured mount is diagnosed in seconds instead of being hunted down through "the block panel is blank" symptoms.

**Why this priority**: The original bug's worst trait was its silence — a 404 asset breaks the editor with nothing in the PHP log. Even with the P1 fix in place, making the failure observable protects against future mount configurations the fix didn't anticipate, and gives operators a first-class diagnosis. It depends on nothing in P1 and is independently valuable, but P1 is the actual fix, so this is P2.

**Independent Test**: With the health probe registered, force a block to a non-resolvable asset URL and confirm the Site Health screen (and `wp corex doctor`) reports that block as a problem with an actionable message; with all URLs healthy, confirm the check reports a clean/passing status.

**Acceptance Scenarios**:

1. **Given** every registered `corex/*` block has asset URLs rooted under the site URL, **When** Site Health runs, **Then** the Corex block-assets check reports a passing status.
2. **Given** at least one registered `corex/*` block has an asset URL containing an absolute filesystem path or otherwise not under the site URL, **When** Site Health runs (or `wp corex doctor` is run), **Then** the check reports a failing status that names the offending block(s) and points at the likely cause (plugin mount/path resolution).

---

### Edge Cases

- **Block with no assets**: a server-rendered block that ships no editor script, view script, or style (e.g. `corex/copyright`) — the normalization is a no-op and the health probe treats "no assets" as healthy.
- **Path already correct**: when the discovered directory is already under `WP_PLUGIN_DIR`, normalization must return it unchanged (no double-mapping, no trailing-slash drift).
- **Mixed separators / casing (Windows)**: backslash vs forward-slash and drive-letter casing must not defeat the "is this under the plugins dir?" comparison.
- **Block mounted under mu-plugins or inside the active theme** rather than `wp-content/plugins`: the framework currently discovers blocks only from its own plugins/add-ons, so this is out of scope for normalization, but the health probe MUST NOT crash on such a block and MUST report it as un-mappable rather than passing it off as healthy.
- **Nested symlink/junction in the path** (e.g. the whole `wp-content` is itself linked): the normalization maps against the effective `WP_PLUGIN_DIR` as WordPress sees it, so it stays correct as long as WordPress's own `plugins_url()` base is consistent.
- **Realpath cache warm vs cold**: the result must not depend on whether PHP's realpath cache has resolved the link, since that timing is non-deterministic across requests.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The framework MUST register every discovered `corex/*` block such that its editor-script, view-script, and style URLs resolve under the site's plugins URL (`…/wp-content/plugins/<plugin>/…`), regardless of whether the block's on-disk directory is reported via a junction, a symlink, or its realpath-resolved absolute location.
- **FR-002**: The framework MUST provide a single, pure (WordPress-independent), headless-testable normalization step that, given an absolute block directory path that may have been resolved outside the plugins directory, returns the equivalent path expressed under `WP_PLUGIN_DIR` (its mount-relative location); when the input is already under the plugins directory it MUST return it unchanged.
- **FR-003**: The normalization MUST be applied uniformly to every Corex block source — the `corex-blocks` plugin and every add-on provider (corex-ui, corex-forms, corex-careers, corex-portfolio, and any future add-on) — so no registration path is left unguarded.
- **FR-004**: When a block directory cannot be mapped to a location under the plugins directory, the framework MUST NOT emit a malformed asset URL (one embedding an absolute filesystem path); it MUST register the block by the best available means and the situation MUST be detectable via the health check (FR-006).
- **FR-005**: The change MUST be behavior-preserving for the currently working junction setup — asset URLs for already-correct mounts MUST be byte-for-byte identical to today's output.
- **FR-006**: The framework MUST expose a health check, integrated into WordPress Site Health and the existing `wp corex doctor` report, that inspects every registered `corex/*` block's asset URLs and reports a failing status (naming the offending block(s) and the likely cause) when any URL does not resolve under the site URL, and a passing status otherwise.
- **FR-007**: A regression test MUST prove that a block directory deliberately presented at a realpath-resolved location outside the plugins directory still yields an asset URL rooted under `wp-content/plugins`, and that a block already under the plugins directory is unaffected.
- **FR-008**: The feature MUST NOT change the build pipeline, any `block.json`, or the discover-by-convention contract, and MUST NOT add any new runtime or build dependency.

### Key Entities *(include if feature involves data)*

- **Block directory descriptor**: the absolute on-disk path of a discovered block (the `dir` field already produced by block discovery), which this feature normalizes to its plugins-relative mount location before registration.
- **Plugins mount root**: WordPress's plugin directory (`WP_PLUGIN_DIR`) and its public URL base (`plugins_url()`), the reference frame the normalization maps a block directory into.
- **Block-assets health result**: a pass/fail report listing, per registered `corex/*` block, whether its computed asset URLs resolve under the site URL — consumed by Site Health and the doctor CLI.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of registered `corex/*` blocks load their editor script, view script, and style with HTTP 200 across all of the documented mount styles (Windows junction, POSIX symlink, copied/CI checkout) — zero 404 assets.
- **SC-002**: For the existing junction-based dev environment, the set of computed block asset URLs is unchanged from before the feature (no regression).
- **SC-003**: A block whose asset URL fails to resolve under the site URL is surfaced by the Site Health check and `wp corex doctor` within one run, named individually, with zero such failures escaping silently.
- **SC-004**: The normalization logic is covered by headless unit tests that pass without a running WordPress, including the realpath-resolved-outside-plugins regression case and the already-correct no-op case.
- **SC-005**: No new runtime or build dependency is introduced, and no `block.json` or build-pipeline file is modified.

## Assumptions

- Corex blocks are discovered only from Corex's own plugins and add-ons, all of which are mounted under `wp-content/plugins` (as junctions on Windows, symlinks on Linux/macOS, or copies in CI/Docker) — not from mu-plugins or the theme. The health probe still degrades gracefully if it ever encounters an un-mappable block.
- WordPress's own `plugins_url()` / `WP_PLUGIN_DIR` correctly describe the public-facing plugins location for the site; the normalization maps into that frame rather than inventing its own URL base.
- The failure mode being hardened against (an asset URL embedding an absolute filesystem path, e.g. `/wp-content/plugins/C:/wamp64/www/corex/addons/…`) was observed historically and does not reproduce under the current junction mount; this feature is preventive hardening plus observability, verified against a synthetic realpath-resolved path rather than a live reproduction.
- The Site Health probe reuses the existing health-probe seam introduced in spec 036 (`HealthProbe` / `HealthReport` / `wp corex doctor`); no new health framework is created.
- The probe's only user-facing strings are its label and messages, which are translation-ready via the shared `corex` text domain; there is no new visual UI, so token/RTL concerns do not apply.
