# Implementation Plan: Block library expansion (027)

**Branch**: `feature/027-block-library-expansion` | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

## Summary

Add four new server-rendered `corex/*` component blocks to `corex-ui` — **stat**, **testimonial**, **pricing**,
**accordion** — each following the existing dynamic-block contract (block.json → container-resolved PHP
renderer, editor `ServerSideRender` preview, token-only RTL styles). They drop into
`addons/corex-ui/src/Blocks/` and are auto-discovered by the corex-blocks engine — no registration change. Each
renderer is pure (attributes → escaped, accessible HTML) and unit-tested headlessly.

## Technical Context

**Language/Version**: PHP 8.3 + block JS (built by `@wordpress/scripts`). **Primary Dependencies**: the spec-009
corex-ui pattern (`BlockRenderer`, BlockMap auto-discovery, the "Corex" category) + the spec-018 build pipeline.
**Testing**: Pest (renderer unit tests, WP escaping stubbed via Brain Monkey, like `UiBlocksTest`). **Project
Type**: WP add-on (`corex-ui`). **Constraints**: token-only + logical CSS (RTL); escaped output; graceful
defaults; server-rendered (no client duplication).

## Constitution Check (v1.2.1)

- [x] **III/IV (layering + DI)** — PASS. Each renderer is a pure class implementing `BlockRenderer`,
  container-resolved by FQCN (autowired; no deps). No engine change.
- [x] **V (tokens)** — PASS. Every block's `style.scss` uses theme.json CSS variables only; a token-only test
  scans the rendered markup.
- [x] **VI (dynamic blocks)** — PASS. All four are dynamic, server-rendered; editor preview via
  `ServerSideRender`; assets conditional via block.json.
- [x] **VII (security)** — PASS. All renderer output escaped (`esc_html`/`esc_url`/`esc_attr`); no request data.
- [x] **VIII (i18n/RTL)** — PASS. Labels/placeholders translatable; logical CSS → RTL-correct.
- [x] **X (spec)** — implements spec 027 (written first).
- [x] **Guard Gate / DoD** — planned: clean-code-guard (renderers) + wp-guard (escaping) + test-guard (Pest).
  Docs: corex-ui README block list.

**Gate**: PASS.

## Blocks (to build) — `addons/corex-ui/src/Blocks/`

| Block | Attributes | Renders |
|---|---|---|
| `corex/stat` | `value`, `label`, `description` | `<div class="corex-stat">` value + label + optional description |
| `corex/testimonial` | `quote`, `author`, `role` | `<figure class="corex-testimonial"><blockquote>…</blockquote><figcaption>` |
| `corex/pricing` | `plan`, `price`, `period`, `features` (newline-delimited), `ctaText`, `ctaUrl` | a pricing card: heading, price, `<ul>` of features, optional CTA link |
| `corex/accordion` | `items` (one `Title \| Content` per line) | a list of native `<details><summary>` disclosures (accessible, no JS) |

Each block = `{ block.json, index.js, style.scss }` + `<Name>Renderer.php` (in `Blocks/`).

## Project Structure (to create)

```text
addons/corex-ui/src/Blocks/
├── stat/{block.json,index.js,style.scss}            + StatRenderer.php
├── testimonial/{block.json,index.js,style.scss}     + TestimonialRenderer.php
├── pricing/{block.json,index.js,style.scss}         + PricingRenderer.php
└── accordion/{block.json,index.js,style.scss}       + AccordionRenderer.php
tests/Unit/Ui/ComponentBlocksTest.php                (one renderer assertion per block + token scan)
```

## Phase 0 / 1 artifacts

- `data-model.md` — each block's attributes + render contract.
- `contracts/blocks-contract.md` — the block.json shape + the inserter expectations.
- `quickstart.md` — run the renderer tests + the build + manifest check.

## Complexity Tracking

No unjustified violations. The blocks reuse the existing renderer contract + auto-discovery; the only new code
is four pure renderers + their block assets + tests. Interactive JS tabs + a media-repeater gallery are an
explicit later increment (Interactivity API), not built here.
