# Tasks: Mail stays where it should

**Input**: [spec.md](spec.md), [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md),
[contracts/rest.md](contracts/rest.md), [quickstart.md](quickstart.md)

Order: story 1, story 5, story 2, story 3, story 4 (plan "Order of work"). [P] = can run alongside the task before it.

## Phase 1: Story 1, one setting (US1, FR-001 to FR-004b)

- [ ] T001 [US1] `plugins/corex-core/src/Environment/SiteEnvironment.php` and `SiteEnvironmentAnswer.php`: the
  resolver of research R1 (dotenv, network lock, `corex_app_env`, mode option, WordPress type), with `allowsMode()`.
- [ ] T002 [US1] Bind it in `CoreServiceProvider`.
- [ ] T003 [P] [US1] `tests/Unit/Environment/SiteEnvironmentTest.php`: every row of the precedence matrix, `local`
  mapping, unknown values, upgrade with nothing set.
- [ ] T004 [US1] `OperationsModeStore::current()`/`set()` apply the lock (R2); `ModeChangeService` returns the
  refusal; `ModeCommand` prints it.
- [ ] T005 [US1] `OperationsSecurityScreen`: mode card shows the lock and where it is set, disables the modes the lock
  refuses; conflict notice compares mapped values. `operations-mode.js` respects disabled options.
- [ ] T006 [US1] Insights facts and Settings `advanced.environment` read the resolver.
- [ ] T007 [US1] Email Studio's three `environment()` copies read the resolver (R5).
- [ ] T008 [US1] Integration test `tests/Integration/Environment/SiteEnvironmentTest.php`: mode change reaches Email
  Studio's payload, Insights and Operations; a lock refuses `set()`; history records the change.

## Phase 2: Story 5, newest first (US5, FR-021 to FR-025)

- [ ] T009 [US5] `EmailStudioStore::latest()`; `WpEmailStudioStore` (`ID DESC`, meta cache primed) and
  `tests/Support/InMemoryEmailStudioStore.php`.
- [ ] T010 [US5] `EmailAttemptRepository::latest()` and `CapturedEmailRepository::latest()` use it.
- [ ] T011 [US5] Index `counts.window`; Overview labels "of the last 100 emails".
- [ ] T012 [US5] `tests/Integration/Email/StudioNewestFirstTest.php`: 600 attempts, newest first, a new one at the top,
  query count independent of rows; fewer than 500 unchanged.

## Phase 3: Story 2, capture (US2, FR-005 to FR-012)

- [ ] T013 [US2] corex-core `Mail/Capture/`: `MailDecision`, `MailPath`, `MailHold`, `MailCaptureStore`,
  `CapturedMessage`, `MailGate` (research R3 table, capture scope R10, refusal record R11).
- [ ] T014 [US2] `WpMailInterceptor` on `pre_wp_mail` with WordPress core names (`WordPressMailNames`, R4); registered
  by corex-core.
- [ ] T015 [US2] corex-email: `CaptureStoreAdapter` binds `MailCaptureStore` to `CapturedEmailRepository` (path, state);
  `MailService::deliver()` asks the gate and returns `captured`; template → path names.
- [ ] T016 [US2] Path scopes at the floors (`NotificationDispatcher`, `SupportMailer`).
- [ ] T017 [US2] `LogsPanel.js` shows path and state of captures; Arabic and RTL check.
- [ ] T018 [P] [US2] `tests/Unit/Mail/MailGateTest.php`: every row of R3 and the scope switch.
- [ ] T019 [US2] `tests/Integration/Mail/MailCaptureTest.php`: the paths of TC-SAFE-01-01 to 13 with a spy; production
  untouched; no store → refused and recorded.

## Phase 4: Story 3, flow tests (US3, FR-013 to FR-017)

- [ ] T020 [US3] `FlowTestService` runs inside `MailHold`; `EmailStudioService` captures `held_for_test` while a hold
  is active; result `held_emails`.
- [ ] T021 [US3] `FlowController::test` returns `held_emails`; `TestTab.js` says emails are held and lists them with a
  link to the log.
- [ ] T022 [US3] `tests/Integration/Forms/FlowTestHoldsMailTest.php` (production with live delivery, CoreX Mail
  inactive, a visitor submit afterwards, delete removes held mail); Jest for `TestTab`.

## Phase 5: Story 4, what Email Studio says (US4, FR-018 to FR-020)

- [ ] T023 [US4] `Delivery/MailOutcome.php` (R9) and the index `outcome`.
- [ ] T024 [US4] `POST corex/v1/email-studio/capture-scope` (R10).
- [ ] T025 [US4] `MailOutcomeBanner.js` on every tab via `StudioPanel`; the switch on the Overview; old hero copy
  replaced.
- [ ] T026 [US4] `Capture/CaptureRetention.php`: daily event, batches of 200, 20 seconds (R7); scheduled by corex-email.
- [ ] T027 [P] [US4] `tests/Unit/Email/MailOutcomeTest.php`; `tests/Integration/Email/CaptureRetentionTest.php`
  (600 captures, 550 old); Jest for the banner; `tests/e2e/email-studio-outcome.spec.js`.

## Phase 6: Polish

- [ ] T028 Docs: `docs/en/03-operations/` (email delivery and modes pages) updated.
- [ ] T029 CHANGELOG `[Unreleased]` with Client impact; DECISIONS #381 onward; PROGRESS.
- [ ] T030 Guard Gate on the diff; full unit, integration, Jest; Playwright specs touched.
