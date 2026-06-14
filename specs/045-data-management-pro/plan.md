# Implementation Plan: Data management pro

**Branch**: `feature/045-data-management-pro` | **Date**: 2026-06-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/045-data-management-pro/spec.md`

## Summary

Make the **Corex → Data** screen a real tool: extend the spec-030 `DataSource` contract to answer a **`DataQuery`**
(search / form filter / sort / paginate) and a single **`record(id)`**, add **CSV export** of the current filtered
result, a **readable detail view**, and a **`SubmissionStore` seam** so the `corex_submission` post storage is one
driver and a custom-table driver can replace it later. All querying/shaping stays in pure sources + a thin reader;
the screen + export route through `AdminGuard` (cap+nonce) and the spec-043 envelope/runtime; **no secret** in any
response. CSV-only for v1; the custom-table driver is out of scope (the seam + post-meta default driver only).

## Technical Context

**Language/Version**: PHP 8.3 (corex-config + corex-forms) + the existing React Data app via `window.Corex` (043).

**Primary Dependencies**: existing only — spec-030 `DataSource`/`DataRegistry`/`DataController`/`DataAdminScreen` +
the React app, spec-038 `TableDataSource`/`WpTableDataReader`, spec-007 `StoreSubmissionListener`, the `AdminGuard`,
and the spec-043 `ResponseEnvelope`/`window.Corex`. No new runtime/build dependency; no data migration.

**Storage**: unchanged data — the default `SubmissionStore` driver wraps the current `corex_submission`
post/postmeta storage; the seam makes a custom-table driver a future swap.

**Testing**: Pest — pure `DataQuery`, the `CsvWriter` (escaping), the source query/record shaping (reader injected),
the store driver shaping. Jest where the React app gains search/sort/export wiring. Browser smoke env-gated.

**Target Platform**: wp-admin (Corex → Data).

**Project Type**: WordPress framework monorepo — corex-config (Data) + corex-forms (the store seam).

**Performance Goals**: queries bounded + paginated (`no_found_rows` off only for the count); export bounded/streamed
(documented cap) so a large set never exhausts memory.

**Constraints**: prepared/escaped search (no injection); cap+nonce on every action; **no secret/internal field** in
any query/export/detail; token-only/admin-fallback, logical/RTL, WCAG 2.2 AA, i18n.

**Scale/Scope**: 1 `DataQuery` VO + 1 `CsvWriter` + the `DataSource` contract extension (3 implementers) + an
export controller + the `SubmissionStore` seam + its post-meta driver + React app wiring (search/sort/export/detail).

## Constitution Check

*GATE: must pass before Phase 0; re-check after Phase 1.* (Corex Constitution v1.2.1.)

- [x] **I. Theme is a skin** — N/A. Admin-only feature; no theme logic.
- [x] **II. Plugins boot themselves** — PASS. corex-config/corex-forms providers on admin/REST hooks; no theme dep.
- [x] **III. Thin controllers, fat services** — PASS. The query/shaping/CSV logic is in pure sources/writers; the
  controllers route → source/store → envelope; the `$wpdb`/`WP_Query` stays in the injected readers.
- [x] **IV. Everything injected** — PASS. Sources/readers/store/writer are container-wired; the `DataQuery`/CSV are
  pure value objects.
- [x] **V. Runtime tokens** — PASS. Any new admin CSS uses tokens + admin fallbacks; no build-time tokens.
- [x] **VI. Conditional assets** — PASS. The Data app + any new asset enqueue only on the Data screen.
- [x] **VII. Declarative security** — PASS. `AdminGuard` cap+nonce; the search term is **prepared/escaped** in the
  reader (no injection); export/detail are cap-gated; envelopes carry **no secret/internal field** (SC-005).
- [x] **VIII. RTL-first** — PASS. Logical CSS; the DataViews controls are direction-agnostic.
- [x] **IX. No optional dep is hard** — PASS. The `SubmissionStore` seam keeps storage swappable (post-meta now,
  custom-table later) behind an interface — Principle IX applied to our own storage.
- [x] **X. Spec is source of truth** — PASS. Traces to spec 045; extends 030/038/043 without re-speccing.
- [x] **Guard Gate + DoD** — wp-guard (prepared query, cap+nonce, escaped, no secret, bounded export), clean-code,
  test-guard, docs-guard (queries/data guides); i18n/RTL/WCAG; PROGRESS/DECISIONS; NEXT STEP.

**Result: PASS — no violations.**

## Project Structure

```text
plugins/corex-config/src/Data/
├── DataQuery.php             # NEW — pure VO: search, filters[], sortColumn, sortDir, page, perPage
├── CsvWriter.php             # NEW — pure: columns + rows → RFC-4180-escaped CSV string
├── DataSource.php            # CHANGE — rows(DataQuery) + total(DataQuery) + record(int): ?array
├── SubmissionsSource.php     # CHANGE — answer DataQuery (search/filter/sort) + record()
├── TableDataSource.php       # CHANGE — answer DataQuery (prepared) + record()
├── SubmissionsReader.php / WpTableDataReader.php  # CHANGE — query support (prepared, bounded)
├── DataController.php        # CHANGE — accept query params; envelope already applied (043)
├── DataExportController.php  # NEW — REST CSV download (cap+nonce, bounded/streamed, no secret)
└── DataAdminScreen.php       # CHANGE — enqueue stays; pass query/export config to the app

plugins/corex-forms/src/Submission/
├── SubmissionStore.php       # NEW — the store seam (save/query/find/delete)
├── PostMetaSubmissionStore.php  # NEW — default driver wrapping corex_submission post/postmeta
└── StoreSubmissionListener.php  # CHANGE — persist through the seam (behavior unchanged)

plugins/corex-config/src/admin/index.js   # CHANGE — search/sort/export/detail via window.Corex.api
tests/Unit/Data/ (Pest) · tests/<jest>     # NEW — DataQuery, CsvWriter, source query/record, store driver
docs-app/.../guides/queries.md (+ a data guide)   # CHANGE — document the query/export/store seam
```

**Structure Decision**: Querying/shaping/CSV are **pure** (corex-config Data); the `$wpdb`/`WP_Query` stays in the
injected readers (Principle III). The `SubmissionStore` seam lives in **corex-forms** (which owns submission
persistence), with a post-meta default driver; the Data screen reads submissions through the source (which reads
through the seam), so a custom-table driver is a swap, not a rewrite. The custom-table driver is **out of scope**.

## Complexity Tracking

> No Constitution Check violations — section intentionally empty.
