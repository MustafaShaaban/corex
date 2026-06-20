# Feature Specification: Header, Mobile Navigation, Mega Menu, and Footer System

**Feature Branch**: `spec/058-header-mobile-navigation`

**Created**: 2026-06-20

**Status**: Draft

**Input**: Milestone M3 (ROADMAP §6). Design input: [M3 navigation and footer handoff](../../design/handoffs/navigation-footer.md) (approved 2026-06-20). Reuses the merged M2 brand tokens and logo package (Spec 057, PR #54).

## Overview

CoreX needs reusable site **header** and **footer** building blocks so a real company website can be assembled
without a page builder. Today the theme has no approved navigation or footer system: a site owner cannot stand up a
branded header (with a working mega menu and an accessible mobile menu) or a structured footer from CoreX patterns.
This feature delivers FSE template parts and block patterns for the header and footer, the interaction behavior they
require (keyboard, focus, Escape, outside-click, sticky/transparent, reduced motion), and full RTL and accessibility
support — all consuming the existing M2 brand tokens rather than introducing new brand visuals.

This is **not** a builder. It produces composable parts and patterns; it does not produce a drag-and-drop editor,
commerce/account business logic, or company-kit pages (those are M4, M5, and M9).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Accessible header with working navigation and mobile menu (Priority: P1)

A site owner adds the CoreX header to their site. On desktop it shows the brand logo, primary navigation, and a
call-to-action. On small screens the same navigation collapses into an accessible mobile menu (drawer or full-screen
overlay) that opens from a clearly labelled control. A visitor — including a keyboard-only or screen-reader user —
can reach every navigation destination, open and close the mobile menu, and always see where focus is.

**Why this priority**: A header with brand + reachable navigation that works on every device and input method is the
minimum viable navigation system. Without it there is no usable site shell; everything else (mega menus, footer
variants) layers on top. This story alone delivers a shippable, accessible header.

**Independent Test**: Activate the header template part on a test page, then verify the brand, primary nav, and CTA
render; shrink the viewport and confirm the mobile menu control appears, opens a focus-trapped menu containing the
same destinations, closes on Escape/outside-click/close-control, and returns focus to its trigger — with visible
focus throughout and no horizontal scrolling.

**Acceptance Scenarios**:

1. **Given** the CoreX header is active, **When** a visitor loads a wide-viewport page, **Then** the brand logo,
   primary navigation links, and the primary CTA are visible and operable.
2. **Given** a narrow viewport, **When** the visitor activates the menu control, **Then** an accessible mobile menu
   opens, moves focus into itself, traps focus while open, and exposes all primary navigation destinations.
3. **Given** the mobile menu is open, **When** the visitor presses Escape, clicks outside it, or activates the close
   control, **Then** the menu closes and focus returns to the control that opened it.
4. **Given** a keyboard-only visitor, **When** they tab through the header, **Then** focus order follows reading
   order, every interactive element shows a visible focus indicator, and no destination is reachable only by hover.
5. **Given** an RTL locale, **When** the header renders, **Then** alignment, the mobile menu side, and chevrons
   mirror correctly using logical properties.

---

### User Story 2 - Mega menu for richer navigation (Priority: P2)

A site owner with many destinations (services, product features, docs/resources) uses a header variant whose
top-level items reveal a **mega menu** — a structured panel with grouped links, optional icons, descriptions,
badges, a featured card, and a CTA. The mega menu is a progressive enhancement: every destination inside it is also
reachable as a plain accessible link list, and on mobile the same content appears as an accordion rather than a
hover panel.

**Why this priority**: Mega menus are essential for product, services, and docs sites but are not required for a
basic company site, so they build on the P1 header rather than blocking it.

**Independent Test**: Add a mega-menu header variant, then verify a top-level trigger reveals its panel on
hover/focus, the panel is fully keyboard operable, Escape/outside-click closes it and restores focus, the trigger
announces expanded/collapsed state, and on a narrow viewport the same items render as an accessible accordion.

**Acceptance Scenarios**:

1. **Given** a mega-menu header, **When** a visitor focuses or hovers a top-level trigger, **Then** its panel opens
   and exposes grouped items (icon, title, description, badge, link), an optional featured card, and an optional CTA.
2. **Given** an open mega-menu panel, **When** the visitor presses Escape or moves focus/clicks outside it, **Then**
   the panel closes and focus returns to its trigger.
3. **Given** assistive technology, **When** a trigger is examined, **Then** its expanded/collapsed state and the
   panel it controls are programmatically announced.
4. **Given** a narrow viewport, **When** the mega-menu section is opened in the mobile menu, **Then** its contents
   appear as a nested accordion with no hover dependency.
5. **Given** `prefers-reduced-motion: reduce`, **When** a panel opens or closes, **Then** transitions are removed or
   minimized while behavior is unchanged.

---

### User Story 3 - Composable footer variants (Priority: P2)

A site owner adds a CoreX footer and chooses a variant appropriate to their site (simple, corporate, SaaS,
newsletter, locations, or legal/utility). The footer is composed from column/region patterns and ends in a
legal/utility row (copyright, legal links). It reflows from multi-column to stacked on small screens without losing
grouping or heading structure, and it mirrors correctly in RTL.

**Why this priority**: A structured footer is needed for a complete site but is independent of the header and can be
delivered after the P1 shell.

**Independent Test**: Activate a footer variant, verify its columns/regions and legal row render with proper heading
semantics and the contentinfo landmark, shrink the viewport to confirm an accessible stacked reflow, and switch to
RTL to confirm mirrored alignment.

**Acceptance Scenarios**:

1. **Given** a footer variant is active, **When** the page renders, **Then** the footer presents its columns/regions
   plus a legal/utility row inside a contentinfo landmark with correct heading semantics.
2. **Given** a narrow viewport, **When** the footer renders, **Then** columns reflow to a stacked layout that
   preserves grouping and reading order.
3. **Given** an RTL locale, **When** the footer renders, **Then** column order and alignment mirror correctly via
   logical properties.

---

### User Story 4 - Header behavior, slots, and variants for different site types (Priority: P3)

A site owner selects a header variant that matches their site type — simple company, corporate with a top utility
bar, SaaS/product, docs, transparent-over-hero, or minimal landing — and optionally enables action slots (search
overlay, language switcher, CTA, account, cart). Sticky headers stay accessible while scrolling; transparent-hero
headers resolve to a solid, readable state once scrolled or when a menu opens. The slots are structural placeholders;
search, account, and cart business logic are out of scope here.

**Why this priority**: Variant breadth and the sticky/transparent behavior add polish and cover more site types, but
the P1 header already delivers core value, so these refine rather than block it.

**Independent Test**: Activate each header variant and confirm it renders with the expected structure; enable the
optional slots and confirm they appear as accessible placeholders; scroll a sticky/transparent-hero header and
confirm the solid, readable state engages and contrast is preserved.

**Acceptance Scenarios**:

1. **Given** the header variants, **When** a site owner selects one, **Then** it renders the documented structure
   (e.g., a top utility bar for the corporate variant) while reusing the same brand and navigation primitives.
2. **Given** a transparent-hero header, **When** the visitor scrolls past the hero or opens a menu, **Then** the
   header resolves to a solid state with readable contrast against its background.
3. **Given** a sticky header, **When** the visitor scrolls, **Then** the header remains operable and does not trap
   or obscure focus.
4. **Given** the optional action slots are enabled, **When** the header renders, **Then** each enabled slot (search,
   language, CTA, account, cart) appears as an accessible, labelled placeholder without requiring backend business
   logic.

---

### Edge Cases

- **Long/dense menus**: many or long navigation labels wrap or scroll within their container without causing
  horizontal page scroll or clipping at 200% zoom.
- **JavaScript unavailable**: with scripts disabled, navigation destinations remain reachable via server-rendered
  markup; mega menus and the mobile menu degrade to usable link lists/disclosures.
- **Empty/partial content**: a header with no CTA, a mega menu with no featured card, or a footer with a single
  column still renders correctly.
- **Deeply nested menus**: third-level items are reachable via the accordion on mobile and via keyboard on desktop.
- **Mixed Arabic/Latin labels**: bidirectional labels preserve readable shaping and isolation.
- **Multiple navigations on one page**: each navigation landmark is distinctly labelled so assistive technology can
  tell them apart.
- **Focus while scrolling a sticky header**: focused elements are never hidden behind the sticky header.

## Requirements *(mandatory)*

### Functional Requirements

**Header and navigation (P1)**

- **FR-001**: The system MUST provide a reusable header template part that renders a brand slot (consuming the M2
  logo package), a primary navigation region, and a primary call-to-action.
- **FR-002**: The header MUST collapse its primary navigation into an accessible mobile menu (drawer or full-screen
  overlay) below a documented, token-driven breakpoint, exposing the same destinations as the desktop navigation.
- **FR-003**: Opening the mobile menu or any overlay MUST move focus into it and trap focus while it is open; closing
  it MUST return focus to the control that opened it.
- **FR-004**: The mobile menu, mega-menu panels, and overlays MUST close on Escape, on outside click/tap, and via an
  explicit close control, in each case restoring focus to the trigger.
- **FR-005**: All navigation destinations MUST be reachable by keyboard and MUST NOT depend on hover alone; every
  interactive element MUST show a visible focus indicator that does not rely on color alone.
- **FR-006**: The header MUST use a banner landmark, primary navigation MUST use a labelled navigation landmark, and
  multiple navigations MUST be distinctly labelled.

**Mega menu (P2)**

- **FR-007**: The system MUST provide mega-menu layouts (simple dropdown, services, product/features,
  docs/resources) whose items support an optional icon, title, description, badge, link, featured card, and CTA.
- **FR-008**: Mega-menu triggers MUST programmatically expose expanded/collapsed state and the panel they control.
- **FR-009**: Every destination reachable through a mega menu MUST also be reachable as an accessible link list, and
  on small viewports the mega-menu content MUST render as a nested accordion without hover dependency.

**Footer (P2)**

- **FR-010**: The system MUST provide reusable footer template part(s) and patterns for the simple, corporate, SaaS,
  newsletter, locations, and legal/utility variants, composed from column/region patterns ending in a legal row.
- **FR-011**: The footer MUST use a contentinfo landmark with correct heading semantics and MUST reflow from
  multi-column to stacked on small viewports without losing grouping or reading order.

**Variants, behavior, and slots (P3)**

- **FR-012**: The system MUST provide header variants: simple company, corporate with top utility bar, SaaS/product,
  docs, transparent-hero, and minimal landing — all reusing the same brand and navigation primitives.
- **FR-013**: The header MUST support sticky behavior and a transparent-to-solid transition that guarantees readable
  contrast once scrolled or when a menu opens.
- **FR-014**: The header MUST support optional action slots (search overlay, language switcher, CTA, account, cart)
  as accessible, labelled placeholders; full search, account, and cart business logic is out of scope.

**Cross-cutting**

- **FR-015**: All navigation and footer surfaces MUST consume M2 semantic tokens and the M2 logo package for color,
  typography, spacing, radius, border, shadow, and focus ring, and MUST NOT introduce new brand values or a parallel
  token registry.
- **FR-016**: All layout MUST use logical (RTL-first) properties; LTR, RTL, and mixed Arabic/Latin content MUST
  render with correct mirroring, shaping, and bidirectional isolation.
- **FR-017**: All motion MUST respect `prefers-reduced-motion: reduce` by removing or minimizing animation while
  preserving behavior.
- **FR-018**: Text, interactive states, and focus indicators MUST meet WCAG 2.2 AA contrast in both dark and light
  modes, and MUST remain usable at 200% zoom and with text resizing without clipping.
- **FR-019**: Navigation/footer surfaces MUST be operable, with all destinations reachable, when JavaScript is
  unavailable (progressive enhancement with a server-rendered fallback).
- **FR-020**: Any navigation JavaScript MUST be loaded only where a header/footer that needs it is present (no global
  site-wide script), and MUST NOT introduce a client-side framework, icon font, or build-time token dependency.
- **FR-021**: The system MUST NOT include a header builder, mega-menu builder, visual editor, WooCommerce
  category mega menu / store footer, M4 kit pages, M5 broad block library, or Pro/commercial features.

### Key Entities

- **Header template part**: the composable site header (brand slot, primary navigation, action slots); rendered as
  an FSE template part and exposed through patterns per variant.
- **Navigation menu**: the ordered set of destinations, possibly multi-level, shared by the desktop navigation, mega
  menus, and the mobile menu.
- **Mega-menu item**: a navigable entry with optional icon, title, description, badge, link, featured card, and CTA.
- **Footer template part**: the composable site footer (column/region patterns + legal/utility row), rendered as an
  FSE template part and exposed through patterns per variant.
- **Action slot**: an optional, structural placeholder in the header (search, language, CTA, account, cart) with no
  bundled business logic.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site owner can assemble a branded, accessible header (brand + navigation + CTA + working mobile menu)
  from CoreX parts/patterns without writing custom code and without a builder.
- **SC-002**: 100% of navigation destinations — desktop, mega menu, and mobile — are reachable by keyboard only and
  by assistive technology, with no destination reachable by hover alone.
- **SC-003**: Every interactive menu/overlay closes on Escape, outside click, and an explicit control, returning
  focus to its trigger in 100% of cases.
- **SC-004**: All header and footer surfaces pass automated accessibility checks for landmarks, names, and WCAG 2.2
  AA contrast in both dark and light modes, in LTR and RTL, with zero blocking violations.
- **SC-005**: With JavaScript disabled, 100% of navigation destinations remain reachable via server-rendered markup.
- **SC-006**: At 200% zoom and on a 320px-wide viewport, no header or footer variant produces horizontal page
  scrolling or clipped content.
- **SC-007**: With `prefers-reduced-motion: reduce`, no header/footer surface plays non-essential animation.
- **SC-008**: Header and footer variants render correctly in both an LTR and an RTL example for each variant.

## Assumptions

- The M2 brand tokens and logo package (Spec 057, merged via PR #54) are the authoritative source for all visual
  values; this feature adds no new brand values.
- The target is a WordPress 7.0+ FSE block theme; navigation reuses WordPress core navigation/blocks and patterns
  wherever they satisfy the requirement, with minimal additional JavaScript only for behavior core blocks do not
  provide (focus trap, Escape, outside-click, sticky/transparent, mobile drawer).
- "Variants" are delivered as registered block patterns and template parts, not as configurable options in an admin
  UI.
- Action slots (search/account/cart) are structural placeholders; their business logic is delivered by later
  milestones (search, M9 commerce) or by the site owner.
- WooCommerce-specific navigation/footer is deferred to M9; this spec does not depend on WooCommerce being present.
- The docs header version/section affordance ships as a neutral pattern slot now; full docs UX is M10.
- Browser-rendered and wp-env evidence may be environment-gated in this workspace and, where unavailable, will be
  recorded as ENVIRONMENT-GATED rather than passing.
