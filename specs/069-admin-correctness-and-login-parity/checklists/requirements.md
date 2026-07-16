# Specification Quality Checklist: Admin Correctness & Login Hiding Parity

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-16
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

**Iteration 1** — four findings, all corrected in the spec before this checklist was marked passing:

1. *Implementation details leaked.* The draft named `wp_die`, `plugins_loaded`, `wp_redirect_admin_locations`, `corex_flow_id`/`corex_form_slug`, and `page=corex-data` directly. Rewritten in stakeholder terms ("the site's ordinary not-found response", "before any other component can act", "how that screen actually stores its association to a form"). The precise mechanics live in `plan.md`, which is where they belong.
2. *Untestable requirement.* "Indistinguishable from a genuinely absent page" had no method attached. SC-001 now names one: compare status and body against a control URL.
3. *Zero clarification markers — verify, don't assume.* Four decisions were resolved by the owner in the planning session (Data page removal, full parity scope, in-place Insights unification, select-alongside-free-text) and are recorded under Assumptions rather than left open. The reserved-address rules and urgency-ordering source were informed defaults, also recorded. No marker was suppressed to hit the limit.
4. *Unbounded scope risk.* The duplicated stylesheet sources found during exploration are explicitly excluded in Assumptions rather than silently absorbed.

**Open risk carried into planning, not a spec defect:** FR-019 (no loss of access) versus the assumption that the surviving destination's permission governs. The two screens carried different permissions, so a user holding only the retired one would lose access. The spec requires the gap to be resolved rather than narrowed; `plan.md` must state how before that task is implemented.
