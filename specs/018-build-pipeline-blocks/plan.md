# Implementation Plan: Front-end build pipeline & dynamic block editor registration

**Branch**: `018-build-pipeline-blocks` (work currently on `develop`, uncommitted) | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/018-build-pipeline-blocks/spec.md`

> **Retrospective plan.** The implementation already exists and is verified on a real WP 7.0 install. This
> plan's job is to (a) record the technical approach, (b) **map each FR to the concrete files that satisfy
> it**, and (c) flag any drift between spec and code. No new architecture is proposed.

## Summary

Give every Corex block a real editor presence and a compiled, conditional, RTL-aware asset set, without
abandoning the "dynamic, server-rendered" model. The approach: `@wordpress/scripts` compiles each block
package's `src` SCSS+JS into a per-package `build/blocks/<name>/`; each block ships an `index.js` that
`registerBlockType`s and previews the PHP render via `<ServerSideRender>` (fixing "block not supported");
discovery prefers `build/` and falls back to source for headless tests; a "Corex" inserter category groups
the blocks; and the add-on plugins are mapped into `wp-content` and activated so their blocks/patterns appear.

## Technical Context

**Language/Version**: PHP 8.3 (block registration/render); JavaScript/JSX (editor + view scripts); SCSS.

**Primary Dependencies**: `@wordpress/scripts` (webpack/Babel/Sass/PostCSS), `@wordpress/server-side-render`,
`@wordpress/blocks`, `@wordpress/block-editor`, `@wordpress/i18n`; npm workspaces; WordPress block APIs
(`register_block_type`, `block_categories_all`, `wp_set_script_translations`).

**Storage**: N/A (no new persistence; blocks read via their own renderers).

**Testing**: Pest (PHP unit + integration on real `./wp`); Jest via `wp-scripts test-unit-js` (JS).

**Target Platform**: WordPress 7.0+ FSE site (front end + Site/Block Editor), CLI/REST/cron contexts.

**Project Type**: WordPress monorepo — plugins + add-ons + a build-tools package + a theme (skin).

**Performance Goals**: Conditional assets — a page loads only the CSS/JS of blocks present on it; no global library.

**Constraints**: Token-only styling (no hardcoded colour/size/font); logical/RTL CSS; build artifacts git-ignored
(rebuilt on checkout/CI); the core must register from source so the Pest suite runs with no build present.

**Scale/Scope**: 7 dynamic blocks across 5 block-bearing packages today; the pipeline scales to any number of
blocks/packages by convention.

## Constitution Check

*GATE: re-checked post-design (retrospective — checked against the shipped code).*

Derived from `.specify/memory/constitution.md` (Corex Constitution **v1.2.0**).

- [x] **I. Theme is a skin** — PASS. Blocks/build live in plugins/add-ons + `packages/build-tools`; the theme
  only consumes tokens (no block logic added to the theme).
- [x] **II. Plugins boot themselves** — PASS. `BlocksServiceProvider` registers blocks on `init`; no theme
  dependency. *(Reconciliation note: the mail-queue eager-resolution that violated "no heavy work at
  plugins_loaded" was a different feature and is fixed under DECISIONS #55.)*
- [x] **III. Thin controllers, fat services** — N/A (no controllers in this feature).
- [x] **IV. Everything injected** — PASS. `DynamicBlockRegistrar`/`BlockMap` resolved via the container; the
  render callback resolves the renderer from the container.
- [x] **V. Runtime tokens** — PASS. Every block `style.scss` consumes `theme.json` CSS variables; no raw
  hex/size/font (a unit test scans for this).
- [x] **VI. Conditional assets** — PASS. Assets declared per block in `block.json`; loaded only when rendered.
- [x] **VII. Declarative security** — N/A (no routes/AJAX/forms in this feature).
- [x] **VIII. RTL-first** — PASS. Logical CSS properties; the build emits `*-rtl.css` automatically.
- [x] **IX. No optional dep is hard** — PASS. No optional plugin involved; the Node build is a dev/release
  step, and the PHP core registers from source without it.
- [x] **X. Spec is source of truth** — PARTIAL→reconciled. The code shipped before this spec; this retrospective
  spec + plan restores the trace (the compliance review + constitution v1.2.0 prevent recurrence).
- [x] **Guard Gate + Definition of Done** — PARTIAL. clean-code + wp-guard + docs-guard were run on this work;
  a formal full re-run is remediation **P2**. Tests: Pest green; Jest exists for the forms validator only —
  **block `index.js` has no Jest test yet** (gap → remediation **P4**). i18n wired; RTL emitted; docs present.

**Gate result:** PASS to proceed, with two acknowledged, tracked debts (P2 formal guards, P4 block-JS/E2E tests).

## FR → implementation map (the retrospective core)

| FR | Satisfied by |
|---|---|
| FR-001 build artifacts | per-package `package.json` (`wp-scripts build --webpack-src-dir=… --output-path=build/blocks`); output `build/blocks/<name>/{index.js,index.asset.php,style-index.css,style-index-rtl.css}` |
| FR-002 dynamic + SSR preview | `block.json` `corex.renderer` (PHP) + each `index.js` (`registerBlockType` + `<ServerSideRender>`); `DynamicBlockRegistrar::renderCallback` |
| FR-003 editor registration | `index.js` per block; `block.json` `editorScript: file:./index.js`; verified: each `corex/*` type has an `editor_script_handles` entry |
| FR-004 conditional assets | `block.json` `style`/`viewScript` (`register_block_type` enqueues only on render) |
| FR-005 token-only + auto-RTL | each `style.scss` (theme.json vars, logical props); wp-scripts emits `style-index-rtl.css` |
| FR-006 build-or-source discovery | `BlockMap::discover` + each provider's `is_dir($built) ? $built : <source>` |
| FR-007 script i18n | `DynamicBlockRegistrar::registerScriptTranslations` (editor+view+script handles) |
| FR-008 Corex category | `BlocksServiceProvider::registerBlockCategory` on `block_categories_all`; blocks set `category:"corex"` |
| FR-009 add-ons mapped+active | junctions in `wp/wp-content/plugins` + `wp plugin activate` (recorded in PROGRESS env section) |
| FR-010 artifacts git-ignored | root `.gitignore` `**/build/`; docs in `packages/build-tools/README.md` |

**Drift found:** none material. Minor: the source fallback registers `editorScript`/`style` paths that only
exist post-build, so a no-build environment emits asset-not-found notices on registration — documented as a
build prerequisite (acceptable; real installs build).

## Project Structure

### Documentation (this feature)

```text
specs/018-build-pipeline-blocks/
├── plan.md              # this file
├── research.md          # Phase 0 — the build/editor approach decisions
├── data-model.md        # Phase 1 — the block-package / block / build-output entities
├── quickstart.md        # Phase 1 — how to build + verify blocks in the editor
├── contracts/
│   └── block-build-contract.md   # the block-package build/discovery contract
└── tasks.md             # Phase 2 (/speckit-tasks — reconciliation tasks)
```

### Source Code (already implemented)

```text
packages/build-tools/                 # build conventions + README (the pipeline doc)
plugins/corex-blocks/src/
├── BlockMap.php                      # convention discovery (FR-006)
├── DynamicBlockRegistrar.php         # register_block_type + SSR wiring + i18n + category helper
├── BlocksServiceProvider.php         # init registration + the "Corex" category (FR-008)
└── blocks/entity-field/{block.json,index.js,style.scss}
plugins/corex-forms/src/Block/blocks/corex-form/{block.json,index.js,view.js,style.scss}
addons/corex-ui/src/Blocks/{posts,breadcrumbs,copyright}/{block.json,index.js,style.scss}
addons/corex-careers/blocks/jobs/{block.json,index.js,style.scss}
addons/corex-kit-portfolio/src/Blocks/projects/{block.json,index.js,style.scss}
<each package>/package.json           # wp-scripts build script
```

**Structure Decision**: monorepo, per-package block sources under each plugin/add-on, compiled to a
package-level `build/blocks/`; the engine (`corex-blocks`) provides discovery + registration reused by every
package.

## Complexity Tracking

No unjustified violations. The two tracked debts (P2 formal guard re-run, P4 block-JS + E2E tests) are
remediation items, not architectural complexity.
