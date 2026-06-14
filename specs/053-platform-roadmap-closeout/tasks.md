# Tasks: Platform Roadmap Closeout (053)

**Feature**: `specs/053-platform-roadmap-closeout` · **Branch**: `feature/053-platform-roadmap-closeout`
**Inputs**: plan.md · spec.md (US1–US4) · research.md (D1–D10) · data-model.md · contracts/{data-screen,
test-buttons, make-site-starter}.md · quickstart.md

**Story legend**: US1 = docs honesty (P1, MVP) · US2 = Data admin UI (P1) · US3 = captcha/insights test
buttons (P2) · US4 = `make:site --starter`/`--minimal` (P2).

**Conventions**: `[P]` = parallelizable (different files, no incomplete-task dependency). Tests are REQUIRED
(constitution DoD): Pest for PHP/CLI, Jest for JS, Playwright (052) reused/env-gated. Every story ends with a
Guard Gate task — no diff ships until its guards run clean. All four stories are independent and independently
testable; recommended order = priority order (US1 → US2 → US3 → US4).

---

## Phase 1: Setup

- [ ] T001 Confirm the build/test toolchain is ready: `composer install`, `npm install`, and that
  `npm run build`, `npm run test:js`, and `composer test` run green on the current branch (baseline counts
  recorded in the PR description). No code change.
- [ ] T002 [P] Skim the verified backends this feature consumes (so tasks reference real signatures):
  `plugins/corex-config/src/Data/DataController.php` (queryFrom params), `DataExportController.php`
  (`admin_post_corex_data_export`, nonce `corex_data_export`), `addons/corex-captcha/src/CaptchaTestController.php`
  (`POST /captcha/test`), `plugins/corex-config/assets/insights.js` (existing Run-check), and
  `packages/cli/src/Site/SiteScaffolder.php` + `Commands/MakeCommand.php`. Note exact names in the PR. No code change.

## Phase 2: Foundational (blocking prerequisites)

- [ ] T003 Confirm no new shared infrastructure is required (no new routes/services/schema — per plan
  "Structure Decision"). If a shared admin CSS token file is needed by both US2 and US3, create
  `plugins/corex-config/assets/data.css` placeholder with token/RTL scaffolding only; otherwise skip. Minimal.

---

## Phase 3: User Story 1 — Documentation honesty (P1) 🎯 MVP

**Goal**: README is an honest public entry point; PROGRESS + 045/049 `tasks.md` checkboxes match the code; a
feature-PR docs rule exists; stale phrases removed.
**Independent test**: `grep -niE "bootstrap stage|no framework code yet" README.md` → no matches; README names
the real modules; the 045/049 contradicted checkboxes now match the code; `docs-guard` clean.

- [X] T004 [US1] Rewrite `README.md` into an honest public entry point: real modules (corex-core/-blocks/
  -config/-forms + add-ons), the read-first source-of-truth hierarchy, accurate local-dev setup (`wp/`
  subdirectory + junction mapping), released status — remove the "bootstrap stage / no framework code yet"
  block and any inaccurate completion/version claim. (FR-001)
- [X] T005 [P] [US1] Reconcile `specs/049-make-site/tasks.md`: T008 corrected `[x]`→`[~]` (claimed
  `--starter`/`--minimal` flags `MakeCommand::runSite` does not parse), T007 annotated NOT-DONE→053, T012
  annotated PARTIAL; reconciliation banner added. (FR-002)
- [X] T006 [P] [US1] Reconcile `specs/045-data-management-pro/tasks.md`: T009/T011/T013 annotated (backend
  done, UI not built → 053 US2); PROGRESS top entry corrected (the "ROADMAP COMPLETE" banner is now historical;
  "roadmap is complete" line superseded). (FR-002)
- [X] T007 [US1] Added the **feature-PR docs rule** as `COREX-WORKING-GUIDE.md` §D.5 (surface↔change mapping +
  honesty clause + generated-reference exclusion) and referenced it from the D.4 Definition of Done. (FR-003, D10)
- [X] T008 [P] [US1] Stale-phrase sweep: README (rewritten), PROGRESS (corrected), `docs-app/.../guides/data.md`
  (status note added — REST layer exists, screen controls pending in 053); grep confirms no
  "bootstrap stage / no framework code yet" remains in hand-authored docs (the only hits are 053's own spec and
  the §D.5 cautionary quote). (FR-004)
- [X] T009 [US1] **Guard Gate (US1)**: `docs-guard` run clean — every README CLI command + module/add-on claim
  verified against source (`CliServiceProvider.php`, directory + specs); no unverifiable claims; links resolve.
  (FR-025)

**Checkpoint**: US1 delivers integrity independently — shippable even if US2–US4 slip.

---

## Phase 4: User Story 2 — Data admin screen (P1)

**Goal**: Corex → Data is fully usable: search, source/form filter, sortable headers, pagination, CSV export of
the current view, a detail drawer, and distinct loading/error/empty states.
**Independent test**: Jest green for the controls; in-browser (env-gated) the seven quickstart US2 steps pass
console-clean.

### Tests first (TDD)

- [X] T010 [P] [US2] Jest: `useSource`/request-building — search sets `search` + resets `page` to 1; header
  click sets `sort` + toggles `dir`; pagination sets `page`; assert the params sent to `window.Corex.api.get`.
  (`window.Corex`, `wp.*` mocked.) File: `plugins/corex-config/src/admin/__tests__/dataQuery.test.js`. (contracts/data-screen.md)
- [X] T011 [P] [US2] Jest: Export URL builder produces `admin-post.php?action=corex_data_export` with the
  current `source/search/form/sort/dir` + `corex_data_export` nonce. Same `__tests__` dir.
- [X] T012 [P] [US2] Jest: detail open fetches `GET /data/{source}/{id}` and renders `fields` (label→value,
  `—` for empty); the three states (loading/error/empty incl. "No matches" vs "No data yet") render.

### Implementation

- [X] T013 [US2] Extend `useSource` in `plugins/corex-config/src/admin/index.js`: add
  `search/form/sort/dir/status/error/detail` state; send the params; track `status: idle|loading|error|ready`;
  reset page on filter change. (FR-005..008, FR-011, D1/D2/D5)
- [X] T014 [US2] Add the controls to the screen: a search box, a source/form `<select>` filter, sortable column
  headers (set `sort`/toggle `dir`, reflect `aria-sort`) for both the DataViews path and the fallback table,
  and pagination wired to `total`. (FR-005..008, FR-012)
- [X] T015 [US2] Add the **Export** button: builds the export URL (T011) with the current query + nonce and
  triggers a file-download navigation; show a "first N rows exported" note when the backend truncates.
  (FR-009, D3)
- [X] T016 [US2] Add the **detail drawer/modal**: opens on row action, fetches `/{id}`, renders label→value
  fields + form + date, focus-trapped + ESC-close + labelled, `—` for empty values. (FR-010, D4)
- [X] T017 [US2] Add the loading/error/empty rendering: spinner + `aria-busy` on loading, actionable message +
  retry on error, distinct empty states. (FR-011, D5)
- [X] T018 [P] [US2] Styling in `plugins/corex-config/assets/data.css` (or `style.scss`): drawer + states +
  controls, token-only (`theme.json` vars), logical CSS/RTL, WCAG focus states. No raw hex/size. (FR-012,
  Principles V/VI/VIII)
- [X] T019 [US2] `npm run build`; ensure the screen enqueues `corex-runtime` (043) as a dependency and loads
  only on the Data screen (Principle VI). Verify Jest (T010–T012) green.
- [X] T020 [US2] **Guard Gate (US2)**: `clean-code-guard` + `wp-guard` (escaping/nonce/no-secret on any touched
  PHP enqueue) + `test-guard` (the Jest suites). Fix findings. (FR-025)

**Checkpoint**: Data screen fully usable; backend untouched (backward compatible).

---

## Phase 5: User Story 3 — Captcha & insights test buttons (P2)

**Goal**: A working captcha **Test** button (the real gap) + verified/polished insights **Check** messaging,
both classified, actionable, and secret-safe.
**Independent test**: Jest green for the captcha module; on the settings/insights screens (env-gated) each
button returns a classified message and never shows a secret.

### Tests first (TDD)

- [X] T021 [P] [US3] Jest: captcha button module — Test click POSTs to `/captcha/test` with the nonce, sets a
  busy state, renders each `status` message (`ok/missing_keys/invalid_keys/network_error/not_applicable`), lists
  `missingKeys`, and **reads no secret field** from config. File:
  `addons/corex-captcha/assets/__tests__/captcha-admin.test.js`. (contracts/test-buttons.md, D6/D7)

### Implementation

- [X] T022 [US3] Create `addons/corex-captcha/assets/captcha-admin.js`: vanilla over `window.Corex.api`, no
  build; Test → busy → POST `/captcha/test` → classified, secret-free message in a `role="status"` live region;
  re-enable on completion; i18n via `wp.i18n`. (FR-013..015, FR-017, D6/D7)
- [X] T023 [US3] Enqueue the module in `addons/corex-captcha/src/CaptchaServiceProvider.php` on the settings
  screen only (conditional), localized with `{ restUrl, nonce }`; depend on `corex-runtime`. (FR-013,
  Principle VI/VII)
- [X] T024 [US3] Verify + polish the existing insights "Run check" in `plugins/corex-config/assets/insights.js`:
  confirm it renders the `PsiDiagnostic` classification (`local_url/http_error/quota/invalid_key/
  invalid_response/ok`) with actionable wording + a "recommended, not required" note for a missing optional API
  key; tighten any vague copy. Add a button only if verification finds it absent. (FR-016, D6)
- [X] T025 [US3] `npm run build` if needed; verify Jest (T021) green; manual/console check (env-gated). 
- [X] T026 [US3] **Guard Gate (US3)**: `clean-code-guard` + `wp-guard` (no-secret, nonce, escaped output,
  conditional enqueue) + `test-guard`. Fix findings. (FR-014/FR-025)

**Checkpoint**: Captcha + insights diagnostics usable from the UI, secret-safe.

---

## Phase 6: User Story 4 — `make:site --starter` / `--minimal` (P2)

**Goal**: `wp corex make:site Acme --starter` emits a runnable example slice + standalone starter theme with an
asset architecture and a removal guide; default/`--minimal` omit the slice.
**Independent test**: Pest green (`--starter` emits + `php -l` clean; `--minimal`/default omit; idempotent;
reserved name refused); `wp corex make:site Acme --starter` runs live (env-gated).

### Tests first (TDD)

- [X] T027 [P] [US4] Pest `tests/Unit/Cli/SiteScaffolderStarterTest.php`: `--starter` emits the slice
  (model/repo/service/controller/block+renderer/option/test/`REMOVE-EXAMPLE.md`) + starter-theme assets, every
  generated `.php` passes `php -l`, identifiers client-namespaced; default and `--minimal` omit the slice;
  idempotent without `--force`; a name normalizing to `corex` is refused. (contracts/make-site-starter.md)

### Implementation

- [X] T028 [P] [US4] Author `packages/cli/stubs/starter/**`: client-namespaced model, repository, service,
  controller using the **spec-043 response envelope**, dynamic block (`block.json`+`index.js`+`style.scss`+
  renderer, token-only/RTL), option page (AdminGuard-gated), a matching test, and `REMOVE-EXAMPLE.md` listing
  the slice files. (FR-018, FR-020, D8)
- [X] T029 [P] [US4] Author the **standalone starter theme** stubs under `packages/cli/stubs/starter/theme/**`:
  `style.css` (not a child theme), `theme.json` consuming Corex tokens, templates/parts, `assets/src/*.scss` +
  `*.js`, `package.json` build scripts (`@wordpress/scripts`: dev source maps, minified prod, hashed
  `*.asset.php`), and `inc/Assets.php` (`url()`/`path()`/`version()` over the manifest for images/icons/fonts).
  (FR-019, D9)
- [X] T030 [US4] Extend `SiteScaffolder::scaffold()` with the `starter` option: when true, add the slice + theme
  assets to the render-all-before-write map; default/`starter=false` unchanged. Keep pure (no WP). (FR-018/021/
  023, D8)
- [X] T031 [US4] Wire flags in `packages/cli/src/Commands/MakeCommand.php` `runSite()`:
  `starter => (bool)($assoc['starter'] ?? false) && ! ($assoc['minimal'] ?? false)`; ensure `--minimal`/
  `--plugin-only`/`--theme-only`/`--force` all recognized; report created files + the REMOVE-EXAMPLE guidance.
  (FR-021/022)
- [X] T032 [US4] Verify Pest (T027) green; run `wp corex make:site Acme --starter` live if env permits, else
  record env-gated. (SC-006)
- [X] T033 [US4] **Guard Gate (US4)**: `clean-code-guard` + `wp-guard` (generated route/envelope/escaping/
  no-secret) + `test-guard` (incl. generated `php -l`) + `docs-guard` (REMOVE-EXAMPLE + generated governance
  accuracy). Fix findings. (FR-025)

**Checkpoint**: `make:site` capstone complete; lean scaffold still the default.

---

## Phase 7: Polish & cross-cutting

- [X] T034 [P] Update docs-app guides for the shipped surfaces: `guides/data.md` (search/filter/sort/export/
  detail), `guides/configuration.md` + `guides/insights.md` (test buttons), `guides/client-site.md` (the
  `--starter` slice + "how to remove"); plus `plugins/corex-config/README.md`, `addons/corex-captcha/README.md`,
  `packages/cli/README.md`. (FR-003 rule applied to this very PR) — `docs-guard` clean.
- [~] T035 Reuse the spec-052 Playwright + console sweep (`tests/e2e/`): Data flow + settings/insights buttons
  console-clean. Execute under wp-env if available; otherwise record exactly what remains env-gated. (SC-007)
- [X] T036 Update `PROGRESS.md` (053 entry, truthful) + `DECISIONS.md` (#87 — the closeout + the feature-PR docs
  rule); end with the NEXT STEP block. Stamp no version (release is a separate step).
- [X] T037 Full-suite verification: `composer test` + `npm run test:js` green; record counts. Then commit per
  story (Conventional Commits) → push → PR into `develop` → CI green.

---

## Dependencies & ordering

- **Setup (T001–T002) → Foundational (T003)** precede the stories.
- **User stories are independent** and can be done in any order; recommended = priority: **US1 → US2 → US3 →
  US4**. US1 is the MVP and ships alone.
- Within a story: **tests before implementation** (T010–T012 → T013–T019; T021 → T022–T025; T027 → T028–T032).
- Polish (T034–T037) after the stories it documents/verifies.

## Parallel opportunities

- US1: T005, T006, T008 in parallel (different files) after T004.
- US2: T010, T011, T012 (Jest specs) in parallel; T018 (CSS) parallel with T013–T017.
- US4: T027, T028, T029 in parallel (test + two independent stub sets) before T030/T031.
- Across stories (if staffed): US1, US2, US3, US4 touch disjoint files and can run concurrently.

## MVP scope

**US1 (docs honesty)** alone is a viable, valuable increment — it restores integrity and corrects the false
completion claims. Add **US2 (Data UI)** for the highest user-facing value. US3 + US4 complete the platform tails.

## Format validation

All tasks use `- [ ] [TaskID] [P?] [Story?] description + file path`; Setup/Foundational/Polish carry no story
label; US1–US4 tasks carry their label; test tasks precede implementation in each story.
