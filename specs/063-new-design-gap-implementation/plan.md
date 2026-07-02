# Implementation Plan: New Design Gap Implementation

**Branch**: `spec/063-new-design-gap-implementation` | **Date**: 2026-07-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/063-new-design-gap-implementation/spec.md`

## Summary

Close the *implementation-ready* CoreX design gaps from the new "Corex Final Design Gap-Closure"
package in priority order, keeping one hard invariant: **every surface communicates its real state — no
fabricated data, integrations, Pro/marketplace/licensing behavior, or dead entry points.** The work is
one parent goal split into independently shippable batches (Phase 0–8). Each batch reuses the frozen M2
tokens and the merged M6 admin shell/login, is spec-first, guard-gated, tested (Pest/Jest/Playwright),
i18n/RTL/WCAG-verified, and documented before it ships. Batches whose owner decision is unresolved
(Operations Mode model, Security Center scope, Access & Abilities scope, Forms-vs-Flow model, Email
Studio boundary, Data-model-manager scope, Company Site Kit coverage) **stop for owner sign-off** rather
than inventing scope.

## Technical Context

**Language/Version**: PHP 8.3+ (WordPress 7.0+), JS (ES2020, `@wordpress/scripts`), SCSS/CSS.

**Primary Dependencies**: WordPress core + FSE, Corex framework (`corex-core` PSR-11 container,
`Corex\Security\Admin\AdminGuard`, events, services/repositories), `corex-config` admin plugin,
`corex-blocks`, the forms/mail framework packages, `corex-media`/`corex-captcha` optional add-ons, the
`KitProvisioner`/`CompanyBlueprint` provisioning. No new hard dependencies; ACF/Woo/Polylang stay behind
drivers (Principle IX).

**Storage**: WordPress options (settings via `SettingsStore`), custom Corex data tables/models via the
existing data layer, WordPress posts for Blog. Secrets are write-only options.

**Testing**: Pest (PHP unit/integration), Jest (JS/blocks + admin React), Playwright render harness
(`tests/e2e/render-admin.mjs`, dark+light). Guards: clean-code-guard, wp-guard, test-guard, docs-guard
(woo-guard only if Woo code is touched).

**Target Platform**: wp-admin (CoreX screens only, scoped `--corex-admin-*`) + FSE block theme frontend.

**Project Type**: WordPress framework (plugins + theme monorepo mapped into a dev `wp/` install).

**Performance Goals**: Conditional asset loading (Principle VI); no global admin CSS/JS; lazy media; no
heavy admin animation; no global slider JS; reduced-motion respected.

**Constraints**: Truthful state only; tokens/`theme.json`/logical CSS only (no hardcoded
colors/sizes/fonts/directional CSS unless unavoidable + documented); nonce+capability+confirmation on
dangerous mutations; secrets write-only; WooCommerce dual-gated; Framework Mode (no `sites/<client>/`).

**Scale/Scope**: One parent goal, 9 batches (Phase 0–8) spanning admin product UI + theme; each batch
independently shippable. This plan sequences the batches; each batch owns its detailed design at
implementation time.

## Constitution Check

*GATE: Must pass before implementation. Re-checked per batch.*

Derived from `.specify/memory/constitution.md` (Corex Constitution v1.2.1).

- [x] **I. Theme is a skin** — Admin logic stays in `corex-config`/`corex-core`; the theme receives only
  presentation (Blog templates/parts/patterns, blocks). No business logic/CPT/bootstrapping in the theme.
- [x] **II. Plugins boot themselves** — New admin features register on `plugins_loaded`/`admin_menu` via
  service providers in `corex-config`/`corex-core`; work in CLI/REST/admin without a theme.
- [x] **III. Thin controllers, fat services** — Follows the existing pattern: pure view models +
  services (e.g. `SiteStatusCard`, `ControlPanelStatus`, `AddonStatusResolver`) and renderers that only
  escape/lay out; data access via readers/repositories (`SubmissionsReader`, data layer).
- [x] **IV. Everything injected** — New services resolve through the PSR-11 container in the config
  service provider; no `new` of a dependency inside a method.
- [x] **V. Runtime tokens** — All styling via the scoped `--corex-admin-*` adapter and `theme.json`
  variables; no build-time token system; no raw hex/size/font.
- [x] **VI. Conditional assets** — Admin CSS/JS enqueued only on CoreX screens; block CSS/JS declared in
  `block.json`, loaded only when the block renders; no global library.
- [x] **VII. Declarative security** — REST/AJAX routes declare middleware; admin-menu screens route
  cap+nonce through the shared `AdminGuard`; output escaped, input sanitized, queries prepared.
- [x] **VIII. RTL-first** — Logical CSS properties throughout; Arabic/RTL correct by default; mirrored
  primitives; per-screen RTL verification.
- [x] **IX. No optional dep is hard** — Forms/media/captcha/Woo detected behind interfaces/filters
  (e.g. `corex_media_support_summary`, `SubmissionsReader` degrades to zero); framework runs without them.
- [x] **X. Spec is source of truth** — This plan traces to the approved spec.md and the design intake;
  intent changes update the spec first.
- [x] **Guard Gate + Definition of Done** acknowledged for every task: guards run clean; Pest/Jest/E2E
  tests; i18n-ready strings; RTL verified; WCAG 2.2 AA; docs + PROGRESS/DECISIONS updated per batch.

**Result: PASS.** No violations; Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/063-new-design-gap-implementation/
├── plan.md              # This file
├── spec.md              # Feature spec (user stories, FRs, success criteria)
├── tasks.md             # Batched task list (Phase 0–8)
└── checklists/
    └── requirements.md  # Spec quality checklist
```

Per-batch design detail (data-model / contracts / render evidence) is produced at the start of each batch
under this directory as the batch is implemented, keeping design close to the code it governs and
avoiding a large speculative design set for later, owner-gated batches.

### Source Code (repository root)

```text
plugins/corex-config/src/
├── Dashboard/          # Overview site-status view models + renderers (Phase 1)
├── ControlPanel/       # Overview onboarding + domain status (Phase 1)
├── Overview/           # NEW: truthful Overview summary sections (Phase 1)
├── Settings/           # Settings screens, registry, sections, secrets (Phase 5)
├── Addons/             # Add-ons screen + status (Phase 1 summary, Phase 5)
├── Data/               # Data explorer + Data Models CRUD/import/export (Phase 3)
├── Forms/ (new)        # Forms & Flows admin + Submissions Inbox (Phase 2)
├── Email/ (new)        # Email Studio (Phase 2)
├── Operations/ (new)   # Operations Mode (Phase 4)
├── Security/ (new)     # Security Center (Phase 4)
├── Access/ (new)       # Access & Abilities AAM-lite (Phase 4)
├── Insights/           # Insights widgets (Phase 6)
└── Setup/ (as needed)  # Setup Wizard + Launch Checklist (Phase 6)

plugins/corex-core/src/  # Foundation: AdminGuard, container, events, capability model
plugins/corex-blocks/    # Company/blog blocks (Phase 7)
theme/                   # FSE Blog templates/parts/patterns, social share (Phase 7)
addons/                  # corex-media, corex-captcha, corex-kit-company (gated)
tests/                   # Pest (tests/Unit, tests/Integration), Jest, e2e render harness
```

**Structure Decision**: Extend the existing `corex-config` admin plugin (pure view model + service +
renderer + Pest tests, mirroring `Dashboard`/`ControlPanel`/`Addons`) and the FSE theme; no new
top-level projects. New admin subsystems get their own namespaced subdirectory under
`plugins/corex-config/src/` and register through `ConfigServiceProvider`.

## Batch sequencing (maps to spec user stories)

- **Phase 0 → cross-cutting FR-001..FR-004** (truthfulness/gates/docs). No runtime code; unblocks all.
- **Phase 1 → US1 / FR-010..FR-012** (Admin Overview truthful states). MVP; reuses Dashboard/ControlPanel.
- **Phase 2 → US2 / FR-020..FR-023** (Forms & Flows + Submissions + Email Studio). Owner-gated model.
- **Phase 3 → US3 / FR-030..FR-032** (Data Models CRUD + import/export + migrations). Owner-gated scope.
- **Phase 4 → US3 / FR-040..FR-042** (Operations Mode + Security + Access). Owner-gated scope.
- **Phase 5 → FR-050..FR-051** (Settings/media/retention/advanced; provider-specific captcha).
- **Phase 6 → US4 / FR-060..FR-061** (Insights + Setup Wizard).
- **Phase 7 → US4 / FR-070..FR-071** (Blog + social share + Company Site Kit gaps + core blocks).
- **Phase 8 → SC verification** (docs, screenshots, full verification suite, PR, handoff).

Cross-cutting FR-080..FR-082 (dark/light/RTL/keyboard/reduced-motion/WCAG, nonce+cap+confirm, write-only
secrets, self-disabling add-ons, Woo dual-gating) apply to **every** batch and are in each batch's DoD.

## Complexity Tracking

No constitution violations. Table intentionally empty.
