# Feature Specification: Add-on manager admin screen

**Feature Branch**: `feature/026-addon-manager`

**Created**: 2026-06-11

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: "A server-rendered 'Corex Add-ons' admin screen (in corex-config, same nonce + capability + i18n + RTL pattern as the settings + setup-wizard screens) to enable/disable each `corex-*` add-on — activating/deactivating its plugin and toggling its feature flag — with dependency awareness. A companion to the setup wizard."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See and toggle Corex add-ons from one screen (Priority: P1)

A site administrator opens a "Corex Add-ons" screen under the Corex menu and sees every Corex add-on with its
current state (active or not, and its feature flag where it has one), and can enable or disable each one from
that screen — which activates/deactivates the add-on's plugin and sets/clears its feature flag together.

**Why this priority**: The setup wizard composes a whole kit; this is the granular companion — turn one add-on
on or off without editing `wp-admin/plugins.php` and a separate flag toggle.

**Independent Test**: Open the screen on a site with some add-ons active; confirm each add-on is listed with its
correct state; toggle one and confirm its plugin activation and its flag changed together; confirm the change
is nonce + capability gated.

**Acceptance Scenarios**:

1. **Given** the registered Corex add-ons, **When** the screen renders, **Then** each is shown with its label,
   its active/inactive state, and (where it has one) its feature-flag state.
2. **Given** an inactive add-on with its dependencies met, **When** the admin enables it, **Then** its plugin is
   activated and its feature flag (if any) is turned on.
3. **Given** an active add-on with no active dependents, **When** the admin disables it, **Then** its plugin is
   deactivated and its feature flag (if any) is turned off.
4. **Given** any toggle, **When** submitted, **Then** it verifies a nonce + `manage_options` before acting, and
   reports the result; an unauthorized or nonce-less request changes nothing.
5. **Given** the screen, **When** rendered, **Then** all output is escaped, translation-ready, and RTL-correct
   (the same discipline as the settings + setup-wizard screens).

---

### User Story 2 - Be protected from breaking dependencies (Priority: P1)

When an admin tries a toggle that would break a dependency — disabling an add-on that an active add-on needs,
or enabling an add-on whose dependency is not active — the screen prevents it and explains why, instead of
leaving the site in a broken state.

**Why this priority**: Add-ons depend on each other (e.g. the site kits need the UI block library). A manager
that lets you disable a dependency out from under a dependent is worse than no manager.

**Independent Test**: With a kit add-on active that depends on the UI add-on, confirm the UI add-on cannot be
disabled (and the reason names the dependent); with the UI add-on inactive, confirm the kit cannot be enabled
(and the reason names the missing dependency).

**Acceptance Scenarios**:

1. **Given** an active add-on B that requires add-on A, **When** the admin tries to disable A, **Then** it is
   refused and the message names B as the blocking dependent; A stays active.
2. **Given** an inactive dependency A, **When** the admin tries to enable add-on B that requires A, **Then** it
   is refused and the message names A as the missing dependency; B stays inactive (the admin enables A first).
3. **Given** the dependency rules, **When** the screen renders, **Then** each add-on whose toggle is blocked
   shows the reason, so the admin sees the constraint before acting.

### Edge Cases

- An add-on whose plugin file is **not present** (never installed) is shown as unavailable, not togglable, never
  a fatal.
- An add-on with no feature flag toggles only its plugin (the flag step is skipped).
- The dependency graph is acyclic and small; the screen never cascades activations silently — it asks the admin
  to enable a dependency explicitly (deterministic, no surprise side effects).
- Disabling an add-on that nothing depends on always succeeds (no false blocks).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A server-rendered "Corex Add-ons" screen MUST exist under the Corex admin menu (in `corex-config`),
  listing every registered Corex add-on with its label, active state, and feature-flag state (where it has one).
- **FR-002**: Enabling an add-on MUST activate its plugin and turn on its feature flag (if it has one); disabling
  MUST deactivate its plugin and turn off its feature flag (if it has one) — the two kept in sync.
- **FR-003**: Every toggle MUST verify a nonce **and** `manage_options` (via the shared `AdminGuard`) before
  changing anything; an unauthorized or nonce-less request MUST change nothing.
- **FR-004**: The screen MUST be **dependency-aware**: it MUST refuse to disable an add-on that an active add-on
  requires (naming the dependent), and refuse to enable an add-on whose required dependency is inactive (naming
  the missing dependency) — and surface those constraints in the rendered list.
- **FR-005**: An add-on whose plugin is not installed MUST be shown as unavailable and not togglable, never
  causing a fatal.
- **FR-006**: All rendered output MUST be escaped, every user-facing string translation-ready with the `corex`
  text domain, and the layout RTL-correct (the settings/setup-wizard discipline).
- **FR-007**: The add-on registry and the dependency/toggle decisions MUST be a **pure, headless-testable**
  layer (no WordPress); only the screen + an activator touch WordPress (plugin activation, option writes) — the
  established pure-core + admin-boundary split.

### Key Entities

- **Addon**: a registered Corex add-on — slug, plugin file, label, optional feature flag, required dependencies.
- **AddonState**: a snapshot of which add-ons are active and which flags are on (fed from WordPress).
- **AddonView**: an add-on's render model — its state plus whether (and why) a toggle is blocked.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The screen lists **every** registered add-on with an accurate active + flag state.
- **SC-002**: A single enable/disable changes both the plugin activation and the feature flag (where present)
  with **one** action.
- **SC-003**: It is **impossible** to disable an add-on that an active add-on requires, or to enable one whose
  dependency is inactive (the toggle is refused, the state unchanged).
- **SC-004**: Every state change is nonce + capability gated; an unauthorized request changes nothing.
- **SC-005**: The registry + dependency logic are covered by unit tests that run with **no WordPress**.

## Assumptions

- Built on the spec-024 setup wizard (the same add-on/flag concepts), the P5 `Corex\Security\Admin\AdminGuard`
  (cap + nonce), and the spec-021 feature-flag layer.
- The set of Corex add-ons and their dependencies is known to the framework (a registry), mirroring the kit
  blueprints' required-module relationships (e.g. the site kits require the UI add-on).
- The screen is server-rendered PHP (the React/DataViews admin remains the deferred build-env upgrade).
