---
title: Navigation & footer
description: Corex header, mobile navigation, mega menu, and footer template parts and patterns — reusable, accessible, token-only, and builder-free.
---

The M3 navigation system gives a site a branded, accessible **header** and **footer** without a builder. It ships
as FSE template parts plus block patterns, all composed from WordPress core blocks and the M2 brand tokens — no new
brand values, no global JavaScript, no commerce business logic.

## What ships

| Surface | Slugs | Notes |
|---|---|---|
| Header (default) | `theme/parts/header.html` | Brand + core navigation (mobile overlay) + CTA. |
| Header variants | `corex/header-simple`, `corex/header-corporate`, `corex/header-saas`, `corex/header-docs`, `corex/header-transparent`, `corex/header-minimal` | Registered under the **CoreX** + core **header** pattern categories. |
| Mega menus | `corex/megamenu-simple`, `corex/megamenu-services`, `corex/megamenu-product`, `corex/megamenu-docs` | Built on the native `<details>`/`<summary>` disclosure. |
| Footer (default) | `theme/parts/footer.html` | Simple variant: legal/utility row. |
| Footer variants | `corex/footer-simple`, `corex/footer-corporate`, `corex/footer-saas`, `corex/footer-newsletter`, `corex/footer-locations`, `corex/footer-legal` | Registered under **CoreX** + core **footer**. |

Patterns are auto-registered by WordPress from `theme/patterns/*.php` (block themes). The **CoreX** pattern category
and the conditional assets are registered by `Corex\Theme\NavigationServiceProvider` in `corex-core` — the theme
stays a presentation-only skin (constitution Principle I).

## Accessibility & behavior

- **Mobile navigation** uses the core navigation block's overlay: an accessible toggle, focus trap, `aria-expanded`,
  Escape-to-close, and focus return — CoreX does not re-implement these.
- **Mega menus** are native `<details>` disclosures, so they are keyboard-operable and fully usable with no
  JavaScript (the summary toggles the panel; every link is reachable). The buildless
  `theme/assets/js/corex-navigation.js` adds only the conveniences `<details>` lacks: opening one panel closes its
  siblings, and Escape or an outside click closes the open panel and returns focus to its summary. Below the
  navigation breakpoint the same markup is an accordion (no hover dependency).
- **Sticky / transparent header**: `corex/header-transparent` is `position: sticky` and transparent over a hero; the
  script flips `data-corex-header-state` on a passive, `requestAnimationFrame`-throttled scroll listener so CSS can
  resolve it to a solid, readable (WCAG 2.2 AA) background. The transition is gated by
  `@media (prefers-reduced-motion: no-preference)`; under `reduce` the state still changes with no animation.
- **Action slots** (search, language, CTA, account, cart) are structural placeholders — for example the docs header
  uses the native `core/search` block, and the corporate header exposes a labelled language link. No search,
  account, or cart business logic is bundled.
- **Landmarks**: headers render a `banner`/`<header>`, primary navigation a labelled navigation landmark, and footers
  a `contentinfo`/`<footer>` ending in a legal/utility row.

## Tokens

Everything consumes M2 `theme.json` tokens (color, typography, spacing, radius, border, shadow, focus, `z`). The only
tokens this system adds are three layout-only custom properties: `--wp--custom--header--height`,
`--wp--custom--header--height-compact`, and `--wp--custom--nav--breakpoint` (the desktop→mobile switch, aligned with
core navigation's mobile overlay). There are no raw color/size/font literals in the patterns or
`theme/assets/css/corex-navigation.css`; the one exception is the breakpoint value inside the desktop media query,
because CSS `@media` cannot read custom properties.

## Conditional loading

`corex-navigation.css` is attached to `core/navigation` and `corex/copyright` via `wp_enqueue_block_style`, and the
behavior script is enqueued through `render_block` only when a navigation or CoreX mega-menu block renders — so
nothing loads on pages without a CoreX header/footer (constitution Principle VI).

## RTL

All layout uses logical properties, so headers, drawers, mega panels, and footers mirror correctly in RTL, reusing
the M2 Arabic typography role for Arabic content.

## Out of scope

No header builder, mega-menu builder, or visual editor; no WooCommerce category mega menu or store footer (M9); no
company-kit pages (M4); no Pro features. See `specs/058-header-mobile-navigation/` for the full spec and contracts.
