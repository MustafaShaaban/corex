# Feature Specification: Admin Dashboard / Settings (MVP)

**Feature Branch**: `017-admin-dashboard`

**Created**: 2026-06-10

**Status**: Draft

**Input**: A top-level "Corex" admin area with a settings screen surfacing the brand, mail, forms, and
captcha config, persisted to the same options the Config engine reads. Lives in `corex-config`. The
settings schema + form + save logic are unit/integration-tested. **A React/DataViews UI is the deferred
enhancement — it needs a Node build + a browser to author and verify, which this environment lacks.**

## User Scenarios & Testing *(mandatory)*

### User Story 1 - One place to configure Corex (Priority: P1) 🎯 MVP

An administrator opens a **Corex** admin menu and edits the brand, mail, forms, and captcha settings; saving
persists them, and the framework picks them up (the values back the Config engine's option layer).

**Independent Test**: The settings registry enumerates the sections/fields; the form renders them with
current values (escaped) + a nonce; the save logic sanitizes and persists each declared field; a saved value
is then readable through the Config option layer (integration).

**Acceptance Scenarios**:

1. **Given** the settings registry, **When** the form renders, **Then** each section's fields appear with
   their current values and a nonce field.
2. **Given** a save with a valid nonce + capability, **When** submitted, **Then** each declared field is
   sanitized and persisted; an invalid nonce/capability persists nothing.
3. **Given** a saved `brand.footer_text`, **When** read via the Config engine, **Then** it returns the saved value.

### Edge Cases

- A POST without a valid nonce or `manage_options` → nothing saved.
- An undeclared field in the POST → ignored (only registry fields are saved).

## Requirements *(mandatory)*

- **FR-001**: The system MUST register a top-level "Corex" admin menu with a settings screen (capability
  `manage_options`).
- **FR-002**: A settings registry MUST declare the configurable sections + fields (brand, mail, forms, captcha).
- **FR-003**: The settings form MUST render each field with its current value (escaped) and a nonce; saving
  MUST verify the nonce + capability, sanitize each declared field, and persist it.
- **FR-004**: Saved settings MUST be stored as the prefixed options the Config engine's option layer reads,
  so the framework consumes them with no extra wiring.
- **FR-005**: The dashboard lives in `corex-config` and MUST NOT alter client-site styling.

## Success Criteria *(mandatory)*

- **SC-001**: The settings registry enumerates the sections/fields.
- **SC-002**: The form renders fields + values + a nonce (headless); save persists only declared fields with
  a valid nonce/capability.
- **SC-003**: A saved value is read back through the Config engine (integration).
- **SC-004**: The schema + form + save logic are unit/integration-tested. **The React/DataViews UI is
  deferred (needs a build/browser env).**

## Assumptions

- **Packaging.** `corex-config` (`Corex\Config\Settings`). Settings persist to `corex_<key>` options (the
  same the Config OptionsSource reads), so there is no separate settings store to reconcile.
- **MVP scope.** A server-rendered settings screen (Settings API-style) covering brand/mail/forms/captcha,
  + a simple status surface. **Deferred (needs a Node build + browser):** the React/DataViews/DataForm UI,
  DataViews tables for submissions/subscribers/applications, the setup wizard, and a health-check runner.
