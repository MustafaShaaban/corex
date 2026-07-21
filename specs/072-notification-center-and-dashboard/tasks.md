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
- [X] **T011** [US3] Add `CorexAbility::MANAGE_NOTIFICATIONS` + `notifications` group to the catalog; `AREA_NOTIFICATIONS`; `AdminPage::requestAbilityFor` section key. Update the ability-coverage test. (35 Access unit tests green; admin inherits via MANAGE_ADMIN.)

## Phase 5: Producers + REST (B5/B7)
- [X] **T012** [US4] Producer registry + dependency-aware registration. Failing tests per producer. (`NotificationProducerRegistry` — availability-gated, idempotent; wired into ConfigServiceProvider boot; 2 unit tests green.)
- [~] **T013** [US4] Implement producers: Submission (new/assigned + Phase A email-failure), Email Studio failure, Job failure/export-complete, Access request, Security lockout/hardening, Readiness blocker/cleared. Dedup keys per spec.
  - [X] **Submission producer** — `SubmissionProcessedEvent` (new forms domain event, dispatched from `FlowVisitorSubmissionService` after a stored submission, carries Phase A's `NotificationDelivery`); `SubmissionNotificationProducer` publishes `submission.new` (occurrence-merged per form → `MANAGE_SUBMISSIONS`) and, on genuine delivery failure, `submission.email_failed` in the `email` category (so T021's channel policy blocks re-emailing it). 4 unit tests + live end-to-end verified.
  - [X] **Access request producer** — `AccessRequestedEvent` (new event dispatched from `AccessService::requestAccess` via an optional `EventDispatcher`, mirroring its existing optional-mailer output); `AccessRequestNotificationProducer` publishes `access.request` (ACTION, `access` category, unique dedup `access.request:{id}` so each request is its own notification → `MANAGE_ACCESS`). 3 unit tests + live end-to-end verified.
  - [X] **Job failure producer** — `JobFinishedEvent` (dispatched from `JobRunner` at every terminal state via a DRY `persist()` helper, EventDispatcher injected); `JobFailureNotificationProducer` publishes `job.failed` (ERROR, `jobs` category, dedup `job.failed:{id}` → `MANAGE_OPERATIONS`) on failure only; the raw error summary is deliberately kept out of the notification (no secret-free guarantee). 3 unit tests (incl. a redaction assertion) + live end-to-end verified. Export-complete is a later producer-side addition on the same event.
  - [X] **Export-ready producer** — `ExportReadyNotificationProducer` reuses `JobFinishedEvent` (state=completed + `.export` kind convention, no new plumbing); publishes `export.ready` (INFORMATION, `imports_exports` category, dedup `export.ready:{id}`) to the **actor who ran it** (`forUser`). 4 unit tests + live end-to-end verified.
  - [X] **Login-lockout producer** — `LoginLockoutEvent` (dispatched from `LoginProtectionEnforcer` only on the transition into a lockout, i.e. decision reasonCode `threshold_exceeded`, via an optional `EventDispatcher`); `LoginLockoutNotificationProducer` publishes `security.lockout` (WARNING, `security` category) keyed by identity (`security.lockout:{identity}` so repeated lockouts of one account merge) → `MANAGE_OPERATIONS`. 4 unit tests + live end-to-end verified.
  - [ ] Remaining producers: submission **assigned**; Email Studio failure; Readiness blocker/cleared. Each needs a signal from its subsystem (most emit no domain event yet) — one slice each.
- [~] **T014** [US1] `NotificationController` (REST corex/v1/notifications): list, counts, read/unread, dismiss, snooze, resolve, preferences, grouped detail. Two-tier gate; bounded pagination; envelope. Failing tests first.
  - [X] Core endpoints shipped: `GET /notifications` (bounded, filtered), `/count`, `GET /notifications/{id}` (grouped detail), `POST {id}/read|unread|dismiss|snooze`, `POST /read-all`, `POST {id}/resolve`. Two-tier gate (read/own-action = logged-in + nonce; manage = `MANAGE_NOTIFICATIONS` + nonce); `ResponseEnvelope`. `NotificationService` grew the `…ForCurrentActor` + `resolveById` surface; `WpNotificationRepository::findForActor` added. Shared `RecordingNotificationService` test double extracted. 4 REST integration tests (list/count, nonce-gated read, visibility 404, manage resolve) + 9 repo integration + unit green; 9 routes verified live.
  - [ ] **preferences** endpoints wait on T020 (`WpNotificationPreferenceStore`).

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
