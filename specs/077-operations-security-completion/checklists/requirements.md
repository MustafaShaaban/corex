# Specification Quality Checklist: Operations & Security UX and Safety Completion

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-27
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

**The most useful thing this spec does is say what is NOT in scope**, and it is worth recording why.

The owner's brief describes work in four areas that the reconnaissance found already implemented:
the environment-versus-mode distinction (`OperationsMode`, `OperationsModeStore`), the blocking /
warning / passed split (`ReadinessSnapshot`, four states), the slug validation list (`LoginSlug`,
built after DECISIONS #140 found two real lockout paths), and maintenance behaviour
(`MaintenanceGuard`). Specifying them again would have produced a rewrite of working code and a much
larger, riskier change. The "What is already right" section states each one and names the class, so
the claim is checkable rather than asserted.

**Two defects were reproduced on the running install rather than inferred**, and both carry a
measured baseline into the success criteria:

- The mode form renders the typed `PRODUCTION` confirmation *and* the maintenance acknowledgement
  while the site is in **Development** — captured in `evidence/before/`, with
  `productionConfirmVisible: true` and `maintenanceAckVisible: true` against `selectedMode:
  "development"`. That is SC-003's baseline of two.
- `OperationsModeStore::set()` writes the option and appends a history entry unconditionally, so
  re-applying the current mode logs `development → development` and reports success. That is
  SC-004.

**One assumption carries real design weight** and is flagged rather than buried: progressive
disclosure needs JavaScript, because the mode form is currently a plain server POST with no client
behaviour whatsoever. FR-013 keeps the no-JS path working, which constrains how the disclosure can
be built — the server has to render and validate correctly without it.

**Deliberately deferred**: the Cache & Performance section (spec 078 builds it against this
architecture; an empty tab now would be the dead end the mandate forbids) and any lockout unlock
control that does not already exist behind a real, audited implementation.
