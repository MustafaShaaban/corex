# Implementation Plan: QueryBuilder complex scenarios & feature-flag configuration (021)

**Branch**: `021-querybuilder-config` (uncommitted on `develop`) | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

> Retrospective plan — maps each FR to the file that already satisfies it and flags drift. No new architecture.

## Summary

Two extensions to the corex-core foundation: (1) the spec-002 `QueryBuilder` gains complex-query methods
(orWhere/whereMeta/whereBetween/metaRelation/whereTax/taxRelation/whereDate/search/orderBy-numeric/paginate),
staying a pure, capped, value-bound arg-builder; (2) a feature-flag layer (`config/features.php` registry +
`FeatureFlags` service + `Config::enabled()` facade) over the spec-001 Config engine, so capabilities flip by
option or env without code.

## Technical Context

**Language/Version**: PHP 8.3. **Primary Dependencies**: the spec-002 data layer (`QueryBuilder`/`QueryExecutor`/
`Collection`), the spec-001 Config engine (`ConfigInterface`, Defaults/Options/Dotenv sources). **Testing**:
Pest. **Project Type**: WP plugin (`corex-core`). **Constraints**: builder is pure (no WP_Query execution);
queries always capped (never `-1`); flags resolve only truthy values; one source of truth shared with Config.

## Constitution Check (v1.2.0)

- [x] **III/IV (layering + DI)** — PASS. `QueryBuilder` pure arg-builder; `FeatureFlags` injected
  `ConfigInterface`; both bound in `CoreServiceProvider`. No `new` of a service in a method.
- [x] **VII (security)** — PASS. Every meta/tax/date value bound as data in the args; per-page always capped
  (no unbounded `-1`); execution confined to `QueryExecutor` (spec 002).
- [x] **IX (optional dep)** — PASS. Flags gate optional capabilities (mail queue / Woo kit / React admin)
  without making any of them a hard dependency.
- [x] **X (spec)** — reconciled by this retrospective spec.
- [x] **Guard Gate / DoD** — PARTIAL. clean-code self-review at delivery; a formal full re-run is **P2**.
  Tests: QueryBuilderTest (11 new complex-query cases + caps) + FeatureFlagsTest (17), green. Docs: corex-core
  README "Complex queries" + "Feature flags".

**Gate**: PASS (P2 formal guard re-run tracked; one clean-code finding → P3, below).

## FR → implementation map

| FR | Satisfied by |
|---|---|
| FR-001 OR/typed-meta/between/relation | `plugins/corex-core/src/Database/QueryBuilder.php` (`orWhere`, `whereMeta`, `whereBetween`, `metaRelation`) |
| FR-002 tax + date | `QueryBuilder::{whereTax,taxRelation,whereDate}` |
| FR-003 search + numeric order | `QueryBuilder::{search,orderBy(...,numeric)}` (→ `meta_value_num` + `meta_key`) |
| FR-004 pagination | `QueryBuilder::paginate` (cap per-page, `paged`, enable found-rows) |
| FR-005 cap + purity | `QueryBuilder::{limit,toArgs}` (cap, `no_found_rows` default true; no execution) |
| FR-006 registry + service | `plugins/corex-core/config/features.php` + `src/Support/Config/FeatureFlags.php` (`enabled`/`disabled`/`all`, truthy coercion) |
| FR-007 layered + facade | flags read via `ConfigInterface` (`corex_features_<flag>` option / `FEATURES_<FLAG>` env); `src/Support/Facades/Config.php` (`enabled()`) |
| FR-008 wiring + Pro split | `CoreServiceProvider` binds `FeatureFlags`; `features.pro` gates the edition |

**Drift found:** none material. Clean-code audit noted `QueryBuilder::orderBy($field,$dir,bool $numeric)` — a
boolean flag arg; split into `orderByNumeric()` → tracked **P3** (finding #1).

## Project Structure (already implemented)

```text
plugins/corex-core/
├── config/features.php
└── src/
    ├── Database/QueryBuilder.php
    ├── Support/Config/FeatureFlags.php
    └── Support/Facades/Config.php   (enabled())
tests/Unit/Data/QueryBuilderTest.php
tests/Unit/Foundation/FeatureFlagsTest.php
```

## Complexity Tracking

No unjustified violations. P2 (guards) and P3 (`orderBy` boolean-flag split) are remediation, not new
complexity. Custom-table queries deliberately stay at the spec-011 `TableRepository` boundary.
