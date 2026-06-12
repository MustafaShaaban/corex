# Implementation Plan: Custom tables in the admin (038)
**Branch**: `feature/038-custom-table-admin` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
Make any Corex-managed table appear in **Corex → Data** automatically. A pure `ManagedTable` (name + label +
columns) registered in a `ManagedTables` registry (corex-core) is turned into a `TableDataSource` (corex-config)
that implements the spec-030 `DataSource`; a `TableDataReader` ($wpdb, prepared + bounded) is the only WP
boundary. The Data registry is seeded from `ManagedTables`, so the existing screen + REST + AdminGuard render the
table with **no new UI**. Opt-in only — Corex never enumerates arbitrary tables.

## Technical Context
PHP 8.3; `$wpdb->prepare` with `%i` (identifiers, WP ≥ 6.2) + `%d`; bounded `LIMIT/OFFSET`. Tests: Pest
(`ManagedTable`, `ManagedTables`, `TableDataSource` with a fake reader). Constraints: Principle VII (prepared +
bounded; cap-gated via the existing REST), IX (opt-in, never list arbitrary tables).

## Constitution Check (v1.2.1)
- [x] III/IV — `ManagedTable`/`ManagedTables`/`TableDataSource` pure; the `$wpdb` reader is the thin boundary.
- [x] VII — every query prepared (`%i`/`%d`), bounded `LIMIT`; the Data REST already gates cap + nonce-on-delete.
- [x] IX — opt-in registry; no blanket table scan; namespaced key avoids collisions.
- [x] X — implements spec 038.
- [x] Guard Gate/DoD — wp-guard ($wpdb->prepare, bounded query), clean-code, test-guard; Pest; docs + docs-app.

**Gate**: PASS.

## Design
- `plugins/corex-core/src/Database/Schema/ManagedTable.php` (value: `name`, `label`, `columns: list<{id,label}>`).
- `plugins/corex-core/src/Database/Schema/ManagedTables.php` (registry: `register(ManagedTable)`, `all()`).
- `plugins/corex-config/src/Data/TableDataReader.php` (interface: `page`, `total`, `delete`).
- `plugins/corex-config/src/Data/WpTableDataReader.php` ($wpdb; prepared + bounded; resolves the full table name).
- `plugins/corex-config/src/Data/TableDataSource.php` (implements `DataSource`; key `table-<name>`; pure shaping).
- Wire: `ConfigServiceProvider` seeds the `DataRegistry` with a `TableDataSource` per `ManagedTables->all()`.
- The Migrator/app registers managed tables (a `ManagedTables` singleton in corex-core, populated by the app's
  schema setup); corex-config reads it.

## FR → component map
| FR | Built in |
|---|---|
| FR-001 managed table + registry | `Database/Schema/{ManagedTable,ManagedTables}.php` |
| FR-002 DataSource shaping | `Data/TableDataSource.php` |
| FR-003 prepared/bounded reader | `Data/{TableDataReader,WpTableDataReader}.php` |
| FR-004 auto-seed | `ConfigServiceProvider` (DataRegistry from ManagedTables) |
| FR-005 opt-in + namespaced key | `ManagedTables` (explicit) + `TableDataSource::key()` |
| FR-006 tested | `tests/Unit/Data/{ManagedTableTest,TableDataSourceTest}.php` |

## Project Structure
```text
plugins/corex-core/src/Database/Schema/{ManagedTable,ManagedTables}.php
plugins/corex-config/src/Data/{TableDataReader,WpTableDataReader,TableDataSource}.php
plugins/corex-config/src/ConfigServiceProvider.php (seed from ManagedTables)
tests/Unit/Data/{ManagedTableTest,TableDataSourceTest}.php
docs/en + docs-app (the Data guide: managed tables)
```

## Complexity Tracking
The shaping + registry are pure and tested; the only WP code is a small prepared/bounded `$wpdb` reader. No new
admin UI — it reuses the spec-030 screen + REST. Live admin view is env-gated.
