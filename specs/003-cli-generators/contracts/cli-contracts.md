# Phase 1 Contracts: CLI Generators

The stable public API. Signatures are the agreed shape; implementation lives in `tasks.md`.

## C1 — Naming

```php
namespace Corex\Cli\Support;

final class Naming
{
    /** Normalize + validate; re-apply $suffix. Throws InvalidNameException on bad input. */
    public function classNameFor(string $raw, string $suffix = ''): string;

    public function postTypeFor(string $className): string;  // 'CareerListing' → 'career_listing'
}
```

`Corex\Cli\Support\InvalidNameException` — empty, non-identifier, or reserved-word names (FR-009).

## C2 — StubRenderer

```php
namespace Corex\Cli\Generators;

final class StubRenderer
{
    /** @param array<string, string> $values  Replace each {{ key }}; a leftover {{…}} throws. */
    public function render(string $stub, array $values): string;
}
```

`Corex\Cli\Generators\UnresolvedPlaceholderException` — a stub token with no value (FR-003).

## C3 — GeneratorContext

```php
namespace Corex\Cli\Generators;

final class GeneratorContext
{
    public function __construct(
        public readonly string $basePath,
        public readonly string $namespace,
        public readonly string $prefix,
    ) {}
}
```

## C4 — Generator + concretes

```php
namespace Corex\Cli\Generators;

abstract class Generator
{
    abstract public function stub(): string;       // 'model'
    abstract public function suffix(): string;      // '' | 'Repository' | 'Controller' | 'Service'
    abstract public function subPath(): string;     // 'Models' | 'Repositories' | ...
    /** @return array<string, string> */
    abstract public function placeholders(string $className, GeneratorContext $ctx): array;
}
```

## C5 — GeneratorEngine + Result

```php
namespace Corex\Cli\Generators;

final class GeneratorEngine
{
    public function __construct(
        private StubRenderer $renderer,
        private \Corex\Cli\Support\Naming $naming,
        private GeneratorContext $context,
        private string $stubsDir,
    ) {}

    public function generate(Generator $generator, string $rawName, bool $force = false): GeneratorResult;
}

final class GeneratorResult
{
    public const CREATED = 'created';
    public const SKIPPED = 'skipped';
    public const ERROR   = 'error';

    public readonly string $status;
    public readonly string $path;
    public readonly ?string $message;

    public function isCreated(): bool;
}
```

**Guarantees**: never overwrites unless `$force` (FR-008); never writes a file containing a leftover
token (FR-003); creates the target directory if missing (FR-002).

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C1 valid name | normalizes + applies suffix | FR-010 |
| C1 invalid name | `InvalidNameException`, no write | FR-009, SC-004 |
| C2 render | all tokens replaced | FR-001 |
| C2 leftover token | `UnresolvedPlaceholderException` | FR-003 |
| C5 generate created | file written, no leftover token, correct path | FR-001/002, SC-001 |
| C5 exists, no force | `skipped`, file unchanged | FR-008, SC-003 |
| C5 exists, force | overwritten | FR-008 |
| C4 four generators | correct stub/suffix/subPath/placeholders | FR-005/006 |
| Provider | commands register only when WP_CLI present | FR-012, SC-005 |
| Generated output | passes clean-code + wp-guard | FR-007, SC-002 |
