# Feature Specification: Unified Admin Error and Access Request Experience

**Feature Branch**: `spec/079-admin-errors-access-request`

**Created**: 2026-07-28

**Status**: Draft

**Input**: User description: "Replace generic WordPress admin errors with the CoreX-designed experience and fix the Access Request workflow redirect defect — the server-rendered form must stop posting a browser directly at a REST endpoint and rendering raw JSON."

## Why this spec exists

A person without access asks for access, and the product hands them an operation envelope.

Reproduced on the running install as a real subscriber (`evidence/before/raw-json-navigation.md`).
They open a CoreX screen, meet a well-designed Access Denied page, type why they need access, press
**Request access**, and land here:

```
URL:          /wp-json/corex/v1/access/requests
Content-Type: application/json
```

```json
{"data":{"result":{"operation_id":"fa6fe8f5-e366-4c01-87e7-78ed83c46d98","state":"completed",
"message":"The access request was created.","errors":[],"affected_ids":[148],
"started_at":"2026-07-27T21:20:16+00:00","finished_at":"2026-07-27T21:20:16+00:00",
"audit_event_id":4258}}}
```

No design, no heading, no navigation, no way back.

**Nothing here is broken.** `AdminPage.php:305` renders a plain HTML form whose `action` is
`rest_url('corex/v1/access/requests')`. The controller correctly returns JSON, because that is what
a REST endpoint does. The browser correctly navigates to the action and renders what it gets. The two
are simply wired to each other.

**And the request succeeded.** `state: completed`, `affected_ids: [148]`, an audit event written.
Their request is genuinely waiting for an administrator, and they have no way to know — the only
thing they were shown was `operation_id`. A failure would at least look like a failure. This looks
like the product breaking at the moment somebody is asking for help.

The same absence of a designed error path shows up wherever WordPress refuses a request: a grey
canvas and a white box reading *"Sorry, you are not allowed to access this page."*

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Asking for access ends somewhere that makes sense (Priority: P1)

Someone without access asks for it, and is told plainly that the request was sent, what was
requested, when, and where to go next — inside the product, never on a JSON document.

**Why this priority**: it is the defect. It is also the worst possible moment to fail, because the
person is already stuck and already asking for help.

**Independent Test**: sign in as a subscriber, request access, and confirm the browser stays on a
CoreX-designed page that says the request was sent.

**Acceptance Scenarios**:

1. **Given** a signed-in user without access, **When** they submit the request form, **Then** they
   land on a CoreX-designed confirmation, and the browser never navigates to a JSON document.
2. **Given** that confirmation, **When** they read it, **Then** it names what was requested, when it
   was sent, and offers a safe way onward.
3. **Given** JavaScript is disabled, **When** they submit, **Then** the same confirmation appears.
   The no-JavaScript path is the one that must work; enhancement is optional.
4. **Given** they refresh the confirmation, **When** the page reloads, **Then** no second request is
   created.
5. **Given** they press Back after submitting, **When** the browser returns, **Then** nothing is
   resubmitted.
6. **Given** an equivalent request is already pending, **When** they submit again, **Then** they are
   told it is already pending and when the first was sent — not shown a generic error, and not given
   a duplicate.
7. **Given** the reason is empty or invalid, **When** they submit, **Then** they stay in the CoreX
   design with the field error shown, focus moved to it, their text preserved, and no request
   created.
8. **Given** the service fails, **When** they submit, **Then** they see a CoreX error state with a
   reference and a retry where retrying is safe — never an internal operation payload.

---

### User Story 2 - A refusal looks like part of the product (Priority: P1)

Every human-facing admin refusal CoreX is responsible for is rendered in the CoreX design: what
happened, why, and what to do next.

**Why this priority**: equal to US1, because the denial screen is where US1 begins. A designed
request form reached through an undesigned refusal is half a journey.

**Independent Test**: trigger each covered refusal as a real low-privilege user and confirm none
produces WordPress's white box.

**Acceptance Scenarios**:

1. **Given** any covered human-facing admin refusal, **When** it renders, **Then** it carries CoreX
   context, an accessible icon, a heading, a plain explanation, what to do next, and a safe way
   back.
2. **Given** the refusal is one Access Request can help with, **When** it renders, **Then** the
   request control is offered — and only when it would really work.
3. **Given** any refusal, **When** it renders, **Then** the HTTP status is correct and unchanged.
4. **Given** a refusal, **When** it renders, **Then** it exposes no stack trace, path, query, token,
   nonce or internal identifier.

---

### User Story 3 - Machine callers keep getting machine answers (Priority: P1)

REST, AJAX, XML-RPC, cron, WP-CLI, feeds, downloads and health checks are unaffected. They keep
their status codes, their headers and their JSON.

**Why this priority**: P1 because it is the way this change could do harm. Wrapping a REST error in
an HTML page would break integrations silently, and the fix for a raw-JSON page must not be to make
JSON pages into HTML.

**Independent Test**: request a REST route that refuses, and confirm the response is still JSON with
its original status.

**Acceptance Scenarios**:

1. **Given** a REST request that is refused, **When** it responds, **Then** it is JSON with the same
   status code as before.
2. **Given** AJAX, cron, WP-CLI, a feed, a download or a health check is refused, **When** it
   responds, **Then** its content type and status are unchanged.
3. **Given** login, recovery mode, plugin or theme installation, or a WordPress update, **When**
   they run, **Then** they are untouched.

---

### User Story 4 - A React screen fails the way the rest of the product does (Priority: P2)

When a CoreX React screen cannot load, cannot save, or is refused, it says so in the same language
as the rest of the admin, at the scale of what failed.

**Why this priority**: real, and lower only because these paths already render something rather than
nothing.

**Acceptance Scenarios**:

1. **Given** a field, an action, a panel or a whole screen fails, **When** it renders, **Then** the
   error appears at that scale — a field error for a field, not a full-page error for a bad value.
2. **Given** any client failure, **When** it renders, **Then** no raw API payload and no blank mount
   is shown.
3. **Given** a failure that is safe to retry, **When** it renders, **Then** a retry is offered.

---

### Edge Cases

- **Two tabs submitting the same request**, or a double-click.
- **A pending request that was already approved** between page load and submit.
- **A user whose account loses the ability mid-flow.**
- **A return address supplied in the URL** — must be server-generated or allowlisted, same-origin,
  and never an open redirect.
- **A flash message read by a different user**, or forged through the query string.
- **A refusal on a screen whose CoreX shell cannot safely load**, which needs a standalone document.
- **Multisite / network admin**, where the refusing screen may not be a site screen at all.
- **A reason containing markup**, which must never reach a page as markup.

## Requirements *(mandatory)*

### Functional Requirements

**The Access Request workflow — the mandatory fix**

- **FR-001**: The server-rendered Access Request form MUST NOT post the browser to a REST endpoint.
  It MUST submit to a dedicated admin endpoint that validates, calls the same Access Service the
  REST route calls, and redirects.
- **FR-002**: The REST route MUST remain JSON, unchanged, for JavaScript, API consumers and tests.
  This is not a conversion of an API into a page.
- **FR-003**: Submission MUST follow Post/Redirect/Get, so refresh creates no second request and
  Back resubmits nothing.
- **FR-004**: On success the user MUST land on a CoreX-designed confirmation naming what was
  requested, when it was sent, and a safe way onward. It MUST NOT show `operation_id`, `state`,
  `affected_ids`, `audit_event_id` or any other internal field.
- **FR-005**: An equivalent pending request MUST produce a designed "already pending" state naming
  what was requested and when the first was sent — not an error, and not a duplicate.
- **FR-006**: Validation failure MUST keep the user in the CoreX design with a field-level error,
  focus on the first error, their reason preserved, and no request created.
- **FR-007**: Service failure MUST produce a CoreX error state with a correlation reference and a
  retry where retrying is safe.
- **FR-008**: Duplicate submission MUST be prevented server-side, not only by disabling a button.
- **FR-009**: A JavaScript enhancement MAY submit in place, but MUST prevent navigation, keep the
  user on the page, and remain entirely optional.
- **FR-010**: Any return address MUST be server-generated or allowlisted, same-origin, free of
  nonces and sensitive values, and MUST fall back to the Dashboard.
- **FR-011**: Any flash state MUST be user-bound, short-lived, single-use, non-sensitive, unreadable
  by another user, and safe against query manipulation. The submitted reason MUST NOT travel in a
  query string.

**The shared error experience**

- **FR-012**: One error model MUST describe server refusals, React failures, WordPress refusals and
  CoreX refusals, carrying type, status, a stable code, severity, a human title, an explanation,
  what to do next, available actions, whether Access Request applies, whether retry is safe, a safe
  back destination, environment, a human timestamp and a correlation reference.
- **FR-013**: Every full-page error MUST carry CoreX context where safe, an accessible icon, a
  status label, a heading, a concise explanation, recovery guidance, a primary action, a secondary
  action, a reference where useful, and the correct HTTP status.
- **FR-014**: Covered human-facing admin HTML refusals MUST use the CoreX experience: capability
  denial, CoreX menu denial, generic admin `wp_die()`, invalid nonce, invalid action, missing admin
  page, session expiry, maintenance, controller exceptions, React mount failure.
- **FR-015**: Errors MUST NOT expose exceptions, SQL, paths, passwords, tokens, nonces, stack traces
  or API payloads. Debug detail may appear only where it is safe to show it.
- **FR-016**: Variants MUST exist for access denied, missing or unavailable page, invalid or expired
  action, session expired, rate limited (preserving 429 and retry timing), and service unavailable.

**Boundaries**

- **FR-017**: REST, AJAX, XML-RPC, WP-CLI, cron, feeds, JSON, downloads, CSV and webhooks MUST NOT
  be converted to HTML. Status, headers, encoding, localization, retry headers and exit behaviour
  MUST be preserved.
- **FR-018**: Login, recovery mode, plugin and theme installation, WordPress updates and file
  downloads MUST NOT break.
- **FR-019**: Where the CoreX shell cannot safely load, a standalone CoreX error document MUST be
  used rather than degrading to WordPress's.

**Recording**

- **FR-020**: Errors MUST record only safe fields — correlation reference, timestamp, user ID,
  requested page, error code, HTTP status, source, environment, resolution. Never passwords, nonces,
  tokens, secret parameters, form bodies or cached values.
- **FR-021**: Repeated operational failures MUST be deduplicated before notifying.

**React**

- **FR-022**: CoreX React screens MUST use shared error components covering load failure, permission
  failure, validation, conflict, offline, server error, empty response, retry and partial
  degradation — at field, action, panel or page scale as appropriate.

### Key Entities

- **Refusal**: a request the product will not fulfil, with a reason, a status and a way forward.
- **Access request**: a person asking for an ability, with what they asked for, why, when, and its
  state.
- **Error presentation**: the designed rendering of a refusal — never the raw result of one.
- **Correlation reference**: a safe identifier tying what a user saw to what was recorded.
- **Flash state**: the short-lived, user-bound result of a redirect.

## Success Criteria *(mandatory)*

- **SC-001**: Requesting access never navigates the browser to a JSON document. It currently always
  does.
- **SC-002**: A successful request shows a designed confirmation naming what was asked and when.
  Currently it shows `operation_id` and seven other internal fields.
- **SC-003**: Refreshing after success creates no second request; Back resubmits nothing.
- **SC-004**: A duplicate request produces a designed pending state, not an error and not a
  duplicate row.
- **SC-005**: No covered human-facing admin refusal renders WordPress's white box, and
  *"Sorry, you are not allowed to access this page."* no longer appears in that presentation.
- **SC-006**: REST routes still answer JSON with unchanged status codes — verified per status.
- **SC-007**: No error page exposes a stack trace, path, SQL, token, nonce or internal identifier.
- **SC-008**: Every error is announced correctly, keyboard reachable, and passes the acceptance
  matrix — RTL, 375px, 200% zoom, light and dark — with no horizontal overflow beyond stock
  wp-admin's own and no console error.

## Assumptions

- **The existing Access Denied design is the canonical error language.** It is already good; this
  spec generalises it rather than redesigning it.
- **The Access Service is not rewritten.** Both the REST route and the new admin endpoint call the
  same service, so the two paths cannot diverge in behaviour.
- **Only human-facing admin HTML is covered.** Machine boundaries are explicitly out (FR-017), and
  that is the requirement most likely to be broken by an over-broad implementation.
- **Dates use the spec 076 contract.** The current JSON exposes raw ISO timestamps, which the
  designed states must not.
- **No requester-facing status page is invented.** The confirmation links onward only to somewhere
  the requester can actually open — linking them to Access & Abilities, which they cannot see, is
  the same defect in a new place.
