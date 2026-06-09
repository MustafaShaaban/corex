# Feature Specification: Custom Tables + TableRepository (MVP)

**Feature Branch**: `011-custom-tables`

**Created**: 2026-06-09

**Status**: Draft

**Input**: A migrations/schema builder + a `TableRepository` with casts, in corex-core — the data
foundation for entities that are many queryable rows (subscribers, applications, bookings), not posts.
Pure schema/cast logic is unit-tested; the repository runs against a real table (integration).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Define a table as code (Priority: P1) 🎯 MVP

A developer declares a table's columns fluently and the framework produces the SQL to create it (prefixed,
charset-aware) — no hand-written DDL.

**Why this priority**: Without schema definition there is no custom-table layer; it is the foundation.

**Independent Test**: Build a table definition and assert the generated `CREATE TABLE` SQL contains each
declared column with the right type and a primary key — headlessly, no database.

**Acceptance Scenarios**:

1. **Given** a table with `id`, a string, an integer, a boolean, and timestamps, **When** its SQL is
   generated, **Then** it is a valid prefixed `CREATE TABLE` with each column and `PRIMARY KEY (id)`.

---

### User Story 2 - Read/write rows with typed casts (Priority: P1)

A repository inserts, finds, updates, deletes, and queries rows on a custom table, casting values to/from
their declared types (int, bool, string, decimal, array/json, datetime).

**Why this priority**: A schema with no typed access is unusable; this is the Laravel-like data experience.

**Independent Test**: Cast each supported type both directions (store ↔ hydrate) headlessly; the
repository's CRUD runs against a real test table (integration).

**Acceptance Scenarios**:

1. **Given** a value and a declared type, **When** stored then hydrated, **Then** it round-trips to the
   correct PHP type (e.g. a `json` array, a `datetime`, a `bool`).
2. **Given** a repository on a real table, **When** a row is inserted then found, **Then** the persisted
   values come back cast to their types; update/delete/where behave as expected.

---

### Edge Cases

- A query with a variable → always parameterized (`$wpdb->prepare`); no interpolation.
- `find` of a missing id → null. `where` with no matches → empty list.
- A malformed stored JSON value → hydrates to an empty array, not a fatal.
- Table create is idempotent (re-running does not error) via `dbDelta`.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST let a developer define a table's columns fluently and generate a prefixed,
  charset-aware `CREATE TABLE` statement with a primary key — no hand-written DDL.
- **FR-002**: The system MUST create/drop custom tables idempotently (via WordPress's `dbDelta`), under the
  site table prefix plus a `corex_` namespace.
- **FR-003**: The system MUST provide a `TableRepository` with insert/find/update/delete/where, where every
  query containing a variable is parameterized (`$wpdb->prepare`) — never interpolated.
- **FR-004**: The repository MUST cast values to/from their declared types — `int`, `bool`, `string`,
  `decimal`, `array`/`json`, `datetime` — on hydrate and on persist.
- **FR-005**: `find`/`where` MUST be safe and bounded; a missing row returns null and an empty query an
  empty list; malformed stored JSON hydrates to an empty array (non-fatal).
- **FR-006**: This layer MUST live in corex-core and MUST NOT depend on any optional plugin.

### Key Entities *(include if feature involves data)*

- **Table (schema definition)**: a table name + fluent columns; produces the create SQL. Pure.
- **Caster**: casts a value to/from a declared type. Pure.
- **TableRepository**: typed CRUD on a custom table (the only layer that runs the queries).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A table is defined in code and its create SQL is correct and prefixed (headless).
- **SC-002**: Every supported type round-trips store↔hydrate to the correct PHP type (headless).
- **SC-003**: The repository's CRUD works against a real table; every variable query is parameterized.
- **SC-004**: A missing/empty/malformed read is non-fatal (null / empty / empty-array).
- **SC-005**: The schema + cast logic is fully unit-tested with no database.

## Assumptions

- **Reuses the existing data conventions.** Lives in corex-core (`Corex\Database\Schema`,
  `Corex\Database\Casts`, `Corex\Repositories\TableRepository`). Migrations run on plugin activation by the
  owning module (e.g. the newsletter add-on creates its subscribers table).
- **MVP scope.** Columns: id, string, integer, boolean, text, decimal, datetime, timestamps; basic
  CRUD + simple `where`. Indexes beyond the primary key, foreign keys, relations across custom tables, a
  query builder, and migration versioning/rollback history are deferred.
