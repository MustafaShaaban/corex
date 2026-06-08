# Implementation Plan: Data Layer — Model, Field Driver, Repository, QueryBuilder

**Branch**: `002-data-layer` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-data-layer/spec.md`

## Summary

Add the data-access layer on top of the corex-core foundation: a read-only **Model** (typed value
object), a **Repository** as the single data-source layer (CRUD over posts + meta), an **ACF-optional
Field driver** behind an interface (ACF API when present, native meta when absent — same calling
code), and a fluent **QueryBuilder** that produces safe, capped queries and eager-loads a belongs-to
relation in a bounded query count (no N+1). Registered through a `ServiceProvider`; everything injected.
No presentation, no controllers. This is the read/write/query half of FRAMEWORK §6–§7; field/post-type
*registration* (ACF Local JSON, `register_post_meta`, editor UI) is a separate later concern.

## Technical Context

**Language/Version**: PHP 8.3 (`declare(strict_types=1)`).

**Primary Dependencies**:
- corex-core (spec 001): container, `ServiceProvider`, `Config`, `BootLogger`.
- WordPress data APIs: `WP_Query`, `get_post`/`wp_insert_post`/`wp_update_post`/`wp_delete_post`,
  `get_post_meta`/`update_post_meta`/`delete_post_meta`.
- ACF functions (`get_field`/`update_field`) — detected at runtime via `function_exists`, **never** a
  Composer/hard dependency (Principle IX).
- No new third-party packages.

**Storage**: Existing WordPress storage — posts + post meta. No custom tables.

**Testing**: Pest + Brain Monkey for headless unit tests (stub `get_field`/`get_post_meta`/etc.); a thin
integration suite against `./wp` for real CRUD and real query/eager-load counts.

**Target Platform**: WordPress ≥ 7.0, PHP ≥ 8.3.

**Project Type**: corex-core data layer (PSR-4 `Corex\` → `plugins/corex-core/src/`).

**Performance Goals**: Eager loading is O(1) extra queries per relation (belongs-to = 2 queries total,
not N+1, SC-004); every list query is capped (default 500, `query.max`) — never `posts_per_page => -1`.

**Constraints**: ACF absent must be fully functional (SC-003); values bound as data, never concatenated
(SC-006); Models carry no persistence (read-only); Repository is the only data-source caller.

**Scale/Scope**: v1 = post-backed entities only; belongs-to relation only; contracts shaped to extend to
taxonomy/user/custom-table sources and has-many/taxonomy relations later. No NEEDS CLARIFICATION (spec
clarified 2026-06-08).

## Constitution Check

*GATE: Must pass before Phase 0. Re-check after Phase 1.*

Derived from `.specify/memory/constitution.md` (v1.1.0).

- [x] **I. Theme is a skin** — PASS: all code in `corex-core`; no presentation.
- [x] **II. Plugins boot themselves** — PASS: registered via a `ServiceProvider` on the existing
  `plugins_loaded` boot; works in all contexts; no theme dependency.
- [x] **III. Thin controllers, fat services** — PASS: the **Repository is the only data-source layer**;
  Models are read-only value objects (no query logic, no `save()`); no controllers/services added here.
- [x] **IV. Everything injected** — PASS: FieldResolver/FieldDriver, QueryBuilder, repositories resolve
  through the container; no inline `new` of dependencies.
- [x] **V. Runtime tokens** — N/A (no styling).
- [x] **VI. Conditional assets** — N/A (no assets).
- [x] **VII. Declarative security** — query values are bound as data, never concatenated (FR-016); no
  request handling here, so route middleware is N/A.
- [x] **VIII. RTL-first** — N/A (no UI).
- [x] **IX. No optional dep is hard** — **PASS (headline)**: ACF lives behind `FieldDriver`, selected by
  `FieldResolver` via `function_exists`; the native-meta driver is the default; SC-003 proves full
  operation with ACF/Woo/Polylang absent.
- [x] **X. Spec is source of truth** — PASS: traces to spec 002 (clarified); implements FRAMEWORK §6–§7.
- [x] **Guard Gate + Definition of Done** — every task runs `clean-code-guard` + `wp-guard` (and
  `test-guard`/`docs-guard` as applicable) clean; Pest tests; PROGRESS/DECISIONS updated.

**Result: PASS** — no violations; Complexity Tracking empty.

## Project Structure

### Documentation (this feature)

```text
specs/002-data-layer/
├── plan.md · spec.md · research.md · data-model.md · quickstart.md
├── contracts/data-layer-contracts.md
└── checklists/requirements.md
```

### Source Code (repository root)

```text
plugins/corex-core/src/
├── Models/
│   └── Model.php                 # abstract read-only value object: attributes(), postType(), fields(), relations()
├── Repositories/
│   ├── RepositoryInterface.php   # find / query / create / update / delete contract
│   └── PostRepository.php        # abstract post-backed repo: hydrates Models, sole data-source caller
├── Fields/
│   ├── FieldDriver.php           # interface: get(id,key,default) / set(id,key,value)
│   ├── FieldResolver.php         # picks the active driver by ACF presence (function_exists)
│   ├── MetaFieldDriver.php       # native get_post_meta / update_post_meta (default)
│   └── AcfFieldDriver.php        # get_field / update_field when ACF is active
├── Database/
│   ├── QueryBuilder.php          # fluent where/orderBy/limit/with → safe, capped WP_Query args
│   ├── QueryExecutor.php         # runs the built args via WP_Query (the only WP_Query caller)
│   └── Collection.php            # immutable list<Model>: first(), isEmpty(), count, iterate
└── Foundation/
    └── DataServiceProvider.php   # binds FieldDriver (via resolver), QueryBuilder, repositories; added to Boot

plugins/corex-core/config/
└── query.php                     # defaults: ['max' => 500]

tests/
├── Unit/Data/                    # Model hydration, FieldDrivers (Brain Monkey), QueryBuilder arg-building, eager-load batching, Collection
└── Integration/                  # real post CRUD, real query + relation query-count
```

**Structure Decision**: Fills the already-scaffolded `Models/`, `Repositories/`, `Fields/`, `Database/`
namespaces from FRAMEWORK §4. `DataServiceProvider` joins `CoreServiceProvider` in `Boot`'s core
provider list (add-on/config provider discovery remains the planned seam from spec 001).

## Key design decisions (testability + scalability)

1. **QueryBuilder builds args; QueryExecutor runs them.** `QueryBuilder` is a pure transformation
   (chained calls → a `WP_Query` args array, cap applied, values bound into `meta_query`) — unit-testable
   with no WordPress. `QueryExecutor` is the single place that instantiates `WP_Query` — integration-
   tested. This keeps the cap/binding/eager-load logic provable headlessly (FR-022, SC-005, SC-006).
2. **Field driver behind an interface, resolved at runtime.** `FieldResolver` returns `AcfFieldDriver`
   when `get_field`+`update_field` exist, else `MetaFieldDriver`. Calling code depends on `FieldDriver`
   only — ACF is an enhancement (Principle IX).
3. **Models are read-only.** Hydrated by the Repository from core post fields + declared fields (via the
   driver). No setters, no persistence — writes return a fresh Model from the Repository.
4. **Eager loading is explicit and batched.** `->with('relation')` makes the executor collect the
   belongs-to foreign keys across the result set and resolve them in one extra query, attaching each
   related Model — 2 queries, not N+1 (FRAMEWORK §7).
5. **Extensible contracts.** `RepositoryInterface` and the relation/driver shapes are designed so
   taxonomy/user/custom-table repositories and has-many/taxonomy relations are added without changing
   calling code.

## Phase 0 — Research

See [research.md](./research.md): ACF detection strategy, WP_Query arg mapping + capping, eager-load
batching, field write semantics, Collection shape, and the args/executor split for testability. No open
NEEDS CLARIFICATION.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md) — Model, Repository, FieldDriver/Resolver, QueryBuilder, Collection,
  Relation: fields, relationships, lifecycle, error paths.
- [contracts/data-layer-contracts.md](./contracts/data-layer-contracts.md) — the PHP public API each
  exposes + a contract→test matrix.
- [quickstart.md](./quickstart.md) — runnable validation scenarios mapped to the success criteria.

## Complexity Tracking

No constitution violations — section intentionally empty.
