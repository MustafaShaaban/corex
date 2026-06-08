# Phase 0 Research: Data Layer

All decisions resolve the Technical Context. No NEEDS CLARIFICATION remain (spec clarified 2026-06-08).

## R1 — ACF detection & driver selection

**Decision**: `FieldResolver` selects `AcfFieldDriver` when **both** `get_field` and `update_field`
exist (`function_exists`), otherwise `MetaFieldDriver`. Resolution happens when the driver is first
requested from the container and is safe to re-evaluate (no boot-time snapshot that could go stale if
ACF activates later).

**Rationale**: `function_exists` is the canonical, dependency-free ACF probe (Principle IX); checking
both read and write functions avoids a half-present ACF. The native driver is the default, so absence
is the normal path, not an error path.

**Alternatives**: `class_exists('ACF')` (less precise than the functions actually called);
`is_plugin_active` (admin-only, requires loading plugin.php) — rejected.

## R2 — QueryBuilder → WP_Query arg mapping + capping

**Decision**: `QueryBuilder` accumulates fluent calls into a `WP_Query` args array:
- `where(attr, value)` on a core field (e.g. `post_status`, `post_parent`) → the matching `WP_Query`
  key; on a declared custom field → a `meta_query` clause (`['key' => <meta key>, 'value' => <bound
  value>, 'compare' => '=']`).
- `orderBy(field, dir)` → `orderby`/`order` (meta fields → `meta_value` + `meta_key`).
- `limit(n)` → `posts_per_page = min(n, cap)`; no explicit limit → `posts_per_page = cap`.
- The cap is `config('query.max', 500)`; `posts_per_page => -1` is never emitted (FR-015, SC-005).
- `'no_found_rows' => true` unless pagination is requested; `'fields'` left default (need full posts to
  hydrate) — relations use `'fields' => 'ids'` for the batch step.

**Rationale**: `WP_Query` already prepares meta/`tax` values (no manual SQL); routing developer values
through `meta_query`/args means they are bound as data, not concatenated (FR-016, SC-006). Building a
plain args array keeps the builder a pure, unit-testable transformation.

**Alternatives**: raw `$wpdb` with `prepare()` (more surface, manual escaping; deferred to custom-table
support); building SQL strings — rejected (injection surface, violates "wrapper not ORM").

## R3 — Eager loading (belongs-to), no N+1

**Decision**: `->with('relation')` records the relation name. After the main query hydrates the parent
Models, `QueryExecutor` reads each parent's belongs-to foreign-key field (the stored related post id),
collects the distinct ids, fetches them in **one** `WP_Query` (`post__in`, `posts_per_page = count`),
hydrates the related Models, and attaches each to its parent. Parents with no/absent related id get an
empty relation (FR-020).

**Rationale**: Two queries regardless of N (SC-004); explicit `->with()` keeps queries visible
(FRAMEWORK §7 "no hidden queries"). Distinct-id collection avoids refetching duplicates.

**Alternatives**: transparent lazy loading (the classic N+1 trap) — explicitly rejected by §7.

## R4 — Field read/write semantics per driver

**Decision** (per clarification): a field is referenced by a single **logical name**; the Model maps it
to a key.
- **AcfFieldDriver**: read `get_field($name, $postId)` (ACF return-format coercion + complex types);
  write `update_field($name, $value, $postId)`. Missing field → `get_field` returns null → return the
  caller default.
- **MetaFieldDriver**: read `get_post_meta($postId, $key, true)`; write `update_post_meta($postId, $key,
  $value)`. Missing meta → empty string → return the caller default.

**Rationale**: matches FRAMEWORK §6 (ACF present → `get_field`; absent → `get_post_meta`); identical
calling code (FR-008/009/012). Default-on-missing is enforced in the driver wrapper, not the caller
(FR-011).

**Note**: spec 002 covers field **access**, not field/post-type **registration** (ACF Local JSON,
`register_post_meta`, editor UI) — that is a separate later concern (§6 registration half).

## R5 — Collection shape

**Decision**: a minimal immutable `Corex\Database\Collection` implementing `Countable` and
`IteratorAggregate` over `list<Model>`, with `first(): ?Model`, `isEmpty(): bool`, `all(): array`.

**Rationale**: the spec lists Collection as a Key Entity; a thin typed wrapper gives ergonomic, empty-
safe results (FR-017) without an over-built collection library (YAGNI — add `map`/`filter` when a caller
needs them).

**Alternatives**: return plain `array` (loses empty-safe ergonomics + the named entity); a full
collection package (over-built).

## R6 — Testing strategy (args/executor split)

**Decision**: unit-test the **pure** pieces headlessly — `QueryBuilder` arg-building (cap applied,
meta_query binding), eager-load id-collection, `Model` hydration, both `FieldDriver`s (Brain Monkey
stubs of `get_field`/`get_post_meta`/`update_*`), and `Collection`. Integration-test the **WP-touching**
pieces against `./wp` — `QueryExecutor` real query + relation query-count (SC-004), real post CRUD via
`PostRepository`, and the ACF-absent full-suite pass (SC-003).

**Rationale**: `WP_Query` is a class (awkward to stub); isolating it behind `QueryExecutor` keeps the
bulk of logic provable without WordPress (FR-022, SC-007) while a thin integration layer covers the real
query behavior stubs cannot.

## Summary

| Concern | Choice |
|---|---|
| ACF detection | `function_exists('get_field')&&('update_field')` → AcfFieldDriver, else MetaFieldDriver |
| Query backing | `WP_Query` args array, capped at `config('query.max',500)`, values via `meta_query` |
| Eager load | `->with()` batched: 2 queries for belongs-to, distinct `post__in` |
| Field I/O | ACF `get_field`/`update_field` vs native `get_post_meta`/`update_post_meta`; default-on-missing |
| Collection | minimal immutable `Collection` (Countable/IteratorAggregate) |
| Tests | pure logic unit (Brain Monkey) + thin WP integration (`QueryExecutor`, CRUD, ACF-absent) |

No new Composer dependencies.
