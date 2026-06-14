# Data Model: Platform Roadmap Closeout (053)

No persistent schema changes. These are the **view-layer and generated-artifact shapes** the feature operates
on — all backed by existing storage (`corex_submission` post-meta) or produced as files (the generated site).

## 1. DataView state (US2 — client-side, in `useSource`)

The React screen's working state; drives every request to the existing `corex/v1/data` routes.

| Field | Type | Notes |
|---|---|---|
| `sourceKey` | string | selected data source (e.g. `submissions`); from `config.sources` |
| `search` | string | substring term → `search` param (`sanitize_text_field` server-side) |
| `form` | string | form filter → `form` param (`sanitize_key`); empty = all |
| `sort` | string | column id → `sort` param (`sanitize_key`) |
| `dir` | `'asc' \| 'desc'` | toggles on repeated header clicks → `dir` param |
| `page` | int ≥ 1 | resets to 1 on any search/filter/sort change |
| `perPage` | int | clamped server-side by `DataQuery` (≤ max) |
| `status` | `idle \| loading \| error \| ready` | drives D5 state rendering |
| `rows` | Record[] | from envelope `data.rows` |
| `columns` | `{id,label}[]` | from envelope `data.columns` |
| `total` | int | from envelope `data.total`; drives pagination |
| `error` | string \| null | classified message when `status==='error'` |
| `detail` | DetailRecord \| null | the open drawer's record, or null |

**Validation / rules**: all params are sanitized server-side (never trusted from the client); `dir` only ever
`asc`/`desc`; page reset on filter change keeps pagination honest; the export URL is built from the **same**
`source/search/form/sort/dir` so "export = current view" holds.

## 2. DetailRecord (US2 — from `GET /data/{source}/{id}`)

Read-only, produced by the existing `QueryableDataSource::record()`.

| Field | Type | Notes |
|---|---|---|
| `id` | int | record id |
| `fields` | `{label,value}[]` | readable label→value pairs; includes the form name and submission date |
| empty values | — | rendered as an em-dash (—), never blank/`undefined` |

## 3. DiagnosticResult (US3 — from `POST /captcha/test`, reused for insights)

Returned inside the spec-043 envelope; **secret-free by construction**.

| Field | Type | Values |
|---|---|---|
| `status` | enum | `ok` · `missing_keys` · `invalid_keys` · `network_error` · `not_applicable` (captcha); insights adds `local_url` · `http_error` · `quota` · `invalid_key` · `invalid_response` |
| `message` | string | human-readable, actionable, i18n-ready; **never contains a secret** |
| `missingKeys` | string[] | (captcha) which keys to add, when `status==='missing_keys'` |

**Rules**: the UI renders only `status` + `message` (+ `missingKeys` names). No key/secret value is ever sent to
or rendered by the client (D7).

## 4. StarterSlice (US4 — generated files in the client plugin)

Emitted only when `--starter`; all identifiers client-namespaced via `SiteIdentity` (distinct from `Corex\`).

| Artifact | Generated path (client `<slug>-site`) | Shape |
|---|---|---|
| Model | `src/Models/Example.php` | value object |
| Repository | `src/Repositories/ExampleRepository.php` | the only data-access layer |
| Service | `src/Services/ExampleService.php` | business logic; injected repo |
| Controller | `src/Controllers/ExampleController.php` | route+validate → service → **043 envelope** response |
| Block | `src/Blocks/example/{block.json,index.js,style.scss}` + `ExampleRenderer.php` | dynamic, token-only, RTL |
| Option page | `src/Options/ExampleOptions.php` | AdminGuard-gated settings screen |
| Test | `tests/ExampleTest.php` | asserts the slice; passes `php -l` |
| Removal guide | `REMOVE-EXAMPLE.md` | exact files to delete to return to a clean scaffold |

## 5. StarterTheme (US4 — generated standalone block theme)

| Artifact | Path (client `<slug>` theme) | Notes |
|---|---|---|
| Theme header | `style.css` | standalone (not a child theme) |
| Tokens | `theme.json` | consumes Corex `--corex-*` tokens; client `--<prefix>-*` layer |
| Templates/parts | `templates/*.html`, `parts/{header,footer}.html` | FSE, presentation only |
| Asset sources | `assets/src/*.scss`, `assets/src/*.js` | SCSS/JS architecture; logical CSS |
| Build | `package.json` scripts (`@wordpress/scripts`) | dev source maps; minified prod; hashed `*.asset.php` |
| Asset helper | `inc/Assets.php` | `url()`/`path()`/`version()` over the build manifest (images/icons/fonts) |

**Rules**: theme = skin (Principle I); no business logic; assets load conditionally (Principle VI); tokens at
runtime (Principle V).

## 6. DocumentationSurface (US1 — the truth-tracking set)

Not code; the inventory the PR rule and sweep operate on.

| Surface | Authoring | Truth obligation |
|---|---|---|
| `README.md` | hand | public entry point; real modules + setup; no stale/false claim |
| `PROGRESS.md`, `specs/*/tasks.md` | hand | checkbox/status matches code |
| plugin/add-on `README.md` | hand | what it does / enable gives / disable removes / config / CLI / limits |
| `docs-app/.../guides/*` | hand | team guide; matches behavior |
| `docs-app/.../reference/*` | **generated** | regenerated by `docs:generate`, not hand-edited |
| `AGENTS.md`, `CLAUDE.md` | hand | agent orientation accurate |

**State transition (the rule)**: a feature PR is "done" only when each code change it makes has updated its
mapped hand-authored surface(s) in the same change (FR-003, enforced via COREX-WORKING-GUIDE + constitution DoD).
