# Research: Brand Tokens and Logo System

## Decision 1: Inventory before value changes

**Decision**: Generate a deterministic inventory of token definitions, generated custom-property names, style
variations, all repository consumers, raw fallbacks, documentation examples, and override fixtures before editing
values.

**Rationale**: The current source contains both defined names and legacy references. A source-first inventory makes
undefined names and compatibility impact testable and prevents visual search-and-replace work from hiding breaks.

**Current evidence**:

- `theme/theme.json` defines 13 palette slugs; heading/Arabic families; 7 type sizes; 8 spacing steps; 3 shadows;
  radius, motion, focus, and z groups.
- `dark.json` supplies only a partial palette; `editorial.json` supplies a broader but still incomplete replacement
  palette.
- Consumers reference undefined or differently named properties including `background`, `foreground`, `danger`,
  `corex-primary`, `corex-info`, `corex-success`, `corex-warning`, `corex-danger`, `white`, `small`, and `medium`.

**Alternatives considered**:

- Change palette values first: rejected because it cannot prove consumer coverage.
- Inventory only theme files: rejected because admin, blocks, add-ons, documentation, and email readers also consume
  brand values.

## Decision 2: Preserve one runtime authority

**Decision**: Keep `theme/theme.json` as the only client-facing runtime token authority. Style variations provide
complete mode mappings, WordPress generates custom properties, and `brand.json` remains the runtime override seam.

**Rationale**: This follows Constitution Principle V and existing `ThemeServiceProvider` integration. A separate
JavaScript, SCSS, or admin registry would drift and make client rebranding require compilation.

**Alternatives considered**:

- New shared JSON token package: rejected as a competing authority.
- SCSS variables or a CSS framework config: rejected as build-time tokens.
- Component-local custom properties with values: rejected because they fragment ownership.

## Decision 3: Compatibility-first classification

**Decision**: Retain stable slugs; add only proven semantic gaps; alias undefined legacy names; migrate first-party
consumers by package; deprecate aliases for at least one minor release and only remove them after zero first-party
references.

**Rationale**: Existing blocks, style variations, documentation, and client overrides can depend on current names.
Aliases make the transition observable and reversible.

**Alternatives considered**:

- Clean-break rename: rejected as an unnecessary client and block compatibility risk.
- Keep every legacy name permanently: rejected because it preserves ambiguity and duplicate vocabulary.

## Decision 4: Complete semantic mode parity

**Decision**: The default/light and dark mappings must contain the complete required palette/font slug set. Values
are accepted only after semantic-pair and focus evidence passes. Editorial remains a compatible variation rather than
a redesign target.

**Rationale**: Palette/font arrays replace wholesale in current merge behavior. Partial arrays cause missing custom
properties, and simple inversion does not cover status, borders, inverse text, overlays, or focus.

**Alternatives considered**:

- Partial dark overrides: rejected because required properties disappear or inherit unpredictably.
- Automatic color inversion: rejected because it cannot establish accessible semantic pairings.

## Decision 5: Scoped admin adapter

**Decision**: Register the minimum `--corex-admin-*` semantic adapter from `corex-core` and enqueue it only as a
dependency of CoreX-owned admin screen styles, mapping to stable WordPress admin variables when available and to
centralized WordPress palette fallbacks otherwise.

**Rationale**: Theme Global Styles are not available across wp-admin. Current admin files repeat fallbacks and raw
values. Registration in `corex-core` lets independently booted add-ons use the adapter without depending on
`corex-config`; screen-owned enqueueing preserves conditional scope and avoids a second client-brand source.

**Alternatives considered**:

- Load front-end theme tokens in wp-admin: rejected because it broadens CSS scope and fights WordPress conventions.
- Retain per-declaration fallbacks: rejected because they drift across screens.
- Globally declare admin aliases: rejected because they could affect unrelated admin screens.

## Decision 6: Preserve `brand.json` merge semantics

**Decision**: Keep recursive associative-map merging and wholesale list replacement. Require complete palette/font
replacement arrays, validate required slugs, and add compatibility fixtures and migration guidance.

**Rationale**: `BrandResolver` and its tests explicitly guarantee this behavior. Changing to merge-by-slug would be
a runtime contract change and could prevent clients from intentionally replacing a list.

**Alternatives considered**:

- Merge known lists by slug: rejected as a silent behavior change.
- New versioned schema: deferred; current needs do not justify a second schema.

## Decision 7: Bound font delivery

**Decision**: Use at most four self-hosted WOFF2 files: Space Grotesk variable Latin 500–700, JetBrains Mono variable
Latin 400–600, and IBM Plex Sans Arabic Arabic subsets at 400 and 600. Latin body/interface text stays on the system
stack. Use `font-display: swap`; preload only with recorded evidence.

**Rationale**: The role split meets the approved identity and RTL direction without making every page pay for
multiple static weights or a custom Latin body face.

**Alternatives considered**:

- Static file per weight: rejected due to request count.
- External CDN: rejected as a hard external dependency and privacy/availability risk.
- Preload all faces: rejected because most pages will not need every script/role.

## Decision 8: Gate logo implementation on provenance

**Decision**: Treat the bundled navy/cyan SVG as migration evidence. Require an owner-approved production vector
package and recorded provenance before implementing the geometric Core X family.

**Rationale**: The current asset encodes the old direction and uses a text element. Planning cannot claim a final
mark or usage rights from a verbal direction alone.

**Alternatives considered**:

- Trace/redraw during implementation: rejected because the result would lack authoritative approval/provenance.
- Promote the existing SVG: rejected because it conflicts with the approved direction.
- Use temporary generated art: rejected because temporary marks become accidental production assets.

## Decision 9: Evidence is a first-class plan artifact

**Decision**: Define complete semantic contrast/focus matrices and a repeatable direction fixture matrix. Automated
checks cover numeric pair thresholds; manual/browser evidence covers forced-colors, imagery, gradients, shaping,
keyboard order, zoom, and overflow.

**Rationale**: Token values are only valid in pairings and rendered contexts. Individual swatch review cannot prove
focus visibility, bidi order, or mode parity.

**Alternatives considered**:

- Manual-only visual review: rejected as non-repeatable.
- Automated-only color tests: rejected because they cannot cover forced colors, imagery, shaping, or focus order.

## Decision 10: Separate headless gates from environment evidence

**Decision**: Schema, inventory, compatibility, and contrast checks are required headless gates. WordPress/wp-env,
browser, network, forced-colors, zoom, and rendered RTL evidence run when their environments are available and remain
explicitly `ENVIRONMENT-GATED` otherwise.

**Rationale**: Unavailable infrastructure is not a pass, but it should not prevent the repository from defining and
running deterministic checks that do not require it.

**Alternatives considered**:

- Treat unavailable browser checks as passed: rejected as false evidence.
- Block all token work on Docker: rejected because inventory/schema/contrast planning and tests are headless.
