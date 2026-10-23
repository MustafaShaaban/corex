# Tasks: One launch checklist and an honest Overview

**Input**: [plan.md](plan.md), [spec.md](spec.md). Tests are written with each story (constitution: tests mandatory).
`[P]` = can run in parallel with the other `[P]` tasks of the same phase (different files).

## Phase 1: Setup

- [ ] T001 Scratch WordPress for the worktree on port 8918, database `corex_w118`
- [ ] T002 Re-read `DECISIONS.md` on origin/main; claim #430 to #439

## Phase 2: Foundation — the one readiness model (US-OVR-01, US-OVR-03, US-SEC-09)

- [ ] T003 `ReadinessSnapshot` accepts `group` and the `not_applicable` state
- [ ] T004 `HardeningFactSource`; the factory gives each hardening check a resolution URL and reads "Could not check"
  when the facts throw
- [ ] T005 `SiteReadinessFacts` (WordPress boundary, each fact guarded) and `SiteChecks` (pure) for the seven site and
  content checks; `EmailAttemptRepository::latestAcceptedTest()`; `brand.terms_page` in `SettingsRegistry`
- [ ] T006 `ReadinessWords` (two voices, counts) and `LaunchChecklist` (groups, counts, rows)
- [ ] T007 Unit tests: SiteChecks, LaunchChecklist, ReadinessWords, ReadinessSnapshot groups; integration: the factory
  on a real site (front page with theme template, privacy draft, terms not chosen, test email captured vs sent, Corex
  Mail off, facts throwing)
- [ ] T008 Delete the kit add-on's `Setup/LaunchChecklist.php` and its tests

## Phase 3: Notifications and widget read the model (US-OVR-01)

- [ ] T009 `ReadinessNotificationProducer` raises on a change of state (`corex_readiness_raised`)
- [ ] T010 `CommandCenterWidget` shows "N of M ready" and the open checks in the gate voice
- [ ] T011 Tests: ten evaluations leave one notification and occurrences 1; passing resolves; failing again raises

## Phase 4: Operations reads the model (US-SEC-09, US-OVR-03)

- [ ] T012 `OperationsSecurityScreen`: one snapshot per render; Hardening tab drawn from the security group; payload
  carries groups, words and counts
- [ ] T013 `SecurityCenter.js` LaunchChecklist panel: two groups, chip per row, "N of M ready", links on open rows
- [ ] T014 Jest for the panel; Pest for the Hardening tab words

## Phase 5: The Overview (US-OVR-02, 04, 05, 06, 07, 08)

- [ ] T015 `OverviewCard` isolation and `OverviewCardFailure` (reference + error log) in `OverviewRenderer`
- [ ] T016 Readiness card from `LaunchChecklist` (to-do voice, progress bar, wizard link)
- [ ] T017 corex-core `FatalErrorRecorder` + `FatalErrorLog`; `OverviewTiles` (health, environment, login, add-ons)
- [ ] T018 Forms card through `SubmissionQueryService`; inbox `status=open`, `unread`, filters from the address
- [ ] T019 `ActivitySentences`; activity card, Insights security card and Access audit log use it
- [ ] T020 Data card: records group, edit/read-only, deep links; `useDataExplorer` reads `?source=`
- [ ] T021 Styles: `.corex-state-chip` in the shell; Overview tiles, rows, progress bar
- [ ] T022 Tests: unit (tiles, sentences, fatal source), integration (each card made to throw; forms counts on real
  submissions; fatal record write and no-write)

## Phase 6: Kit and wizard (US-OVR-09, US-WIZ-06)

- [ ] T023 `ApplyOutcome::isComplete()`; the wizard REST and form applies mark the kit applied through the shared
  rule; `BlueprintKitProvisioner` recognises recorded pages
- [ ] T024 `ReadinessController` (`GET corex/v1/readiness`) with the Go live form's fields
- [ ] T025 Wizard launch and Done steps; `OperationsModeController` returns to the wizard on `corex_return=setup`
- [ ] T026 Tests: Jest launch/Done steps; integration apply marks applied, partial apply does not, legacy pages

## Phase 7: Insights and notification groups (US-INS-02, US-SHL-09)

- [ ] T027 Insights: chip per state with icon, subject icons, titles, forms counts "Could not load"
- [ ] T028 `NotificationGroups`; repository lists and counts groups; item shows count and members; group actions
- [ ] T029 Tests: unit grouping rules; Jest group item; integration counts

## Phase 8: Polish

- [ ] T030 Playwright: Overview, Operations, wizard launch, Insights chips, bell grouping; measured at 1280 and 375,
  dark, RTL
- [ ] T031 Guards (clean-code, wp, test, docs); CHANGELOG `[Unreleased]`, PROGRESS "In flight", DECISIONS #430 to #439
- [ ] T032 Merge origin/main, push, open the PR, CI green
