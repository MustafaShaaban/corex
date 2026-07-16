---

description: "Task list for Spec 069 — Admin Correctness & Login Hiding Parity"
---

# Tasks: Admin Correctness & Login Hiding Parity

**Input**: Design documents from `/specs/069-admin-correctness-and-login-parity/`

**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md). No `research.md`/`data-model.md`/`contracts/` — see plan.md §Project Structure for why.

**Tests**: REQUIRED. The constitution's Definition of Done mandates unit + E2E tests (Pest / Jest / Playwright) that pass, plus the Guard Gate, i18n-readiness, RTL verification, and WCAG 2.2 AA for UI. Every implementation task owes corresponding test task(s).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to

---

## Phase 1: Setup

- [ ] **T001** Verify the Environment Gate: `wp theme list` shows `corex`; `wp plugin list` shows `corex-core`, `corex-blocks`, `corex-config` active; `http://corex.local` boots with no PHP fatals. **Blocks everything** — constitution mandate.
- [ ] **T002** Confirm the login recovery path works *before* touching login code: run `wp corex security reset-login` and verify `COREX_LOGIN_UNGUARD` is reachable. **Blocks T010-T024.** Note: login protection is currently disabled on corex.local and the admin password was reset to `password` during 068 testing.
- [ ] **T003** Capture a baseline: unit 1,260, integration 110, Jest 263, Playwright 35/35. Any later red must be attributable to this branch.
- [ ] **T004** [P] Confirm guard skills are installed (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`); install any missing via `npx skills add amElnagdy/guard-skills --skill <name> --agent claude-code`. A missing guard is never an excuse to skip the gate.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The container must not be able to fatal before any login behaviour is changed on a live install.

- [ ] **T005** [US2] Write a failing Pest unit test: `LoginProtectionSettings` construction with a 1–2 char slug currently throws, and `ConfigServiceProvider` L304-308 constructs it on every `make()` ⇒ site-wide fatal. Test asserts the container binding never throws on stored data.
- [ ] **T006** [US2] Make the binding fault-tolerant: an unusable stored value falls back to a safe default rather than throwing (FR-011, Principle IV). `plugins/corex-config/src/ConfigServiceProvider.php`, `.../LoginProtection/LoginProtectionSettings.php`.
- [ ] **T007** [US2] Write a failing Pest unit test for the shared slug sanitiser: the value `save()` stores and the value `current()` reads back must be identical for every input (FR-010). Today `save()` L30 has a `?: 'corex-login'` fallback and `current()` L52 does not.
- [ ] **T008** [US2] Extract one sanitiser used by both `save()` and `current()` in `.../LoginProtection/LoginProtectionSettingsStore.php`.
- [ ] **T009** [US2] Verify T005-T008 green; run `clean-code-guard` + `wp-guard`.

**Checkpoint**: the stored-settings path can no longer fatal or read back a different value than it stored.

---

## Phase 3: US1 — The hidden login is genuinely hidden (P1) 🎯 MVP

**Goal**: An anonymous probe of the default endpoints is indistinguishable from a probe of an absent page. **Independent test**: probe the defaults, compare status+body against a control URL, then complete a sign-in round trip through the custom address.

### Tests first

- [ ] **T010** [US1] Failing Pest unit tests for the single collapsed hiding predicate — replacing the two disagreeing rulesets (`decision()` L164-184 vs `hidesDefaultEndpoint()` L83-106). Cover: anonymous default login → hide; anonymous admin → hide; **logged-in default login → serve** (FR-006, the current code wrongly blocks); AJAX/`admin-ajax.php`/`admin-post.php` → never hide (FR-007); recovery constant → never hide; custom slug → serve. `tests/Unit/Security/LoginRouteGuardTest.php`.
- [ ] **T011** [P] [US1] Failing Pest unit tests for URL rewriting: query args preserved, `is_ssl()` scheme honoured, `admin_url`/`network_admin_url` covered. Today `rewriteLoginUrl()` L138-141 is a bare `str_replace` and is untested.
- [ ] **T012** [P] [US1] Failing Pest integration test: the hidden response is a real theme 404 (`$wp_query->is_404()` true, status 404), **not** `wp_die` output. `tests/Integration/Security/`.

### Implementation

- [ ] **T013** [US1] Collapse the two rulesets into one pure, tested predicate. Delete `decision()`, `maybeBlockDefaultEndpoint()`, the `login_init` registration (L25), and the dead `normalizedPath()` no-op ternary (L203). `.../LoginProtection/LoginRouteGuard.php`.
- [ ] **T014** [US1] Move registration to `plugins_loaded` prio 1; set `$pagenow` **and** rewrite `$_SERVER['REQUEST_URI']` before any component reads it (FR-002). Keep the `wp-login.php` global declarations that make the include warning-free.
- [ ] **T015** [US1] Replace `deny()`'s `wp_die` (L108-117) with `$wp_query->set_404()` + `status_header(404)` + the theme's 404 template (FR-001). **This is the headline defect** — the bare `wp_die` page is what fingerprints the plugin.
- [ ] **T016** [US1] Rewrite `rewriteLoginUrl()` properly: parse + `add_query_arg`, honour `is_ssl()`; add `admin_url`/`network_admin_url` filters (FR-004).
- [ ] **T017** [US1] Filter `wp_redirect` so *any* component's redirect to the default login lands on the custom address instead (FR-004).
- [ ] **T018** [US1] `remove_action('template_redirect', 'wp_redirect_admin_locations', 1000)` so `/login`, `/dashboard`, `/admin` stop leaking (FR-005).
- [ ] **T019** [US1] Serve the default login to authenticated users so interim login and `action=postpass` keep working (FR-006).
- [ ] **T020** [P] [US1] Multisite: filter `site_option_welcome_email`; handle `wp-signup.php`/`wp-activate.php` (FR-008).
- [ ] **T021** [US1] Playwright E2E for the real hide-and-serve behaviour — **none exists today**; `tests/e2e/security-access.spec.js` L10-16 only asserts the screen renders. Assert the default endpoints' response is byte-identical to a control 404 (SC-001), and that sign-in/sign-out/password-reset round-trip through the custom address (SC-002).
- [ ] **T022** [US1] Verify on `corex.local`: probe defaults + shortcuts vs a control URL; custom slug serves warning-free; logged-in `action=postpass` works. Guards clean.

**Checkpoint**: US1 independently shippable — login hiding is genuinely indistinguishable from an absent page.

---

## Phase 4: US2 — The owner cannot be locked out (P1)

**Goal**: No interface path reaches "default hidden, no working alternative". **Independent test**: attempt empty/short/reserved/colliding addresses; site stays reachable throughout.

- [ ] **T023** [US2] Failing Pest tests for slug rejection: empty-after-cleanup, too-short, reserved, and colliding-with-existing-content each refused with a reason (FR-009).
- [ ] **T024** [US2] Add a real `args` schema (`sanitize_callback`/`validate_callback`) to `SecuritySettingsController::register()` L31-47 — it has **none** today, a standing Principle VII violation — and reject bad slugs in `save()` L59-88 with a named reason.
- [ ] **T025** [P] [US2] `SecurityResetLoginCommand::restore()` L29-42 resets `enabled` and `block_default_endpoints` but **not the slug** — reset it too, or recovery can leave a broken slug in place (FR-013). `packages/cli/src/Commands/SecurityResetLoginCommand.php` + unit test.
- [ ] **T026** [P] [US2] Fix JS/PHP default drift in `securityCenterState.js`: `enabled` true (L41) vs PHP false; `windowSeconds` 900 (L45) vs PHP 300 (FR-014). Jest test.
- [ ] **T027** [US2] `SecurityCenter.js` `LoginPolicy()` L105-180: display the resulting login URL prominently after save; warn before the default is hidden (FR-012). Jest test; i18n; WCAG 2.2 AA.
- [ ] **T028** [US2] Wire the Lockouts panel to the real store — `OperationsSecurityScreen.php` L287 hardcodes `'lockouts' => []` though the table holds real rows (FR-015). Integration test.
- [ ] **T029** [US2] `SecurityCenter.js` `Recovery()` L210-232 — the button only flips a label. Make it perform a real action or remove it; no inert control survives (FR-016). Jest test.
- [ ] **T030** [US2] Verify on `corex.local`: every bad slug refused; site never unreachable; recovery restores access. Guards clean.

**Checkpoint**: US1+US2 shippable together — hiding works and cannot lock the owner out.

---

## Phase 5: US3 — Records live in one place (P2)

- [ ] **T031** [US3] Failing tests for the permission resolution (plan.md §Resolved risk): a `MANAGE_DATA`-only user reaches records; a `MANAGE_DATA_MODELS`-only user reaches models; **neither loses access** (FR-019). Note `tests/Unit/DataModels/DataAdminClientsContractTest.php` L40-41 asserts the current ability wiring and will need updating in step.
- [ ] **T032** [US3] Gate the Data Models screen on `MANAGE_DATA || MANAGE_DATA_MODELS`, following the existing either-ability precedent (`DataRestGateway.php` L58-59, `DataManagementController.php` L393-394). `DataModelsScreen.php` L42, L51.
- [ ] **T033** [US3] Gate each tab on the ability it needs: Records → `MANAGE_DATA`; Models/Import/Export/Migrations → `MANAGE_DATA_MODELS`. Render only permitted tabs; default to the first permitted. **This also fixes a pre-existing defect**: a `MANAGE_DATA_MODELS`-only user currently sees an empty explorer because sources read via `MANAGE_DATA` (`DataSourceService.php` L124).
- [ ] **T034** [US3] Add `?tab=` query-arg sync to `DataModelsApp.js` (tabs are `useState` only, L24) so views deep-link (FR-018). The redirect depends on this. Jest test.
- [ ] **T035** [US3] Remove `DataAdminScreen`'s submenu registration; redirect `page=corex-data` → `page=corex-data-models&tab=records` (FR-017). Update `AdminPage.php` L325-328's slug→ability map. Keep `DataExplorer` + `data.css`.
- [ ] **T036** [US3] Verify: old address lands on records; deep links open correctly; no user lost access. Guards clean.

---

## Phase 6: US4 — Filter by form name (P2)

- [ ] **T037** [US4] Failing tests for the flow list localization via lazy container resolution in `try/catch` (Principle IX; pattern at `InsightWidgetFacts.php` L143-158) — including **forms entirely absent → screens still work** (FR-023, SC-008).
- [ ] **T038** [US4] Localize the flow list (`FlowService::all()` → id/name/slug) into both screens' configs. `SubmissionsInboxScreen.php`, `DataModelsScreen.php`.
- [ ] **T039** [US4] Submissions: replace the numeric `Flow ID` input (`index.js` L79) with a name select posting the **flow id** (meta `corex_flow_id`); keep free-text search (L78). Server path unchanged. Jest test.
- [ ] **T040** [US4] Records: give `QueryBar.js`'s `form` field a select posting the **form slug** (meta `corex_form_slug`) — **a different key from Submissions; do not conflate** (FR-022). Keep free text alongside. Jest test.
- [ ] **T041** [P] [US4] Empty-list state communicated plainly (FR-023). Prefer the `.corex-select` widget (`corex-admin-shell.css` L485-561). i18n; RTL; WCAG 2.2 AA.
- [ ] **T042** [US4] Verify against seeded data on `corex.local`: both filters narrow correctly; confirm each keys on the right meta. Guards clean.

---

## Phase 7: US5 — Models accordion (P3)

- [ ] **T043** [US5] Failing Jest test for accordion semantics: first expanded, rest collapsed, state announced.
- [ ] **T044** [US5] Convert `ModelsPanel.js` L31 cards to an accordion — header as trigger; fields table + chips collapse (FR-024). Styles in `assets/data-models.css` using logical properties.
- [ ] **T045** [US5] Verify keyboard-only operation and screen-reader announcement; RTL. Guards clean.

---

## Phase 8: US6 — The admin reads cleanly (P3)

- [ ] **T046** [US6] Fix the dark-mode select option highlight — no `option:hover`/`option:checked` rule exists anywhere. Apply the tokens that already work on `.corex-select` (shell L552-555). Delete the redundant `.corex-data__form option` override (`data.css` L287-290). `corex-admin-shell.css`.
- [ ] **T047** [US6] **Verify T046 in a real browser** — pinned dark *and* `prefers-color-scheme: dark` (FR-026, SC-011). Native popups are OS-rendered: **if the highlight will not take, migrate the remaining native selects to `.corex-select`** rather than ship a rule that silently does nothing. Do not mark T046 done on a CSS read alone.
- [ ] **T048** [US6] Add the missing base layer to `corex-admin-shell.css`: heading rhythm (L258-266 sets colour/font only), a `.corex-admin p` rule (**none exists**), and one shared description/help component (FR-025).
- [ ] **T049** [US6] Remove the per-sheet duplicates the base layer replaces: the `margin: 0` heading resets (`control-panel.css` L14-20, `insights.css` L43-48/L133-136, shell L588-590/L682-684/L699-700) and the four spellings of the muted-description pattern (shell L247-252, control-panel L243-247, insights L138-143, addons L150). Reconcile stat-card `xs` (control-panel L37-59) vs overview tile `2xs` (L501-522).
- [ ] **T050** [US6] Visual pass across **all 11 admin sheets** — T048/T049 reach every screen, not just the three in this spec.
- [ ] **T051** [P] [US6] Insights: unify the two grids (`.corex-insights` 24rem/lg L3-7 vs `.corex-insights__widgets` 18rem/md L108-113) onto shared tokens (FR-027).
- [ ] **T052** [US6] Insights: order cards by state urgency rather than registration order (`InsightWidgets::widgets()` L53-64) — critical first, `disconnected`/`empty` last (FR-027). Unit test for the ordering.
- [ ] **T053** [P] [US6] Fix `InsightsScreen.php` L75's hardcoded `'1.1.0'` → `filemtime`, matching every other sheet (FR-029). **Without this, the WS8 redesign ships stale to returning visitors.**
- [ ] **T054** [US6] stylelint clean; RTL + light mode verified on every changed screen (FR-028). Guards clean.

---

## Phase 9: Polish & Close

- [ ] **T055** Full suites green, no regression against the T003 baseline: Pest unit + integration, Jest, Playwright, stylelint, `composer validate --strict`.
- [ ] **T056** All guards clean on the full diff: `wp-guard` + `clean-code-guard` (PHP/JS), `test-guard` (tests), `docs-guard` (docs).
- [ ] **T057** Update docs where behaviour changed — `docs/en/03-operations/security.md`, `docs-app/src/content/docs/guides/security.mdx`, `packages/cli/README.md` (all reference `COREX_LOGIN_UNGUARD` / the reset command). FR-013 requires recovery to be documented where a locked-out owner can still find it.
- [ ] **T058** Update `PROGRESS.md`; log non-trivial decisions in `DECISIONS.md` — the either-ability permission resolution, and the out-of-scope duplicated-SCSS flag.
- [ ] **T059** Raise the PR against `main` naming branch, spec path, task IDs, verification results, and guard status.

---

## Dependencies

- **T001-T004** block everything. **T002 blocks all login work** (T010-T030) — recovery is proven before hiding is touched.
- **Phase 2 (T005-T009)** blocks Phase 3 and 4 — the container must not fatal before live login changes.
- **US1 (Phase 3)** → **US2 (Phase 4)**: sequential, same files.
- **US3 / US4 / US5** are independent of the login work and of each other — parallelisable after Phase 1.
- **T034 blocks T035** (the redirect needs `?tab=` sync).
- **T048/T049 block T050**; **T046 blocks T047**.
- **Phase 9** requires all prior phases.

## Implementation Strategy

**MVP = US1 + US2** (Phases 1-4). Together they make login hiding real and un-lockout-able — the defect the owner reported twice. Everything after is consolidation, usability, and presentation, each independently shippable.

Recommended order: Phase 1 → 2 → 3 → 4 (P1 complete, shippable) → 5 → 6 (P2) → 7 → 8 (P3) → 9.
