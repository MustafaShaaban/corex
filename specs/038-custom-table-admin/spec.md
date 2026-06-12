# Feature Specification: Custom tables in the admin (038)

**Created**: 2026-06-12 · **Status**: Draft · **Input**: "If I created a custom table I should find the table
view for it in the admin like post types." Make any Corex-managed custom database table **appear automatically**
in the Corex → Data screen — browsable, paginated, deletable — with no new UI code, the way a registered post
type gets a list table.

## User Scenarios & Testing

### US1 — A managed table shows up automatically (P1) 🎯 MVP
As a developer, after I create a custom table with the Corex schema and **mark it managed** (one declaration), it
appears as its own source in **Corex → Data** — its columns as the table headers, its rows paginated, each row
deletable — without writing any admin UI.

**Acceptance**: a `ManagedTable` (name + label + columns) registered in the `ManagedTables` registry is turned
into a `DataSource` and listed in the Data screen's source switcher; selecting it shows the rows via the existing
cap-gated `corex/v1/data/<key>` REST; delete removes the row.

### US2 — Correct, safe columns and rows (P1)
As a site owner, I see the table's real columns and values, paginated, with the id available for delete — and the
queries are safe (prepared) and bounded (never an unbounded scan).

**Acceptance**: the source returns the declared columns; rows are read page-by-page with a prepared, limited
query; total is a prepared count; delete is a prepared delete by id; an unknown column is ignored.

### US3 — Opt-in, never surprising (P2)
As a developer, only the tables I explicitly mark managed appear — Corex never enumerates and exposes arbitrary
database tables.

**Acceptance**: with no managed tables registered, the Data screen shows only the built-in sources (submissions);
a table appears only after it is registered as managed.

## Requirements

- **FR-001**: A `ManagedTable` value (unprefixed table name, label, ordered columns `{id,label}`) and a
  `ManagedTables` registry (register/all) — pure, in corex-core (the schema layer).
- **FR-002**: A `TableDataSource` implementing the spec-030 `DataSource` over a `ManagedTable` and an injected
  reader; the row/column shaping is **pure and unit-tested** (no `$wpdb`).
- **FR-003**: A `TableDataReader` seam (`page`, `total`, `delete`) with a `$wpdb` implementation that uses
  **prepared** statements (`%i` identifiers / `%d` ids), a bounded `LIMIT/OFFSET`, and a prepared `COUNT` — never
  an unbounded scan (Principle VII performance + security).
- **FR-004**: The Data registry is **seeded automatically** from `ManagedTables` — each managed table becomes a
  `TableDataSource` in Corex → Data, reusing the existing screen + REST + `AdminGuard` with no new UI.
- **FR-005**: **Opt-in only** (Principle IX): Corex never lists tables that weren't explicitly registered as
  managed; the table key is namespaced (`table-<name>`) to avoid collisions with built-in sources.
- **FR-006**: The pure pieces (`ManagedTable`, `ManagedTables`, `TableDataSource`) are headless **Pest**-tested;
  the `$wpdb` reader is the thin boundary.

## Success Criteria

- **SC-001**: A developer registers a managed table in one declaration and it appears in Corex → Data with its
  columns, paginated rows, and a working delete — no admin code.
- **SC-002**: All table queries are prepared and bounded; no arbitrary table is ever exposed.
- **SC-003**: The new pure pieces have passing Pest tests; the full suite stays green.

## Assumptions

- A "Corex-managed" table is one created via the Corex `Table`/`Migrator` schema; marking it managed is a small
  explicit registration (so the feature is safe and predictable, not a blanket scan of the database).
- The table has an integer `id` primary key (the Corex schema's `id()` convention), used for the delete action.
- Read-only + delete in the admin (matching the submissions source); editing rows is out of scope for this spec.

## Dependencies

Spec 002 (the data layer: `Table`, `Migrator`, `TableRepository`), spec 030 (the Data screen + `DataSource` +
REST + `AdminGuard`).
