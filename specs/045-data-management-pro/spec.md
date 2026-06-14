# Feature Specification: Data management pro

**Feature Branch**: `feature/045-data-management-pro`

**Created**: 2026-06-13

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Make the Data tab a real data-management tool — search, filter by form, sortable
columns, pagination, CSV export, a readable submission detail view (pretty labels, not a raw summary), and decide
the long-term storage (CPT now, a custom-table driver later). Build on the shipped specs 030 (DataViews) and 038
(custom tables); reuse the spec-043 envelope/runtime; don't re-spec them."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Find the submission you need (Priority: P1) 🎯 MVP

A site admin on **Corex → Data** searches submissions by text, filters by which form they came from, sorts by a
column (e.g. newest/oldest), and pages through the results — instead of scrolling one undifferentiated list. The
data tab becomes a tool for finding a specific record among many.

**Why this priority**: Search/filter/sort/paginate is the core of "make the Data tab a real tool"; with real
volume (the live install already has 30+ submissions) an unfiltered list is unusable.

**Independent Test**: With many submissions across forms, a text search narrows to matching records; a form
filter shows only that form's submissions; a sort reorders them; paging moves through the set — each via the
cap-gated data source.

**Acceptance Scenarios**:

1. **Given** stored submissions, **When** the admin enters a search term, **Then** only records matching the term
   are listed (and the total/pagination reflect the filtered set).
2. **Given** submissions from multiple forms, **When** the admin selects a form filter, **Then** only that form's
   submissions are shown.
3. **Given** a sortable column, **When** the admin sorts by it, **Then** the records reorder accordingly (e.g.
   date ascending/descending), stable across pages.
4. **Given** a filtered/sorted result, **When** the admin pages, **Then** the search/filter/sort persist across
   pages and the counts are correct.
5. **Given** the query, **When** it runs, **Then** it comes from the **cap-gated** data source (`manage_options`)
   and never exposes a record the user may not see.

---

### User Story 2 - Export what you're looking at (Priority: P1)

The admin exports the current (searched/filtered) submissions to a **CSV** file they can open in a spreadsheet —
to report, back up, or hand off — with the columns and values they see on screen.

**Why this priority**: Export is the most-requested "do something with the data" action and pairs directly with
the filtering in US1 (export the filtered set).

**Independent Test**: With a filter applied, the export produces a CSV of exactly those records, with a header row
and the visible columns; opening it in a spreadsheet shows the data; no secret/internal field leaks.

**Acceptance Scenarios**:

1. **Given** a filtered result, **When** the admin exports, **Then** a CSV downloads containing exactly the
   filtered records with a header row and the source's columns.
2. **Given** a value containing a comma, quote, or newline, **When** exported, **Then** it is correctly escaped so
   the CSV parses cleanly.
3. **Given** the export, **When** requested, **Then** it is **cap-gated** (`manage_options` + a valid token) and
   contains no internal/secret field.

---

### User Story 3 - Read a submission properly (Priority: P2)

The admin opens a single submission and sees its fields with **readable labels and values** (Name, Email,
Message — not a raw concatenated summary), with the form and date — so a submission is legible at a glance.

**Why this priority**: The current "summary" column is a raw blob; a real detail view makes individual records
usable. P2 because list-level find + export (US1/US2) deliver the headline value first.

**Independent Test**: Open a stored submission; its fields render as label → value pairs (pretty labels), with the
form name and date; an empty/missing field degrades gracefully.

**Acceptance Scenarios**:

1. **Given** a submission, **When** opened, **Then** its fields render as readable label → value pairs (not a raw
   summary), with the form and date.
2. **Given** a field with no value, **When** rendered, **Then** it degrades gracefully (no broken markup).
3. **Given** the detail, **When** requested, **Then** it comes from the cap-gated source and exposes no secret.

---

### User Story 4 - A storage seam that can grow (Priority: P2)

The framework reads/writes submissions through a **store abstraction** so the current `corex_submission`
post/postmeta storage is one driver, and a custom-table driver can replace it later for volume/reporting —
**without** changing the forms engine or the admin screen.

**Why this priority**: The brief's explicit architecture question ("is CPT OK long-term?"). Defining the seam now
keeps CPT for the MVP while making the custom-table path a driver swap, not a rewrite. P2 — design + the default
driver; the custom-table driver itself is deferred.

**Independent Test**: The forms store/read submissions through the seam; swapping to a different driver
implementation changes where data lives with no change to the form submit path or the Data screen contract.

**Acceptance Scenarios**:

1. **Given** the submission store seam, **When** a form is submitted, **Then** it persists through the store
   (the default driver = the current `corex_submission` post storage — behavior unchanged).
2. **Given** the Data screen, **When** it lists/exports submissions, **Then** it reads through the same seam, so a
   future driver swap needs no screen change.
3. **Given** a documented driver boundary, **When** a developer reads it, **Then** the post-meta driver vs a
   future custom-table driver is clearly delineated (the custom-table driver is out of scope here).

---

### Edge Cases

- An empty search/filter result → an empty-state table, never an error.
- A search term with special characters is treated as a literal, safely (no injection).
- Export of zero records → a CSV with just the header row (or a clear "nothing to export"), not a broken file.
- A very large export → bounded/streamed so it does not exhaust memory (a sane cap, documented).
- A submission with a field whose key isn't a known form field → still shown (label falls back to the key).
- Sorting by a column the source doesn't support → ignored gracefully (default order), never an error.

## Requirements *(mandatory)*

### Functional Requirements

**Query: search / filter / sort / paginate (US1)**

- **FR-001**: The data source contract MUST accept a **query** (search text, a source filter such as form, a sort
  column + direction, page + per-page) and return the matching rows + the matching total.
- **FR-002**: The Data screen MUST let the admin search by text, filter by form, sort by a column, and paginate,
  with the query persisting across pages.
- **FR-003**: All querying MUST run through the **cap-gated** (`manage_options`) source/REST and MUST never expose
  a record outside the user's permission.
- **FR-004**: A search term MUST be treated as a safe literal (prepared/escaped) — no injection, even with special
  characters.

**Export (US2)**

- **FR-005**: The admin MUST be able to export the **current (filtered) result** to a CSV with a header row and
  the source's columns; values MUST be CSV-escaped (commas, quotes, newlines).
- **FR-006**: Export MUST be **cap-gated** (`manage_options` + a valid token) and MUST contain no internal/secret
  field; a zero-record export yields a valid header-only CSV.
- **FR-007**: Export MUST be bounded (a documented cap / streamed) so a large data set does not exhaust memory.

**Detail view (US3)**

- **FR-008**: A single submission MUST be viewable as readable **label → value** pairs (pretty labels, not a raw
  summary), with its form and date; a missing value degrades gracefully.
- **FR-009**: The detail MUST come from the cap-gated source and expose no secret.

**Storage seam (US4)**

- **FR-010**: Submissions MUST be persisted and read through a **store seam** (an interface), with the current
  `corex_submission` post/postmeta storage as the **default driver** — behavior unchanged for the MVP.
- **FR-011**: The forms submit path and the Data screen MUST depend on the seam, not on the post storage directly,
  so a future custom-table driver is a swap, not a rewrite.
- **FR-012**: The driver boundary MUST be documented (post-meta driver now; custom-table driver later, out of
  scope here).

**Cross-cutting**

- **FR-013**: The Data screen's actions (query, export, detail) MUST route through the shared `AdminGuard`
  (cap + nonce) and answer REST through the **spec-043 response envelope** via the shared `window.Corex` runtime —
  no bespoke fetch (the spec-043 migration already applied to this screen).
- **FR-014**: All UI MUST be token-only (admin-fallback), logical/RTL, WCAG 2.2 AA (sortable headers + controls
  accessible), and i18n-ready.

### Key Entities *(include if feature involves data)*

- **DataQuery**: the query a source answers — search text, filters (e.g. `form`), sort (column + direction), page,
  per-page. A pure value object.
- **DataSource (extended)**: the spec-030 contract, now answering a `DataQuery` (rows + total), plus a single
  **record(id)** for the detail view. Submissions + any custom table implement it.
- **Submission record**: id, date, form, and the field map (key → value) the detail view renders as label → value.
- **SubmissionStore (seam)**: the persistence interface for submissions — `save`, `query`, `find`, `delete` — with
  a post-meta driver (default) and a future custom-table driver (out of scope).
- **CSV export**: the header + escaped rows derived from the source's columns + the current query's records.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can locate a specific submission among **hundreds** by combining search + form filter +
  sort, in **under 15 seconds**, without scrolling the full list.
- **SC-002**: The admin can export the current filtered result to a spreadsheet-openable CSV in **one action**,
  with **100%** of values correctly escaped (commas/quotes/newlines parse cleanly).
- **SC-003**: A single submission reads as labelled field → value pairs in **100%** of cases (no raw-summary
  blob), degrading gracefully on missing values.
- **SC-004**: Swapping the submission storage driver changes where data lives with **zero** changes to the form
  submit path or the Data screen contract.
- **SC-005**: **No secret/internal field** appears in any query, export, or detail response; every action is
  refused for a non-`manage_options` or unauthenticated request.

## Assumptions

- Builds on and **reuses** the spec-030 Data screen + `DataSource`/`DataRegistry`/`DataController`, the spec-038
  custom-table source, the shared `AdminGuard`, and the spec-043 envelope + `window.Corex` runtime — this feature
  **extends** the `DataSource` contract (query + record) and adds export + a storage seam; it does not re-spec them.
- The **default** `SubmissionStore` driver is the current `corex_submission` post/postmeta storage (spec 007's
  `StoreSubmissionListener` + the spec-030 reader, refactored behind the seam) — no data migration, behavior
  preserved. The **custom-table driver is explicitly out of scope** (a later increment / the brief's "when volume
  demands").
- Export format is **CSV** for v1; PDF/Excel are out of scope (the brief deferred PDF).
- Search is a substring match over the stored fields/summary; filter is by form slug; sort covers the displayed
  columns (date primarily). Advanced query (date ranges, status) beyond this is out of scope for v1.
- The export bound is a documented cap (e.g. a few thousand rows) / streamed write to avoid memory exhaustion.
- Browser-visual confirmation of the DataViews search/sort/export UI requires a running server + browser; per the
  project-wide environment gate, the automated unit coverage is authoritative and the live browser smoke runs when
  the environment is available.
