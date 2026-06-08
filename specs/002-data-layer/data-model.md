# Phase 1 Data Model: Data Layer

Runtime objects and their relationships. No new database tables — entities map to posts + post meta.

## Entity map

```text
Repository (sole data-source layer)
  ├─ find/query ──> Model[]  (read-only value objects)
  │                   └─ fields read via ──> FieldDriver (AcfFieldDriver | MetaFieldDriver)
  │                                            ▲ chosen by FieldResolver (ACF presence)
  ├─ query() ─────> QueryBuilder ──build args──> QueryExecutor ──WP_Query──> Collection<Model>
  │                       └─ with('rel') ──batched belongs-to──> related Model attached
  └─ create/update/delete ──> Model (writes go only through here)
```

## 1. Model  *(spec: Model; FR-001–FR-003)*

- **Role**: read-only value object for one entity.
- **Class declares**: `postType(): string`; `fields(): array` (logical name → meta/ACF key);
  `relations(): array` (logical name → `['type' => 'belongsTo', 'model' => Class, 'foreignKey' => field]`);
  `casts(): array` (attribute → type: `int`/`bool`/`DateTimeImmutable`/`string`/`array`).
- **Instance holds**: `array $attributes` (core post fields + resolved declared fields + attached
  relations). Read access via typed getters/`__get`; **no setters, no `save()`** (FR-001).
- **Invariants**: constructible from an array by its Repository, usable in tests without a DB (FR-003);
  attributes exposed with their cast types (FR-002).

## 2. Repository  *(spec: Repository; FR-004–FR-007)*

- **Conforms to**: `RepositoryInterface` — `find(int $id): ?Model`, `query(): QueryBuilder`,
  `create(array $attributes): Model`, `update(int $id, array $attributes): Model`,
  `delete(int $id): bool`.
- **PostRepository (abstract)**: bound to a Model class + post type. `find()` → `get_post` →
  hydrate (core fields + declared fields via `FieldDriver`) → Model, or `null` if absent (FR-005).
  Writes via `wp_insert_post`/`wp_update_post`/`wp_delete_post` + `FieldDriver::set` for declared fields,
  returning the resulting Model (FR-006). **The only layer that calls WP data functions** (FR-004).
- **Resolved from the container** with `FieldDriver` + `QueryBuilder` factory injected (FR-007).

## 3. FieldDriver + FieldResolver  *(spec: Field driver; FR-008–FR-012)*

- **FieldDriver (interface)**: `get(int $entityId, string $key, mixed $default = null): mixed`;
  `set(int $entityId, string $key, mixed $value): void`.
- **MetaFieldDriver** (default): `get_post_meta($id, $key, true)` (empty → `$default`);
  `update_post_meta`.
- **AcfFieldDriver**: `get_field($key, $id)` (null → `$default`, ACF coercion); `update_field`.
- **FieldResolver**: returns `AcfFieldDriver` when `get_field`+`update_field` exist, else
  `MetaFieldDriver`. Bound so `FieldDriver` resolves to the active driver (FR-009, FR-010).
- **Invariants**: identical calling code both ways (FR-008); missing field → caller default (FR-011);
  framework fully operational with ACF absent (FR-010, SC-003).

## 4. QueryBuilder  *(spec: QueryBuilder; FR-013–FR-017)*

- **Fluent API**: `where(string $field, mixed $value, string $compare = '=')`, `orderBy(string $field,
  string $direction = 'ASC')`, `limit(int $n)`, `with(string $relation)`, `get(): Collection`,
  `first(): ?Model`.
- **State**: accumulated core-field conditions, `meta_query` clauses, order, limit, requested relations.
- **Behavior**: `toArgs(): array` produces a `WP_Query` args array — values placed into `meta_query`
  (bound as data, FR-016); `posts_per_page = min(limit ?? cap, cap)` with `cap = config('query.max',
  500)`; never `-1` (FR-015). `get()` hands args to `QueryExecutor`, wraps results in a `Collection`
  (empty when none, FR-017).
- **Invariants**: a wrapper, no SQL dialect (FR-014); unbounded request capped (SC-005); special chars
  bound literally (SC-006).

## 5. QueryExecutor  *(the only WP_Query caller)*

- **Behavior**: `run(array $args, string $modelClass, array $relations): Collection` — instantiate one
  `WP_Query`, hydrate each post into a Model, then for each requested belongs-to relation collect the
  distinct foreign-key ids and resolve them in **one** extra query, attaching related Models (FR-018,
  FR-019). Absent relation → empty attachment (FR-020).
- **Invariants**: query count for a belongs-to eager load is constant in N (SC-004).

## 6. Collection  *(spec: Collection)*

- **Role**: immutable ordered set of Models. Implements `Countable`, `IteratorAggregate`.
- **Methods**: `all(): array`, `first(): ?Model`, `isEmpty(): bool`, `count()`.
- **Invariant**: empty (not null/error) when nothing matches (FR-017).

## 7. Relation  *(spec: Relation)*

- **Shape (v1 belongs-to)**: `['type' => 'belongsTo', 'model' => RelatedModel::class, 'foreignKey' =>
  '<field holding related post id>']` declared in the Model's `relations()`.
- **Resolution**: eager via `->with()` (batched). A parent whose foreign key is empty/points nowhere
  reports the relation as empty (FR-020).
- **Extensibility**: the shape leaves room for `hasMany` / `belongsToMany` (taxonomy) later without
  changing `with()`'s call site.

## 8. DataServiceProvider

- **Role**: register the data layer. Binds `FieldDriver` (via `FieldResolver`), a `QueryBuilder`
  factory, `QueryExecutor`, and concrete repositories; ships `config/query.php` defaults. Added to
  `Boot`'s core provider list.

## Lifecycle & error paths

| Trigger | Handling | FR |
|---|---|---|
| `find()` on absent id | returns `null` | FR-005 |
| read missing field | returns caller default | FR-011 |
| ACF absent | MetaFieldDriver used; everything works | FR-010, SC-003 |
| unbounded list | capped at `query.max` (500) | FR-015, SC-005 |
| special chars in filter | bound via `meta_query` (literal) | FR-016, SC-006 |
| query matches nothing | empty `Collection` | FR-017 |
| eager-load absent relation | empty relation on that Model | FR-020 |
| eager load over N parents | one extra query (constant in N) | FR-019, SC-004 |
