# Implementation Plan: CoreX Notification Center & Dashboard Command Center

**Branch**: `spec/072-notification-center-and-dashboard` | **Date**: 2026-07-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/072-notification-center-and-dashboard/spec.md`

## Summary

Add a shared, recipient-aware Notification Center to CoreX — a store, a bell + drawer, a full screen,
a WordPress-toolbar entry, real producers, preferences, and bounded retention — then a WordPress
Dashboard Command Center that greets an administrator with site state, attention items, and safe
shortcuts. It is **not** a second Activity log: Activity records what happened; a notification is a
targeted, resolvable nudge with per-user state.

Approach: mirror the Activity split exactly — pure contracts and value objects in `Corex\Notifications\*`
(corex-core), WP adapters/controllers/screens in `Corex\Config\Notifications\*` (corex-config). Copy
the proven `ActivityTable`/`WpActivityRepository`/`JobController`/`RetentionSweep`/`InsightWidgets`
patterns rather than invent. Producers publish through a stable contract so the Center never queries a
module's private tables. Phase A's typed `NotificationDelivery` is one of the first producers' inputs.

## Technical Context

**Language/Version**: PHP 8.3+, JavaScript (`@wordpress/element` admin bundle + buildless shell JS)

**Primary Dependencies**: WordPress 7.0+; Action Scheduler when available (else WP-Cron) for retention

**Storage**: Two new managed tables — `notifications`, `notification_user_state` — via the existing
`Table`/`Migrator`/`ManagedTable` primitives; `FOUNDATION_SCHEMA_VERSION` `'2'` → `'3'`.

**Testing**: Pest (unit + integration), Jest (JS), Playwright (E2E), performance tests at 10k rows.

**Target Platform**: WordPress admin (single-site + multisite) + the WP Dashboard + toolbar.

**Performance Goals**: Indexed, bounded queries; no remote calls during header/toolbar/Overview/
Dashboard render; fast at 10,000 notifications (SC-004).

**Constraints**: Every read/mutation re-checks recipient visibility + ability (no leakage via counts
or badges). Loop prevention: a mail-failure notification never sends an email.

**Scale/Scope**: ~9 workstreams (B1–B7 + Phase C + docs), new `Notifications/` and `Dashboard/` trees.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Constitution v1.2.1.

- [x] **I. Theme is a skin** — PASS. No theme changes; all admin/Dashboard surfaces.
- [x] **II. Plugins boot themselves** — PASS. Producers/retention register on boot; the Center works in
  admin/REST/cron. Producer registration is dependency-aware (absent module → nothing).
- [x] **III. Thin controllers, fat services** — PASS. `NotificationController` delegates to
  `NotificationService`; recipient authorization + query building live in services, never in React or
  the controller.
- [x] **IV. Everything injected** — PASS. Store, repository, service, presenter, producers all
  container-bound; no `new` of a dependency in a method.
- [x] **V. Runtime tokens** — PASS. Bell, drawer, screen, widgets use `--corex-admin-*` tokens only;
  no raw hex/size/font; severity never colour-alone.
- [x] **VI. Conditional assets** — PASS. Shell assets attach to the `corex-notifications` screen via
  the existing slug-prefix pattern; the toolbar loads a **minimal** asset, never the full admin bundle
  on the front end (FR-017).
- [x] **VII. Declarative security** — PASS. REST uses the two-tier `canManage()`/`canMutate()` gate
  (nonce + ability) from `JobController`; admin screen routes cap+nonce through `AdminGuard`. Every
  query re-checks visibility (FR-002).
- [x] **VIII. RTL-first** — PASS. Logical properties throughout; bell/drawer/screen verified RTL.
- [x] **IX. No optional dep is hard** — PASS. Producers for optional modules (Forms, Email Studio,
  Blog) register only when present; Action Scheduler is detected, WP-Cron is the fallback.
- [x] **X. Spec is source of truth** — PASS. `spec.md` written first (stakeholder-level); mechanics
  live here and in data-model/contracts.
- [x] **Guard Gate + Definition of Done** — acknowledged. `clean-code-guard`, `wp-guard`,
  `test-guard`, `docs-guard`, `ui-ux-pro-max`. Tests, i18n, RTL, WCAG 2.2 AA, docs, PROGRESS,
  DECISIONS before any diff is presented.

**Environment Gate**: re-verified 2026-07-21 — `corex-core`/`corex-config`/`corex-blocks`/`corex-forms`
active; `FOUNDATION_SCHEMA_VERSION` at `'2'` (bump pending). Site boots clean.

### Deviations requiring justification

- **First recurring job (Principle II framing).** The repo has no scheduled job today; retention needs
  one. Justified: bounded, idempotent, `RetentionSweep`-backed, Action-Scheduler-or-cron. Recorded in
  `DECISIONS.md`.
- **Shared admin shell affordance.** `AdminPage` is presentation-only. The bell is added via a new
  `apply_filters('corex_admin_header_actions', '')` in `open()`, following the existing
  `corex_admin_appearance` filter precedent — not by injecting a service into `AdminPage`.

## Project Structure

```text
plugins/corex-core/src/Notifications/         # NEW — pure contracts + value objects
├── Notification.php, NotificationId.php, NotificationType.php, NotificationSeverity.php
├── NotificationStatus.php, NotificationRecipient.php, NotificationAction.php
├── NotificationService.php (contract), NotificationStore.php, NotificationRepository.php
├── NotificationPreference.php, NotificationPreferenceStore.php
├── NotificationPresenter.php, NotificationProducer.php, NotificationChannelPolicy.php
plugins/corex-core/src/Admin/AdminPage.php    # header-actions filter for the bell
plugins/corex-core/src/Access/CorexAbilityCatalog.php   # MANAGE_NOTIFICATIONS + group
plugins/corex-core/src/Activity/ActivityEvent.php        # AREA_NOTIFICATIONS
plugins/corex-core/assets/css/corex-admin-shell.css      # bell + drawer component block

plugins/corex-config/src/Notifications/       # NEW — WP adapters, controllers, screen, producers
├── NotificationTable.php, NotificationUserStateTable.php, WpNotificationRepository.php
├── NotificationServiceImpl.php, WpNotificationPreferenceStore.php
├── NotificationController.php, NotificationsScreen.php, NotificationRetention.php
├── NotificationBell.php (header-actions), NotificationToolbar.php (admin_bar_menu)
├── producers/{SubmissionProducer,EmailProducer,JobProducer,AccessProducer,SecurityProducer,ReadinessProducer}.php
plugins/corex-config/src/Dashboard/           # NEW — Phase C
├── CommandCenterWidget.php + optional widgets, DashboardWidgetFacts.php, DashboardSettings.php
plugins/corex-config/src/ConfigServiceProvider.php       # bindings + schema version bump
plugins/corex-config/src/Overview/OverviewRenderer.php   # Attention Required card
addons/corex-ui/src/ApprovedComponentInventory.php       # register the drawer component
```

**Structure Decision**: mirrors the Activity split; new trees only, no relocation of existing code.

## Workstreams

The B1–B7 (Notification Center) and Phase C (Dashboard) detail is carried verbatim in the approved
master plan (`corex-execution-goal-humming-pie.md`, sections "PHASE B" and "PHASE C") and is the
source of record for implementation. Ordered build sequence:

1. **B1/B2 foundation** — value objects + contracts (corex-core), then the two tables +
   `WpNotificationRepository` (copy `ActivityTable`/`WpActivityRepository`), schema-version bump.
2. **B3 access** — `MANAGE_NOTIFICATIONS` ability, `AREA_NOTIFICATIONS`, visibility re-checks.
3. **B5 producers** — the service + the first producers, fed by Phase A's delivery result and by
   Activity events; dependency-aware registration.
4. **B4 surfaces** — REST (B7) → the full screen → the bell + drawer → toolbar → Overview card.
5. **B6** — preferences, retention (first recurring job), loop prevention.
6. **Phase C** — Command Center widget, then optional widgets + settings.
7. Docs + Phase-B gate.

Each step is TDD (failing test first), guard-clean before the diff, exact-count verification.

## Complexity Tracking

Two justified deviations (first recurring job; shared-shell header-actions filter), both recorded in
`DECISIONS.md`. No standing violations added; producers reuse Activity/Jobs/Retention/Insights
abstractions rather than duplicating them.
