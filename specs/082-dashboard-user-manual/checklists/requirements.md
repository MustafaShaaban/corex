# Specification Quality Checklist: The CoreX Dashboard User Manual

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

**The gap is real and was checked, not assumed.** `docs/en/` has six sections — getting started,
operations, team workflow, deployment, cookbooks, contributing — and every one addresses a developer.
`docs-app/` adds architecture, a design system and a class reference. A search for "end user",
"user guide" and "user manual" across `docs/` returns nothing. The person who will run a finished
site has no documentation at all.

**One decision carries this spec, and it is deliberately not left to the plan: screenshots are
generated, never pasted.** A hand-illustrated manual is accurate the day it is written and quietly
wrong from then on — a button moves and the picture keeps showing last quarter's product with
nothing to report it. This project has spent four consecutive specs removing surfaces that
confidently said something untrue; an out-of-date screenshot is precisely that, in the one format
nobody thinks to test. SC-003 is written to be *proved by doing it*: change a screen, regenerate,
see a different image.

**Five decisions are left open on purpose**, listed in the spec rather than guessed:

- where the manual lives (`docs/en/` follows the Arabic mirror; `docs-app/` is published and
  searchable but is a separate npm project currently blocked from upgrading, DECISIONS #181);
- task order versus screen order;
- whether Arabic is in scope, given `docs/ar/` already exists;
- whether CI regenerates and fails on drift, which needs a running WordPress that only two jobs have;
- whether screenshots carry numbered callouts, which is a build step that must survive regeneration.

Each would be cheap to assume and expensive to get wrong, and naming them is more useful than
picking one silently.

**FR-008 exists for a specific reason.** The obvious way to make a manual is to screenshot the
machine you happen to be sitting at. That produces images of a developer's test data — real names,
real addresses, whatever was in the submissions inbox that afternoon — published to the internet.
The capture seeds its own fixtures, which is also what makes runs comparable at all.

**Deliberately out of scope**: any change to product code. If a screen turns out to be impossible to
explain without changing it, that is a finding for a different spec — and one worth reporting,
because a screen that cannot be documented usually cannot be used either.
