# Quickstart: Admin data management (030)

## 1. Pest — sources + REST
```bash
vendor/bin/pest tests/Unit/Config/SubmissionsSourceTest.php tests/Unit/Config/DataControllerTest.php
```
Expected: `SubmissionsSource` shapes rows/columns from a stub reader; `DataController` is `manage_options`-gated,
deletes require a nonce, unknown source → 404. Green.

## 2. Build the admin React
```bash
npm run build --workspace=plugins/corex-config
```
Expected: `build/admin/data/index.js` compiled.

## 3. Live
```bash
wp eval 'do_action("admin_menu"); ...' --path=wp   # the Data submenu registers
# GET /wp-json/corex/v1/data/submissions with an admin nonce → {columns,rows,total}
```

## 4. Browser (env-gated)
Open Corex → Data; the submissions table renders, sortable/paginated; delete a row.
