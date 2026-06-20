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

## Semantic color roles, modes, and compatibility

The 13 color slugs above are the **canonical brandable roles**. `theme.json` also defines **added semantic roles**
(surface-raised, surface-strong, inverse, overlay, selection, selection-text) for elevation, overlays, and text
selection, plus a set of **one-minor-release compatibility aliases** (e.g. `background`, `foreground`, `danger`,
the `corex-*` legacy names) kept so existing consumers keep resolving during migration. The complete
retained / added / aliased / migrated classification and the deprecation window live in
`specs/057-brand-tokens-logo-system/inventories/classifications.json` and `consumer-migration.md`.

**Modes** are WordPress **style variations**, not new tokens: the default is the dark-first base, with `theme/styles/dark.json`
and `theme/styles/editorial.json` replacing the palette/font arrays only. Each mode ships a **complete** replacement
list so every semantic role stays defined; the theme remains a logic-free skin.

## Typography & self-hosted fonts

Four roles map to three self-hosted, OFL-licensed families — **no external font CDN**:

| Role | Family | Self-hosted file | Weights |
|---|---|---|---|
| Display / heading | Space Grotesk | `space-grotesk-latin-500-700.woff2` | 500–700 |
| Body | system UI stack | *(none — `system-ui` fallback)* | — |
| Technical / code | JetBrains Mono | `jetbrains-mono-latin-400-600.woff2` | 400–600 |
| Arabic (RTL) | IBM Plex Sans Arabic | `ibm-plex-sans-arabic-400.woff2`, `…-600.woff2` | 400, 600 |

The package is capped at **four WOFF2 files**; each declares `font-display: swap`, **no `preload`** (no measured
need), and a `system-ui` fallback so text is readable before the face loads. Provenance — upstream Google Fonts
commit, OFL license files, subset tooling, weights, scripts, and sha256 checksums — is recorded in
`theme/assets/fonts/manifest.json`.

## Admin token adapter

CoreX wp-admin screens read a scoped semantic adapter, `--corex-admin-*` (surface, text, border, action, success,
warning, error, focus, space, radius), defined in `plugins/corex-core/assets/css/corex-admin-tokens.css`. It is
**scoped to `.wrap`** (never `:root`/`html`/`body`), carries a `prefers-color-scheme: dark` override, and is
**registered but never globally enqueued** (handle `corex-admin-tokens`): only CoreX-owned admin styles declare it
as a dependency, so it loads on CoreX screens and nowhere else. It is the CoreX product chrome, never a client-site
or global token authority.

## Guidelines

- **RTL** — style with **logical properties** (`margin-inline-start`, `inset-inline-end`, …) so Arabic layouts
  are correct by default; `postcss-rtlcss` handles edge cases only (Principle VIII).
- **Accessibility (WCAG 2.2 AA)** — convey state by more than color (icon + text); every interactive element is
  keyboard-operable with a visible focus ring (the focus token); meet contrast against the surface tokens. The
  semantic color and focus pairs are checked against a contrast matrix (4.5:1 normal text, 3:1 large/non-text/focus)
  and a forced-colors / 200%-zoom / reduced-motion review; the matrices and results live in
  `specs/057-brand-tokens-logo-system/inventories/accessibility-evidence.md`. WordPress-rendered browser evidence is
  environment-gated where Docker/wp-env or a compatible browser runtime is unavailable.
- **Focus states** — use the focus token consistently; never remove an outline without an equivalent visible
  indicator.
- **Motion** — use the motion tokens; respect `prefers-reduced-motion` (drop non-essential animation).
- **Icons** — Corex does **not** bundle a heavyweight icon font as a hard dependency; inline accessible SVG (with
  `aria-hidden` when decorative, a label when meaningful) or a core/`social-icons` approach.

## Overriding for a brand

Put a client's palette/scale into `theme.json`, or a per-site `brand.json` for overrides; run `wp corex
brand:apply`. The tokens above are what a brand overrides — a rebrand is configuration, not a recompile.
