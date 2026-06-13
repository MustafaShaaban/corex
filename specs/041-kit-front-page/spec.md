# Feature Specification: Kit Apply Must Never Leave a Blank Front Page

**Feature Branch**: `feature/041-kit-front-page`

**Created**: 2026-06-13

**Status**: Draft

**Input**: User description: "Fix the bug where applying a site kit produces an empty home page — a pre-existing blank page with the kit's slug is skipped and the front page is never populated."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Applying a kit always produces a populated front page (Priority: P1)

A site owner applies a site kit (e.g. the Company kit). When the apply finishes, their site's front page shows the kit's designed home layout — the hero, features, and call-to-action — not a blank screen. This holds even when the WordPress install already had a placeholder "Home" page (a default page, or a leftover from a previous reset) occupying the kit's home slug.

**Why this priority**: This is the bug. Today, applying a kit on a site that already has an empty page at the kit's home slug leaves the front page blank — the kit appears to "do nothing." A site kit whose entire purpose is to build a starter site MUST result in a visibly built front page; anything less makes the kit feel broken and is the single biggest reason the framework feels disconnected.

**Independent Test**: Start from an install that has an empty page using the kit's home slug set as the front page. Apply the kit. Confirm the front page now renders the kit's home pattern content and is set as the site's front page.

**Acceptance Scenarios**:

1. **Given** no page exists at the kit's home slug, **When** the kit is applied, **Then** the home page is created with the kit's content and set as the site's front page (unchanged from today).
2. **Given** an empty page already exists at the kit's home slug (zero meaningful content) and may be set as the front page, **When** the kit is applied, **Then** that page is populated with the kit's home content and set as the site's front page.
3. **Given** a page at the kit's home slug already contains real, user-authored content, **When** the kit is applied, **Then** that page is left untouched (no overwrite) and the user is informed it was skipped.
4. **Given** the kit declares additional pages (e.g. About, Contact), **When** the kit is applied, **Then** each is created or populated by the same rule, and the front-page assignment still targets the declared home page.

---

### User Story 2 - Re-applying a kit is safe and never destroys work (Priority: P2)

A site owner who has already applied a kit — and may have since edited the seeded pages — applies it again (or a teammate does). Nothing is duplicated, and none of their edits are lost.

**Why this priority**: Idempotency and safety are existing guarantees (specs 025/031). The classification change in User Story 1 must not weaken them: an "adopt empty page" rule must never become an "overwrite my page" rule on a second run once the page has content.

**Independent Test**: Apply a kit, edit a seeded page to add custom content, apply the kit again, and confirm the custom content survives and no duplicate pages appear.

**Acceptance Scenarios**:

1. **Given** a kit was applied and its home page now has content (kit-seeded or user-edited), **When** the kit is applied again, **Then** no duplicate page is created and the existing page content is preserved.
2. **Given** a kit page that was created by the kit (empty of user edits), **When** the kit is applied again, **Then** the page is not duplicated and remains a single tracked kit page.

---

### User Story 3 - Reset removes only what the kit owns (Priority: P3)

A site owner performs a soft reset (spec 025) after applying a kit. Pages the kit created are removed; a pre-existing page that the kit merely populated is not deleted out from under the owner — it is returned to an empty state instead.

**Why this priority**: The new "adopt an existing page" path introduces a page the kit populated but did not create. Reset must distinguish "created by kit" (safe to delete) from "adopted/populated existing page" (must not delete a page the user owned) so reset stays trustworthy. Depends on the tracking introduced for User Story 1.

**Independent Test**: On an install where the kit created a Contact page and populated a pre-existing Home page, run a soft reset and confirm the Contact page is deleted while the Home page remains (emptied of kit content), and the front-page setting is cleared appropriately.

**Acceptance Scenarios**:

1. **Given** a page the kit created, **When** a soft reset runs, **Then** the page is deleted (unchanged from today).
2. **Given** a pre-existing page the kit populated (adopted), **When** a soft reset runs, **Then** the page is not deleted; its kit-injected content is removed (page returns to empty) and it is no longer tracked as a kit page.

---

### Edge Cases

- **Empty vs. "looks empty"**: a page containing only whitespace, an empty paragraph, or nothing is treated as empty (adoptable); a page with any real blocks/text is treated as user content (skip).
- **A Corex-seeded placeholder** (a page the kit itself created on a prior run but never populated) counts as adoptable, not user content.
- **Home slug occupied by a non-page** (e.g. a post or CPT sharing the slug) — the kit must not adopt it; it creates/uses its page normally and reports the conflict rather than failing.
- **Front page currently set to a page the kit will skip** (real user content at the home slug) — the kit does not forcibly reassign the front page over the user's existing home; it reports that it kept the user's home.
- **Multiple declared pages, one adoptable, one with user content** — each is classified independently; the front-page rule applies only to the declared home page.
- **No home page declared by the kit** — front-page assignment is a no-op; other pages still create/adopt by the same rule.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Applying a kit MUST classify each declared page into exactly one of: **create** (no page at that slug), **adopt** (a page exists at that slug but is empty or is an un-populated kit placeholder), or **skip** (a page exists at that slug with real, user-authored content).
- **FR-002**: For a **create** page, the system MUST create the page with the kit's content (unchanged from current behavior).
- **FR-003**: For an **adopt** page, the system MUST populate the existing page with the kit's content without changing its identity (same page ID/slug), and MUST track that the page was adopted (as distinct from created).
- **FR-004**: For a **skip** page, the system MUST make no change to the page's content and MUST record that it was skipped so the outcome can be reported.
- **FR-005**: After apply, the site's front page MUST be set to the kit's declared home page whenever that page was **created** or **adopted**; when the declared home page was **skipped** (user content present), the system MUST NOT override the user's existing front-page choice.
- **FR-006**: Applying a kit MUST be idempotent: a second apply MUST NOT duplicate pages and MUST NOT overwrite content that exists at apply time (whether kit-seeded or user-edited).
- **FR-007**: The classification decision MUST be pure and independently testable — it MUST be expressible from the declared pages plus a per-slug "exists / is-empty / is-kit-placeholder" signal, without performing data access itself.
- **FR-008**: A soft reset MUST delete pages the kit **created**, and MUST NOT delete pages the kit **adopted**; for an adopted page, reset MUST remove the kit-injected content (return the page to empty) and stop tracking it as a kit page. The front-page setting MUST be cleared only if it points at a page being deleted.
- **FR-009**: After apply, the system MUST surface a summary of what happened per page (created / populated / skipped) so the outcome is visible rather than silent.
- **FR-010**: The change MUST apply to every kit using the shared kit-apply path (the Company kit's home/about/contact and any future or existing kit, including Portfolio), with no change to the kit patterns themselves, the Addon Manager, or the block engine, and MUST introduce no new runtime or build dependency.

### Key Entities *(include if feature involves data)*

- **Declared kit page**: the kit's manifest entry for a page — its title, slug, composed pattern content, and whether it is the front page.
- **Existing-page signal**: per declared slug, whether a page already exists, whether it is empty, and whether it is an un-populated kit placeholder — the input the pure classifier needs.
- **Page disposition**: the classification result per declared page (create / adopt / skip) plus, after apply, the resulting page identity and whether it became the front page — the basis for tracking, reset, and the apply summary.
- **Kit page tracking record**: the record of which pages the kit created vs. adopted, used so reset removes only what is safe to remove (extends the existing `corex_kit_seeded_pages` tracking from spec 031).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After applying a kit on an install whose home slug is an empty page, 100% of the time the front page renders the kit's home pattern content (zero blank front pages).
- **SC-002**: Re-applying a kit any number of times never creates a duplicate page and never changes content that existed before that apply (0 duplicates, 0 overwrites of existing content).
- **SC-003**: A page containing real user content at a kit slug is preserved unchanged in 100% of applies and is reported as skipped.
- **SC-004**: After a soft reset, every kit-created page is removed and every kit-adopted (pre-existing) page is retained, across all tested scenarios (0 wrongful deletions, 0 leftover kit content).
- **SC-005**: The classification logic is covered by headless tests that pass without a running WordPress, including the create / adopt-empty / adopt-placeholder / skip-user-content cases.
- **SC-006**: No new runtime or build dependency is introduced and no kit pattern file is modified.

## Assumptions

- "Empty / adoptable" means a page whose content has no meaningful blocks or text — an empty string, whitespace, or a single empty paragraph. Any real block or text marks it as user content and therefore skip. The precise emptiness test is an implementation detail decided in planning, consistent with this definition.
- A kit "placeholder" is a page previously created by a kit run but never populated; it is tracked via the existing kit-page meta/option so it can be recognized as adoptable rather than user content.
- Reset behavior builds on the existing soft reset (spec 025) and kit-page tracking (spec 031); this feature extends that tracking to distinguish created vs adopted, it does not introduce a new reset mode.
- The apply summary reuses the existing admin-notice surface from the setup-wizard apply flow; no new screen is added (the richer "what changed" dashboard is deferred to spec 042).
- The shared kit-apply path (`Corex\Kit` planner + activator) is the single place this logic lives; kits that declare pages inherit the fix without per-kit changes.
