# Research: Admin data management (030)

## R1 — One screen, two kinds of data (the DataSource abstraction)
**Decision**: A `DataSource` interface (`key/label/columns/rows/total/delete`) backs the screen. Form
submissions (CPT `corex_submission`) are the reference `SubmissionsSource`; custom tables (TableRepository)
implement the same interface. The React table is generic — it renders whatever columns/rows a source returns.
**Rationale**: Submissions are post-backed and custom tables are row-backed; a single interface lets one
DataViews screen serve both without per-table UI. **Alternatives**: a bespoke screen per data type (rejected —
doesn't scale, duplicates UI).

## R2 — DataViews for the table
**Decision**: Use `@wordpress/dataviews` (the modern WP admin table: sorting, pagination, bulk actions),
externalized by the build to `wp.dataviews`. **Rationale**: It's the WordPress-native admin data UI (matches
core's Site Editor lists), so it's familiar + maintained. **Alternatives**: a hand-rolled table (rejected —
reinvents sorting/pagination/a11y).

## R3 — Cap-gating + delete safety
**Decision**: `GET` is `manage_options`-gated; `DELETE` additionally requires the REST nonce. Sources return
only summarised, safe fields. **Rationale**: viewing data is admin-only; deleting is a state change → nonce
(Principle VII). **Alternatives**: edit_posts (rejected — submissions/tables are admin data).

## R4 — Testability of post-backed sources
**Decision**: `SubmissionsSource` takes an injected reader (a thin WP_Query wrapper) so `rows()`/`columns()`
shaping is unit-tested with a stub; the WP boundary stays in the reader. **Rationale**: the data-shaping is the
logic worth testing; WP_Query is the boundary.
