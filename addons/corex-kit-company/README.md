# Corex Kit — Company Website

A neutral company-website **blueprint**: it composes the Corex UI section patterns (spec 009) with
the theme's universal FSE templates into a deployable, accessible, RTL company site. Composes
modules — **no business logic**. Optional add-on; requires `corex-core` (and `corex-ui` for the
section patterns).

## What it provides

- **FSE templates** (in the theme): `front-page` (composes hero → features → CTA → contact),
  `page`, `single`, `archive`, `search`, `404`, plus enhanced `header` (site title + navigation)
  and `footer` (the `corex/copyright` block) parts. Token-only, logical CSS (RTL), accessible.
- **Blueprint manifest** (the add-on's code): `CompanyBlueprint` declares the kit's required module
  (`corex-ui`), recommended modules (`corex-forms`, `corex-email`), and the templates, parts, and
  patterns it relies on — held in a `BlueprintRegistry` for tooling / a future setup wizard.

## Neutral by design

The kit is intentionally un-branded — its entire look comes from `theme.json` + a client's
`brand.json` (and a style variation), so a client site (e.g. Blackstone) applies its Figma identity
with **no template edits**. The FSE templates live in the theme (the skin); the add-on only
contributes the discoverable manifest, so deactivating it leaves the templates intact.

## Tests

```bash
composer test   # headless: the Blueprint registry/manifest + template presence + token-only scan
```

> Template **presence**, structure references, token-only, and the manifest are covered headlessly.
> The **visual/editor** correctness of the templates and pattern composition should be confirmed in a
> browser.
