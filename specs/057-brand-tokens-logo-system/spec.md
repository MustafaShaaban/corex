# Feature Specification: Brand Tokens and Logo System

**Feature Branch**: `spec/057-brand-tokens-logo-system`

**Created**: 2026-06-19

**Status**: Draft

**Input**: User description: "Approve the M2 brand foundation handoff and specify the CoreX brand tokens and logo
system without implementing the visual redesign."

## Goal

Establish one accessible, brandable CoreX logo and visual-token contract that aligns the product identity,
front-end blocks, FSE theme, and restrained admin product surfaces without creating a competing token source or
expanding into later design milestones.

## Problem Statement

CoreX already has substantial theme, block, admin, and design-system foundations, but the current visual values and
usage are not yet reconciled with the approved dark-first CoreX identity. Token consumers use a mixture of current
theme presets, custom properties, WordPress admin fallbacks, and historical naming. Typography does not yet express
the approved Latin display, technical, and Arabic roles, while the logo system and mode-specific usage rules are not
recorded as an implementable contract. Changing isolated values would create drift, duplicate sources, inaccessible
states, or a CoreX product identity that cannot be rebranded for client sites.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Apply one coherent CoreX visual foundation (Priority: P1)

As a CoreX maintainer, I need one documented brand-token contract so product, theme, block, and admin surfaces use
the same visual roles and can be changed without hunting through component-specific values.

**Why this priority**: Every later design milestone depends on stable token names and mode behavior. Parallel or
ambiguous sources would multiply migration work across navigation, kits, components, forms, and documentation.

**Independent Test**: A reviewer can trace every approved token group to one canonical runtime source and identify
how theme, block, and admin consumers obtain the corresponding semantic role.

**Acceptance Scenarios**:

1. **Given** existing token definitions and consumers, **When** the token contract is reviewed, **Then** every
   retained, added, aliased, migrated, or deprecated name has one documented purpose and owner.
2. **Given** a component needs a brand value, **When** its styling is reviewed, **Then** it consumes the canonical
   semantic token or documented admin fallback instead of introducing a component-local value.
3. **Given** a client overrides CoreX defaults, **When** the approved override mechanism is used, **Then** the site
   can express its own brand without changing component code or inheriting unnecessary CoreX product branding.

---

### User Story 2 - Use CoreX accessibly in dark, light, LTR, and RTL contexts (Priority: P1)

As a site visitor or administrator, I need readable typography, visible interaction states, and correct direction
behavior in both supported color modes so the interface remains usable regardless of language or mode.

**Why this priority**: Accessibility, Arabic, RTL, and light-mode support are baseline Free/Core requirements, not
later polish or commercial features.

**Independent Test**: Representative content and interactive states can be evaluated in dark/light and LTR/RTL
combinations without missing semantic roles, invisible focus, clipped text, or physical-direction assumptions.

**Acceptance Scenarios**:

1. **Given** dark or light mode, **When** representative text, controls, borders, statuses, overlays, and focus rings
   are inspected, **Then** each state has a documented semantic mapping and meets the accessibility baseline.
2. **Given** Arabic or mixed Arabic/Latin content, **When** headings, body text, code, labels, numbers, and long
   strings render, **Then** the documented font and direction strategy preserves shaping, order, and readability.
3. **Given** keyboard navigation, **When** focus moves across supported surfaces, **Then** the focus indicator remains
   visible and is not communicated by color alone.

---

### User Story 3 - Present the CoreX mark consistently (Priority: P2)

As a maintainer producing CoreX product and documentation surfaces, I need a reusable geometric Core X logo system
so the mark is recognizable, legible, and used consistently without embedding it into client-site identity.

**Why this priority**: A coherent logo system completes the product foundation, but it must remain separate from
the client-brand override contract.

**Independent Test**: A reviewer can select the correct symbol, wordmark, lockup, or monochrome variant for a
supported background and size using documented rules.

**Acceptance Scenarios**:

1. **Given** an approved CoreX-owned surface, **When** a logo is selected, **Then** its variant, minimum size,
   clear space, contrast treatment, and accessible-name behavior follow the documented usage rules.
2. **Given** a client website, **When** its brand is configured, **Then** the CoreX product mark is not forced into
   the public site identity.
3. **Given** dark and light surfaces, **When** the logo renders, **Then** an appropriate vector variant remains
   legible without a separate raster asset for each viewport or density.

---

### User Story 4 - Preserve existing surfaces during token migration (Priority: P2)

As an engineer, I need a bounded migration and compatibility contract so token alignment does not silently break
existing blocks, style variations, admin screens, or brand overrides.

**Why this priority**: Existing consumers use historical slugs and admin fallbacks. A visual replacement without an
inventory and compatibility path would create regressions that ordinary unit tests may not expose.

**Independent Test**: The implementation plan can map current consumers, define compatibility handling, and verify
representative existing surfaces before removing or changing any historical token name.

**Acceptance Scenarios**:

1. **Given** an existing token consumer, **When** its token is changed or deprecated, **Then** a documented migration
   or compatibility path prevents an undefined custom property.
2. **Given** an admin surface where theme tokens are unavailable, **When** it renders, **Then** a documented semantic
   fallback remains usable and consistent with WordPress admin conventions.
3. **Given** a current per-site override, **When** the canonical token contract changes, **Then** compatibility and
   rollback behavior are documented before implementation.

### Edge Cases

- A user selects a mode or client palette whose accent fails against one or more surfaces.
- Arabic headings contain Latin product names, code, numerals, or punctuation.
- A font asset fails to load or a requested script/weight is unavailable.
- A logo appears at a very small size, on a photographic background, or inside a constrained square icon slot.
- A historical block references a token slug absent from the current theme definition.
- Theme tokens are unavailable in wp-admin and the WordPress fallback palette is active.
- High-contrast or forced-colors modes override authored colors and shadows.
- A client override replaces a palette list rather than merging individual semantic entries.
- Dark/light switching occurs without client-side JavaScript or before optional assets load.
- A future edition badge needs neutral naming without introducing entitlement behavior.

## Scope

- CoreX logo system integration: geometric Core X symbol, wordmark, lockup, monochrome/contrast variants, usage
  rules, accessible naming, and separation from client-site branding.
- Brand token definitions and naming rules.
- Typography roles for Space Grotesk, JetBrains Mono, IBM Plex Sans Arabic, body/system fallbacks, weights,
  line-height, letter spacing, and fluid type scale.
- Semantic color roles for dark-first and complete light behavior, including surfaces, text hierarchy, borders,
  links, interactive states, statuses, overlays, and selection.
- Spacing, radius, shadow/elevation, border, and focus-ring token groups.
- Dark/light mode selection and semantic parity.
- RTL typography, mixed-script, bidirectional isolation, and logical-direction behavior.
- Alignment between the canonical theme token source, WordPress-generated custom properties, style variations,
  per-site overrides, admin product fallbacks, and block/front-end consumers.
- Restrained admin/product visual-base alignment without redesigning screens or workflows.
- Documentation updates for token ownership, logo usage, modes, typography, overrides, accessibility, and migration.
- Version/readiness documentation only when implementation changes a release surface or readiness evidence.

## Out of Scope

- Full admin dashboard redesign.
- Header, mobile navigation, mega-menu, footer, or template-part patterns.
- Company Kit page expansion.
- Blocks or components expansion beyond token alignment.
- Forms redesign.
- Documentation-site visual redesign.
- Marketing landing-page rebuild.
- Pro licensing, entitlement, distribution, or commercial UI.
- Front-office editor workspace.
- Heavy animation or a new motion system.
- Specs 058 or 059 and any implementation belonging to them.

## Design Dependency

Implementation depends on the approved [M2 Brand Foundation Handoff](../../design/handoffs/brand-foundation.md).
The handoff approves the identity, typography, mode, accessibility, RTL, brandability, and scope direction. Planning
must still inventory current token consumers and confirm the production vector assets and minimum font files/weights
before implementation tasks are finalized. A later visual exploration may refine values only by updating the handoff
and this spec before code changes.

## Likely Affected Areas

- FSE theme token definitions, style variations, and global element styles.
- Per-site brand override documentation and compatibility behavior.
- CoreX-owned logo assets and product-brand usage documentation.
- Front-end and editor block styles that consume current token names.
- Admin product styles that currently use theme-token references with WordPress palette fallbacks.
- Design-system and branding documentation.
- Token, metadata, build, accessibility, RTL, and visual-regression checks.
- Release/readiness documentation if the implementation changes versioned behavior.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST retain one canonical runtime design-token source and MUST NOT add a parallel token
  registry, component-local palette, CSS framework variable layer, or build-time token authority.
- **FR-002**: The token inventory MUST classify every existing and proposed token as retained, added, aliased,
  migrated, or deprecated before consumers are changed.
- **FR-003**: Token names MUST describe stable semantic roles or reusable scales rather than a single component,
  page, product edition, or transient campaign.
- **FR-004**: The color contract MUST define semantic parity for dark and light modes across surfaces, text,
  borders, links, controls, statuses, overlays, selection, and focus.
- **FR-005**: The typography contract MUST define display/heading, body/interface, code/technical, and Arabic roles
  with explicit fallbacks and only the weights/scripts required by actual usage.
- **FR-006**: Arabic content MUST use IBM Plex Sans Arabic in the approved roles while mixed-script content and
  technical fragments retain readable bidirectional order.
- **FR-007**: The spacing, radius, shadow/elevation, border, and focus-ring scales MUST each have documented names,
  purposes, and supported usage boundaries.
- **FR-008**: Focus-ring tokens MUST produce a visible indicator across every supported surface and mode and MUST
  not depend on color as the sole indication of focus.
- **FR-009**: The CoreX logo system MUST define symbol, wordmark, lockup, and monochrome/contrast variants with
  minimum-size, clear-space, background, and accessible-name rules.
- **FR-010**: CoreX product logo assets MUST be reusable vectors and MUST NOT be imposed on client-site identity.
- **FR-011**: Theme tokens, generated CSS custom properties, style variations, and per-site overrides MUST use a
  documented mapping with no undefined or competing names.
- **FR-012**: Existing block/front-end consumers MUST be inventoried and aligned without expanding their features
  or redesigning their layouts.
- **FR-013**: Existing admin product consumers MUST map to the shared semantic roles while retaining documented
  WordPress admin fallbacks where theme tokens are unavailable.
- **FR-014**: The implementation MUST preserve logical-property and RTL-first styling and MUST NOT add physical
  direction assumptions.
- **FR-015**: Client branding MUST remain configurable through the existing runtime override mechanism without a
  recompile or component-code change.
- **FR-016**: Future edition/status token names MAY be neutral and extensible, but this feature MUST NOT implement
  Pro entitlement, licensing, gating, or commercial interface behavior.
- **FR-017**: Brand and design-system documentation MUST identify token ownership, consumption, overrides, modes,
  typography, logo usage, accessibility, and migration behavior.
- **FR-018**: Implementation MUST preserve existing behavior until compatibility and rollback tests cover any
  renamed or removed token.
- **FR-019**: This specification-creation task MUST NOT change product/design implementation code, runtime behavior,
  logo assets, font assets, release metadata, or component styling.

## Implementation Notes

- Start implementation planning with a machine-readable inventory of current theme definitions and all CSS/custom-
  property consumers. Do not begin by replacing palette values.
- Prefer retaining stable WordPress preset slugs and mapping them to approved semantics. Add or migrate a slug only
  when the inventory proves the current contract cannot express the required role.
- Keep `theme.json` authoritative. Style variations express mode mappings; per-site `brand.json` remains the runtime
  override seam.
- Treat admin styles as a deliberate adapter: shared semantic intent with WordPress-native fallbacks when theme
  variables are not present. Do not inject the full front-end theme into wp-admin.
- Keep logo assets presentation-only and separate from framework/business logic.
- Use self-hosted fonts where licensing and asset provenance permit. Planning must specify actual weights, formats,
  subsets, preload decisions, and fallbacks before files are added.
- Use logical properties and semantic mode roles so later M3/M4 work consumes the system without reopening it.

## Accessibility Requirements

- Meet WCAG 2.2 AA contrast for normal text, large text, meaningful non-text graphics, controls, focus indicators,
  and interaction states in both modes.
- Verify focus visibility against every supported surface, including error/status surfaces and overlays.
- Do not communicate state or edition by color alone.
- Support keyboard use, forced-colors/high-contrast behavior, zoom, text resizing, content expansion, and visible
  selection states.
- Provide documented accessible-name and decorative-image handling for each logo usage category.
- Record contrast evidence and any constrained token pairings rather than claiming that every arbitrary client
  override is automatically accessible.

## RTL Requirements

- Use logical properties and direction-aware alignment by default.
- Define Arabic font roles, fallbacks, weights, line height, and shaping expectations.
- Verify mixed Arabic/Latin headings, code, commands, numerals, punctuation, badges, and product names.
- Use bidirectional isolation where embedded technical fragments could reorder surrounding text.
- Do not create mirrored logo artwork unless the approved mark specifically requires directional meaning; document
  the logo as non-directional by default.

## Mobile/Responsive Requirements

- Preserve bounded fluid type scales and readable line lengths from narrow mobile screens to wide layouts.
- Verify text zoom, long translations, Arabic content expansion, and compact technical labels without clipping or
  horizontal scrolling.
- Provide compact and full logo lockup guidance based on available space, not separate raster assets per breakpoint.
- Token alignment MUST NOT introduce new page layouts, breakpoint systems, or component behavior in this feature.

## Performance Requirements

- Font delivery MUST be self-hostable, subset by required scripts where practical, and limited to approved weights
  and formats.
- Font loading MUST retain readable fallback text and avoid invisible-text blocking.
- Logo delivery MUST use optimized reusable vectors without global JavaScript.
- Token alignment MUST not add a CSS framework, icon font, client-side theming framework, or unconditional asset
  bundle.
- Existing conditional block-asset loading behavior MUST remain intact.

## Acceptance Criteria

- [ ] Existing required tests and builds remain passing after implementation.
- [ ] Token names are internally consistent, documented, and mapped to one canonical source.
- [ ] No duplicate or competing token source is introduced.
- [ ] `theme.json`, WordPress-generated CSS custom properties, style variations, per-site overrides, admin fallbacks,
      and existing CSS consumers are aligned where applicable.
- [ ] Dark and light behavior is documented for every semantic color role and representative state.
- [ ] The RTL font and mixed-script strategy is documented and verified with representative content.
- [ ] The accessibility baseline includes recorded contrast and focus-visibility checks for both modes.
- [ ] The logo system documents supported variants, spacing, size, contrast, client-brand separation, and accessible
      naming.
- [ ] Existing block and admin surfaces receive token alignment only; no feature or layout redesign is included.
- [ ] Font and logo assets have documented provenance, bounded delivery requirements, and fallbacks before shipping.
- [ ] Documentation explains token ownership, consumption, override, compatibility, and rollback behavior.
- [ ] No product/design code, assets, styling, or runtime behavior is implemented in this spec-creation task.

## Tests/Checks Required

- Parse and schema-validate all changed token and style-variation data.
- Add or update token-contract tests covering required groups, unique slugs, semantic mappings, and absence of
  undefined references.
- Scan block, theme, and admin styles for raw values and unknown/retired custom-property names, with documented
  allowances for WordPress admin fallbacks and functional layout constants.
- Verify per-site override merge behavior and compatibility for retained/migrated token names.
- Run existing PHP, JavaScript, build, metadata, and documentation checks.
- Run automated contrast checks for representative dark/light token pairings and manual review for meaningful
  non-text graphics and focus states.
- Verify keyboard focus, forced-colors behavior, zoom, LTR, RTL, Arabic, and mixed-script samples.
- Verify font fallback/loading and confirm only required assets load.
- Verify logo SVG structure, accessible-name usage, contrast variants, and rendering at documented minimum sizes.
- Run visual regression or browser checks where the environment supports them; mark unavailable browser evidence as
  environment-gated rather than passing.
- Run `git diff --check` and the applicable documentation, clean-code, WordPress, and test guards before delivery.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of token definitions and discovered consumers are classified and mapped to one documented
  canonical contract before implementation is accepted.
- **SC-002**: Zero duplicate token authorities or undefined token references remain in the verified implementation.
- **SC-003**: All representative dark/light and LTR/RTL accessibility checks meet the documented WCAG 2.2 AA
  baseline or produce an explicit blocking finding.
- **SC-004**: The approved typography roles render representative Latin, Arabic, mixed-script, and technical content
  without clipping, invisible text, or direction-order defects at supported responsive sizes.
- **SC-005**: Every supported logo usage can be selected from documented vector variants without creating a new
  raster asset for a viewport, density, or mode.
- **SC-006**: Existing required test suites and builds retain their pre-implementation pass status.
- **SC-007**: The specification-creation change contains zero product, runtime, style, font, or logo-asset changes.

## Risks

- Renaming established slugs can break blocks, style variations, and client overrides outside obvious theme files.
- Dark-first values can produce incomplete or low-contrast light mappings if roles are treated as direct inversions.
- Loading three font families without strict weights/subsets can harm rendering performance.
- Arabic may inherit Latin display assumptions that harm shaping, rhythm, or mixed-script readability.
- Bringing front-end tokens directly into wp-admin can fight WordPress conventions or increase CSS scope.
- Logo work can accidentally become a marketing redesign or leak CoreX identity into client sites.
- Automated contrast checks can miss gradients, imagery, forced-colors behavior, and non-text focus visibility.

## Rollback Notes

- Keep the implementation isolated so the token/logo alignment can be reverted without reverting unrelated product
  behavior or data.
- Preserve a documented mapping of previous token names and values until all consumers and overrides are verified.
- If a renamed token causes regressions, restore the previous name or compatibility alias first; do not patch each
  component with local values.
- Font and logo assets must be removable without breaking content, administration, or runtime boot.
- No database migration is expected; if planning later discovers one, update and re-review this spec before work.

## Assumptions

- The approved M2 handoff is the product owner's design approval for this engineering-spec stage.
- The existing theme token source and runtime brand override mechanism remain architectural constraints.
- Exact production token values, logo vectors, and font files will be finalized during planning from the approved
  direction and current-consumer inventory, not invented during this spec-creation task.
- Accessibility, RTL, internationalization, and client brandability remain Free/Core capabilities.
