# Contract: data REST + admin screen

## `GET corex/v1/data/<source>`
- Permission: `current_user_can('manage_options')`.
- Query: `page` (>=1), `per_page` (1..100).
- 200: `{ "columns": [{id,label}], "rows": [..], "total": N }`. Unknown source → 404.

## `DELETE corex/v1/data/<source>/<id>`
- Permission: `manage_options` + a valid REST nonce (`X-WP-Nonce`).
- 200: `{ "deleted": true }`. Missing/forbidden → 403/404. Never deletes without the nonce.

## Admin screen
- Submenu under `corex-settings` (`corex-data`), `manage_options`.
- Enqueues the built `admin/data` React; prints the mount node + REST root + nonce.
- Source switcher lists `DataRegistry::all()`; the table renders the selected source.
