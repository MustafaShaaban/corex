# Data Model: Admin data management (030)

## DataSource (interface)
| Method | Returns | Notes |
|---|---|---|
| `key()` | string | url-safe source key (e.g. `submissions`) |
| `label()` | string | human label |
| `columns()` | `list<array{id,label}>` | the table columns |
| `rows(int $page,int $perPage)` | `list<array<string,scalar>>` | one assoc row per record, keyed by column id |
| `total()` | int | total record count (for pagination) |
| `delete(int $id)` | bool | remove one record; false if not permitted/found |

## SubmissionsSource row
```json
{ "id": 42, "date": "2026-06-12 10:00", "form": "contact", "summary": "name: Sam · email: sam@…" }
```
Columns: id, date, form, summary. `delete($id)` trashes the `corex_submission` post.

## REST shapes
- `GET corex/v1/data/<source>?page=1&per_page=20` → `{ rows: [...], total: N, columns: [...] }`
- `DELETE corex/v1/data/<source>/<id>` → `{ deleted: true }`

## Rules
- All values escaped at render; summaries are plain text (no HTML).
- `manage_options` for read; nonce + `manage_options` for delete.
