# Phase 1 Contracts: Block Engine

The stable public API. Signatures are the agreed shape; implementation lives in `tasks.md`.

## C1 — BlockMap

```php
namespace Corex\Blocks;

final class BlockMap
{
    /**
     * Discover valid block folders under $blocksDir.
     *
     * @return list<array{dir: string, name: string, metadata: array<string, mixed>}>
     */
    public function discover(string $blocksDir): array;
}
```

Skips folders without a valid `block.json`; logs + skips malformed ones; de-dupes by `name`.

## C2 — BlockRenderer

```php
namespace Corex\Blocks;

interface BlockRenderer
{
    /** @param array<string, mixed> $attributes */
    public function render(array $attributes, string $content, object $block): string;  // escaped, i18n
}
```

## C3 — DynamicBlockRegistrar

```php
namespace Corex\Blocks;

final class DynamicBlockRegistrar
{
    public function __construct(
        private \Corex\Container\ContainerInterface $container,
        private \Corex\Support\BootLogger $logger,
    ) {}

    /** @param array{dir: string, name: string, metadata: array<string,mixed>} $block */
    public function register(array $block): void;  // register_block_type + container-resolved render_callback
}
```

The render callback resolves a `Renderer.php` (`BlockRenderer`) in the block folder from the container;
a throwable yields an empty string and a logged warning (never fatal).

## C4 — Connector + Registry

```php
namespace Corex\Blocks\Connectors;

interface Connector
{
    public function name(): string;                          // 'corex/career'
    public function value(string $field, array $args): mixed; // escaped, empty-safe
}

abstract class RepositoryConnector implements Connector
{
    public function __construct(protected \Corex\Repositories\RepositoryInterface $repository) {}
    // value() loads through the Repository; missing → fallback; escaped on return
}

final class ConnectorRegistry
{
    public function register(Connector ...$connectors): void;  // register_block_bindings_source per connector
}
```

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C1 discover valid | returns the block dir + name + metadata | FR-001 |
| C1 non-block / malformed / empty | skipped/logged; empty set ok | FR-002, FR-003 |
| C1 duplicate name | one registration (first wins) | FR-004 |
| C3 render delegation | callback resolves renderer from container; output is the renderer's | FR-008, FR-009 |
| C3 render throws | empty output + logged, non-fatal | FR-010 |
| C4 connector value | Repository field, escaped | FR-012 |
| C4 missing value | safe fallback, not error | FR-013 |
| Integration | register_block_type + bindings source register; example renders a field | FR-005, SC-002/004 |
| Example block | RTL/tokens/i18n/WCAG | SC-006 |
