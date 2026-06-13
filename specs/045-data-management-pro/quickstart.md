# Quickstart — Validate 045 Data management pro

## Prerequisites

- The monorepo in a working WP 7.0+ install with corex-core/config/forms active and some `corex_submission`
  records (the live install has 30+). `composer install` done.

## 1. Unit — pure cores (Pest, headless)

```bash
composer test -- --filter="DataQuery|CsvWriter|SubmissionsSource|PostMetaSubmissionStore"
```

**Expected**:
- `DataQuery` clamps page ≥ 1 and per_page ≤ the max; carries search/filters/sort.
- `CsvWriter` produces a header row + escaped rows (a value with a comma/quote/newline is quoted, embedded quotes
  doubled); zero rows → header only; only the declared columns appear (no secret).
- `SubmissionsSource` answers a `DataQuery` (search narrows, form filter narrows, sort orders) and `record(id)`
  returns label → value fields; the `$wpdb`/`WP_Query` is in the injected reader.
- `PostMetaSubmissionStore` shapes save/query/find/delete over the `corex_submission` storage.

## 2. Contract — query/detail/export, no secret (SC-005)

```bash
curl -s "$SITE/wp-json/corex/v1/data/submissions?search=hello&form=contact&sort=date&dir=desc&page=1&per_page=20" -H "X-WP-Nonce: $NONCE" | jq
curl -s "$SITE/wp-json/corex/v1/data/submissions/12" -H "X-WP-Nonce: $NONCE" | jq
curl -s "$SITE/wp-json/corex/v1/data/submissions/export?form=contact" -H "X-WP-Nonce: $NONCE" -o submissions.csv && head submissions.csv
```

**Expected**: envelope-shaped list (filtered `total`), a label→value detail, and a spreadsheet-openable CSV; no
secret/internal field; an unauthenticated request is refused (403).

## 3. Browser smoke (environment-gated)

1. Corex → Data: search narrows the table; the form filter narrows it; a column sort reorders; paging keeps the
   query; the empty result shows an empty state.
2. Export downloads a CSV of the filtered set that opens cleanly in a spreadsheet (commas/quotes intact).
3. Open a submission → readable label → value fields, form + date.
4. RTL (Arabic) → the table, controls, and detail mirror correctly.

## 4. Guard Gate

```text
clean-code-guard · wp-guard (prepared search, cap+nonce, escaped, NO secret, bounded export) ·
test-guard (Pest/Jest) · docs-guard (queries/data guide + the store-seam boundary)
```

**Done when**: §1–2 pass headlessly, Guard Gate clean, docs updated, §3 confirmed or recorded env-gated;
PROGRESS + DECISIONS updated; NEXT STEP present.
