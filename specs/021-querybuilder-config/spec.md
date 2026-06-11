# Feature Specification: QueryBuilder complex scenarios & feature-flag configuration

**Feature Branch**: `021-querybuilder-config`

**Created**: 2026-06-11

**Status**: Draft (RETROSPECTIVE — documents delivered, tested, real-WP-verified code; items 6 + 7 of the "Finish Corex" initiative; reconciled to the implementation in `plugins/corex-core`)

**Input**: "The QueryBuilder can express real-world queries — OR clauses, typed meta, ranges, taxonomies, dates, search, numeric ordering, pagination — without a developer hand-writing WP_Query args; and the framework has a feature-flag layer so capabilities flip per-site by option or env without code."

> **Retrospective note.** Written after the code shipped, to restore spec-first compliance (Principle X). It
> extends the spec-002 data layer (`QueryBuilder` → `QueryExecutor`) with complex-query methods, and the
> spec-001 `Config` engine with a feature-flag layer. Requirements describe the existing `plugins/corex-core`
> code.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Express complex queries fluently and safely (Priority: P1)

A developer composes a non-trivial query — multiple meta conditions with OR, a numeric range, a taxonomy
filter, a date window, a search term, numeric ordering, and pagination — through the fluent builder, and gets a
correct, capped, value-bound `WP_Query` args array without touching raw WP_Query.

**Why this priority**: A data layer that only does single-field AND queries forces developers back to raw
WP_Query for anything real, defeating the abstraction and its safety guarantees.

**Independent Test**: Build each scenario; assert the produced args array (pure, headless) — meta_query
relation, typed values bound as data, tax_query, date_query, `s`, `meta_value_num` ordering, capped
`posts_per_page`, paged + found-rows for pagination — then compose them all into one.

**Acceptance Scenarios**:

1. **Given** several meta conditions, **When** added via `where`/`orWhere` + `metaRelation`, **Then** the
   args carry a `meta_query` with the right AND/OR relation and each value bound as data.
2. **Given** a numeric range, **When** `whereBetween(field, min, max)`, **Then** a NUMERIC `BETWEEN` meta
   clause is produced.
3. **Given** taxonomy and date constraints, **When** `whereTax`/`taxRelation` and `whereDate(after, before)`,
   **Then** a `tax_query` (with relation) and a `date_query` are produced.
4. **Given** a search term and an ordering, **When** `search(term)` and `orderBy(field, dir, numeric)`,
   **Then** the args set `s` and order by `meta_value`/`meta_value_num` + `meta_key`.
5. **Given** pagination, **When** `paginate(perPage, page)`, **Then** `posts_per_page` is capped, `paged` is
   set, and found-rows is enabled (so totals work); otherwise found-rows stays disabled.

---

### User Story 2 - Flip capabilities per-site without code (Priority: P1)

An operator (or env) enables or disables a framework capability — Pro features, the mail queue, the React
admin, the Woo kit — by setting an option or env var, and the framework reads that decision through one
feature-flag service layered over the existing Config engine.

**Why this priority**: Free/Pro splits, deferred capabilities, and optional integrations all need a single,
consistent on/off switch that doesn't require redeploying code.

**Independent Test**: Register flags in the registry; resolve `enabled`/`disabled`/`all` with the registry
default, an explicit default, and a layered override; confirm only truthy values enable.

**Acceptance Scenarios**:

1. **Given** the flag registry (`config/features.php`), **When** a flag is absent, **Then** `enabled()`
   returns its default (false), and `all()` reports every registered flag's resolved state.
2. **Given** an option `corex_features_<flag>` or env `FEATURES_<FLAG>`, **When** set truthy
   (`1/true/on/yes`), **Then** the flag is enabled (override beats the registry default); deleted → off.
3. **Given** `Config::enabled('<flag>')`, **When** called, **Then** it returns the same resolved boolean as
   the `FeatureFlags` service (one source of truth).

### Edge Cases

- `QueryBuilder` stays backward compatible: a single AND clause remains a bare `meta_query` list.
- `limit`/`paginate` always cap `posts_per_page` and never emit `-1` (no unbounded queries).
- Eager loading (`with`) batches `post__in` — no N+1.
- Non-truthy flag values (`0`, `false`, `off`, `""`) resolve to disabled.
- Custom-table joins are out of scope here — that is the spec-011 `TableRepository` boundary, not faked
  through WP_Query.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `QueryBuilder` MUST support `orWhere`, `whereMeta` (typed), `whereBetween` (NUMERIC range), and
  `metaRelation`, producing a correct `meta_query` with values bound as data.
- **FR-002**: `QueryBuilder` MUST support `whereTax` + `taxRelation` (tax_query) and `whereDate` (date_query).
- **FR-003**: `QueryBuilder` MUST support `search` (the `s` arg) and `orderBy(field, direction, numeric)`
  mapping a declared field to `meta_value`/`meta_value_num` + `meta_key`.
- **FR-004**: `QueryBuilder::paginate(perPage, page)` MUST cap per-page, set `paged`, and enable found-rows;
  otherwise found-rows MUST stay disabled.
- **FR-005**: All builder methods MUST remain backward compatible and MUST never emit an unbounded query
  (`posts_per_page` always capped, never `-1`); the builder stays a pure arg-builder (no WP_Query execution).
- **FR-006**: A `config/features.php` registry MUST declare known flags with defaults; a `FeatureFlags`
  service MUST expose `enabled`/`disabled`/`all`, coercing only truthy values to enabled.
- **FR-007**: Flags MUST layer through the Config engine so each flips by option (`corex_features_<flag>`) or
  env (`FEATURES_<FLAG>`) without code; `Config::enabled()` MUST return the same result as `FeatureFlags`.
- **FR-008**: The flag layer MUST be bound in the core service provider so it is available framework-wide; the
  Free/Pro split rides on `features.pro`.

### Key Entities

- **QueryBuilder**: fluent, pure arg-builder producing a capped, value-bound `WP_Query` args array.
- **Feature flag registry**: `config/features.php` — the list of known flags + defaults.
- **FeatureFlags**: the service resolving a flag through Config (registry → option → env).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Each complex-query scenario (OR meta, typed meta, BETWEEN, tax, date, search, numeric order,
  pagination) produces the correct args array, verified headlessly.
- **SC-002**: A composed query using meta + tax + date + search + order + pagination yields one coherent args
  array.
- **SC-003**: A query never emits `posts_per_page = -1`; per-page is always capped.
- **SC-004**: A feature flag flips on/off by option or env at runtime with no code change (verified on real WP).
- **SC-005**: `Config::enabled()` and `FeatureFlags::enabled()` agree for every flag.

## Assumptions

- Built on the spec-002 data layer (`QueryBuilder`/`QueryExecutor`/`Collection`) and the spec-001 Config engine
  (Defaults → Options → Dotenv precedence).
- Custom-table queries are the spec-011 `TableRepository` boundary, intentionally not routed through the
  builder.
