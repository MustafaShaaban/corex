# Tasks: Admin control panel & integrations

**Feature**: 044-admin-control-panel · **Branch**: `feature/044-admin-control-panel`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md) · [research.md](./research.md) · [data-model.md](./data-model.md)
· [contracts/](./contracts) · [quickstart.md](./quickstart.md)

**Tests**: REQUIRED (constitution DoD — Pest + Jest; Playwright smoke env-gated).

**Story legend**: US1 = control panel IA + status + checklist (P1, MVP) · US2 = captcha config + test (P1) ·
US3 = Insights/PSI diagnostics (P1) · US4 = rich add-on manifests (P2) · US5 = authorship metadata (P3).

---

## Phase 1: Setup

- [x] T001 Add a `ControlPanel/` namespace under `plugins/corex-config/src/` and a `Settings/assets/control-panel.css`
  (empty token-only stub) wired to enqueue on the Corex settings + dashboard screens only (conditional, Principle VI).

## Phase 2: Foundational (blocking)

- [x] T002 [P] Pest `tests/Unit/Config/DomainStatusTest.php` — the D2 rules (configured/needs_setup/error; honeypot→configured; recaptcha-without-keys→needs_setup; recorded failed test→error).
- [x] T003 Implement `Corex\Config\ControlPanel\DomainStatus` + `ControlPanelStatus` (pure; Config injected) until T002 green.
- [x] T004 [P] Pest `tests/Unit/Config/OnboardingChecklistTest.php` — lists only not-configured domains; `allSet` when none remain.
- [x] T005 Implement `Corex\Config\ControlPanel\OnboardingChecklist` (pure) until T004 green.

## Phase 3: US1 — Control panel IA + status + checklist (P1) 🎯 MVP

- [x] T006 [US1] Render domain cards in `Settings/AdminDashboard.php` — grouped domains, status badge (icon+text, not color-only), warning + "how to set this up" link when not configured; secrets shown as "set" only. Escaped, i18n, RTL.
- [x] T007 [US1] Render the onboarding checklist on the Corex dashboard from `OnboardingChecklist`; "all set" state when complete; deep-links per step.
- [x] T008 [P] [US1] Fill `Settings/assets/control-panel.css` — token-only with admin-palette fallbacks, logical/RTL, status badges legible without color (WCAG 2.2 AA).

**Checkpoint**: the settings screen is a control panel with accurate per-domain status + a dashboard checklist.

## Phase 4: US2 — Captcha config + test (P1)

- [x] T009 [US2] Extend `Settings/SettingsRegistry.php` captcha section — add `captcha.site_key` (text), `captcha.score_threshold` (number), `captcha.action` (text); keep `captcha.secret` write-only. Reveal key/v3 fields only for the chosen driver (in `AdminDashboard`/`SettingsForm`).
- [x] T010 [P] [US2] Pest `tests/Unit/Config/CaptchaDiagnosticTest.php` — classify ok/missing_keys/invalid_keys/network_error/not_applicable; assert **no secret** in the classified output.
- [x] T011 [US2] Implement `Corex\Config\Captcha\CaptchaDiagnostic` (pure) until T010 green.
- [x] T012 [US2] Implement `Corex\Config\Captcha\CaptchaTestController` — `POST corex/v1/captcha/test`, AdminGuard-gated, runs the configured driver's verify, returns the spec-043 envelope (no secret); register the route. Pest for the envelope shape + a "no secret" assertion.
- [ ] T013 [US2] Wire the captcha card's **Test verification** button to `window.Corex.api` + `Corex.notices` (enqueue `corex-runtime` on the settings screen); inline "where to get keys" links + guidance per driver.

**Checkpoint**: captcha can be configured per-driver and verified with a test button; the secret never leaks.

## Phase 5: US3 — Insights/PSI diagnostics (P1)

- [x] T014 [P] [US3] Pest `tests/Unit/Insights/SiteUrlReachabilityTest.php` — false for localhost/*.local/loopback/private ranges, true for a public host.
- [x] T015 [US3] Implement `Corex\Config\Insights\SiteUrlReachability` (pure) until T014 green.
- [x] T016 [P] [US3] Pest `tests/Unit/Insights/PsiDiagnosticTest.php` — classify local_url/http_error/quota/invalid_key/invalid_response/ok; `detail` scrubs `key=`/tokens; carries no secret.
- [x] T017 [US3] Implement `Corex\Config\Insights\PsiDiagnostic` (pure) until T016 green.
- [x] T018 [US3] Update `Insights/Providers/PerformanceProvider.php` — check reachability first, read `wp_remote_retrieve_response_code`, classify failures via `PsiDiagnostic`; carry an **admin-only** diagnostic detail; replace the generic `unavailable()` message with the classified one.
- [ ] T019 [US3] Add a cap+nonce **test** action to `Insights/InsightsController.php` (`POST corex/v1/insights/test`) returning the classified envelope (admin-only `details`, no secret); wire the Insights "Test key/URL" button to `window.Corex.api`; state key optional/recommended + a docs link. Pest for the action.

**Checkpoint**: a failed performance check yields a specific, actionable cause (incl. local-URL); admin-only raw detail.

## Phase 6: US4 — Rich add-on manifests (P2)

- [x] T020 [P] [US4] Pest `tests/Unit/Addons/AddonManifestTest.php` — the extended `Addon` exposes summary/description/provides/requires/enable-disable/needs-config/docs; `missingKeys()` reflects Config; existing registrations default safely.
- [x] T021 [US4] Extend `Addons/Addon.php` with the rich manifest fields (additive defaults) until T020 green; populate the real add-ons' manifests (accurate to what each registers).
- [x] T022 [US4] Render the rich manifest in `Addons/AddonView.php` + `Addons/AddonsScreen.php` — provides/requires, enable/disable behavior, needs-config + missing keys, docs link; explain an unmet dependency on enable. Escaped, i18n, RTL.

**Checkpoint**: the Add-ons screen explains each add-on accurately before you toggle it.

## Phase 7: US5 — Authorship metadata (P3)

- [x] T023 [P] [US5] Set the `Author:` header to the owner/brand in every `corex-*` plugin main file + the theme `style.css`; grep for the non-existent "team" credit and replace.
- [x] T024 [US5] Document the authorship convention in `CONTRIBUTING` / the docs handbook.

## Phase 8: Polish & Cross-Cutting

- [x] T025 [P] Update docs-app guides (`configuration.md` — the control panel + captcha; `insights.md` — the diagnostics) + corex-config README; add the add-on-manifest note.
- [x] T026 Run the Guard Gate on the full diff: clean-code-guard, wp-guard (AdminGuard cap+nonce, escaping, remote-get, **no secret in any response**), test-guard, docs-guard. Fix findings.
- [x] T027 Token-only scan over `control-panel.css`; confirm status legibility without color (WCAG) + logical/RTL.
- [x] T028 Run suites: `composer test` (Pest) + `npm run test:js` (Jest) green; record counts. Attempt the env-gated Playwright smoke or record it as environment-gated.
- [x] T029 Update `PROGRESS.md` + log `DECISIONS.md` #78; end with the NEXT STEP block. Commit → PR → CI → merge.

---

## Dependencies & order

- Setup (T001) → Foundational (T002–T005) block US1.
- US1 (T006–T008) = MVP. US2 (T009–T013), US3 (T014–T019), US4 (T020–T022) each depend on Foundational/043 but are
  independent of each other (parallelizable). US5 (T023–T024) is independent. Polish last.
- **Parallel**: T002/T004/T010/T014/T016/T020 (`[P]`, different test files); T008 CSS; T023 authorship.

## Implementation strategy

- **MVP = Setup + Foundational + US1** — the control panel with accurate status + checklist. Ship/verify first.
- Then US2 + US3 (the integration-trust fixes, both P1), then US4 (manifests), then US5, then Polish.
- TDD: each pure service's Pest task precedes its implementation (T002→T003, T004→T005, T010→T011, T014→T015,
  T016→T017, T020→T021).
