# Specification Quality Checklist: Forms & Mail Correctness

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
- [x] Success criteria are technology-agnostic
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

**The scope shrank by one item during verification, and that is the most useful thing this
checklist records.** The issue lists nine problems; this spec takes five; and of those five, item 2
turned out to need no work at all. `DataRegistry` already defers to first read via
`registerDeferred()` — the exact mechanism the issue proposes — because spec 074 shipped it at 07:44
on 2026-07-27, hours before the issue was filed at 15:04 against an older tree.

Had the report been trusted rather than checked, the result would have been a second deferral
mechanism sitting beside a working one, and a spec claiming to fix something that was not broken.
Every other item was confirmed present, in the tree, at the lines the issue named.

**One item is a behaviour change rather than a bug fix**, and is called out as such in the plan:
wrapping manual replies in the brand layout changes what existing sites' replies look like. It is
the intended outcome — a manual reply and an automated email should be the same product — but it is
not invisible, and pretending otherwise would be the wrong kind of quiet.

**Deliberately not specified**: anything from items 6–9. Those four are one feature — file handling
end to end — and splitting them into this correctness pass would have produced a diff mixing "the
code lies" with "the capability does not exist". They are spec 081.
