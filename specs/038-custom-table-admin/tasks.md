# Tasks: Custom tables in the admin (038)

**Forward, TDD-ordered.** `ManagedTable`/`ManagedTables`/`TableDataSource` are the headless core (Pest); the
`$wpdb` reader is the thin boundary. Reuses the spec-030 Data screen + REST. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the spec-030 `DataSource`/`DataRegistry`/Data REST + the spec-002 `Table`/`Migrator` are the integration points.

## Phase 2: US1 — managed table registry + data source (P1) 🎯 MVP
- [x] T002 [US1] Write `tests/Unit/Data/ManagedTableTest.php` (RED): a `ManagedTable` exposes name/label/columns; `ManagedTables` registers + lists them.
- [x] T003 [US1] Implement `Database/Schema/{ManagedTable,ManagedTables}.php` to pass T002.
- [x] T004 [US1] Write `tests/Unit/Data/TableDataSourceTest.php` (RED): `key()` = `table-<name>`, `columns()` from the managed table, `rows()`/`total()`/`delete()` delegate to a fake reader; unknown column ignored.
- [x] T005 [US1] Implement `Data/{TableDataReader,TableDataSource}.php` to pass T004.

## Phase 3: US2 — safe $wpdb reader (P1)
- [x] T006 [US2] Implement `Data/WpTableDataReader.php`: prepared (`%i`/`%d`), bounded `LIMIT/OFFSET` page, prepared `COUNT` total, prepared delete by id; resolves the full (prefixed) table name via the Migrator namespace.

## Phase 4: US3 — auto-seed, opt-in (P1/P2)
- [x] T007 Seed the `DataRegistry` in `ConfigServiceProvider` with a `TableDataSource` per `ManagedTables->all()` (built from a `ManagedTables` singleton); with none registered, only the built-in sources show.

## Phase 5: Polish
- [x] T008 Guard Gate: wp-guard ($wpdb->prepare on every query, bounded LIMIT, no arbitrary table exposure), clean-code, test-guard; fix.
- [x] T009 [P] `composer test` green; verify a registered managed table appears as a source with rows + delete; none registered → only built-ins.
- [x] T010 Docs: the Data guide (docs-app) + corex-config README — registering a managed table; PROGRESS + DECISIONS #72; NEXT STEP.
