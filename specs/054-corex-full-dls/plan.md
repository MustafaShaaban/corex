# Implementation Plan: Corex Full Design Language System

**Branch**: `feature/054-corex-full-dls` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/054-corex-full-dls/spec.md`

## Summary

Make the Corex DLS complete and navigable, **native-first**. The plan is driven by the gap analysis in
[research.md](./research.md), which classifies every candidate UI element against the real inventory. The
headline finding: **most candidate "components" are WordPress core blocks to document or Corex block styles, not
new blocks** — only a small, high-reuse set warrants a custom block. Concretely:

- **Foundations (US2):** radius + layout already exist in theme.json; the *real* missing token groups are
  **motion, focus, and z-index** → add them; document **all** groups in a Foundations guide.
- **Components (US3):** **one** justified new block — **`corex/modal`** (native `<dialog>`, focus-trap + ESC,
  degrades without JS) — plus **block styles** (button variants, card, table, section, empty-state) and a
  token-only **skeleton/loading** utility; everything else (button, link, search, dropdown, pagination, table,
  list, image, columns) is **documented core usage**; toast = the existing `window.Corex.notices` runtime.
- **Patterns/Templates (US4):** add the justified patterns (section-header, content-split, stats, FAQ,
  posts/news) and page-type templates (landing, contact, form) — composed only of real blocks/parts.
- **Catalog + docs (US1):** expand `DesignSystemCatalog` to the full taxonomy (drift-checked) and publish the
  gap analysis + a docs-app design-system section.

No new plugin/add-on; the DLS home is **corex-ui**. No build-time tokens. Risk is concentrated in the one
interactive block (`corex/modal`), whose visual/a11y verification is env-gated (spec-052 sweep).

## Technical Context

**Language/Version**: PHP 8.3 (corex-ui block renderers + the catalog), JavaScript/JSX via `@wordpress/scripts`
(block editor scripts; `corex/modal` view behavior via the WordPress Interactivity API), SCSS (block styles +
token-only CSS), JSON (theme.json tokens, block.json, style variations).

**Primary Dependencies**: WordPress 7.0+ block API (`register_block_type`, `register_block_style`), theme.json v3
+ `brand.json` runtime resolver (spec 006), the spec-043 `window.Corex` runtime (for `notices` = toast), the
spec-052 Playwright/console sweep; Astro + Starlight docs-app (spec 022). No new third-party dependency; no icon
library bundled (icon guidance is documented).

**Storage**: None. The DLS is presentation + a pure catalog registry; no schema, no migrations.

**Testing**: Pest (catalog enumeration + drift tests; each new block renderer; pattern-accuracy tests); Jest
(editor registration for any new block with an editor script); spec-052 Playwright + console-error sweep for
visual/RTL/a11y (env-gated execution, recorded honestly).

**Target Platform**: WordPress block editor + front end (FSE block theme), bilingual AR/EN, WCAG 2.2 AA.

**Project Type**: WordPress framework monorepo (corex-ui add-on + theme + docs-app).

**Performance Goals**: Conditional assets (Principle VI) — a block's CSS/JS loads only when present; the modal's
JS loads only on pages that use it; no global library. Token resolution is runtime (CSS custom properties).

**Constraints**: Token-only (no hardcoded color/size/font/radius/shadow/motion); logical CSS/RTL by default;
state conveyed beyond color; keyboard-operable; interactive components degrade without JS. The catalog must stay
drift-checked.

**Scale/Scope**: ~1 new block (`corex/modal`), ~5 block styles, 3 new token groups, 1 token-only utility,
~5 patterns, ~3 templates, a Foundations guide + a docs-app design-system section, and the expanded catalog +
gap-analysis doc. US1/US2 are the tractable, headless core; US3 modal + US4 are the heavier, partly env-gated tail.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Corex Constitution **v1.2.1**.

- [x] **I. Theme is a skin** — PASS. New templates/patterns/style-variations are presentation only; the catalog +
  block renderers live in the corex-ui **add-on**, not the theme. No logic enters the theme.
- [x] **II. Plugins boot themselves** — PASS. corex-ui registers blocks/styles/patterns on its own hooks,
  theme-independent. No change to boot.
- [x] **III. Thin controllers, fat services** — PASS / N/A. Block renderers are presentation; any data (e.g.
  posts) goes through the existing injected providers (e.g. `WpPostsProvider`). No controller logic added.
- [x] **IV. Everything injected** — PASS. New renderers resolve dependencies via the container as the existing
  corex-ui blocks do; no `new` of a dependency inside a method.
- [x] **V. Runtime tokens** — PASS (central). New motion/focus/z-index tokens live in theme.json as CSS custom
  properties; every component consumes a token; brand.json overrides at runtime. No build-time tokens.
- [x] **VI. Conditional assets** — PASS. Each block style / block / utility enqueues only where used; the modal's
  view JS loads only when the block is present. No global library; no bundled icon font.
- [x] **VII. Declarative security** — PASS / mostly N/A. The DLS is presentation; no new REST/AJAX routes. Output
  is escaped in every renderer.
- [x] **VIII. RTL-first** — PASS (central). All new CSS uses logical properties; components RTL-correct by
  default; the dark/editorial variations and new patterns verified RTL.
- [x] **IX. No optional dep is hard** — PASS. No icon library or JS framework becomes a hard dependency; icon +
  motion guidance are documented approaches; the modal uses the in-core Interactivity API / native `<dialog>`.
- [x] **X. Spec is source of truth** — PASS. This plan traces to spec 054; the gap analysis (research.md) is the
  evidence; intent changes update the spec first.
- [x] **Guard Gate + Definition of Done** — acknowledged. Per change: `clean-code-guard`, `wp-guard`
  (blocks/styles/escaping/conditional assets), `test-guard` (Pest/Jest), `docs-guard`; tests green; i18n; RTL;
  WCAG 2.2 AA; docs updated in the same change (§D.5); PROGRESS/DECISIONS updated.

**Result: PASS — no violations.** Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/054-corex-full-dls/
├── plan.md              # This file
├── research.md          # Phase 0 — THE GAP ANALYSIS (per-candidate decisions) + token-gap findings
├── data-model.md        # Phase 1 — catalog entry / gap record / token group / component entities
├── quickstart.md        # Phase 1 — runnable validation per story
├── contracts/           # Phase 1 — catalog.md, foundations-tokens.md, modal-block.md, block-styles.md
└── tasks.md             # Phase 2 (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
addons/corex-ui/
├── src/DesignSystemCatalog.php          # US1 — expand to the full taxonomy; stays drift-checked
├── src/Blocks/modal/                    # US3 — NEW corex/modal (block.json + index.js + view.js + style.scss)
│   └── ModalRenderer.php                 #        native <dialog>, focus-trap/ESC, degrades without JS
├── src/Blocks/BlockStyles.php            # US3 — register_block_style: button variants, card, table, section, empty-state
├── assets/ (block-style scss)            # US3 — token-only block-style CSS + a skeleton/loading utility
├── src/Patterns/PatternLibrary.php       # US4 — add section-header, content-split, stats, FAQ, posts/news
└── README.md                             # docs — DLS overview

theme/
├── theme.json                           # US2 — add motion, focus, z-index token groups (radius/layout already exist)
├── templates/                           # US4 — add landing, contact, form page-type templates
└── styles/                              # (existing dark/editorial variations documented)

docs-app/src/content/docs/design-system/ # US4 — NEW section: foundations, components/*, patterns, templates, guidelines
docs-app/src/content/docs/guides/design-system.md  # (existing 051 page — expanded/linked)

tests/
├── Unit/Ui/DesignSystemCatalogTest.php  # US1 — full-taxonomy enumeration + drift (extend existing)
├── Unit/Ui/ModalRendererTest.php        # US3 — Pest renderer (escaped, token-only, ARIA)
├── Unit/Ui/BlockStylesTest.php          # US3 — registered styles present
├── Unit/Ui/PatternLibraryTest.php       # US4 — pattern-accuracy (composes real blocks) — extend existing
├── (Jest) modal/index.test.js           # US3 — editor registration
└── e2e/console.spec.js                  # US3/US4 — reuse 052 (env-gated)
```

**Structure Decision**: Everything DLS lives in **corex-ui** (catalog, the new block, block styles, patterns)
except the runtime token groups + page templates, which are the **theme's** presentation responsibility
(Principle I). Documentation lands in a new **docs-app design-system section**. No new plugin/add-on.

## Complexity Tracking

> No constitution violations — section intentionally empty.
