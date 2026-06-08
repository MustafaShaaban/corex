# Phase 1 Contracts: Data Layer

The stable public API module authors program against. Signatures are the agreed shape; implementation
lives in `tasks.md`/the implementation phase.

## C1 — Model

```php
namespace Corex\Models;

abstract class Model
{
    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes);

    abstract public static function postType(): string;

    /** Logical field name => meta/ACF key. @return array<string, string> */
    abstract public static function fields(): array;

    /** Logical name => ['type'=>'belongsTo','model'=>class-string,'foreignKey'=>string]. @return array<string, array> */
    public static function relations(): array { return []; }

    /** Attribute => 'int'|'bool'|'string'|'array'|DateTimeImmutable::class. @return array<string, string> */
    public static function casts(): array { return []; }

    public function get(string $attribute, mixed $default = null): mixed;  // read-only; no setters
    public function id(): int;
}
```

## C2 — Repository

```php
namespace Corex\Repositories;

use Corex\Database\QueryBuilder;
use Corex\Models\Model;

interface RepositoryInterface
{
    public function find(int $id): ?Model;                       // null when absent (FR-005)
    public function query(): QueryBuilder;                        // fluent reads
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Model;
    /** @param array<string, mixed> $attributes */
    public function update(int $id, array $attributes): Model;
    public function delete(int $id): bool;
}
```

`PostRepository` (abstract) implements this for a Model class + post type and is the **only** caller of
WordPress data functions (FR-004).

## C3 — Field driver

```php
namespace Corex\Fields;

interface FieldDriver
{
    public function get(int $entityId, string $key, mixed $default = null): mixed;  // missing → default (FR-011)
    public function set(int $entityId, string $key, mixed $value): void;
}

final class FieldResolver
{
    public function driver(): FieldDriver;  // AcfFieldDriver if get_field+update_field exist, else MetaFieldDriver
}
```

## C4 — QueryBuilder + Collection

```php
namespace Corex\Database;

final class QueryBuilder /* returns $this for chaining */
{
    public function where(string $field, mixed $value, string $compare = '='): self;
    public function orderBy(string $field, string $direction = 'ASC'): self;
    public function limit(int $max): self;
    public function with(string $relation): self;     // eager-load a belongs-to relation
    public function get(): Collection;                 // empty Collection when none (FR-017)
    public function first(): ?Model;
    /** @return array<string, mixed> the WP_Query args (capped, values bound) — pure, unit-testable */
    public function toArgs(): array;
}

final class Collection implements \Countable, \IteratorAggregate
{
    /** @return list<\Corex\Models\Model> */ public function all(): array;
    public function first(): ?\Corex\Models\Model;
    public function isEmpty(): bool;
    public function count(): int;
}
```

**Guarantees**: `toArgs()` never emits `posts_per_page => -1` (cap = `config('query.max', 500)`);
developer values go into `meta_query` (bound), never string-concatenated.

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C1 Model | typed attribute access; read-only; hydratable without DB | FR-001–003 |
| C2 find absent | returns null | FR-005 |
| C2 CRUD | create/update/delete return Model/bool; repo is sole data caller | FR-004, FR-006 |
| C3 driver select | ACF present → AcfFieldDriver; absent → MetaFieldDriver | FR-009 |
| C3 missing field | returns caller default (both drivers) | FR-011 |
| C3 ACF absent | full operation, native meta | FR-010, SC-003 |
| C4 toArgs cap | no `-1`; capped at config max | FR-015, SC-005 |
| C4 binding | filter value lands in meta_query as data | FR-016, SC-006 |
| C4 empty | empty Collection, not null/error | FR-017 |
| Executor eager | belongs-to populated; query count constant in N | FR-018/019, SC-004 |
| Executor absent rel | empty relation, no error | FR-020 |
