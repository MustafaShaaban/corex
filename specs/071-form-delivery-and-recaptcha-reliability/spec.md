# Feature Specification: Form Delivery & reCAPTCHA v3 Reliability

**Feature Branch**: `spec/071-form-delivery-and-recaptcha-reliability`
**Created**: 2026-07-20
**Status**: Draft
**Input**: Owner request — "Make every submission handled through the CoreX Forms pipeline reliably and truthfully report whether the submission was saved and whether its notification email was accepted, captured, queued, rejected, or failed. At the same time, implement proper automatic reCAPTCHA v3 protection for protected CoreX forms."

## Context

Two failures, both silent, both discovered by reading the pipeline rather than by a bug report.

**Spam protection is not merely absent — switching it on breaks the form.** A site owner who selects
reCAPTCHA in settings, saves a site key and a secret, and publishes a contact form will find that
*every* submission is refused. The protection step asks the browser for proof the visitor is human,
the browser was never given any way to produce that proof, and the step correctly refuses an empty
answer. The settings screen offers a score threshold and an action label; neither has any effect on
anything. The owner is given a control that appears to work, reports itself as configured, and
quietly rejects their customers.

**A submission that was saved can look like a submission that was lost.** When the optional CoreX
Mail add-on is inactive, a form built in the visual builder saves the submission and then sends no
notification at all — not a failed notification, no notification. Nothing tells the owner. They
learn about the enquiry when the customer phones to ask why nobody replied.

Even when mail is attempted, the outcome is recorded as a loose string rather than a checked
result, an administrator cannot see it in the inbox, and the pipeline's own timeline entry is
written in a shape the admin screen cannot read, so it renders blank.

The connecting theme is that both surfaces **report success they have not earned**. This feature is
about making the system say what actually happened.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A protected form accepts real people (Priority: P1) 🎯 MVP

**Why this priority**: This is a live regression, not a missing feature. Any site that has turned on
reCAPTCHA is currently rejecting every genuine enquiry. Nothing else in this spec matters if the
form cannot be submitted.

**Independent Test**: Configure reCAPTCHA on a form, submit it as an ordinary visitor in a browser,
and confirm the submission is accepted. Then confirm an automated submission with no proof of
humanity is refused.

**Acceptance Scenarios**:

1. **Given** a form protected by reCAPTCHA and a correctly configured provider, **When** an ordinary
   visitor submits it, **Then** the submission is accepted.
2. **Given** the same form, **When** a request arrives carrying no proof of humanity, **Then** it is
   refused.
3. **Given** a visitor who submits the form twice, **When** the second submission is made, **Then**
   it is judged on fresh evidence and not on the first submission's.
4. **Given** a page containing two independently protected forms, **When** either is submitted,
   **Then** it is judged against its own form's expectations.
5. **Given** a page with no protected form, **When** it loads, **Then** no protection code is
   requested from the provider at all.
6. **Given** the optional protection add-on is inactive, **When** any form is submitted, **Then** the
   form still works and nothing fatals.

---

### User Story 2 — A saved submission is never lost to a mail problem (Priority: P1)

**Why this priority**: Losing a customer enquiry is the most expensive failure this system can
produce, and it is currently possible.

**Independent Test**: Break the notification route, submit the form, and confirm the submission is
still in the inbox afterwards.

**Acceptance Scenarios**:

1. **Given** a working form, **When** the notification cannot be delivered for any reason, **Then**
   the submission remains saved and readable.
2. **Given** the CoreX Mail add-on is inactive, **When** a form built in the visual builder is
   submitted, **Then** a notification is still attempted through the site's ordinary mail path.
3. **Given** the site's mail path refuses the message, **When** the visitor completes the form,
   **Then** they are told their submission was received, because it was.
4. **Given** any submission, **When** it is processed, **Then** it is saved *before* delivery is
   attempted, never after.

---

### User Story 3 — An administrator can see what actually happened (Priority: P1)

**Why this priority**: US2 keeps the data; this makes the failure visible. Without it, a silent
failure is merely a slower silent failure.

**Independent Test**: Cause each delivery outcome in turn, then open the inbox and confirm each one
is distinguishable at a glance and in detail.

**Acceptance Scenarios**:

1. **Given** submissions with different delivery outcomes, **When** an administrator views the list,
   **Then** each submission's outcome is distinguishable without opening it.
2. **Given** a submission whose notification failed, **When** the administrator opens it, **Then**
   they see a safe explanation of what went wrong and when it was attempted.
3. **Given** a submission recorded before this feature existed, **When** it is viewed, **Then** it is
   honestly reported as having no delivery record, not as successful.
4. **Given** a delivery outcome, **When** it is presented, **Then** it is conveyed by text and shape
   as well as colour.
5. **Given** an administrator without permission to view mail records, **When** they open a
   submission, **Then** no link to the underlying mail record is offered.
6. **Given** any delivery failure, **When** it is shown to an administrator, **Then** it reveals no
   credentials, server names, file paths, or internal diagnostics.

---

### User Story 4 — A form declares its own protection (Priority: P2)

**Why this priority**: Valuable and requested, but the site-wide default from US1 already protects
every form. This adds control, not capability.

**Independent Test**: Give two forms different protection settings and confirm each is judged by its
own.

**Acceptance Scenarios**:

1. **Given** a form with no explicit protection settings, **When** it is submitted, **Then** it uses
   a stable default derived from the form's own identity.
2. **Given** two unrelated forms, **When** each is submitted, **Then** neither is judged using the
   other's expectations.
3. **Given** a form published before this feature existed, **When** it is loaded and submitted,
   **Then** it continues to work unchanged.
4. **Given** an administrator sets a stricter threshold for one form, **When** that form is
   submitted, **Then** only that form is affected.

---

### User Story 5 — The boundary with the site's mail plugin is clear (Priority: P3)

**Why this priority**: Prevents a recurring support question and a class of misattributed bug
reports. It changes explanation, not behaviour.

**Independent Test**: Read the Email Studio and Forms help surfaces and confirm they state who owns
which part of sending an email.

**Acceptance Scenarios**:

1. **Given** an administrator reading the email help, **When** they look for who controls the sender
   address, **Then** they are told CoreX composes it and a mail transport plugin may override it.
2. **Given** a site where reliable evidence of a sender override exists, **When** the administrator
   views the guidance, **Then** they are shown that specific likelihood.
3. **Given** a site where no reliable evidence exists, **When** they view the guidance, **Then** they
   are shown general guidance and never a fabricated "detected" state.
4. **Given** any site, **When** CoreX runs, **Then** it never reads, stores, copies, or requires the
   mail plugin's credentials.

---

### Edge Cases

- Proof of humanity arrives but is stale, or is reused from an earlier submission.
- Proof arrives that was issued for a different site, or for a different form.
- The provider is unreachable, or answers with something unreadable.
- The visitor has JavaScript disabled, so no proof can ever be produced.
- Two people submit the same form in the same second.
- A visitor submits, the notification is queued rather than sent, and the queue later fails.
- Development environments where mail is deliberately captured instead of delivered.
- Submissions saved by earlier versions with no delivery record at all.

## Requirements *(mandatory)*

### Protection

- **FR-001**: Protection code MUST be requested from the provider only on pages that actually
  contain a form requiring it.
- **FR-002**: A protected form MUST obtain fresh proof of humanity immediately before each
  submission, and MUST NOT reuse proof from a page load or an earlier submission.
- **FR-003**: The system MUST refuse a submission whose proof is missing, empty, stale, already
  used, unreadable, issued for another site, issued for another form, or scored below the configured
  confidence threshold.
- **FR-004**: The expectation a submission is judged against MUST be determined by the server from
  the form's own stored configuration, and MUST NOT be taken from the request.
- **FR-005**: The provider secret MUST NOT be exposed in any page, script, response, or log.
- **FR-006**: Existing honeypot and rate-limiting protection MUST remain active; this protection
  adds a layer rather than replacing one.
- **FR-007**: A provider or network failure MUST NOT silently allow an unverified submission through.
- **FR-008**: Multiple independently protected forms on one page MUST each be judged against their
  own expectations, and the provider code MUST be requested only once.
- **FR-009**: When proof cannot be obtained, the visitor MUST receive an honest, recoverable message
  and MUST be able to try again.
- **FR-010**: The confidence threshold MUST default to a conservative value suited to ordinary
  low-traffic sites, MUST be constrained to the range the provider supports, and MUST be presented as
  a starting point to monitor rather than a universally correct setting.
- **FR-011**: Allowed site names MUST be matched exactly against an explicit list, never by partial
  match.

### Delivery

- **FR-012**: A submission MUST be saved before its notification is attempted.
- **FR-013**: A delivery failure MUST NOT delete, roll back, or hide an otherwise valid saved
  submission.
- **FR-014**: Every notification MUST record a checked outcome distinguishing *not attempted*,
  *accepted*, *captured*, *queued*, *rejected*, and *failed*.
- **FR-015**: Acceptance by a mail transport MUST NOT be reported as confirmed delivery to an inbox.
- **FR-016**: When the CoreX Mail add-on is active, the notification MUST be routed through it and
  the submission MUST be linked to the resulting mail record.
- **FR-017**: When the CoreX Mail add-on is inactive, the notification MUST still be attempted
  through the site's ordinary mail path, and its outcome recorded.
- **FR-018**: A submission with no delivery record MUST be reported as having none, and MUST NOT be
  presented as successful.
- **FR-019**: A public response MUST NOT reveal server names, credentials, file paths, internal
  identifiers, or diagnostic detail.
- **FR-020**: A correctly saved submission SHOULD be reported to the visitor as received even when
  administrator notification failed.
- **FR-021**: Each notification attempt MUST add a timeline entry that the administration screens can
  read and render.
- **FR-022**: A link to the underlying mail record MUST be offered only when one exists and the
  viewer is permitted to see it.

### Configuration

- **FR-023**: A form MUST be able to declare its own protection expectations, and MUST fall back to a
  stable value derived from its own identity when it declares none.
- **FR-024**: Two unrelated forms MUST NOT share an expectation by accident.
- **FR-025**: Forms published before this feature MUST continue to work without modification.
- **FR-026**: The settings surface MUST honestly distinguish configured, unconfigured, unavailable,
  error, and disabled states.

### Boundary

- **FR-027**: CoreX MUST NOT read, alter, copy, store, or require the credentials or internals of any
  third-party mail transport plugin.
- **FR-028**: Guidance MUST explain which system owns message composition and which owns transport,
  and MUST warn that a transport may override the sender.
- **FR-029**: A sender-mismatch warning MUST be shown only where reliable, non-sensitive evidence
  exists; otherwise general guidance MUST be shown instead of a fabricated detection.

### Scope boundary

- **FR-030**: Documentation MUST state that this covers submissions made through the official CoreX
  Forms interfaces only, and MUST NOT claim coverage of third-party form plugins, custom code that
  bypasses CoreX, direct mail calls made outside the pipeline, or WordPress login forms.

## Success Criteria *(mandatory)*

- **SC-001**: With protection configured, an ordinary visitor completes a protected form
  successfully in a real browser on the first attempt. *(Today this fails 100% of the time.)*
- **SC-002**: A request submitting the form without proof of humanity is refused.
- **SC-003**: A page containing no protected form issues zero requests to the provider.
- **SC-004**: Two consecutive submissions of the same form present different proof.
- **SC-005**: With the notification path deliberately broken, 100% of submitted forms are still
  present in the inbox afterwards.
- **SC-006**: With the CoreX Mail add-on inactive, a visual-builder form submission still produces a
  recorded notification attempt. *(Today it produces none.)*
- **SC-007**: An administrator can identify each of the six delivery outcomes from the submission
  list without opening any record.
- **SC-008**: No delivery failure shown to an administrator, and no public response, contains a
  credential, server name, file path, or internal identifier.
- **SC-009**: Every affected screen is usable by keyboard alone and meets WCAG 2.2 AA in dark mode,
  light mode, right-to-left layout, and at 375px width.
- **SC-010**: Forms published before this feature continue to submit successfully with no change.

## Assumptions

- reCAPTCHA v3 is the provider being added; the existing Turnstile and hCaptcha paths keep their
  current behaviour and are not extended in this feature.
- The confidence-threshold default is **0.3**, chosen with the owner for ordinary low-traffic company
  sites where a stricter default rejects legitimate visitors. The settings help states this is a
  starting point.
- reCAPTCHA v3 requires client-side execution. Where a visitor cannot run it, that is stated
  honestly rather than worked around; the form is not silently allowed through.
- The existing mail-record vocabulary is reused rather than a parallel one invented.
- Submissions remain stored as they are today; this feature adds a delivery record, not a new store.

## Out of scope

- MFA, two-factor authentication, one-time codes, authenticator applications, passkeys, WebAuthn,
  security keys, trusted-device authentication, and MFA recovery codes. Explicitly excluded by the
  owner from this and every phase of this work.
- Notification Center and WordPress Dashboard widgets — spec 072, deliberately sequenced after this.
- Third-party form plugins and any form that bypasses the official CoreX interfaces.
- Replacing or reimplementing any mail transport plugin.
- The recurring `Mail rejected: Illegal characters in the subject field.` warning inherited from
  spec 070. It is adjacent to this work; this feature must determine whether its delivery-outcome
  work surfaces it, and must not silently absorb it.
