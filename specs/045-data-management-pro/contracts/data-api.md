# Contract — Data query, export & detail (REST, envelope-shaped)

All routes are `manage_options` + REST-nonce gated and answer with the spec-043 `ResponseEnvelope`. No
internal/secret field appears in any response.

## List / query

`GET corex/v1/data/{source}?search=&form=&sort=&dir=&page=&per_page=`

```json
{ "ok": true, "message": "", "data": {
  "columns": [ { "id": "date", "label": "Date" }, … ],
  "rows":    [ { "id": "12", "date": "…", "form": "contact", "summary": "…" }, … ],
  "total":   137
} }
```

- `search` is a substring literal (prepared/escaped); `form` filters by slug; `sort`/`dir` order a supported
  column; `page`/`per_page` paginate (per_page clamped). `total` reflects the **filtered** set.

## Detail

`GET corex/v1/data/{source}/{id}`

```json
{ "ok": true, "message": "", "data": {
  "id": "12", "date": "…", "form": "contact",
  "fields": [ { "label": "Name", "value": "Mustafa" }, { "label": "Email", "value": "m@x.com" }, … ]
} }
```

- `fields` are label → value (form labels where known, else the key); missing value → empty string. No secret.

## Export

`GET corex/v1/data/{source}/export?search=&form=&sort=&dir=` → a CSV **download**
(`Content-Type: text/csv`, `Content-Disposition: attachment`).

- The CSV header row = the source's column labels; one row per record of the **current filtered** result.
- RFC-4180 escaping (quotes doubled; comma/quote/newline fields quoted). Zero records → header-only CSV.
- Bounded to a documented row cap / streamed; cap+nonce gated; no internal/secret field.

## Security (all)

- `permission_callback` enforces `manage_options` + a valid REST nonce; the search term is prepared/escaped in the
  reader (no injection). Responses carry only the source's declared columns/fields — never a secret.
