# Feature Specification: Newsletter / Subscriptions (MVP)

**Feature Branch**: `013-newsletter`

**Created**: 2026-06-10

**Status**: Draft

**Input**: A professional newsletter: subscribe with topic selection, double opt-in (signed token),
secure unsubscribe/suppression, GDPR consent, and an on-publish trigger (a post in a topic emails its
confirmed subscribers). Add-on `corex-newsletter`. Builds on Mail (008), Custom Tables (011), Captcha
(012), and the event seam. The lifecycle + token logic are unit-tested; storage + email are integration.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Double opt-in subscribe (Priority: P1) 🎯 MVP

A visitor submits the subscribe form (email + topics + consent). A pending subscriber is recorded and a
confirmation email with a signed link is sent; the subscriber is active only after confirming.

**Why this priority**: Double opt-in is the professional, GDPR-correct core; without it the list is unsafe.

**Independent Test**: With a fake store + mailer, subscribing records a pending subscriber and dispatches a
confirmation; confirming a valid token marks them confirmed; an invalid/expired token does not.

**Acceptance Scenarios**:

1. **Given** a valid subscribe request, **When** submitted, **Then** a `pending` subscriber is stored with a
   signed confirm token and a confirmation email is sent.
2. **Given** a valid confirm token, **When** confirmed, **Then** the subscriber becomes `confirmed`.
3. **Given** a tampered/invalid token, **When** confirmed, **Then** nothing changes (fail-closed).

---

### User Story 2 - Secure unsubscribe + suppression (Priority: P1)

A confirmed subscriber unsubscribes via a signed one-click link; they are suppressed and never emailed again.

**Why this priority**: Legal requirement and trust; one-click, token-signed, no enumeration.

**Independent Test**: A valid unsubscribe token marks the subscriber `unsubscribed`; a suppressed address is
excluded from any send.

**Acceptance Scenarios**:

1. **Given** a valid unsubscribe token, **When** used, **Then** the subscriber becomes `unsubscribed`.
2. **Given** an `unsubscribed` subscriber, **When** a topic publishes, **Then** they are not emailed.

---

### User Story 3 - Email confirmed subscribers on publish (Priority: P2)

When a post in a newsletter topic is published, its confirmed (non-suppressed) subscribers for that topic
are emailed — through the mail engine, off the request path.

**Why this priority**: This is the payoff, but the subscribe/confirm lifecycle is valuable on its own (P1).

**Independent Test**: Given a published post's topics and a set of subscribers, the recipient set is exactly
the confirmed subscribers whose topics intersect (pure matching); the send goes through the Mailer seam.

**Acceptance Scenarios**:

1. **Given** a post published in topic T, **When** the trigger runs, **Then** every confirmed subscriber of
   T (and no pending/unsubscribed one) is emailed once.

---

### Edge Cases

- Subscribing an already-confirmed email → no duplicate; re-sends confirm only if pending (no enumeration in
  the response either way).
- A confirm/unsubscribe token that is tampered, for another address, or expired → rejected (fail-closed).
- The subscribe form fails the captcha/honeypot/validation → not recorded (reuses spec 007 + 012).
- A topic with no confirmed subscribers → publish trigger is a no-op.
- Many subscribers → sending is bounded/queued (the mail queue is the proper home; see Assumptions).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST record a subscriber with email, selected topics, explicit consent, a status
  (`pending`/`confirmed`/`unsubscribed`), and signed confirm + unsubscribe tokens, in a custom table.
- **FR-002**: Subscribing MUST be double opt-in — a new subscriber is `pending` until they confirm via a
  signed link; the confirmation email is sent through the Mail engine.
- **FR-003**: Confirm and unsubscribe MUST use signed tokens (HMAC over the subscriber identity) that are
  verified server-side; a tampered/invalid token MUST be rejected (fail-closed), with no email enumeration.
- **FR-004**: Unsubscribing MUST suppress the address; a suppressed/pending subscriber MUST be excluded from
  every send.
- **FR-005**: On publishing a post in a newsletter topic, the system MUST email exactly the confirmed,
  non-suppressed subscribers whose topics intersect the post's — once each, through the Mail engine.
- **FR-006**: The subscribe endpoint MUST apply the existing anti-abuse layer (nonce/sanitize/throttle +
  honeypot + captcha) and validation; consent MUST be required and recorded.
- **FR-007**: The add-on MUST NOT hard-depend on an optional plugin; it builds on corex-core (tables,
  events) and uses Mail/Captcha when present.

### Key Entities *(include if feature involves data)*

- **Subscriber** (custom table): email, status, topics, consent, confirm/unsubscribe tokens, timestamps.
- **TokenSigner**: signs/verifies a payload (HMAC + a config secret). Pure.
- **SubscriptionService**: subscribe / confirm / unsubscribe orchestration.
- **Newsletter topic**: a taxonomy on posts; subscribers select topics by the same set.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A subscribe records a pending subscriber + a confirmation send; confirm activates only with a
  valid token (headless, with fakes).
- **SC-002**: A tampered confirm/unsubscribe token never changes state (fail-closed).
- **SC-003**: An unsubscribed/pending subscriber is never included in a send.
- **SC-004**: A publish in a topic targets exactly its confirmed subscribers (pure matching).
- **SC-005**: The token + lifecycle + matching logic are fully unit-tested; storage + email are integration.

## Assumptions

- **Packaging.** `addons/corex-newsletter` (`Corex\Newsletter`). Subscribers in a `corex_subscribers` custom
  table (spec 011). Confirmation/notification emails via the Mail engine (spec 008); captcha via spec 012.
- **Send scale.** The MVP dispatches the publish notification through the Mailer seam in a bounded pass; the
  **Action Scheduler queue** (proper batching/rate-limiting for large lists) is the deferred home and slots
  in behind the same dispatch. Bounce handling and analytics are deferred.
- **MVP scope.** Topic selection, double opt-in, suppression, on-publish trigger. Newsletter *campaign*
  composing/scheduling UI, segments, A/B, and a subscriber admin screen are deferred (the admin dashboard,
  spec 017, surfaces subscribers).
