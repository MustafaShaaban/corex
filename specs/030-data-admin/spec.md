# Feature Specification: Admin data management (DataViews)

**Feature Branch**: `feature/030-data-admin` · **Created**: 2026-06-12 · **Status**: Draft (forward, full Spec Kit)

**Input**: "Where does my contact form data appear? Why can't I find and manage my custom-table data through the admin?"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See and manage form submissions (Priority: P1)
A site admin opens a **Corex → Data** screen and sees every form submission in a sortable, paginated table —
date, form, and the submitted values — and can delete a submission. The contact-form data is finally visible.

**Independent Test**: With submissions stored, the screen lists them (cap-gated); deleting one removes it.

**Acceptance Scenarios**:
1. **Given** stored submissions, **When** the Data screen loads, **Then** it shows them in a DataViews table
   (date, form, value summary), paginated, newest first.
2. **Given** a submission, **When** the admin deletes it, **Then** it is removed (cap + nonce gated).
3. **Given** the data, **When** requested, **Then** it comes from a **cap-gated** REST source (`manage_options`).

### User Story 2 - Manage any Corex custom table the same way (Priority: P2)
A developer registers a Corex data source (a custom table) and it appears in the same Data screen with its rows
— so custom-table data is manageable in admin through one consistent pattern.

**Independent Test**: A registered table source lists its rows in the screen via the same REST contract.

**Acceptance Scenarios**:
1. **Given** a registered `DataSource`, **When** the screen's source switcher selects it, **Then** its columns
   + rows render in the same DataViews table.
2. **Given** the submissions source, **When** the framework boots, **Then** it is registered automatically as
   the reference source.

### Edge Cases
- No submissions → an empty-state table, never an error.
- A source returns only what the user may see; never secrets.
- Deleting requires a valid nonce + capability; a read-only viewer can't delete.

## Requirements *(mandatory)*
- **FR-001**: A **Corex → Data** admin screen MUST render a React **DataViews** table of a selected data source,
  paginated + sortable, gated by `manage_options`.
- **FR-002**: Form **submissions** MUST be a registered `DataSource` exposing date, form slug, and a value
  summary per row; the screen MUST list and **delete** them.
- **FR-003**: A pure `DataSource` abstraction (`key/label/columns/rows/total/delete`) MUST let any Corex custom
  table (TableRepository) plug into the same screen; the submissions source is the reference implementation.
- **FR-004**: Data MUST be served by a **cap-gated REST** controller — `GET corex/v1/data/<source>` (list,
  `manage_options`) and `DELETE corex/v1/data/<source>/<id>` (nonce + `manage_options`).
- **FR-005**: The screen's React MUST build through `@wordpress/scripts` and use the `@wordpress/dataviews`
  package; the data-shaping (rows/columns) MUST be unit-tested headlessly.
- **FR-006**: All output escaped/i18n/RTL; no secrets exposed; deletes verified (nonce + cap).

### Key Entities
- **DataSource**: `key`, `label`, `columns()`, `rows(page,perPage)`, `total()`, `delete(id)`.
- **DataRegistry**: the registered sources (submissions + any custom-table sources).

## Success Criteria *(mandatory)*
- **SC-001**: Form submissions are visible + deletable in admin.
- **SC-002**: A registered custom-table source appears in the same screen with no new UI code.
- **SC-003**: The data REST is `manage_options`-gated; deletes require a nonce; no secrets leak.
- **SC-004**: The DataSource shaping is unit-tested; the admin React builds clean.
- **SC-005**: Empty data shows an empty state, never an error.

## Assumptions
- Built on spec-007 submissions + spec-011 TableRepository + spec-018 build. `@wordpress/dataviews` is available
  in WP 7.0. The admin lives in `corex-config` beside the other screens (shared `AdminGuard` discipline).
- Browser-visual confirmation of the React table is environment-gated; the REST, the sources, and the
  data-shaping are verified headlessly + live (route registration).
