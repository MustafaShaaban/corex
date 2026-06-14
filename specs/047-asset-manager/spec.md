# Feature Specification: Asset manager & environments

**Feature Branch**: `feature/047-asset-manager`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Corex needs a formal asset/performance layer — an AssetManager/AssetResolver with
standard path/URL/version helpers (`$assets->url('images/logo.svg')`, `->path()`, `->version('build/app.css')`),
cache-busting (manifest hash where available, filemtime fallback, framework/site-version fallback), and
environment modes (local: source maps + filemtime; staging/production: minified + content-hash/manifest, no public
source maps), plus `cache:clear` / `assets:doctor`. No stale CSS/JS after a release."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reference an asset without hardcoding URLs or versions (Priority: P1) 🎯 MVP

A developer references a theme/plugin asset through one helper — `$assets->url('images/logo.svg')` for the URL,
`$assets->path(...)` for the filesystem path, `$assets->version('build/app.css')` for a cache-busting version —
instead of hand-building `plugins_url()` calls and guessing a version string. Enqueues get a correct URL + a
version that changes when the file changes.

**Why this priority**: This is the core primitive every theme/plugin/block + the future `make:site` starter needs;
without it, asset URLs and cache-busting are hand-rolled and inconsistent.

**Independent Test**: Resolve the URL, path, and version of a known asset; the URL points at the right location,
the path is the file on disk, and the version changes when the file's content/mtime changes.

**Acceptance Scenarios**:

1. **Given** a relative asset path, **When** `url()` is called, **Then** it returns the correct public URL for the
   asset's base (theme or the calling plugin), junction/symlink-safe (reusing the spec-040 normalisation).
2. **Given** a relative asset path, **When** `path()` is called, **Then** it returns the absolute filesystem path.
3. **Given** an asset file, **When** `version()` is called, **Then** it returns a cache-busting token that
   **changes when the file changes** and is stable when it does not.
4. **Given** a missing asset, **When** `version()` is called, **Then** it degrades to a safe fallback (the
   framework/site version), never an error.

---

### User Story 2 - The right version strategy per environment (Priority: P1)

The version token follows the environment: in **local/development** it is filemtime-based (busts on every edit);
in **staging/production** it prefers a build **manifest hash / content hash** (busts only on a real rebuild), with
the framework/site version as the final fallback — so a release never serves stale CSS/JS and local edits are
always seen.

**Why this priority**: "no stale assets after a release" + "local edits always visible" is the whole point of a
versioning layer; getting the per-environment strategy right is what makes it trustworthy.

**Independent Test**: In each environment, `version()` returns the expected kind of token (filemtime in local;
manifest/content hash in production when a manifest exists; framework/site version fallback otherwise).

**Acceptance Scenarios**:

1. **Given** local/development, **When** `version()` runs, **Then** it uses filemtime (changes on every file edit).
2. **Given** production with a build manifest, **When** `version()` runs for a built asset, **Then** it uses the
   manifest's content hash (changes only on a rebuild).
3. **Given** production without a manifest entry, **When** `version()` runs, **Then** it falls back to the
   framework/site version (changes on every release).
4. **Given** the environment, **When** it is resolved, **Then** it is determined from explicit configuration
   (e.g. an env value), defaulting sensibly, with no per-request guesswork that could differ across requests.

---

### User Story 3 - A build manifest is honoured (Priority: P2)

When a build emits a **manifest** mapping source names to hashed output files, the asset manager reads it so
`url()`/`version()` resolve to the hashed filename + its hash — the standard cache-busting-by-filename strategy —
falling back to the plain file + filemtime/version when no manifest entry exists.

**Why this priority**: Manifest/content-hash busting is the production-correct strategy; it builds on US1/US2's
resolution and is P2 because filemtime fallback already delivers correctness without it.

**Independent Test**: With a manifest present, resolving a built asset yields the hashed filename + hash; without
a manifest, it yields the plain file + the fallback version.

**Acceptance Scenarios**:

1. **Given** a build manifest with an entry for an asset, **When** `url()` resolves it, **Then** it returns the
   hashed output filename and `version()` returns its content hash.
2. **Given** no manifest (or no entry), **When** the asset resolves, **Then** it returns the plain file + the
   filemtime/version fallback — never an error.

---

### User Story 4 - Diagnose and clear asset state (Priority: P2)

A developer runs `wp corex assets:doctor` to see the asset configuration (environment, manifest present/absent,
sample resolutions, source-map exposure) and `wp corex cache:clear` to clear Corex's cached asset/version state —
so a release or a confusing cache situation is diagnosable and resettable.

**Why this priority**: Operability — being able to see *why* an asset resolved a certain way and to clear stale
state. P2 because the resolution (US1–US3) works without the commands.

**Independent Test**: `assets:doctor` reports the environment + manifest status + sample resolutions; `cache:clear`
clears the cached state; both are WP-CLI-gated.

**Acceptance Scenarios**:

1. **Given** the asset configuration, **When** `assets:doctor` runs, **Then** it reports the environment, whether
   a manifest is present, sample URL/version resolutions, and whether source maps are exposed.
2. **Given** cached asset state, **When** `cache:clear` runs, **Then** it is cleared (next resolution recomputes).
3. **Given** no WP-CLI, **When** the framework loads, **Then** the commands are absent; the resolver is usable
   without WP-CLI.

---

### Edge Cases

- An asset path that escapes the base (`../`) → rejected/normalised, never resolving outside the allowed base.
- A manifest that is malformed/absent → ignored gracefully (fallback resolution), never a fatal.
- A file with the same mtime after a no-op rebuild → stable version (no spurious busting).
- Production with **public source maps** → not exposed unless explicitly enabled (a `.map` is not linked publicly
  by default in production).
- Concurrent requests → the same input resolves to the same version within an environment (deterministic).
- A very large manifest → read once + cached, not re-parsed per asset.

## Requirements *(mandatory)*

### Functional Requirements

**Resolution (US1)**

- **FR-001**: An `AssetManager`/`AssetResolver` MUST provide `url(relative)`, `path(relative)`, and
  `version(relative)` for an asset relative to its base (theme or the calling plugin).
- **FR-002**: `url()` MUST return a correct, junction/symlink-safe public URL (reusing the spec-040 normalisation);
  `path()` MUST return the absolute filesystem path.
- **FR-003**: `version()` MUST return a token that **changes when the file changes** and is stable otherwise; a
  missing asset MUST fall back to the framework/site version, never erroring.
- **FR-004**: A relative path MUST be confined to its base — a traversal (`../`) MUST NOT resolve outside the
  allowed base.

**Environments (US2)**

- **FR-005**: The version strategy MUST follow the resolved environment: **local/development** → filemtime;
  **staging/production** → manifest/content hash when available, else the framework/site version.
- **FR-006**: The environment MUST be resolved from explicit configuration (defaulting sensibly) and be
  **deterministic** within a request lifecycle (no per-call guessing that could differ).
- **FR-007**: In production, public **source maps** MUST NOT be exposed unless explicitly enabled.

**Manifest (US3)**

- **FR-008**: When a build manifest is present, `url()`/`version()` MUST resolve a built asset to its hashed output
  filename + content hash; with no manifest/entry, they MUST fall back to the plain file + filemtime/version.
- **FR-009**: The manifest MUST be read once + cached (not re-parsed per asset); a malformed/absent manifest MUST
  be ignored gracefully.

**Operability (US4)**

- **FR-010**: `wp corex assets:doctor` MUST report the environment, manifest presence, sample resolutions, and
  source-map exposure; `wp corex cache:clear` MUST clear Corex's cached asset/version state. Both MUST be
  WP-CLI-gated; the resolver MUST be usable without WP-CLI.

**Cross-cutting**

- **FR-011**: The resolution core (path/url/version/manifest/environment) MUST be **pure** (filesystem values
  injected) so it is unit-tested headlessly; WordPress URL/CLI calls are a thin boundary.
- **FR-012**: No new hard dependency; the layer works with or without a build/manifest (Principle IX). No secret is
  ever exposed (a manifest/diagnostic carries no secret).

### Key Entities *(include if feature involves data)*

- **Asset reference**: a relative path + its base (theme or a plugin) → resolved to a URL, a filesystem path, and a
  version token.
- **Version token**: the cache-busting value — a content hash (manifest), a filemtime (local), or the framework/
  site version (fallback).
- **Build manifest**: a map of source name → hashed output filename (+ hash), read once + cached.
- **Environment**: local / staging / production — resolved from config; selects the version strategy + source-map
  exposure.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer resolves an asset's URL, path, and version through **one** helper, with **zero**
  hand-built `plugins_url()` or guessed version strings.
- **SC-002**: After a production **rebuild/release**, every changed asset's URL/version changes — **no stale
  CSS/JS** is served — and a local edit is **always** reflected immediately (filemtime).
- **SC-003**: In production, a public source map is exposed **only** when explicitly enabled (off by default).
- **SC-004**: The same asset input resolves to the **same** version within an environment (deterministic), and a
  missing asset/manifest never causes an error.
- **SC-005**: The resolution core is unit-tested headlessly (no WordPress), and the framework runs fully with **no**
  build/manifest and **no** WP-CLI.

## Assumptions

- Builds on and **reuses** the spec-040 block-path normalisation (junction/symlink-safe URLs), the spec-007/036
  Config + version constants (`COREX_*_VERSION`), and the spec-018 build pipeline (`@wordpress/scripts`, which can
  emit a manifest) — this feature adds the resolver + environment strategy + manifest reading + the CLI; it does
  not re-spec them.
- The resolution core is **pure** (mtime/manifest/version injected or read through a thin boundary), the WP URL +
  WP-CLI calls a thin gated layer (the spec-003/036 pattern) — so it is unit-tested headlessly.
- Environment is resolved from an explicit config/env value (e.g. `APP_ENV` / a Corex config key), defaulting to
  production-safe behavior when unset.
- The manifest format is the common "source → { file, hash }" JSON a build emits; a custom format is out of scope.
- Out of scope (explicitly): running the actual asset build (spec 018 owns it), a CDN/Blob integration (a later
  performance increment), and image-specific optimization (spec 048).
- Live behavior (real enqueue URLs/versions across a rebuild, source-map exposure) requires a running server +
  build; per the project-wide environment gate, the pure resolver/manifest/version logic is unit-tested headlessly
  and the live confirmation runs when the environment is available.
