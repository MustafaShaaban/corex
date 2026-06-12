# Feature Specification: Interactive, inline-editable blocks

**Feature Branch**: `feature/029-interactive-blocks`

**Created**: 2026-06-12

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: "I want to edit a block's text **inside** the FSE canvas like a modern page builder — not only from the right pane. And where a block input takes data (like a form), it should offer the **list** of that data, not make me type 'contact' by hand."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Edit block text inline in the canvas (Priority: P1)

A site builder inserts a Corex component block (stat, testimonial, pricing, accordion) and **types directly into
it on the canvas** — the heading, the quote, the price — seeing the result as they type, exactly like a modern
page builder, instead of editing every field in the right sidebar.

**Why this priority**: This is the single biggest editor-UX gap. Today every Corex block is edited only via
`InspectorControls` (right pane); none supports inline canvas editing.

**Independent Test**: Insert each block; confirm its text fields are `RichText` regions edited in place on the
canvas; confirm the saved attributes carry that text and the front-end server render reflects it.

**Acceptance Scenarios**:

1. **Given** a component block, **When** inserted, **Then** its text fields are editable **inline on the
   canvas** (RichText), not only in the sidebar.
2. **Given** inline edits, **When** the post is saved, **Then** the text is stored in the block's attributes and
   the block stays **dynamic** (server-rendered from those attributes — Principle VI preserved).
3. **Given** rich text (bold/italic/links) in an editable field, **When** rendered on the front end, **Then**
   the server output preserves the safe HTML (`wp_kses_post`) and strips anything unsafe.
4. **Given** an empty editable field, **When** rendered, **Then** the block degrades gracefully (no broken
   markup, no notice).

---

### User Story 2 - Pick data from a list, don't type identifiers (Priority: P1)

When a block needs to reference existing data — the **form** to display — the builder picks it from a
**dropdown of the forms that exist**, instead of typing a slug like `contact`.

**Why this priority**: Typing internal identifiers is error-prone and unfriendly; a builder shouldn't need to
know slugs.

**Independent Test**: Insert the form block; confirm the form control is a select populated from the actual
registered forms; selecting one renders that form.

**Acceptance Scenarios**:

1. **Given** the form block, **When** its settings open, **Then** the form is chosen from a **select list of
   registered forms** (label + slug), not a free-text field.
2. **Given** no forms registered, **When** the control renders, **Then** it shows a clear empty state (e.g.
   "No forms found"), never a broken control.
3. **Given** a selected form, **When** the block renders, **Then** it displays that form (the existing secured
   render path is unchanged).
4. **Given** the list of forms, **When** requested, **Then** it comes from a **cap-gated** source (only users
   who can edit posts can enumerate forms in the editor).

### Edge Cases

- A block with **both** inline text and a data selector (none today, but the pattern must allow it) edits text
  inline and data via the sidebar.
- Rich text is stored as HTML in an attribute; the renderer MUST escape it with `wp_kses_post` (not
  `esc_html`), and plain fields keep `esc_html`.
- Backwards data: blocks already placed with sidebar-only attributes keep rendering (attribute names are
  preserved where possible; a migration note is documented if any change).
- The form list endpoint returns only what the user is allowed to see; it never exposes submissions or secrets.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Corex component blocks (stat, testimonial, pricing, accordion) MUST support **inline canvas
  editing** of their text via `RichText`, writing to block attributes (not only `InspectorControls`).
- **FR-002**: Those blocks MUST remain **dynamic** — `save: () => null`; the PHP renderer reads the attributes
  and produces the markup (Principle VI), so editor and front end share one source of truth.
- **FR-003**: Rich-text attributes MUST be rendered with `wp_kses_post` (safe HTML preserved, unsafe stripped);
  non-rich fields keep `esc_html`/`esc_url`/`esc_attr`.
- **FR-004**: Each block MUST degrade gracefully on empty fields (sane defaults, no notices/fatals) and keep its
  token-only, RTL-correct styling.
- **FR-005**: The form block MUST choose its form from a **select list of registered forms** (label + slug),
  replacing the free-text `formSlug`; an empty registry shows a clear empty state.
- **FR-006**: The form list MUST come from a **cap-gated** data source (a read-only, `edit_posts`-gated REST
  route or editor data store) exposing only `slug` + `label` — never submissions or secrets.
- **FR-007**: All new editor JS MUST build through the existing `@wordpress/scripts` pipeline and be **unit
  tested with Jest** (registration, RichText wiring, the form selector, `save: () => null`).
- **FR-008**: Any attribute rename MUST preserve already-placed blocks (keep the attribute name, or provide a
  deprecation) and be documented.

### Key Entities

- **Inline-editable block**: a dynamic block whose `edit` renders `RichText` for text, stored in attributes,
  rendered server-side.
- **Form option**: a `{ slug, label }` pair the form selector lists.
- **Form list source**: the cap-gated read-only provider of form options for the editor.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: All four component blocks are edited **inline on the canvas**; sidebar editing is no longer the
  only way to change their text.
- **SC-002**: Rich text entered inline (incl. bold/italic/link) renders correctly and safely on the front end.
- **SC-003**: The form block is configured by **selecting** a form from a list; zero free-text slug entry.
- **SC-004**: Every new editor component is covered by a Jest test; `npm run build` compiles all blocks clean.
- **SC-005**: Already-placed blocks keep rendering after the upgrade (no broken content).

## Assumptions

- Built on the spec-018 build pipeline (editor registration + compiled assets) and the spec-009/027 block
  library. The render-from-attributes hybrid keeps blocks dynamic while enabling inline editing.
- Browser/visual confirmation of the editing experience is environment-gated (needs a browser); registration,
  the render path, the form source, and the Jest suite are verified headlessly.
- This spec establishes the **inline-editable block architecture**; the block-library expansion (spec 035)
  builds new blocks on it.
