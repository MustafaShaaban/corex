# Implementation Plan: Access and logs

**Branch**: `spec/112-access-and-logs` | **Date**: 2026-10-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/112-access-and-logs/spec.md` (seven stories, clarified 2026-10-20)

## Summary

Seven fixes to who may do what, and to what the logs keep: access requests refuse abilities only an administrator
grants, and approval shows what is granted, needs a note and an expiry, and is listed, revocable and ended on time;
refused visits are counted per person instead of written once each; Maintenance closes public submissions (sign-in and
password reset stay open) and says so; the Security Center says when recovery is on and shows both ways back in; Blog
Pro checks each post and comment the way WordPress does; the Data screen leaves out records about access and sign-in
for anyone without the matching ability; and an email resent from the inbox goes to the people it was first sent to.
The design is in [research.md](research.md) (R1 to R13), the stored shapes in [data-model.md](data-model.md), the route
changes in [contracts/rest.md](contracts/rest.md), and the end-to-end checks in [quickstart.md](quickstart.md).

## Technical Context

**Language/Version**: PHP 8.3 (plugins and add-ons); JavaScript built with `@wordpress/scripts` (admin screens)

**Primary Dependencies**: WordPress 7.0+ core APIs (REST, capabilities, WP-Cron, object cache); CoreX corex-core
contracts; no new library

**Storage**: the existing `{prefix}corex_access_requests` table gains five nullable columns (foundation schema 3 → 4,
added by the migration runner); the `corex_access_audit_log` option changes entry shape; submission email meta gains a
recipients list

**Testing**: Pest (unit and integration, 512M memory), Jest for the admin JS, Playwright for the Access, Security
Center, Blog Pro, Data and inbox screens; mail always captured in tests

**Target Platform**: WordPress 7.0+ on shared and managed hosts, single site and multisite; with or without a
persistent object cache

**Project Type**: WordPress framework (plugins, add-ons, admin React screens)

**Performance Goals**: a refused page reload within the hour adds no database row (SC-003); a 503 in Maintenance runs
before any submission handler

**Constraints**: no change to a client-implemented interface signature (`SubmissionEmailGateway::resend()`); a new
`ManagedTable` argument is optional and last; nothing in the public text names a weakness

**Scale/Scope**: seven stories across corex-core, corex-config,
corex-forms and the profile (account), newsletter, bookings, careers and email add-ons

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Checked before research and again after the
design; both passes give the same answers.

- [x] **I. Theme is a skin**: PASS. Nothing goes in a theme.
- [x] **II. Plugins boot themselves**: PASS. The grant scheduler and the Maintenance REST filter register in the
  corex-config service provider; the marker constant is in corex-core; everything works in REST, admin, CLI and cron.
- [x] **III. Thin controllers, fat services**: PASS. Preview, grant, revoke and expiry logic live in `AccessService` and
  the store; Blog Pro checks in one `BlogProPermissions` policy; recipients in `SubmissionEmailService`.
- [x] **IV. Everything injected**: PASS. `LoginRecovery`, `RequestableAbilities`, `BlogProPermissions`,
  `MaintenanceDecision` and the `AccessRefusalRecorder` are container bindings.
- [x] **V. Runtime tokens**: PASS. The grants list and the recovery copy field use existing `--corex-` variables.
- [x] **VI. Conditional assets**: PASS. `AccessGrantsPanel.js` is part of the Access screen's existing bundle.
- [x] **VII. Declarative security**: PASS with a note. The access, Blog Pro and Data routes keep the existing
  `permission_callback` form their controllers use today; the Blog Pro per-post and per-comment checks are one policy
  called from those callbacks, not checks written in controller bodies. Closing public submissions is declared by each
  route (the marker in its registration arguments) and enforced in one place, because the add-ons do not run the
  middleware pipeline (research R8).
- [x] **VIII. RTL-first**: PASS. New UI uses logical properties; code lines in the recovery panel are set left to right
  inside right-to-left pages, as `StandalonePage::copyField()` does.
- [x] **IX. No optional dep is hard**: PASS. A persistent object cache is used when present and not required.
- [x] **X. Spec is source of truth**: PASS. The spec is clarified (four answers, 2026-10-20) and this plan traces every
  decision to an FR.
- [x] **Guard Gate + Definition of Done** acknowledged: `clean-code-guard` and `wp-guard` on every PHP and JS change,
  `test-guard` on tests, `docs-guard` on docs; i18n strings, RTL check, WCAG 2.2 AA for the dialog, grants list and
  recovery panel; DECISIONS, PROGRESS and the release notes (FR-037) updated.

## Project Structure

### Documentation (this feature)

```text
specs/112-access-and-logs/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── rest.md
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks, not created yet
```

### Source Code (repository root)

```text
plugins/corex-core/src/
├── Access/              # RequestableAbilities (new); InvalidAccessInput (new code); AccessRequestStore and
│                        # AccessUserDirectory (new methods); AccessRefusalRecorder (new interface)
├── Admin/AdminPage.php  # requestState(): no-form state
├── Database/Schema/ManagedTable.php   # optional ownerAbility
├── Http/PublicSubmission.php          # marker constant (new)
└── Mail/                # MultiRecipientResend (new, optional interface)

plugins/corex-config/src/
├── Access/              # AccessService, AccessController (preview, grants, revoke), AccessRequestRepository,
│                        # WpAccessUserDirectory, AccessAuditLog (merged entries), AccessGrantScheduler (new),
│                        # AccessGrantsPanel.js (new), AccessRequestsPanel.js (approval dialog)
├── Notifications/Producers/   # grant-ended producer (new)
├── Operations/          # MaintenanceDecision (new), MaintenanceGuard, ModeDisclosure, Maintenance REST filter (new)
├── Security/            # LoginRecovery (new), LoginRouteGuard, OperationsSecurityScreen, Security Center JS
├── Blog/                # BlogProPermissions (new), BlogProController, BlogPro screen
├── Data/                # RestrictedDataSource (new), DataAccessRestricted (new), TableDataSource,
│                        # DataSourceService, DataRestGateway
├── Submissions/         # SubmissionEmailService, inbox drawer JS
└── ConfigServiceProvider.php   # FOUNDATION_SCHEMA_VERSION 4, new bindings

plugins/corex-forms/src/Submission/Stages/EmailStage.php   # records recipients
addons/corex-{profile,newsletter,bookings,careers}/        # declare the marker on public routes (account routes are in corex-profile)
addons/corex-email/src/Delivery/EmailAttemptRepository.php  # wasSentTo() used by the re-check

tests/Unit/…, tests/Integration/…, tests/e2e/…              # per story, listed in tasks.md
```

**Structure Decision**: the existing plugin and add-on layout; no new package. Contracts other plugins see go in
corex-core, behaviour in corex-config, one stage change in corex-forms.

## Client impact

- **Perego** implements `SubmissionEmailGateway` and builds a `ManagedTable`: both keep working unchanged (the
  interface is not touched; the new argument is optional). Without `MultiRecipientResend`, a resend to several people
  is sent once per person.
- **Muva** and **Perego** take plugin changes with an update; their standalone themes are not touched. Their
  Maintenance pages keep working; their public forms answer 503 only while Maintenance is on.
- An `AccessUserDirectory` or `AccessRequestStore` implementation outside CoreX would need the new methods; none is
  known in the three client checkouts.
- **Mizzey** stays on v0.42.0 and is not affected until it updates.

## Order of work

Story 3 and story 4 first (independent, smallest); story 1 (schema 4, preview, grants, scheduler); story 2; story 6
(records its refusals through story 2's entries); story 5 and story 7 in either order (research R14).

## Complexity Tracking

No violations to justify.
