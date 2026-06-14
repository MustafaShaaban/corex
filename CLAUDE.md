# Corex — Agent Entry Point (Claude Code)

You are working on **Corex**, a professional, Laravel-inspired WordPress framework.
Namespace `Corex\`, CLI `wp corex`, CSS prefix `--corex-`. Target: WordPress 7.0+,
PHP 8.3+, FSE block themes. Built spec-first with Spec Kit.

## BEFORE doing anything
1. Read `specs/constitution.md` — the non-negotiable rules. They override everything.
   (Canonical source lives at `.specify/memory/constitution.md`; `specs/constitution.md`
   mirrors it for the source-of-truth hierarchy.)
2. Read `PROGRESS.md` — current status and the recommended next step. Continue from "Next".
3. Read the active spec in `specs/` for the module you're touching.
4. Skim `COREX-FRAMEWORK.md` for the architecture if unfamiliar; `COREX-WORKING-GUIDE.md`
   for how we work. `COREX-EMAIL-ADDON.md` is the Corex Mail spec for when its turn comes.

## Source-of-truth hierarchy (top wins) — COREX-WORKING-GUIDE.md §A.1
1. `specs/constitution.md`  2. `COREX-FRAMEWORK.md`  3. the active module spec
4. `PROGRESS.md`  5. the code. If code contradicts the constitution, the code is wrong.

## WHILE working
- Follow the constitution exactly. If a request conflicts with it, say so rather than comply.
- Use the `wp corex make:*` generators (once built) rather than hand-writing boilerplate.
- Keep controllers thin; logic goes in services; data access in repositories.
- Everything injected via the PSR-11 container — never `new` a dependency inside a method.
- All styling via `theme.json` CSS variables. No hardcoded colors/sizes/fonts. No CSS frameworks.
- Logical CSS properties (RTL-first). No optional plugin (ACF/Woo/Polylang) as a hard dependency.

## AFTER producing any code (Definition of Done — COREX-WORKING-GUIDE.md §D.4)
- **Guard Gate:** run the relevant guard skill on the diff BEFORE presenting it. Auto-install
  it first if missing. No diff ships until its guard runs clean.
  - any production code → `clean-code-guard`
  - WP plugin/theme/block/REST/AJAX/query → `wp-guard`
  - WooCommerce code → `woo-guard` (on top of wp-guard)
  - test code → `test-guard`   ·   docs/README/docstrings → `docs-guard`
- Write tests (Pest unit, Jest for JS blocks, Playwright E2E). i18n-ready, RTL-verified, WCAG 2.2 AA.
- Update `PROGRESS.md`; log any non-trivial decision in `DECISIONS.md`.
- **End every response with a NEXT STEP block** (format in COREX-WORKING-GUIDE.md §A.3 / constitution).

## Spec Kit workflow (commands are namespaced `speckit-*`)
`/speckit-constitution` → `/speckit-specify` → `/speckit-clarify` → `/speckit-plan`
→ `/speckit-tasks` → `/speckit-implement`. Write the spec before the code; review between tasks.
Module build order: see `COREX-SPECKIT-START.md` ("The rhythm from here").

<!-- SPECKIT START -->
**Active plan:** `specs/053-platform-roadmap-closeout/plan.md` (close the gap between the "043–052 COMPLETE /
v0.25.0" claim and the code — no new architecture, consuming surfaces only: **US1** rewrite the stale README +
reconcile PROGRESS/045+049 `tasks.md` checkboxes + add a feature-PR docs rule; **US2** build the missing Data
admin React controls (search/source+form filter/sortable headers/pagination/CSV-export button/detail
drawer/loading+error+empty states) over the existing `corex/v1/data` query+detail routes + `admin_post`
export; **US3** wire the captcha **Test** button JS in corex-captcha over `POST /captcha/test` — the real gap;
insights "Run check" already exists, so verify+polish its classified secret-safe message; **US4** add the
`make:site --starter` slice (`packages/cli/stubs/starter/**` model/repo/service/controller-on-envelope/block/
option/test/REMOVE-EXAMPLE.md + a standalone starter block theme with `@wordpress/scripts` SCSS/JS, dev-only
maps, minified prod, manifest cache-busting, url/path/version helper) + wire `--starter`/`--minimal` into
`SiteScaffolder`/`MakeCommand` (default omits). Constitution Check PASS, no violations. Non-scope: new DLS atoms
(→054), Excel/PDF export, AVIF/CDN/Azure Blob. Next: `/speckit-tasks`. Superseded prior plan below.

**Active plan (prev):** `specs/041-kit-front-page/plan.md` (kit apply never leaves a blank front page — pure
`KitPagePlanner::plan()` classifies each declared page create/adopt/skip; `BlueprintActivator` populates
adopted pages + sets the front page after the loop for a created|adopted home + records `_corex_kit_page`=
created|adopted; CLI `ResetExecutor` deletes created pages but only empties adopted ones; returns an
`ApplyOutcome` the wizard shows as a summary). Part of the 2026-06-13 connectivity batch with
`specs/040-block-asset-urls/plan.md` (junction/symlink-safe block asset URLs — normalize block dir under
`WP_PLUGIN_DIR` at the `DynamicBlockRegistrar` chokepoint + `BlockAssetsProbe` health check) and
`specs/042-kit-activation/spec.md` (unified prompt-to-apply activation + "what changed" summary + dashboard
status card; depends on 041). Build order: 040 + 041 → 042. Specs 001–039 delivered + released
(v0.18.0 → v0.23.1). Earlier:
**Active plan (prev):** `specs/027-block-library-expansion/plan.md` (four server-rendered `corex/*` component
blocks — stat/testimonial/pricing/accordion — token-only, RTL, accessible). Before it: `026-addon-manager`
(dependency-aware add-on screen) and `025-project-reset` (`wp corex reset`, soft + gated full). The P1
retrospective backfill 018–024 reconciled the "Finish Corex" initiative to specs. Earlier:
**Active plan (prev):** `specs/018-build-pipeline-blocks/plan.md` (RETROSPECTIVE — @wordpress/scripts build
pipeline + dynamic block editor registration via ServerSideRender + "Corex" inserter category +
add-on activation; reconciles items 1–2 of the "Finish Corex" initiative to a spec). Built on spec 004. Earlier:
**Active plan (prev):** `specs/007-forms-engine/plan.md` (Form schema + headless Validator + shared
EventDispatcher seam in corex-core + secured REST submit lifecycle reusing the spec-005 middleware
+ email/store listeners + FSE form block; new plugin corex-forms). Built on specs 001–006. Earlier:
**Active plan (prev):** `specs/006-theme-tokens/plan.md` (theme.json token source + brand.json deep-merge
resolver via wp_theme_json_data_theme + style variations; theme is a skin). Built on specs 001–005.
Earlier:
**Active plan (prev):** `specs/005-middleware-security/plan.md` (declarative middleware pipeline + nonce/auth/
throttle/sanitize + SecurityModule; Principle VII; headless-testable). Built on specs 001–004. Earlier:
**Active plan (prev):** `specs/004-block-engine/plan.md` (corex-blocks: discovery + conditional assets +
container-resolved render + Block-Bindings connectors; server-rendered, no JS build). Built on
specs 001–003. Earlier (superseded):
**Active plan (prev):** `specs/003-cli-generators/plan.md` (wp corex make:* stub generators). Built on
specs 001 (container/providers/Config) + 002 (Model/Repository/Service/Controller shapes it
scaffolds). WP-CLI is optional (`class_exists('WP_CLI')` gate); the generator engine (render+write) is
pure/headless-testable, separate from the WP-CLI command layer. Earlier plans (superseded):
**Active plan (prev):** `specs/002-data-layer/plan.md` (Model + Field driver + Repository + QueryBuilder).
For technologies, structure, contracts, and validation, read that plan and its siblings
(`research.md`, `data-model.md`, `contracts/`, `quickstart.md`). Built on the **complete** corex-core
foundation (spec 001): `Boot`, custom PSR-11 `Container`, `ServiceProvider` seam, `Config`,
`HookRegistry`, `ControllerMap`. Tech: PHP 8.3, Pest + Brain Monkey; ACF behind a `FieldDriver`
(native-meta fallback, never a hard dependency); QueryBuilder builds capped `WP_Query` args, executed
by a thin `QueryExecutor`.
<!-- SPECKIT END -->
