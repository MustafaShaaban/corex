# Feature Specification: Block library expansion (component blocks)

**Feature Branch**: `feature/027-block-library-expansion`

**Created**: 2026-06-11

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: "Grow the `corex/*` block + pattern library with the component blocks every kit needs (stat, pricing, accordion, testimonial-as-block, …) — token-only, dynamic, accessible, RTL, following the established corex-ui dynamic-block contract."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Build a page from Corex component blocks (Priority: P1)

A site builder opens the editor and finds new Corex component blocks under the "Corex" category — a statistic,
a testimonial, a pricing card, and a disclosure/accordion — each configurable from the block sidebar and
previewed live, so they can assemble a marketing page from accessible, on-brand building blocks without custom
code.

**Why this priority**: The kits and patterns need a richer block vocabulary than the three data blocks shipped
so far; these are the reusable presentation pieces a real site is built from.

**Independent Test**: For each new block, insert it, set its fields in the sidebar, and confirm the editor
preview and the front-end render match — accessible markup, token-only styling, escaped output.

**Acceptance Scenarios**:

1. **Given** the new blocks, **When** the inserter is opened, **Then** each appears under the "Corex" category
   with a title, icon, and description.
2. **Given** a block's attributes set in the sidebar, **When** the page renders, **Then** the server output
   reflects those attributes, escaped, with no hardcoded colors or sizes (theme.json tokens only).
3. **Given** each block, **When** rendered, **Then** the markup is accessible (semantic elements, the
   accordion using a native disclosure, the testimonial a `<figure>`/`<blockquote>`/`<figcaption>`).
4. **Given** RTL, **When** a block renders, **Then** its layout uses logical CSS properties and is correct
   right-to-left.

---

### User Story 2 - Each block is server-rendered and testable (Priority: P1)

Each new block follows the established Corex dynamic-block contract — a `block.json` pointing at a PHP renderer
resolved from the container — so its output is server-rendered (one source of truth, editor preview via
`ServerSideRender`) and unit-testable headlessly.

**Why this priority**: Consistency with the existing `corex/*` blocks (no client-rendered duplication) and the
constitution's testability requirement.

**Independent Test**: Call each renderer with attributes and assert the produced HTML headlessly (WP escaping
stubbed), as the existing UI block tests do.

**Acceptance Scenarios**:

1. **Given** a renderer + attributes, **When** `render()` is called, **Then** it returns the expected
   accessible, escaped HTML — verifiable with no WordPress runtime.
2. **Given** an empty/partial attribute set, **When** rendered, **Then** the block degrades gracefully (sane
   defaults, no notices, never a fatal).
3. **Given** the build, **When** `npm run build` runs, **Then** each block compiles its token-only stylesheet
   (+ its RTL variant) and editor script, like the existing blocks.

### Edge Cases

- A multi-item block (accordion) takes its items from a simple newline/delimited attribute, so the editor UI
  stays a sidebar control (no bespoke repeater) while the render is accessible.
- Missing required text (e.g. an empty quote) → the block renders nothing or a minimal placeholder, never a
  broken fragment.
- All user text is escaped on output; any URL through `esc_url`.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The library MUST gain a set of new `corex/*` **dynamic** component blocks under the "Corex"
  category — at minimum: a **stat**, a **testimonial**, a **pricing** card, and an **accordion** (disclosure).
- **FR-002**: Each block MUST follow the established contract: a `block.json` (apiVersion 3, `category:"corex"`,
  `editorScript`, compiled `style`, `corex.renderer` FQCN) + an `index.js` (`registerBlockType` +
  InspectorControls for its attributes + `<ServerSideRender>` preview + `save: () => null`) + a token-only
  `style.scss`.
- **FR-003**: Each block MUST be server-rendered by a PHP renderer implementing `Corex\Blocks\BlockRenderer`,
  resolved from the container (auto-discovered by the corex-blocks engine), producing **escaped**, **accessible**
  markup.
- **FR-004**: All block styling MUST be **token-only** (theme.json CSS variables, no hardcoded color/size/font)
  and use **logical CSS** properties (RTL-correct).
- **FR-005**: Each block MUST degrade gracefully on empty/partial attributes (sane defaults, no notices/fatals).
- **FR-006**: Each renderer MUST be **unit-tested headlessly** (WP escaping stubbed), asserting the accessible
  markup and the escaping/bounding behaviour — matching the existing UI block tests.
- **FR-007**: The new blocks MUST be discoverable through the existing `UiManifest` (so kits can compose them)
  and require **no** change to the registration engine (they drop into `addons/corex-ui/src/Blocks/`).

### Key Entities

- **Component block**: a `block.json` + `index.js` + `style.scss` + a PHP renderer, under
  `addons/corex-ui/src/Blocks/`.
- **Block attributes**: the scalar/text fields a builder sets in the sidebar (e.g. quote/author; plan/price/
  features; accordion items).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: At least **four** new `corex/*` component blocks register under the "Corex" category and render
  server-side.
- **SC-002**: Every new block's renderer is covered by a headless unit test asserting accessible, escaped
  markup.
- **SC-003**: A token-only scan of every new block's markup + SCSS finds **no** hardcoded color/size/font.
- **SC-004**: `npm run build` compiles each new block's style (+ RTL variant) and editor script with no errors.
- **SC-005**: The `UiManifest` enumerates the new blocks (kits can compose them) with no engine change.

## Assumptions

- Built on the spec-009 corex-ui library (the dynamic-block + renderer pattern) and the spec-018 build
  pipeline (editor registration + compiled styles). The new blocks extend `corex-ui` directly (framework
  presentation), the same way `posts`/`breadcrumbs`/`copyright` do.
- Blocks use scalar/text attributes (sidebar controls + `ServerSideRender`), not bespoke repeater editor UI;
  multi-item blocks read a delimited text attribute. Interactive multi-panel widgets (e.g. JS tabs) and a
  media-repeater gallery are a later increment via the Interactivity API, noted but out of this scope.
