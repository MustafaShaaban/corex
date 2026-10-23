# Implementation Plan: Settings that save what you changed

**Branch**: `spec/119-settings-save` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/119-settings-save/spec.md` (nine stories of release R3.4; open choices
decided under the owner's 2026-10-21 mandate)

## Summary

The Settings screen becomes seven pages under Settings in the CoreX rail, each a form of its own with a save bar that
tracks unsaved changes, restores them, guards leaving, and redirects after a save. Each field is drawn from a field
state: the value shown, the stored value to restore, and where its value is locked (`.env` or the network), read from
the same layers the config engine reads. A provider section says which key is missing and what that stops, and shows
the latest remembered test; PageSpeed and Cloudflare gain "Test connection", and the captcha add-on's test reports to
the same record. A saved secret can be removed with a confirmed save, recorded in the activity log. Media, Captcha and
Guides follow their add-on; an add-on that is off links to its card. Every string is translated and a unit test fails
on one that is not. On/off settings are switches. Brand gains the login logo link. Corex Media gains "Convert existing
library", a bounded job started, followed and cancelled from the page. Design: [research.md](research.md) (R1 to
R10); shapes: [data-model.md](data-model.md); interfaces: [contracts/](contracts/); checks:
[quickstart.md](quickstart.md).

## Technical Context

**Language/Version**: PHP 8.3; vanilla JavaScript for the Settings screen (no build), as today

**Primary Dependencies**: WordPress 7.0+ core APIs (options, transients, REST, HTTP API); CoreX corex-core config
sources, activity log and bounded jobs; no new library

**Storage**: no schema change. New options: `corex_provider_tests` (latest test per provider, no key values, not
autoloaded) and `corex_media_conversion` (the latest library run's job id and failures, not autoloaded). One
per-user transient holds a save's result across the redirect for two minutes.

**Testing**: Pest unit and integration (512M), Jest for `settings.js` and the conversion panel script, Playwright for
the Settings pages (save bar, switches, removal, conversion, and a pseudo-translation through a header-gated fixture);
locks are proven in Pest integration with a `.env` of the test's own

**Target Platform**: WordPress 7.0+, single site and multisite, shared hosts without a command line

**Project Type**: WordPress framework (plugins, add-ons, admin screens)

**Performance Goals**: nothing on a public request. A Settings page renders one page's fields; a provider test makes
one outbound request with a 10 second timeout; a conversion step converts at most the runner's batch.

**Constraints**: option names and stored formats unchanged (a switch saves "1"/"0"); `SettingsForm::render()` keeps
drawing option pages; `corex_tab` links keep working; `AdminPage::open()` gains an optional argument only.

**Scale/Scope**: nine stories across corex-core (setting locks, rail sub-navigation, the shared switch), corex-config
(Settings pages, saver, provider states and tests, Add-ons card line and anchor), corex-captcha (test result reported)
and corex-media (library conversion).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Both passes give the same answers.

- [x] **I. Theme is a skin**: PASS. Nothing goes in a theme.
- [x] **II. Plugins boot themselves**: PASS. Every new service registers in its plugin's provider; the conversion job
  runs from WP-Cron with no page open.
- [x] **III. Thin controllers, fat services**: PASS. The screen verifies and redirects; `SettingsSaver` saves;
  `ProviderTestService` and `MediaConversionService` hold the rules; option stores are their only data access.
- [x] **IV. Everything injected**: PASS. New classes are container bindings; nothing is constructed in a method.
- [x] **V. Runtime tokens**: PASS. The save bar, sub-navigation, switch and panels use `--corex-admin-*` variables.
- [x] **VI. Conditional assets**: PASS. The Settings script loads on Settings only; the conversion script on the Media
  page only.
- [x] **VII. Declarative security**: PASS. The Settings write stays on `AdminGuard`; the two new REST routes check
  the capability and the REST nonce in their permission callback, as the existing captcha test route does.
- [x] **VIII. RTL-first**: PASS. Logical properties only; the switch mirrors through `inset-inline-start`; checked in a
  right-to-left admin.
- [x] **IX. No optional dep is hard**: PASS. corex-config reaches Media and Captcha through filters and actions;
  corex-media reaches the jobs runner through its action hook.
- [x] **X. Spec is source of truth**: PASS. Each design choice traces to an FR.
- [x] **Guard Gate + Definition of Done** acknowledged: `clean-code-guard` and `wp-guard` on PHP and JS, `test-guard`
  on tests, `docs-guard` on docs; DECISIONS #440 to #448, PROGRESS and the release notes updated.

## Project Structure

### Documentation (this feature)

```text
specs/119-settings-save/
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
plugins/corex-core/src/Support/Config/SettingLocks.php, SettingLock.php     (new: where a value is locked)
plugins/corex-core/src/Foundation/CoreServiceProvider.php                    (lock layers bound once)
plugins/corex-core/src/Admin/AdminPage.php                                   (rail sub-navigation)
plugins/corex-core/assets/css/corex-admin-shell.css                          (sub-navigation, save bar, switch)
plugins/corex-config/src/Settings/                                           (pages, field states, saver, flash,
    SettingsPages, FieldState, SettingsFieldValues, SettingsSaver,             provider states and tests, renderer)
    SettingsSaveResult, SettingsSaveFlash, SettingsPageRenderer,
    SettingsControls, SettingsSectionStates, ProviderSetup,
    ProviderTestLog, ProviderTestService, ProviderTestController,
    Providers/PageSpeedKeyProbe, Providers/CloudflareTokenProbe
plugins/corex-config/src/Settings/AdminDashboard.php, SettingsForm.php, SettingsRegistry.php (changed)
plugins/corex-config/src/Addons/AddonsScreen.php                             (card anchor; captcha setup line)
plugins/corex-config/assets/settings.js, control-panel.css                    (save bar, remove, tests)
addons/corex-captcha/src/CaptchaTestController.php, CaptchaDiagnostic.php    (report the result)
addons/corex-media/src/Conversion/                                            (new: library conversion)
addons/corex-media/assets/media-conversion.js
tests/Unit/Config/, tests/Integration/Config/, tests/Integration/Media/, tests/e2e/settings-pages.spec.js
tests/e2e/fixtures/corex-e2e-pseudo-locale.php                               (header-gated pseudo-translation)
```

**Structure Decision**: the Settings screen keeps its class (`AdminDashboard`) for the menu and the Overview; the
pages, the saving and the field states move into classes of their own, so the screen only verifies, redirects and
composes. Option pages keep `SettingsForm::render()`, drawn with the same controls.

## Complexity Tracking

No constitution violation to justify.
