# Implementation Plan: Admin data management (DataViews) (030)

**Branch**: `feature/030-data-admin` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary

A **Corex → Data** admin screen renders a React `@wordpress/dataviews` table of a selected **DataSource**.
Form submissions are the reference source (date/form/value-summary, list + delete); any Corex custom table
plugs into the same screen via the `DataSource` interface. A cap-gated REST controller serves the data
(`GET corex/v1/data/<source>`, `DELETE …/<id>`). The pure data-shaping is Pest-tested; the React builds via
`@wordpress/scripts`.

## Technical Context
PHP 8.3 + React (block-editor data packages). Deps: spec-007 submissions (CPT `corex_submission`), spec-011
TableRepository, spec-018 build, `@wordpress/dataviews`. Tests: Pest (sources + REST cap/shape) + build.
Constraints: cap-gated (`manage_options`); deletes nonce-verified; output escaped/i18n/RTL; sources expose no
secrets; pure shaping headless-testable.

## Constitution Check (v1.2.1)
- [x] III/IV — `DataSource`/`DataRegistry` pure + injected; `DataController` thin REST; `DataAdminScreen`
  renders + gates (shared `AdminGuard`). No `new` of services in methods.
- [x] VII — REST `manage_options`-gated; DELETE nonce-verified; sources return safe, summarised data only.
- [x] VIII — strings translatable; the React table is RTL-aware (Starlight/WP admin is RTL-ready).
- [x] X — implements spec 030.
- [x] Guard Gate/DoD — wp-guard (REST cap/nonce, escaping) + clean-code + test-guard; Pest for sources/REST;
  React built. Docs + docs-app updated.

**Gate**: PASS.

## Design (in `plugins/corex-config/src/Data/`)
- `DataSource` (interface): `key():string`, `label():string`, `columns():array`, `rows(int $page,int $perPage):array`, `total():int`, `delete(int $id):bool`.
- `DataRegistry`: registered sources by key (`register`, `all`, `find`).
- `SubmissionsSource`: reads `corex_submission` posts (via an injected reader for testability) → rows
  `{id,date,form,summary}`; `delete` trashes the post.
- `DataController` (REST): `GET corex/v1/data/(?P<source>[\w-]+)` (list+paginate, `manage_options`),
  `DELETE corex/v1/data/(?P<source>[\w-]+)/(?P<id>\d+)` (nonce + `manage_options`).
- `DataAdminScreen`: submenu under `corex-settings`; enqueues the built admin React; prints a mount node +
  the REST root + nonce.
- React `src/admin/data/index.js`: mounts DataViews, fetches the source list + rows, supports delete.

## FR → component map
| FR | Built in |
|---|---|
| FR-001/006 screen | `Data/DataAdminScreen.php` + `src/admin/data/index.js` (DataViews) |
| FR-002 submissions | `Data/SubmissionsSource.php` |
| FR-003 abstraction | `Data/{DataSource,DataRegistry}.php` + `SubmissionsSource` reference |
| FR-004 REST | `Data/DataController.php` (cap + nonce) |
| FR-005 shaping tests + build | `tests/Unit/Config/SubmissionsSourceTest.php`, `DataControllerTest.php`; `npm run build` |

## Project Structure (new)
```text
plugins/corex-config/src/Data/{DataSource,DataRegistry,SubmissionsSource,DataController,DataAdminScreen}.php
plugins/corex-config/src/admin/data/index.js   (+ build entry)
plugins/corex-config/package.json              (build script if absent)
tests/Unit/Config/{SubmissionsSourceTest,DataControllerTest}.php
```

## Phase 0/1 artifacts
research.md (DataSource abstraction; CPT-vs-table sources; DataViews choice) · data-model.md (source/row shapes)
· contracts/data-rest-contract.md · quickstart.md.

## Complexity Tracking
The `DataSource` abstraction is justified — it's what makes one screen serve both submissions and custom
tables (the feature's point). React-visual confirmation is env-gated.
