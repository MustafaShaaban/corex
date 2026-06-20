# Phase 0 Research: CoreX Admin Product Experience

Decisions. Format: Decision · Rationale · Alternatives.

## R1. State set, ordering, and where `pro_required` sits

**Decision**: One enum `AddonStatus` = `not_installed | inactive | feature_off | active | dependency_missing |
woocommerce_missing | pro_required`. Resolution order: **pro_required first** (a future/commercial add-on shows the
disabled Pro indicator regardless of install state), then the runtime order mirroring `blockedReason`:
not_installed → inactive → dependency_missing → feature_off → woocommerce_missing → active.
**Rationale**: matches the spec edge case ("a Pro/future add-on that is also not installed → Pro required") and the
existing boot-resolution ordering, so admin display and boot gating agree.
**Alternatives**: pro checked last — rejected (a not-installed pro add-on would show "not installed", hiding that it's
commercial/future).

## R2. New pure resolver vs. reusing `blockedReason`

**Decision**: Add a new pure `AddonStatusResolver` returning the `AddonStatus` enum, sharing the same ordering as
`AddonProviderResolver::blockedReason()`. The boot resolver keeps returning string reasons for logging; the display
resolver returns a typed state for the UI.
**Rationale**: the boot resolver's strings are for logs and don't distinguish `active`; a typed enum is what the UI
and tests need. Keeping both avoids overloading the boot path (SRP) while sharing the documented order (DRY of
knowledge, not text).
**Alternatives**: parse the boot resolver's strings in the UI — rejected (brittle string-coupling).

## R3. Settings-section state from add-on state

**Decision**: A section's display derives from its add-on's `AddonStatus`: `not_installed` → hidden or "add-on not
installed"; `inactive`/`feature_off`/`pro_required` → disabled state (no usable fields); `active` but required config
absent → "configuration needed"; `active` + configured → normal fields. "Configured" is a per-add-on predicate
(e.g. captcha = both keys present).
**Rationale**: one mapping keeps every settings section consistent (FR-006) and reads from the single source of truth
(US1).
**Alternatives**: per-section bespoke logic — rejected (drift, inconsistency).

## R4. Captcha write-only secrets

**Decision**: The secret key is stored but never rendered: the field shows a neutral "set / not set" affordance, the
input is empty on load, and saving an empty value leaves the stored secret unchanged. No secret appears in HTML or
any API/REST response.
**Rationale**: FR-008/SC-003; standard write-only secret handling; prevents leakage via view source or responses.
**Alternatives**: masked echo of the stored secret — rejected (still exposes length/value via source).

## R5. Admin asset scoping

**Decision**: Continue the Spec 057 pattern — `corex-admin-tokens.css` is registered by `corex-core` and enqueued
only by CoreX admin screen handles; M6 admin CSS (cards/tables/badges) is likewise registered and enqueued per CoreX
screen, declaring `corex-admin-tokens` as a dependency. Nothing is enqueued globally or on the frontend.
**Rationale**: Principle VI + the existing tested precedent (`AdminTokenAdapterTest`); guarantees no global restyle /
no frontend branding (FR-010/SC-004).
**Alternatives**: `admin_enqueue_scripts` global enqueue — rejected (Principle VI; would leak onto all wp-admin).

## R6. Setup Wizard scope

**Decision**: Reuse the existing setup foundation (`corex-kit-company` SetupWizard + `corex-config` setup) as a guided
first-run screen styled via the adapter; a full multi-step flow is a later refinement.
**Rationale**: avoids rebuilding; M6 is admin experience + truthful state, not a new wizard engine (YAGNI).
**Alternatives**: a new multi-step wizard — rejected (scope).

## R7. Pro-required source

**Decision**: An explicit, optional `proRequired` descriptor flag on the admin add-on descriptor (default false). No
licensing/entitlement; the flag only drives the disabled indicator.
**Rationale**: FR-005 — a static, non-actionable indicator; keeps Pro out of M6 beyond a label.
**Alternatives**: derive from the Free/Pro boundary matrix — deferred (heavier coupling; the boundary matrix is
release-side, not a per-add-on admin descriptor).

## Cross-cutting

- **Security**: enable/disable + settings save through the shared `AdminGuard` (cap+nonce); output escaped; secrets
  write-only (Principle VII).
- **i18n/RTL/a11y**: all admin strings `corex` text domain; logical properties; landmarks/focus/contrast; status not
  by color alone; reduced-motion respected.
- **Environment gating**: rendered admin a11y/RTL + wp-env evidence ENVIRONMENT-GATED where Docker/browser runtime is
  unavailable, never PASS.
