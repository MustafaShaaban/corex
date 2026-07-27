# Specification Quality Checklist: Unified Admin Error and Access Request Experience

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-28
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

**The defect was reproduced before the spec was written**, as a real subscriber on the running
install, and the reproduction is in `evidence/before/raw-json-navigation.md` with two screenshots.
That changed two things about this spec.

**First, the finding is worse than "an ugly page".** The request *succeeded* — `state: completed`,
a row created, an audit event written. So this is not an error being displayed badly; it is a
success being displayed as an operation envelope. The person who asked for help cannot tell whether
anything happened. A spec written from the brief alone would likely have treated this as error
presentation and landed a "friendly error page", leaving the real hole — no confirmation — open.

**Second, it named the exact mechanism**, which let FR-001 and FR-002 be written as a pair rather
than as one instruction. `AdminPage.php:305` posts a browser form to `rest_url()`. The controller is
not wrong; the wiring is. So the fix adds an admin endpoint that calls the same service, and FR-002
states explicitly that the REST route does **not** change. Without that pairing, the obvious
implementation is to make the REST route return HTML when `Accept` says so — which would satisfy
the visible symptom and break every API consumer, silently.

**US3 is P1 on purpose.** It contains nothing a user asked for. It is there because the plausible
implementations of US1 and US2 — a global `wp_die` handler, content negotiation on REST — are how
this feature would do real damage. Making the boundary a P1 story means it gets tests rather than
good intentions.

**One assumption worth the owner's eye**: the confirmation does not link to a request-status page,
because the requester cannot see Access & Abilities. Inventing a requester-facing status screen is
a larger feature; linking them to a screen they will be denied from would reproduce this very
defect one page later. The confirmation therefore ends at somewhere they can actually open.

**Deliberately not specified**: any change to the Access Service's approval workflow, any new
notification channel, and any redesign of the existing Access Denied page — it is already the
design language this spec generalises.
