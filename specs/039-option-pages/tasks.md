# Tasks: Easy option pages (039)

**Forward, TDD-ordered.** `OptionPage`/`OptionPageRegistry`/`FieldSections` + the generator output are the headless
core (Pest); the screen + WP-CLI command are thin boundaries. Reuses spec-032 `SettingsForm`/`SettingsStore`.
FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the reuse points: spec-032 `SettingsForm` (per-type controls), `SettingsStore`, `AdminGuard`, and the spec-003 generator engine + WP-CLI gate.

## Phase 2: Foundational — the FieldSections seam (blocking)
- [x] T002 Extract `Settings/FieldSections.php` (interface: `sections()`, `keys()`); make `SettingsRegistry` implement it and `SettingsForm` typehint it (no behaviour change; existing settings tests stay green).

## Phase 3: US1 — declare a page (P1) 🎯 MVP
- [x] T003 [US1] Write `tests/Unit/Options/OptionPageTest.php` (RED): an `OptionPage` exposes slug/title/menu/capability/parent and satisfies `FieldSections` (one section from its fields; `keys()`).
- [x] T004 [US1] Implement `Options/OptionPage.php` to pass T003.
- [x] T005 [US1] Write `tests/Unit/Options/OptionPageRegistryTest.php` (RED) + implement `Options/OptionPageRegistry.php` (register/all/find).
- [x] T006 [US1] Implement `Options/OptionPageScreen.php`: per page add the menu, render via `new SettingsForm($page)` + `SettingsStore`, save on `admin_init` (cap + per-page nonce + sanitise); wire registry + screen in `ConfigServiceProvider`.

## Phase 4: US2 — the generator (P1)
- [x] T007 [US2] Write `tests/Unit/Cli/OptionPageGeneratorTest.php` (RED): the engine renders an `OptionPage` definition from `option-page.stub` for a given name/namespace/path.
- [x] T008 [US2] Add `stubs/option-page.stub` + `Generators/OptionPageGenerator.php`; register `option-page` in the `make:*` map in `CliServiceProvider`.

## Phase 5: Polish
- [x] T009 Guard Gate: wp-guard (cap + nonce on save, sanitise per type, escape output, password write-only, prefixed menu), clean-code, test-guard; fix.
- [x] T010 [P] `composer test` green; verify a registered page renders + saves (cap+nonce) and `make:option-page` scaffolds a definition.
- [x] T011 Docs: a "custom option pages" guide (docs-app) + corex-config/CLI README; PROGRESS + DECISIONS #73; NEXT STEP.
