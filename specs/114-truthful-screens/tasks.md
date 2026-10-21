# Tasks: Screens that tell the truth

**Input**: [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md),
[contracts/interfaces.md](contracts/interfaces.md). Tests are written with each story (constitution: tests mandatory).
`[P]` = can run in parallel with the other `[P]` tasks of the same phase (different files).

## Phase 1: Setup

- [ ] T001 Scratch WordPress for the worktree on port 8914, database `corex_w114`; CoreX plugins active, mail captured
- [ ] T002 Re-read `DECISIONS.md` on origin/main; claim #390 to #398

## Phase 2: User Story 9 — Settings save only what changed (US-SET-14, P1)

- [ ] T003 [US9] `AdminDashboard::maybeSave()` skips Hidden and Disabled sections; `saveField()` writes only a changed
  value (checkbox on/off, text as typed) in `plugins/corex-config/src/Settings/AdminDashboard.php`
- [ ] T004 [US9] Integration test `tests/Integration/Config/SettingsSaveChangedOnlyTest.php`: Media inactive keeps
  stored conversion; Captcha absent keeps captcha options; an unchanged save writes nothing; a switch turned off is
  saved off; a refused value is refused and nothing else changes

## Phase 3: User Story 7 — No SSO on the login screen (US-SET-10, P1)

- [ ] T005 [P] [US7] Remove the SSO slot from `AdminBranding`, `BrandingService::loginSsoEnabled()`, the Brand field in
  `SettingsRegistry`, and the slot's rules in `plugins/corex-core/assets/css/corex-admin-login.css`
- [ ] T006 [US7] Update `tests/Unit/Config/LoginSsoTest.php` (no control for any stored value), `AdminAppearanceTest.php`,
  `SettingsTabsTest.php`; Playwright check that the login page has no sign-in control besides the form

## Phase 4: User Story 6 — Add-on toggles say what happened (US-SAFE-08, P1)

- [ ] T007 [US6] `AddonToggleResult` and `AddonActivator` returning WordPress's answer; disable for this site only
- [ ] T008 [US6] `AddonToggleService`: refuse Network and MustUse, call the activator, record `addon.enabled` /
  `addon.disabled` in the activity log
- [ ] T009 [US6] `AddonsScreen`: locked switch with its reason, notices from the result, kit prompt only on success
- [ ] T010 [US6] Tests: unit `AddonToggleServiceTest`, integration `AddonToggleTest` (missing file, real toggle,
  must-use scope), multisite check that a sub-site cannot deactivate a network-active add-on

## Phase 5: User Story 3 — Failed PageSpeed runs (US-SAFE-12, P1)

- [ ] T011 [US3] `InsightResult::failed()`, `InsightFailure` (kinds, recognition of old rows), `InsightStore`
  reading through it and `scoredHistory()`
- [ ] T012 [US3] `PerformanceProvider` and `PsiNormalizer` return failures; local reason text in `PsiDiagnostic`
- [ ] T013 [US3] `insights.js` / `insights.css`: "Could not run", reason, Retry; `InsightWidgets` state for a failure
- [ ] T014 [US3] Tests: unit for result, recognition (English, translated, HTTP number), store, provider; Jest for the
  card

## Phase 6: User Story 2 — Login endpoints row (US-SAFE-11, P1)

- [ ] T015 [US2] `LoginAddressState`; `LoginRouteGuard::hidesDefaults()` and `OperationsSecurityScreen` use it; recovery
  row text with how to end it
- [ ] T016 [US2] Tests: unit truth table; update `RecoveryOnTest`; Playwright: whenever the row says Hidden,
  `/wp-login.php` serves no login form

## Phase 7: User Story 1 — True Overview (US-SAFE-10, P1)

- [ ] T017 [US1] `OverviewFacts` (brand, insights, captcha, login, each guarded); `OverviewModel` rows; renderer links;
  remove `ControlPanelStatus` / `DomainStatus` and their test
- [ ] T018 [US1] Tests: unit `OverviewModelTest` for every row state including "Could not check"; integration on a
  fresh site; update `AdminScreenVisualContractTest` / `BootContextsTest` where they name the renderer

## Phase 8: User Story 4 — Security events (US-SAFE-13, P2)

- [ ] T019 [US4] `WpActivityRepository::query()` area list; `ActivityRepository` docblock
- [ ] T020 [US4] Actions in `OperationsModeStore::set()` and `AccessAuditLog::record()`; `SecurityActivityRecorder`
  and `SecurityEventsSince` registered in the service provider; lockout listener
- [ ] T021 [US4] `InsightWidgetFacts::securityEvents()` asks the three areas; labels in words; `InsightWidgets` copy and
  "Recorded since"
- [ ] T022 [US4] Tests: integration — the four events after 30 trashed submissions; one event per counted entry; the
  empty message; unit for the area list query

## Phase 9: User Story 5 — Captcha test button (US-SAFE-16, P2)

- [ ] T023 [US5] `captcha-admin.js`: `SECRET_FIELD_ID`, `ready`, the hint; `CaptchaServiceProvider` passes `ready`;
  `CaptchaDiagnostic` sentences
- [ ] T024 [US5] Tests: Jest with the real id (button, hint, live region); PHP test that the form's secret id equals the
  script's; Playwright: the button under the secret field with keys saved

## Phase 10: User Story 8 — Blog Pro reading data (US-BLG-05, P1)

- [ ] T025 [US8] `BlogProApp.js`: the one line instead of the panel and period line; Sharing card removed; screen config
  and subtitle in `BlogProScreen.php`
- [ ] T026 [US8] Tests: Jest for both states; update `blog-pro.spec.js`

## Phase 11: Polish

- [ ] T027 DECISIONS #390–#398; CHANGELOG `[Unreleased]` with Client impact and the WebP check; PROGRESS "In flight"
- [ ] T028 Guards: clean-code-guard, wp-guard, test-guard, docs-guard on the diff
- [ ] T029 Full Pest unit + integration, Jest, lint, and the touched Playwright specs on the scratch site; RTL check in
  the Arabic admin
- [ ] T030 Push, open the PR, CI green

## Dependencies

Phase 1 first. Stories 9, 7, 6, 3 and 8 are independent. Story 2's `LoginAddressState` comes before story 1 (the
Overview's Login row) and story 3's failed result before story 1's Insights row. Phase 11 last.
