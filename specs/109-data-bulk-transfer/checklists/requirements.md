# Specification Quality Checklist: Several data models are exported, imported and moved together

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-10-11
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [ ] No [NEEDS CLARIFICATION] markers remain
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

- Three markers remain, on FR-020 (who may take personal data out in a file), FR-024 (whether
  pages, templates and settings are part of a move) and FR-048 (what "migrate" meant). They are
  questions 3, 2 and 1 under "Questions for the owner". Questions 4 and 5 carry no marker: the
  spec states the recommended answer for each and says so. `/speckit-clarify` ran on 2026-10-11
  with nobody to answer; the session is recorded in the spec with every answer open. This
  spec is not to be planned until the owner has answered.
- "Why this spec exists" and the three bullets above it say what the code does today, read at
  `21fb2dd9`. One statement in the first draft was wrong and was corrected against the source
  before this was committed: it said no notification is sent for a finished export. One is,
  from `ExportReadyNotificationProducer`, for both export kinds.
- Stated as leads, not as proof: that no expiry exists for a Data export's file (none was found
  in the Data export's classes; the daily sweep's export store is the Submissions export's),
  and that job applications accept an import (read from the table's declaration; no import was
  run).
- CSV, Excel and PDF are named because they are what the person chooses, as in spec 103.
- SC-007's 20,000 records is a target to be measured in planning, not a measurement.
