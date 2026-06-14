---
title: Foundations
description: The Corex design tokens — color, typography, spacing, shadow, radius, layout, motion, focus, z-index — and the cross-cutting guidelines (RTL, accessibility, icons, motion).
---

Every Corex component consumes **design tokens**, never a hardcoded value. Tokens live in the theme's
`theme.json` and are exposed as CSS custom properties at runtime; a per-site `brand.json` overrides them without
a recompile (spec 006). This is the single source of truth for the look of a Corex site.

## Token groups

| Group | CSS custom property | Values |
|---|---|---|
| **Color** | `--wp--preset--color--<slug>` | primary, primary-dark, accent, accent-dark, surface, surface-alt, border, ink, ink-soft, success, warning, error, info |
| **Typography** | `--wp--preset--font-family--<slug>`, `--wp--preset--font-size--<slug>` | heading / body families; the size scale |
| **Spacing** | `--wp--preset--spacing--<step>` | the spacing scale (used for padding/margin/gap) |
| **Shadow** | `--wp--preset--shadow--<slug>` | sm, md, lg |
| **Radius** | `--wp--custom--radius--<slug>` | sm (0.25rem), md (0.5rem), lg (1rem), full (9999px) |
| **Layout & grid** | `--wp--style--global--content-size`, `--wp--style--global--wide-size` | content 768px, wide 1200px |
| **Motion** | `--wp--custom--motion--duration--<fast\|base\|slow>`, `--wp--custom--motion--easing--<standard\|emphasized>` | 150 / 250 / 400 ms; standard & emphasized easings |
| **Focus** | `--wp--custom--focus--width`, `--…--color`, `--…--offset` | 2px, accent, 2px |
| **Z-index** | `--wp--custom--z--<base\|dropdown\|sticky\|overlay\|modal\|toast>` | 0 / 1000 / 1100 / 1200 / 1300 / 1400 |

### Consuming a token

```css
.my-component {
	border-radius: var(--wp--custom--radius--md);
	transition: opacity var(--wp--custom--motion--duration--base) var(--wp--custom--motion--easing--standard);
}
.my-component:focus-visible {
	outline: var(--wp--custom--focus--width) solid var(--wp--custom--focus--color);
	outline-offset: var(--wp--custom--focus--offset);
}
.my-overlay { z-index: var(--wp--custom--z--modal); }
```

Never write a raw hex, px size, font, radius, shadow, or duration in a component — add a token if one is missing
(constitution Principle V).

## Guidelines

- **RTL** — style with **logical properties** (`margin-inline-start`, `inset-inline-end`, …) so Arabic layouts
  are correct by default; `postcss-rtlcss` handles edge cases only (Principle VIII).
- **Accessibility (WCAG 2.2 AA)** — convey state by more than color (icon + text); every interactive element is
  keyboard-operable with a visible focus ring (the focus token); meet contrast against the surface tokens.
- **Focus states** — use the focus token consistently; never remove an outline without an equivalent visible
  indicator.
- **Motion** — use the motion tokens; respect `prefers-reduced-motion` (drop non-essential animation).
- **Icons** — Corex does **not** bundle a heavyweight icon font as a hard dependency; inline accessible SVG (with
  `aria-hidden` when decorative, a label when meaningful) or a core/`social-icons` approach.

## Overriding for a brand

Put a client's palette/scale into `theme.json`, or a per-site `brand.json` for overrides; run `wp corex
brand:apply`. The tokens above are what a brand overrides — a rebrand is configuration, not a recompile.
