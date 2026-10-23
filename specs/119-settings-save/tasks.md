# Tasks: Settings that save what you changed

**Input**: [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md),
[contracts/interfaces.md](contracts/interfaces.md). Tests are written with each story (constitution: tests mandatory).
`[P]` = can run in parallel with the other `[P]` tasks of the same phase (different files).

## Phase 1: Setup

- [ ] T001 Scratch WordPress for the worktree on port 8919, database `corex_w119`; CoreX plugins active
- [ ] T002 Re-read `DECISIONS.md` on origin/main and open PRs; claim #440 to #449

## Phase 2: Foundation (blocks every story)

- [ ] T003 `SettingLock`, `SettingLocks` in `plugins/corex-core/src/Support/Config/`; bind the `.env`, network-lock and
  options sources once in `CoreServiceProvider` (as spec 113 does) and `SettingLocks` on them
- [ ] T004 `SettingsPages` (seven pages, `corex_tab` alias), `FieldState`, `SettingsFieldValues` (shown, saved, lock,
  Media defaults, Advanced diagnostics), `SettingsSectionStates` (add-on state for Media, Captcha, Guides; captcha
  needs setup from the value in force) in `plugins/corex-config/src/Settings/`
- [ ] T005 `AdminPage::open()` sub-navigation argument; shell CSS for the sub-navigation (rail and narrow row)

## Phase 3: User Story 1 — Pages and the save bar (US-SET-01, P1)

- [ ] T006 [US1] `SettingsSaver` + `SettingsSaveResult` (page only, editable only, changed only, refusals) and
  `SettingsSaveFlash` (per-user transient)
- [ ] T007 [US1] `SettingsControls` (controls shared by option pages and Settings) and `SettingsPageRenderer` (one page,
  section headings, notices, save bar, toasts); `SettingsForm::render()` kept for option pages
- [ ] T008 [US1] `AdminDashboard`: page from the address, sub-navigation, "not available" state, verify → save →
  flash → redirect
- [ ] T009 [US1] `settings.js`: save bar state, Discard, leave guard, toast focus; tabs code removed; save bar CSS in
  `control-panel.css`
- [ ] T010 [US1] Tests: unit `SettingsPagesTest`, `SettingsPageRendererTest`; integration
  `SettingsSaveChangedOnlyTest` (moved onto the saver), `SettingsPagesSaveTest` (page only, redirect result, refused
  value shown typed); Jest `settings-savebar.test.js`; update `SettingsTabsTest` and the e2e specs that used tabs

## Phase 4: User Story 2 — Locked values (US-SET-02, P1)

- [ ] T011 [US2] Locked controls (read-only with the value in force and where it is set; a secret's line); saver skips
  locked keys
- [ ] T012 [US2] Tests: unit `SettingLocksTest`; integration `SettingsLockedValuesTest` with a `.env` of its own (shown,
  not written, captcha keys count, removed key makes it editable)

## Phase 5: User Story 6 — Translation (US-SET-08, P1)

- [ ] T013 [US6] Wrap every `SettingsRegistry` and page string in `__()`; brand names literal
- [ ] T014 [US6] Unit `SettingsTranslationTest` (fails on an unmarked string); e2e fixture `corex-e2e-pseudo-locale.php`
  and a browser check of every page in right-to-left

## Phase 6: User Story 7 — Switches (US-SET-09, P3)

- [ ] T015 [US7] Move `.corex-toggle` to the shell stylesheet; switch control in `SettingsControls`; On/Off by CSS
- [ ] T016 [US7] Tests: unit (role, value, read-only when off); integration stored "1"; e2e Space toggles

## Phase 7: User Story 5 — Add-on sections (US-SET-07, P2)

- [ ] T017 [US5] Off notice with "Open Add-ons", read-only fields, no save bar; not installed → not in the navigation and
  "not available"; card ids on the Add-ons screen
- [ ] T018 [US5] Tests: integration (Guides off read-only and not written; captcha absent not in navigation)

## Phase 8: User Story 8 — Login logo link (US-SET-13, P3)

- [ ] T019 [US8] `brand.login_url` field (url, help says blank keeps the home page); tests: refused, saved, used by
  `login_headerurl`

## Phase 9: User Story 3 — Provider states and tests (US-SET-03, P2)

- [ ] T020 [US3] `ProviderSetup` (captcha keys missing and effect; Insights lines), `ProviderTestLog` (fingerprint),
  `PageSpeedKeyProbe`, `CloudflareTokenProbe`, `ProviderTestService`, `ProviderTestController`
- [ ] T021 [US3] Captcha add-on reports `corex_provider_tested`; Settings records it; status lines and "Test connection"
  buttons on the page (`settings.js`)
- [ ] T022 [US3] Tests: unit probes classification and `ProviderSetup`; integration `ProviderTestLogTest` (stale after a
  change, no key stored), route permission and no key in the answer; Jest for the test buttons

## Phase 10: User Story 9 — Secret removal (US-SET-15, P2)

- [ ] T023 [US9] Remove control (checkbox, enhanced into a confirmed button); saver deletes and records
  `settings.secret_removed`; Add-ons captcha card setup line from `ProviderSetup`
- [ ] T024 [US9] Tests: integration (removed, kept when empty, locked not removable, not removed without the
  capability, activity without the value); Jest for the confirm flow

## Phase 11: User Story 4 — Library conversion (US-SET-06, P2)

- [ ] T025 [US4] `MediaRegenerationSource` reports failures; `MediaConversionRuns`, `MediaConversionService`,
  `MediaConversionController`, the panel through `corex_settings_section_extra`, `media-conversion.js`; activity records
- [ ] T026 [US4] Tests: unit job failures and service rules; integration start/advance/cancel/one-at-a-time with real
  uploads; Jest for the panel

## Phase 12: Polish

- [ ] T027 Browser measurement: gaps, 375px overflow, dark, right-to-left on every page; Playwright `settings-pages.spec.js`
- [ ] T028 Docs: Settings guide and `plugins/corex-config/README.md`; `CHANGELOG.md` Unreleased with Client impact
  (Muva, Perego, Mizzey); `PROGRESS.md` In flight; `DECISIONS.md` #440 to #448
- [ ] T029 Guards: `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`; token inventory regenerated
- [ ] T030 Merge origin/main, full unit + integration + Jest, push, PR, CI green
