# Implementation Plan: One launch checklist and an honest Overview

**Branch**: `claude/spec-118-launch-checklist` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

**Input**: `specs/118-launch-checklist/spec.md` (thirteen stories; open choices decided under the owner's 2026-10-21
mandate)

## Summary

`ProductionReadinessSnapshotFactory` stays the one evaluation and grows into the whole launch checklist: the six
hardening checks (group `security`) with the place each is fixed, plus seven site and content checks (group `site`)
read by `SiteReadinessFacts`. Every screen turns the snapshot into rows through one pure `LaunchChecklist` view model
and one `ReadinessWords` vocabulary: the Overview's readiness card, Operations & Security (Overview, Environment and
Hardening tabs), the Dashboard widget, and a new `GET corex/v1/readiness` route that the Setup Wizard's launch step
reads. The readiness notifications are raised on a change of state, not on every evaluation. The Overview is rebuilt
as isolated cards (`OverviewCard`), each failing on its own with a logged reference; it opens with four state tiles
fed by CoreX's health report and a new PHP fatal error record in corex-core. Activity sentences move into one
`ActivitySentences` class read by the Overview, Insights and the Access audit log. The forms card counts through the
inbox's own query service, which learns an `open` status, an `unread` filter and reads them from its address. The
data card lists records-group sources only. A wizard apply is recorded as applied through the kit provisioner's own
rule. Insights chips become a shared `.corex-state-chip`. Unread finished-export and failed-job notifications are
grouped at read time.

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (vanilla admin scripts, `@wordpress/scripts` React screens)

**Primary Dependencies**: WordPress 7.0+ core APIs; CoreX corex-core contracts; no new library

**Storage**: no schema change. New options: `corex_php_fatal_errors` (not autoloaded; bounded list of
`{time,type,kind,slug}`, 30 days), `corex_readiness_raised` (not autoloaded; the keys of blocked checks whose
notification was raised). New Config key `brand.terms_page` (a page id) saved by Settings.

**Testing**: Pest unit and integration (512M), Jest, Playwright (Overview, Operations, wizard, Insights, Notifications)

**Target Platform**: WordPress 7.0+, single site and multisite, shared hosts

**Project Type**: WordPress framework (plugins, add-ons, admin screens)

**Performance Goals**: no new work on a public request except one `error_get_last()` at shutdown. The readiness
evaluation stays local (no network); Operations evaluates it once per render instead of three times.

**Constraints**: `ReadinessSnapshot` keeps its constructor and `blockingKeys()`; a stored snapshot shape is never read
back, so adding `group` is safe. `NotificationService` gains no method; grouping lives in the repository's reads.
Production still blocks on exactly what it blocked on.

**Scale/Scope**: corex-core (fatal error record, `ApplyOutcome::isComplete()`), corex-config (Readiness, Overview,
Operations, Notifications, Insights, Access, Submissions, Data Models, Settings), corex-kit-company (wizard),
corex-email (one repository read).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Both passes give the same answers.

- [x] **I. Theme is a skin**: PASS. Nothing in the theme.
- [x] **II. Plugins boot themselves**: PASS. The fatal error recorder registers in corex-core's provider in every
  context; the readiness route registers on `rest_api_init`.
- [x] **III. Thin controllers**: PASS. `ReadinessController` calls the factory and the view model; the wizard's Go live
  posts to the existing `OperationsModeController`, which calls `ModeChangeService`.
- [x] **IV. Everything injected**: PASS. New classes are container-built; `HardeningFactSource` replaces the static
  call inside the factory so a test can rebind it.
- [x] **V. Runtime tokens**: PASS. The state chip and the tiles use `--corex-admin-*` variables only.
- [x] **VI. Conditional assets**: PASS. No new asset file; styles go in the shell and screen stylesheets already loaded.
- [x] **VII. Security**: PASS. The readiness route checks `manage_options` (the wizard's and Operations' capability);
  Go live reuses the mode form's nonce and the shared `AdminGuard`; output escaped; the fatal record stores no
  message, path or visitor data, and its source is shown only with `manage_options`.
- [x] **VIII. RTL**: PASS. Logical properties; chips and progress bars measured right to left.
- [x] **IX. No optional dependency**: PASS. Corex Mail, the forms add-on and the kit are resolved lazily and their
  absence is a state ("Needs Corex Mail", "Forms are off", no kit row).
- [x] **X. Spec first**: PASS. This spec, plan and tasks precede the code.

## Design decisions (DECISIONS #430 to #439)

| # | Decision |
|---|---|
| #430 | One readiness evaluation: the factory adds groups, resolution URLs and the site checks; `LaunchChecklist` + `ReadinessWords` are the only way a screen draws it; the kit's unused `LaunchChecklist` is deleted |
| #431 | One set of states, two voices (to-do and gate); "Needs Corex Mail" is `not_applicable` and out of the count |
| #432 | Readiness notifications raised on a change of state, kept in `corex_readiness_raised` |
| #433 | Site checks: brand (name + logo), kit (provisioner), front page (static or `front-page` template), privacy, terms (`brand.terms_page`), test email (Email Studio attempt, source test, sent/opened, 90 days), login protection |
| #434 | Four state tiles; PHP fatal errors recorded by corex-core at shutdown, source shown to administrators only |
| #435 | Each Overview card rendered alone; a failure logs `CoreX Overview: the <card> card could not load (reference <ref>)` |
| #436 | Forms card counts through `SubmissionQueryService`; inbox gains `status=open`, `unread=1` and reads both from the address |
| #437 | `ActivitySentences` is the one source of activity wording; no activity list link until one exists |
| #438 | Data card = records-group sources; Data Models reads `?source=`; a wizard apply marks the kit applied through `KitProvisioner`, legacy sites recognised from `_corex_kit_page` |
| #439 | Notification groups at read time (finished exports, failed jobs, by job kind); shared `.corex-state-chip` for Insights and readiness rows |

## Project Structure

### Documentation (this feature)

```text
specs/118-launch-checklist/
├── spec.md
├── plan.md
├── tasks.md
└── checklists/requirements.md
```

### Source Code (repository root)

```text
plugins/corex-core/src/Health/FatalErrorRecorder.php        (new) shutdown capture
plugins/corex-core/src/Health/FatalErrorLog.php             (new) bounded option store
plugins/corex-core/src/Provisioning/ApplyOutcome.php        isComplete()
plugins/corex-config/src/Readiness/                         (new) SiteReadinessFacts, SiteChecks, LaunchChecklist,
                                                            ReadinessWords, ReadinessController, HardeningFactSource
plugins/corex-config/src/Operations/ReadinessSnapshot.php   group + not_applicable
plugins/corex-config/src/Operations/ProductionReadinessSnapshotFactory.php
plugins/corex-config/src/Security/OperationsSecurityScreen.php
plugins/corex-config/src/Security/SecurityCenter.js         LaunchChecklist panel in groups and words
plugins/corex-config/src/Notifications/CommandCenterWidget.php
plugins/corex-config/src/Notifications/Producers/ReadinessNotificationProducer.php
plugins/corex-config/src/Notifications/WpNotificationRepository.php + NotificationGroups.php (new)
plugins/corex-config/src/admin/notifications/NotificationItem.js, NotificationsApp.js, bell drawer
plugins/corex-config/src/Overview/                          OverviewRenderer (cards), Cards/* (new), OverviewTiles
plugins/corex-config/src/Activity/ActivitySentences.php     (new)
plugins/corex-config/src/Insights/                          widgets, screen title, facts
plugins/corex-config/assets/insights.js, insights.css, control-panel.css, operations-security.css
plugins/corex-config/src/Submissions/                       query `open` / `unread`, inbox URL filters
plugins/corex-config/src/admin/data/useDataExplorer.js      `?source=`
plugins/corex-config/src/Settings/SettingsRegistry.php      brand.terms_page
plugins/corex-core/assets/css/corex-admin-shell.css         .corex-state-chip
addons/corex-kit-company/                                   wizard launch step, apply marks applied, checklist removed
addons/corex-email/src/Delivery/EmailAttemptRepository.php  latestAcceptedTest()
```

## Complexity Tracking

No constitution violation. Two vocabularies for one state set are deliberate (#431): the stories require both and a
single class owns them.
