# Quickstart / Validation: Corex Full DLS (054)

Prereqs: `composer install`, `npm install`, the monorepo mapped into a WP 7.0+/PHP 8.3 install. Browser steps run
under the spec-052 wp-env/Playwright path; where no browser is available, record the step env-gated.

## US1 — Catalog + gap analysis

```bash
vendor/bin/pest tests/Unit/Ui/DesignSystemCatalogTest.php   # full taxonomy + drift (both directions)
```
- **Expect:** all six categories enumerated; `blockNames()` ⊆ registered `corex/*`; `corex/modal` listed;
  block-style/core/deferred entries carry `block:null`. A published gap-analysis doc classifies every candidate.

## US2 — Foundations

```bash
node -e "JSON.parse(require('fs').readFileSync('theme/theme.json'))"   # valid JSON
grep -o '\-\-wp--custom--motion--[a-z-]*' -r theme addons/corex-ui     # tokens consumed, not hardcoded
```
- **Expect:** `settings.custom.motion/focus/z` present; a Foundations doc page documents every token group;
  (env-gated) a `brand.json` override of a new token flows to render with no recompile.

## US3 — Components

```bash
vendor/bin/pest tests/Unit/Ui/ModalRendererTest.php tests/Unit/Ui/BlockStylesTest.php
npm run test:js     # modal editor registration
```
- **Expect:** `corex/modal` renders trigger + `<dialog aria-labelledby>` + close, escaped, token-only; the 6
  block styles register on their blocks; the skeleton utility is token-only.
- **Env-gated (browser):** modal opens/ESC/backdrop/focus-return, console-clean, RTL.

## US4 — Patterns, templates, docs

```bash
vendor/bin/pest tests/Unit/Ui/PatternLibraryTest.php        # pattern-accuracy (real blocks only)
cd docs-app && npm run build                                 # design-system section builds, no broken links
```
- **Expect:** the new patterns compose only registered blocks; the new templates are valid FSE; the docs-app
  design-system section has Foundations/Components/Patterns/Templates/Guidelines pages, every component with
  when-to-use / when-not-to-use.

## Whole-feature gates

```bash
vendor/bin/pest ; npm run test:js
# Guard Gate per diff: clean-code, wp-guard (blocks/styles/escaping/conditional assets), test-guard, docs-guard
```
- **Done when:** catalog full + drift-clean; motion/focus/z tokens added + documented; `corex/modal` + the block
  styles + skeleton shipped; the justified patterns/templates added; the docs-app design-system section green;
  PROGRESS/DECISIONS updated; browser checks executed or recorded env-gated.
