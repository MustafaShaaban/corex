# Tasks: Admin data management (DataViews) (030)

**Forward spec, TDD-ordered.** Pure `DataSource` shaping is headless-tested; REST is cap/nonce-gated; the React
table is built (visual env-gated). FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm spec-007 submissions (`corex_submission` CPT), spec-011 TableRepository, spec-018 build, and the `corex-config` admin pattern (AdminGuard) are the model.

## Phase 2: Foundational — the DataSource abstraction
- [x] T002 Create `plugins/corex-config/src/Data/DataSource.php` (interface) + `DataRegistry.php` (register/all/find).

## Phase 3: US1 — submissions source + REST + screen (P1) 🎯 MVP
- [x] T003 Write `tests/Unit/Config/SubmissionsSourceTest.php` (RED): rows `{id,date,form,summary}` + columns from a stub reader; empty → `[]`.
- [x] T004 Implement `Data/SubmissionsSource.php` (injected reader; `delete` trashes the post) to pass T003; register it in `ConfigServiceProvider`.
- [x] T005 Write `tests/Unit/Config/DataControllerTest.php` (RED): `manage_options` permission; delete needs a nonce; unknown source handling.
- [x] T006 Implement `Data/DataController.php` (GET list + DELETE, cap+nonce) + register the routes.
- [x] T007 Implement `Data/DataAdminScreen.php` (Corex → Data submenu, AdminGuard, enqueue the React, print mount + REST root + nonce) + register in `ConfigServiceProvider`.
- [x] T008 Implement `plugins/corex-config/src/admin/data/index.js` (mount `@wordpress/dataviews`, fetch sources + rows, delete action) + the build entry (`package.json`).

## Phase 4: US2 — generic table sources (P2)
- [x] T009 [US2] Document + verify the `DataSource` interface lets a TableRepository-backed source register and appear in the same screen (a short reference `TableSource` example or test).

## Phase 5: Polish
- [x] T010 Guard Gate: wp-guard (REST cap/nonce, escaping), clean-code, test-guard; fix violations.
- [x] T011 [P] `npm run build --workspace=plugins/corex-config`; `composer test` green; record.
- [x] T012 [P] Verify on `./wp`: the Data submenu registers; `corex/v1/data/submissions` returns `{columns,rows,total}` (cap-gated).
- [x] T013 Docs: `plugins/corex-config/README.md` + **docs-app** configuration guide (the Data screen); PROGRESS + DECISIONS; NEXT STEP.

## Dependencies
- T002 before sources. T003–T004 (submissions) before T005–T008. US1 is the MVP; US2 is the generic extension.
