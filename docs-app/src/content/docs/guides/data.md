---
title: Manage submission data
description: Search, filter, sort, export, and read form submissions — and the storage seam behind them.
---

**Corex → Data** lists every form submission (and any registered custom table, spec 038) in one cap-gated
screen. Spec 045 built the query/export/detail backend (REST + `admin-post`); spec **053** (US2) added the
matching screen controls.

## The screen

The Data screen gives an administrator, over the REST routes below:

- a **search box** (substring match, server-side);
- a **source switcher** and an accumulated **form filter**;
- **sortable column headers** (click to sort, click again to flip direction; `aria-sort` reflects it);
- **pagination** (Previous / Next, "Page X of Y") against the filtered total;
- an **Export CSV** button that downloads the *current filtered view* (bounded to 5000 rows; a note shows when
  truncated);
- a **detail drawer** (open a row → readable label→value fields, ESC to close, `—` for empty values);
- distinct **loading / error / empty / no-matches** states.

Any search/filter/sort change resets to page 1, and the Export button always reflects the controls currently
applied, so "export = what you see".

## Query: search, filter, sort, paginate

A source that implements `Corex\Config\Data\QueryableDataSource` answers a **`DataQuery`** — a search term, a
`form` filter, a sort column + direction, and pagination. The REST list route accepts them:

```
GET corex/v1/data/submissions?search=hello&form=contact&sort=date&dir=desc&page=1&per_page=20
```

The response is the spec-043 envelope: `data.columns`, `data.rows`, and the **filtered** `data.total`. The search
term is treated as a literal (the post-meta driver searches post fields; indexing meta values is a documented
limitation a custom-table driver would solve). `DataQuery` clamps `per_page` to a safe maximum.

## Export to CSV

```
GET (admin-post) corex_data_export?source=submissions&form=contact
```

Downloads the **current filtered** result as a CSV — a header row from the source's columns, one escaped row per
record (RFC-4180: commas/quotes/newlines quoted). It is `manage_options` + nonce gated, **bounded** to a row cap
so a large set never exhausts memory, and writes **only the declared columns**, so no internal field can leak.

## Read one submission

```
GET corex/v1/data/submissions/{id}
```

Returns the record as readable **label → value** fields (the field key humanised), with the form and date — not a
raw summary.

## The storage seam

Submissions persist through `Corex\Forms\Submission\SubmissionStore`. The **default driver** is the
`corex_submission` post + `corex_field_*` postmeta storage (`SubmissionRepository`) — unchanged. A **custom-table
driver** (a `TableRepository` implementation, for volume/reporting) is the documented future option and is **out of
scope** here: swapping the driver changes where data lives without touching the form submit path or the Data
screen contract.

## See also

- [Settings & feature flags](./configuration.md) — the Corex admin, where the Data screen lives.
- [Write queries](./queries.md) — the `QueryBuilder` for your own data (distinct from this admin Data screen).
