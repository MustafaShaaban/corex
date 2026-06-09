# Feature Specification: Corex UI Block Library (MVP)

**Feature Branch**: `009-corex-ui`

**Created**: 2026-06-09

**Status**: Draft

**Input**: A reusable library of `corex/*` blocks + section patterns under a "Corex" inserter category, so
designs are composed of Corex building units. Token-only, RTL, WCAG, i18n. Server-rendered (no JS build in
this MVP); rich custom-edit blocks + the JS build pipeline are deferred.

## Clarifications

### Session 2026-06-09 (informed defaults)

- **Q: Custom JS-edit blocks or no-build?** → No-build in this MVP. **Dynamic (data) sections** ship as
  spec-004 server-rendered blocks (`corex/posts`, `corex/breadcrumbs`, `corex/copyright`); **content
  sections** (hero, features, CTA, team, testimonials, stats, FAQ, contact) ship as **block patterns**
  composed of core blocks (editable with no custom JS). Rich custom-edit blocks + a `@wordpress/scripts`
  build are a later spec (needs a browser/build env).
- **Q: Where do patterns/blocks live?** → The dynamic blocks live in the `corex-ui` add-on
  (`addons/corex-ui`, `Corex\Ui`); the patterns register through the same add-on under a single "Corex"
  pattern category. The Company Kit (spec 010) and any design then compose these.
- **Q: How is "token-only" enforced?** → A test asserts no pattern/block render output contains a raw hex
  color or `px`/`rem` literal — every visual value resolves from a `var(--wp--preset--*)` token.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Drop in a ready section (Priority: P1) 🎯 MVP

A site-builder opens the inserter, finds the Corex sections under one **Corex** category, and drops a hero
or CTA into a page — getting accessible, token-styled, RTL-correct markup with no hand-coding.

**Why this priority**: Reusable sections are the unit of every page; this is the library's core value and
is independently usable.

**Independent Test**: Each Corex pattern is registered under the "Corex" category; its registered content
is token-only (no raw hex/px) and carries the section's accessible structure (a heading, labelled controls,
alt slots).

**Acceptance Scenarios**:

1. **Given** the inserter, **When** the user opens patterns, **Then** the Corex sections appear under one
   "Corex" category.
2. **Given** any Corex pattern, **When** inserted, **Then** its markup references only design tokens and
   uses logical CSS (RTL-correct).
3. **Given** any Corex pattern, **When** checked, **Then** it has a sensible heading level and meaningful,
   translation-ready text.

---

### User Story 2 - Show dynamic content as a block (Priority: P1)

A builder adds a Corex dynamic block — a posts list, breadcrumbs, or a copyright line — and it renders live
site data, server-side, token-styled and accessible, with no JS build.

**Why this priority**: Data-driven sections (blog list, breadcrumbs, footer year) can't be static patterns;
they are the other half of "everything is blocks" and are independently testable.

**Independent Test**: Each dynamic block registers via the block engine and its render callback returns
accessible, escaped, token-only markup for given inputs (headless render test).

**Acceptance Scenarios**:

1. **Given** the `corex/posts` block, **When** rendered, **Then** it lists recent posts as accessible cards
   (each a linked heading), bounded by a query cap, escaped.
2. **Given** the `corex/breadcrumbs` block, **When** rendered on a page, **Then** it outputs an accessible
   `nav` breadcrumb trail.
3. **Given** the `corex/copyright` block, **When** rendered, **Then** it outputs the current year + site
   name, translation-ready.

---

### User Story 3 - One catalog, discoverable and documented (Priority: P2)

The library exposes a machine-readable manifest of what it provides (the blocks and patterns, with their
categories) so kits and tooling can discover and compose it.

**Why this priority**: Enables the Company Kit (010) and future tooling to compose the library
programmatically; the blocks/patterns are usable without it, hence P2.

**Independent Test**: The manifest lists every registered Corex block and pattern with its title and
category; it matches what is actually registered.

**Acceptance Scenarios**:

1. **Given** the UI manifest, **When** read, **Then** it enumerates the Corex blocks and patterns that are
   actually registered.

---

### Edge Cases

- `corex/posts` with no posts → an accessible empty state, no error.
- A pattern inserted under an RTL locale → mirrors correctly (logical CSS).
- The block engine (corex-blocks) inactive → the dynamic blocks simply do not register; no fatal.
- A render callback throws → the block engine yields empty output + a logged warning (spec-004 behavior).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The library MUST register a set of section **block patterns** (at minimum hero, feature grid,
  CTA, testimonial, contact) under a single dedicated "Corex" pattern category.
- **FR-002**: The library MUST provide server-rendered **dynamic blocks** for live content — at minimum a
  posts list, breadcrumbs, and a copyright line — built on the existing block engine (no JS build).
- **FR-003**: Every pattern and block render MUST output only design-token values (no hardcoded colors,
  sizes, or fonts) and MUST use logical CSS so it is RTL-correct by default.
- **FR-004**: Every pattern and block MUST meet WCAG 2.2 AA structure (sensible headings, `nav`/landmark
  semantics, labelled controls, image alt slots) and be translation-ready (no hardcoded user-facing text).
- **FR-005**: Dynamic block output MUST be escaped, and any query MUST be bounded (no unbounded `posts_per_page`).
- **FR-006**: The library MUST expose a machine-readable manifest enumerating its registered blocks and
  patterns (title + category) for discovery by kits/tooling.
- **FR-007**: The library MUST register only when the block engine is active and MUST NOT hard-depend on any
  optional plugin; absence of the engine is non-fatal.
- **FR-008**: The library MUST add no business logic — it is presentation only; deactivating it removes the
  blocks/patterns, never data or behavior.

### Key Entities *(include if feature involves data)*

- **UI Manifest**: the catalog of provided blocks and patterns (name, title, category). Read-only.
- **Pattern**: a named, categorized composition of core blocks (a section) — token-styled, accessible, RTL.
- **Dynamic block**: a server-rendered `corex/*` block (posts/breadcrumbs/copyright) with bounded, escaped output.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A builder can insert any Corex section from one "Corex" category and get accessible,
  token-styled, RTL markup with no hand-coding.
- **SC-002**: 100% of the library's patterns and block renders contain no hardcoded color/size/font.
- **SC-003**: Every dynamic block renders accessible, escaped, bounded output (and a graceful empty state).
- **SC-004**: The manifest matches exactly the set of blocks/patterns actually registered.
- **SC-005**: The headless suite covers the dynamic-block renders and the token-only assertion with no browser.
- **SC-006**: Deactivating the library removes its presentation only; no data/behavior is affected.

## Assumptions

- **No-build MVP.** Content sections are block patterns (core blocks); data sections are spec-004
  server-rendered blocks. Custom JS-edit blocks + a `@wordpress/scripts` build pipeline are a later spec
  (they need a browser/build environment to author and verify).
- **Add-on packaging.** Ships as `addons/corex-ui` (`Corex\Ui`), the optional UI layer; reuses spec 004
  (block engine), 006 (tokens), 007 (the contact form block embedded in the contact pattern).
- **Neutral.** Patterns are minimal and token-driven so a client brand restyles them with no markup edits.
- **Out of scope (deferred)**: custom JS-edit blocks + the JS build pipeline; the full company page/template
  set (spec 010); animations/interactivity; bundled media; additional sections beyond the MVP set.
