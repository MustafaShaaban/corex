# Implementation Plan: Easy option pages (039)
**Branch**: `feature/039-option-pages` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
A declarative `OptionPage` (title, menu, capability, parent, fields) registered in an `OptionPageRegistry` becomes
a real admin settings screen — rendered by the **existing** spec-032 `SettingsForm` controls and persisted by the
`SettingsStore`, cap + nonce gated via `AdminGuard`. The reuse is enabled by extracting a tiny `FieldSections`
seam that both `SettingsRegistry` and `OptionPage` satisfy, so no form rendering is duplicated. A
`wp corex make:option-page <Name>` generator scaffolds a page definition. Pure pieces are unit-tested; the screen
+ WP-CLI command are thin boundaries.

## Technical Context
PHP 8.3. Reuse: `SettingsForm` (per-type controls), `SettingsStore` (option persistence), `AdminGuard` (cap +
nonce). Generator: the spec-003 `GeneratorEngine` + a new stub, WP-CLI-gated. Tests: Pest. Constraints: Principle
VII (cap + nonce on save, escape output, password write-only), V/VIII (token-only, a11y/RTL — inherited from
SettingsForm), IX (WP-CLI optional).

## Constitution Check (v1.2.1)
- [x] III/IV — `OptionPage`/`OptionPageRegistry`/`FieldSections` pure; the screen + the WP-CLI command are thin.
- [x] V/VIII — rendering reuses the token-only, accessible SettingsForm controls (no new markup style).
- [x] VII — the save verifies the page capability + a per-page nonce, sanitises each field, escapes output.
- [x] IX — `make:option-page` behind the existing `class_exists('WP_CLI')` gate; the engine is headless.
- [x] X — implements spec 039.
- [x] Guard Gate/DoD — wp-guard (nonce/cap/sanitize/escape), clean-code, test-guard; Pest; docs + docs-app.

## Design
- `plugins/corex-config/src/Settings/FieldSections.php` (interface: `sections()`, `keys()`); `SettingsRegistry`
  implements it; `SettingsForm` typehints it (behaviour unchanged).
- `plugins/corex-config/src/Options/OptionPage.php` (value + `FieldSections`: one section from `fields`; accessors
  `slug/title/menuLabel/capability/parent`).
- `plugins/corex-config/src/Options/OptionPageRegistry.php` (register/all/find).
- `plugins/corex-config/src/Options/OptionPageScreen.php` (per page: `add_menu_page`/`add_submenu_page`, render via
  a `SettingsForm($page)` + `SettingsStore`, save on `admin_init` verifying cap + a per-page nonce).
- Wire: `ConfigServiceProvider` binds the registry + screen; `boot()` registers the screen.
- `packages/cli/stubs/option-page.stub` + `packages/cli/src/Generators/OptionPageGenerator.php`; add `option-page`
  to the `make:*` map in `CliServiceProvider`.

## FR → component map
| FR | Built in |
|---|---|
| FR-001 page value | `Options/OptionPage.php` |
| FR-002 shared controls | `Settings/FieldSections.php` + `SettingsForm` typehint |
| FR-003 registry + screen + save | `Options/{OptionPageRegistry,OptionPageScreen}.php` |
| FR-004 persistence/write-only | `SettingsStore` (reused) + SettingsForm password control |
| FR-005 generator | `stubs/option-page.stub` + `Generators/OptionPageGenerator.php` + CLI map |
| FR-006 tested | `tests/Unit/Options/{OptionPageTest,OptionPageRegistryTest}.php` + a generator test |

## Project Structure
```text
plugins/corex-config/src/Settings/FieldSections.php (+ SettingsRegistry implements, SettingsForm typehint)
plugins/corex-config/src/Options/{OptionPage,OptionPageRegistry,OptionPageScreen}.php
packages/cli/stubs/option-page.stub
packages/cli/src/Generators/OptionPageGenerator.php (+ CliServiceProvider make map)
tests/Unit/Options/{OptionPageTest,OptionPageRegistryTest}.php · tests/Unit/Cli/OptionPageGeneratorTest.php
docs/en + docs-app (a guide: custom option pages)
```

## Complexity Tracking
The `FieldSections` extraction is a no-behaviour-change interface so the page reuses the settings form + store +
controls; the only new code is the page value/registry + a thin per-page screen + a stub generator. Live admin
render/save is env-gated.
