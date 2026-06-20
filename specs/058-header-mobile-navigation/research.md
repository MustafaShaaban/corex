# Phase 0 Research: Header, Mobile Navigation, Mega Menu, and Footer System

All unknowns from the plan's Technical Context are resolved below. Format: Decision · Rationale · Alternatives.

## R1. Where pattern-category registration lives (theme vs plugin)

**Decision**: Register the CoreX pattern **category** (`register_block_pattern_category`) in a new plugin service
provider, `Corex\Theme\NavigationServiceProvider` (in `corex-core`). Individual **patterns** are PHP files under
`theme/patterns/` and are auto-registered by WordPress core for block themes (no PHP needed for the patterns
themselves).

**Rationale**: Principle I forbids bootstrapping/registration logic in the theme. `register_block_pattern_category`
is a registration call, so it belongs in a plugin. Pattern *files* in `theme/patterns/` are presentation and are
auto-registered by core's `_register_theme_block_patterns()` without the theme running any code — this keeps the
theme disposable while honoring the FSE convention. Patterns can reference core categories (`header`, `footer`,
`call-to-action`) too; the CoreX category is added so site owners can filter CoreX variants in the inserter.

**Alternatives considered**: (a) A `theme/functions.php` registering the category — rejected: puts registration in
the theme (Principle I) and the theme is intentionally PHP-free. (b) Calling `register_block_pattern` for each
pattern in PHP — rejected: redundant with core auto-registration and harder to keep token-driven.

## R2. Owner of the behavior CSS/JS and the conditional-enqueue mechanism

**Decision**: The presentation CSS (`theme/assets/css/corex-navigation.css`) and the behavior JS
(`theme/assets/js/corex-navigation.js`) **files live in the theme** (presentation/behavior of presentation), but
they are **registered and conditionally enqueued by the plugin** provider via `wp_enqueue_block_style('core/navigation', …)`
for the CSS and a render-scoped enqueue (e.g. on `render_block` for `core/navigation`, or attached to the pattern)
for the JS. Nothing is enqueued globally.

**Rationale**: Principle VI ("assets load only when the block renders; no global library") and the existing
`HttpServiceProvider` pattern (register, never global-enqueue). `wp_enqueue_block_style` is the core mechanism that
loads a stylesheet only on pages where a given block renders — exactly the conditional behavior required. The JS is
attached to the same surface and feature-detects; with no JS, the markup (core nav + disclosure) still works.

**Alternatives considered**: (a) Enqueue on `wp_enqueue_scripts` globally — rejected (Principle VI). (b) Put the JS
in the theme and enqueue from `theme/functions.php` — rejected (Principle I; theme stays PHP-free). (c) Ship as a
full block with `viewScript` in `block.json` — rejected: these are template parts/patterns over core blocks, not a
new custom block; a custom block would be unnecessary scope (YAGNI) for M3.

## R3. How far core `core/navigation` Interactivity covers the required behavior

**Decision**: Rely on the WordPress core `core/navigation` block for the **mobile overlay** and **submenu
disclosure** baseline: it already provides the open/close button, an `aria-modal` overlay with **focus trap**,
**Escape to close**, **`aria-expanded`** on submenu toggles, and click-outside/return-focus behavior through the
Interactivity API. CoreX therefore does **not** re-implement basic mobile-nav or submenu a11y; it only adds the
**increments core lacks**: (1) sticky + transparent-to-solid header state, (2) a true **mega-menu panel** (a wide
multi-column dropdown) and its **mobile accordion** equivalent, and (3) styling/focus-visibility from M2 tokens.

**Rationale**: Reusing core (Principle: prefer WordPress-native) minimizes custom a11y JS, which is the riskiest
code to get right, and inherits core's tested keyboard model. The mega menu is the genuine gap (core submenus are
single-column dropdowns), so CoreX's JS is scoped to that plus the scroll-state header.

**Alternatives considered**: A bespoke navigation block/JS replacing core nav — rejected: large surface, duplicates
core a11y, violates "prefer native", and risks regressions.

## R4. Sticky / transparent-to-solid technique with reduced-motion and no layout shift

**Decision**: Implement the header scroll state with a tiny script that toggles a state attribute
(`data-corex-header-state="top|scrolled"`) using a passive scroll listener throttled via `requestAnimationFrame`
(or an `IntersectionObserver` sentinel at the top of the page). The transparent variant starts transparent and
switches to a solid token-driven background on `scrolled` or when a menu opens. The visual transition is a CSS
`transition` gated by `@media (prefers-reduced-motion: no-preference)`; under `reduce`, the state still flips but
without animation. Sticky positioning uses `position: sticky` (CSS only) with the `--wp--custom--z--sticky` layer;
no JS is required for plain sticky, only for the transparent→solid swap.

**Rationale**: Sticky is pure CSS (no JS, no layout shift). The transparent swap needs a scroll signal; an `rAF`-
throttled passive listener (or IntersectionObserver) is cheap and avoids jank. Gating the transition on
`prefers-reduced-motion` satisfies FR-017/SC-007. Contrast is guaranteed because the solid state uses M2 surface/ink
tokens that already meet AA.

**Alternatives considered**: Unthrottled scroll handler (rejected: jank), JS-driven `position: fixed` with manual
offset (rejected: layout shift + complexity vs `position: sticky`).

## R5. Accessible mega-menu disclosure pattern and its mobile equivalent

**Decision**: Each mega-menu top-level item is a **disclosure button** (`<button aria-expanded aria-controls>`)
that toggles a panel (the WAI-ARIA disclosure pattern), not a menu/menubar role — disclosure is the most robust
pattern for "click/focus opens a panel of links". On desktop the panel is a positioned multi-column dropdown opened
on hover **and** focus/click; on narrow viewports the same panel renders inline as an **accordion** (the same
disclosure button, no hover). Escape closes and returns focus to the button; outside-click/blur closes. Every link
in the panel is a normal link reachable by Tab; nothing is reachable by hover alone (FR-005/FR-009).

**Rationale**: The disclosure pattern (vs `role="menu"`) matches how site navigation actually behaves (links, not a
menu of commands) and is the W3C-recommended approach for nav dropdowns; it degrades cleanly to an accordion on
mobile and to a plain expanded list with no JS.

**Alternatives considered**: `role="menubar"`/`menuitem` with arrow-key roving — rejected: the ARIA menu pattern is
intended for application menus, is error-prone for link navigation, and breaks the no-JS fallback.

## R6. Navigation breakpoint and header layout as tokens

**Decision**: Add layout-only custom properties to `theme.json` `settings.custom` under the existing convention:
`custom.header.height` (e.g. `4rem`), `custom.header.heightCompact`, and `custom.nav.breakpoint` (the desktop→mobile
switch, default `782px` to align with WordPress's standard mobile breakpoint and core nav's `overlayMenu:"mobile"`).
CSS consumes `--wp--custom--header--height` and `--wp--custom--nav--breakpoint`. No new color/spacing/type tokens.

**Rationale**: Principle V — everything token-driven, no magic numbers in CSS. Reusing the existing `custom` block
(which already holds `radius`, `motion`, `focus`, `z`) keeps one token source. Aligning the breakpoint with core's
`overlayMenu:"mobile"` (which switches at the `782px` mobile breakpoint) keeps the CoreX collapse point consistent
with where core nav already collapses, avoiding a mismatch between the CSS breakpoint and core's overlay trigger.

**Alternatives considered**: Hard-coded `px` breakpoints in CSS (rejected: Principle V), a JS-measured breakpoint
(rejected: unnecessary, fights core nav's CSS-based overlay).

## Cross-cutting notes

- **i18n**: pattern PHP files wrap all visible strings in `corex` text-domain translation functions and escape
  output; patterns are presentation but still translation-ready (Definition of Done).
- **No optional dependency**: the language-switcher and cart/account slots are static placeholders — no Polylang/
  WPML/WooCommerce calls (Principle IX). WooCommerce category mega menu / store footer is deferred to M9.
- **Environment gating**: rendered browser evidence (focus order, Escape, outside-click, sticky/transparent, RTL,
  reduced-motion, 200% zoom, 320px width) requires wp-env + a browser runtime. Where unavailable in this workspace
  (Docker Linux engine absent; Node < browser-bridge minimum), it is recorded ENVIRONMENT-GATED, never PASS.
