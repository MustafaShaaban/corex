---
title: Apply a brand
description: Runtime design tokens — theme.json to CSS variables to a per-site brand.json.
---

Corex never hardcodes colours, sizes, or fonts. Design tokens are **runtime**, not
build-time (constitution Principle V).

## The token source

`theme/theme.json` (v3) is the single source of truth for tokens, exposed by WordPress as
CSS custom properties (`--wp--preset--color--*`, `--wp--preset--spacing--*`, …). Blocks and
patterns consume those variables — never a raw hex/size/font.

## Per-site overrides — `brand.json`

A site ships a `brand.json` whose values are **deep-merged** over `theme.json` at runtime
by `BrandResolver` (hooked on `wp_theme_json_data_theme`): nested **maps** merge key-by-key
(siblings preserved), while **scalars and lists replace wholesale**. A rebrand is
configuration, not a recompile.

Because the palette and font-family **lists replace wholesale**, a replacement list must be
**complete** — it must keep every canonical semantic role (the 13 color roles + the `heading`
and `arabic` font roles). `BrandOverrideValidator` checks each replacement list before the
merge: a **complete** list is applied as-is; an **incomplete** list (one that would drop a
required role) is **reported (logged) and ignored**, so the complete defaults stay in place.
Nested map overrides (e.g. a single radius value) are unaffected and still merge.

```jsonc
// brand.json — a COMPLETE palette replacement (every canonical role present)
{
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary",      "color": "#0B5FFF", "name": "Primary" },
        { "slug": "primary-dark",  "color": "#0941B8", "name": "Primary dark" },
        { "slug": "accent",        "color": "#C9A25E", "name": "Accent" },
        { "slug": "accent-dark",   "color": "#AD8643", "name": "Accent dark" },
        { "slug": "surface",       "color": "#FFFFFF", "name": "Surface" },
        { "slug": "surface-alt",   "color": "#F5F6F8", "name": "Surface alt" },
        { "slug": "border",        "color": "#E2E5EA", "name": "Border" },
        { "slug": "ink",           "color": "#14151A", "name": "Ink" },
        { "slug": "ink-soft",      "color": "#5B616D", "name": "Ink soft" },
        { "slug": "success",       "color": "#2F8F5B", "name": "Success" },
        { "slug": "warning",       "color": "#B5781F", "name": "Warning" },
        { "slug": "error",         "color": "#C2433B", "name": "Error" },
        { "slug": "info",          "color": "#175CD3", "name": "Info" }
      ]
    }
  }
}
```

The space beside a page's text, on a phone and in any window narrower than its content, is
the theme's root padding. It is a map, so a site changes it without restating anything
else:

```jsonc
// brand.json: a wider gutter
{
  "styles": {
    "spacing": {
      "padding": {
        "left": "var(--wp--preset--spacing--50)",
        "right": "var(--wp--preset--spacing--50)"
      }
    }
  }
}
```

Give both sides the same value: `theme.json` has no logical side, and a page read right to
left is correct only while the two are equal.

To recolor just one role without restating the list, override the **scalar** instead (e.g. a
single `settings.custom` value), which merges. Text drawn on `accent` takes
`settings.custom.color.onAccent`, graphite by default, so a brand whose `accent` is dark sets it to a light value
in its `brand.json` (`{ "settings": { "custom": { "color": { "onAccent": "#FFFFFF" } } } }`). The brass
band (the call-to-action pattern) is drawn for a light `accent`: with a dark one, its button loses its label, so such
a brand puts its call to action on the dark band, which keeps CoreX's graphite and brass unless the brand also sets
the `band*` custom colours. Point the resolver
at the file with `config('theme.brand_path')`, or place it at the active theme root. A **missing or malformed**
`brand.json` leaves the defaults intact (logged), so removing the file is a clean rollback.
Style variations (e.g. `theme/styles/dark.json`) override tokens only — the theme stays a skin.

## Compatibility aliases & migration

When a token slug is renamed, the old generated property is kept as a **compatibility alias**
for **one minor release** (introduced in one minor, removable in the next) so existing
consumers keep resolving while they migrate. An alias is only marked removal-eligible once no
first-party consumer references it. The full retained / added / aliased / migrated mapping and
the deprecation window are recorded in
`specs/057-brand-tokens-logo-system/inventories/classifications.json` and `consumer-migration.md`.

## Product brand vs. client brand

`brand.json` controls the **client site's** identity. The **CoreX product** identity (the Core X
logo on the wp-admin login and dashboard) is separate and never imposed on a client site: a per-site
`brand.logo_url` override always wins over the bundled product mark. The login draws the lockup that suits its
card (the dark lockup on a Dark card, the light one on a Light card; System is dark first). The CoreX theme, and
only the CoreX theme, links the CoreX favicon when a site has no site icon of its own; a site icon chosen in
WordPress always wins. The product logo variants, the favicon set and their usage rules are in the
`plugins/corex-config/README.md` "Brand identity" section (spec 134, DECISIONS #647).

## Sections and the button (spec 134)

The CoreX theme's main button is `accent` with the `onAccent` label, 48px tall
(`settings.custom.control.height`), and every button style is one height. corex-ui offers three section styles
for a Group, the **quiet band** (`surface-alt`), the **dark band** and the **brass band**, and the hero and
call-to-action patterns use the dark and brass bands. The dark band is dark in both styles and reads the `band*`
custom colours, so it keeps CoreX's graphite and brass under any palette unless the brand sets those too. The
brass band is the brand's `accent` with `onAccent` text and button; it is drawn for a light `accent` (see
above). The bands are offered only where the theme can draw them: the CoreX theme, whatever palette a
`brand.json` gives it, or a theme whose palette holds `primary`, `surface`, `surface-alt`, `accent`,
`accent-dark` and `inverse`. A client theme with a palette of its own does not see them (DECISIONS #642).

## RTL

All styling uses logical properties (`margin-inline-start`, `inset-inline-end`, …), so
Arabic (RTL) layouts are correct by default. The block build also emits an automatic
`*-rtl.css` per stylesheet.


## Fonts

CoreX targets WordPress 7+, which ships **Appearance → Fonts** (the Font Library). There are three sources of
fonts; use each for what it's good at:

| Source | Use it for | Production brand identity? |
|---|---|---|
| **CoreX bundled/theme fonts** | The framework's self-hosted default typefaces, registered in `theme.json`. | Reproducible defaults. |
| **Client brand fonts** (generated client theme) | The client's final, approved typefaces — source-controlled in the **client theme** and registered through its `theme.json` / a style variation. | **Yes — this is the production path.** |
| **WordPress 7 Appearance → Fonts** | Temporary testing, prototyping, and editor-managed additions. Optional admin/editor tooling. | Not on its own — see below. |

Guidance:

- WordPress 7's Font Library is **optional** — CoreX does not require it. It's convenient for trying a face in
  the editor before committing.
- For production, **source-control the approved fonts in the client theme** and register them in `theme.json`
  (or a style variation), so the brand identity is reproducible across local/staging/production and survives
  migration. Don't rely on a manually uploaded font for the production brand unless its deployment/migration is
  documented.
- **Licensing:** only commit or upload fonts the project is licensed to use.

For example, on the Acme site you might trial a heading face via Appearance → Fonts while designing, then, once
approved, drop the licensed font files into `sites/acme/acme-theme/assets/fonts/` and register them in
`sites/acme/acme-theme/theme.json` so every environment renders identically.

### The curated CoreX Font Library collection

CoreX registers its brand typefaces — **Space Grotesk**, **JetBrains Mono**, and **IBM Plex Sans Arabic** (all
OFL, self-hosted) — as a **CoreX** collection in **Appearance → Fonts**, so an editor can install them from the
WordPress 7 Font Library in one click (`wp_register_font_collection`). This is optional editor tooling; the
production path for a client's brand fonts is still source-controlled fonts registered in the client theme's
`theme.json` (above). The framework serves the woff2 from `corex-core/assets/fonts/`.

## The design system (spec 033)

`theme.json` ships a real token system: an expanded palette (surface/border/ink-soft + state colours), a
multi-step type scale, a full spacing scale, **shadow** presets (`--wp--preset--shadow--sm|md|lg`), and **radius**
tokens (`--wp--custom--radius--sm|md|lg|full`). Buttons/links/headings are styled from tokens, and the card
blocks use shadow + radius for depth. Since spec 134 the palette is graphite and brass, bound to the brand
contract (`design/tokens/brand-contract.json`), with the band and on-accent custom colours beside it. Switch the
whole look to the **Dark** style variation in the Site Editor; a site that chose Dark before spec 134 keeps its
saved values until it chooses Dark again. Everything stays token-only: no hardcoded colours or sizes.
