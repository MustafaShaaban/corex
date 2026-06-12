# Feature Specification: Modern settings UX

**Feature Branch**: `feature/032-settings-ux` · **Created**: 2026-06-12 · **Status**: Draft (forward, full Spec Kit)

**Input**: "In Corex settings, why can't I upload images through the option page — why do I need to add the URL? Make it more modern. And I can't find the branding."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Upload an image from the settings page (Priority: P1)
An admin sets the Corex logo by **opening the media library and picking/uploading an image** — with a preview —
instead of pasting a URL.

**Acceptance Scenarios**:
1. **Given** an image setting, **When** the screen renders, **Then** it shows a media control (preview + Select
   image + Remove), not a bare URL text box.
2. **Given** a chosen image, **When** saved, **Then** the setting stores its URL (read by the branding service).
3. **Given** no JavaScript, **When** the page loads, **Then** the underlying value field is still present.

### User Story 2 - Richer field types (Priority: P2)
Settings use the right control per field: a **select** for fixed choices (captcha driver), a **toggle** for
booleans — not free text.

**Acceptance Scenarios**:
1. **Given** a choice field, **When** rendered, **Then** it is a select of its options.
2. **Given** a boolean field, **When** rendered, **Then** it is a checkbox storing on/empty.

### User Story 3 - Find the branding (Priority: P2)
The Corex settings screen shows the configured logo in its header, so the branding is visible and findable.

**Acceptance Scenarios**:
1. **Given** a configured logo, **When** the settings screen loads, **Then** the logo appears in the header.

### Edge Cases
- Every value escaped; media stores a URL (esc_url); a select value is one of its options.
- The media control degrades to a value field with no JS; saving still works.
- The registry drives everything; no invented keys.

## Requirements *(mandatory)*
- **FR-001**: SettingsForm MUST render per field type: text/email/url/password (input), media (preview +
  Select/Remove + a value field), select (options), checkbox (toggle storing on/empty). Values escaped per type.
- **FR-002**: The logo setting MUST be a media field; the captcha driver a select; booleans a checkbox.
- **FR-003**: A small script MUST wire the WordPress media frame to media fields, enqueued only on the settings
  screen; the field degrades to an editable value without it.
- **FR-004**: A saved media value MUST be the image URL (read by BrandingService); selects persist an option
  value; checkboxes on/empty.
- **FR-005**: The settings screen MUST display the configured logo in its header.
- **FR-006**: The form rendering MUST be unit-tested headlessly (each field type's markup); output escaped/i18n.

### Key Entities
- Field definition: label, type, options (for select), in the SettingsRegistry.
- Media field: preview + value + Select/Remove, wired to the media frame.

## Success Criteria *(mandatory)*
- **SC-001**: The logo is set by picking from the media library (no URL typing).
- **SC-002**: Choice/boolean settings use select/toggle.
- **SC-003**: The settings header shows the configured logo.
- **SC-004**: Each field type's rendering is unit-tested; output escaped.
- **SC-005**: The media field still saves with JS disabled.

## Assumptions
- Built on spec-016 branding + spec-017 settings. The WordPress media frame is standard. Visual confirmation of
  the picker is env-gated; rendering + persistence + the registry are verified headlessly + live.
