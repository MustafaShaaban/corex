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
**Active plan:** `specs/018-build-pipeline-blocks/plan.md` (RETROSPECTIVE — @wordpress/scripts build
pipeline + dynamic block editor registration via ServerSideRender + "Corex" inserter category +
add-on activation; reconciles items 1–2 of the "Finish Corex" initiative to a spec. First of the P1
retrospective-spec backfill 018–024; see PROGRESS "COMPLIANCE REVIEW"). Built on spec 004. Earlier:
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
