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

### Manifest accuracy

A dedicated test (`CompanyKitManifestTest`) cross-checks the blueprint against reality: every
template/part it declares **must** exist as a theme file, and every pattern it composes **must** be one
the UI library (`PatternLibrary`) actually provides. The manifest can't silently drift away from the
files and patterns it points at.

## Setup wizard

A **Setup Wizard** submenu (under the Corex menu) lists the registered kits and, on apply
(nonce + `manage_options`), runs the kit's plan: enables its feature flags, activates its
module plugins, and seeds an idempotent demo Home page. The planning core (`SetupWizard`:
`kits()` + `plan(name)`) is pure and unit-tested; the screen is the thin admin boundary
(a React stepped wizard is the deferred upgrade). Kits declare their flags via
`Blueprint::featureFlags()`.


## Pages

Applying the kit **creates its pages** (Home as the front page, About, Contact) by composing the Corex
section patterns — idempotently (existing slugs are skipped) and tracked (`_corex_kit_page` meta +
`corex_kit_seeded_pages`), so `wp corex reset` removes exactly the kit pages. Declared in
`CompanyBlueprint::pages()`; created by `BlueprintActivator::seedPages()` (planned by the pure `KitPagePlanner`).
Spec 031.
