# Tasks: The Help tab CoreX never asked for

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md)

## Phase 1 — The screen predicate

- [ ] **T001** `CorexScreens` — the regex and `supports(string $hook): bool`, moved out of
      `CorexAdminAssets` with its docblock. (FR-004)
- [ ] **T002** `CorexAdminAssets` takes `CorexScreens` and delegates `supports()`.
- [ ] **T003** `NotificationToolbar` depends on `CorexScreens` instead of `CorexAdminAssets`.
- [ ] **T004** `ConfigServiceProvider` binds `CorexScreens` and injects it into both.
- [ ] **T005** Pest: `CorexScreensTest` — toplevel, submenu (`corex_page_*`,
      `corex-framework_page_*`), option page, and rejection of foreign hooks.

## Phase 2 — Remove the help

- [ ] **T006** `ScreenHelp` — `admin_head` at `PHP_INT_MAX`; on a CoreX screen call
      `remove_help_tabs()` and `set_help_sidebar('')`. (FR-001, FR-002, FR-003)
- [ ] **T007** Register it in `ConfigServiceProvider::boot()` beside `CorexAdminAssets`. (FR-005)
- [ ] **T008** Pest: `ScreenHelpTest` — both calls on a CoreX screen, neither on `edit.php`, and no
      output or enqueue of any kind. (FR-010)
- [ ] **T009** Delete `addons/corex-guides/src/ContextualHelp.php` and its registration in
      `GuidesServiceProvider::boot()`.
- [ ] **T010** Remove `GuideRegistry::forScreen()` and retarget the `registerDeferred()` docblock
      paragraph that describes reading the registry on `current_screen`. (FR-009)
- [ ] **T011** Retarget the `Guide::$screen` / `onScreen()` docblocks at the Guides-screen deep link.
- [ ] **T012** Repository audit — `add_help_tab`, `remove_help_tabs`, `set_help_sidebar`,
      `contextual_help`, `contextual-help`, `WP_Screen`, "Help tab" — source, tests, specs, docs,
      CLI stubs, release notes.

## Phase 3 — Browser coverage

- [ ] **T013** Lift `ROUTES` out of `admin-command-center.spec.js` into `helpers.js` as
      `COREX_ROUTES`; consume it from both files.
- [ ] **T014** `tests/e2e/admin-help-tab.spec.js` — no help link, panel or wrapper on any CoreX
      route; shell top edge flush under the admin bar. (FR-001, FR-006)
- [ ] **T015** Same file — the appearance / direction / width / zoom matrix. (FR-011)
- [ ] **T016** Same file — `edit.php`, `options-general.php`, `plugins.php` keep their Help tab.
      (FR-010)
- [ ] **T017** Same file — the Guides screen still renders, searches and links out. (FR-007)
- [ ] **T018** Same file — a client-registered guide appears, via an mu-plugin fixture seeded in
      `.github/workflows/ci.yml`. (FR-008)
- [ ] **T019** Rewrite `guides.spec.js`'s "through the Help tab" test against
      `.corex-guides__guide-screen`.

## Phase 4 — The two layout defects

- [ ] **T020** Diagnose and fix the Forms & Flows disclosure interception. (FR-013)
- [ ] **T021** Diagnose and fix the Blog Pro workspace overflow at 1024px. (FR-012)
- [ ] **T022** Audit every browser spec for assertions that reached into the removed Help panel.

## Phase 5 — Close the loop

- [ ] **T023** Mark spec 084 FR-013 / T022 superseded by this spec; do not delete them.
- [ ] **T024** Update `docs-app/src/content/docs/guides/user-guides.md`, `packages/cli/stubs/guide.stub`
      and `GuideGenerator` where they describe the Help tab.
- [ ] **T025** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`.
- [ ] **T026** `PROGRESS.md` + `DECISIONS.md`.
