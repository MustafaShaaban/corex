---

description: "Task list for Spec 072 — Notification Center & Dashboard Command Center"
---

# Tasks: Notification Center & Dashboard Command Center

**Input**: Design documents from `/specs/072-notification-center-and-dashboard/`

**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [data-model.md](./data-model.md). B1–B7 + Phase C detail in the approved master plan.

**Tests**: REQUIRED (Pest unit + integration, Jest, Playwright, performance). Guard Gate before any diff.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup
- [X] **T001** Environment Gate (done 2026-07-21: plugins active, schema at '2'). Baseline suite counts.
- [X] **T002** [P] Confirm guard skills installed.

## Phase 2: Foundation — value objects + contracts (B1) 🎯 blocks everything
- [X] **T003** [US1] Failing Pest unit tests for `NotificationSeverity`, `NotificationCategory`, `NotificationStatus` (closed vocabularies, reject out-of-vocabulary).
- [X] **T004** [US3] Failing Pest unit tests for `NotificationRecipient::canBeSeenBy` — user/users/ability/assigned/category_admins; unauthorized sees nothing.
- [X] **T005** [US1] Failing Pest unit tests for `Notification` — construction, `withOccurrence`, `resolved`, `toArray`/`fromArray`, `assertNoSecretKeys` rejects tokens/credentials.
- [X] **T006** [US1] Implement `Corex\Notifications\` value objects: `NotificationId`, `NotificationSeverity`, `NotificationCategory`, `NotificationStatus`, `NotificationRecipient`, `NotificationAction`, `Notification`, `NotificationPreference`.
- [X] **T007** [US1] Contracts (interfaces): `NotificationRepository`, `NotificationService`, `NotificationProducer`, `NotificationChannelPolicy`; `NotificationQuery` value object. (Store/PreferenceStore/Presenter/Page deferred until their consumers land.)

## Phase 3: Persistence (B2)
- [X] **T008** [US1] `NotificationTable` + `NotificationUserStateTable` (copy `ActivityTable`); register `ManagedTable`s; bump `FOUNDATION_SCHEMA_VERSION` '2'→'3'; add to `installFoundationSchema`.
- [X] **T009** [US1] Failing integration tests for `WpNotificationRepository`: upsert-by-dedup increments occurrences; visibility-filtered `queryForActor`; unread count; per-user mark/dismiss/snooze; resolve/reopen; prune. Real WP + real tables. (9 passing.)
- [X] **T010** [US1] Implement `WpNotificationRepository` (copy `WpActivityRepository` discipline) + `NotificationServiceImpl`.

## Phase 4: Access (B3)
- [ ] **T011** [US3] Add `CorexAbility::MANAGE_NOTIFICATIONS` + `notifications` group to the catalog; `AREA_NOTIFICATIONS`; `AdminPage::requestAbilityFor` section key. Update the ability-coverage test.

## Phase 5: Producers + REST (B5/B7)
- [ ] **T012** [US4] Producer registry + dependency-aware registration. Failing tests per producer.
- [ ] **T013** [US4] Implement producers: Submission (new/assigned + Phase A email-failure), Email Studio failure, Job failure/export-complete, Access request, Security lockout/hardening, Readiness blocker/cleared. Dedup keys per spec.
- [ ] **T014** [US1] `NotificationController` (REST corex/v1/notifications): list, counts, read/unread, dismiss, snooze, resolve, preferences, grouped detail. Two-tier gate; bounded pagination; envelope. Failing tests first.

## Phase 6: Surfaces (B4)
- [ ] **T015** [US2] `apply_filters('corex_admin_header_actions','')` in `AdminPage::open()`; `NotificationBell` renders the count (99+, true count in label); shell CSS + a real drawer component (focus trap, Escape, focus return) added to `ApprovedComponentInventory` with `EXPECTED_COUNTS` bumped.
- [ ] **T016** [US1] `NotificationsScreen` (slug `corex-notifications`) — views/filters, five view-states, bounded server-side filtering, bulk mark-read.
- [ ] **T017** [US2] `NotificationToolbar` (`admin_bar_menu`) outside CoreX screens; never both bells; minimal front-end asset.
- [ ] **T018** [US1] Overview *Attention Required* card (alongside Recent Activity, not replacing it).
- [ ] **T019** Jest + Playwright: bell count, drawer open/close+focus return, 99+ with true label, toolbar-not-doubled, RTL/mobile/dark/light.

## Phase 7: Preferences, retention, loop prevention (B6)
- [ ] **T020** [US5] `WpNotificationPreferenceStore` + preferences UI (mandatory items non-disableable).
- [ ] **T021** [US5] `NotificationChannelPolicy` — block email for the `email` category + mail-failure sources; dedup + per-window cap (no loop).
- [ ] **T022** [US5] `NotificationRetention implements PrunableStore` into `RetentionSweep`; the **first recurring job** (Action Scheduler or WP-Cron) calling `RetentionSweep::apply()`.

## Phase 8: Dashboard (Phase C)
- [ ] **T023** [US6] `CommandCenterWidget` (id `corex_command_center`) — site state, attention (canonical query), security snapshot, navigation-only actions; server-rendered, no remote calls; Screen Options respected.
- [ ] **T024** [US7] Optional opt-in widgets + settings; Development-only rules; never register for users with no data.
- [ ] **T025** Dashboard Jest/Playwright + no-remote-call assertion.

## Phase 9: Performance, docs, gate
- [ ] **T026** Performance tests: 10k notifications — unread count, drawer, Dashboard, filtered center within budget.
- [ ] **T027** Docs: Notification Center guide, Dashboard widget guide, producer-integration guide, retention/privacy, sidebar, README; docs-guard.
- [ ] **T028** Phase-B/C gate: full suites (exact counts), Playwright matrix, all guards, render-verify; PROGRESS + DECISIONS.

**Checkpoint order:** T003–T010 (foundation + store) ship first and are independently testable; nothing in Phases 4+ starts until the store is green.
