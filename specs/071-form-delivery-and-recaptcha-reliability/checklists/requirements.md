# Specification Quality Checklist: Form Delivery & reCAPTCHA v3 Reliability

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-20
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

- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`

### Validation record

Written deliberately after the 070 checklist finding, which failed four items for mixing mechanics
into a spec. This spec keeps the split 069 established and 070 did not: `spec.md` states user-visible
behaviour only ("proof of humanity", "the site's ordinary mail path", "a safe explanation of what
went wrong"); every named symbol, file path, core function, and provider field lives in `plan.md`,
`research.md`, and `data-model.md`. The provider name "reCAPTCHA v3" appears only where the owner
request names it and where the user would see it — it is the product being configured, not an
implementation leak.

**Clarifications resolved with the owner rather than left open** (recorded in `spec.md` §Assumptions):

1. *Threshold default.* Owner chose **0.3** for ordinary low-traffic company sites over Google's
   documented 0.5, on the reasoning that a stricter default rejects legitimate visitors where the
   model has little traffic signal. Recorded as a starting point to monitor, not a fixed truth.
2. *Provider scope.* reCAPTCHA v3 is the provider being added; Turnstile and hCaptcha keep their
   current behaviour. Recorded in §Assumptions and `plan.md` §Out of scope, not left ambiguous.
3. *Vocabulary.* The existing `MailResult` states are reused rather than a parallel set invented —
   the owner request mandates this ("reuse them instead of creating competing vocabulary").
4. *No-JS behaviour.* reCAPTCHA v3 needs client execution; where it cannot run, that is stated
   honestly and the form is not silently allowed through (FR-007, edge case). An informed default,
   recorded, not a suppressed marker.

**No scope creep.** The Notification Center and Dashboard work the owner requested in the same brief
is explicitly deferred to spec 072 (§Out of scope), preserving the mandatory Phase-A-first ordering.
MFA and its whole family are excluded per the owner, in §Out of scope.

### Requirement → success-criterion coverage

Every FR maps to at least one SC or acceptance scenario:

| Area | Requirements | Verified by |
|---|---|---|
| Protection accepts real people | FR-001–011 | SC-001, SC-002, SC-003, SC-004; US1 scenarios |
| Submission survives mail failure | FR-012, FR-013, FR-017, FR-020 | SC-005, SC-006; US2 scenarios |
| Outcome is truthful & visible | FR-014–016, FR-018, FR-021, FR-022 | SC-007; US3 scenarios |
| Nothing sensitive leaks | FR-005, FR-019 | SC-008; US1.2, US3.6 |
| Per-form protection | FR-023–025 | SC-010; US4 scenarios |
| Honest settings states | FR-026 | US1 setup; ui-state.md |
| Transport boundary | FR-027–029 | US5 scenarios |
| Scope boundary documented | FR-030 | §Out of scope; docs task |
| Accessibility | (all UI) | SC-009 |

### Open risk carried into planning

None that blocks planning. One item is **flagged, not resolved**: the inherited
`Mail rejected: Illegal characters in the subject field.` warning (spec 070 §Out of scope). WS3
touches the code path that logs it. `plan.md` §Out of scope commits to reporting whether the delivery
work surfaces the cause rather than silently absorbing it — that determination is made during
implementation, not deferred indefinitely.
