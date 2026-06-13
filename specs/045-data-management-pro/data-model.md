# Phase 1 — Data Model: 045 Data management pro

No data migration. The `corex_submission` storage is unchanged; the seam wraps it. New artifacts are mostly pure
value objects.

## DataQuery (pure VO — `Corex\Config\Data\DataQuery`)

| Field | Type | Meaning |
|---|---|---|
| `search` | string | substring match over the record's fields/summary (treated as a literal) |
| `filters` | array<string,string> | source filters, e.g. `{ form: "contact" }` |
| `sortColumn` | string | a column id the source supports (e.g. `date`); empty = default |
| `sortDir` | enum | `asc` \| `desc` |
| `page` | int | 1-based, clamped ≥ 1 |
| `perPage` | int | clamped to a sane max (e.g. ≤ 100) |

Pure: built from request params (sanitised at the controller), clamped in the VO. A source that doesn't support a
given sort column ignores it (default order) — never an error.

## DataSource (extended — `Corex\Config\Data\DataSource`)

The spec-030 contract, now query-aware:
- `rows(DataQuery $query): list<array<string,scalar>>` — the matching page of rows.
- `total(DataQuery $query): int` — the matching total (for pagination).
- `record(int $id): ?array` — one record as `{ id, date, form, fields: { key: value } }` for the detail view, or
  null if absent/forbidden.
- `columns()` / `key()` / `label()` / `delete(int)` — unchanged.

Implemented by `SubmissionsSource` (submissions) and `TableDataSource` (custom tables, spec 038). The `$wpdb`/
`WP_Query` lives in the injected readers; the query is **prepared** (`%s`/`%d`, `%i` identifiers) and **bounded**.

## SubmissionStore (seam — `Corex\Forms\Submission\SubmissionStore`)

The persistence interface for submissions:
- `save(string $formSlug, array $values): int` — persist, return id.
- `query(DataQuery $query): list<array>` + `count(DataQuery $query): int` — read for the screen.
- `find(int $id): ?array` — one record (detail).
- `delete(int $id): bool`.

**Default driver**: `PostMetaSubmissionStore` — the current `corex_submission` post + `corex_field_*` postmeta
storage (spec 007/030 refactored behind the seam; behavior unchanged). **Custom-table driver: out of scope.**

## CSV export (pure — `Corex\Config\Data\CsvWriter`)

`write(array $columns, array $rows): string` — RFC-4180: a header row from the column labels, one row per record,
every value quoted/escaped (double-quotes doubled; fields with comma/quote/newline quoted). No internal/secret
field — only the source's declared columns. The `DataExportController` streams this as a cap-gated download,
bounded to a documented row cap.

## Submission record (detail)

`{ id, date, form, fields: { key: value } }` — the detail view renders `fields` as **label → value** (the form's
field labels where known, else the key), with the form name + date. A missing value renders empty, not broken.
