# Feature Specification: Forms Engine

**Feature Branch**: `007-forms-engine`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "Forms engine — the next module after theme/tokens. Forms are an application of the existing architecture (security middleware, data layer, a new event-dispatch seam): a code-defined Form schema, a headless validator, a secured submit lifecycle that dispatches a FormSubmittedEvent to listeners, a thin EventDispatcher seam, and an FSE form block. Out of scope: admin submissions viewer, CRM/webhook listeners, multi-step/conditional logic, file uploads, full email templating (Corex Mail)."

## Clarifications

### Session 2026-06-08

- Q: When a field's value fails more than one of its rules, does the validator report the first failing rule or all of them? → A: The first failing rule per field ("bail per field") — at most one error per field, evaluated in rule order.
- Q: How does the store listener persist a submission? → A: As a Corex-owned custom post type (`corex_submission`) via the existing data layer (spec 002), keyed/queryable by form slug — no custom table.
- Q: If a listener throws during dispatch, what happens to the other listeners and the submission result? → A: The failure is isolated and logged; remaining listeners still run, and the submission is still accepted (dispatch is best-effort, not transactional).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Validate submitted values against a form's rules (Priority: P1)

A developer defines a form's fields and validation rules once. When values arrive, the framework runs each field's rules and returns a structured set of per-field errors (or none), with translation-ready messages — without any WordPress runtime present.

**Why this priority**: The validator is the headless heart of the module and the single gate that protects every downstream side effect. It is independently valuable (any caller can validate a payload) and is the MVP: with only the validator, a form's correctness is provable.

**Independent Test**: Headless unit tests run a rule set (`required`, `email`, `max:N`, `min:N`, `numeric`) against valid and invalid payloads and assert the exact per-field error structure and message keys — no WordPress, no HTTP.

**Acceptance Scenarios**:

1. **Given** a field with `['required','email']` and an empty value, **When** validated, **Then** the result is invalid with a `required` error for that field.
2. **Given** a field with `['required','email']` and `"not-an-email"`, **When** validated, **Then** the result is invalid with an `email` error.
3. **Given** a field with `['max:120']` and a 121-character value, **When** validated, **Then** the result is invalid with a `max` error naming the limit.
4. **Given** all fields satisfying their rules, **When** validated, **Then** the result is valid with zero errors and exposes the sanitized/normalized values.
5. **Given** a payload missing an optional field, **When** validated, **Then** the result is valid (absence is only an error under `required`).

---

### User Story 2 - Dispatch a submission to its listeners in order (Priority: P1)

When a form is submitted successfully, the framework dispatches a single event object to every registered listener, in registration order, so side effects (email, store) run as independent, composable units. Adding a side effect means adding a listener — never editing the submit path.

**Why this priority**: The event seam is foundational and shared (Corex Mail and other add-ons will consume it). It is the architectural seam that keeps the submit path thin. Independently valuable and headless-testable on its own.

**Independent Test**: Headless unit tests register multiple listeners for an event type, dispatch an event, and assert each listener received the same event instance exactly once, in registration order; listeners for other event types are not invoked.

**Acceptance Scenarios**:

1. **Given** two listeners registered for an event type, **When** that event is dispatched, **Then** both run once, in registration order, each receiving the event.
2. **Given** a listener registered for event type A, **When** an event of type B is dispatched, **Then** that listener is not invoked.
3. **Given** no listeners for an event type, **When** it is dispatched, **Then** dispatch completes without error.
4. **Given** an event carrying the submission data, **When** dispatched, **Then** each listener can read the form identifier and the validated values from the event.

---

### User Story 3 - Submit a form through the secured lifecycle (Priority: P1)

A visitor fills in a registered form (e.g. contact) and submits it. The request passes the security gate (nonce, sanitize, rate-limit, honeypot) and the validator; on success the framework dispatches the submission event (triggering the email and store listeners) and returns a structured success response; on failure it returns the per-field errors without running any side effect.

**Why this priority**: This is the end-to-end product behavior — the lifecycle that ties the validator, the event seam, the existing security middleware, and the data layer together. It proves the module works in a real WordPress request.

**Independent Test**: An integration test posts to the submit endpoint for the example contact form: a valid, correctly-nonced request returns success and the registered listeners observe the event; a request with an invalid nonce, a tripped honeypot, or invalid fields is rejected with the appropriate status and no side effect runs.

**Acceptance Scenarios**:

1. **Given** the contact form and a valid, nonced submission, **When** posted, **Then** the response is success, the validated values are returned, and each registered listener (email, store) is invoked once.
2. **Given** a submission with a missing/invalid nonce, **When** posted, **Then** it is rejected (fail-closed) and no listener runs.
3. **Given** a submission failing validation, **When** posted, **Then** the response carries the per-field errors, the status indicates a validation failure, and no listener runs.
4. **Given** a submission with the honeypot field filled (bot), **When** posted, **Then** it is silently rejected and no listener runs.
5. **Given** more submissions from one client than the configured rate limit allows, **When** the limit is exceeded, **Then** further submissions are throttled.
6. **Given** a successful submission, **When** the store listener runs, **Then** the submission is persisted (retrievable by the form identifier) and the email listener sends to the configured recipient.

---

### User Story 4 - Place a form on a page as a block (Priority: P2)

An editor adds a Corex Form block to a page and selects a registered form. The front end renders that form's fields from its schema — accessible (WCAG 2.2 AA), RTL-aware, internationalized, styled only with theme tokens — and the form's client script loads only on pages that contain the block.

**Why this priority**: The block is how forms reach real pages, but it depends on the schema + lifecycle (US1–US3) being in place. It is the visible surface; the engine is valuable to API/headless consumers without it.

**Independent Test**: Render the block for the registered contact form and assert the output contains each schema field with correct labels/`for`/`id` associations, required markers, the nonce and honeypot fields, and references only `var(--wp--preset--*)` tokens; confirm the form script is enqueued only when the block is present.

**Acceptance Scenarios**:

1. **Given** a page with the Corex Form block bound to the contact form, **When** rendered, **Then** every schema field appears with an associated `<label>` and the required fields are marked accessibly.
2. **Given** the block is on a page, **When** the page loads, **Then** the nonce and honeypot fields are present and the form script is enqueued; on a page without the block, the script is not enqueued.
3. **Given** an RTL locale, **When** the block renders, **Then** layout uses logical properties and reads correctly right-to-left, with all visible strings translation-ready.

---

### Edge Cases

- **Unknown form identifier** at submit or block render → a clear, non-fatal error (rejected submission / editor notice), never a crash.
- **Unknown validation rule** in a schema → fails closed at registration/resolution (surfaced to the developer), never a silent pass.
- **A listener throws** during dispatch → the failure is isolated and logged; the remaining listeners still run and the submission is still accepted (dispatch is best-effort, not transactional; delivery guarantees beyond "attempted" are out of scope here).
- **Duplicate field names** in a schema → rejected at schema resolution.
- **Payload contains fields not in the schema** → ignored (not persisted, not validated); only declared fields are processed.
- **Empty optional field** → valid; absent value treated as empty, not as a rule violation unless `required`.
- **Very large field value** beyond a `max` rule → a `max` validation error, not a truncation.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST let a form be defined in code as a schema of named, typed fields, each carrying an ordered list of validation rules; one definition is the single source of truth for that form.
- **FR-002**: The system MUST provide a headless validator that runs a field's rules against submitted values and returns a structured result: validity, per-field errors (rule key + message), and the normalized values. Rules are evaluated in declaration order and the validator records **at most one error per field** — the first failing rule ("bail per field").
- **FR-003**: The validator MUST support at least the rules `required`, `email`, `max:N`, `min:N`, and `numeric`, and MUST be extensible with additional named rules without modifying existing ones.
- **FR-004**: Validation messages MUST be translation-ready and MUST NOT depend on a WordPress runtime to be produced (the rules→errors logic is pure).
- **FR-005**: The system MUST provide a form-schema resolver that normalizes a form definition (fields → a canonical rule set), rejecting duplicate field names and unknown rules.
- **FR-006**: The system MUST provide an event-dispatch seam that registers listeners for a named event type and dispatches an event object to every listener registered for that type.
- **FR-007**: Listeners MUST be invoked in registration order, each exactly once per dispatch; dispatching an event type with no listeners MUST complete without error.
- **FR-008**: A submission that passes the security gate and validation MUST dispatch a single submission event carrying the form identifier and the validated values; listeners are the only place side effects run.
- **FR-009**: The submit path MUST enforce identity and intent before any side effect: a valid nonce, input sanitization, and rate limiting, reusing the existing security middleware (Principle VII) rather than hand-rolled checks; a missing/invalid nonce MUST fail closed.
- **FR-010**: The submit path MUST include a honeypot (and rate-limit) spam defense; a tripped honeypot MUST reject the submission with no side effect and no error disclosure.
- **FR-011**: On validation failure the system MUST return the per-field errors and run no listener/side effect.
- **FR-012**: The system MUST ship one example form (contact: name/email/message) with an email listener (notifies a configured recipient) and a store listener (persists the submission as a `corex_submission` custom post type via the data layer, retrievable by form identifier).
- **FR-012a**: Dispatch MUST be best-effort and isolated: if one listener fails (throws), the failure is logged, the remaining listeners still run, and the submission is still accepted (dispatch is not transactional).
- **FR-013**: The system MUST provide an FSE block that renders a registered form from its schema, including accessible labels/markup, the nonce and honeypot fields, and required-field indication.
- **FR-014**: The form block's client script MUST load only on pages that contain the block.
- **FR-015**: All form styling MUST use theme.json design tokens and logical (RTL-first) CSS — no hardcoded colors/sizes/fonts and no CSS framework; all user-facing strings MUST be internationalized.
- **FR-016**: All WordPress API calls (the submit endpoint handler, the block registrar, the email/store boundary) MUST be confined to boundary classes; the validator, schema resolver, and event dispatcher MUST be pure and container-resolved.
- **FR-017**: No optional plugin (ACF, page builders, etc.) may be a hard dependency of the forms module; it MUST function on a stock install with corex-core active.
- **FR-018**: Unknown form identifiers and unknown rules MUST be handled non-fatally and surfaced (rejected submission / developer-visible error), never crashing the request.

### Key Entities *(include if feature involves data)*

- **Form (schema)**: A code-defined definition identified by a slug; owns an ordered set of fields. The single source of truth feeding the validator, the submit endpoint, and the block.
- **Field**: A named, typed input within a form (e.g. text, email, textarea) carrying an ordered list of validation rules and an accessible label.
- **Validation Rule**: A named constraint (`required`, `email`, `max:N`, `min:N`, `numeric`, …) that, given a value, passes or yields a structured error.
- **Validation Result**: The outcome of validating a payload — validity flag, per-field errors (rule key + translatable message), and the normalized values.
- **Event**: An immutable object describing something that happened (e.g. a form submission), carrying the form identifier and the validated values.
- **Listener**: A unit of side effect (email, store) registered against an event type and invoked on dispatch.
- **Submission**: The validated values of one successful form submission, persisted by the store listener as a `corex_submission` custom post type (via the data layer) and retrievable by form identifier.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The validator, schema resolver, and event dispatcher are each covered by headless unit tests that pass with no optional plugins and no WordPress runtime loaded.
- **SC-002**: For every supported rule, a valid payload yields zero errors and an invalid payload yields exactly the expected per-field error — verified by tests.
- **SC-003**: Dispatching an event invokes every registered listener for that type exactly once, in registration order, and no listener of another type — verified by tests.
- **SC-004**: A correctly-nonced, valid submission to the example contact form returns success and triggers both listeners; an invalid-nonce, tripped-honeypot, or invalid-field submission is rejected with no side effect — verified by an integration test.
- **SC-005**: The form block renders every schema field with associated labels and required markers, includes the nonce and honeypot, references only preset tokens, and enqueues its script only when present on the page.
- **SC-006**: A submission that fails validation never reaches a listener (zero side effects on failure) — verified by tests.
- **SC-007**: The module adds no hardcoded colors/sizes/fonts and no CSS framework; all visible strings are translation-ready (RTL verified).
- **SC-008**: When one listener throws during dispatch, the remaining listeners still run and the submission is still accepted — verified by a test (best-effort, isolated dispatch).

## Assumptions

- **Submit transport**: a REST endpoint under the Corex namespace (e.g. `corex/v1/forms/{slug}`) is used for submission — the modern, testable boundary — rather than `admin-ajax`. The security gate is applied as middleware on this route. (Recommended default; the lifecycle is transport-agnostic above the boundary.)
- **Event seam location**: the `EventDispatcher` seam lives in corex-core (`Corex\Events`) because it is foundational and shared (Corex Mail and add-ons will consume it); the forms module (`Corex\Forms`) consumes it. Introduced here because Forms is its first consumer.
- **Store listener persistence**: submissions are persisted through the existing data layer as a Corex-owned `corex_submission` custom post type, keyed/queryable by form slug; the admin viewer for them is out of scope (Corex Mail / a later DataViews spec).
- **Email listener**: sends a plain notification to a configured recipient via the platform mail boundary; rich templates/queueing are Corex Mail (spec 009). This listener is a thin, replaceable boundary.
- **Module placement**: the forms module ships as `plugins/corex-forms` (namespace `Corex\Forms`), consistent with the other first-party modules; it registers its provider through the standard extension seam.
- **Client behavior**: progressive enhancement — the form posts to the REST endpoint via a small script; the field markup and labels are server-rendered so the form is meaningful without JS. The Interactivity API niceties (animated responses) are presentation polish, not required for the lifecycle.
- **Rules baseline**: the five named rules above are the v1 set; the rule registry is open for extension (e.g. `url`, `in:a,b`) without changing existing rules.
- Existing modules are reused: spec 005 security middleware (nonce/sanitize/throttle), spec 002 data layer, spec 006 theme tokens, spec 004 block engine.
