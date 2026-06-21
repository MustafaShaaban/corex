---
description: "Task list for Spec 060 — CoreX Admin Product Experience"
---

# Tasks: CoreX Admin Product Experience

**Input**: design docs in `specs/060-corex-admin-product-experience/`. **Tests**: REQUIRED (Corex DoD).

**Foundation reused:** `corex-core` `Foundation\Addon*` (state facts + ordering), the scoped `--corex-admin-*`
adapter (Spec 057 US4), the existing CoreX admin screens + `AdminGuard`.

## Phase 1: Setup

- [ ] T001 Confirm the scoped admin adapter + existing admin screen handles; record the screens M6 restyles
  (Dashboard/Add-ons/Data/Settings/Setup/Readiness).

## Phase 2: US1 — truthful add-on state model (P1)

- [ ] T002 [US1] Pest `AddonStatusResolverTest` (RED): the state matrix — every (pro/installed/active/dependency/
  flag/woo) combination resolves to exactly one expected `AddonStatus`; `canToggle` rules.
- [ ] T003 [US1] Add `AddonStatus` enum (+ `isUsable/isInstalled/canToggle`) and pure `AddonStatusResolver` in
  `corex-core/Foundation`, ordering per contract C1. GREEN.
- [ ] T004 [US1] Add-ons admin screen: render each add-on's state; enable/disable + settings access for installed
  only; no enable for not_installed; name missing dependency / WooCommerce. Pest for screen state + no-install-action.
- [ ] T005 [P] [US1] ENV-gated Playwright `admin-addons` (a11y/RTL) — recorded ENVIRONMENT-GATED if no browser.

## Phase 3: US2 — state-aware settings + captcha (P2)

- [ ] T006 [US2] Pest `SettingsSectionStateTest` (RED): section display per add-on state (hidden/disabled/config-
  needed/normal).
- [ ] T007 [US2] Derive settings-section state from `AddonStatus` + a per-add-on "configured" predicate; apply to the
  Settings screen. GREEN.
- [ ] T008 [US2] Pest `CaptchaSettingsStateTest` (RED): reCAPTCHA across not-installed/inactive/active-no-keys/
  active-configured; secret write-only (never rendered; empty submit preserves stored).
- [ ] T009 [US2] Implement captcha settings state + write-only secret handling in `corex-captcha`. GREEN.

## Phase 4: US3 — scoped admin visual design (P2)

- [ ] T010 [US3] Pest `AdminAssetScopingTest` (RED): the adapter + M6 admin CSS are registered, depend on
  `corex-admin-tokens`, and are NOT globally enqueued / not on the frontend.
- [ ] T011 [US3] Author scoped admin CSS (cards/tables/topbar/status badges/states) consuming `--corex-admin-*`;
  apply to the CoreX screens (dark+light, logical properties). No raw literals outside the admin-token allowance.
- [ ] T012 [US3] Conditional enqueue per CoreX screen (declares `corex-admin-tokens` dep). GREEN; `lint:css` clean.
- [ ] T013 [P] [US3] ENV-gated Playwright `admin-visual` (contrast/focus/RTL/reduced-motion/narrow/zoom, dark+light).

## Phase 5: US4 — setup, readiness, universal states (P3)

- [ ] T014 [US4] Readiness/Status screen renders env-gated checks as gated (never passing); Pest assertion.
- [ ] T015 [US4] Universal states (loading/empty/error/success/permission-denied) on data-bearing screens; setup
  guided first-run styled via the adapter. Pest where headless-testable.

## Phase 6: Polish & Final Gate

- [ ] T016 [P] Docs: docs-app admin-experience page (screens, states, captcha rule, scoping, a11y/RTL) + sidebar;
  docs-guard.
- [ ] T017 Full gate: `composer test`, `npm run test:js`, `npm run build`, docs-app build, `lint:css`; ENV-gate
  wp-env/Playwright honestly.
- [ ] T018 Whole-diff Guard Gate (wp/clean-code/test/docs) clean.
- [ ] T019 Update PROGRESS/ROADMAP/CHANGELOG; DECISIONS entry (display resolver, settings-state derivation, captcha
  write-only, admin scoping).

## Dependencies

- Setup (T001) → US1 (T002-T005) → US2 (T006-T009) → US3 (T010-T013) → US4 (T014-T015) → Polish (T016-T019).
- US2/US3 consume the US1 resolver. Same-file: admin CSS edits sequential.

## Notes

- RED→GREEN per test. Pure resolver first (headless). Reuse adapter/guard/screens; do not rebuild.
- Secrets write-only. No global wp-admin restyle, no frontend branding, no marketplace/install-from-admin, no Pro
  licensing, no generators. ENVIRONMENT-GATED steps recorded honestly.
