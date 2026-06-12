# Implementation Plan: Modern settings UX (032)
**Branch**: `feature/032-settings-ux` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
SettingsForm renders per field type (text/email/url/password input, `media` picker, `select`, `checkbox`). The
registry marks the logo `media`, the captcha driver `select`, booleans `checkbox`. A small script wires the
WordPress media frame to media fields (enqueued only on the settings screen; degrades without JS). The settings
header shows the configured logo (branding findable). The form rendering is Pest-tested.

## Technical Context
PHP 8.3 + a small admin JS. Deps: spec-016 BrandingService, spec-017 SettingsForm/Registry/Store/AdminDashboard.
Tests: Pest (each field type's markup). Constraints: escape per type (esc_url for media, esc_attr for value,
option validity); media stores a URL; no-JS degrade; i18n output.

## Constitution Check (v1.2.1)
- [x] III/IV — SettingsForm stays pure (returns HTML); the JS is a thin enqueue; no service tangling.
- [x] VII — values escaped per type; saving stays nonce + cap gated (AdminDashboard via AdminGuard).
- [x] V/VIII — token-only/RTL; strings i18n.
- [x] X — implements spec 032.
- [x] Guard Gate/DoD — wp-guard (escaping, enqueue, wp_enqueue_media), clean-code, test-guard; Pest field-type
  tests; docs + docs-app.

**Gate**: PASS.

## Design
- `SettingsForm::field($name,$field,$value)` switches on `type`: input | media | select | checkbox. A `media`
  field renders a `url` value input + an `<img>` preview + Select/Remove buttons (data-target = the input id). A
  `select` renders `<option>`s from `$field['options']` (value => label). A `checkbox` renders a checkbox value `1`.
- `SettingsRegistry`: `brand.logo_url` → `media`; `captcha.driver` → `select` with options.
- `plugins/corex-config/src/admin/settings.js`: on a media button click, open the media frame, set the target
  input value + preview. `AdminDashboard` enqueues it (+ `wp_enqueue_media()`) only on its screen.
- `AdminDashboard::render()`: show the configured logo (`BrandingService::logoUrl()`) in the header.

## FR → component map
| FR | Built in |
|---|---|
| FR-001 field types | `SettingsForm::field()` |
| FR-002 registry types | `SettingsRegistry` |
| FR-003 media wiring | `src/admin/settings.js` + `AdminDashboard` enqueue |
| FR-004 persistence | `SettingsStore` (media=url, select=value, checkbox=1) |
| FR-005 branding header | `AdminDashboard::render()` + `BrandingService` |
| FR-006 tests | `tests/Unit/Config/SettingsFormTest.php` |

## Project Structure
```text
plugins/corex-config/src/Settings/{SettingsForm.php, SettingsRegistry.php, AdminDashboard.php}
plugins/corex-config/src/admin/settings.js
tests/Unit/Config/SettingsFormTest.php
```

## Complexity Tracking
The field-type switch is the feature; justified. The media-frame wiring is standard WP. Visual is env-gated.
