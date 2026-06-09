# Feature Specification: Call Request (MVP)

**Feature Branch**: `015-call-request`

**Created**: 2026-06-10

**Status**: Draft

**Input**: A "book a call with one of our leaders" flow — pick a leader, leave contact + a preferred time,
which is stored and notifies the leader + confirms the visitor. Add-on `corex-bookings`. Stored in a custom
table (011); notifications via Mail (008); honeypot + captcha (012) on the endpoint. Service logic is
unit-tested; the data path is integration-tested.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Request a call with a leader (Priority: P1) 🎯 MVP

A visitor chooses a leader, leaves their name/email/phone + a preferred time + message; the request is
validated, stored, and the leader is notified + the visitor confirmed.

**Why this priority**: This is the whole feature.

**Independent Test**: With fakes, a valid request to a known leader stores one record and sends two emails;
an unknown leader or a missing/invalid field is rejected with zero side effects.

**Acceptance Scenarios**:

1. **Given** a known leader + valid contact, **When** requested, **Then** a record is stored and the leader
   + visitor are emailed.
2. **Given** an unknown leader, a missing name, or an invalid email, **When** requested, **Then** it is
   rejected and nothing is stored or sent.

---

### Edge Cases

- A captcha/honeypot failure → rejected, no side effect (spec 012/007).
- An unknown leader id (tampered) → rejected.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST present the configured leaders to choose from and accept a request only for a
  known leader.
- **FR-002**: A request MUST require a name + a valid email; it MAY include phone, a preferred time, and a
  message.
- **FR-003**: A valid request MUST be stored in a custom table (leader, contact, preferred time, status,
  timestamps) and MUST notify the leader + confirm the visitor via Mail.
- **FR-004**: A rejected request MUST have zero side effects.
- **FR-005**: The request endpoint MUST be honeypot + captcha gated (spec 012/007).
- **FR-006**: The add-on MUST NOT hard-depend on an optional plugin; it builds on corex-core + uses Mail/Captcha.

### Key Entities *(include if feature involves data)*

- **Leader**: a configured `{id, name, email}` (from `bookings.leaders`).
- **CallRequest** (custom table): leader id, name, email, phone, preferred time, message, status, timestamps.
- **CallRequestService**: validate → store → notify. Pure.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A valid request stores one record + sends two emails; an invalid one has zero side effects.
- **SC-002**: Only requests to a known leader are accepted.
- **SC-003**: The service + leader logic is fully unit-tested; the data path is integration-tested.

## Assumptions

- **Packaging.** `addons/corex-bookings` (`Corex\Bookings`). Requests in a `corex_call_requests` custom table
  (011). Leaders are configured (`bookings.leaders`); a leaders CPT/screen and real calendar/scheduling
  integration are deferred.
- **MVP scope.** Request + store + notify. Availability calendars, time-zone handling, and reminders are deferred.
