# Implementation Plan: corex-blocks (Block Engine)

**Branch**: `004-block-engine` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/004-block-engine/spec.md`

## Summary

Deliver the block engine: discover blocks by convention (a folder + `block.json` under
`src/blocks`), register each from its metadata via the core block API (which loads each block's
declared assets only when the block renders — Principle VI), resolve a dynamic block's render through
the container so it stays thin and injectable, and register connectors via the WP Block Bindings API so
editors can bind block attributes to Corex Repository fields. Ships one **dynamic, server-rendered**
example block bound to a Repository field — no JS build. The headless-testable core is the discovery,
the render delegation, and the connector value resolution; WP registration is integration-tested.

## Technical Context

**Language/Version**: PHP 8.3.

**Primary Dependencies**:
- corex-core (container, `ServiceProvider`, `Config`, `BootLogger`) + the data layer (Repositories the
  connectors expose).
- WordPress block APIs: `register_block_type()` (from a directory with `block.json` — handles
  conditional `script`/`style`/`viewScript` enqueueing), `render_callback`, and
  `register_block_bindings_source()` (WP 6.5+/7.0) for connectors.
- No new Composer packages. No JS build in this spec (server-rendered).

**Storage**: None new — blocks render existing data via the data layer.

**Testing**: Pest + Brain Monkey. Discovery (scan + `block.json` validation), the render-delegation
(container resolves a renderer), and the connector value resolution are unit-tested headlessly with a
temp dir / stubbed Repository. Block + binding *registration* and the rendered example are
integration-tested against `./wp`.

**Target Platform**: WordPress ≥ 7.0 (block bindings/connectors), PHP ≥ 8.3.

**Project Type**: `Corex\Blocks` → `plugins/corex-blocks/src`; blocks under `src/blocks`, connectors
under `src/connectors`.

**Performance Goals**: Discovery scans one folder level once on boot; assets enqueue only per-block
(no global library); connector value resolution reuses the data layer (no N+1 beyond the Repository).

**Constraints**: Conditional assets only (Principle VI); `theme.json` tokens + logical CSS (V/VIII);
render thin + injected (III/IV); escape output, i18n, WCAG (VII/§); ACF/Woo/Polylang absent must work.

**Scale/Scope**: The engine + one example block. A full block library, the theme/token module, forms,
interactive JS blocks, and the build pipeline are out of scope. No NEEDS CLARIFICATION (clarified
2026-06-08).

## Constitution Check

- [x] **I. Theme is a skin** — PASS: blocks live in the `corex-blocks` plugin (markup/assets), not the
  theme; the theme only consumes tokens.
- [x] **II. Plugins boot themselves** — PASS: registered via a `ServiceProvider`; discovery/registration
  hook on `init`.
- [x] **III. Thin controllers/render** — PASS: a dynamic block's render delegates to a container-resolved
  renderer; no data-source calls/business rules in the callback.
- [x] **IV. Everything injected** — PASS: BlockMap, renderers, connectors resolve through the container.
- [x] **V. Runtime tokens** — PASS: the example block's styles use `theme.json` CSS variables; no
  hardcoded colors/sizes.
- [x] **VI. Conditional assets** — **PASS (headline)**: `register_block_type` enqueues a block's declared
  assets only when it renders; no global library (SC-002).
- [x] **VII. Declarative security** — render output is escaped; connector values escaped (FR-010, FR-013).
- [x] **VIII. RTL-first** — example block styles use logical properties (SC-006).
- [x] **IX. No optional dep is hard** — PASS: connectors expose Repositories (ACF-optional via the data
  layer); SC-005 proves operation with ACF/Woo/Polylang absent.
- [x] **X. Spec is source of truth** — PASS: traces to spec 004 (clarified); implements FRAMEWORK §5/§8.
- [x] **Guard Gate + Definition of Done** — guards per task; the example block is i18n/escaped/RTL/WCAG.

**Result: PASS** — no violations.

## Project Structure

```text
plugins/corex-blocks/
└── src/
    ├── BlockMap.php               # discover src/blocks folders, validate block.json, register each
    ├── BlockRenderer.php          # interface: render(array $attributes, string $content, object $block): string
    ├── DynamicBlockRegistrar.php  # wires register_block_type with a container-resolved render_callback
    ├── Connectors/
    │   ├── Connector.php           # interface: name(), value(string $field, array $args): mixed (Repository-backed)
    │   ├── ConnectorRegistry.php   # register each Connector via register_block_bindings_source
    │   └── RepositoryConnector.php # base: resolves a field from a Repository, escaped, empty-safe
    ├── BlocksServiceProvider.php   # bind the above; boot() hooks 'init' to discover+register
    └── blocks/
        └── entity-field/           # the example: dynamic, server-rendered, binds a Repository field
            ├── block.json          # name, attributes, style (conditional), render
            ├── Renderer.php         # Corex\Blocks\blocks\entity_field\Renderer implements BlockRenderer
            └── style.css           # theme.json tokens + logical properties only

tests/
├── Unit/Blocks/                   # BlockMap discovery (temp dir), render delegation, connector value
└── Integration/Blocks/            # real register_block_type + bindings source + rendered example
```

**Structure Decision**: Fills `corex-blocks` (`Corex\Blocks`). `BlocksServiceProvider` joins `Boot`'s
core provider list; discovery + registration run on `init` (the correct WP moment for blocks/bindings).

## Key design decisions

1. **Convention discovery** — `BlockMap` scans one level of `src/blocks/*/block.json` and registers each
   via `register_block_type($dir)`. Zero central list (SC-001), mirroring spec 001's ControllerMap.
2. **Conditional assets for free** — declaring `style`/`script`/`viewScript` in `block.json` makes WP
   enqueue them only when the block renders; we add **no** global asset (Principle VI).
3. **Container-resolved render** — a block folder with a `Renderer.php` (implementing `BlockRenderer`) is
   registered with a `render_callback` that resolves that renderer from the container and calls
   `render()`; the callback catches throwables → empty output + logged (FR-008/010). Static blocks need
   no renderer.
4. **Connectors over the WP Block Bindings API** — `ConnectorRegistry` registers each `Connector` via
   `register_block_bindings_source(name, ['get_value_callback' => …])`; `RepositoryConnector` resolves
   the bound field through a Repository (the only data-source caller), escaped and empty-safe (FR-012/13).
5. **Headless-testable seams** — discovery validation, render delegation, and connector value resolution
   are pure/injectable and unit-tested; only `register_block_type`/`register_block_bindings_source` need
   WordPress (integration).

## Phase 0 — Research

See [research.md](./research.md): block registration from metadata + conditional assets, container-resolved
render_callback, the Block Bindings connector source, discovery validation, and the test split. No open
NEEDS CLARIFICATION.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md) — Block, BlockMap, BlockRenderer, Connector/Registry, Binding.
- [contracts/block-contracts.md](./contracts/block-contracts.md) — the PHP public API + test matrix.
- [quickstart.md](./quickstart.md) — runnable validation scenarios mapped to the success criteria.

## Complexity Tracking

No constitution violations — section intentionally empty.
