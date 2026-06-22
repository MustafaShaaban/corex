# Feature Specification: CoreX Admin Product Experience

**Feature Branch**: `spec/060-corex-admin-product-experience` (foundation),
`fix/060-admin-design-implementation` (corrective visual implementation)

**Created**: 2026-06-21

**Status**: Implemented; corrective visual implementation ready for review

**Input**: Milestone M6 (ROADMAP §9). Design input: [M6 admin experience handoff](../../design/handoffs/admin-experience.md) (approved 2026-06-21, from the owner-supplied admin design package). Built on M2 tokens (Spec 057) via the scoped `--corex-admin-*` adapter and the existing `AddonRuntimeState`.

## Overview

CoreX has admin screens (Dashboard/Settings/Data/Insights, a setup wizard, captcha settings) but they don't yet
present a coherent CoreX product experience or a **truthful** picture of which add-ons and settings are actually
usable. A site operator can be shown settings for an add-on that isn't installed, or reCAPTCHA fields that do nothing
because captcha is inactive. This feature gives the shared CoreX wp-admin a calm, accessible, dark-first (with light)
design through the scoped `--corex-admin-*` adapter, and a **truthful add-on/settings/captcha state model** so every
screen reflects the real runtime state. It is the shared CoreX admin seen by both company sites; it does **not**
touch public company-site frontends.

This is not a marketplace and not a Pro store: the admin manages only **installed** add-ons (enable/disable/status/
dependency explanation/settings access). Installing packages is developer/CLI/deployment work.

PR #58 delivered the truthful-state foundation. It did not complete the visual contract in US3/US4: most existing
CoreX screens retained legacy/plain wp-admin presentation and the login surface received only a logo replacement.
The corrective branch applies the approved visual system from login through every current CoreX-owned admin screen;
this completes the existing M6 scope and is not minor cosmetic polish.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Truthful add-on states (Priority: P1)

An operator opens the CoreX **Add-ons** screen and sees each add-on in exactly one honest state: not installed,
inactive, feature off, active, dependency missing, WooCommerce missing, or Pro required (disabled). Installed add-ons
can be enabled/disabled and explain their dependencies; a not-installed add-on shows "not installed" and **cannot** be
enabled from the admin. No add-on is ever shown as usable when it is not.

**Why this priority**: A truthful state model is the foundation everything else (settings, captcha, dashboard
badges) reads from. Without it the admin lies about capability. It is independently testable as a pure resolver.

**Independent Test**: Drive the state resolver with each runtime combination (installed?/active?/flag?/dependency?/
Woo?/pro?) and assert exactly one correct state; render the Add-ons screen and confirm not-installed add-ons have no
enable action while installed ones do.

**Acceptance Scenarios**:

1. **Given** an add-on whose package is absent, **When** the Add-ons screen renders, **Then** it shows "not
   installed" and offers no enable control (install is out of admin scope).
2. **Given** an installed but WordPress-inactive add-on, **When** rendered, **Then** it shows "inactive" and is not
   presented as usable.
3. **Given** an active add-on whose CoreX feature flag is off, **When** rendered, **Then** it shows "feature off".
4. **Given** an active add-on with an unmet CoreX dependency, **When** rendered, **Then** it shows "dependency
   missing" and names the dependency.
5. **Given** a WooCommerce-gated add-on with WooCommerce absent, **When** rendered, **Then** it shows "WooCommerce
   missing".
6. **Given** a Pro/future add-on, **When** rendered, **Then** it shows a disabled "Pro required" state with no
   purchase/licensing action.
7. **Given** an active, fully-satisfied add-on, **When** rendered, **Then** it shows "active" with enable/disable and
   settings access.

### User Story 2 - State-aware settings, including captcha/reCAPTCHA (Priority: P2)

An operator opens **Settings** and sees only settings that make sense for the current runtime state. An add-on that
isn't installed hides its advanced settings (or shows "add-on not installed"); inactive/disabled add-ons show a
disabled state with no usable fields; active-but-unconfigured shows "configuration needed"; active+configured shows
normal settings. reCAPTCHA follows this exactly, and captcha secrets are write-only.

**Why this priority**: Settings are where a dishonest state does real harm (operators enter keys that do nothing).
This consumes the US1 state model.

**Independent Test**: For captcha across its states (not installed / inactive / active-no-keys / active-configured),
render the settings section and assert the correct state; submit a secret and confirm it is stored but never rendered
back.

**Acceptance Scenarios**:

1. **Given** captcha is not installed, **When** Settings renders, **Then** reCAPTCHA settings are hidden or shown as
   "add-on not installed" and never appear active.
2. **Given** captcha is installed but inactive/feature-off, **When** rendered, **Then** reCAPTCHA fields are disabled
   or replaced by a clear disabled state.
3. **Given** captcha is active but keys are missing, **When** rendered, **Then** the section shows "configuration
   needed".
4. **Given** captcha is active and configured, **When** rendered, **Then** provider settings and a test action are
   shown.
5. **Given** any captcha secret, **When** the settings screen renders, **Then** the secret value is never output
   (write-only).
6. **Given** a non-installed add-on, **When** Settings renders, **Then** its advanced fields are hidden or shown as a
   clear "not installed" state, not as active inputs.

### User Story 3 - Coherent CoreX admin visual design (Priority: P2)

An operator sees a calm, branded CoreX surface across Dashboard, Add-ons, Data, and Settings — stat cards, data
tables, a topbar, and status badges — styled through the scoped `--corex-admin-*` adapter in both dark and light
modes, mirrored in RTL, and responsive. Generic wp-admin chrome is untouched, and nothing styles the public
frontend.

**Why this priority**: The visual layer makes the truthful states legible and the product coherent, but the states
(US1/US2) deliver the core value first.

**Independent Test**: Load each CoreX screen and confirm it uses the adapter (scoped class), renders the design
components, passes accessibility checks (landmarks/contrast/focus), mirrors in RTL, and that the adapter/assets do
not load on non-CoreX admin pages or the frontend.

**Acceptance Scenarios**:

1. **Given** a CoreX admin screen, **When** it renders, **Then** it applies the `--corex-admin-*` adapter and the
   design components (cards/tables/topbar/badges) with WCAG 2.2 AA contrast and visible focus.
2. **Given** a non-CoreX wp-admin page or the public frontend, **When** it renders, **Then** the CoreX admin adapter
   and admin assets are absent (no global restyle, no frontend branding).
3. **Given** an RTL locale, **When** a CoreX screen renders, **Then** the layout mirrors via logical properties.
4. **Given** `prefers-reduced-motion: reduce`, **When** a CoreX screen renders, **Then** no non-essential animation
   plays.
5. **Given** WordPress renders login, lost-password, reset-password, or login-error states, **When** CoreX branding
   is active, **Then** the approved CoreX login layer is applied without replacing WordPress forms, messages,
   navigation, accessible names, or authentication behavior.

### User Story 4 - Setup, readiness, and universal states (Priority: P3)

An operator completing first-run sees a guided **Setup** screen, and a **Readiness/Status** screen reporting
release/CI/environment checks honestly (environment-gated items shown as such, never falsely green). Data-bearing
screens present loading, empty, error, success, and permission-denied states.

**Why this priority**: Polish and operational completeness; the core admin works without it.

**Independent Test**: Render setup and readiness screens; assert environment-gated checks render as gated; force each
universal state on a data screen and confirm it renders.

**Acceptance Scenarios**:

1. **Given** the readiness screen, **When** an environment-gated check is unavailable, **Then** it renders as
   environment-gated, never as passing.
2. **Given** a data screen with no records, **When** it renders, **Then** it shows an empty state, not a broken table.
3. **Given** an operator without capability, **When** a CoreX screen loads, **Then** it shows a permission-denied
   state.

### Edge Cases

- An add-on installed but its dependency add-on not installed (chain) — show "dependency missing" naming the chain.
- Feature flag on but plugin inactive — inactive wins (not usable).
- Captcha active, one of multiple key fields missing — "configuration needed".
- Very long add-on/dependency names and mixed Arabic/Latin labels — no overflow, correct bidi.
- A Pro/future add-on that is also not installed — "Pro required" disabled state (no enable, no install).
- 200% zoom / 320px-equivalent narrow admin — no horizontal scroll, sidebar/content reflow.

## Requirements *(mandatory)*

### Functional Requirements

**Add-on state model (P1)**

- **FR-001**: The system MUST resolve every CoreX add-on to exactly one display state: `not_installed`, `inactive`,
  `feature_off`, `active`, `dependency_missing`, `woocommerce_missing`, or `pro_required`.
- **FR-002**: The resolver MUST derive state from the existing runtime snapshot (installed/active/flag/external-gate)
  and declared dependencies, as a pure, headless, unit-tested unit (no WordPress calls inside the resolver).
- **FR-003**: The Add-ons screen MUST allow enable/disable, status, dependency explanation, and settings access for
  **installed** add-ons only; a not-installed add-on MUST show "not installed" and MUST NOT offer an enable action.
- **FR-004**: The system MUST NOT provide marketplace, download, or install-from-admin behavior.
- **FR-005**: The `pro_required` state MUST be a disabled, non-actionable indicator only — no licensing, entitlement,
  or purchase flow.

**State-aware settings + captcha (P2)**

- **FR-006**: Settings sections MUST reflect the add-on's runtime state: not installed → hidden or "add-on not
  installed"; inactive/feature-off → disabled state with no usable fields; active+unconfigured → "configuration
  needed"; active+configured → normal settings.
- **FR-007**: reCAPTCHA settings MUST follow FR-006 against the captcha add-on's state and MUST NOT appear active
  when captcha is not installed.
- **FR-008**: Captcha secret values MUST be write-only — stored but never rendered back to any screen or API
  response.

**Visual design + scoping (P2/P3)**

- **FR-009**: The WordPress login surface and all current CoreX admin screens MUST apply the M2 brand via the scoped
  `--corex-admin-*` adapter and the approved design components (shell, cards, tables, topbar, status badges), in
  dark and light modes. Login styling MUST remain additive and preserve native WordPress authentication flows.
- **FR-010**: The admin adapter and CoreX admin assets MUST load only on CoreX admin screens (Principle VI) and MUST
  NOT globally restyle wp-admin or apply to the public frontend.
- **FR-011**: All CoreX admin UI MUST meet WCAG 2.2 AA (landmarks, heading order, visible focus, names, status not by
  color alone), be keyboard-operable, respect `prefers-reduced-motion`, mirror in RTL via logical properties, and be
  responsive without horizontal scroll at narrow widths / 200% zoom.

**States and operations (P3)**

- **FR-012**: Data-bearing CoreX screens MUST define loading, empty, error, success, and permission-denied states.
- **FR-013**: The Readiness/Status screen MUST render environment-gated checks as environment-gated, never as
  passing.
- **FR-014**: Admin state changes (enable/disable, settings save) MUST go through the existing declarative security
  path (capability + nonce via the shared admin guard); the resolver and views MUST NOT hand-roll security.

**Scope guards**

- **FR-015**: This feature MUST NOT modify public company-site frontends, implement Pro licensing/entitlement/
  distribution, a client portal, an editor workspace, or any CPT/form/addon/model/table/resource generator.

### Key Entities

- **Add-on descriptor**: an add-on's identity + gating metadata (slug, plugin file, dependencies, feature flag,
  external gate, optional pro flag).
- **Runtime state snapshot**: installed/active/flag/external-gate facts (existing `AddonRuntimeState`).
- **Add-on display state**: the single resolved state enum (FR-001).
- **Settings section state**: the per-section display state derived from the add-on display state (FR-006).
- **Admin screen**: a CoreX-owned wp-admin screen (Dashboard/Add-ons/Data/Settings/Setup/Readiness/Insights and any
  registered CoreX option page) styled via the adapter. The WordPress login surface is a separately scoped CoreX
  product-branding surface.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of add-ons resolve to exactly one of the seven truthful states across every runtime combination,
  verified by the resolver's unit matrix.
- **SC-002**: No CoreX admin screen presents a not-installed/inactive add-on's settings as active/usable in any state.
- **SC-003**: Captcha secrets are never present in rendered admin HTML or API responses (write-only), verified.
- **SC-004**: The CoreX admin adapter/assets load on CoreX screens only and never on other wp-admin pages or the
  public frontend.
- **SC-005**: Every CoreX admin screen passes automated accessibility checks (landmarks/contrast/focus) in dark and
  light, LTR and RTL, with zero blocking violations (browser-verified where available, else ENVIRONMENT-GATED).
- **SC-006**: Environment-gated readiness checks are never reported as passing.
- **SC-007**: No public frontend output changes as a result of this feature.

## Assumptions

- The M2 tokens (Spec 057) and the scoped `--corex-admin-*` adapter exist and are the source of admin visuals.
- The existing `AddonRuntimeState`/`AddonProvider` model the runtime facts; M6 adds a pure display-state resolver on
  top and surfaces it — it does not change boot-time gating behavior.
- The admin screens (control panel/data/insights/captcha/setup) exist; M6 restyles and makes them state-truthful, not
  rebuilds them.
- "Pro required" is a static, non-actionable indicator sourced from an explicit descriptor flag; no licensing.
- The Setup Wizard ships as a guided first-run screen reusing the existing setup foundation (full multi-step flow is a
  later refinement).
- Browser-rendered and wp-env admin evidence may be ENVIRONMENT-GATED in this workspace and recorded as such, never
  PASS.
