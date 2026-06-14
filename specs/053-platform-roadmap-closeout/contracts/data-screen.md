# Contract: Data admin screen (US2)

The Data screen consumes **existing** routes. This contract fixes the client↔server interaction so the UI and
the export agree on "current view". No route is added or changed.

## Routes consumed (already implemented)

| Purpose | Method + path | Params | Auth |
|---|---|---|---|
| List + query | `GET /wp-json/corex/v1/data/{source}` | `search`, `form`, `sort`, `dir`, `page`, `per_page` | `permission_callback` (cap) + REST nonce |
| Detail | `GET /wp-json/corex/v1/data/{source}/{id}` | — | cap + nonce |
| Delete | `DELETE /wp-json/corex/v1/data/{source}/{id}` | — | cap + nonce |
| Export | `POST /wp-admin/admin-post.php?action=corex_data_export` | `source`, `search`, `form`, `sort`, `dir`, `_wpnonce=corex_data_export` | `manage_options` + nonce |

## Request/response shapes (envelope, spec 043)

- **List** → `200` `{ ok:true, data:{ rows:[{id,...}], columns:[{id,label}], total:int } }`; unknown source →
  `404` `{ ok:false, error:'unknown_source', message }`.
- **Detail** → `200` `{ ok:true, data:{ id, fields:[{label,value}] } }`; missing → `404`
  `{ ok:false, error:'not_found', message }`.
- **Export** → a `text/csv` download (Content-Disposition attachment); cap+nonce failure → `403` `wp_die`.

## UI obligations (this feature)

1. Sending `search/form/sort/dir` MUST reset `page` to 1 and re-fetch.
2. The Export URL MUST carry the **same** `source/search/form/sort/dir` currently applied (export = visible
   view) + the `corex_data_export` nonce; trigger as a navigation (file download), not `fetch`.
3. Sortable headers MUST set `sort=<column.id>` and toggle `dir`; the active column + direction MUST be
   indicated accessibly (`aria-sort`).
4. The three states MUST be distinct: `loading` (spinner, `aria-busy`), `error` (actionable message + retry),
   `ready` (table). Empty splits into "No data yet" (no filter) vs "No matches" (filter active).
5. Detail opens in a focus-trapped drawer/modal (ESC closes, labelled), empty values shown as `—`.
6. All controls keyboard-operable + labelled (WCAG 2.2 AA), strings via `@wordpress/i18n`, RTL via logical CSS,
   **zero console errors**.

## Test contract

- **Jest**: search sets `search` + resets page; header click sets `sort`/toggles `dir`; pagination sets `page`;
  Export builds the URL with current params + nonce; detail open fetches `/{id}` and renders `fields`; the
  three states render for loading/error/empty. (`window.Corex.api` + `wp.*` mocked.)
- **Playwright (052, env-gated)**: seed submissions → search/sort/paginate/export/detail flow + console-clean.
