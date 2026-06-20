# Feature Specification: Company Site Kit v1 — Structure and Page Coverage

**Feature Branch**: `spec/059-company-site-kit`

**Created**: 2026-06-20

**Status**: Draft

**Input**: Milestone M4 (ROADMAP §7). Design input: [M4 company site kit handoff](../../design/handoffs/company-kit.md) (approved 2026-06-20). Reuses merged M2 brand tokens (Spec 057) and the M3 navigation/footer system (Spec 058).

## Overview

CoreX needs a neutral, brand-aware **Company Site Kit v1** so the first real company websites can be launched without
a page builder. Today CoreX has framework foundations, M2 tokens, and the M3 header/footer system, but no complete,
applyable company-page set. This feature delivers the page coverage (templates / patterns) and a safe **preview →
apply** kit flow with demo content levels and conflict handling, all composed from M2 tokens, the M3 nav/footer, and
existing CoreX/core blocks. It does not build the Portfolio or WooCommerce kits, a page builder, or M5's broad block
library.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Core company pages, applied safely (Priority: P1)

A site owner runs the company kit and gets the essential pages of a company website — Home, About, Services,
Contact, plus the required system pages (404, Search Results, No Results, Privacy, Terms) — composed from CoreX/core
blocks with the M3 header/footer and M2 brand. Before anything is created the kit shows a **summary** of what it will
do; the owner confirms, and nothing pre-existing is silently overwritten.

**Why this priority**: A small, safe, brand-consistent set of core pages plus a trustworthy apply flow is the minimum
that lets a real site start. Everything else (the full page set, demo levels) extends this.

**Independent Test**: Run the kit on a clean site at the `minimal` level; confirm the summary lists the pages,
confirm apply creates them with the M3 header/footer and token-only markup, and confirm a re-run does not duplicate
or clobber existing pages.

**Acceptance Scenarios**:

1. **Given** a site with the CoreX theme + plugins, **When** the owner previews the company kit, **Then** a summary
   lists every page/menu it will create or change before any mutation occurs.
2. **Given** the previewed summary, **When** the owner applies the kit, **Then** the core pages (Home, About,
   Services, Contact + 404/Search/No Results/Privacy/Terms) are created using the M3 header/footer, M2 tokens, and
   existing blocks, with no raw color/size/font literals.
3. **Given** a page slug that already exists, **When** the kit applies, **Then** it follows the chosen conflict
   behavior (skip/adopt) and never silently overwrites existing content.
4. **Given** an RTL locale, **When** the pages render, **Then** layout, navigation, and footer mirror correctly.

### User Story 2 - Full company page coverage (Priority: P2)

The kit additionally provides the full v1 page set — Single Service, Case Studies/Work, Single Case Study,
Industries, FAQ, Blog/News, Single Post, Team, Testimonials, Locations/Branches, Cookie Policy, Maintenance — each
accessible, responsive, RTL-correct, and token-only.

**Why this priority**: Complete coverage makes the kit production-ready for varied company sites, but the core set
(US1) already lets a site start.

**Independent Test**: Apply the kit at `standard`; confirm every page in the v1 set renders with correct
landmarks/headings, reuses the M3 parts, and passes the no-raw-literal and RTL checks.

**Acceptance Scenarios**:

1. **Given** the kit applied, **When** each v1 page renders, **Then** it has correct landmarks/heading structure and
   reuses the M3 header/footer.
2. **Given** any v1 page, **When** scanned, **Then** it contains no raw hex/size/font literals and uses logical CSS.

### User Story 3 - Demo levels, brand-aware setup, and SEO starter (Priority: P3)

The owner chooses a demo content level (`minimal`, `standard`, `full`), supplies brand-aware setup fields (site name,
tagline, logo, primary contact, brand color/typography), and gets editable SEO starter metadata. Re-running offers
`reset`/`adopt`/`skip`/`conflict` handling.

**Why this priority**: These make the kit polished and re-runnable, but a single safe apply at one level (US1)
already delivers value.

**Independent Test**: Apply at each level and confirm the content volume differs while structure is identical; set
brand fields and confirm they map to M2 tokens/`brand.json` (not hardcoded); confirm SEO metadata is present and
editable; re-run and confirm reset/adopt/skip/conflict behave as summarized.

**Acceptance Scenarios**:

1. **Given** a demo level, **When** the kit applies, **Then** the same page structure is created with content volume
   matching the level.
2. **Given** brand setup fields, **When** applied, **Then** they personalize the site via M2 tokens/`brand.json`
   without embedding a client brand into Corex.
3. **Given** a re-run, **When** the owner chooses reset/adopt/skip, **Then** the kit applies that behavior as shown in
   the summary.
4. **Given** applied pages, **When** inspected, **Then** each has editable SEO starter metadata compatible with
   common SEO plugins (no hard dependency).

### Edge Cases

- Re-running on a site that already has some kit pages (partial state).
- An existing front page / menu the owner wants to keep (adopt) vs replace (reset).
- Missing brand inputs (fall back to neutral M2 defaults).
- Very long content, mixed Arabic/Latin, and 200%/320px viewports without overflow.
- Applying with optional plugins absent (no ACF/Woo/Polylang dependency).
- No-JS: all pages remain usable.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The kit MUST present an explicit preview summary of every page/menu it will create or change before any
  mutation.
- **FR-002**: The kit MUST create the core company pages (Home, About, Services, Contact) and the required system
  pages (404, Search Results, No Results, Privacy, Terms) using the M3 header/footer, M2 tokens, and existing blocks.
- **FR-003**: The kit MUST provide the full v1 page set (see handoff scope) as templates and/or page patterns.
- **FR-004**: Every kit page MUST be token-only (no raw hex/size/font), RTL-correct (logical properties), responsive
  (no horizontal scroll at 320px, usable at 200% zoom), and WCAG 2.2 AA (landmarks, headings, visible focus).
- **FR-005**: The kit MUST support demo content levels `minimal`, `standard`, and `full` with identical structure and
  increasing example content.
- **FR-006**: The kit MUST handle existing content safely with defined `reset`, `adopt`, `skip`, and `conflict`
  behavior, never silently overwriting.
- **FR-007**: Brand-aware setup fields MUST personalize the site through M2 tokens / `brand.json`, never by hardcoding
  a client brand into Corex.
- **FR-008**: The kit MUST write editable SEO starter metadata that remains compatible with common SEO plugins and
  requires none.
- **FR-009**: The kit MUST reuse the M3 navigation/footer and existing CoreX/core blocks and patterns; it MUST NOT
  introduce a page builder, a parallel token registry, or a new broad block library (M5 selects only proven gaps).
- **FR-010**: The kit MUST run through the existing CoreX kit/provisioning foundations and MUST NOT require editing
  framework internals to function.
- **FR-011**: The kit MUST NOT hard-depend on any optional plugin (ACF/Woo/Polylang/WPML) and MUST run fully without
  them.
- **FR-012**: The kit MUST be limited to the Company kit; Portfolio (M8) and WooCommerce (M9) kits, Pro features, and
  admin/page builders are out of scope.

### Key Entities

- **Kit**: the company-kit definition — its page set, demo levels, and apply behavior.
- **Kit page**: one page/template/pattern in the set (structure + leveled demo content + SEO metadata).
- **Apply plan**: the previewed summary of creates/changes/skips, including conflict decisions.
- **Brand setup**: the owner-supplied fields mapped onto M2 tokens / `brand.json`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site owner can preview and apply a brand-consistent set of core company pages from CoreX without a
  builder and without editing framework internals.
- **SC-002**: Applying the kit never silently overwrites existing content — 100% of conflicts follow the summarized
  reset/adopt/skip behavior.
- **SC-003**: Every v1 page passes automated checks for no raw literals, landmarks/headings, and (where the browser
  runtime is available) WCAG 2.2 AA contrast in LTR and RTL.
- **SC-004**: The three demo levels produce identical structure with differing content volume.
- **SC-005**: At 200% zoom and 320px width, no kit page produces horizontal scrolling or clipped content.
- **SC-006**: The kit applies fully with no optional plugins installed.
- **SC-007**: The generated site can be the basis of a real company website via `wp corex make:site` without
  modifying framework code.

## Assumptions

- The M2 tokens (Spec 057) and M3 nav/footer (Spec 058) are merged/available; this kit composes them and adds no new
  brand values.
- The kit applies through the existing CoreX kit/provisioning/`make:site` foundations; this spec extends content
  coverage and apply safety, not a new provisioning engine.
- Pages ship as a mix of FSE templates, reusable patterns, and pages created at apply time (planning decides per
  page).
- SEO starter metadata is plugin-compatible defaults, not a full SEO engine.
- Net-new section blocks are deferred to a minimal, M4-proven M5 batch; M4 reuses existing blocks/patterns first.
- Browser-rendered and wp-env evidence may be ENVIRONMENT-GATED in this workspace and recorded as such, never PASS.
