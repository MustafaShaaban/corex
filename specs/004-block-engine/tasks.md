---
description: "Task list for the Block Engine (spec 004)"
---

# Tasks: corex-blocks (Block Engine)

**Input**: Design documents from `specs/004-block-engine/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/block-contracts.md, quickstart.md

**Tests**: REQUIRED (constitution). Headless-testable seams (BlockMap discovery, render delegation,
connector value) are unit-tested; WP registration + the rendered example are integration-tested.

**Guard Gate (per story)**: `clean-code-guard` + `wp-guard` (production), `test-guard` (tests),
`docs-guard` (docs). ABSPATH guard on every src class file. The example block must be RTL/token/i18n/WCAG.

## Format: `[ID] [P?] [Story] Description`

- Code under `plugins/corex-blocks/src` (`Corex\Blocks`).

---

## Phase 1: Setup

- [X] T001 [P] Create `tests/Unit/Blocks/` and `tests/Integration/Blocks/`; confirm `Corex\Blocks` → `plugins/corex-blocks/src` autoload resolves.

---

## Phase 2: Foundational

- [X] T002 [P] `plugins/corex-blocks/src/BlockRenderer.php` — interface `render(array $attributes, string $content, object $block): string`.
- [X] T003 [P] `plugins/corex-blocks/src/Connectors/Connector.php` — interface `name(): string`, `value(string $field, array $args): mixed`.

---

## Phase 3: User Story 1 — Blocks self-register by convention (Priority: P1) 🎯 MVP

**Goal**: discover and register blocks from `src/blocks/*/block.json` with zero central list.

**Independent Test**: a temp dir with a valid block, a non-block folder, a malformed `block.json`, and a
duplicate name → discover returns only the valid, de-duped blocks; malformed logged; empty set ok.

- [X] T004 [P] [US1] Write failing `tests/Unit/Blocks/BlockMapTest.php`: `discover()` returns valid block folders (dir/name/metadata); skips non-blocks; logs+skips malformed `block.json`; de-dupes by name; empty set ok (FR-001–FR-004).
- [X] T005 [US1] Implement `plugins/corex-blocks/src/BlockMap.php` (scan one folder level, parse+validate `block.json`, de-dupe, log malformed via BootLogger).
- [X] T006 [US1] Guard gate; (integration registration is proven with the example block in Phase 6).

**Checkpoint**: MVP — blocks discovered by convention.

---

## Phase 4: User Story 3 — Dynamic render through the container (Priority: P2)

**Goal**: a dynamic block's render resolves a `BlockRenderer` from the container; throwable → empty + logged.

**Independent Test**: a block folder with a `Renderer.php` → the registrar's callback resolves it from
the container and returns its output; a renderer that throws → empty string + logged.

- [X] T007 [P] [US3] Write failing `tests/Unit/Blocks/RenderDelegationTest.php`: the render closure resolves a fake `BlockRenderer` from a real Container and returns its markup; a throwing renderer yields '' and a BootLogger entry (FR-008, FR-010).
- [X] T008 [US3] Implement `plugins/corex-blocks/src/DynamicBlockRegistrar.php` — `register(array $block)` calls `register_block_type($dir, $args)`; if `$dir/Renderer.php` exists, `$args['render_callback']` resolves the renderer from the container (try/catch → ''+log). Expose the callback factory as a unit-testable method.
- [X] T009 [US3] Guard gate (wp-guard on register_block_type usage; render output escaped).

**Checkpoint**: dynamic blocks render thin + injected.

---

## Phase 5: User Story 4 — Connectors bind blocks to Corex data (Priority: P2)

**Goal**: register a connector exposing a Repository field; bound block attributes render that value.

**Independent Test**: a `RepositoryConnector` over a stubbed Repository → `value(field)` returns the
escaped field value; a missing record/field → safe fallback.

- [X] T010 [P] [US4] Write failing `tests/Unit/Blocks/RepositoryConnectorTest.php`: `value()` returns the Repository field (escaped); a missing record/field → fallback, not error (FR-012, FR-013).
- [X] T011 [US4] Implement `plugins/corex-blocks/src/Connectors/RepositoryConnector.php` (abstract; field resolution via the injected Repository, escaped + empty-safe).
- [X] T012 [US4] Implement `plugins/corex-blocks/src/Connectors/ConnectorRegistry.php` — `register(Connector ...)` → `register_block_bindings_source($name, ['label'=>…, 'get_value_callback'=>…])` (FR-011, FR-019).
- [X] T013 [US4] Guard gate (wp-guard on register_block_bindings_source; values escaped).

**Checkpoint**: connectors registrable; values Repository-sourced + escaped.

---

## Phase 6: User Story 2 — Conditional assets + the example block (Priority: P1)

**Goal**: ship a dynamic, server-rendered example block whose assets load only when present, proving the
engine end-to-end.

**Independent Test (integration, ./wp)**: the example block registers; on a page with it the style is
enqueued (and absent otherwise); its dynamic render shows a connector-bound field; no global asset.

- [X] T014 [P] [US2] `plugins/corex-blocks/src/blocks/entity-field/block.json` (name `corex/entity-field`, an attribute, conditional `style`, dynamic `render`/api version) + `style.css` (theme.json tokens + logical properties only).
- [X] T015 [US2] `plugins/corex-blocks/src/blocks/entity-field/Renderer.php` (`BlockRenderer`) — thin, escaped, i18n; delegates display of the bound value.
- [X] T016 [US2] Write `tests/Integration/Blocks/ExampleBlockTest.php`: `register_block_type` registers `corex/entity-field`; rendering the block outputs its markup; its style is registered/enqueued conditionally; no framework-global asset (FR-005, FR-006, SC-002, SC-003).
- [X] T017 [US2] Guard gate (wp-guard + the example block RTL/tokens/i18n/WCAG).

**Checkpoint**: example block proves discovery + assets + render end-to-end.

---

## Phase 7: Wiring & Polish

- [X] T018 `plugins/corex-blocks/src/BlocksServiceProvider.php` — bind BlockMap/DynamicBlockRegistrar/ConnectorRegistry + the example Renderer/Connector; `boot()` hooks `init` to discover+register blocks then connectors; add `BlocksServiceProvider::class` to `Boot`'s provider list.
- [X] T019 [P] Write `tests/Integration/Blocks/BlocksBootTest.php`: blocks register on init in real WP; with ACF/Woo/Polylang absent the suite passes (FR-017, SC-005).
- [X] T020 [P] Update `plugins/corex-blocks/README.md` (or the corex-core docs) with a block-engine section; run docs-guard.
- [X] T021 Run full `quickstart.md` validation against `./wp`; final guard pass; confirm the headless unit suite passes with no optional plugins (SC-007).
- [X] T022 Update `PROGRESS.md` (spec 004 done) + `DECISIONS.md` (any new choices); verify Definition of Done.

---

## Dependencies & Execution Order

- **Setup** → **Foundational (interfaces)** → **US1 (discovery)** → **US3 (render)** → **US4 (connectors)**
  → **US2 (example + conditional assets, integration)** → **Wiring/Polish**.
- US1 discovery is the MVP. US3/US4 are independent of each other (both build on the interfaces +
  container). US2's example block exercises US1+US3+US4 together in real WP.

### Parallel opportunities
- Foundational interfaces (T002/T003) in parallel.
- Unit test tasks (T004/T007/T010) in parallel with their interfaces.

---

## Implementation Strategy

### MVP
Setup + Foundational + **US1 (BlockMap discovery)** = convention-based block discovery, headless-proven.
Then render (US3), connectors (US4), and the example block (US2) that ties them together in real WP.

### Notes
- `register_block_type` / `register_block_bindings_source` are the only WP-block calls — confined to
  `DynamicBlockRegistrar` / `ConnectorRegistry`; discovery + render-delegation + connector-value logic
  stays headless-testable. Conditional assets come from `block.json` (Principle VI; no global library).
- One task at a time; guards before each commit; the example block is RTL/token/i18n/WCAG.
