# M3 Navigation and Footer Handoff

**Status:** Approved

**Approved:** 2026-06-20

**Engineering target:** Spec 058 - Header, Mobile Navigation, Mega Menu, and Footer System

## Approval evidence

The product owner approved authoring this structural and behavioral handoff for M3 in the 2026-06-20 working
session, after the M2 token contract was reviewed and merged (PR #54, merge commit `f9994f8`). No external visual
design package exists for navigation or footer, and none is invented here: this handoff is **structural,
behavioral, and token-driven**. It composes the already-approved M2 brand tokens and logo package (Spec 057) into
reusable navigation and footer surfaces. Visual identity is inherited from M2; this handoff adds layout structure,
interaction behavior, states, responsive/RTL behavior, and accessibility expectations only.

This handoff records design direction inside the repository. It is an input to the engineering spec; it does not
authorize implementation without the reviewed Spec 058.

## Approved direction

- Reusable FSE template parts and block patterns for site header and footer that serve company, product, docs, and
  (later, gated) commerce sites **without a builder UI**.
- All visual values come from M2 semantic tokens (`theme.json` `--wp--preset--*` / `--corex-*`) and the approved
  logo package. Navigation and footer own **layout and behavior**, not new brand values.
- Header is a composable shell: brand slot, primary navigation, and an actions area (CTA, search, language, account,
  cart) where each action is an **optional slot**, not a hardcoded feature.
- Mega menus are progressive enhancements over accessible link lists, never the only way to reach a destination.
- Mobile navigation collapses the same content into an accessible disclosure pattern; it does not maintain a
  separate, divergent menu structure.
- Footer is a composable set of column/region patterns culminating in a legal/utility row.
- Calm, restrained motion; dark-first identity inherited from M2 with complete light-mode behavior.

## Scope

### Header variants

- Simple company (brand + nav + single CTA).
- Corporate with top utility bar (contact/locale row above the main row).
- SaaS/product (nav with feature mega menu + primary + secondary CTA).
- Docs (nav + search slot + version/section affordance).
- Transparent-hero (transparent over a hero, solid after scroll).
- Minimal landing (brand + single CTA, reduced nav).
- RTL example for each variant.

### Mobile navigation

- Slide-in drawer.
- Full-screen overlay menu.
- Nested accordion for multi-level menus.
- Mobile mega-menu rendered as an accordion (no hover dependency).

### Mega menus

- Simple dropdown, services, product/features, and docs/resources layouts.
- Item anatomy: optional icon, title, description, badge, link, featured card, and CTA.
- Commerce/category mega menu is **excluded** here and deferred to M9.

### Header behavior and slots

- Sticky behavior and transparent-to-solid transition on scroll.
- Optional slots: search overlay, language switcher, CTA, account, cart. These are **markup/placeholder slots**;
  full search, account, and cart business logic is out of scope.

### Footer variants

- Simple, corporate, SaaS, newsletter, locations, and legal/utility variants.
- RTL example for each variant.

### Reusable parts and patterns

- Header and footer FSE template parts registered by the theme.
- Block patterns for each variant, composed from WordPress core blocks first.

## Interaction and state behavior

- **Keyboard:** all menus, disclosures, and overlays are fully operable by keyboard. Tab order follows reading
  order; mega-menu triggers expose expanded/collapsed state; arrow-key movement within a mega menu is a documented
  enhancement, not a requirement for access.
- **Focus management:** opening an overlay/drawer moves focus into it and traps focus while open; closing returns
  focus to the trigger. Focus is always visible against every supported surface and never relies on color alone.
- **Escape:** Escape closes the open mega menu, search overlay, or mobile drawer and returns focus to the trigger.
- **Outside click:** clicking/tapping outside an open mega menu, overlay, or drawer closes it.
- **Sticky/transparent:** the transparent state resolves to a solid, readable state once scrolled or when a menu
  opens, preserving contrast at all times.
- **Reduced motion:** `prefers-reduced-motion: reduce` removes or reduces transitions/animations; functionality is
  unchanged.
- **Disclosure semantics:** triggers use correct expanded/collapsed and controls relationships so assistive
  technology announces state.

## Desktop, tablet, and mobile behavior

- Desktop shows the full header with inline navigation and hover/focus mega menus.
- Tablet and below collapse navigation into the mobile pattern at a documented, token-driven breakpoint.
- No layout introduces horizontal scrolling; long labels and dense menus wrap or scroll within their container.
- Footer columns reflow from multi-column to stacked without losing grouping or heading semantics.

## LTR, RTL, and mixed-script behavior

- All layout uses logical properties; nothing assumes a physical left/right direction.
- RTL mirrors menu alignment, drawer side, chevrons/affordances, and reading order.
- Mixed Arabic/Latin labels preserve readable shaping and bidirectional isolation, reusing M2 typography roles.

## Accessibility

- WCAG 2.2 AA: contrast for text and interactive states comes from M2 tokens in both modes.
- Landmarks: header uses a banner landmark and primary navigation a labelled navigation landmark; footer uses a
  contentinfo landmark. Multiple navigations are distinctly labelled.
- Visible focus, accessible names for icon-only controls, and state announcements for disclosures/overlays.
- Skip-to-content support is preserved/available.
- Targets remain usable at 200% zoom and with text resizing without clipping.

## Performance and conditional assets

- Prefer WordPress core blocks, core navigation, and CSS for structure and the hover/disclosure behavior.
- Any navigation JavaScript (focus trap, Escape, outside-click, sticky/transparent, mobile drawer) must be small,
  loaded only where a header/footer that needs it is present, and must degrade to a usable server-rendered fallback.
- No global site-wide JavaScript framework, icon font, or build-time token dependency. Reuse SVG/logo assets from
  M2; do not ship per-size raster assets.

## Design tokens and reusable primitives

- Consume M2 semantic roles for color, typography, spacing, radius, border, shadow/elevation, and focus ring.
- Introduce only layout-level custom properties (e.g., header height, z-index layer, breakpoint) that map to or
  extend existing token conventions; do not create a parallel brand registry or component-local palette.
- The logo brand slot consumes the approved M2 logo package and respects clear-space/minimum-size guidance.

## Explicit exclusions

- A header builder, mega-menu builder, or any drag/drop/visual editor.
- WooCommerce category mega menu and store-specific footer (deferred to M9), and any commerce business logic.
- M4 company-kit pages, M5 broad block library, and Pro/commercial features.
- Admin builder / front-office editor workspace.
- Full search, account, and cart business logic (slots only).
- Heavy or decorative animation systems.

## Open questions

- Final default breakpoint value for the desktop→mobile navigation switch (to be fixed as a token in planning).
- Whether the docs header version/section affordance ships as a pattern in M3 or defers to M10 docs productization
  (planning to decide; default: ship a neutral pattern slot now, full docs UX in M10).
