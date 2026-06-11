# Feature Specification: Shared form validation schema & flexible form builder

**Feature Branch**: `020-forms-validation-builder`

**Created**: 2026-06-11

**Status**: Draft (RETROSPECTIVE — documents delivered, tested, real-WP-verified code; items 4 + 5 of the "Finish Corex" initiative; reconciled to the implementation in `plugins/corex-forms`)

**Input**: "The form's validation rules are defined once in PHP and the front-end validates against the exact same definition; and a form field can be any input type with full control over options, label, width, classes, and attributes."

> **Retrospective note.** Written after the code shipped, to restore spec-first compliance (Principle X). It
> builds on the spec-007 forms engine (`Validator`, `SchemaResolver`, `FieldSchema`, secured REST submit) and
> the spec-018 block contract; this feature adds the schema **export + client mirror** and the **full field
> renderer**. Requirements describe the existing `plugins/corex-forms` code.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - One validation schema, front + back (Priority: P1)

A developer defines a form's fields and rules once in PHP. The block embeds that exact schema, the browser
validates against it before submitting, and the server re-validates against the same schema — so there is
never a second, hand-kept copy of the rules to drift.

**Why this priority**: Duplicated client/server validation is the classic source of "passes in the browser,
rejected by the server" bugs. A single source of truth is the whole point.

**Independent Test**: Render a form block; confirm it embeds the serialized schema and per-field error
regions; confirm the client validator (`validation.js`) and the PHP `Validator` apply identical rules to the
same inputs.

**Acceptance Scenarios**:

1. **Given** a resolved PHP schema, **When** the block renders, **Then** it embeds the exported schema as
   `data-corex-schema` (`esc_attr(wp_json_encode(...))`) plus per-field `role="alert"` error regions wired via
   `aria-describedby`.
2. **Given** an invalid value, **When** the user submits, **Then** the client validator shows the field error
   and does not POST; **when** valid, it POSTs to the unchanged secured REST route.
3. **Given** a request that bypasses the client, **When** it reaches the server, **Then** the server
   re-validates against the same schema and is authoritative.
4. **Given** the PHP and JS validators, **When** each runs a rule (required/email/max/min/numeric), **Then**
   they bail per field at the first failing rule, identically.

---

### User Story 2 - A field can be any input type with full control (Priority: P1)

A developer can declare any supported field type — text/email/number/tel/url/password/date/file/textarea/
select/radio/checkbox-group/checkbox/toggle — and control its options, label display, column width, extra CSS
classes, and extra HTML attributes, and get accessible, token-only, RTL markup for free.

**Why this priority**: A form engine that only renders text inputs is not a form builder. Real forms need
choices, layout, and per-field customization without dropping to raw HTML.

**Independent Test**: Resolve a field of each type with presentation options; confirm `FieldRenderer` emits
the correct accessible control (e.g. `<fieldset><legend>` for groups, `name[]` arrays for multi-value), honors
label mode / width / class, and drops unsafe attributes.

**Acceptance Scenarios**:

1. **Given** a field definition, **When** resolved, **Then** `FieldSchema` carries `options`, `labelMode`,
   `width`, `cssClass`, and a whitelisted `attrs` map in addition to name/type/label/rules/required.
2. **Given** a choice field (select/radio/checkbox-group), **When** rendered, **Then** it emits the options;
   radio/checkbox groups render as a `<fieldset>` with a `<legend>` and one input per option, and checkbox
   groups submit as a `name[]` array.
3. **Given** `labelMode` (visible/hidden/inline), a `width` (full/half/third/two-thirds/quarter), and a custom
   class, **When** rendered, **Then** the markup reflects each on a 12-column token grid.
4. **Given** an `attrs` map containing a reserved attribute (e.g. `name`) or an `on*` handler, **When**
   rendered, **Then** those are dropped and only whitelisted attributes are emitted.

### Edge Cases

- A field definition with only `['rules' => [...]]` still resolves — every presentation field has a default.
- An unknown form slug renders nothing (non-fatal), never a PHP error.
- The exported schema is JSON-serializable (round-trips to the client without loss).
- Multi-value (`name[]`) fields are collected back to their canonical key by the client `collect()`.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The resolved PHP schema MUST be the single source of validation truth; a pure `SchemaExporter`
  MUST serialize it (name/type/label/required/rules) into a JSON-able list, performing no encoding itself.
- **FR-002**: The form block MUST embed the exported schema as `data-corex-schema` (json-encoded + attr-escaped
  at the output boundary) and render a per-field `role="alert"` error region wired with `aria-describedby`.
- **FR-003**: A client validator MUST mirror the PHP rules exactly (required/email/max/min/numeric, bail per
  field) and the block's view script MUST client-validate, then POST valid data to the unchanged secured REST
  route, then render server-returned field errors.
- **FR-004**: The server MUST re-validate every submission against the same schema and remain authoritative
  regardless of client behaviour.
- **FR-005**: `FieldSchema` MUST carry presentation knobs — `options`, `labelMode`, `width`, `cssClass`, and a
  whitelisted `attrs` map — each defaulting so a minimal definition still resolves.
- **FR-006**: A dedicated `FieldRenderer` (SRP) MUST render every supported type accessibly and token-only:
  text/email/number/tel/url/password/date/file/textarea/select/radio/checkbox-group/checkbox/toggle, with
  `<fieldset><legend>` for groups and `name[]` arrays for multi-value.
- **FR-007**: `attrs` MUST be whitelisted — reserved attributes and `on*` event handlers MUST be dropped; all
  rendered output MUST be escaped.
- **FR-008**: `FormBlockRenderer` MUST stay thin and delegate per-field markup to `FieldRenderer`.

### Key Entities

- **FieldSchema**: a normalized, immutable field (name/type/label/rules/required + options/labelMode/width/
  cssClass/attrs).
- **Exported schema**: the JSON-able field list the client validator consumes.
- **FieldRenderer**: renders one `FieldSchema` to accessible, escaped, token-only HTML.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For identical inputs, the client validator and the PHP `Validator` produce the same pass/fail per
  field (verified by parallel PHP + Jest suites).
- **SC-002**: A rendered form block embeds the exact serialized schema and an accessible error region per field.
- **SC-003**: Every supported field type renders an accessible, token-only control (no hardcoded color/size/
  font in the markup).
- **SC-004**: An `attrs` map can never introduce a reserved attribute or an event handler into the markup.
- **SC-005**: A submission that skips the client is still validated server-side against the same schema.

## Assumptions

- Built on spec 007 (Validator/SchemaResolver/FieldSchema/secured REST submit) and spec 018 (block build +
  editor registration). This feature adds the exporter + client mirror + the full field renderer.
- The secured REST route, nonce/throttle/sanitize pipeline, and event listeners are unchanged from spec 007.
- _Deferred (documented in PROGRESS item 5): multi-section fieldset grouping; multi-value server sanitize for
  checkbox-group arrays._
