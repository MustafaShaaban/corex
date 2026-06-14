# Feature Specification: Design Language System

**Feature Branch**: `feature/051-design-language-system`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Corex should have its own WordPress-native Design Language System — a documented
taxonomy (Components, Blocks, Patterns, Templates, Guidelines) like the UAE design system concept (borrow the
structure, not the code/brand). Organize the existing corex-ui blocks into it, fill a few component gaps
(alert/badge), and document it. Decide: corex-ui vs a new corex-dls."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A documented design-system taxonomy (Priority: P1) 🎯 MVP

A developer or designer sees Corex's UI organized into a clear **Design Language System** taxonomy —
**Components** (small UI atoms: alert, badge, button, card…), **Blocks** (the server-rendered `corex/*` blocks:
hero, CTA, stat, testimonial, pricing, accordion, tabs, team, gallery…), **Patterns** (composed sections),
**Templates** (FSE templates), and **Guidelines** (tokens, accessibility, RTL) — so the library is navigable and
its parts have a place, instead of a flat undocumented list.

**Why this priority**: The substance (blocks/tokens/patterns) ships across 027/029/033/035; what's missing is the
*organizing taxonomy + catalog* that makes it a coherent, navigable system.

**Independent Test**: A catalog enumerates every Corex UI element under its taxonomy category (Component / Block /
Pattern / Template / Guideline); every listed block maps to a real registered `corex/*` block.

**Acceptance Scenarios**:

1. **Given** the design system, **When** the catalog is built, **Then** it organizes the Corex UI into the five
   taxonomy categories (Components, Blocks, Patterns, Templates, Guidelines).
2. **Given** the catalog's Blocks, **When** checked against reality, **Then** every listed block is an actually
   registered `corex/*` block (no drift / no invented entry).
3. **Given** the catalog, **When** queried, **Then** it returns the entries for a given category.

---

### User Story 2 - Fill the component gaps (Priority: P1)

The component layer gains the missing UI atoms a real site needs — at minimum an **alert** (and a **badge**) —
added to `corex-ui` as server-rendered, token-only, accessible, RTL-ready blocks following the existing pattern,
so the system covers feedback/status UI, not only marketing sections.

**Why this priority**: A "design system" that lacks basic feedback components (alert/badge) is incomplete; these
are the most-reused atoms.

**Independent Test**: Insert the new `corex/alert` (and `corex/badge`); each registers as a dynamic block, renders
token-only accessible markup (an alert has a role; a badge is a labelled span), and is RTL-correct.

**Acceptance Scenarios**:

1. **Given** the new `corex/alert` block, **When** rendered, **Then** it outputs an accessible alert (a `role` +
   the message), token-only (no hardcoded color/size), with a variant (info/success/warning/error) by class.
2. **Given** the new `corex/badge` block, **When** rendered, **Then** it outputs a labelled, token-styled span.
3. **Given** either block, **When** inspected, **Then** it is dynamic (server-rendered), escaped, i18n-ready, and
   logical-CSS/RTL.

---

### User Story 3 - The DLS is documented (Priority: P2)

The Design Language System is **documented** in the docs app — the taxonomy, the catalog of what Corex provides
under each category, and the guidelines (tokens, accessibility, RTL) — so a team browses the system like a real
design system reference.

**Why this priority**: A design system is only usable if documented. P2 because the catalog + components (US1/US2)
are the substance the docs describe.

**Independent Test**: The docs app has a Design System page describing the taxonomy + listing the catalog entries +
linking the guidelines; the listed elements match the catalog.

**Acceptance Scenarios**:

1. **Given** the docs, **When** the Design System page loads, **Then** it describes the five-category taxonomy and
   lists the catalog (Components/Blocks/Patterns/Templates/Guidelines).
2. **Given** the page, **When** read, **Then** the guidelines (tokens are the single source — theme.json/brand.json;
   accessibility WCAG 2.2 AA; RTL via logical properties) are stated.

---

### User Story 4 - One home for the system (Priority: P2)

The Design Language System lives in **`corex-ui`** (the existing block library add-on) — not a new `corex-dls`
plugin — with the catalog as a small registry there, so there is one home and no duplication; the decision is
documented.

**Why this priority**: Avoids fragmentation. P2 because it's an architectural decision realized by where US1's
catalog lives.

**Independent Test**: The catalog + the new components live in `corex-ui`; no new `corex-dls` plugin is created; the
decision + rationale are documented.

**Acceptance Scenarios**:

1. **Given** the implementation, **When** inspected, **Then** the catalog + components are in `corex-ui`, and no
   separate `corex-dls` plugin exists.
2. **Given** the docs/DECISIONS, **When** read, **Then** the "corex-ui is the DLS home" decision + rationale are
   recorded.

---

### Edge Cases

- A catalog Block entry with no matching registered block → a drift test fails (the catalog must not list a block
  that isn't real).
- The new alert/badge with empty content → renders nothing or a safe empty state, never broken markup.
- A new component MUST be token-only (no hardcoded color/size) and RTL-correct, like the existing blocks.
- The taxonomy MUST borrow the *structure* of a public design system, never its code or brand.

## Requirements *(mandatory)*

### Functional Requirements

**Taxonomy + catalog (US1)**

- **FR-001**: A `DesignSystemCatalog` MUST organize the Corex UI into five categories — **Components**, **Blocks**,
  **Patterns**, **Templates**, **Guidelines** — and return the entries for each.
- **FR-002**: Every catalog **Block** entry MUST map to an actually-registered `corex/*` block (a drift check
  guarantees no invented/stale entry).
- **FR-003**: The catalog MUST be **pure** (the entries declared, not discovered at runtime), so it is unit-tested,
  and live in `corex-ui` (US4).

**Components (US2)**

- **FR-004**: `corex-ui` MUST add a `corex/alert` block — server-rendered, an accessible alert (`role` + message),
  token-only, with a variant (info/success/warning/error) by class, escaped, i18n-ready, logical-CSS/RTL.
- **FR-005**: `corex-ui` MUST add a `corex/badge` block — server-rendered, a labelled token-styled span, escaped,
  i18n, RTL.
- **FR-006**: The new blocks MUST follow the existing `corex/*` block pattern (dynamic, `BlockRenderer`, in the
  Corex inserter category, conditional assets, no hardcoded values).

**Docs + home (US3/US4)**

- **FR-007**: The Design Language System MUST be **documented** in the docs app — the taxonomy, the catalog, and
  the guidelines (tokens source-of-truth, WCAG 2.2 AA, RTL).
- **FR-008**: The DLS MUST live in **`corex-ui`** (no new `corex-dls` plugin); the decision MUST be recorded.

**Cross-cutting**

- **FR-009**: All new markup MUST be escaped, token-only, i18n-ready, and RTL (logical properties); no secret. The
  taxonomy borrows structure only, never another system's code/brand.

### Key Entities *(include if feature involves data)*

- **Catalog entry**: a UI element — name, category (Component/Block/Pattern/Template/Guideline), and (for a Block)
  its `corex/*` block name. Pure.
- **Design system catalog**: the enumeration of entries by category, drift-checked against the real blocks.
- **Alert / Badge component**: new server-rendered `corex/*` blocks (token-only, accessible, RTL).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Corex's UI is organized into the **five-category** DLS taxonomy, navigable as a documented system.
- **SC-002**: **100%** of the catalog's Block entries map to real registered `corex/*` blocks (drift-checked) — no
  invented or stale entry.
- **SC-003**: The component layer gains at least **alert + badge** — server-rendered, accessible, token-only, RTL.
- **SC-004**: The DLS is **documented** in the docs app (taxonomy + catalog + guidelines) and lives in a **single**
  home (`corex-ui`), with the decision recorded.

## Assumptions

- Builds on and **reuses** the shipped corex-ui block library (027/029/033/035: hero/CTA/stat/testimonial/pricing/
  accordion/tabs/team/gallery/breadcrumbs/posts/copyright), the spec-033 tokens, the spec-004 block engine + the
  `make:block` pattern, and the existing pattern/template libraries — this feature adds the catalog + alert/badge +
  the documentation; it does not re-spec them.
- The catalog is a **pure declared registry** in corex-ui (drift-tested against the real blocks), not a runtime
  scanner.
- The taxonomy borrows the **conceptual structure** of public design systems (Components/Blocks/Patterns/Templates/
  Guidelines), never any specific system's code or brand.
- Out of scope (explicitly): a Storybook-style interactive component explorer, a full visual redesign, net-new
  marketing blocks beyond the alert/badge gap-fill, and a separate `corex-dls` plugin (the decision is corex-ui).
- Live visual confirmation of the new blocks needs a browser; per the environment gate, the catalog + renderers are
  unit-tested headlessly and the visual smoke runs when the environment is available.
