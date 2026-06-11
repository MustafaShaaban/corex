# Tasks: QueryBuilder complex scenarios & feature-flag configuration (021)

**Retrospective spec** — the implementation exists, is unit-tested, and (for flags) verified on real WP
(option set → on; deleted → off). These are **reconciliation/verification** tasks: confirm each FR against the
mapped file/behaviour (most already satisfied, marked `[x]`), plus the tracked debts (a formal Guard Gate
re-run, remediation **P2**; the `orderBy` boolean-flag split, remediation **P3**). The FR→file map is in
`plan.md`.

**No new implementation work** beyond the tracked P2/P3 debts — flag any mismatch found as a defect rather
than scope.

## Phase 1: Setup (verification context)

- [x] T001 Confirm the data-layer base (spec 002) is present: `Database/{QueryBuilder,QueryExecutor,Collection}`, `Repositories/PostRepository`, `Models/Model`.
- [x] T002 Confirm the Config base (spec 001) is present: `Support/Config/{Repository,Sources/{Defaults,Options,Dotenv}}` + `ConfigInterface`.

## Phase 2: Foundational (no shared blocker — the two stories are independent)

- [x] T003 Confirm `QueryBuilder` remains a pure arg-builder (`toArgs()` returns args; execution stays in `QueryExecutor`) and that `config/features.php` is the flag registry read through `ConfigInterface`.

## Phase 3: User Story 1 — Express complex queries fluently and safely (P1) 🎯 MVP

**Goal**: real-world queries (OR meta, typed meta, ranges, tax, date, search, numeric order, pagination) build correctly + capped, no raw WP_Query.
**Independent test**: assert each scenario's args array headlessly, then compose all.

- [x] T004 [US1] Verify FR-001: `where`/`orWhere` + `metaRelation` produce a `meta_query` with the right AND/OR relation, values bound as data; `whereMeta` adds a typed condition; `whereBetween` a NUMERIC range (`tests/Unit/Data/QueryBuilderTest.php` — "combines multiple meta…", "switches the meta relation to OR…", "adds a raw-meta condition…", "builds a numeric BETWEEN range…").
- [x] T005 [US1] Verify FR-002: `whereTax`/`taxRelation` build a `tax_query` with relation; `whereDate` builds a `date_query` (QueryBuilderTest — "builds a taxonomy query, and an OR relation…", "restricts by a post-date range").
- [x] T006 [US1] Verify FR-003: `search` sets `s`; `orderBy(field, dir, numeric)` maps a declared field to `meta_value`/`meta_value_num` + `meta_key` (QueryBuilderTest — "passes a search term through…", "maps orderBy on a declared field…", "orders by a declared field numerically…").
- [x] T007 [US1] Verify FR-004: `paginate` caps per-page, sets `paged`, enables found-rows (QueryBuilderTest — "paginates: caps per-page, sets the page, and enables found-rows").
- [x] T008 [US1] Verify FR-005 + SC-002/SC-003: `limit` caps `posts_per_page` and never emits `-1`; found-rows defaults disabled; a composed meta+tax+date+search+order+pagination query yields one coherent args array (QueryBuilderTest — "caps posts_per_page and never emits -1", "sets the post type and disables found-rows", "composes meta, tax, date, search, order, and pagination into one args array").

## Phase 4: User Story 2 — Flip capabilities per-site without code (P1)

**Goal**: a flag flips on/off by option or env, read through one service shared with Config.
**Independent test**: resolve `enabled`/`disabled`/`all` against the registry, an explicit default, and a layered override.

- [x] T009 [US2] Verify FR-006: `config/features.php` registers known flags with defaults; `FeatureFlags::{enabled,disabled,all}` resolve them, coercing only truthy values (`tests/Unit/Foundation/FeatureFlagsTest.php` — "treats only truthy values as enabled", "is off when the flag is absent", "honours an explicit default", "reports every registered flag via all()").
- [x] T010 [US2] Verify FR-007 + SC-004/SC-005: an option `corex_features_<flag>` / env `FEATURES_<FLAG>` overrides the registry default (FeatureFlagsTest — "follows the layered Config value"); `Config::enabled()` returns the same boolean as the service; flip verified on real WP (option set → on, deleted → off).
- [x] T011 [US2] Verify FR-008: `CoreServiceProvider` binds `FeatureFlags` framework-wide; the Free/Pro split rides on `features.pro`.

## Phase 5: Polish & cross-cutting

- [ ] T012 [P] **(P2)** Run the Guard Gate formally on this feature's diff: `clean-code-guard` (builder + flags) + `wp-guard` (query arg-binding / caps) + `test-guard` (the new Pest cases) + `docs-guard` (corex-core README "Complex queries" + "Feature flags"); fix any reported violation. _Tracked as remediation P2._
- [x] T013 **(P3 — DONE 2026-06-11)** Split `QueryBuilder::orderBy($field,$dir,bool $numeric)` into `orderBy()` + `orderByNumeric()` (no boolean flag arg); test + README + queries-guide callers updated; 269 unit green. DECISIONS #57.
- [x] T014 Confirm docs: corex-core README "Complex queries" table + composed example and "Feature flags"; DECISIONS #47 (queries) + #48 (flags) record the approach; `.env.example` has the FEATURES_* section; PROGRESS reflects completion.

## Dependencies

- The two stories are independent (different files: `Database/QueryBuilder` vs `config/features.php` +
  `FeatureFlags`). Each is independently verifiable.
- T012 (P2) and T013 (P3) are the only **open** tasks; both are already tracked as remediation items.

## Implementation strategy

This spec is retrospective: US1 (complex queries) and US2 (feature flags) are already delivered, unit-tested
(11 + 17 cases), and the flag flip verified on real WP. The remaining work is the two tracked debts (T012 → P2;
T013 → P3) — **not** new feature work.

## Parallel opportunities

- T012 [P] (guard run) is independent of T013 (orderBy split).
- US1 (QueryBuilder) and US2 (FeatureFlags) verification touch different files and can run in parallel.
