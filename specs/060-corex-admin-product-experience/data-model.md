# Phase 1 Data Model: CoreX Admin Product Experience

No new database schema. Add-on enable/disable and settings use existing options; the resolver is pure. Entities:

## AddonStatus (new enum)

- **Values**: `not_installed`, `inactive`, `feature_off`, `active`, `dependency_missing`, `woocommerce_missing`,
  `pro_required`.
- **Meaning**: the single truthful display state of an add-on (FR-001). `active` is the only "usable" state.
- **Helpers**: `isUsable()` (true only for `active`); `isInstalled()` (false only for `not_installed` and a
  `pro_required` that is not installed); `canToggle()` (true only when installed — never for `not_installed`/
  `pro_required`).

## Add-on descriptor (extends existing metadata for the admin)

- **From `AddonProvider`**: `slug`, `pluginFile`, `dependencies[]`, `featureFlag?`, `externalGate?`.
- **Added (admin-only)**: `label` (i18n), optional `proRequired` (bool, default false). No licensing data.

## Runtime state snapshot (existing — `AddonRuntimeState`)

- installed plugin files, active slugs, enabled flags, external gates. Read-only facts the resolver consumes.

## AddonStatusResolver (new, pure)

- **Input**: an add-on descriptor + `AddonRuntimeState` + the set of already-satisfied add-on slugs (for dependency
  resolution).
- **Output**: exactly one `AddonStatus` (ordering per research R1).
- **Invariant**: no WordPress calls inside the resolver (headless, unit-tested).

## Settings section state (derived)

- A value derived from an add-on's `AddonStatus` + a per-add-on "configured" predicate:
  `hidden_or_not_installed | disabled | configuration_needed | normal` (FR-006). Captcha's predicate = required keys
  present.

## Admin screen (existing, restyled)

- A CoreX-owned wp-admin screen (Dashboard/Add-ons/Data/Settings/Setup/Readiness) that enqueues the scoped
  `--corex-admin-*` adapter + its screen CSS (conditional), renders the design components, and reads the resolver.

## Captcha secret (existing option, write-only)

- Stored secret key; **never** rendered back. Field shows set/not-set; empty submit preserves the stored value.

No state is added to the framework DB by the resolver; admin actions reuse existing options through the secured
admin path.
