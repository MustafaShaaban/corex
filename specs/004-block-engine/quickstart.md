# Quickstart & Validation: Block Engine

Runnable scenarios. Types live in [contracts/block-contracts.md](./contracts/block-contracts.md) and
[data-model.md](./data-model.md).

## Prerequisites

- corex-core + data layer active (specs 001–002); WordPress ≥ 7.0 at `./wp`.
- `composer install`. No JS build (server-rendered).

## Run the tests

```bash
composer test                 # headless unit: BlockMap discovery (temp dir), render delegation, connector value
composer test:integration     # against ./wp: register_block_type + bindings source + example render
```

## Scenario 1 — Blocks self-register by convention (US1, SC-001)

```text
Add a folder src/blocks/<name>/block.json → on init it is registered automatically.
A folder without a valid block.json is ignored; an empty src/blocks still boots.
```
**Expected**: every valid block folder is registered (visible via the block registry); no central list
edited; malformed/non-blocks skipped with no fatal.

## Scenario 2 — Assets load only when the block renders (US2, SC-002)

```text
Render a page containing the example block → its style.css is enqueued.
Render a page without it → its style.css is absent. No framework-global asset on either page.
```
**Expected**: per-block conditional enqueue (from block.json); zero global library.

## Scenario 3 — Dynamic render through the container (US3, SC-003)

```text
The example block's render resolves its Renderer from the container and outputs the service's markup.
A renderer that throws → empty output (logged), no fatal page.
```
**Expected**: container-resolved, thin render; escaped, i18n-ready output; non-fatal on error.

## Scenario 4 — Bind a block to Corex data (US4, SC-004)

```text
Register a RepositoryConnector; bind a core block attribute to one of its fields; render the page.
```
**Expected**: the block shows the field's value sourced through the Repository; a missing value renders
a safe fallback; the value is escaped.

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 zero-registry discovery | 1 |
| SC-002 conditional assets, no global | 2 |
| SC-003 container render, non-fatal | 3 |
| SC-004 connector binding + fallback | 4 |
| SC-005 passes with no optional plugins | `composer test` |
| SC-006 example RTL/tokens/WCAG | 2–4 (example block) |
| SC-007 headless coverage | `composer test` |
