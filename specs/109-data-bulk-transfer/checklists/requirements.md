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

- Three markers stood on FR-020, FR-024 and FR-048 in the first draft, with two more questions
  stated as recommendations. The owner answered all five on 2026-10-11 with "as recommended";
  the decisions are in the spec under "Decided by the owner", each with its reason and its
  cost, and are his to reopen. User Story 7 and FR-048 were withdrawn by the first answer.
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
