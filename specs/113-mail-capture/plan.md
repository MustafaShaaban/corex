# Implementation Plan: Mail stays where it should

**Branch**: `spec/113-mail-capture` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/113-mail-capture/spec.md` (five stories; the owner's decisions SAFE-D1,
SAFE-D2, SAFE-D3 and EML-D6)

## Summary

One resolver in corex-core answers "is this a development, staging or production site", from a locking `APP_ENV`, the
operations mode, then WordPress's environment type, and every screen and Email Studio read it. One gate in corex-core,
asked by CoreX's mail service and by WordPress's `pre_wp_mail` filter, captures every email on a development or staging
site, holds every email of a flow test in any environment, refuses mail that has nowhere to be kept, and leaves
production untouched. Email Studio shows on every tab what happens to mail, offers a "Capture only CoreX mail" switch,
lists the newest mail first and deletes captured mail after its 30 days. The design is in [research.md](research.md)
(R1 to R12), the stored shapes in [data-model.md](data-model.md), the routes in [contracts/rest.md](contracts/rest.md),
the checks in [quickstart.md](quickstart.md).

## Technical Context

**Language/Version**: PHP 8.3; JavaScript built with `@wordpress/scripts`

**Primary Dependencies**: WordPress 7.0+ core APIs (`pre_wp_mail`, WP-Cron, REST, options); CoreX corex-core
contracts; no new library

**Storage**: no schema change. Option `corex_mail_capture_scope` (new). The `captured_email` record payload gains
`path` and `state`. Activity events of kind `mail.refused`.

**Testing**: Pest unit and integration (512M), Jest, Playwright for Email Studio, Operations & Security and the flow
Test tab; a `pre_wp_mail` spy proves nothing reaches the transport; tests never deliver mail

**Target Platform**: WordPress 7.0+ on shared and managed hosts, single site and multisite, with or without CoreX Mail
and corex-config active

**Project Type**: WordPress framework (plugins, add-ons, admin React screens)

**Performance Goals**: the log loads in under a second with 5,000 records and makes no query per row (FR-025); the
gate adds no database query to a production send (it reads loaded options only)

**Constraints**: production delivery unchanged on every path (SAFE-D2); no client-implemented interface changes
(`Mailer`, `MailRequest`, `SubmissionEmailGateway` untouched; `EmailStudioStore` gains a method and has no client
implementation); the retention clean-up runs in bounded batches on shared hosts

**Scale/Scope**: five stories across corex-core, corex-config, corex-forms and corex-email

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Both passes give the same answers.

- [x] **I. Theme is a skin**: PASS. Nothing goes in a theme.
- [x] **II. Plugins boot themselves**: PASS. The gate's `pre_wp_mail` filter registers in corex-core's provider and
  works in REST, admin, CLI and cron; corex-email binds the capture store and schedules its clean-up.
- [x] **III. Thin controllers, fat services**: PASS. Decisions live in `SiteEnvironment`, `MailGate` and `MailOutcome`;
  the new route only validates and saves.
- [x] **IV. Everything injected**: PASS. All new services are container bindings; the two scopes (`MailPath`,
  `MailHold`) are request-lifetime singletons, not statics reached from methods.
- [x] **V. Runtime tokens**: PASS. The banner and the switch use existing `--corex-` variables.
- [x] **VI. Conditional assets**: PASS. The banner is part of Email Studio's bundle.
- [x] **VII. Declarative security**: PASS. The new route declares its permission callback (Email Studio's manage
  check and the REST nonce), as the other Email Studio routes do.
- [x] **VIII. RTL-first**: PASS. Logical properties; path names and dates checked in Arabic.
- [x] **IX. No optional dep is hard**: PASS. Without CoreX Mail the gate refuses and records (FR-009); without
  corex-config the resolver reads the mode option directly.
- [x] **X. Spec is source of truth**: PASS. Every decision traces to an FR; three gaps the stories did not settle are
  decided in research (R1, R5, R6) under the owner's 2026-10-21 mandate.
- [x] **Guard Gate + Definition of Done**: `clean-code-guard` and `wp-guard` on PHP and JS, `test-guard` on tests,
  `docs-guard` on docs; i18n, RTL, WCAG 2.2 AA for the banner and switch; DECISIONS #381 onward, PROGRESS and the
  release notes (FR-028).

## Project Structure

### Documentation (this feature)

```text
specs/113-mail-capture/
├── spec.md  plan.md  research.md  data-model.md  quickstart.md  tasks.md
├── contracts/rest.md
└── checklists/requirements.md
```

### Source Code (repository root)

```text
plugins/corex-core/src/
├── Environment/         # SiteEnvironment (new), SiteEnvironmentAnswer (new value)
├── Mail/Capture/        # MailGate, MailDecision, MailPath, MailHold, MailCaptureStore (contract),
│                        # CapturedMessage, WpMailInterceptor (pre_wp_mail), WordPressMailNames (new)
└── Foundation/CoreServiceProvider.php    # bindings, the pre_wp_mail filter

plugins/corex-config/src/
├── Operations/          # OperationsModeStore::current()/set() read the lock; ModeChangeService refusal
├── Security/OperationsSecurityScreen.php # locked mode card; conflict notice on mapped values
├── Insights/InsightWidgetFacts.php       # environment from SiteEnvironment
└── Settings/AdminDashboard.php           # advanced.environment from SiteEnvironment

plugins/corex-forms/src/Submission/       # FlowTestService runs inside MailHold; the result lists held emails
plugins/corex-config/src/Forms/tabs/TestTab.js   # held-not-sent copy and the held list

addons/corex-email/src/
├── MailService.php      # asks the gate; status captured
├── Capture/             # CapturedEmailRepository (path, state, latest), CaptureStoreAdapter (new),
│                        # CaptureRetention (new daily clean-up)
├── Studio/              # store latest(); EmailStudioService hold; controller outcome + capture-scope route;
│                        # environment() copies replaced
├── Routing/EmailRouteDispatcher.php
└── Delivery/MailOutcome.php (new)

plugins/corex-config/src/Email/           # StudioPanel banner (new MailOutcomeBanner.js), OverviewPanel switch,
                                          # LogsPanel path and state columns

tests/Unit/…, tests/Integration/…, tests/e2e/…   # listed in tasks.md
```

**Structure Decision**: the existing layout. Contracts and the gate in corex-core (the one plugin always active);
storage and screens where they already are.

## Client impact

- **Muva and Perego** (production, no provider, live delivery off, no `APP_ENV`, no `WP_ENVIRONMENT_TYPE` known): the
  gate answers deliver before anything else, so every email they send today is sent. Their local copies capture when the
  mode is Development or Staging or WordPress's environment is `local`/`development`/`staging`; captured mail is in the
  local Email Studio log. The owner tells both sessions before they update.
- A site whose code implements `Corex\Mail\Mailer` itself, or binds another `MailDriver`, bypasses `MailService`; its
  mail still meets `pre_wp_mail` when it calls `wp_mail()`.
- **Mizzey** stays on v0.42.0.

## Order of work

Story 1 (resolver; everything reads it), story 5 (store and log; the clean-up builds on it), story 2 (gate and
capture), story 3 (hold), story 4 (outcome banner and switch, which describe stories 1 to 3).
