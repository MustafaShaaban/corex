# Implementation Plan: Tokens and type

**Branch**: `spec/121-tokens-and-type` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

## Summary

Spec 121 is revamp release R2.1. It writes the brand down once and checks every copy against it, makes the theme draw
its own spacing, type, radius and motion, gives Arabic pages the Arabic face, sets interface labels in Space Grotesk,
retires the old logo, ships CoreX's first Arabic catalogue with a proof that runs CoreX in Arabic, and generates the
Foundations docs page's token tables from the shipped files.

## Technical Context

**Language/Version**: PHP 8.3 (Pest 3), JavaScript (Node 22, Playwright, Jest 30), CSS, theme.json v3
**New dependency**: none
**Storage**: none. The contract, the catalogue and the generated tables are committed files.
**Testing**: Pest unit tests for the contract, the light/system parity, the theme scales, the fonts and the catalogue;
Jest for the Foundations generator and the catalogue tools; Playwright for the computed type and spacing, the Arabic
face, the admin typefaces and the Arabic proof.
**Target**: WordPress 7.0+, the CoreX theme, wp-admin and wp-login.php; the docs site build.
**Constraints**: no front-end CSS loaded in wp-admin; no font file added; preset slugs kept; the Arabic files load
only where Arabic is drawn.

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| I The theme is a skin | Scales and type rules are theme.json data; the theme gains no PHP. |
| II Plugins boot themselves | The script-translation lookup is registered by corex-core's own provider. |
| IV Everything is injected | The new PHP is one small class built by the provider; no `new` inside a method. |
| V Tokens are runtime | theme.json stays the theme's source; the contract is a test fixture, never a build step. |
| VI Assets load conditionally | No new stylesheet; the Arabic faces carry a character range so they load only when used. |
| VII Security | No route, no input. The fixture is test-only (mu-plugin copied by CI). |
| VIII RTL | Arabic is proven in Arabic text; logical properties throughout. |
| IX Optional dependencies | None added. |
| X Spec first | This spec, plan and tasks before the code. |
| Guard Gate | clean-code-guard and wp-guard on PHP/CSS/JS, test-guard on tests, docs-guard on docs. |

## Research and decisions

1. **Contract location and shape (#460).** `design/tokens/brand-contract.json`: `colors`, `shadows` (light and
   dark), `radius`, `motion`, each entry naming its admin custom property, and where they exist its email palette
   member and theme key. Values are the shipped admin set (BRD-D3). The theme's colours and shadows have no binding
   until US-BRD-01; the test says so by reading only the bindings the contract declares. Rejected: generating the
   admin CSS from theme.json (spec 057 chose a scoped adapter; DECISIONS #5 forbids build-time tokens).
2. **Light parity (#461).** The System block inside `@media (prefers-color-scheme: light)` and the explicit Light
   block carry the same declarations; a test parses both and compares them. Light shadows take the design's graphite
   (`rgb(20 21 26 / …)`), overlay and backdrop the System values, login card and field the Light values. Motion:
   `--corex-admin-motion-instant|fast|base|slow` = 80/140/200/320ms and `--corex-admin-ease` = the standard curve;
   the spinner's 900ms turn moves to `--corex-admin-motion-spin`.
3. **Theme scales (#462).** `defaultSpacingSizes`, `defaultFontSizes` false and `shadow.defaultPresets` false. Spacing
   slug `25` = 0.75rem. Font sizes keep their slugs with design values and role names (xs Caption 0.75, sm Small 0.875,
   base Body 1, lg Lead 1.375, xl Title 1.75, 2xl H3 2.25, new 3xl H2 3, hero H1 4, new display 5.5), fluid between
   375px and the 1200px wide size. Elements h1–h6 take sizes, line heights (1.05, 1.15, 1.2, 1.3, 1.4, 1.5), weight 600
   and letter spacing (−0.03em to −0.01em). Radius keys `xs` 0.1875rem, `sm` 0.375rem, `md` 0.625rem, `lg` 1rem,
   `full` 999px (the admin's names). Motion keys `instant`, `fast`, `base`, `slow`.
4. **Arabic face (#463).** theme.json `styles.css` holds `:lang(ar|fa|ur|ps|ckb|ug)` rules for body (Plex Arabic, 1.8)
   and headings (Plex Arabic then Space Grotesk, 1.3); the Arabic `fontFace` entries declare
   `unicodeRange: U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF`, so Latin in Arabic text falls to
   the next family and an English page downloads nothing. Admin: the RTL block adds body and heading line heights.
5. **Typeface roles (#464).** `--corex-admin-font-ui` = the display face; buttons, tabs, navigation and labels use it
   in the admin shell, the settings tabs and the login; theme.json sets the button element, the navigation block and
   form labels to the heading family. The font test counts `theme/assets/fonts` and `plugins/corex-core/assets/fonts`
   and requires byte-identical copies.
6. **Old logo (#465).** Delete `corex-config/assets/corex-logo.svg`; `BrandingTest` pins the lockup; manifest, README
   and spec 111's contract updated.
7. **Arabic catalogue (#466).** `plugins/corex-core/languages/corex-ar.po` is the source; `scripts/i18n-arabic.mjs`
   compiles `corex-ar.mo` (committed), checks placeholders and markup, reports strings of the template with no Arabic,
   and writes one script-translation JSON per built bundle (named `corex-ar-<md5 of the path inside its plug-in>.json`,
   WordPress's own scheme) when the bundles are built. corex-core's `ScriptTranslations` answers
   `load_script_translation_file` with that folder when WordPress's own folder has no file. CLI help (`packages/cli`)
   is out of scope: nobody reads it in Arabic on a host with no command line.
8. **Arabic proof (#467).** Fixture `tests/e2e/fixtures/corex-e2e-arabic.php`: with cookie `corex_e2e_locale=ar` the
   request runs in Arabic (`determine_locale`, `locale`), right to left, and every `corex` string a PHP or JS call
   asked for that came back unchanged is recorded in `window.__corexArabicMissing` (names the glossary keeps are not
   counted). `tests/e2e/quality/arabic-proof.spec.js` opens every discovered admin screen, the kit pages, the default
   form with errors and the email previews in four cells and fails naming the strings and the screen; it saves the
   screenshots. The reader's checklist is `specs/121-tokens-and-type/arabic-review.md`.
9. **Foundations (#468).** `scripts/foundations-tokens.mjs` writes the tables between markers in `foundations.md` and,
   with `--check`, fails naming the first token that differs. Jest runs the check on every pull request; the docs
   build runs it as `prebuild`.

## Structure

```text
design/tokens/brand-contract.json                     contract (new)
theme/theme.json, theme/styles/*.json                 scales, Arabic, typeface roles
plugins/corex-core/assets/css/corex-admin-tokens.css  parity, motion, RTL, ui face
plugins/corex-core/assets/css/corex-admin-{shell,login}.css, corex-config/assets/control-panel.css
plugins/corex-core/src/I18n/ScriptTranslations.php    script translations lookup (new)
plugins/corex-core/languages/corex-ar.po|.mo          catalogue (new)
scripts/i18n-arabic.mjs, scripts/foundations-tokens.mjs
tests/Unit/Theme/BrandContractTest.php, ThemeScalesTest.php, FontAssetContractTest.php (changed)
tests/Unit/I18n/ArabicCatalogueTest.php, ScriptTranslationsTest.php
tests/e2e/fixtures/corex-e2e-arabic.php, tests/e2e/quality/arabic-proof.spec.js, tests/e2e/tokens-and-type.spec.js
tests/foundations-tokens.test.js, tests/i18n-arabic.test.js
docs-app/src/content/docs/design-system/foundations.md
```

## Risks

- **Every theme page moves** (spacing, heading sizes): before and after screenshots of every template at 1280 and
  375px are committed under `evidence/` and the changes listed in the changelog.
- **Admin and login look changes reach Muva and Perego**: named in Client impact.
- **Machine-drafted Arabic**: the native review is owed before the release ships (NFR-D3); the catalogue is complete
  so the proof can run.
