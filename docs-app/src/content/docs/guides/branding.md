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
by `BrandResolver` (hooked on `wp_theme_json_data_theme`): nested maps merge key-by-key,
siblings are preserved, scalars/lists replace. A rebrand is configuration, not a recompile.

```jsonc
// brand.json — only the tokens you want to override
{
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary", "color": "#0B5FFF", "name": "Primary" }
      ]
    }
  }
}
```

Point the resolver at it with `config('theme.brand_path')`, or place it at the active
theme root. Style variations (e.g. `theme/styles/dark.json`) override tokens only — the
theme stays a skin.

## RTL

All styling uses logical properties (`margin-inline-start`, `inset-inline-end`, …), so
Arabic (RTL) layouts are correct by default. The block build also emits an automatic
`*-rtl.css` per stylesheet.
