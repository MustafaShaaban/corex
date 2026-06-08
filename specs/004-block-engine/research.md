# Phase 0 Research: Block Engine

All decisions resolve the Technical Context. No NEEDS CLARIFICATION (clarified 2026-06-08).

## R1 — Block discovery & registration from metadata

**Decision**: `BlockMap` scans `src/blocks/*/` for a `block.json`, validates it (valid JSON with a
`name`), and registers each via `register_block_type($dir)` — WordPress reads the metadata and wires
the block. Folders without a valid `block.json` are skipped; a malformed one is logged and skipped
without aborting others (FR-001–FR-003). A duplicate `name` is registered once (first wins, FR-004).

**Rationale**: `register_block_type($dir)` is the canonical metadata path; one folder per block + a
directory scan gives zero-central-registry discovery (SC-001), mirroring spec 001's ControllerMap.

**Alternatives**: a hand-maintained `blocks` array (rejected — central list); `register_block_type` per
block by hand (rejected — boilerplate).

## R2 — Conditional assets

**Decision**: A block declares `style`/`editorStyle`/`script`/`viewScript` in its `block.json`;
WordPress enqueues those only when the block is present on the page. The engine adds **no** global
asset of its own.

**Rationale**: Block-metadata asset handling is exactly Principle VI ("pay only for what renders; never
a global library") — for free, no custom enqueue logic (SC-002).

## R3 — Container-resolved dynamic render

**Decision**: When a block folder contains a `Renderer.php` (a `BlockRenderer`), `DynamicBlockRegistrar`
registers the block with a `render_callback` that resolves the renderer **from the container** and calls
`render($attributes, $content, $block)`. The callback wraps the call in try/catch → empty string +
`BootLogger` on a throwable (FR-008, FR-010). Static blocks (no `Renderer.php`) register without a
callback.

**Rationale**: Keeps the render thin and injectable (Principles III/IV) while staying non-fatal; the
renderer is unit-testable directly (resolve it, call `render`), independent of WordPress.

**Alternatives**: a `render.php` file included by WP (rejected — not injectable, hard to test); logic in
the callback (rejected — violates thin-render).

## R4 — Connectors via the Block Bindings API

**Decision**: `ConnectorRegistry` registers each `Connector` through
`register_block_bindings_source($name, ['label' => …, 'get_value_callback' => fn($args, $block, $key) =>
$connector->value(...)])`. `RepositoryConnector` resolves the requested field through a Repository (the
only data-source caller), returning an escaped, empty-safe value (FR-011–FR-013, FR-019). Where core
binding support is partial, the same `Connector` powers a thin Corex source behind the registration.

**Rationale**: Block Bindings is the WP-native way to surface dynamic values into core blocks without a
bespoke block (FRAMEWORK §8); routing through a Repository keeps Principle III intact.

## R5 — Discovery validation (headless)

**Decision**: `BlockMap::discover(string $dir): array` returns the list of valid block directories +
their parsed metadata, and is the pure seam unit-tested with a temp dir (valid block, no-block folder,
malformed `block.json`, duplicate name). The WP `register_block_type` call is a separate, thin step
exercised in integration.

**Rationale**: Separating "what to register" (pure) from "register it" (WP) keeps the discovery rules
provable headlessly (FR-018, SC-007) — the same args/executor split used in specs 002–003.

## R6 — Test split

**Decision**: Unit — `BlockMap::discover` (temp dir), the render-delegation closure (container resolves
a fake renderer; throwable → empty + logged), and `RepositoryConnector::value` (stubbed Repository,
escaped/empty-safe). Integration — real `register_block_type` registers the example block, the bindings
source registers, and the example renders a Repository field on a page.

## Summary

| Concern | Choice |
|---|---|
| Discovery | scan `src/blocks/*/block.json`, validate, `register_block_type($dir)` |
| Conditional assets | declared in `block.json`; WP enqueues per-block; no global |
| Dynamic render | `render_callback` → container-resolved `BlockRenderer`; throwable → empty + logged |
| Connectors | `register_block_bindings_source` → `RepositoryConnector` (escaped, empty-safe) |
| Tests | discovery/render/connector unit; registration + example render integration |

No new Composer dependencies; no JS build.
