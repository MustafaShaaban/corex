# Research: Modern settings UX (032)

## R1 — A field-type rendering system
**Decision**: `SettingsForm::field()` switches on a declared `type` to render the right control (input / media /
select / checkbox). **Rationale**: one registry-driven form serves every control without per-field code; the
registry is already the source of truth. **Alternatives**: a control class per field (rejected — heavier than a
switch for a small fixed set).

## R2 — Media picker via the WordPress media frame, degrading gracefully
**Decision**: A `media` field renders a `url` value input + a preview `<img>` + Select/Remove buttons; a small
script opens `wp.media` and writes the chosen URL into the value input. With no JS, the value input remains
editable, so saving still works. **Rationale**: `wp.media` is the standard, accessible WP picker; storing the
URL keeps `BrandingService` unchanged (it reads `brand.logo_url`). **Alternatives**: store the attachment ID
(rejected — would change what BrandingService reads); a custom uploader (rejected — reinvents wp.media).

## R3 — Branding visibility
**Decision**: Show the configured logo in the Corex settings screen header (in addition to login + footer).
**Rationale**: the user couldn't find the branding; the settings screen is where they configure it, so showing
it there makes it visible and confirms the setting took effect. **Alternatives**: admin-bar logo (rejected —
more global surface; out of scope here).
