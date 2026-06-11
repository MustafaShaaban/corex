# Feature Specification: Site kits — Company polish, Portfolio, WooCommerce store

**Feature Branch**: `023-site-kits`

**Created**: 2026-06-11

**Status**: Draft (RETROSPECTIVE — documents delivered, tested, real-WP-verified code; items 10 + 11 + 12 of the "Finish Corex" initiative; reconciled to the implementation in `addons/corex-kit-*`)

**Input**: "Corex ships ready-made site kits — a company site, a portfolio, and a WooCommerce store — each a manifest that composes existing blocks/patterns/templates and can't silently drift from what the theme and library actually provide; the Woo kit self-disables unless WooCommerce is active and the operator opts in."

> **Retrospective note.** Written after the kits shipped, to restore spec-first compliance (Principle X). It
> builds on the spec-010 Company kit + Blueprint seam, the spec-009 UI block/pattern library, and the theme.
> The setup wizard that activates kits is **spec 024** (item 13), not here. Requirements describe the existing
> `addons/corex-kit-company`, `addons/corex-kit-portfolio`, and `addons/corex-kit-woo` code.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A company-site kit that can't drift (Priority: P1)

The Company kit is a pure manifest composing existing presentation (templates, parts, patterns). Its
declarations are cross-checked against reality, so it can never reference a template, part, or pattern that
doesn't exist.

**Why this priority**: A kit manifest that drifts from the theme/library produces broken sites silently. Drift
protection is what makes a kit trustworthy.

**Independent Test**: Cross-check the Company blueprint against the filesystem + the pattern library — every
declared template/part is a theme file and every composed pattern is one the library provides.

**Acceptance Scenarios**:

1. **Given** the Company blueprint, **When** validated, **Then** every declared template (front-page/page/
   single/archive/search/404/index) exists as a theme file.
2. **Given** the blueprint, **When** validated, **Then** every declared part (header/footer) exists as a theme
   file.
3. **Given** the blueprint, **When** validated, **Then** every composed pattern is one the UI `PatternLibrary`
   actually provides.

---

### User Story 2 - A portfolio kit with a projects block (Priority: P1)

A developer activates the Portfolio kit and gets a public `corex_project` CPT (with a `project_type`
taxonomy and a `/projects` archive), a `corex/projects` dynamic block, FSE templates, and a drift-checked
blueprint — all token-only and accessible.

**Why this priority**: Portfolio is a distinct, common site shape; it proves the kit model extends past the
company site with its own CPT + block.

**Independent Test**: Render the projects block (accessible linked cards with thumbnails, bounded count,
accessible empty state); validate the blueprint against the theme + library.

**Acceptance Scenarios**:

1. **Given** projects exist, **When** the `corex/projects` block renders, **Then** it outputs accessible linked
   cards with lazy thumbnails, escaped, on a token grid.
2. **Given** a requested count, **When** the block renders, **Then** the project count is bounded to the max
   (1–24); the sole `WP_Query` lives in `WpProjectsProvider` (no_found_rows).
3. **Given** no projects, **When** the block renders, **Then** it shows an accessible empty state (non-fatal).
4. **Given** the Portfolio blueprint, **When** validated, **Then** it declares only templates/parts that exist
   and patterns the library provides.

---

### User Story 3 - A WooCommerce store kit that self-disables (Priority: P2)

The Woo kit composes a storefront from WooCommerce's own blocks/templates and declares HPOS compatibility. It
runs only when WooCommerce is active **and** the operator turns on its flag; otherwise its provider is a no-op.

**Why this priority**: WooCommerce must never be a hard dependency (Principle IX). The kit has to be gated so a
Woo-less site is unaffected, and a Woo site opts in explicitly.

**Independent Test**: Resolve the gate with the four combinations of (Woo present?) × (flag on?) — enabled only
when both true; confirm the provider self-disables otherwise and the kit declares HPOS compatibility.

**Acceptance Scenarios**:

1. **Given** WooCommerce active **and** the `woocommerce_kit` flag on, **When** the gate resolves, **Then** it
   is enabled; in any other combination it is disabled.
2. **Given** the flag off (default), **When** the provider boots, **Then** it is a no-op (self-disables) — a
   Woo-less or opted-out site is unaffected.
3. **Given** the kit plugin, **When** it loads, **Then** it declares HPOS (`custom_order_tables`) compatibility
   and its blueprint declares only templates/parts that exist.

### Edge Cases

- A kit is a pure manifest — it composes existing presentation, it does not re-implement blocks/patterns.
- The Woo kit accesses no order/product meta directly (storefront reuses Woo's blocks) → minimal, HPOS-safe
  woo-guard surface.
- Portfolio uses a new `Corex\Portfolio\` PSR-4 prefix to avoid the `Corex\Kit\` collision.
- The projects block's count is always bounded; the provider is the only `WP_Query` caller.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Company kit MUST be a pure `Blueprint` manifest; a test MUST cross-check every declared
  template/part against theme files and every composed pattern against the UI `PatternLibrary`, so it can't
  drift.
- **FR-002**: The Portfolio kit MUST register a public `corex_project` CPT (thumbnail/REST/`/projects`
  archive) + a `project_type` taxonomy, under a `Corex\Portfolio\` prefix.
- **FR-003**: The Portfolio kit MUST provide a `corex/projects` dynamic block rendering accessible linked
  cards with lazy thumbnails, a bounded count (1–24), and an accessible empty state, all token-only + escaped;
  the sole `WP_Query` MUST live in `WpProjectsProvider` (injected, `no_found_rows`).
- **FR-004**: The Portfolio kit MUST add FSE templates (`archive-project`, `single-project`) to the theme
  (skin, token-only) and a drift-checked `PortfolioBlueprint`.
- **FR-005**: The Woo kit MUST be gated by a pure `WooKitGate` — enabled only when `class_exists('WooCommerce')`
  AND the `woocommerce_kit` flag is on; its provider MUST self-disable (no-op) otherwise.
- **FR-006**: The Woo kit plugin MUST declare HPOS (`custom_order_tables`) compatibility and compose the
  storefront from WooCommerce's own blocks/templates (no direct order/meta access).
- **FR-007**: Each kit MUST be wired (Boot provider list, composer PSR-4, npm workspace where it has a built
  block) and carry a README; none may make an optional plugin a hard dependency.

### Key Entities

- **Blueprint**: a kit manifest (required/recommended modules + templates/parts/patterns).
- **WpProjectsProvider**: the sole `WP_Query` caller for the projects block.
- **WooKitGate**: the pure predicate gating the Woo kit on (Woo present) × (flag on).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The Company + Portfolio blueprints cannot declare a non-existent template/part or an unprovided
  pattern (a manifest test fails if they do).
- **SC-002**: The `corex/projects` block renders accessible, bounded, token-only cards and an accessible empty
  state, verified headlessly.
- **SC-003**: The Woo kit is enabled only when Woo is active AND its flag is on; disabled in all three other
  combinations.
- **SC-004**: With the Woo flag off (default), a site (Woo present or not) is unaffected — the provider is a
  no-op.
- **SC-005**: Each kit activates on real WP with zero PHP fatals.

## Assumptions

- Built on spec 010 (Company kit + Blueprint), spec 009 (UI blocks/patterns + PatternLibrary), spec 018 (block
  build), and spec 007 (the projects block follows the dynamic-block contract).
- The setup wizard that enables flags + activates kits is spec 024 (item 13).
- Visual/editor validity of templates/patterns needs a browser; structure is drift-protected headlessly.
