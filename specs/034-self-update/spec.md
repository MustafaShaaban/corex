# Feature Specification: Self-update mechanism + distribution

**Feature Branch**: `feature/034-self-update` · **Created**: 2026-06-12 · **Status**: Draft (forward, full Spec Kit)

**Input**: "If I'm using the project somewhere and I publish a new update, how do users get notified — like WordPress plugins showing an update? And will updating override the user's own implementations?"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Update notifications in wp-admin (Priority: P1)
A site running Corex sees an **update available** notice for the Corex framework in **Plugins → Updates**, just
like any plugin, when a newer version is published — and can update from wp-admin.

**Acceptance Scenarios**:
1. **Given** a published newer version (in the update source), **When** WordPress checks for updates, **Then**
   the Corex plugin shows an available update (version + package), surfaced in wp-admin.
2. **Given** the current version is the latest, **When** checked, **Then** no update is offered.
3. **Given** the framework is not on wordpress.org, **When** WordPress checks, **Then** it routes the check to
   Corex's own update source (no wrong wordpress.org match).

### User Story 2 - Updates never override the user's own code (Priority: P1)
Updating the framework replaces **framework** files only — never the developer's application code
(`wp-content/corex-app/`), their `brand.json`, their content, or their database. The safe-edit boundary is
explicit.

**Acceptance Scenarios**:
1. **Given** an update, **When** applied, **Then** only the framework plugin's own files change; `corex-app/`,
   `brand.json`, content, and data are untouched.
2. **Given** the docs, **When** a developer reads them, **Then** the safe-edit boundary (what is framework vs
   theirs) is clearly documented.

### User Story 3 - A documented distribution model (Priority: P2)
A maintainer can publish updates: host an **update manifest** (version + package URL) and the package, and
point Corex at it — so their team/clients get notified.

**Acceptance Scenarios**:
1. **Given** the distribution docs, **When** followed, **Then** a maintainer can publish a manifest + package
   (e.g. on GitHub Releases / a static host) and configure the update endpoint.

### Edge Cases
- A missing/unreachable update source → no update offered, no fatal (fail-safe).
- A malformed manifest → ignored safely.
- The update source URL is configurable (`updates.endpoint`); secrets never required to *check*.

## Requirements *(mandatory)*
- **FR-001**: A pure `UpdateChecker` MUST decide whether an update is available — given the current version and
  a fetched manifest (`{version, package, url, …}`), return the update info iff `manifest.version` is newer
  (semver compare); otherwise null. Headless-testable.
- **FR-002**: An `UpdateService` MUST hook WordPress's update flow (`pre_set_site_transient_update_plugins`) to
  inject the update for the Corex plugin, and `plugins_api` for the details popup, fetching the manifest from
  the configured endpoint (`wp_remote_get`); a missing/unreachable/malformed source MUST be a safe no-op.
- **FR-003**: The Corex plugin MUST declare an **Update URI** header so WordPress routes the check to Corex's
  source (not wordpress.org).
- **FR-004**: Updates MUST replace only the framework plugin's files — never `wp-content/corex-app/`,
  `brand.json`, content, or the database (the safe-edit boundary); this MUST be documented.
- **FR-005**: The update endpoint MUST be configurable (`updates.endpoint`); checking MUST require no secret.
- **FR-006**: A **distribution guide** MUST document publishing a manifest + package and pointing Corex at it.

### Key Entities
- **Update manifest**: `{version, package, url, requires?, tested?}` served by the maintainer's endpoint.
- **UpdateChecker**: pure version-compare + update-object builder.
- **UpdateService**: the WP boundary (transient + plugins_api hooks + remote fetch).

## Success Criteria *(mandatory)*
- **SC-001**: A newer published version surfaces as an available update in wp-admin.
- **SC-002**: When current is latest, no update is offered.
- **SC-003**: The check routes to Corex's source, not wordpress.org (Update URI present).
- **SC-004**: Updating never touches `corex-app/`, `brand.json`, content, or data (documented + by design).
- **SC-005**: The UpdateChecker logic is unit-tested; a missing source is a safe no-op.

## Assumptions
- Built on the spec-001 Config (the endpoint) + WP's plugin update API. The actual package download/install is
  WordPress's standard updater; Corex only supplies the manifest + injects it. Live update-from-admin needs a
  real published manifest + a browser (env-gated); the checker logic + the hooks + the fail-safe are verified
  headlessly + live (route/hook registration).
