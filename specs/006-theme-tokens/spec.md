# Feature Specification: Theme + Design Tokens

**Feature Branch**: `006-theme-tokens`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "Theme + design tokens — a complete theme.json (v3) as the single source of truth for design tokens exposed as CSS variables; per-site brand.json runtime overrides (deep-merge resolution, deepest key wins, validate — the headless-testable PHP core); style variations (alternate theme.json styles); the theme stays a skin (presentation only, no logic). Honors the constitution (runtime tokens, logical CSS/RTL, WCAG AA, i18n, no CSS framework). Delivers the token system + brand.json overrides + style variations + skin discipline, proven by headless override tests + theme.json validity + one example style variation."

## Overview

This feature makes Corex's look configurable, not coded. A complete `theme.json` is the single source of
truth for design tokens (colors, typography, spacing, layout), exposed by WordPress as CSS custom
properties. A site can ship a `brand.json` that overrides token values at runtime — so a rebrand is
configuration, not a recompile — resolved by deep-merging `brand.json` onto the theme defaults (the
headless-testable PHP core). Alternate looks ship as style variations (e.g. dark, alt-brand). Throughout,
the theme stays a **skin**: presentation only — templates/parts/patterns that consume tokens, with no
business logic, no CPT registration, no plugin bootstrapping. The "users" are Corex theme/site
developers; site editors and visitors are the beneficiaries.

## Clarifications

### Session 2026-06-08

- Q: Where does a site's brand.json live and how is it supplied? → A: At the active theme root (`theme/brand.json`) by default; the path is configurable via the Config engine (`theme.brand_path`). The resolver reads it at runtime when assembling theme.json data.
- Q: What happens when brand.json overrides a token path that does not exist in the defaults? → A: It is added (the deep merge inserts new keys), never corrupting sibling keys — same recursive merge as an existing path.
- Q: How are the merged tokens applied to the page? → A: Via the WordPress `wp_theme_json_data_theme` filter — the resolver deep-merges brand.json onto the theme.json data WordPress assembles, so the effective tokens (and their CSS custom properties) reflect the overrides.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Design tokens come from one source (Priority: P1)

A developer relies on `theme.json` as the single source of design tokens; every color, font size, and
spacing value used in the theme resolves to a token exposed as a CSS custom property. No hardcoded
hex/size/font appears in the theme's styling, and no build-time token system (no Tailwind/Bootstrap
variables) is introduced.

**Why this priority**: The token source is the irreducible core — brand overrides and style variations
all operate on it; it is what Principle V ("tokens are runtime, never build-time") requires.

**Independent Test**: load the theme and confirm `theme.json` defines the token palettes (colors, font
sizes, spacing) and that they are exposed as CSS custom properties; scan the theme's styling and confirm
no raw hex/px values and no CSS-framework variables.

**Acceptance Scenarios**:

1. **Given** the theme, **When** it loads, **Then** `theme.json` defines the color/typography/spacing/
   layout token palettes and they are available as CSS custom properties.
2. **Given** the theme's styling, **When** it is inspected, **Then** every color/size/font value resolves
   from a token (no hardcoded hex/px/font); no CSS-framework variable system is present.
3. **Given** `theme.json`, **When** it is validated, **Then** it is a valid theme.json (v3) the editor and
   front end consume.

### User Story 2 - A site rebrands via brand.json (Priority: P1)

A site ships a `brand.json` with a few token overrides (e.g. brand color, base font). At runtime the
framework resolves the effective tokens by deep-merging `brand.json` onto the theme defaults (deepest key
wins), so the site shows the overridden values without editing `theme.json` or recompiling anything. A
missing `brand.json` changes nothing; a malformed one is ignored (logged) and the defaults stand.

**Why this priority**: Runtime rebranding is the headline value — "a rebrand is configuration, not a
recompile" (Principle V). The merge/validate logic is the headless-testable PHP core.

**Independent Test**: with no `brand.json`, confirm the effective tokens equal the theme defaults; add a
`brand.json` overriding one nested token and confirm only that token changes (deep merge, deepest key
wins); supply a malformed `brand.json` and confirm the defaults stand and the problem is logged.

**Acceptance Scenarios**:

1. **Given** no `brand.json`, **When** tokens are resolved, **Then** the effective tokens equal the theme
   defaults (no change).
2. **Given** a `brand.json` overriding a nested token, **When** tokens are resolved, **Then** only that
   token changes; sibling/unrelated tokens keep their defaults (deep merge, deepest key wins).
3. **Given** a malformed `brand.json`, **When** tokens are resolved, **Then** the defaults stand and the
   problem is logged — never a fatal or a blank theme.
4. **Given** the resolved tokens, **When** they reach the page, **Then** the overridden values appear as
   the corresponding CSS custom properties.

### User Story 3 - Alternate looks as style variations (Priority: P2)

A developer offers alternate looks (e.g. dark, alt-brand) as style variations — full alternate
`theme.json` styles — that an editor can select. Selecting a variation changes the design tokens
site-wide without touching the default theme.json or any code.

**Why this priority**: Style variations are how a site switches looks (dark mode, sub-brands) the
WordPress-native way; they build on the token source.

**Independent Test**: place an alternate style variation; confirm it is registered/available for the
editor to select and that selecting it changes the effective tokens (e.g. dark palette) site-wide.

**Acceptance Scenarios**:

1. **Given** an alternate style variation file, **When** the theme loads, **Then** it is registered and
   selectable as a style variation.
2. **Given** a selected style variation, **When** the page renders, **Then** its tokens apply site-wide
   (the default theme.json is unchanged).
3. **Given** a style variation, **When** it is inspected, **Then** it uses tokens (no hardcoded values)
   and meets the same constraints as the default.

### User Story 4 - The theme is a skin (Priority: P2)

The theme contains presentation only: FSE templates, parts, patterns, and style variations that consume
tokens. It registers no post types/taxonomies, bootstraps no plugin, and holds no business logic.
Deactivating the theme breaks presentation but never data or the API.

**Why this priority**: Principle I ("the theme is a skin, not a skeleton") — separability of
presentation from domain is what lets a rebrand never risk data or behavior.

**Independent Test**: inspect the theme and confirm it contains no business logic, CPT/taxonomy
registration, or plugin bootstrapping; deactivate the theme and confirm data/API endpoints still work
(only presentation changes).

**Acceptance Scenarios**:

1. **Given** the theme, **When** it is inspected, **Then** it contains no business logic, no CPT/taxonomy
   registration, and no plugin bootstrapping.
2. **Given** the theme is deactivated, **When** the data/API layer is exercised, **Then** it still works
   (only presentation is affected).
3. **Given** the brand-override resolver, **When** it is located, **Then** it lives in corex-core (a
   service-provider-registered component), not in the theme — keeping the theme logic-free.

### Edge Cases

- **Missing brand.json**: defaults stand; no error.
- **Malformed brand.json** (invalid JSON / wrong shape): ignored, logged; defaults stand.
- **Override of a non-existent token path**: ignored (or added as a new value per the documented rule) —
  never corrupts the rest of the token tree.
- **Deep nested override**: only the deepest specified key changes; sibling keys at every level are
  preserved (true deep merge, not shallow replace).
- **Contrast failure in an override**: a brand override that breaks WCAG AA contrast is surfaced (warned)
  — the system does not silently ship inaccessible colors. (Detection is best-effort/advisory.)
- **RTL**: all theme styling uses logical properties; the theme is correct in RTL by default.
- **No optional plugins**: tokens, overrides, and variations work with ACF/Woo/Polylang absent.

## Requirements *(mandatory)*

### Functional Requirements

**Token source**

- **FR-001**: `theme.json` (v3) MUST be the single source of design tokens — color, typography/font-size,
  spacing, and layout palettes — exposed as CSS custom properties.
- **FR-002**: The theme's styling MUST consume tokens only — no hardcoded colors/sizes/fonts and no
  CSS-framework variable system.
- **FR-003**: `theme.json` MUST be valid theme.json (v3) that the editor and front end consume.

**Brand overrides**

- **FR-004**: The framework MUST resolve effective tokens by deep-merging a site `brand.json` (at the
  active theme root, path configurable via `theme.brand_path`) onto the theme defaults — deepest key
  wins; sibling keys preserved at every level; an override to an unknown path is added, not dropped.
- **FR-005**: A missing `brand.json` MUST leave the defaults unchanged; a malformed one MUST be ignored
  and logged, with the defaults standing (never a fatal or blank theme).
- **FR-006**: Overridden token values MUST reach the page as the corresponding CSS custom properties,
  applied through the WordPress `wp_theme_json_data_theme` filter.
- **FR-007**: The brand-override resolution (merge + validate) MUST be exercisable in headless automated
  tests.

**Style variations**

- **FR-008**: Alternate looks MUST be shippable as style variations (full alternate `theme.json` styles),
  registered/available for an editor to select.
- **FR-009**: Selecting a style variation MUST apply its tokens site-wide without changing the default
  `theme.json`; a variation MUST itself use tokens (no hardcoded values).

**Skin discipline**

- **FR-010**: The theme MUST contain presentation only — no business logic, no CPT/taxonomy registration,
  no plugin bootstrapping.
- **FR-011**: Deactivating the theme MUST break presentation only — never data or the API.
- **FR-012**: The brand-override resolver MUST live in corex-core (service-provider-registered), not in
  the theme.

**Cross-cutting**

- **FR-013**: All theme styling MUST use logical CSS properties (RTL-correct by default); colors MUST meet
  WCAG 2.2 AA contrast; template strings MUST be translation-ready.
- **FR-014**: Everything MUST work with no optional plugin (ACF/Woo/Polylang) installed.
- **FR-015**: The feature MUST ship one example style variation (e.g. dark) proving the variation +
  token-only discipline end-to-end.

### Key Entities

- **Design token**: a named design value (color, size, spacing, font) in `theme.json`, exposed as a CSS
  custom property.
- **theme.json**: the single source of token defaults (v3).
- **brand.json**: a per-site override file deep-merged onto the defaults at runtime.
- **Brand-override resolver**: the corex-core component that merges/validates brand overrides onto the
  defaults (headless-testable).
- **Style variation**: a full alternate `theme.json` style selectable in the editor.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: **100%** of the theme's color/size/font values resolve from `theme.json` tokens — **zero**
  hardcoded hex/px/font and **zero** CSS-framework variables in the theme styling.
- **SC-002**: `theme.json` validates as theme.json (v3) with **zero** schema errors.
- **SC-003**: A `brand.json` overriding one nested token changes **only** that token; **100%** of sibling
  keys at every level are preserved (deep merge).
- **SC-004**: A missing or malformed `brand.json` leaves the defaults intact in **100%** of cases (no
  fatal, no blank theme); a malformed one is logged.
- **SC-005**: An example style variation applies its tokens site-wide without editing the default
  `theme.json`; the theme registers **zero** post types/taxonomies and bootstraps **zero** plugins.
- **SC-006**: With ACF, WooCommerce, and Polylang uninstalled, **100%** of the override-resolution tests
  pass headlessly.
- **SC-007**: The brand-override resolution is covered by headless automated tests; `theme.json` and the
  example variation are validated.

## Assumptions

- **Audience**: Corex theme/site developers; site editors/visitors benefit. No application UI beyond the
  theme.
- **Token model**: `theme.json` v3 `settings`/`styles` per the WordPress block-theme convention; tokens
  surface as `--wp--preset--*` CSS custom properties consumed by templates/parts/patterns/style.css.
- **brand.json location/shape**: a JSON file at a known per-site location (e.g. the active child theme or
  a configured path) whose shape mirrors the `theme.json` token tree it overrides; resolved at the WP
  moment the theme data is assembled.
- **Override semantics**: deep recursive merge — arrays/objects merged key-by-key, scalars replaced;
  deepest specified key wins; an override to an unknown path is added (documented), never corrupting
  siblings.
- **Resolver placement**: the merge/validate resolver lives in corex-core (`Corex\Theme` /
  `Corex\Support`), registered via a service provider and applied through the theme.json filter; the
  theme itself stays logic-free.
- **Contrast checking**: WCAG AA contrast on shipped palettes is verified for the defaults + the example
  variation; runtime brand-override contrast checking is best-effort/advisory.
- **Scope boundary**: a full pixel-perfect design system / many finished templates, the forms module, JS
  interactivity, and the build/asset pipeline are **out of scope**; this spec delivers the token system +
  brand.json overrides + style variations + skin discipline + one example variation.
- **Foundation dependency**: built on corex-core (container, providers, config, BootLogger for logging a
  malformed brand.json); the theme depends on no plugin to render.
- **Environment**: developed against the working WordPress install (WP ≥ 7.0, theme.json v3); the Corex
  theme is the active theme; Environment Gate satisfied.
