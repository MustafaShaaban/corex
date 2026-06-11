# Feature Specification: Project reset CLI

**Feature Branch**: `feature/025-project-reset`

**Created**: 2026-06-11

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: "`wp corex reset` — reset a Corex site to a clean state. SOFT: deactivate Corex add-ons, clear Corex flags + `corex_*` options, remove seeded demo content. FULL/HARD: wipe the database back to a fresh Corex starter (theme only, no add-ons). The DB wipe is a SAFETY GATE — destructive, irreversible, never auto-run, requires an explicit typed safeguard. Pure planning core; WP-CLI confined to a thin command layer."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Soft reset to a clean Corex slate (Priority: P1)

A developer who has been trying kits, flipping flags, and seeding demo content wants to return the site to a
clean Corex baseline **without destroying the database** — so they can start a fresh build on the same install.

**Why this priority**: This is the everyday reset. It must be safe, reversible-in-spirit (it touches only
Corex's own footprint), and predictable.

**Independent Test**: Run the soft reset on a site with add-ons active, flags on, and a seeded demo Home; confirm
the Corex add-ons are deactivated, every Corex feature flag + `corex_*` option is cleared, and the seeded demo
Home page + its marker are removed — and that **non-Corex content (other posts/pages/users/settings) is
untouched**.

**Acceptance Scenarios**:

1. **Given** active Corex add-on plugins, **When** soft reset runs, **Then** every `corex-*` add-on is
   deactivated (the core framework + theme remain).
2. **Given** Corex feature flags and `corex_*` options set, **When** soft reset runs, **Then** all of them are
   deleted (the flags return to their registry defaults).
3. **Given** a wizard-seeded demo Home page (and `corex_setup_demo_seeded`), **When** soft reset runs, **Then**
   the seeded page is trashed/deleted, the static-front-page settings it changed are reverted, and the marker is
   cleared.
4. **Given** unrelated content (a user's own posts, pages, users, and non-Corex options), **When** soft reset
   runs, **Then** none of it is modified.
5. **Given** the soft reset, **When** it runs, **Then** it reports exactly what it changed (a summary of
   deactivated add-ons, cleared options, and removed demo content).

---

### User Story 2 - Full reset behind a safety gate (Priority: P1)

A developer wants to wipe the database entirely and return to a **fresh Corex starter** — a clean WordPress
install with only the Corex theme active and no add-ons, options, flags, or demo content — to begin again from
zero. Because this is destructive and irreversible, it must be impossible to trigger by accident.

**Why this priority**: A true "start over" is a real need, but a DB wipe that runs too easily is a footgun. The
safety gate is the feature.

**Independent Test**: Run the full reset **without** the typed safeguard and confirm it refuses and changes
nothing; run it **with** the typed safeguard and confirm it restores the defined fresh-starter state.

**Acceptance Scenarios**:

1. **Given** the full/hard mode, **When** invoked **without** the typed safeguard flag, **Then** it refuses,
   prints what it *would* do, and makes **no** change (fail-closed).
2. **Given** the full/hard mode, **When** invoked **with** the typed safeguard flag (and WP-CLI's own
   confirmation), **Then** it wipes the database and reinstalls a fresh WordPress with **only the Corex theme
   active** and **no Corex add-ons / options / flags / demo content**.
3. **Given** a completed full reset, **When** the site is loaded, **Then** it is a clean Corex starter (the
   precise restored state is defined under *Fresh Corex starter* below) with **zero** PHP fatals.
4. **Given** the destructive nature, **When** the command runs, **Then** it never runs as part of any automated
   or non-interactive flow that did not pass the explicit safeguard.

---

### User Story 3 - Preview a reset before committing (Priority: P2)

Before either reset, a developer wants to see exactly what would change — a dry run that lists the actions
without performing them — so they can confirm the blast radius.

**Why this priority**: For a destructive tool, "show me first" is a core safety affordance and makes the pure
planner directly observable.

**Independent Test**: Run either mode with the dry-run option; confirm it lists the planned actions and makes
**no** change to the site.

**Acceptance Scenarios**:

1. **Given** either mode, **When** run with the dry-run option, **Then** it prints the ordered list of planned
   actions (and, for full mode, that a DB wipe would occur) and changes nothing.
2. **Given** the planner, **When** asked for a plan, **Then** the plan is produced purely from inputs (the list
   of Corex add-ons, flag keys, option keys, and the demo markers) with no side effects — making it
   headless-testable.

### Edge Cases

- **No Corex footprint present** (fresh site): soft reset is a no-op that reports "nothing to reset", never an
  error.
- **The safeguard flag is mistyped or partial**: treated as absent → full mode refuses (fail-closed).
- **WP-CLI absent**: the command is simply not registered (the planner still runs in tests); never a fatal.
- **Soft reset and the framework**: soft reset deactivates **add-ons** but leaves the Corex core plugin + theme
  active (it is a *clean slate*, not an *uninstall*).
- **Demo content the user has since edited**: soft reset only removes the page it can identify as the seeded
  demo (via the marker); it does not delete arbitrary pages.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `wp corex reset` MUST support two explicit modes — a **soft** mode (default) and a **full/hard**
  mode (opt-in by flag) — and MUST never perform the full mode unless explicitly selected.
- **FR-002**: Soft mode MUST deactivate every active `corex-*` add-on plugin while leaving the Corex core
  plugin and theme active.
- **FR-003**: Soft mode MUST delete every Corex feature-flag option (`corex_features_*`) and every other
  `corex_*` option Corex created, returning flags to their registry defaults.
- **FR-004**: Soft mode MUST remove the wizard-seeded demo content — the seeded Home page (identified by its
  marker), revert the `show_on_front` / `page_on_front` settings it changed, and clear `corex_setup_demo_seeded`
  — without touching any content it did not seed.
- **FR-005**: Full/hard mode MUST wipe the database and restore a **fresh Corex starter** (defined below), and
  MUST be gated: it refuses unless an explicit typed safeguard flag (e.g. `--yes-i-mean-it`) is passed in
  addition to WP-CLI's own destructive-command confirmation. Without it, it makes no change (fail-closed).
- **FR-006**: Both modes MUST support a dry-run that lists the planned actions and performs none of them.
- **FR-007**: The reset **planner** MUST be pure and headless-testable — it computes the ordered action plan
  from its inputs (add-on list, flag/option keys, demo markers, mode, flags) with no WordPress calls and no side
  effects; WP-CLI and the destructive operations MUST be confined to a thin command/executor layer (Principle
  IX-style separation, like the `make:*` commands).
- **FR-008**: Every run (soft, full, or dry) MUST report a clear, accurate summary of what was done (or would be
  done).
- **FR-009**: The full reset MUST NOT be reachable by any non-interactive/automated path that did not pass the
  explicit safeguard (the gate is enforced in code, not only by convention).

### Fresh Corex starter *(definition — required by the input)*

A **fresh Corex starter** is the deterministic state the full reset restores to:

1. The database is reset to a clean WordPress installation (the standard fresh-install schema + default
   options), as if newly installed — no Corex tables/rows, no prior content.
2. **Only the Corex theme is active.** No `corex-*` add-on plugins are active (the Corex core plugin remains
   present/active as the framework, mirroring the documented install).
3. **No Corex footprint:** no `corex_*` options, no feature flags set, no seeded demo content, no Corex custom
   tables' rows.
4. The site loads with **zero PHP fatals** and the Corex theme rendering its default templates.

### Key Entities

- **Reset plan**: the ordered list of actions (deactivations, option deletions, demo removal, and — for full
  mode — the DB-wipe step), produced by the pure planner.
- **Reset mode**: soft (default) or full/hard (gated).
- **Safeguard**: the explicit typed flag required for the destructive full mode.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After a soft reset, **100%** of Corex add-ons are deactivated, **100%** of `corex_*` options +
  flags are cleared, and the seeded demo content is gone — while **0** non-Corex items are modified.
- **SC-002**: The full reset is **impossible** to perform without the explicit typed safeguard (verified: every
  invocation lacking it makes zero changes).
- **SC-003**: After a full reset, the site matches the *Fresh Corex starter* definition exactly and loads with
  zero PHP fatals.
- **SC-004**: A dry run of either mode changes **nothing** and lists the exact actions the real run would take.
- **SC-005**: The reset planner is covered by unit tests that run with **no WordPress and no database** (pure).

## Assumptions

- Built on the spec-003/019 CLI engine pattern (pure core + thin WP-CLI command, `class_exists('WP_CLI')`
  gated) and the spec-021 feature-flag registry (the flag keys to clear).
- The list of `corex-*` add-ons and the `corex_*` option/flag/demo-marker keys are knowable from the framework
  (the planner is fed them; it does not guess).
- The full reset uses WordPress/WP-CLI's own database-reset + core-install primitives at the executor boundary;
  the spec governs *what* is restored, not the exact primitive.
- "Reversible-ish" soft mode is **not** a safety gate (it only touches Corex's own footprint); only the full
  DB-wipe is gated.
