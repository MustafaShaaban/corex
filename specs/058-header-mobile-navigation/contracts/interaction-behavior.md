# Contract: Interaction Behavior

Behavioral contracts for the navigation and footer surfaces. Each item is testable (Jest for the behavior module;
Playwright for rendered keyboard/AT behavior where the browser runtime is available — otherwise ENVIRONMENT-GATED).

## C1. Keyboard operability (FR-005)

- Every navigation destination on every surface (desktop, mega menu, mobile) MUST be reachable by Tab/Shift+Tab.
- No destination may be reachable by hover/pointer only.
- Tab order MUST follow visual reading order (and mirror correctly in RTL).
- Mega-menu and mobile triggers MUST be operable with Enter and Space.

## C2. Focus management (FR-003)

- Opening the mobile overlay (core nav) MUST move focus into it and trap focus while open (provided by core nav).
- Opening a mega-menu panel MUST keep focus on/within the trigger→panel sequence; closing returns focus to the
  trigger.
- Focus indicators MUST be visible against every supported surface (uses `--wp--custom--focus--*`) and MUST NOT rely
  on color alone.
- A focused element MUST never be hidden behind the sticky header (scroll-margin / header offset).

## C3. Escape, outside-click, explicit close (FR-004)

- Escape MUST close the open mobile overlay (core), mega-menu panel, and search overlay, returning focus to the
  trigger.
- A pointer click/tap outside an open mega-menu panel or overlay MUST close it.
- An explicit close control MUST close the mobile overlay (core) and return focus to its opener.

## C4. Disclosure semantics (FR-008)

- Each mega-menu trigger MUST be a `<button>` with `aria-expanded` reflecting state and `aria-controls` referencing
  its panel id.
- Mobile accordion sections MUST use the same `aria-expanded`/controls semantics.

## C5. Sticky / transparent-to-solid (FR-013)

- Plain sticky headers MUST use CSS `position: sticky` at `--wp--custom--z--sticky` (no JS required).
- Transparent-hero headers MUST start transparent and switch to a solid token-driven background (`data-…-state`
  flip) once scrolled past a top threshold OR whenever a menu/overlay opens; contrast in the solid state MUST meet
  WCAG 2.2 AA.
- The scroll listener MUST be passive and throttled (rAF or IntersectionObserver); it MUST NOT cause layout shift.

## C6. Mobile mega accordion (FR-009)

- On viewports below `--wp--custom--nav--breakpoint`, mega-menu content MUST render as a nested accordion using the
  disclosure pattern (no hover dependency); all panel links remain reachable.

## C7. RTL (FR-016)

- All layout MUST use logical properties; in RTL the menu alignment, drawer/overlay side, chevrons/affordances, and
  reading order MUST mirror.
- Mixed Arabic/Latin labels MUST preserve shaping and bidirectional isolation, using the M2 Arabic font role.

## C8. Reduced motion (FR-017)

- All transitions/animations MUST be gated by `@media (prefers-reduced-motion: no-preference)`.
- Under `prefers-reduced-motion: reduce`, state still changes (open/close, transparent→solid) but with no non-
  essential animation.

## C9. No-JS fallback (FR-019)

- With JavaScript unavailable, all navigation destinations MUST remain reachable via server-rendered markup; mega
  menus degrade to expanded/accessible link lists and the mobile menu to core nav's no-JS behavior.

## C10. Landmarks & names (FR-006, FR-011)

- The header MUST be a `banner` landmark; primary navigation a labelled `navigation` landmark; multiple navigations
  distinctly labelled; the footer a `contentinfo` landmark.
- Icon-only controls (menu toggle, search) MUST have accessible names.

## Acceptance (test hooks)

- Jest: the behavior module toggles `data-corex-header-state` on scroll threshold, toggles `aria-expanded` on
  accordion/mega triggers, no-ops animation under a mocked `prefers-reduced-motion: reduce`, and removes listeners
  on teardown.
- Playwright (ENV-gated): keyboard traversal reaches all destinations; Escape/outside-click close panels and restore
  focus; RTL mirrors; reduced-motion plays no animation; no horizontal scroll at 320px / 200% zoom.
