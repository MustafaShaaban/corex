---
description: "Task list for the Data Layer (spec 002)"
---

# Tasks: Data Layer — Model, Field Driver, Repository, QueryBuilder

**Input**: Design documents from `specs/002-data-layer/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/data-layer-contracts.md, quickstart.md

**Tests**: REQUIRED (constitution Definition of Done). Every implementation task is preceded by a
failing test (TDD). Pure logic is unit-tested headlessly (Pest + Brain Monkey); WP-touching pieces
(`QueryExecutor`, real CRUD) are integration-tested against `./wp`.

**Guard Gate (per task)**: run `clean-code-guard` + `wp-guard` (production), `test-guard` (tests),
`docs-guard` (docs) clean before any diff ships. ABSPATH guard on every src class file (DECISIONS #20).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: parallelizable (different files, no incomplete dependency)
- All paths are repo-relative from `C:\wamp64\www\corex`.

---

## Phase 1: Setup

- [ ] T001 [P] Create `plugins/corex-core/config/query.php` returning `['max' => 500]` (ABSPATH guard + `return`); create `tests/Unit/Data/` and `tests/Integration/Data/` directories.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: the read-only Model value object — used by US1, US3, US4.

- [ ] T002 [P] Write failing `tests/Unit/Data/ModelTest.php`: typed attribute access via `casts()` (int/bool/date/array); `get()` returns a default for an absent attribute; `id()`; read-only (no setter); hydratable from a plain array with no DB (FR-001–FR-003).
- [ ] T003 Implement `plugins/corex-core/src/Models/Model.php` — abstract read-only value object: `__construct(array)`, `get()`, `id()`, abstract `postType()`/`fields()`, default `relations()`/`casts()`; cast on read. No setters, no `save()`.

**Checkpoint**: Model ready; user stories can begin.

---

## Phase 3: User Story 2 — ACF-optional field access (Priority: P1)

**Goal**: read/write a field through one abstraction that uses ACF when present and native meta when
absent — identical calling code; framework fully operational with ACF uninstalled.

**Independent Test**: read a field with `get_field`/`update_field` stubbed (ACF path) and with only
`get_post_meta`/`update_post_meta` stubbed (native path); confirm both return the value and a missing
field returns the caller default.

> Independent of US1/US3 (operates on entity ids, not Models).

- [ ] T004 [P] [US2] Write failing `tests/Unit/Data/FieldDriverTest.php`: `MetaFieldDriver` get/set via stubbed `get_post_meta`/`update_post_meta` (empty → default); `AcfFieldDriver` via stubbed `get_field`/`update_field` (null → default); `FieldResolver` returns Acf driver when `get_field`+`update_field` exist, else Meta (FR-009–FR-012, SC-002).
- [ ] T005 [P] [US2] `plugins/corex-core/src/Fields/FieldDriver.php` — interface `get(int,string,mixed=null)` / `set(int,string,mixed)`.
- [ ] T006 [US2] `plugins/corex-core/src/Fields/MetaFieldDriver.php` — native meta; empty → caller default (depends on T005).
- [ ] T007 [US2] `plugins/corex-core/src/Fields/AcfFieldDriver.php` — `get_field`/`update_field`; null → caller default (depends on T005).
- [ ] T008 [US2] `plugins/corex-core/src/Fields/FieldResolver.php` — select driver via `function_exists('get_field') && function_exists('update_field')` (FR-010).
- [ ] T009 [US2] Guard gate (clean-code + wp-guard + test-guard) on the US2 diff; validate quickstart Scenario 2.

**Checkpoint**: field access works with and without ACF.

---

## Phase 4: User Story 1 — Read an entity via Model + Repository (Priority: P1)

**Goal**: define a Model + Repository and read an entity by id as a typed Model; the Repository is the
only data-source caller.

**Independent Test**: with `get_post` and the field driver stubbed, fetch an entity by id and confirm a
typed Model with core + declared fields; a missing id returns `null`.

> Depends on Model (Phase 2) + Field driver (US2, for declared-field hydration).

- [ ] T010 [P] [US1] Write failing `tests/Unit/Data/PostRepositoryTest.php`: `find()` hydrates a Model (core post fields + declared fields via the field driver) or returns `null` when absent (FR-005); `create`/`update`/`delete` via stubbed `wp_insert_post`/`wp_update_post`/`wp_delete_post` return the Model/bool (FR-006); the repo is the only data caller (FR-004).
- [ ] T011 [P] [US1] `plugins/corex-core/src/Repositories/RepositoryInterface.php` — `find`/`query`/`create`/`update`/`delete` (contract C2).
- [ ] T012 [US1] `plugins/corex-core/src/Repositories/Hydrator.php` — turn a `WP_Post`/array + field driver into a Model (shared by repository and executor; DRY) (depends on T003, US2).
- [ ] T013 [US1] `plugins/corex-core/src/Repositories/PostRepository.php` — abstract post-backed repo (Model class + post type); implements the interface; sole caller of WP data functions; uses the Hydrator + field driver (depends on T011, T012).
- [ ] T014 [US1] Guard gate on the US1 diff; validate quickstart Scenario 1.

**Checkpoint**: entities read/written as typed Models through the Repository.

---

## Phase 5: User Story 3 — Fluent query with discipline (Priority: P2)

**Goal**: build a filtered/ordered/limited query that returns a `Collection` of Models, capped and with
values bound as data.

**Independent Test**: assert `toArgs()` applies the cap (never `-1`) and binds a filter value into
`meta_query`; run a real query (integration) and get a `Collection`.

> Depends on Model + Field driver + Hydrator.

- [ ] T015 [P] [US3] Write failing `tests/Unit/Data/QueryBuilderTest.php`: `where`/`orderBy`/`limit`/`with` build correct `WP_Query` args; `posts_per_page = min(limit, cap)` and never `-1` (cap from config, default 500); a filter value lands in `meta_query` as data; a metacharacter value is bound literally (FR-013–FR-016, SC-005, SC-006).
- [ ] T016 [P] [US3] Write failing `tests/Unit/Data/CollectionTest.php`: `first()`, `isEmpty()`, `count()`, iteration; empty when nothing matches (FR-017).
- [ ] T017 [US3] `plugins/corex-core/src/Database/Collection.php` — immutable `Countable`/`IteratorAggregate` over `list<Model>`.
- [ ] T018 [US3] `plugins/corex-core/src/Database/QueryBuilder.php` — fluent `where/orderBy/limit/with`; `toArgs()` (cap via `config('query.max', 500)`, values into `meta_query`); `get()`/`first()` delegate to the executor (depends on T003, T017).
- [ ] T019 [US3] `plugins/corex-core/src/Database/QueryExecutor.php` — the only `WP_Query` caller: run args, hydrate each post into a Model (via Hydrator), return a `Collection` (no eager loading yet) (depends on T012, T017).
- [ ] T020 [US3] Write failing `tests/Integration/Data/QueryExecutorTest.php`: real posts → filtered/ordered `Collection<Model>`; empty result is an empty Collection. Make it pass.
- [ ] T021 [US3] Guard gate on the US3 diff; validate quickstart Scenario 3.

**Checkpoint**: fluent, safe, capped querying returns Collections of Models.

---

## Phase 6: User Story 4 — Eager-load a relation, no N+1 (Priority: P2)

**Goal**: `->with('relation')` populates a belongs-to relation on every Model in a bounded query count.

**Independent Test**: seed entities each with a related id; fetch with `->with()`; confirm the relation
is populated and the data-source query count is constant for N=2 and N=50; an absent relation is empty.

> Depends on US3 (QueryExecutor).

- [ ] T022 [P] [US4] Write failing `tests/Unit/Data/EagerLoadTest.php`: belongs-to id-collection produces the distinct foreign-key id set; an entity with an empty foreign key contributes nothing (FR-018, FR-020).
- [ ] T023 [US4] Write failing `tests/Integration/Data/EagerLoadTest.php`: `->with('relation')` populates the relation on every Model; the data-source query count is the same for N=2 and N=50 (no N+1); absent relation → empty (FR-019, SC-004).
- [ ] T024 [US4] Implement eager loading in `QueryExecutor` — collect distinct belongs-to foreign keys across the result set, resolve in one batched query (`post__in`), attach each related Model; absent → empty relation. Guard gate; validate quickstart Scenario 4.

**Checkpoint**: all four stories independently functional.

---

## Phase 7: Wiring & Polish

- [ ] T025 `plugins/corex-core/src/Foundation/DataServiceProvider.php` — bind `FieldDriver` (via `FieldResolver`), a `QueryBuilder` factory, `QueryExecutor`, and the `query.php` config; add `DataServiceProvider::class` to `Boot`'s core provider list.
- [ ] T026 [P] Write `tests/Integration/Data/DataLayerBootTest.php`: the data layer resolves through the container after boot on the real `./wp`; with ACF absent the full unit suite passes (SC-003).
- [ ] T027 [P] Update `plugins/corex-core/README.md` with a Data Layer section (Model/Repository/Field driver/QueryBuilder usage); run docs-guard.
- [ ] T028 Run full `quickstart.md` validation (Scenarios 1–4) against `./wp`; final guard pass (clean-code + wp-guard + test-guard); confirm the headless unit suite is green with no optional plugins (SC-003, SC-007).
- [ ] T029 Update `PROGRESS.md` (spec 002 done) and `DECISIONS.md` (any new choices); verify the Definition-of-Done checklist.

---

## Dependencies & Execution Order

### Phase order
Setup → Foundational (Model) → **US2 (Field driver)** → **US1 (Repository)** → US3 (QueryBuilder) →
US4 (eager) → Wiring/Polish.

### Story dependencies (layered, like spec 001)
- **US2 (P1)**: depends only on Setup — independent (operates on ids). Can run parallel to Model.
- **US1 (P1)**: depends on Model (Phase 2) + US2 (field driver for declared-field hydration).
- **US3 (P2)**: depends on Model + Collection + the Hydrator (US1's T012) + US2.
- **US4 (P2)**: depends on US3 (extends `QueryExecutor`).

### Within each story
- The failing test precedes its implementation.
- Interfaces before concretes; `QueryBuilder` arg-building (unit) before `QueryExecutor` (integration).

### Parallel opportunities
- Setup T001 ∥ Foundational T002.
- US2 (T004–T008) can proceed in parallel with the Model and even US1 interface work.
- Within US2: T005 interface, then T006/T007 drivers in parallel; T008 resolver after.
- US3 test tasks T015/T016 in parallel; `Collection` (T017) independent of `QueryBuilder` (T018).

---

## Implementation Strategy

### MVP
Setup + Foundational (Model) + **US2 (field driver)** + **US1 (Repository)** = a usable read/write data
layer with ACF-optional fields. STOP and validate (Scenarios 1–2). Querying (US3) and eager loading
(US4) are the next increments.

### Incremental delivery
Model → US2 field driver → US1 Repository → US3 QueryBuilder → US4 eager loading → wire the
`DataServiceProvider` and validate end-to-end on `./wp`. Each story is independently testable; run its
guard gate + quickstart scenario before moving on.

---

## Notes
- One task at a time (constitution): after each implementation task, run guards, keep tests green,
  update PROGRESS/DECISIONS, then stop for review.
- `QueryBuilder` stays a pure arg-builder (unit-testable); `QueryExecutor` is the only `WP_Query`
  caller (integration). Do not leak `WP_Query` into the builder.
- ACF is referenced only behind `AcfFieldDriver`/`FieldResolver` via `function_exists` — never a hard
  dependency; the native-meta path is the default (Principle IX).
