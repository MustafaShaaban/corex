# Feature Specification: Kits that build a real site

**Feature Branch**: `feature/031-kit-content` · **Created**: 2026-06-12 · **Status**: Draft (forward, full Spec Kit)

**Input**: "I have a bad feeling kits aren't working — when I enable a kit, no new pages are created."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Applying a kit creates its pages (Priority: P1)
An admin applies a kit (Company or Portfolio) from the Setup Wizard and the kit **creates its actual pages** —
a composed front page (hero / features / CTA / contact), an About page, a Contact page — and sets the front
page. The site is no longer empty; it looks like the kit it advertised.

**Independent Test**: Apply a kit; the declared pages exist as published posts composing the kit's patterns,
and the front page is set.

**Acceptance Scenarios**:
1. **Given** a kit blueprint declaring pages, **When** the kit is applied, **Then** each page is created as a
   published post whose content composes the kit's `corex/*` patterns/blocks.
2. **Given** a page marked as the front page, **When** the kit is applied, **Then** it is set as the static
   front page.
3. **Given** the kit declares which modules/flags it needs, **When** applied, **Then** pages, modules, and
   flags are all set together.

### User Story 2 - Idempotent and reversible (Priority: P1)
Re-applying a kit does not duplicate its pages, and a soft reset removes the pages the kit seeded (only those).

**Independent Test**: Apply twice → no duplicates. Soft reset → the seeded pages are gone; unrelated content
stays.

**Acceptance Scenarios**:
1. **Given** a kit already applied, **When** applied again, **Then** existing pages are **not** duplicated (the
   planner skips slugs that already exist).
2. **Given** seeded pages, **When** the soft reset runs, **Then** exactly the kit-seeded pages are removed
   (tracked by a marker), and non-kit content is untouched.

### Edge Cases
- A page slug that already exists (kit or user) is skipped, never overwritten.
- Page content uses only `corex/*` patterns/blocks that exist (no invented patterns).
- Seeded pages are recorded (a marker + an id list) so removal is exact.

## Requirements *(mandatory)*
- **FR-001**: `Blueprint` MUST declare the pages a kit creates — `pages(): list<array{title,slug,content,front?}>`
  — composing existing `corex/*` patterns/blocks; default `[]`.
- **FR-002**: Applying a kit MUST create each declared page as a **published** post (idempotent: skip a slug
  that already exists), set the front page where marked, and do so alongside enabling flags + activating modules.
- **FR-003**: A pure planner MUST decide which pages to create from the declared pages + the existing slugs
  (headless-testable); the WP `wp_insert_post`/front-page writes stay at the boundary.
- **FR-004**: Seeded pages MUST be **tracked** (a `corex_kit_page` marker + a recorded id list) so a soft reset
  removes exactly them and nothing else.
- **FR-005**: The Company and Portfolio kits MUST declare real pages (front + about + contact for company; a
  projects landing for portfolio) using their existing patterns.
- **FR-006**: All content/markup safe; no invented patterns; output through WP page creation (sanitized).

### Key Entities
- **Kit page**: `{title, slug, content, front?}` declared by a Blueprint.
- **KitPagePlanner**: pure — `toCreate(declaredPages, existingSlugs)`.
- **Seeded record**: the marker + id list enabling exact reset.

## Success Criteria *(mandatory)*
- **SC-001**: Applying a kit creates its pages; the site shows the kit's front page.
- **SC-002**: Re-applying creates no duplicates.
- **SC-003**: A soft reset removes exactly the kit-seeded pages.
- **SC-004**: The page planner is unit-tested headlessly; the seeded pages compose only existing patterns.

## Assumptions
- Built on spec-010/023 kits + the spec-024 `BlueprintActivator` + spec-025 reset. Pages compose the spec-009
  UI patterns (hero/features/cta/testimonial/contact). Visual confirmation of the rendered pages is env-gated;
  page creation + planning + tracking are verified headlessly + live.
