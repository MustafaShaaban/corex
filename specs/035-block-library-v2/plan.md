# Implementation Plan: Block library expansion v2 (035)
**Branch**: `feature/035-block-library-v2` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
Five new dynamic, inline-edited, server-rendered blocks in corex-ui — **hero, cta, team, gallery, tabs** — built
on the spec-029 hybrid (RichText `edit` → attributes; `save: () => null`; PHP renderer reads attributes) and the
spec-033 design tokens. Image blocks use the WordPress media library. Tabs render with a CSS-only (no-JS)
accessible pattern. Each block auto-registers through the corex-blocks engine and the spec-018 build pipeline; each
renderer is headless Pest-tested.

## Technical Context
PHP 8.3; `@wordpress/scripts` build (auto-discovers `block.json`); `@wordpress/block-editor` (RichText, MediaUpload,
useBlockProps, InspectorControls). Tests: Pest (renderers, escaping stubbed) + Jest (edit shapes). Constraints:
Principle VI (server-rendered), V (token-only, logical CSS), VIII (i18n/RTL/WCAG), no view JS for tabs.

## Constitution Check (v1.2.1)
- [x] VI — every block `save: () => null` + PHP renderer via `corex.renderer`; auto-discovered.
- [x] V — token-only SCSS (shadow/radius/spacing from spec 033); no hex/px/inline style; logical properties.
- [x] VIII — Corex category, literal `corex` text domain, keyboard-operable, real headings/alt; RTL via logical CSS.
- [x] IX — media library is core WP; no optional plugin dependency.
- [x] X — implements spec 035.
- [x] Guard Gate/DoD — wp-guard (escaping per field type, esc_url media, lazy img), clean-code, test-guard; Pest +
  Jest; docs (blocks guide + README) + docs-app.

**Gate**: PASS.

## Design (each in `addons/corex-ui/src/Blocks/<name>/` + `<Name>Renderer.php`)
- **hero**: attrs `{eyebrow, title, subtitle, ctaText, ctaUrl, image:{id,url,alt}}`. Inline RichText for text; a
  MediaUpload for the background image. Renderer: `<section class="corex-hero">` with a real heading; CTA gated on
  text+url; empty title → ''.
- **cta**: attrs `{title, text, ctaText, ctaUrl}`. Banner; button gated on text+url; empty title → ''.
- **team**: attr `members: [{name, role, image:{id,url,alt}, bio}]`. Grid of `<figure>`; skip nameless; empty → ''.
- **gallery**: attr `images: [{id, url, alt, caption}]`. CSS grid of `<figure>`; skip url-less; empty → ''.
- **tabs**: attr `tabs: [{label, content}]`. CSS-only tabs: one `name`-shared radio group + `<label>`s + `:checked`
  sibling selectors reveal panels. No view JS. Skip label-less; empty → ''.
- Shared: a small `Media` helper convention in JS (store `{id,url,alt}` on select); renderers normalize arrays
  defensively (array|missing) and escape rich fields `wp_kses_post`, plain `esc_html`, urls `esc_url`.

## FR → component map
| FR | Built in |
|---|---|
| FR-001 dynamic/server-rendered | each `block.json` (`corex.renderer`, no save) + `<Name>Renderer.php` |
| FR-002 inline editing | each `index.js` (RichText) |
| FR-003 media | hero/team/gallery `index.js` (MediaUpload) + renderer `<img>` esc_url/alt/lazy |
| FR-004 repeatable | team/gallery/tabs array attrs + add/remove + skip-incomplete in renderer |
| FR-005 token-only | each `style.scss` (spec-033 tokens, logical CSS) |
| FR-006 no-JS tabs | `tabs/style.scss` (radio + :checked) — no viewScript |
| FR-007 category/i18n/a11y | each `block.json` (`category: corex`) + RichText placeholders + semantic markup |
| FR-008 tested | `tests/Unit/Ui/ComponentBlocksV2Test.php` (Pest) + `*/index.test.js` (Jest) |

## Project Structure
```text
addons/corex-ui/src/Blocks/{hero,cta,team,gallery,tabs}/{block.json,index.js,style.scss,index.test.js}
addons/corex-ui/src/Blocks/{Hero,Cta,Team,Gallery,Tabs}Renderer.php
tests/Unit/Ui/ComponentBlocksV2Test.php
docs/en/06-cookbooks/ (blocks) + docs-app guides/blocks (mention the new set)
```

## Complexity Tracking
The renderers stay pure/string-building and individually tested; media is the WP media frame (no custom upload);
tabs avoid JS entirely via a known CSS-only pattern, preserving Principle VI. Visual/editor behavior is env-gated.
