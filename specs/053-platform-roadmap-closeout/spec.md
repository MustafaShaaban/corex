# Feature Specification: Platform Roadmap Closeout

**Feature Branch**: `feature/053-platform-roadmap-closeout`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Close the gap between Corex's 'roadmap 043–052 COMPLETE / v0.25.0' claim and what
is actually implemented, by correcting false completion claims and finishing the partially-built user-facing
tails: documentation honesty, the 045 Data admin React UI, the 044 captcha + insights test buttons, and the
049 `make:site --starter`/`--minimal` slice."

## Overview

A code-grounded audit (2026-06-14) found that several features marked "complete / released" in `PROGRESS.md`
shipped only their **backend** — the user-facing surface that makes them usable was never built, and some
documentation and task checkboxes assert completeness the code does not support. This feature closes those
specific, verified gaps. It builds **no new architecture**: every backend it needs already exists and is
tested. The work is the consuming UI, the CLI starter slice, and the truthful documentation.

This spec deliberately **excludes** new design-system component atoms (deferred to `054-corex-full-dls`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Honest documentation and reconciled status (Priority: P1) 🎯 MVP

A newcomer (developer, evaluator, or teammate) opens the project's public entry point and reads an accurate
description of what Corex is and what works today — not "bootstrap stage, no framework code yet" (which is
false). A maintainer reading `PROGRESS.md` and the per-spec `tasks.md` files sees status that matches the
code: unfinished tails are marked unfinished, and no checkbox claims a capability the code lacks.

**Why this priority**: Integrity first. The false "complete" claims are what caused the gap to hide; correcting
them is cheap, unblocks trust, and prevents the same drift recurring. It is independently valuable even if
nothing else in this feature ships.

**Independent Test**: Read `README.md` — it accurately states the framework's real, shipped capabilities and
contains no "bootstrap stage / no framework code yet" phrasing. Cross-check three previously-contradicted
checkboxes (049 `--starter`/`--minimal` flag task; 045 Data React UI tasks) against the code: each checkbox's
state now matches reality.

**Acceptance Scenarios**:

1. **Given** the root `README.md`, **When** a newcomer reads it, **Then** it describes Corex as a working,
   released framework with its real modules, the read-first hierarchy, and accurate local-setup steps — with no
   stale "bootstrap / no framework code" claim and no inaccurate completion/version claims.
2. **Given** `PROGRESS.md` and the affected `tasks.md` files (045, 049), **When** their checkboxes are compared
   to the code, **Then** every checkbox state is truthful (a task is checked only if the code satisfies it).
3. **Given** the project governance docs, **When** a contributor opens a feature PR, **Then** a written rule
   requires that PR to update the relevant docs (root README, docs-app, plugin/add-on README, agent docs) in the
   same change, and the rule names which docs surface maps to which kind of change.
4. **Given** a documentation sweep, **When** stale phrases are searched for (e.g. "bootstrap stage", "no
   framework code yet", outdated version/completion claims), **Then** none remain that misrepresent the code.

---

### User Story 2 - A fully usable Data admin screen (Priority: P1)

A site administrator opens **Corex → Data** to work with collected records (form submissions today, any
registered source tomorrow). They can search the records, narrow them by source/form, sort by a column,
page through results, export the current filtered view to CSV, and open a single record to read its full
label→value detail — and the screen tells them clearly when it is loading, when something failed, and when
there is simply nothing to show.

**Why this priority**: The backend for all of this already exists and is tested (query/search/sort/filter, a
bounded CSV export route, a per-record detail route). The screen currently uses none of it — it only paginates
and deletes. This is the single highest user-value gap and the lowest risk (no new server work).

**Independent Test**: On the Data screen with seeded submissions: type in the search box and the list narrows;
choose a source/form filter and the list narrows; click a sortable column header and the order changes; page
through results; click Export and a CSV of the **current filtered query** downloads; open a row and a readable
detail view (label→value, including the form name and date) appears; with no matching records an empty state
shows; while fetching a loading state shows; on a failed request an actionable error shows.

**Acceptance Scenarios**:

1. **Given** the Data screen, **When** the admin types a search term, **Then** the listed records narrow to
   matches and the result count/pagination updates accordingly.
2. **Given** more than one source or multiple forms, **When** the admin selects a source/form filter, **Then**
   only records for that source/form are shown.
3. **Given** a sortable column, **When** the admin clicks its header, **Then** the records reorder by that
   column and the sort direction toggles on repeated clicks.
4. **Given** a filtered/searched/sorted view, **When** the admin clicks Export, **Then** a CSV reflecting that
   exact current view downloads, containing only the displayed columns (no secret/internal fields).
5. **Given** a record row, **When** the admin opens its detail view, **Then** a readable label→value panel
   (drawer or modal) shows every field plus the form name and submission date, with graceful handling of empty
   values.
6. **Given** the screen, **When** data is loading, has failed to load, or is empty, **Then** a distinct,
   human-readable loading, error, or empty state is shown (not a blank screen or a silent failure).
7. **Given** the screen, **When** rendered, **Then** all controls are keyboard-operable, labelled for assistive
   technology (WCAG 2.2 AA), translation-ready, and RTL-correct, and the browser console shows no errors.

---

### User Story 3 - Working "Test" buttons for captcha and insights (Priority: P2)

An administrator configuring an integration that needs a third-party key (captcha provider; PageSpeed Insights)
clicks a **Test** / **Check** button next to that setting and immediately sees whether the configuration works —
a clear success, or an actionable, secret-safe failure that tells them exactly what to do next (e.g. "add a site
key", "this key is invalid", "this URL is local and cannot be checked"). Today the server-side verification
exists but no button is wired to it.

**Why this priority**: The diagnostic backends (captcha test controller; PSI/insights diagnostics with failure
classification) already exist and are tested; without a button they are invisible. It is high-value for
configuration UX but ranks below the Data screen because it affects fewer day-to-day tasks.

**Independent Test**: On the settings screen, with a captcha provider configured, click **Test** → a success or
classified failure message appears (sourced from the existing REST route), never exposing a secret. With keys
missing, the message states precisely which key to add. Repeat for the insights/PSI **Check** button (including
the local-URL and missing-optional-key cases).

**Acceptance Scenarios**:

1. **Given** a configured captcha provider, **When** the admin clicks **Test**, **Then** a success or a
   classified, actionable failure message is shown, and no secret value is ever rendered or logged.
2. **Given** a captcha provider with a missing required key, **When** the admin clicks **Test**, **Then** the
   message names exactly which key is missing and how to obtain/enter it.
3. **Given** the insights/PSI panel, **When** the admin clicks **Check**, **Then** the result distinguishes
   success, a local/private URL that cannot be measured, a missing-but-optional API key (recommended, not
   required), an invalid key, and a network/quota error — each with an actionable message.
4. **Given** either button, **When** a request is in flight, **Then** the button shows a busy/disabled state and
   re-enables on completion; results are announced accessibly; the console shows no errors; strings are i18n-ready.

---

### User Story 4 - `make:site --starter` produces a runnable example (Priority: P2)

An agency developer runs `wp corex make:site Acme --starter` and receives a **client site they can activate and
learn from immediately**: a client-namespaced plugin containing one complete vertical slice (model → repository
→ service → controller on the spec-043 response envelope → a dynamic block → an option page → a test), plus a
standalone starter block theme that consumes Corex `theme.json` tokens and ships a professional SCSS/JS asset
setup — and a clear "how to remove this example" guide so the slice can be deleted once understood. Running
without `--starter` (or with `--minimal`) produces the lean plugin+theme+governance scaffold only.

**Why this priority**: It completes the `make:site` capstone whose `--starter` slice was never built (the
generated plugin currently has only empty folders), and gives teams a correct, copyable starting point. It
ranks P2 because the lean scaffold already works; the starter is additive.

**Independent Test**: `wp corex make:site Acme --starter` generates a plugin whose example slice passes `php -l`
on every generated PHP file and whose theme builds; the generated README explains how to remove the example.
`wp corex make:site Acme --minimal` (and the default) generate the same scaffold **without** the example slice.
Re-running without `--force` does not overwrite. All generation is client-namespaced (distinct from `Corex\`).

**Acceptance Scenarios**:

1. **Given** `make:site <Name> --starter`, **When** it runs, **Then** it generates a client-namespaced example
   slice — model, repository, service, controller (using the response envelope), a dynamic block, an option
   page, and a matching test — plus a standalone starter block theme with an SCSS/JS asset architecture
   (conditional loading, dev-only source maps, minified production output, manifest/versioned cache-busting,
   and asset url/path helpers for images/icons/fonts).
2. **Given** the generated starter, **When** a developer reads it, **Then** a "how to remove this example"
   guide explains exactly which files to delete to return to a clean scaffold.
3. **Given** `make:site <Name>` with no flag, or with `--minimal`, **When** it runs, **Then** the example slice
   is **omitted** and only the plugin+theme+governance scaffold is produced.
4. **Given** the CLI command, **When** `--starter`/`--minimal`/`--plugin-only`/`--theme-only`/`--force` are
   passed, **Then** each flag is recognized and behaves as documented (no flag silently ignored).
5. **Given** any generated PHP, **When** linted, **Then** it passes `php -l`; **and** re-running without
   `--force` does not overwrite existing files.

---

### Edge Cases

- **Data search/filter with zero matches** → the empty state appears (distinct from the not-yet-loaded state).
- **Data export of a very large filtered set** → export stays bounded to the documented row cap; the admin is
  told if the export was truncated.
- **Data detail for a record with missing/empty fields** → labels still render; empty values are shown
  gracefully (no "undefined"/blank-row confusion).
- **Captcha/insights Test clicked with no provider selected or all keys blank** → the message guides setup
  rather than reporting a generic failure.
- **Insights Check against `localhost`/a private IP** → classified as "local URL, cannot be measured", not a
  network error.
- **`make:site --starter` into a directory that already has the site** → without `--force`, nothing is
  overwritten; the command reports it was skipped.
- **`make:site` with a name that normalizes to a reserved identifier (e.g. `corex`)** → refused with a clear
  message (existing identity guard).
- **A docs sweep finds a stale phrase inside generated/derived docs** → the rule and sweep account for which
  docs are hand-authored vs generated, so generated reference pages are not "corrected" by hand.

## Requirements *(mandatory)*

### Functional Requirements

**Documentation honesty (US1)**

- **FR-001**: The root `README.md` MUST accurately present Corex as a working, released framework — its real
  modules, the read-first source-of-truth hierarchy, accurate local-development steps — and MUST NOT contain the
  "bootstrap stage / no framework code yet" claim or any inaccurate completion/version statement.
- **FR-002**: `PROGRESS.md` and the affected `tasks.md` files (at least specs 045 and 049) MUST be reconciled so
  every status/checkbox truthfully reflects the code; a checkbox MUST NOT assert a capability the code lacks.
- **FR-003**: The governance documentation MUST add an enforceable rule that every feature PR updates the
  relevant documentation surfaces (root README, docs-app, plugin/add-on README, agent docs) in the same change,
  and MUST state which documentation surface corresponds to which kind of change.
- **FR-004**: A documentation sweep MUST remove or correct stale/misleading phrases (e.g. "bootstrap stage",
  "no framework code yet", outdated version or completion claims) across the hand-authored docs set, while
  leaving generated reference pages to their generator.

**Data admin screen (US2)**

- **FR-005**: The Data screen MUST let an admin search records by a text term, narrowing results server-side.
- **FR-006**: The Data screen MUST let an admin filter records by source and, where applicable, by form.
- **FR-007**: The Data screen MUST provide sortable columns whose sort is applied server-side, with toggleable
  direction.
- **FR-008**: The Data screen MUST paginate results and reflect the true total for the current query.
- **FR-009**: The Data screen MUST provide an Export action that downloads a CSV of the **current filtered/
  searched/sorted query**, limited to the displayed columns and excluding any secret/internal field, bounded to
  the documented row cap, and indicating when output was truncated.
- **FR-010**: The Data screen MUST let an admin open a single record's detail as a readable label→value view
  (including the form name and submission date), handling empty values gracefully.
- **FR-011**: The Data screen MUST present distinct, human-readable loading, error, and empty states.
- **FR-012**: All Data screen controls MUST be keyboard-operable and labelled for assistive technology (WCAG
  2.2 AA), translation-ready, RTL-correct, and MUST produce no browser console errors.

**Test buttons (US3)**

- **FR-013**: The settings UI MUST provide a captcha **Test** control that invokes the existing verification
  route and displays a success or classified, actionable failure message.
- **FR-014**: The captcha and insights diagnostics surfaced in the UI MUST NOT render or log any secret value.
- **FR-015**: When a required key is missing, the message MUST name exactly which key to add and how.
- **FR-016**: The insights/PSI UI MUST provide a **Check** control whose result distinguishes success, a
  local/private URL, a missing-optional (recommended) API key, an invalid key, and a network/quota error — each
  with an actionable message.
- **FR-017**: Each Test/Check control MUST show a busy state while in flight, re-enable on completion, announce
  results accessibly, and keep its strings translation-ready.

**`make:site` starter slice (US4)**

- **FR-018**: `make:site` MUST recognize a `--starter` flag that additionally generates a client-namespaced
  example vertical slice: a model, a repository, a service, a controller using the spec-043 response envelope, a
  dynamic block, an option page, and a matching automated test.
- **FR-019**: The `--starter` output MUST include a standalone starter block theme that consumes Corex
  `theme.json` tokens (not a child theme) and provides an SCSS/JS asset architecture with conditional asset
  loading, source maps in development only, minified production assets, manifest/versioned cache-busting, and
  asset url/path helpers for images, icons, and fonts.
- **FR-020**: The `--starter` output MUST include a "how to remove this example" guide identifying exactly which
  files to delete to return to a clean scaffold.
- **FR-021**: The default `make:site` and the `--minimal` flag MUST produce the plugin+theme+governance scaffold
  **without** the example slice.
- **FR-022**: `make:site` MUST recognize and correctly apply `--starter`, `--minimal`, `--plugin-only`,
  `--theme-only`, and `--force`; no passed flag may be silently ignored.
- **FR-023**: All generated PHP MUST pass `php -l`; generation MUST be render-all-before-write (no half-written
  site) and MUST NOT overwrite existing files without `--force`; all generated identifiers MUST be
  client-namespaced and distinct from the `Corex\` framework namespace.

**Cross-cutting**

- **FR-024**: This feature MUST NOT add new design-system component atoms (deferred to `054`), Excel/PDF export,
  or AVIF/CDN/Azure Blob storage; where those are referenced they MUST be documented as future increments.
- **FR-025**: Every change MUST satisfy the constitution's Definition of Done (relevant guard(s) clean, tests
  green, i18n-ready, RTL-verified, WCAG 2.2 AA for UI, docs updated in the same change).

### Key Entities *(include if feature involves data)*

- **Data record (row)**: one entry from a registered data source (e.g. a form submission) — an id, a set of
  displayable column values, and, in detail view, the full label→value field set plus form name and date.
- **Data source**: a registered, queryable provider of records (form submissions today; custom-table sources
  later) exposing columns, total count, query, and a single-record lookup.
- **Diagnostic result**: the outcome of a captcha/insights test — a status (ok / missing-key / invalid-key /
  local-url / network-or-quota error / not-applicable) plus an actionable, secret-free message.
- **Generated client site**: a scaffolded plugin + standalone theme + governance docs under a client namespace;
  with `--starter`, additionally an example vertical slice and asset architecture.
- **Documentation surface**: a place where claims about Corex live (root README, docs-app pages, plugin/add-on
  READMEs, agent docs, PROGRESS/tasks) that must stay truthful and in sync with code.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A newcomer reading the root README can correctly state what Corex is and name its real modules
  without consulting the code; zero "bootstrap stage / no framework code yet" or contradicted-completion claims
  remain.
- **SC-002**: 100% of the previously-contradicted task checkboxes (045 Data UI tasks; 049 `--starter`/`--minimal`
  flag task) match the code after this feature.
- **SC-003**: On the Data screen, an admin can search, filter, sort, paginate, export the current view to CSV,
  and open a record's detail — all six — and sees correct loading, error, and empty states for each.
- **SC-004**: An exported CSV contains only displayed columns and no secret/internal field in 100% of cases.
- **SC-005**: Clicking captcha **Test** and insights **Check** each returns a result with an actionable message
  for every classified outcome (success, missing key, invalid key, local URL, network/quota), and no secret is
  ever shown.
- **SC-006**: `wp corex make:site Acme --starter` produces a site whose every generated PHP file passes
  `php -l` and whose README explains example removal; `--minimal`/default omit the slice — verified by automated
  test.
- **SC-007**: The block editor and the Data/settings admin screens show **zero console errors** in the
  browser-verification sweep for the screens this feature touches.
- **SC-008**: Every documentation surface touched by this feature is updated in the same change set (no doc
  drift introduced), enforced by the new feature-PR docs rule.

## Assumptions

- The backends the consuming UI depends on already exist and are tested: the `corex/v1/data` query/detail
  routes, the bounded CSV `DataExportController`, the captcha `CaptchaTestController`, and the PSI/insights
  diagnostics with failure classification (per specs 045/044). This feature wires UI to them, not new servers.
- The Data screen builds on the existing React app and the spec-043 `window.Corex` runtime/envelope; where the
  core `@wordpress/dataviews` component is available it is used, with a graceful table fallback otherwise.
- Generated client sites use a **standalone starter block theme** (user decision), consume Corex `theme.json`
  tokens, and the team edits only the generated client plugin/theme — never Corex framework internals.
- The prebuilt **contact form is an add-on**, not core (user decision); this feature does not move it.
- The deployment model assumes WordPress core in a `wp/` subdirectory with junction/symlink-mapped content
  (user decision); deployment-doc accuracy beyond this feature's touched surfaces is out of scope here.
- CSV is the only export format in scope; Excel/PDF, and AVIF/CDN/Azure Blob storage, are documented as future
  increments and not built.
- Browser/visual verification runs via the existing spec-052 Playwright + console-sweep workflow (wp-env); if
  the local environment cannot run a browser, the remaining visual confirmation is recorded as env-gated, not
  silently skipped.
