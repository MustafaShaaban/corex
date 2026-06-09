# Feature Specification: Corex Mail (MVP)

**Feature Branch**: `008-corex-mail`

**Created**: 2026-06-09

**Status**: Draft

**Input**: User description: "Corex Mail MVP — a templated, secure transactional email engine built on
corex-core (the event seam, Config, the security Validator) and the data layer. Mail facade, code-registered
templates with safe merge variables, a MailDriver abstraction (default wp_mail), a header-injection +
recipient-validation security gate, a RecipientResolver, an EmailLog, and detect-and-defer integration with
the spec-007 Forms email listener. Queue, attachments, multi-provider drivers, admin UI, CLI, suppression,
per-language variants, and the WooCommerce override are out of scope for this MVP."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Send a templated email from code (Priority: P1) 🎯 MVP

A developer sends a transactional email in one statement: choose recipients, a registered template, and a
data context; the engine renders the template (merging the context safely), wraps it in the shared brand
layout, delivers it through the default driver, and records the result.

**Why this priority**: This is the engine. Every other capability (security gate, recipients, the Forms
integration) exists to serve a correct, safe send. Without it there is no product.

**Independent Test**: Register a template, call the Mail API with a context, and assert the rendered body
contains the merged values (escaped), the driver received a well-formed message, and one audit record was
written with status `sent`.

**Acceptance Scenarios**:

1. **Given** a registered `welcome` template and a user context, **When** a developer calls the Mail API to
   send it to a valid address, **Then** the recipient receives the rendered email and an audit record is
   written with status `sent`.
2. **Given** a template whose body references `{{ user.name }}`, **When** the context supplies that value,
   **Then** the rendered body contains the value, escaped for HTML output.
3. **Given** an ad-hoc message (subject + body, no template), **When** sent to a valid address, **Then** it
   is delivered and logged like a templated send.
4. **Given** the default driver fails, **When** a send is attempted, **Then** the failure is caught, the
   audit record is written with status `failed`, and the caller/request is not aborted.

---

### User Story 2 - Safe by default: no header injection, no invalid sends (Priority: P1)

The engine refuses to send anything dangerous: a subject or header field containing a line break is rejected
(header-injection defense), and any invalid recipient address is dropped before delivery — never silently
sent to.

**Why this priority**: Email is a classic injection and abuse vector. Security that is automatic cannot be
forgotten (constitution Principle VII); a mail engine that can be made to inject headers or blast invalid
addresses is unshippable.

**Independent Test**: Attempt to send with a subject containing `\r\n` and assert nothing is delivered and
the attempt is logged; attempt to send to an invalid address and assert it is dropped and logged while a
valid co-recipient still receives the message.

**Acceptance Scenarios**:

1. **Given** a subject containing a carriage return or line feed, **When** a send is attempted, **Then** no
   email is sent and the attempt is recorded as rejected.
2. **Given** a recipient list with one invalid and one valid address, **When** sent, **Then** the invalid
   address is dropped and logged and the valid recipient still receives the message.
3. **Given** a message whose every recipient is invalid, **When** sent, **Then** nothing is delivered and the
   attempt is recorded as failed.
4. **Given** a merge variable that references a path outside the whitelisted context, **When** the template
   renders, **Then** the path resolves to empty — no code executes and no unintended data leaks.

---

### User Story 3 - Resolve recipients flexibly (Priority: P2)

A caller (or a trigger) targets recipients as a fixed address, a WordPress role, or a dynamic reference into
the event/model context (e.g. "the user who submitted"), with an optional reply-to.

**Why this priority**: Real transactional mail rarely goes to a hardcoded address. This makes the engine
usable for notifications and confirmations, but the P1 send works without it (fixed address only).

**Independent Test**: Given a context and a recipient specification of each kind, assert the resolver returns
the expected, validated address set (and skips invalid ones).

**Acceptance Scenarios**:

1. **Given** a role-based recipient (e.g. site administrators), **When** resolved, **Then** every current
   user in that role with a valid email is included.
2. **Given** a dynamic recipient referencing `event.submitter.email`, **When** resolved against the context,
   **Then** that address is included.
3. **Given** a reply-to is configured, **When** the message is built, **Then** the reply-to header is set.

---

### User Story 4 - Forms uses Corex Mail when present (detect-and-defer) (Priority: P2)

When Corex Mail is active, the spec-007 contact-form notification is delivered as a Corex Mail templated,
logged email; when it is not active, Forms still emails via the basic fallback. Neither plugin hard-depends
on the other.

**Why this priority**: It turns the thin `wp_mail` call shipped in spec 007 into a real, templated, audited
notification — immediate, visible value — while preserving the no-hard-dependency rule (Principle IX).

**Independent Test**: With Corex Mail active, submit the contact form and assert the notification was sent
through Corex Mail (templated + an audit record exists); with Corex Mail inactive, assert the form still
sends via the fallback.

**Acceptance Scenarios**:

1. **Given** Corex Mail is active, **When** a valid contact form is submitted, **Then** the admin
   notification is rendered from a template, delivered, and recorded in the email log.
2. **Given** Corex Mail is not active, **When** a valid contact form is submitted, **Then** the notification
   is still delivered via the basic fallback and the form lifecycle is unchanged.

---

### Edge Cases

- A template references a context key that is absent → resolves to empty, render still succeeds.
- A context value is itself an array/object where a scalar is expected → coerced/skipped safely, never dumped raw.
- A send is attempted with an empty recipient list → not sent; recorded as failed (no recipient).
- The configured from-address is empty → falls back to the site admin identity.
- The default driver throws or returns failure → caught, logged failed, request continues (non-fatal).
- A merge value contains HTML/script → escaped on output; never rendered as live markup.
- A very large context → only whitelisted, declared paths are resolved (no unbounded traversal).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide a developer API to compose and send one email — recipients (to, plus
  optional cc/bcc/reply-to), and either a registered template + a data context, or an ad-hoc subject + body.
- **FR-002**: The system MUST support code-registered email templates identified by a stable name, each
  declaring its subject and body source.
- **FR-003**: Template merge variables MUST resolve ONLY from a whitelisted, typed context (the event
  payload, named model(s), and site identity). A reference outside the whitelist resolves to empty; templates
  MUST NOT execute arbitrary code.
- **FR-004**: Every merged value MUST be sanitized on resolve and escaped for its output context, so a value
  containing markup or script cannot alter the rendered email (no template-injection/XSS).
- **FR-005**: Templated emails MUST render within a shared layout whose brand colors and logo derive at
  runtime from `brand.json` (the same token source as the site), and the layout MUST be RTL-correct and
  translation-ready.
- **FR-006**: The system MUST reject any message whose subject or header fields contain a line break or
  control character (header-injection defense); a rejected message is not delivered and is recorded.
- **FR-007**: Every recipient address MUST be validated before delivery; invalid addresses are dropped and
  recorded, never sent to. A message with no valid recipient MUST NOT be delivered.
- **FR-008**: The system MUST resolve recipients as a fixed address, a role (current users in a WordPress
  role/capability), or a dynamic reference into the context, with an optional reply-to.
- **FR-009**: Delivery MUST go through a driver abstraction; the default driver delivers via the platform
  mail function (honoring the site's existing mail configuration). The from-identity (name/address) and
  reply-to MUST be configurable through the Config engine. Switching the driver MUST be configuration, not a
  code change.
- **FR-010**: Every delivery attempt MUST be recorded as an audit entry with its final status (sent / failed
  / rejected), recipients, the template name or subject, and a timestamp; the log MUST be queryable.
- **FR-011**: Sending MUST be non-fatal — a driver error or validation rejection is caught and logged, and
  MUST NOT abort the triggering request or the event dispatch.
- **FR-012**: The mail engine MUST function on a stock install with corex-core active and MUST NOT be a hard
  dependency of any other module. The Forms module MUST use it when active and fall back to basic mail when
  it is not (detect-and-defer).
- **FR-013**: No optional plugin (WooCommerce, ACF, page builders, an SMTP plugin) may be a hard dependency
  of the mail engine.

### Key Entities *(include if feature involves data)*

- **EmailMessage**: the immutable, validated value object the driver delivers — recipients (to/cc/bcc),
  reply-to, subject, rendered body, headers.
- **EmailTemplate**: a code-registered template — a stable name, a subject source, a body source, and the
  context keys it expects.
- **MailContext**: the typed, whitelisted data bag from which merge variables resolve (event, named models,
  site identity) — the only data a template can read.
- **EmailLog**: a persisted audit record per delivery attempt — status, recipients, template/subject,
  timestamp; queryable (e.g. by status or recipient).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can send a templated email in a single statement, with no manual escaping or header
  assembly.
- **SC-002**: 100% of messages whose subject or header contains a line break are blocked from delivery and
  recorded — none are ever sent.
- **SC-003**: A merge reference outside the whitelisted context never executes code and never emits
  unintended data — it renders empty in 100% of cases.
- **SC-004**: An invalid recipient address is never delivered to; it is dropped and recorded, while valid
  co-recipients still receive the message.
- **SC-005**: Every delivery attempt produces exactly one audit record carrying the correct final status.
- **SC-006**: With Corex Mail active, a valid contact-form submission is delivered as a templated email and
  logged; with it inactive, the submission still emails via the fallback and the form lifecycle is unchanged.
- **SC-007**: The headless cores (template renderer, header-injection guard, recipient resolver) are fully
  unit-tested with no WordPress runtime.
- **SC-008**: A rendered email contains no unescaped merge data and is RTL-correct.

## Assumptions

- **Delivery via the platform mail function by default.** The default driver delivers through `wp_mail`,
  honoring whatever SMTP/mail configuration the site already has (e.g. an SMTP plugin or server MTA). The
  engine does **not** store provider credentials in this MVP; dedicated provider drivers (SES/Brevo/SendGrid)
  and credential encryption are deferred.
- **Code-registered templates only.** Templates are PHP classes committed to the repo (version-controlled,
  deployable). Visual/HTML authoring and the admin template studio are deferred.
- **Synchronous, best-effort send.** There is no queue in the MVP; a send is attempted in-process and is
  non-blocking-safe (caught + logged, never fatal). The Action Scheduler queue, retries, and rate limiting
  are deferred.
- **The audit log reuses the existing data layer** (spec 002) — a non-public post-backed store, consistent
  with the spec-007 submission store — since custom tables are not yet part of the framework.
- **Reuses delivered framework seams**: the event dispatcher (spec 007) for triggers/integration, `brand.json`
  (spec 006) for the layout, the Config engine (spec 001) for the from-identity, and the data layer (spec 002)
  for the log.
- **Out of scope (deferred to later specs)**: Action Scheduler queue + retries + rate limiting; attachments
  (media and generated); multi-provider drivers and credential encryption; the DataViews/DataForm admin UI;
  CLI generators (`make:email-template`, `email:test`, …); suppression list / unsubscribe / consent; per-
  language template variants; the WooCommerce transactional-email override; and exposing a `send_email` agent
  ability.
