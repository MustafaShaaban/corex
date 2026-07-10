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

A **Setup Wizard** submenu (under the Corex menu) runs a guided **nine-step flow** (Welcome, Brand,
Choose a kit, Demo content, Review plan, Backup, Apply, Launch checklist, Done). The JS wizard
mounts over the server-rendered flow (which stays as the no-JS fallback) and consumes the cap+nonce
gated REST surface `GET/POST corex/v1/setup/state|plan|apply`. It is composed from pure, unit-tested
services (`Corex\Kit\Setup\`): `SetupProgress` (step/percentage/resume/blocked state), `ConflictResolver`
(Keep/Replace/Suffix — the default is always Keep, so existing content is never overwritten silently),
and `LaunchChecklist` (indexing/debug/environment/email/security/legal/forms/performance).

- **Brand (FR-135):** persists real `brand.*` Config fields (company name, tagline, phone, email, address,
  primary action, social links) via CoreX Settings.
- **Demo levels (FR-137):** Minimal / Standard / Full seed progressively larger page sets
  (`CompanyBlueprint::pages($level)`).
- **Conflicts (FR-139/143):** a page that already holds user content is only ever Replaced or created
  under a suffixed slug from an explicit operator choice; a required backup confirmation gates Apply.
- **Rollback (FR-141):** the activator records `_corex_kit_page` dispositions (`created`/`adopted`/
  `replaced`/`suffixed`) so a reset reverses only tracked changes.

The planning core (`SetupWizard`: `kits()`, `plan(name, level)`, `brandFields()`, `demoLevels()`,
`conflictChoices()`) is pure and unit-tested. Kits declare their flags via `Blueprint::featureFlags()`.


## Pages

Applying the kit **creates its pages** (Home as the front page, About, Contact) by composing the Corex
section patterns — idempotently (existing slugs are skipped) and tracked (`_corex_kit_page` meta +
`corex_kit_seeded_pages`), so `wp corex reset` removes exactly the kit pages. Declared in
`CompanyBlueprint::pages()`; created by `BlueprintActivator::seedPages()` (planned by the pure `KitPagePlanner`).
Spec 031.

## Applying a kit never leaves a blank front page (spec 041)

Applying a kit classifies each declared page and acts accordingly, so a re-apply is safe and a pre-existing
empty page is filled in rather than skipped:

- **create** — the slug doesn't exist → the page is created with the kit's content.
- **adopt** — the slug exists but the page is **empty** (or an un-populated kit placeholder) → it is populated
  in place (same page id), recorded as `adopted`.
- **skip** — the slug exists with real user content → it is left untouched.

The front page is set to the declared home whenever that page was created or adopted (so a kit always yields a
real front page); if the home is skipped because the user already has content there, the existing front page is
kept. The decision is the pure `Corex\Provisioning\PagePlanner` (in corex-core); `BlueprintActivator` is the
WordPress boundary and returns an `ApplyOutcome` the wizard renders as a created/populated/skipped summary.

**Reset safety:** `wp corex reset` (soft) **deletes** pages the kit created but only **empties** a page it
adopted (a pre-existing page the user owned is never deleted) — tracked via the `_corex_kit_page` meta
(`created` | `adopted`).

## Driving activation from elsewhere (spec 042)

The kit framework binds `Corex\Provisioning\KitProvisioner` (a corex-core interface) to `BlueprintKitProvisioner`,
so other code (the Add-ons screen, the dashboard) can list applicable kits, compute a **read-only** apply preview,
and run apply through the one shared path — without depending on this add-on. With the kit framework inactive the
interface resolves to a `NullKitProvisioner` and consumers degrade gracefully.
