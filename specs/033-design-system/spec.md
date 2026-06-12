# Feature Specification: Design system overhaul

**Feature Branch**: `feature/033-design-system` · **Created**: 2026-06-12 · **Status**: Draft (forward, full Spec Kit)

**Input**: "The design is very poor. The blocks look too simple/flat." (A richer token system, depth, and polish.)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A rich, modern token system (Priority: P1)
A site builder gets a proper design system out of the box — a fuller color palette (with surface/border/state
colors), a real type scale, a complete spacing scale, **shadow** presets, and **border-radius** presets — so
the site looks designed, not bare.

**Acceptance Scenarios**:
1. **Given** the theme, **When** loaded, **Then** `theme.json` exposes an expanded palette, a multi-step type
   scale, a full spacing scale, shadow presets, and radius presets — all as tokens.
2. **Given** the existing tokens (primary/accent/surface/ink, sm/lg/hero, 30/50/80), **When** the new ones are
   added, **Then** the existing ones still exist (no breaking changes to current blocks/patterns).

### User Story 2 - Polished base + block styling (Priority: P1)
The default styles look intentional: styled buttons, links, and headings; blocks use the new shadow/radius
tokens so cards/inputs have depth and rounded corners — all token-only (no hardcoded values).

**Acceptance Scenarios**:
1. **Given** the new tokens, **When** the blocks render, **Then** their styling uses the shadow/radius/spacing
   tokens (token-only — no hardcoded color/size/font).
2. **Given** the theme styles, **When** applied, **Then** buttons/links/headings have intentional, on-brand
   styling driven by tokens.

### User Story 3 - Switchable style variations (Priority: P2)
A user can switch the whole look via a **style variation** (e.g. a bold/editorial variation), in addition to
the existing dark variation — each token-only.

**Acceptance Scenarios**:
1. **Given** the theme, **When** the Site Editor lists variations, **Then** at least one new variation (plus
   dark) is available; each overrides tokens only.

### Edge Cases
- No hardcoded colors/sizes/fonts anywhere (token scans stay clean).
- Logical CSS properties (RTL) preserved.
- All theme.json + variation files are valid JSON.

## Requirements *(mandatory)*
- **FR-001**: `theme/theme.json` MUST expand the **color palette** (add surface-alt, border, ink-soft, and
  state colors success/warning/error/info) while keeping the existing slugs.
- **FR-002**: It MUST provide a fuller **type scale** (add xs, base, xl, 2xl while keeping sm/lg/hero) and a
  complete **spacing scale** (add 10/20/40/60/70 while keeping 30/50/80).
- **FR-003**: It MUST add **shadow presets** (`settings.shadow.presets`) and **radius** tokens (a `--wp--custom`
  radius scale or border presets), all referenceable as CSS variables.
- **FR-004**: The default **styles** MUST style elements (button, link, heading) and apply spacing/typography
  tokens for an intentional baseline.
- **FR-005**: The existing block SCSS MUST adopt the new **shadow/radius** tokens (cards rounded + elevated)
  while staying **token-only** + logical-CSS (RTL).
- **FR-006**: At least one **new style variation** MUST exist alongside dark; each token-only.
- **FR-007**: All token usage MUST keep the token-only scans green; all JSON valid.

### Key Entities
- **Design tokens**: palette / type scale / spacing / shadows / radii in `theme.json`.
- **Style variation**: a `theme/styles/*.json` overriding tokens only.

## Success Criteria *(mandatory)*
- **SC-001**: The palette, type scale, spacing, shadows, and radii are all richer (more tokens) than before, with
  the old tokens preserved.
- **SC-002**: Blocks/cards use shadow + radius tokens (depth + rounded), token-only.
- **SC-003**: At least one new style variation is available; all variations valid JSON, token-only.
- **SC-004**: Token-only scans + theme.json validity tests stay green.

## Assumptions
- Built on spec-006 theme tokens. Visual confirmation of the new look is env-gated (needs a browser); token
  presence, JSON validity, and the token-only discipline are verified headlessly.
