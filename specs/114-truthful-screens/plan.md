# Implementation Plan: Screens that tell the truth

**Branch**: `claude/spec-114-truthful-screens-43137f` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/114-truthful-screens/spec.md` (nine stories; open choices decided under
the owner's 2026-10-21 mandate)

## Summary

Nine admin screens stop saying things that are not so. The Overview builds every row from the fact it names (brand
name and logo, the latest PageSpeed run, the captcha add-on and its keys, the Security Center's login settings) and
says "Could not check" when a fact cannot be read. One rule decides whether the default login is hidden, and the
Security Center, the Overview and the login guard all ask it. A PageSpeed run that does not complete is stored as a
failure with no score; failures stored by earlier releases are recognised when read. Mode changes, refusals, lockouts
and access decisions reach the activity log, and the Security events card asks for those areas only. The captcha test
button finds the secret field by its real id and appears only with saved keys. The Add-ons screen checks what WordPress
answered, locks network and must-use add-ons, and logs each toggle. The login screen loses the SSO slot and Settings
its switch. Blog Pro says it does not collect reading data yet and loses the Sharing card. A Settings save writes only
the fields whose value changed, and never a section whose add-on is off. Design: [research.md](research.md) (R1 to
R9); shapes: [data-model.md](data-model.md); interface changes: [contracts/](contracts/); checks:
[quickstart.md](quickstart.md).

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (vanilla admin scripts and `@wordpress/scripts` React screens)

**Primary Dependencies**: WordPress 7.0+ core APIs (plugins API, options, REST, activity table through `$wpdb`); CoreX
corex-core contracts; no new library

**Storage**: no schema change. One new autoloaded option `corex_security_events_since` (a Unix time). The
`corex_insights` option gains failed results in a new shape; older rows are read, never rewritten.

**Testing**: Pest unit and integration (512M), Jest for the admin scripts, Playwright for Overview, Operations &
Security, Insights, Settings, Add-ons, login and Blog Pro

**Target Platform**: WordPress 7.0+, single site and multisite, shared hosts

**Project Type**: WordPress framework (plugins, add-ons, admin screens)

**Performance Goals**: no new query on a public request. The Security events card makes one activity query of five
rows; a refusal adds an activity write only when the access log opens a new entry.

**Constraints**: no change to an interface a client implements; `ActivityRepository::query()` keeps its signature
(an `area` filter may now be a list); a stored setting (`brand.login_sso_enabled`) is left in place.

**Scale/Scope**: nine stories in corex-core (activity contract docs), corex-config (Overview, Security, Insights,
Add-ons, Branding, Settings, Blog Pro, Access, Operations) and corex-captcha.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Both passes give the same answers.

- [x] **I. Theme is a skin**: PASS. Nothing goes in a theme.
- [x] **II. Plugins boot themselves**: PASS. The security activity recorder registers in the corex-config service
  provider and works in admin, REST, CLI and cron (a mode changed from WP-CLI is recorded).
- [x] **III. Thin controllers, fat services**: PASS. Add-on toggling moves into `AddonToggleService`; the Overview's
  facts into `OverviewFacts`; the screens only render.
- [x] **IV. Everything injected**: PASS. New classes are container bindings; screens receive them.
- [x] **V. Runtime tokens**: PASS. The failed Insights card and the recovery note use existing `--corex-` variables.
- [x] **VI. Conditional assets**: PASS. No new asset; the captcha script stays on the Settings screen only.
- [x] **VII. Declarative security**: PASS. No new route. The Add-ons and Settings writes stay on `AdminGuard`.
- [x] **VIII. RTL-first**: PASS. New text and styles use logical properties; checked in the Arabic admin.
- [x] **IX. No optional dep is hard**: PASS. The captcha, media and forms add-ons are read through the activation
  inspector; corex-config runs without them.
- [x] **X. Spec is source of truth**: PASS. Each design choice below traces to an FR.
- [x] **Guard Gate + Definition of Done** acknowledged: `clean-code-guard` and `wp-guard` on PHP and JS, `test-guard`
  on tests, `docs-guard` on docs; DECISIONS #390 to #398, PROGRESS and the release notes updated.

## Project Structure

### Documentation (this feature)

```text
specs/114-truthful-screens/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── interfaces.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
plugins/corex-config/src/
├── Overview/            OverviewFacts (new), OverviewModel, OverviewRenderer
├── ControlPanel/        ControlPanelStatus, DomainStatus (removed: the Overview was their only reader)
├── Security/            OperationsSecurityScreen; LoginProtection/LoginAddressState (new), LoginRouteGuard
├── Insights/            InsightResult, InsightStore, PsiDiagnostic, InsightWidgetFacts, InsightWidgets,
│                        Providers/PerformanceProvider, Normalizers/PsiNormalizer, InsightFailure (new)
├── Activity/            WpActivityRepository (area list), SecurityActivityRecorder (new), SecurityEventsSince (new)
├── Access/              AccessAuditLog (announces a new counted entry)
├── Operations/          OperationsModeStore (announces a mode change)
├── Addons/              AddonActivator, AddonToggleService (new), AddonToggleResult (new), AddonsScreen
├── Branding/            AdminBranding, BrandingService
├── Settings/            AdminDashboard, SettingsRegistry
└── Blog/                BlogProApp.js, BlogProScreen, blogState
plugins/corex-config/assets/insights.js, insights.css
plugins/corex-core/assets/css/corex-admin-login.css
addons/corex-captcha/assets/captcha-admin.js, src/CaptchaServiceProvider.php, src/CaptchaDiagnostic.php
tests/Unit/…, tests/Integration/…, tests/e2e/…
```

**Structure Decision**: every change sits in the screen's existing module; four small new classes keep the screens thin.

## Complexity Tracking

No constitution violation to justify.
