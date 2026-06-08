# Phase 1 Data Model: Block Engine

Runtime objects of the block engine. No persisted entities — blocks render existing data.

## Entity map

```text
BlocksServiceProvider (boot on 'init')
  ├─ BlockMap.discover(src/blocks) → [valid block dirs + metadata]
  │     └─ DynamicBlockRegistrar.register(dir) → register_block_type(dir, [render_callback?])
  │            render_callback → container.make(Renderer)->render(...)  (BlockRenderer)
  └─ ConnectorRegistry.register(Connector[]) → register_block_bindings_source(...)
        RepositoryConnector.value(field) → Repository → escaped, empty-safe
```

## 1. BlockMap  *(FR-001–FR-004)*

- **discover(string $blocksDir): array** — list each subfolder with a valid `block.json`
  (`['dir' => ..., 'name' => ..., 'metadata' => [...]]`); skip non-blocks (no error) and malformed
  `block.json` (logged); de-dupe by `name` (first wins).
- **Invariant**: empty set is valid; one malformed block never aborts the others.

## 2. DynamicBlockRegistrar  *(FR-005, FR-008, FR-010)*

- **register(array $block): void** — `register_block_type($block['dir'], $args)`. If the folder has a
  `Renderer.php`, `$args['render_callback']` resolves that `BlockRenderer` from the container and calls
  `render()`, wrapped in try/catch (throwable → empty string + `BootLogger`). Conditional assets come
  from `block.json` (no custom enqueue).

## 3. BlockRenderer (interface)  *(FR-008, FR-009)*

- `render(array $attributes, string $content, object $block): string` — returns escaped, i18n-ready
  markup; delegates to a service for any data (no data-source calls in the renderer beyond an injected
  service/Repository).

## 4. Connector (interface) + RepositoryConnector + ConnectorRegistry  *(FR-011–FR-013, FR-019)*

- **Connector**: `name(): string`, `value(string $field, array $args): mixed`.
- **RepositoryConnector**: constructed with a Repository; `value()` loads the entity/field through the
  Repository, returns an **escaped, empty-safe** value (missing → fallback, never error).
- **ConnectorRegistry**: `register(Connector ...$connectors)` → `register_block_bindings_source($name,
  ['label' => ..., 'get_value_callback' => fn($args,$block,$key) => $connector->value($key, $args)])`.

## 5. Binding  *(FR-012)*

- The editor-created link from a block attribute to `['source' => '<connector name>', 'args' => [...]]`.
  Resolved at render by the connector's `get_value_callback`. (Data created in the editor, not a code
  entity.)

## 6. BlocksServiceProvider  *(FR-014)*

- **register()**: bind `BlockMap`, `DynamicBlockRegistrar`, `ConnectorRegistry`, and the example
  block's `Renderer`/`Connector`.
- **boot()**: on `init`, `discover` + register all blocks, then register connectors. Added to `Boot`'s
  core provider list.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| folder without block.json | skipped, no error | FR-002 |
| malformed block.json | skipped + logged | FR-003 |
| duplicate name | registered once (first wins), reported | FR-004 |
| missing asset in block.json | block still registers; reported | FR-007 |
| render callback throws | empty output + logged, non-fatal | FR-010 |
| connector field missing | safe empty/fallback, escaped | FR-013 |
| optional plugin absent | discovery/render/connectors all work | FR-017, SC-005 |
