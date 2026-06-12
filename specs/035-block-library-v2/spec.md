# Feature Specification: Block library expansion v2 (035)

**Created**: 2026-06-12 · **Status**: Draft · **Input**: "Not enough custom blocks; the existing ones are too
simple." Expand the Corex block library with the layout/marketing blocks a real site needs — hero, call-to-action,
team, gallery, tabs — all built on the spec-029 inline-editing architecture so they are edited directly on the
FSE canvas, server-rendered, token-styled, accessible, and i18n/RTL-ready.

## User Scenarios & Testing

### US1 — A hero that opens a page (P1) 🎯 MVP
As an editor, I drop a **Hero** block at the top of a page and type the headline, subheadline, and button label
directly on the canvas, pick an optional background image from the media library, and set the button link — no
right-panel hunting. Front end renders a prominent, accessible hero with a real heading and a token-styled button.

**Acceptance**: eyebrow/title/subtitle are inline RichText; the CTA renders only when both text and URL are set;
a chosen background image renders with its alt text (decorative-safe when empty); an empty hero (no title) renders
nothing. The heading is a real `<h1>`/`<h2>` for document outline.

### US2 — A call-to-action banner (P1)
As an editor, I add a **CTA** block — heading, supporting line, and one button — edited inline, to convert
visitors. Renders as a token-styled banner; the button appears only when text + URL are present; empty → nothing.

### US3 — A team grid (P2)
As an editor, I add a **Team** block and append member cards; each has a name, role, photo (media library), and a
short bio, edited inline. Renders as an accessible, responsive grid of figures; a member with no name is skipped;
an empty team renders nothing.

### US4 — An image gallery (P2)
As an editor, I add a **Gallery** block and append images from the media library, each with alt text and an
optional caption. Renders as a responsive CSS grid of `<figure>`s with real `<img>` alt text; images with no URL
are skipped; empty → nothing.

### US5 — Tabbed content, no JavaScript (P2)
As an editor, I add a **Tabs** block and append tabs (label + content), edited inline. The front end shows
accessible tabs **with no view JavaScript** — a CSS-only pattern (radio inputs + labels) toggles panels, so it
works even with scripts disabled. Empty → nothing.

## Requirements

- **FR-001**: Each new block is a **dynamic, server-rendered** block (Principle VI): `save: () => null`, a PHP
  renderer resolved via the block's `corex.renderer`, registered through the existing corex-blocks engine
  (auto-discovered under `addons/corex-ui/src/Blocks/*`).
- **FR-002**: Each block is **edited inline on the canvas** (spec 029): text regions are `RichText` writing to
  attributes; renderers escape rich fields with `wp_kses_post`, plain fields with `esc_html`/`esc_attr`, URLs with
  `esc_url`.
- **FR-003**: Blocks that take images use the **WordPress media library** (`MediaUpload`/`MediaUploadCheck`),
  storing `{id, url, alt}`; renderers output real `<img>` with `alt`, `esc_url`'d `src`, and lazy loading.
- **FR-004**: Repeatable blocks (team, gallery, tabs) store an **array attribute** of items with add/remove
  controls; a member/image/tab missing its required field is skipped; an empty collection renders nothing.
- **FR-005**: All styling is **token-only** (Principle V): no hex colors, no px/rem literals, no inline styles,
  logical CSS properties (RTL-first). Blocks reuse the spec-033 shadow/radius/spacing tokens.
- **FR-006**: Tabs render **without view JavaScript** — an accessible CSS-only disclosure (radio + label) pattern.
- **FR-007**: Every block lives in the **"Corex"** inserter category, is i18n-ready (literal `corex` text domain,
  translator-safe), and is keyboard-operable + WCAG 2.2 AA.
- **FR-008**: Each renderer is **headless-unit-tested** (Pest): renders expected markup from attributes; empty/
  partial input degrades gracefully (the documented "renders nothing" rules); no hardcoded colors/px/inline style.

## Success Criteria

- **SC-001**: The library gains **5 new blocks** (hero, cta, team, gallery, tabs), all in the Corex category.
- **SC-002**: An editor can build a full landing page (hero → features → team → gallery → cta) entirely from Corex
  blocks, editing all text inline and picking all images from the media library — no theme code.
- **SC-003**: Every new renderer has passing Pest tests; the JS edit shapes have passing Jest tests; the full
  suite stays green.
- **SC-004**: Front end renders accessible, token-styled markup with zero hardcoded colors/sizes and (for tabs)
  zero view JavaScript.

## Assumptions

- "stats-grid" is achieved by grouping existing **Stat** blocks in a columns/grid container, so it needs no new
  block; this spec adds the genuinely missing layout/marketing blocks instead.
- Media handling stores the attachment URL (and id/alt) on the block; updates never touch the media library.
- The build pipeline (spec 018, `@wordpress/scripts`) auto-discovers each new `block.json`, so no build-config
  change is required.

## Dependencies

Spec 004 (block engine), spec 018 (build pipeline), spec 029 (inline editing), spec 033 (design tokens).
