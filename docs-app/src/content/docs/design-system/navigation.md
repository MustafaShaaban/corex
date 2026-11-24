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
| Mega menu item | `corex/mega-menu` (CoreX UI add-on) | An item of the Navigation block that opens a panel; the add-on registers the template part area "Mega menu panels" (`mega-menu`). |
| Mega menu panels | `corex/megamenu-simple`, `corex/megamenu-services`, `corex/megamenu-product`, `corex/megamenu-docs` | Panel patterns: the content of a panel, offered when a panel is made. |
| Footer (default) | `theme/parts/footer.html` | Simple variant: legal/utility row. |
| Footer variants | `corex/footer-simple`, `corex/footer-corporate`, `corex/footer-saas`, `corex/footer-newsletter`, `corex/footer-locations`, `corex/footer-legal` | Registered under **CoreX** + core **footer**. |

Patterns are auto-registered by WordPress from `theme/patterns/*.php` (block themes). The **CoreX** pattern category
and the conditional assets are registered by `Corex\Theme\NavigationServiceProvider` in `corex-core` — the theme
stays a presentation-only skin (constitution Principle I).

## Accessibility & behavior

- **Mobile navigation** uses the core navigation block's overlay: an accessible toggle, focus trap, `aria-expanded`,
  Escape-to-close, and focus return — CoreX does not re-implement these.
- **Mega menus** are items of the menu (spec 137). With the CoreX UI add-on on, add a "Mega menu item" to a
  Navigation block, type its label in place, and choose its panel in the item's settings: a template part of the
  "Mega menu panels" area, opened for editing from there, or made from a panel pattern under "New panel". The item
  is a list item of the menu holding a button and its panel; it is not shown until it has a label and a panel. In
  the bar the panel opens under the header, as wide as the header's content, and the header keeps its height:
  after a mouse rests on the item for about 150ms, or on a click, a tap, Enter or Space. It closes when focus
  leaves it, on a click outside and on Escape, which returns focus to the button; Down, Up, Home and End move among
  its links, and Left and Right among the menu's items, mirrored right to left. Without JavaScript a panel opens
  while the pointer or the focus is inside its item. Outside a CoreX header the panel is as wide as the Navigation
  block; a theme widens it by giving an ancestor `position: relative`, with the elements between static.
- **Panels in a child theme**: a panel part belongs only on a site with the CoreX UI add-on on, which registers
  its area. A child theme ships one as a file in its `parts/` and lists it in its `theme.json` `templateParts`
  with `"area": "mega-menu"`, after the CoreX theme's header and footer entries, which it repeats: WordPress
  merges that list with the parent's by position. A draft or an empty panel is not shown, nor is its item.
- **Headers saved earlier** keep their native `<details>` panels: the buildless `theme/assets/js/corex-navigation.js`
  still opens one at a time and closes it on Escape or an outside click, returning focus to its summary.
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
`--wp--custom--header--height-compact`, and `--wp--custom--nav--breakpoint`. The last is the navigation breakpoint,
`settings.custom.nav.breakpoint` in `theme.json`: the one width at which a CoreX header changes between its phone form
and its desktop form, `782px` in the CoreX theme. A child theme sets its own in its `theme.json`. It must be a whole or
decimal number of `px` (`1024px`, `1023.5px`); anything else, such as `64em` or a bare `1024`, is read as `782px`, and
a value below `600px` is read as `600px`. WordPress's own Navigation block folds at 600px; inside a `.corex-header` it
folds at the token instead (spec 137).

There are no raw color or font literals in the patterns or in `theme/assets/css/corex-navigation.css`; the one size
that file writes itself is the search toggle's, a `2.75rem` target holding a `1.25rem` icon. CSS `@media` cannot read
custom properties, so every rule that depends on the width lives in `theme/assets/css/corex-navigation-collapse.css`,
written at the default; when a theme sets another width, CoreX prints that file with the theme's width in its place.

## Conditional loading

`corex-navigation.css` is attached to `core/navigation` and `corex/copyright` via `wp_enqueue_block_style`, and the
behavior script is enqueued through `render_block` only when a navigation or a `<details>` mega panel renders, so
nothing loads on pages without a CoreX header/footer (constitution Principle VI). The mega menu item's script and
style are declared in its `block.json` and load only where a menu holds an item. `corex-navigation-collapse.css` is
enqueued through `render_block` when a `core/navigation` block renders, and through `enqueue_block_assets` wherever
WordPress loads every block's style instead, which includes the block editor's screens.

## RTL

All layout uses logical properties, so headers, drawers, mega panels, and footers mirror correctly in RTL, reusing
the M2 Arabic typography role for Arabic content.

## Out of scope

No header builder, mega-menu builder, or visual editor; no WooCommerce category mega menu or store footer (M9); no
company-kit pages (M4); no Pro features. See `specs/058-header-mobile-navigation/` for the full spec and contracts.
