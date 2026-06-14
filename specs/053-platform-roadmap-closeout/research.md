# Research: Platform Roadmap Closeout (053)

Phase 0 decisions. Each resolves a "how" the spec left open, anchored to the **existing, verified** code so the
plan adds consuming surfaces only. No `NEEDS CLARIFICATION` remained after specifying.

## D1 — Data screen: extend the existing React app, don't rewrite the stack

- **Decision**: Keep `plugins/corex-config/src/admin/index.js` as the host; extend its `useSource` hook to send
  `search/form/sort/dir` (the params `DataController::queryFrom` already sanitizes) alongside `page/per_page`,
  and add the controls. Keep the `@wordpress/dataviews`-when-present / table-fallback split.
- **Rationale**: The backend (`GET /data/{source}`) already answers the full `DataQuery`; the runtime envelope
  (043) is already consumed. Rewriting to a different UI stack would add risk for no gain.
- **Alternatives**: A brand-new screen (rejected — throws away working wiring); server-rendered table (rejected
  — loses the live filter/sort UX and the DataViews path).

## D2 — Sort/filter/search are server-side, driven by existing params

- **Decision**: Column header clicks set `sort` (a column id `sanitize_key`-safe) + toggle `dir`
  (`asc`/`desc`); the search box sets `search`; a `<select>` sets `form`. Each change resets to page 1 and
  re-fetches. The fallback table gets clickable headers too.
- **Rationale**: `queryFrom` already reads exactly these params; `SubmissionsSource`/`WpSubmissionsReader`
  already apply substring search, form filter, and date sort, prepared + bounded.
- **Alternatives**: Client-side sort/filter (rejected — wrong for paginated data; would sort only the current
  page and disagree with the export).

## D3 — CSV export: link to the existing `admin_post` handler with the current query

- **Decision**: The Export button builds the `admin_post.php?action=corex_data_export` URL with the current
  `source` + `search/form/sort/dir` params + the `corex_data_export` nonce, and navigates to it (a real file
  download). Not a `fetch` (the browser must save the file).
- **Rationale**: `DataExportController` is an `admin_post` streaming handler (cap + nonce, bounded to 5000 rows,
  declared columns only, formula-injection guarded). Reusing it keeps "export = current view" truthful and
  secret-safe. The button passes the same filters the list shows.
- **Truncation note**: when the result exceeds the cap, the handler already bounds output; the UI states "first
  N rows exported" so the admin is not misled (FR-009).
- **Alternatives**: A new REST export returning a blob (rejected — duplicates a tested handler; worse for large
  files); client-side CSV from loaded rows (rejected — would export only the current page).

## D4 — Detail view: a drawer over `GET /data/{source}/{id}`

- **Decision**: Opening a row fetches `GET /data/{source}/{id}` and shows its `record()` label→value fields
  (incl. form + date) in an accessible drawer/modal (focus-trapped, ESC to close, `aria` labelled). Empty
  values render as an em-dash, not blank.
- **Rationale**: The detail route + `record()` readable fields already exist (spec 045 US3 backend). A drawer is
  the lighter, mobile-friendly pattern and matches WP admin conventions.
- **Alternatives**: Inline row expansion (rejected — cramped for many fields); a separate admin page (rejected —
  extra navigation for a read-only peek).

## D5 — Loading / error / empty are three distinct states

- **Decision**: `useSource` tracks `status: idle|loading|error|ready`. The view renders a spinner on `loading`,
  an actionable message + retry on `error` (envelope `ok===false` or a network failure), the existing "No data
  yet" only when `ready && rows.length===0`, and a distinct "No matches" when a search/filter is active.
- **Rationale**: FR-011/SC-003 require the three states be visibly different; today only a static empty line
  exists and failures are silent.
- **Alternatives**: A single combined placeholder (rejected — hides failures, the exact audit complaint).

## D6 — Captcha Test button is the real US3 gap; insights already has its button

- **Decision**: Add `addons/corex-captcha/assets/captcha-admin.js` — a small vanilla module (over
  `window.Corex.api`, no build) that POSTs to `corex/v1/captcha/test` on button click, shows a busy state, and
  renders the classified, secret-free message from `CaptchaDiagnostic` (ok / missing_keys / invalid_keys /
  network_error / not_applicable). `CaptchaServiceProvider` enqueues it on the settings screen with a nonce.
  For **insights**, the "Run check" button **already exists** in `insights.js` (POST `/insights/run`) and shows
  the `PsiDiagnostic` classification — so US3's insights work is *verification + message polish*
  (local-url / missing-optional-key / invalid-key / quota wording), not a new button.
- **Rationale**: The audit found corex-captcha has **no JS asset** (the genuine gap), while insights JS already
  wires a check. Building only what's missing keeps scope honest.
- **Alternatives**: A React control (rejected — heavier than needed; the settings screen is server-rendered);
  putting the captcha button JS in corex-config (rejected — spec 044 placed the test controller in the captcha
  add-on for domain ownership; the JS belongs with it).

## D7 — Secret safety is structural, not a runtime filter

- **Decision**: The UI renders only the diagnostic's classified `status` + `message`; it never reads or echoes
  any key/secret field. The diagnostics are already "secret-free by construction" (044) — the UI preserves that
  by displaying only their output.
- **Rationale**: FR-014/SC-005 — no secret may ever appear; the safest design never sends the secret to the
  client at all (the test runs server-side and returns a verdict).

## D8 — `make:site --starter`: a `starter/` stub set mirroring `ApiResourceScaffolder`, gated by a flag

- **Decision**: Author `packages/cli/stubs/starter/**` (client-namespaced model · repository · service ·
  controller-on-envelope · dynamic block · option page · test · `REMOVE-EXAMPLE.md`) **plus** a standalone
  starter block theme (`style.css`, `theme.json` consuming Corex tokens, templates/parts, `assets/src/*.scss` +
  `*.js`, a build config, and an asset url/path/version helper). `SiteScaffolder::scaffold()` gains a
  `starter` option: when true it adds the slice + theme assets to the render-all-before-write map; default and
  `--minimal` omit them. `MakeCommand::runSite` parses `--starter` and `--minimal` into the options array.
- **Rationale**: Mirrors the proven render-all-before-write + identity-derivation pattern; keeps the lean
  scaffold the default; satisfies FR-018..023 without a new engine.
- **Alternatives**: Generate the slice via the existing `make:*` generators post-scaffold (rejected — couples
  `make:site` to a WP runtime; the scaffolder is pure/headless-testable and must stay so); a separate
  `make:starter` command (rejected — the user asked for one `--starter` flag on `make:site`).

## D9 — Starter theme asset architecture: `@wordpress/scripts` + a manifest helper

- **Decision**: The starter theme ships `@wordpress/scripts` build scripts (`build`/`start`), `src/` SCSS+JS
  compiled to `build/` with `*.asset.php` (hashed deps = cache-busting), source maps only under `start`/dev,
  minified `build` output, and a generated `Assets` helper (`url()`/`path()`/`version()` reading the manifest)
  for images/icons/fonts. Conditional enqueue per template/block.
- **Rationale**: Matches the framework's own build pipeline (spec 018) and asset-manager decisions (spec 047),
  so the generated site is idiomatic Corex. Satisfies FR-019 (conditional load, dev-only maps, minified prod,
  versioned cache-busting, url/path helpers).
- **Alternatives**: A raw Vite/webpack config (rejected — diverges from the framework's `@wordpress/scripts`
  standard); no build, plain enqueues (rejected — fails the "professional asset setup" requirement).

## D10 — US1 docs sweep: hand-authored only; generated reference left to its generator

- **Decision**: The stale-phrase sweep + the feature-PR docs rule apply to hand-authored surfaces (README,
  PROGRESS, tasks, plugin/add-on READMEs, docs-app guides, agent docs). The generated class reference
  (`docs-app/.../reference/*`, produced by `docs:generate`) is regenerated, not hand-edited. The PR rule names
  the mapping: code change → its plugin/add-on README + the matching docs-app guide + (if public-facing) README.
- **Rationale**: FR-004/edge case — avoid "correcting" generated pages by hand and reintroducing drift.
