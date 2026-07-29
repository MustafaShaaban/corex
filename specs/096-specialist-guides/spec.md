# Feature Specification: The screens somebody is sent to

**Feature Branch**: `spec/096-specialist-guides`

**Created**: 2026-07-29

**Status**: Draft

**Input**: The follow-up spec 094 named in advance — the six screens it deliberately left.

## Why this spec exists

Spec 094 documented the everyday path — orientation, Settings, Overview, Add-ons, Forms & Flows,
Setup — and stopped there rather than shipping fourteen thin guides. It named the seam: *"these are
the screens somebody is sent to when they need them, not ones they browse."*

This is the other half. With it, **every CoreX admin screen has a guide**: 16 guides, 40 topics.

### These read differently, on purpose

Nobody opens Operations & Security out of curiosity. They arrive because something told them to,
usually while worried, and usually needing one specific answer. So each guide here opens with why
you are on the screen rather than with a tour, and says plainly which actions are reversible.

The distinctions that matter are the ones the screens cannot make for themselves:

- **Read is not resolved.** Marking a notification read is a fact about you; the condition may still
  be true. Resolving it clears it for everybody — including when it is not actually fixed.
- **Clear the cache layer you mean, not everything.** Nothing there deletes by pattern, deliberately:
  a blanket sweep would reset brute-force protection at the moment somebody reaches for it.
- **An export of personal data is personal data.** It is audited, and the privacy policy has to
  account for where the file goes next.
- **Critical abilities let the holder change what everybody else can do.** Grant those only to people
  you would make an administrator anyway.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Somebody sent to a specialist screen finds the answer (Priority: P1)

**Independent Test**: open any of the six screens, find its guide, and complete the task without
leaving the admin.

### User Story 2 — Nobody is shown instructions for a screen that will refuse them (Priority: P2)

**Independent Test**: as an editor, none of the six appears — each requires an ability the role does
not hold.

### Edge Cases

- **A destructive step.** Every one carries a warning *before* the instruction: imports, exports,
  cache clears, mode changes and resolving a notification for the whole team.
- **Insights needing a key.** The guide says "could not look" is reported differently from "looked
  and found a problem", because the screen distinguishes them and a reader should not read a skipped
  check as a passed one.

## Requirements *(mandatory)*

- **FR-001**: All six specialist screens MUST have a guide.
- **FR-002**: Each MUST name the ability its screen enforces.
- **FR-003**: Anything hard to undo MUST carry a warning before the instruction.
- **FR-004**: No new registry API — the seam spec 084 built is used as-is.

## Success Criteria *(mandatory)*

- **SC-001**: 16 guides, 40 topics, covering every CoreX admin screen.
- **SC-002**: The suites stay green; the guides browser spec still passes.

## Out of scope

- **Screenshots.** Two exist for 40 topics now. `capture-guide-screenshots.mjs` exits non-zero for
  any id it cannot capture, so a partial set breaks it for everybody — this is its own piece of work
  and is the last remaining gap in the guides.
