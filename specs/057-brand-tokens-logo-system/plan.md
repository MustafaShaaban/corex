# Implementation Plan: Brand Tokens and Logo System

**Branch**: `spec/057-brand-tokens-logo-system` | **Date**: 2026-06-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/057-brand-tokens-logo-system/spec.md`

## Summary

Reconcile the existing CoreX token system around `theme/theme.json` without creating a second authority. The work
starts with a machine-readable inventory of every definition and consumer, retains stable slugs, adds only missing
semantic roles, aliases legacy references for at least one minor release, and migrates first-party consumers in
bounded batches. Complete default/dark/light mappings, a scoped CoreX-admin adapter, compatible `brand.json`
validation, font delivery, accessibility evidence, and documentation follow. Logo integration is a separate gated
batch: planning and tests may proceed, but no production mark ships until an owner-approved vector package and its
provenance are recorded.

## Technical Context

**Language/Version**: PHP 8.3; WordPress 7.0+ `theme.json` v3; CSS/SCSS; JavaScript on Node.js 20+ for repository
tooling/tests; JSON; SVG and WOFF2 assets only when their implementation gates are satisfied.

**Primary Dependencies**: WordPress Global Styles/theme JSON APIs and generated custom properties;
`Corex\Theme\BrandResolver` and `ThemeServiceProvider`; existing `@wordpress/scripts` workspaces; WordPress admin
CSS variables with centralized fallback values; Pest, Jest, and Playwright. No new CSS framework, token framework,
icon font, client-side theming library, or external font CDN.

**Storage**: Repository files only. No database schema, options migration, or persistent runtime store. Per-site
`brand.json` remains a file-based runtime override.

**Testing**: Pest contract/unit tests for JSON validity, token definitions, consumers, aliases, style variations,
brand merge/validation, font/logo manifests, contrast matrices, and file-scope rules; existing Jest/build checks;
Playwright/manual evidence for rendered dark/light, focus, forced-colors, zoom, and LTR/RTL fixtures where the
environment is available.

**Target Platform**: WordPress 7.0+ FSE editor and front end, CoreX-owned wp-admin screens, modern browsers with
Arabic shaping and bidirectional text support, and client themes using runtime brand overrides.

**Project Type**: WordPress framework monorepo spanning an FSE theme, independently booted plugins/add-ons, shared
tests, and technical documentation.

**Performance Goals**: At most four self-hosted WOFF2 files; Latin body/interface text stays on the system stack;
`font-display: swap`; no preload without measured benefit; no new global JavaScript; existing block assets remain
conditional; optimized reusable SVGs only; zero undefined production token references.

**Constraints**: `theme.json` remains the single runtime token authority; complete palette/font arrays are required
where `brand.json` replaces lists; stable slugs remain compatible; admin aliases are scoped to CoreX screens and are
not a client-brand source; logical CSS is mandatory; no product code/assets are changed during planning; production
logo work is blocked pending an owner-approved package.

**Scale/Scope**: Reconcile 13 current palette slugs, 2 font-family slugs, 7 type-size slugs, 8 spacing slugs,
3 shadow presets, 4 radius tokens, motion/focus/z groups, 2 style variations, the current brand resolver, 4 primary
admin stylesheets, and the repository-wide block/front-end consumer set. New work is alignment and evidence, not a
component or page redesign.

## Constitution Check

*GATE: Passed before research and re-checked after Phase 1 design against CoreX Constitution v1.2.1.*

- [x] **I. Theme is a skin** — PASS. Theme changes remain presentation-only: tokens, style variations, font
  declarations, and assets. Validation/merge behavior stays in the existing plugin boundary.
- [x] **II. Plugins boot themselves** — PASS / N/A. No boot contract changes; admin and brand services remain
  independently plugin-owned.
- [x] **III. Thin controllers, fat services** — PASS / N/A. No controllers or data access are introduced.
- [x] **IV. Everything injected** — PASS. Any validation added around the existing brand boundary follows the
  container/service pattern; planning adds no construction path.
- [x] **V. Runtime tokens** — PASS (central). `theme.json` remains authoritative; WordPress-generated custom
  properties and runtime `brand.json` overrides are the consumption paths. No build-time token source is added.
- [x] **VI. Conditional assets** — PASS. Existing block asset loading remains conditional; fonts use Global Styles
  declarations; no global library or JavaScript is introduced.
- [x] **VII. Declarative security** — PASS / N/A. No route, request, persistence, or capability surface is added.
  File parsing continues to fail safely through the existing brand boundary.
- [x] **VIII. RTL-first** — PASS (central). Logical properties, Arabic typography, bidi isolation, and LTR/RTL
  fixture evidence are explicit plan gates.
- [x] **IX. No optional dependency is hard** — PASS. No optional plugin, CDN, font service, or UI framework becomes
  required.
- [x] **X. Spec is source of truth** — PASS. This plan implements only clarified Spec 057 and preserves the explicit
  M3/M4/M6 and commercial exclusions.
- [x] **Guard Gate + Definition of Done** — acknowledged. Implementation batches require applicable clean-code,
  WordPress, test, and documentation guards; headless tests/builds; i18n, RTL, WCAG evidence; and honest environment
  gates.

**Post-design re-check:** PASS. The research, data model, contracts, and quickstart introduce no constitution
violation. Complexity tracking is not required.

## Project Structure

### Documentation (this feature)

```text
specs/057-brand-tokens-logo-system/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── asset-contract.md
│   ├── token-contract.md
│   └── verification-contract.md
└── tasks.md                         # created later by /speckit-tasks, not by this plan
```

### Source Code (likely implementation scope)

```text
theme/
├── theme.json                       # canonical definitions, fontFace declarations, global element mappings
├── styles/dark.json                 # complete dark semantic mapping
├── styles/editorial.json            # retain compatibility; complete required replacement arrays
└── assets/fonts/                    # future gated WOFF2 files; no files added during planning

plugins/corex-core/
├── assets/css/corex-admin-tokens.css # planned shared adapter; registered centrally, enqueued only by CoreX screens
├── src/Theme/BrandResolver.php      # retain list replacement; validation/reporting boundary if required
├── src/Theme/ThemeServiceProvider.php
├── config/theme.php
└── README.md

plugins/corex-config/
├── assets/control-panel.css
├── assets/data.css
├── assets/insights.css
├── assets/corex-logo.svg            # legacy migration reference; replace only after asset approval
├── src/Branding/BrandingService.php
├── src/Branding/AdminBranding.php
├── src/ConfigServiceProvider.php
└── README.md

addons/corex-captcha/assets/captcha-admin.css

addons/corex-ui/
├── assets/block-styles.css
└── src/Blocks/*/style.scss

plugins/corex-blocks/src/blocks/entity-field/style.scss
plugins/corex-forms/src/Block/blocks/corex-form/style.scss
plugins/corex-core/assets/css/corex-runtime.css
addons/corex-careers/blocks/jobs/style.scss
addons/corex-kit-portfolio/src/Blocks/projects/style.scss

tests/
├── Unit/Theme/ThemeJsonTest.php
├── Unit/Theme/DesignTokensTest.php
├── Unit/Theme/BrandResolverTest.php
├── Unit/Theme/TokenInventoryTest.php       # planned
├── Unit/Theme/ContrastMatrixTest.php       # planned
├── Unit/Config/BrandingTest.php
├── Fixtures/Theme/                         # planned complete/invalid brand and direction fixtures
└── e2e/                                    # planned rendered mode/RTL/focus evidence

docs-app/src/content/docs/
├── design-system/foundations.md
└── guides/branding.md
```

Other token consumers discovered by the inventory are audit scope first. They enter modification scope only when
they reference an undefined, migrated, or semantically incorrect token. Email templates remain audit-only because
email-client-safe brand injection is a separate constraint; M7 redesign is excluded.

**Structure Decision**: Keep the token authority and font declarations in the FSE theme, brand merge/validation in
the existing core plugin boundary, product logo/admin presentation in `corex-config`, and component consumption in
the owning plugin/add-on. Tests and documentation remain centralized. No new package or runtime subsystem is added.

## Inventory and Migration Strategy

### Source inventory

Create a deterministic inventory artifact from:

1. `theme/theme.json` and every `theme/styles/*.json` definition;
2. WordPress preset/custom-property names generated from each slug/path;
3. every CSS/SCSS/JSON/PHP/JavaScript reference under `theme/`, `plugins/`, `addons/`, and `packages/`;
4. admin fallback literals and alias chains; and
5. documentation examples and `brand.json` fixtures that establish consumer expectations.

Each row follows [data-model.md](./data-model.md) and [contracts/token-contract.md](./contracts/token-contract.md):
definition source, generated property, consumers, mode/admin/client context, classification, replacement/alias,
compatibility window, and evidence status. The inventory test fails on duplicate definitions, undefined references,
unknown classifications, or unrecorded raw design values outside approved admin/layout allowances.

### Classification policy

- **Retained**: existing stable palette, type-size, spacing, shadow, radius, motion, focus, z, and layout slugs that
  already express the intended semantic role.
- **Added**: only proven gaps, initially the body/system and mono typography roles; semantic color roles required to
  avoid using a surface token as inverse text or collapsing raised/strong states; and border width/strength roles
  needed to replace repeated design literals. Existing focus, radius, spacing, and shadow scales are extended only
  when the inventory proves they cannot express the required role. Exact names and values are finalized in the
  inventory contract before implementation.
- **Aliased**: currently referenced but undefined legacy names such as `background`, `foreground`, `danger`,
  `small`, and `medium`, plus any `corex-*` preset reference that cannot migrate atomically. Aliases point to one
  canonical retained/added role.
- **Migrated**: first-party consumers of aliased or semantically incorrect names. Migrate by owning package in small
  testable batches; do not replace every consumer in one unreviewable change.
- **Deprecated**: aliases only after consumers migrate. Keep for at least one minor release and until repository
  scans show zero first-party consumers; document the replacement and removal version separately.

## Canonical Mapping Strategy

- `theme/theme.json` owns every client-facing runtime definition. WordPress generates
  `--wp--preset--*`/`--wp--custom--*`; components consume those names and never a parallel value registry.
- Default and dark behavior use complete semantic mappings. Because palette/font lists replace wholesale, every
  style variation and `brand.json` fixture supplies the complete required slug set.
- `theme/styles/dark.json` becomes the dark-first CoreX product mapping with full parity. The default/light mapping
  remains complete rather than an inversion. `editorial.json` is preserved as a compatible variation, not expanded
  into marketing redesign scope.
- The mapping covers surface/base/raised/inverse roles, text hierarchy, borders, primary/accent interactions,
  status colors, overlays, selection, focus, border width/strength, radius, spacing, and shadow/elevation. Exact
  values are accepted only after the evidence matrix passes.
- Existing Global Styles element mappings and block styles migrate to canonical roles without changing component
  layout or behavior.

### WordPress-generated CSS custom-property mapping

| `theme.json` definition | Generated consumer contract |
|---|---|
| `settings.color.palette[].slug` | `--wp--preset--color--<slug>` |
| `settings.typography.fontFamilies[].slug` | `--wp--preset--font-family--<slug>` |
| `settings.typography.fontSizes[].slug` | `--wp--preset--font-size--<slug>` |
| `settings.spacing.spacingSizes[].slug` | `--wp--preset--spacing--<slug>` |
| `settings.shadow.presets[].slug` | `--wp--preset--shadow--<slug>` |
| nested `settings.custom` path | `--wp--custom--<path-segments>` |

The inventory records the exact generated name for every definition and fails any consumer that references a name
without a canonical definition or active compatibility alias.

## Admin Adapter Strategy

Register one shared stylesheet from `corex-core`, then enqueue it only as a dependency of CoreX-owned admin screen
styles. This lets independently booted add-ons consume the adapter without depending on `corex-config`. It defines
the minimum scoped `--corex-admin-*` roles for surface, muted surface, text, muted text, border, accent/action, hover,
success, warning, error, info, focus, spacing, and radius. Each role maps to a stable WordPress admin CSS variable
where one exists and otherwise to a single documented WordPress palette fallback. Existing admin styles consume the
adapter and stop repeating literal fallback chains. The adapter is not a client-brand authority, does not load
front-end Global Styles, and must not leak to unrelated admin screens.

## Client Override and Rollback Strategy

- Preserve `BrandResolver`: associative maps merge recursively; lists replace wholesale.
- Validate required palette/font slugs for any replacement list and report incomplete lists without inventing
  merge-by-slug behavior. Missing/malformed files continue to leave defaults intact.
- Provide complete valid/invalid/existing fixtures, including current documented override shapes, and update examples
  that imply partial list merging.
- Rollback restores the previous complete arrays and aliases as one unit. It never patches individual components
  with literals or changes merge semantics under incident pressure.
- Client overrides remain free to replace CoreX product values; CoreX logo use remains limited to CoreX-owned admin,
  documentation, and product surfaces.

## Logo and Font Integration Gates

### Logo

The existing navy/cyan `plugins/corex-config/assets/corex-logo.svg` is migration evidence only. Before logo tasks can
move from blocked to ready, the owner must supply or explicitly approve a vector package containing the required
symbol, wordmark, lockup, and monochrome/contrast variants plus source/author, license/usage rights, approval date,
viewBox/variant naming, and accessible-name guidance. The package is validated against
[contracts/asset-contract.md](./contracts/asset-contract.md). No tracing, redraw, placeholder promotion, or raster
variant generation is allowed.

### Fonts

Use at most four provenance-recorded, self-hosted WOFF2 files:

1. Space Grotesk variable Latin, weight 500–700, for display/headings;
2. JetBrains Mono variable Latin, weight 400–600, for code/commands/technical labels;
3. IBM Plex Sans Arabic Arabic subset, weight 400; and
4. IBM Plex Sans Arabic Arabic subset, weight 600.

Latin body/interface text stays on the system stack. All faces use `font-display: swap` and explicit fallbacks.
No font is preloaded by default; a preload needs recorded before/after evidence and must not create an unused-preload
warning. If authoritative upstream files cannot meet the requested variable/subset form, the plan permits an
equal-or-smaller package without broader weights or more than four files, recorded before tasks proceed.

## Accessibility, RTL, and Evidence Strategy

- Generate a semantic-pair matrix for default/light and dark mappings. Automated checks enforce at least 4.5:1 for
  normal text and 3:1 for large text and meaningful non-text UI/focus boundaries. Values that fail do not ship.
- Add a focus matrix covering every supported surface, status, overlay, keyboard state, and the admin adapter.
- Manually verify forced-colors/high-contrast behavior, imagery/gradients, non-color state recognition, 200% text
  zoom, content expansion, and text resizing.
- Use fixtures for Arabic-only, English-only, mixed product names, code/commands, Arabic and Western numerals,
  punctuation, badges, long translations, and nested direction changes. Test both modes and LTR/RTL document
  directions for shaping/order, bidi isolation, focus order, logical alignment, clipping, and overflow.
- Browser/wp-env evidence is **ENVIRONMENT-GATED** when Docker, WordPress, or the compatible browser runtime is not
  available. Headless inventory/schema/contrast tests must still pass; unavailable browser evidence is never marked
  passed.

## Implementation Sequence

1. **Inventory and failing contracts**: produce definition/consumer inventory and tests that expose undefined names,
   incomplete style-variation arrays, raw values, and override compatibility gaps.
2. **Canonical token and mode mapping**: retain/add/alias definitions in `theme.json`; complete dark/light/editorial
   arrays; make headless schema/inventory/contrast tests green.
3. **Consumer migration**: migrate block/front-end styles by owning package while aliases remain; preserve conditional
   assets and layouts.
4. **Admin adapter**: add the scoped adapter, migrate the four initial admin stylesheets, and verify no leakage.
5. **Brand override validation**: preserve merge semantics; add complete/incomplete fixtures, reporting, docs, and
   rollback coverage.
6. **Typography assets**: add only approved/provenance-recorded WOFF2 files and mappings; verify file count, loading,
   fallbacks, scripts, weights, and no default preload.
7. **Logo package (blocked until owner approval)**: validate provenance/package, integrate product variants, migrate
   the default admin/login surface, and retain rollback evidence.
8. **Rendered evidence and docs**: run mode/focus/RTL/zoom/forced-colors checks where available, record environment
   gates honestly, update design-system/branding docs, and run full guards/tests.

Token, admin, override, and typography batches may proceed before the logo package arrives. The logo batch must stay
explicitly blocked; it is not silently skipped or satisfied by the legacy SVG.

## Test and Guard Strategy

Headless required checks:

- `composer validate --no-check-publish`
- PHP lint over relevant PHP paths
- focused Pest tests for theme, branding, token inventory, contrast, and override compatibility
- `composer test`
- `npm.cmd run build`
- `npm.cmd run test:js -- --runInBand`
- `npm.cmd run lint:css` after CSS/SCSS implementation
- docs-app build when documentation changes
- `git diff --check`

Rendered/environment checks:

- WordPress theme/plugin recognition and target readiness command before implementation begins;
- wp-env/browser checks for default/light/dark, admin scope, focus, forced-colors, 200% zoom, font loading, and
  LTR/RTL fixture pages;
- network evidence for font count/preload/conditional block assets; and
- logo rendering/minimum-size checks only after the approved package exists.

Guards: `clean-code-guard` for production changes, `wp-guard` for WordPress/theme/block/admin changes, `test-guard`
for tests, and `docs-guard` for documentation. Woo guard is not applicable unless scope changes, which requires spec
review first.

## Complexity Tracking

No constitution violations. No new package, database, API, framework, or persistent store is introduced.
