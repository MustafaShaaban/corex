# Feature Specification: Easy option pages (039)

**Created**: 2026-06-12 · **Status**: Draft · **Input**: "I need to make it easy to be able to create an option
page." Give developers a one-declaration way to add a custom admin **settings/option page** — a title, a menu
location, and a list of fields — that renders a secured, token-styled form and persists to options, reusing the
existing settings controls. Plus a generator to scaffold one.

## User Scenarios & Testing

### US1 — Declare a page, get a working settings screen (P1) 🎯 MVP
As a developer, I declare an `OptionPage` (title, menu label, capability, parent, and a list of fields) and
register it; an admin page appears with those fields rendered as the right controls, and saving persists the
values — I write no form HTML, no nonce handling, no save loop.

**Acceptance**: a registered `OptionPage` adds an admin menu/submenu; its render shows each field with the
spec-032 control for its type; submitting verifies the capability **and** a nonce, sanitizes each field, and
saves it; reloading shows the saved values.

### US2 — Scaffold one from the CLI (P1)
As a developer, I run `wp corex make:option-page <Name>` and get a ready `OptionPage` definition in my app to
register and extend.

**Acceptance**: the generator writes an `OptionPage` definition class (with example fields) to the app's
configured namespace/path, like the other `make:*` generators; it is WP-CLI-gated and the engine is headless.

### US3 — The same controls + safety as Settings (P2)
As a site owner, the custom page's fields behave exactly like Corex → Settings — text/email/url/password inputs, a
**media picker**, selects, checkboxes — and every value is escaped on output and sanitized on save.

**Acceptance**: each field type renders the same control; output is escaped per type; save is capability- +
nonce-gated and sanitized; secrets (`password` fields) are write-only and never re-rendered with their value.

## Requirements

- **FR-001**: An `OptionPage` value — `slug`, `title`, `menuLabel`, `capability`, `parent` (a parent menu slug, or
  empty for a top-level page under Corex), and `fields` (a list of `{key, label, type, options?}`). Pure.
- **FR-002**: The page's fields render through the **existing** spec-032 field controls (no duplicate rendering):
  `SettingsForm` is generalised to a `FieldSections` seam that both `SettingsRegistry` and `OptionPage` satisfy.
- **FR-003**: An `OptionPageRegistry` (register/all/find) and an `OptionPageScreen` that, per registered page, adds
  the admin menu, renders the form (shared `AdminGuard`), and **saves** on POST — verifying the page's capability
  **and** a per-page nonce, sanitising each field, and persisting via the settings store (Principle VII).
- **FR-004**: Field values persist to options under the field's dot-key (via the existing `SettingsStore`), so a
  page's values are readable through `Config` like any other setting; `password` fields are write-only in the UI.
- **FR-005**: A `wp corex make:option-page <Name>` generator scaffolds an `OptionPage` definition; the engine is
  pure/headless and the WP-CLI command is the thin gated layer (Principle IX; spec-003 precedent).
- **FR-006**: The pure pieces (`OptionPage`, `OptionPageRegistry`, the `FieldSections` adapter, the generator
  output) are headless **Pest**-tested; the screen + the WP-CLI command are thin boundaries.

## Success Criteria

- **SC-001**: A developer adds a working custom option page with one `OptionPage` declaration + a registration —
  no form/nonce/save code.
- **SC-002**: `wp corex make:option-page Billing` scaffolds a definition the developer can register immediately.
- **SC-003**: The page's controls, escaping, and cap+nonce-gated save match Corex → Settings exactly.
- **SC-004**: The new pure pieces have passing Pest tests; the full suite stays green.

## Assumptions

- Reuse over reinvention: the page uses the spec-032 `SettingsForm` controls and the `SettingsStore` persistence;
  this spec adds the page abstraction + registry + screen + generator, not a new form engine.
- A page is a flat list of fields (one section). Multi-section pages can be added later without breaking this.
- Field keys are developer-chosen dot-keys (e.g. `billing.tax_id`), readable via `Config`; the developer
  namespaces them to avoid collisions.

## Dependencies

Spec 001 (Config), spec 003 (the `make:*` generator engine + WP-CLI gate), spec 017/030 (`AdminGuard` + admin
screen precedent), spec 032 (the per-field-type `SettingsForm` controls + `SettingsStore`).
