# Implementation Plan: Design system overhaul (033)
**Branch**: `feature/033-design-system` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
Expand `theme/theme.json` **additively** — fuller palette (surface-alt/border/ink-soft + state colors), a real
type scale (xs/base/xl/2xl + existing sm/lg/hero), a full spacing scale (10/20/40/60/70 + 30/50/80), **shadow**
presets, and **radius** tokens (a `custom.radius` scale) — keeping every existing slug so no block/pattern
breaks. Polish the default `styles` (button/link/heading elements + spacing). Update the block SCSS to use the
new shadow/radius tokens (rounded, elevated cards) token-only. Add a new **style variation** alongside dark.

## Technical Context
theme.json (WP v3) + SCSS. Deps: spec-006 tokens, spec-027/029 block styles. Tests: Pest (token presence + JSON
validity + token-only scans — extend `ThemeTemplatesTest`/the token scans). Constraints: additive (old slugs
kept); token-only + logical CSS (RTL); valid JSON.

## Constitution Check (v1.2.1)
- [x] V (tokens) — PASS. Everything stays token-driven; new shadow/radius/state tokens added, no hardcoded values.
- [x] VIII (RTL) — PASS. Logical CSS preserved in the block SCSS updates.
- [x] X — implements spec 033.
- [x] Guard Gate/DoD — clean-code (SCSS readability), test-guard (token/JSON tests); docs-app theme note. No
  wp/woo surface (pure theme assets).

**Gate**: PASS.

## Design
- `theme.json` settings: extend `color.palette` (+ surface-alt, border, ink-soft, success, warning, error, info),
  `typography.fontSizes` (+ xs, base, xl, 2xl), `spacing.spacingSizes` (+ 10/20/40/60/70), add `shadow.presets`
  (sm/md/lg), and `custom.radius` (sm/md/lg/full) under `settings.custom` (→ `--wp--custom--radius--md`).
- `theme.json` styles: `elements.button`/`link`/`heading` (token colors + radius + spacing); base `typography`.
- Block SCSS: cards (posts/testimonial/pricing/stat/accordion) use `--wp--preset--shadow--md` +
  `--wp--custom--radius--md`; inputs (forms) use radius. Token-only, logical CSS.
- `theme/styles/editorial.json`: a new variation (token overrides only); keep `dark.json`.
- Tests: extend a theme test to assert the new tokens exist + JSON valid; the existing token-only scans must
  stay green.

## FR → component map
| FR | Built in |
|---|---|
| FR-001/002/003 tokens | `theme/theme.json` settings |
| FR-004 styles | `theme/theme.json` styles |
| FR-005 block SCSS | the blocks' `style.scss` (shadow/radius tokens) |
| FR-006 variation | `theme/styles/editorial.json` |
| FR-007 scans/validity | `tests/Unit/Theme/*` (token presence + JSON) |

## Project Structure
```text
theme/theme.json (expanded)
theme/styles/editorial.json (new)
addons/corex-ui/src/Blocks/*/style.scss (shadow/radius)
plugins/corex-forms/src/Block/blocks/corex-form/style.scss (radius)
tests/Unit/Theme/DesignTokensTest.php (token presence + JSON validity)
```

## Complexity Tracking
Additive expansion — no breaking changes. Visual confirmation env-gated.
