---
title: Write queries
description: The fluent, capped, safe QueryBuilder — meta, taxonomy, dates, search, pagination, eager loading.
---

The QueryBuilder is a **pure arg-builder**: it produces a safe, capped `WP_Query` args
array and (via the executor) a `Collection` of Models. It never instantiates `WP_Query`
itself, and every value is passed as data into a `*_query` clause — never interpolated.

```php
$jobs->query()
    ->where('salary', 50000, '>=')        // declared field → meta_query
    ->whereBetween('salary', 50000, 120000)
    ->whereTax('department', 'engineering', 'slug')
    ->whereDate('2026-01-01')             // post-date range
    ->search('engineer')
    ->orderByNumeric('salary', 'DESC')
    ->paginate(20, 1)
    ->with('company')                     // eager-load belongs-to (no N+1)
    ->get();                              // Collection<Job> — empty, never null
```

## Methods

| Method | Builds |
|---|---|
| `where($field, $value, $compare)` | declared field → `meta_query`; core field → arg |
| `orWhere(...)` / `metaRelation('OR')` | OR across meta conditions |
| `whereMeta($key, $value, $compare, $type)` | a raw-meta condition with a SQL type |
| `whereBetween($field, $min, $max)` | a numeric `BETWEEN` range |
| `whereTax($tax, $terms, $field, $op)` / `taxRelation()` | taxonomy clauses |
| `whereDate($after, $before, $inclusive)` | a `date_query` range |
| `search($term)` | full-text `s` |
| `orderBy($field, $dir)` | `meta_value` (declared) / core |
| `orderByNumeric($field, $dir)` | `meta_value_num` (declared, numeric sort) |
| `paginate($perPage, $page)` | capped per-page + `paged` + found-rows |
| `with($relation)` | eager-load a belongs-to relation |

An unbounded query is capped at `config('query.max')` (default 500) — never
`posts_per_page => -1`. Eager loading runs a bounded number of queries (belongs-to is two
queries, not N+1).

## Custom tables

Many-row entities that are **rows in a `{prefix}corex_*` table**, not posts, use the
`TableRepository` (typed CRUD + prepared `where()`), not `WP_Query`. Cross-table joins are
that repository's boundary.
