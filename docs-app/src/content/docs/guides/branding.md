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

To recolor just one role without restating the list, override the **scalar** instead (e.g. a
single `settings.custom` value), which merges. Point the resolver at the file with
`config('theme.brand_path')`, or place it at the active theme root. A **missing or malformed**
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
logo on the wp-admin login/dashboard) is separate and never imposed on a client site — a per-site
`brand.logo_url` override always wins over the bundled product mark. The approved product logo
variants and usage rules are documented in the `plugins/corex-config/README.md` "Brand identity"
section.

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
approved, drop the licensed font files into `themes/acme/assets/fonts/` and register them in
`themes/acme/theme.json` so every environment renders identically.

> A future, optional curated CoreX font collection via the WP Font Library APIs is on the backlog; it is not
> required for this workflow.

## The design system (spec 033)

`theme.json` ships a real token system: an expanded palette (surface/border/ink-soft + state colours), a
multi-step type scale, a full spacing scale, **shadow** presets (`--wp--preset--shadow--sm|md|lg`), and **radius**
tokens (`--wp--custom--radius--sm|md|lg|full`). Buttons/links/headings are styled from tokens, and the card
blocks use shadow + radius for depth. Switch the whole look with a **style variation** (Dark or Editorial) in
the Site Editor. Everything stays token-only — no hardcoded colours or sizes.
