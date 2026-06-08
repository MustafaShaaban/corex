# Feature Specification: Data Layer — Model, Field Driver, Repository, QueryBuilder

**Feature Branch**: `002-data-layer`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "Data layer: Model + Field driver (ACF-optional) + QueryBuilder — the next module after the corex-core foundation. Model (value objects), Field driver (ACF-optional behind an interface, native meta fallback), Repository (sole data access), QueryBuilder (fluent wrapper, not a full ORM, eager loading to prevent N+1, query discipline). Built on corex-core; honors the constitution; no presentation/controllers."

## Overview

This feature gives Corex modules a clean, testable way to read and write WordPress data without
touching `WP_Query`, `$wpdb`, or `get_post_meta` directly. A **Model** describes an entity's shape; a
**Repository** is the only place that talks to the data source; a **Field driver** resolves custom
fields through ACF when it is installed or native WordPress meta when it is not; and a **QueryBuilder**
expresses queries fluently while enforcing performance discipline.

It builds directly on the corex-core foundation (container, service providers, config) and adds no
presentation, controllers, or business rules — it is the data-access layer the later modules
(controllers, blocks, forms, add-ons) depend on. The "users" are Corex module developers.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Read an entity through a Model and Repository (Priority: P1)

A developer defines a Model for an entity (e.g. a "Job" post type) declaring its typed attributes,
and reads an instance by id through that entity's Repository. The Repository is the only code that
touches the data source; the returned Model carries the entity's data as typed, read-only attributes —
no query logic, no business rules.

**Why this priority**: A typed Model + a single data-access layer is the irreducible core of the data
layer; every later capability (fields, queries, eager loading) returns or operates on Models. Without
it nothing else exists.

**Independent Test**: Define a Model and Repository for a known entity, seed one record, fetch it by
id, and confirm the returned Model exposes the entity's core attributes with the correct types — and
that fetching a non-existent id yields a clear "not found" result, not an error.

**Acceptance Scenarios**:

1. **Given** an entity exists in the data source, **When** the Repository fetches it by id, **Then** a
   Model is returned exposing the entity's typed attributes.
2. **Given** no entity exists for an id, **When** the Repository fetches it, **Then** a documented
   "not found" result is returned (null/Optional), never a fatal error.
3. **Given** a Model instance, **When** its attributes are read, **Then** they reflect the stored data
   with their declared types (e.g. integers as integers, dates as date values).
4. **Given** the Repository, **When** it is obtained, **Then** it is resolved from the container with
   its dependencies injected (no hand-construction).

### User Story 2 - Read and write custom fields without depending on ACF (Priority: P1)

A developer reads and writes an entity's custom fields through a single field-access abstraction. When
ACF is installed, fields resolve through ACF; when ACF is absent, the exact same code resolves through
native WordPress meta. The developer's code never references ACF directly, and the framework runs fully
with ACF not installed.

**Why this priority**: Constitution Principle IX — no optional plugin may be a hard dependency. Field
access is where ACF coupling normally leaks in; the driver makes ACF an enhancement, not a requirement,
and the rest of the framework depends on the abstraction.

**Independent Test**: Read a field for an entity with ACF absent (resolves via native meta), then with
ACF present (resolves via ACF), using identical calling code, and confirm both return the field value;
confirm the framework boots and operates with ACF uninstalled.

**Acceptance Scenarios**:

1. **Given** ACF is not installed, **When** a field is read for an entity, **Then** the value is
   resolved from native WordPress meta.
2. **Given** ACF is installed, **When** the same field is read, **Then** the value is resolved through
   ACF.
3. **Given** a field value, **When** it is written through the abstraction, **Then** it persists and a
   subsequent read returns it (through whichever driver is active).
4. **Given** no ACF anywhere, **When** the framework runs, **Then** every data-layer feature still
   works (no class-not-found, no missing-function error).
5. **Given** a field that does not exist, **When** it is read, **Then** a caller-supplied default is
   returned rather than an error.

### User Story 3 - Query entities fluently with performance discipline (Priority: P2)

A developer builds a query for a set of entities using a fluent, chainable API — filtering by attribute
or field, ordering, and limiting — and gets back a collection of Models. The builder refuses
performance-hostile patterns: it does not allow an unbounded "fetch everything" query, and any
developer-supplied values are bound safely (never concatenated into a query).

**Why this priority**: Fluent querying is how modules retrieve sets of entities; it depends on US1's
Model/Repository and is the next most-used capability. Query discipline (no unbounded fetch, safe
binding) is a constitution requirement.

**Independent Test**: Seed several entities, build a query that filters and orders them, and confirm the
returned collection matches the expected subset and order; confirm an attempt to fetch an unbounded
result set is rejected or safely capped; confirm a filter value containing query metacharacters is
treated as data, not query syntax.

**Acceptance Scenarios**:

1. **Given** several entities, **When** a query filters by an attribute and orders the results, **Then**
   a collection of Models matching the filter in the requested order is returned.
2. **Given** a query, **When** a result limit is set, **Then** at most that many Models are returned.
3. **Given** a query, **When** an unbounded "all results" request is made, **Then** it is rejected or
   capped at a safe maximum (never an uncapped fetch).
4. **Given** a filter value containing special characters, **When** the query runs, **Then** the value
   is treated as data (safely bound), not as query syntax.
5. **Given** a query that matches nothing, **When** it runs, **Then** an empty collection is returned,
   not an error.

### User Story 4 - Eager-load related data to prevent N+1 (Priority: P2)

A developer requesting a collection of entities also requests related data (e.g. each Job's company)
in one declaration, and the related data is loaded in a bounded number of queries rather than one query
per entity.

**Why this priority**: N+1 query explosions are the most common WordPress performance failure; eager
loading is the wrapper's headline value over raw `WP_Query`. It builds on US3's querying.

**Independent Test**: Seed entities each with a relation, fetch the collection with the relation eager-
loaded, and confirm the relation is populated on every Model while the number of data-source queries
stays bounded (does not grow linearly with the number of entities).

**Acceptance Scenarios**:

1. **Given** a collection request with a relation eager-loaded, **When** it runs, **Then** every
   returned Model has its related data populated.
2. **Given** N entities with a relation, **When** the relation is eager-loaded, **Then** the number of
   queries does not grow proportionally to N (no N+1).
3. **Given** a relation that has no related record for some entities, **When** it is eager-loaded,
   **Then** those Models report the relation as empty, not as an error.

### Edge Cases

- **Missing entity**: fetching an absent id returns a documented "not found" result; eager-loading a
  relation that is absent yields an empty relation, never a fatal.
- **ACF activated/deactivated at runtime**: the active driver is determined by ACF's current presence;
  switching does not corrupt reads/writes (each operation resolves against the current driver).
- **Field type mismatch**: reading a field whose stored value does not match the expected type returns
  a sane, documented coercion or the caller default — not a fatal.
- **Unbounded query**: an explicit or accidental "fetch all" is capped at a safe maximum; the cap is
  configurable through the Config engine.
- **Special characters in filters**: values are always bound as data; a value like `' OR 1=1` matches
  literally and never alters the query.
- **Empty result**: queries and collections return empty results, not nulls or errors, when nothing
  matches.
- **Custom-table vs post-backed entity**: the Repository abstraction covers the post-backed case first;
  the contract is shaped so a custom-table source can be added without changing calling code.

## Requirements *(mandatory)*

### Functional Requirements

**Model**

- **FR-001**: A Model MUST describe an entity as typed attributes (a value object); it MUST NOT contain
  query logic, data-source calls, or business rules.
- **FR-002**: A Model's attributes MUST be exposed with their declared types (e.g. id as integer, dates
  as date values).
- **FR-003**: A Model MUST be constructible from a data-source record by its Repository and be usable
  in tests without a database.

**Repository**

- **FR-004**: The Repository MUST be the only layer that talks to the data source (no data-source calls
  in Models, services, or controllers).
- **FR-005**: The Repository MUST fetch an entity by id and return a Model, or a documented "not found"
  result (null/Optional) when absent — never a fatal error.
- **FR-006**: The Repository MUST support creating, updating, and deleting an entity, returning the
  resulting Model (or success indicator) for writes.
- **FR-007**: Repositories MUST be resolved through the container with dependencies injected.

**Field driver (ACF-optional)**

- **FR-008**: Custom-field access MUST go through a single field-access abstraction (interface); calling
  code MUST NOT reference ACF directly.
- **FR-009**: The abstraction MUST resolve fields through ACF when ACF is present and through native
  WordPress meta when it is not, behind the same interface (driver selection at runtime).
- **FR-010**: The framework MUST run fully with ACF absent — no field-driver code path may hard-depend
  on ACF classes/functions.
- **FR-011**: Reading a missing field MUST return a caller-supplied default, never an error.
- **FR-012**: Writing a field through the abstraction MUST persist via the active driver and be
  readable back.

**QueryBuilder**

- **FR-013**: The QueryBuilder MUST offer a fluent, chainable API to filter (by attribute/field), order,
  and limit, returning a collection of Models.
- **FR-014**: The QueryBuilder MUST be a wrapper over the platform query mechanism, not a full ORM; it
  MUST NOT introduce its own SQL dialect.
- **FR-015**: The QueryBuilder MUST refuse or safely cap an unbounded "fetch all" request; the cap MUST
  be configurable via the Config engine.
- **FR-016**: Developer-supplied filter values MUST be bound as data (prepared), never concatenated into
  a query.
- **FR-017**: The QueryBuilder MUST return an empty collection (not null/error) when nothing matches.

**Eager loading**

- **FR-018**: The QueryBuilder MUST support declaring related data to eager-load for a collection.
- **FR-019**: Eager-loading a relation across N entities MUST use a bounded number of queries (no N+1).
- **FR-020**: A relation with no related record MUST resolve as empty for that Model, not as an error.

**Cross-cutting**

- **FR-021**: Every part of this module MUST be registered through a corex-core `ServiceProvider` and
  resolved via the container (no `new` of dependencies inside methods).
- **FR-022**: Every behavior MUST be exercisable in automated tests headlessly and with no optional
  plugin (ACF/Woo/Polylang) installed.
- **FR-023**: The module MUST add no presentation, controllers, or business rules (data access only).

### Key Entities

- **Model**: a typed value object describing one entity's shape (attributes + their types). No behavior
  beyond exposing its data.
- **Repository**: the sole data-access layer for an entity; reads/writes entities and returns Models.
- **Field driver**: the abstraction (interface) over custom-field access; concrete drivers are the ACF
  driver and the native-meta driver, selected at runtime by ACF presence.
- **Field resolver**: the component that picks the active field driver.
- **QueryBuilder**: a fluent query object that produces a collection of Models with bounded, safe queries.
- **Collection**: the ordered set of Models a query returns (empty when nothing matches).
- **Relation**: a declared link from one entity to related data, eager-loadable in a bounded query count.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can read an entity by id and get a typed Model in **5 lines or fewer**, with
  the Repository injected (no manual `new`, no direct `WP_Query`/`get_post_meta`).
- **SC-002**: Identical field-access code returns the correct value in **both** environments — ACF
  installed and ACF absent (2/2).
- **SC-003**: With ACF, WooCommerce, and Polylang all uninstalled, **100%** of the module's automated
  tests pass headlessly.
- **SC-004**: Eager-loading a relation across **N** entities issues a **bounded** number of queries
  that does not increase as N grows (verified: same query count for N=2 and N=50).
- **SC-005**: An unbounded "fetch all" request is **never** executed uncapped — it is rejected or capped
  at the configured maximum in 100% of attempts.
- **SC-006**: A filter value containing query metacharacters matches **literally** and never alters the
  query (injection attempt yields zero unintended matches).
- **SC-007**: Every queryable/readable/writable behavior is covered by a headless automated test that
  passes with no optional plugins present.

## Assumptions

- **Audience**: consumers are Corex module developers; there is no end-user UI in this feature.
- **Primary entity source**: post-backed entities (custom post types) are the first-class case; the
  Repository/Model contracts are shaped so taxonomy-, user-, and custom-table-backed sources can be
  added later without changing calling code. Taxonomy/user/custom-table repositories are out of scope
  for this feature unless trivially covered by the same contract.
- **Query backing**: post-backed queries are expressed over the platform's native post-query mechanism;
  no custom SQL dialect is introduced (FR-014).
- **Models are read-oriented value objects**: writes happen through the Repository (which returns an
  updated Model), keeping Models free of persistence logic.
- **Field driver selection**: determined at runtime by ACF's presence; no configuration toggle is
  required to choose the driver (it can be overridden via an explicit binding if needed).
- **Foundation dependency**: this module depends on corex-core (container, service providers, Config)
  delivered in spec 001; it is registered as a service provider and reuses the Config engine for the
  query cap and any tunables.
- **Scope boundary**: migrations/seeders, CLI generators (`make:model`/`make:repository`), block
  connectors, and caching strategy are **out of scope** here (later phases per COREX-SPECKIT-START).
- **Persistence**: no new custom tables are introduced in this feature; entities map to existing
  WordPress storage (posts + meta). Custom-table support is a later extension of the same contract.
- **Environment**: developed against the working WordPress install (Environment Gate satisfied).
