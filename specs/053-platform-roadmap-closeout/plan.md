# Implementation Plan: Platform Roadmap Closeout

**Branch**: `feature/053-platform-roadmap-closeout` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/053-platform-roadmap-closeout/spec.md`

## Summary

Close four verified gaps between Corex's "roadmap 043–052 complete" claim and the code — **without new
architecture**. Every backend the consuming surfaces need already exists and is unit-tested:

| Story | Existing backend (verified) | What this feature adds |
|---|---|---|
| **US1 docs honesty** | — | Rewrite `README.md`; reconcile `PROGRESS.md` + 045/049 `tasks.md`; add a feature-PR docs rule; sweep stale phrases. |
| **US2 Data UI** | `DataController` (`GET /data/{source}` with `search/form/sort/dir/page/per_page`; `GET /data/{source}/{id}`; `DELETE`), `DataExportController` (`admin_post_corex_data_export`, cap+nonce, bounded), envelope (043). | Build the React controls: search box, source/form filter, sortable headers, pagination, export button, detail drawer, loading/error/empty states. |
| **US3 test buttons** | `CaptchaTestController` (`POST /captcha/test`); `PsiDiagnostic` + `InsightsController` (`POST /insights/run`, already wired in `insights.js`). | Wire the **captcha Test button** JS (the real gap) in the settings screen; verify/polish the existing insights "Run check" messaging is classified + secret-safe. |
| **US4 make:site starter** | `SiteScaffolder` (render-all-before-write; identity guard), `MakeCommand::runSite`, `StubRenderer`. | `starter/` stub set + a standalone starter theme + asset architecture; wire `--starter`/`--minimal` flags; default omits the slice. |

The work is consuming UI (React + vanilla JS), CLI stubs + flag wiring, and truthful docs. Risk is low: no new
REST routes, no new services, no schema changes.

## Technical Context

**Language/Version**: PHP 8.3 (corex-core/-config, CLI generators, captcha add-on); JavaScript/JSX via
`@wordpress/scripts` (Data React app, settings/insights vanilla JS); SCSS (theme/asset architecture).

**Primary Dependencies**: WordPress 7.0+ REST + `admin_post`; `@wordpress/element`/`components`/`dataviews`
(graceful fallback); the spec-043 `window.Corex` runtime + `ResponseEnvelope`; `@wordpress/scripts` build;
WP-CLI (optional, `class_exists('WP_CLI')`-gated) for `make:site`.

**Storage**: None new. Reads existing `corex_submission` post-meta via the queryable `SubmissionsSource`; the
`SubmissionStore` seam is unchanged. No migrations.

**Testing**: Pest (unit) for the `make:site` starter scaffolder (`php -l` on generated PHP, idempotency,
`--minimal` omits / `--starter` emits) + any new PHP helper; Jest for the Data React controls
(search/sort/paginate/export/detail) and the captcha button module; reuse the spec-052 Playwright
console-clean + Data-flow E2E (env-gated execution).

**Target Platform**: WordPress admin (block editor + settings/data/insights screens) and the WP-CLI host;
generated client sites target the same WP 7.0+/PHP 8.3 baseline.

**Project Type**: WordPress framework monorepo (plugins + add-ons + theme + CLI packages + docs-app).

**Performance Goals**: Data screen interactions feel instant (server-bounded queries; per-page cap already
enforced by `DataQuery`); export bounded to the documented 5000-row cap; no global asset loads (Principle VI).

**Constraints**: No new hard dependency; secret-safe diagnostics (no key ever rendered/logged); WCAG 2.2 AA;
i18n-ready; RTL via logical CSS; token-only styling; zero browser console errors on touched screens.

**Scale/Scope**: ~1 React screen rebuild (`corex-config/src/admin/index.js`), 1 small captcha JS module,
1 CLI stub set (`packages/cli/stubs/starter/**` + starter theme) + `SiteScaffolder`/`MakeCommand` flag wiring,
and a documentation sweep across README/PROGRESS/tasks/plugin-READMEs/docs-app/agent docs.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Derived from
`.specify/memory/constitution.md` (Corex Constitution **v1.2.1**).

- [x] **I. Theme is a skin** — PASS. The generated **starter theme** is presentation only (templates/parts/
  theme.json/assets); no business logic or CPT registration in it. The example CPT/slice lives in the generated
  **plugin**. The Corex parent theme is untouched.
- [x] **II. Plugins boot themselves** — PASS. No change to boot model. The Data/captcha UI attach to existing
  admin screens already registered on `plugins_loaded`/`admin_menu`. The generated plugin self-boots.
- [x] **III. Thin controllers, fat services** — PASS. No controller logic added; UI calls existing thin
  controllers (`DataController`, `CaptchaTestController`, `InsightsController`). The generated **starter
  controller** follows the shape (route+validate→one service call→envelope response).
- [x] **IV. Everything injected** — PASS. No new `new` of dependencies in methods; the generated slice wires
  its service via the container, mirroring `ApiResourceScaffolder` output.
- [x] **V. Runtime tokens** — PASS. Data/detail/button CSS and the starter theme consume `theme.json` CSS vars
  (`--corex-*` / client `--<prefix>-*`); no raw hex/size/font; no build-time token system.
- [x] **VI. Conditional assets** — PASS. The Data app + captcha button enqueue only on their admin screens; the
  starter theme/block assets load only when present (block.json / screen hook). No global library.
- [x] **VII. Declarative security** — PASS / scope-correct. Reuses existing cap+nonce gates: REST routes carry
  their `permission_callback`; the export `admin_post` handler checks `manage_options` + the `corex_data_export`
  nonce; admin-menu screens use the shared `AdminGuard` (v1.2.1 scope). No hand-rolled checks added.
- [x] **VIII. RTL-first** — PASS. All new UI uses logical CSS; Data table, drawer, and buttons RTL-correct by
  default; starter theme SCSS is logical-property-first.
- [x] **IX. No optional dep is hard** — PASS. `@wordpress/dataviews` is used only when present (table
  fallback); WP-CLI gated for `make:site`; no provider/library becomes a hard dependency.
- [x] **X. Spec is source of truth** — PASS. This plan traces to the approved `spec.md`; the audit that drove
  it is recorded; intent changes update the spec first.
- [x] **Guard Gate + Definition of Done** — acknowledged. Per task: `clean-code-guard` (all prod code),
  `wp-guard` (REST/admin/block/escaping/nonce), `test-guard` (Pest/Jest), `docs-guard` (README/docs-app/plugin
  READMEs); tests green; i18n; RTL; WCAG 2.2 AA; docs + PROGRESS/DECISIONS updated in the same change.

**Result: PASS — no violations.** Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/053-platform-roadmap-closeout/
├── plan.md              # This file
├── research.md          # Phase 0 — decisions D1–D9
├── data-model.md        # Phase 1 — view/diagnostic/generated-site entities
├── quickstart.md        # Phase 1 — runnable validation scenarios
├── contracts/           # Phase 1 — data-screen.md, test-buttons.md, make-site-starter.md
└── tasks.md             # Phase 2 (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
plugins/corex-config/
├── src/admin/index.js                 # US2 — rebuild: search/filter/sort/paginate/export/detail/states
├── assets/data.css                    # US2 — drawer/states tokens, RTL (new or extended)
├── src/Data/DataController.php         # (unchanged) GET list+query / GET detail / DELETE
├── src/Data/DataExportController.php   # (unchanged) admin_post export, bounded, no-secret
└── assets/settings.js / captcha JS     # US3 — captcha Test button wiring

addons/corex-captcha/
├── assets/captcha-admin.js            # US3 — NEW: Test button → POST /captcha/test → classified message
└── src/CaptchaServiceProvider.php      # US3 — enqueue the admin JS on the settings screen

packages/cli/
├── stubs/starter/**                   # US4 — NEW: model/repository/service/controller(envelope)/block/
│                                       #        option-page/test/REMOVE-EXAMPLE.md + starter-theme assets
├── src/Site/SiteScaffolder.php         # US4 — emit starter slice + theme when starter=true
└── src/Commands/MakeCommand.php        # US4 — parse --starter/--minimal, pass into options

theme/                                  # (unchanged — Corex parent theme; starter theme is generated, not here)

tests/
├── Unit/Cli/SiteScaffolderStarterTest.php   # US4 Pest — php -l, idempotent, minimal omits / starter emits
├── (Jest) corex-config Data controls        # US2 — search/sort/paginate/export/detail
└── e2e/{console,smoke}.spec.js              # US2/US3 — reuse 052 (env-gated)

# Docs (US1 + per-story):
README.md · PROGRESS.md · specs/045/tasks.md · specs/049/tasks.md · DECISIONS.md
docs-app/src/content/docs/guides/{data,insights,configuration,client-site}.md
plugins/corex-config/README.md · addons/corex-captcha/README.md · packages/cli/README.md
COREX-WORKING-GUIDE.md (+ constitution) — the feature-PR docs rule
```

**Structure Decision**: WordPress framework monorepo. US2 lives in `corex-config` (Data screen owner); US3's
captcha button lives in `corex-captcha` (domain ownership, per spec 044) with enqueue on the settings screen;
US4 lives in `packages/cli` (stubs + scaffolder + command). US1 is a cross-cutting documentation sweep. No new
plugin/add-on is created.

## Complexity Tracking

> No constitution violations — section intentionally empty.
