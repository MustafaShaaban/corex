# Tasks: Front-end build pipeline & dynamic block editor registration (018)

**Retrospective spec** — the implementation exists and is verified on real WP. These are
**reconciliation/verification** tasks: confirm each FR against the mapped file/behaviour (most already
satisfied, marked `[x]`), plus the one genuine open gap (a block-`index.js` Jest test, remediation **P4**).
Verification commands are in `quickstart.md`; the FR→file map is in `plan.md`.

**No new implementation work** beyond the P4 gap — flag any mismatch found as a defect rather than scope.

## Phase 1: Setup (verification context)

- [x] T001 Confirm the build toolchain resolves: `node_modules/.bin/wp-scripts` exists after `npm install` (root `package.json` declares `@wordpress/scripts`).
- [x] T002 Confirm per-package build scripts exist (`--webpack-src-dir`/`--output-path=build/blocks`) in `plugins/corex-blocks`, `plugins/corex-forms`, `addons/corex-ui`, `addons/corex-careers`, `addons/corex-kit-portfolio` `package.json`.

## Phase 2: Foundational (engine — blocks all stories)

- [x] T003 Verify `plugins/corex-blocks/src/BlockMap.php` discovers `<dir>/*/block.json`, de-dupes by name, skips malformed (covered by `tests/Unit/Blocks/BlockMapTest.php`).
- [x] T004 Verify `plugins/corex-blocks/src/DynamicBlockRegistrar.php` registers via `register_block_type` with a container-resolved render callback and wires `wp_set_script_translations` for editor+view+script handles.
- [x] T005 Verify `plugins/corex-blocks/src/BlocksServiceProvider.php` registers the "Corex" category (`block_categories_all`) and discovers from `build/blocks` else source.

## Phase 3: User Story 1 — Blocks recognised + editable in FSE (P1)

**Goal**: every `corex/*` block is editor-registered and previews its server render.
**Independent test**: `quickstart.md` step 2 (every block prints "— ok") + step 3 (server render).

- [x] T006 [US1] Verify FR-003: each block `block.json` declares `editorScript: file:./index.js` and each built type exposes a non-empty `editor_script_handles` (real-WP check, quickstart step 2).
- [x] T007 [US1] Verify FR-002: each `index.js` uses `registerBlockType` + `<ServerSideRender>`; the PHP `corex.renderer` produces the markup (`tests/Unit/Blocks/RenderDelegationTest.php`, `tests/Unit/Ui/UiBlocksTest.php`).
- [x] T008 [US1] Verify FR-008: all blocks set `category:"corex"` and the category registers (real-WP `get_block_categories` shows "Corex").
- [x] T009 [P] [US1] **(P4 — DONE 2026-06-11)** Added `addons/corex-ui/src/Blocks/posts/index.test.js` — asserts `registerBlockType(metadata.name, …)`, `save() === null`, and that `edit()` renders `<ServerSideRender block={metadata.name}>`. Virtual mocks for `@wordpress/blocks`/`block-editor`/`components`/`i18n`/`server-side-render` (they are webpack externals, not in node_modules) + the scss import. Added root `jest.config.js` scoping the run to Corex (excludes `wp/`). `npm run test:js` → 2 suites / 11 tests green.

## Phase 4: User Story 2 — One SCSS+JS build workflow, conditional + RTL (P1)

**Goal**: a single build emits compiled, conditional, RTL assets per block.
**Independent test**: `quickstart.md` step 1 (artifacts) + step 4 (conditional/RTL).

- [x] T010 [US2] Verify FR-001: `npm run build` emits `build/blocks/<name>/{index.js,index.asset.php,style-index.css,style-index-rtl.css}` (+ `view.js` for forms).
- [x] T011 [US2] Verify FR-005: each `style.scss` is token-only + logical CSS (`tests/Unit/Theme`/pattern token scans assert no hardcoded colour/size/font) and a `*-rtl.css` is emitted.
- [x] T012 [US2] Verify FR-004: assets declared in `block.json` (`style`/`viewScript`) so they enqueue only on render.

## Phase 5: User Story 3 — Headless core + visible add-ons (P2)

**Goal**: tests run with no build; add-ons mapped + active.
**Independent test**: `quickstart.md` step 5 (`composer test` green, no build) + add-ons active, no fatals.

- [x] T013 [US3] Verify FR-006: with no `build/`, discovery registers from source and `composer test` (269 unit) passes.
- [x] T014 [US3] Verify FR-009: `corex-forms` + the seven add-ons are junctioned into `wp/wp-content/plugins` and activated (no PHP fatals on boot).
- [x] T015 [US3] Verify FR-010: `.gitignore` excludes `**/build/`; build is a documented checkout/CI step (`packages/build-tools/README.md`).

## Phase 6: Polish & cross-cutting

- [ ] T016 [P] **(P2)** Run the Guard Gate formally on this feature's diff: `clean-code-guard` + `wp-guard` (+ `docs-guard` on `packages/build-tools/README.md`); fix any reported violation. _Tracked as remediation P2._
- [x] T017 Confirm docs: `packages/build-tools/README.md` describes the pipeline; DECISIONS #43 records the approach; PROGRESS reflects completion.

## Dependencies

- Phase 2 (engine) precedes all user stories. US1/US2/US3 are independently verifiable.
- T009 (P4) and T016 (P2) are the only **open** tasks; both are already tracked as remediation items.

## Implementation strategy

This spec is retrospective: the MVP (US1 — editor-recognised blocks) and US2/US3 are already delivered and
verified. The remaining work is the two tracked debts (T009 block-JS Jest test → P4; T016 formal guard run →
P2), executed when their remediation phase runs — **not** new feature work.

## Parallel opportunities

- T009 [P] (Jest test) and T016 [P] (guard run) touch different artifacts and can proceed in parallel during
  P4/P2.
