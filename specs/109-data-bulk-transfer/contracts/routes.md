# Contract: routes

All under `corex/v1`, all through the Data gateway's pipeline (nonce, ability, sanitising), all
answering CoreX's envelope `{ ok, data, message }`. A refusal is a 4xx with `message` in words
and a `code`. None exists today; the per-source routes under `/data/{source}/…` stay as they
are unless a row says otherwise.

New paths are not under `/data/`, where `/data/{source}` matches any word.

## Export of several models (slice 2)

| Method and path | Asks for | Does |
|---|---|---|
| `POST /data-exports/preview` | export right on each named model | `{ sources: [key] }` → per model: `count`, the columns the person may export, which of them are personal; the formats on offer and why PDF is not, when it is not |
| `POST /data-exports` | the same; the right of D3 for any personal column | `{ parts: [{ source, columns? }], format, separator?, personal_data_acknowledged }` → the run. `columns` left out means every column the person may export |
| `GET /data-exports` | manage data or submissions | the person's runs, newest first; everybody's with the admin ability. Each: parts, format, records, bytes, state, made by, made at, expires at, whether its file is still held |
| `POST /data-exports/{id}/advance` | the run's maker, or the admin ability | one step → `{ state, processed, total, error }` |
| `GET /data-exports/{id}/download` | the same | the file, in the envelope the single export uses |
| `DELETE /data-exports/{id}` | the same | removes the file, keeps the entry, writes the activity log |

Changed: `GET /data/{source}/exports` lists the runs that hold a part for that source.
`POST /data/{source}/exports` is unchanged in shape and gains the check of D3.

Refusals: `nothing_to_export` (every model empty), `pdf_too_many`, `personal_data_not_allowed`
(a personal column asked for without the right), `personal_data_not_acknowledged`,
`source_unavailable`.

## Import (slice 3)

No new path. Changed:

| Method and path | Change |
|---|---|
| `POST /data/{source}/imports` | accepts `after_runs: [id]`; each must be a run of the same model and person, or the request is refused. The answer's `import` gains `duplicate_rows` |
| `GET /data/{source}/imports/{id}/report` | the report lists rows already there after the rejected ones, each with where it was found |

## Making a package (slice 4)

| Method and path | Asks for | Does |
|---|---|---|
| `GET /data-packages` | manage data | what can be moved: per model its kind, count and, if it cannot be moved, why; whether files can be included; whether the person must replace personal data; what the host lacks, if anything. Then the person's packages, as the export history lists runs |
| `POST /data-packages/preview` | export right on each named model | `{ sources, include_files }` → per model `count`; `file_count`, `file_bytes`; what would not fit, if anything |
| `POST /data-packages` | the same; the right of D3 unless `replace_personal_data` | `{ sources, include_files, replace_personal_data, personal_data_acknowledged }` → the run |
| `POST /data-packages/{id}/advance` | the maker, or the admin ability | one step |
| `GET /data-packages/{id}/download` | the same | the file |
| `DELETE /data-packages/{id}` | the same | removes the file, keeps the entry |

Refusals: `not_movable`, `no_zip`, `no_room`, `personal_data_not_allowed`,
`personal_data_not_acknowledged`, `nothing_to_move`.

## Receiving a package (slice 5)

| Method and path | Asks for | Does |
|---|---|---|
| `GET /data-receipts` | manage data models | what this install is taken to be (live or not, and its mode), what the host lacks, and the receipts, newest first |
| `GET /data-receipts/uploads/{sha256}` | manage data models | how many bytes of that file are held |
| `POST /data-receipts/uploads/{sha256}` | the same | appends a part that says where it starts; answers what is held when it does not start there |
| `POST /data-receipts/uploads/{sha256}/complete` | the same | checks the whole file's hash and name → a receipt in the state `inspecting` |
| `POST /data-receipts/{id}/inspect` | the receipt's maker | one step of inspection → progress, then the preview: where and when the package was made, by whom, whether its personal data was replaced, and per model the verdict, the fields left out and the counts |
| `POST /data-receipts/{id}/confirm` | the maker; on a live install also the right to run dangerous actions | `{ update_differing: [source] }` → starts receiving. `update_differing` is refused on a live install |
| `POST /data-receipts/{id}/advance` | the maker | one step of receiving → progress, then the counts of what was added, brought up to date, arrived since and failed |
| `DELETE /data-receipts/{id}/package` | the maker, or the admin ability | removes the uploaded package and its working file; the receipt stays |

Refusals: `not_a_package`, `damaged` (a member's hash differs), `format_too_new`,
`replaced_data_on_live`, `test_data_on_live`, `preview_expired`, `not_your_receipt`,
`changed_since_preview`, `no_room`, `no_zip`.

## What every route keeps

- A file is never served by address: only by a route that checked the person.
- An id in a path that is not the person's, and that they may not manage, answers as if it did
  not exist.
- Nothing a route answers holds a value of a record, except the file itself.
