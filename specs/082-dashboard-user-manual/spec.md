# Feature Specification: The CoreX Dashboard User Manual

**Feature Branch**: `spec/082-dashboard-user-manual` *(not yet created)*

**Created**: 2026-07-28

**Status**: Draft — specified, not implemented

**Input**: Owner request — a step-by-step guide showing an end user how to use the CoreX dashboard,
with real screenshots from the system.

## Why this spec exists

CoreX has a lot of documentation and none of it is for the person who will actually run the site.

`docs/en/` is organised for developers: installation on four platforms, operations, team workflow,
deployment, cookbooks, contributing. `docs-app/` mirrors it with an architecture section, a design
system and a class reference. Every guide assumes the reader is building CoreX or building *with*
CoreX.

Nobody has written for the person who is handed a finished site and told to keep it running — the
one who needs to know how to read a form submission, answer it, publish a post, check whether an
email went out, or find out why a page is slow. That person exists on every project, and right now
their documentation is a phone call to the developer.

## The decision this spec is really about

**Every screenshot is produced by a script that drives the real admin. None is pasted in by hand.**

This is the whole reason the spec is worth writing rather than just writing the pages. A manual
illustrated by hand is correct on the day it is written and silently wrong afterwards: a button
moves, a screen is renamed, and the images keep showing last quarter's product with nothing to
report it. This project has spent four specs removing surfaces that confidently said something
untrue; a screenshot is exactly that, in a format nobody thinks to test.

Generated images regenerate on demand. A screen that changes shape either produces a new image or
fails the capture, and either outcome is visible.

The repository already has everything needed: `tests/e2e/` with `playwright.config.js`, a shared
admin `storageState` from `global-setup.js`, a fixture-seeding step in CI, and the evidence-capture
pattern used through specs 076–079.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A site owner can complete a task without asking anyone (Priority: P1)

Someone who has never seen CoreX opens the manual, finds the thing they need to do, and does it.

**Why this priority**: it is the entire point. A manual that is accurate but unusable has failed.

**Independent Test**: hand the manual to somebody who has not used CoreX and ask them to perform a
named task without help.

**Acceptance Scenarios**:

1. **Given** a task a site owner actually performs, **When** they find its page, **Then** it is
   numbered steps, each with what to click and what they will see.
2. **Given** a step that changes something, **When** it is described, **Then** the manual says what
   will happen before they do it — particularly where the action is hard to undo.
3. **Given** a screen with a control that is disabled or absent on their site, **When** they read the
   page, **Then** it explains why, rather than describing something they cannot find.
4. **Given** a reader who is not a developer, **When** they read any page, **Then** it does not
   require them to know PHP, WordPress internals or CoreX architecture.

---

### User Story 2 - The pictures match the product (Priority: P1)

Every screenshot shows the current product, and stays that way.

**Why this priority**: equal to US1, because a stale screenshot is worse than no screenshot — it
teaches something false with the authority of a photograph.

**Acceptance Scenarios**:

1. **Given** the manual, **When** its images are regenerated, **Then** the command is one step and
   anybody can run it.
2. **Given** a CoreX screen that changed, **When** the images are regenerated, **Then** the change
   appears, or the capture fails loudly.
3. **Given** a capture run, **When** it seeds its own data, **Then** the screenshots show a populated
   product — an empty install teaches nothing.
4. **Given** a capture run, **When** it produces images, **Then** they contain no real personal data
   from whoever ran it.

---

### User Story 3 - The manual is findable and readable where the user already is (Priority: P2)

**Acceptance Scenarios**:

1. **Given** a site owner, **When** they look for help, **Then** the manual is where they would
   look — and the spec must decide where that is.
2. **Given** the published documentation, **When** they search it, **Then** manual pages are
   searchable alongside everything else.
3. **Given** an RTL reader, **When** they read the manual, **Then** it reads correctly — the
   framework is RTL-first and `docs/` already carries an `ar` mirror.

---

### Edge Cases

- **A screen only some sites have** — Blog Pro, Careers, Bookings and the kits are add-ons; the
  manual must not describe a screen a reader cannot open.
- **A screen that looks different by role.** An editor and an administrator see different things,
  and the manual is written for the site owner.
- **A destructive action** — deleting submissions, clearing caches, changing operations mode. These
  need more than a screenshot.
- **A capture that fails because the local site is not running**, which must say so rather than
  produce a broken image.
- **A screen with genuinely nothing to show** on a fresh install.

## Requirements *(mandatory)*

**The manual**

- **FR-001**: The manual MUST be written for a non-technical site owner and MUST NOT assume PHP,
  WordPress internals or CoreX architecture.
- **FR-002**: It MUST cover every CoreX screen a site owner can open, and MUST say which ones depend
  on an add-on being active.
- **FR-003**: Each task MUST be numbered steps naming the control to use and the result to expect.
- **FR-004**: Any step that is hard to undo MUST say so before it is taken.
- **FR-005**: It MUST NOT document a control that does not exist, or a screen a site owner cannot
  reach.

**The screenshots**

- **FR-006**: Every screenshot MUST be produced by a capture script driving the real admin. No
  hand-captured images.
- **FR-007**: Regeneration MUST be a single documented command.
- **FR-008**: The capture MUST seed its own deterministic content, so runs are comparable and no
  real data leaks.
- **FR-009**: Images MUST be captured at a consistent viewport and theme, and the manual MUST state
  which.
- **FR-010**: A capture MUST fail loudly when a screen or control it expects is missing.
- **FR-011**: Captured images MUST be committed, so the docs build needs no WordPress.

**Placement**

- **FR-012**: The manual MUST have one home, decided in the plan, and MUST be reachable from the
  documentation's front door.
- **FR-013**: It MUST be published and searchable alongside the rest of the documentation.
- **FR-014**: Whether the Arabic mirror is in scope MUST be decided explicitly and stated — not left
  implied by the existence of `docs/ar/`.

## Success Criteria *(mandatory)*

- **SC-001**: Somebody who has never used CoreX completes a named task from the manual alone,
  without asking a developer.
- **SC-002**: Regenerating every screenshot is one command, and it either succeeds or reports which
  capture failed and why.
- **SC-003**: Changing a CoreX screen and regenerating produces a visibly different image — proven by
  doing it, not asserted.
- **SC-004**: No screenshot contains data belonging to whoever ran the capture.
- **SC-005**: Every CoreX screen a site owner can open has a page, and every page that describes an
  add-on screen says so.
- **SC-006**: The manual is reachable from the documentation front page in one click.

## Open decisions for the plan

These are genuinely open, and guessing any of them would put the wrong answer in the product:

1. **Where it lives.** `docs/en/` sits beside the developer documentation and is the source the
   Arabic mirror follows; `docs-app/` is published, searchable and styled but is a separate npm
   project — and one currently blocked from upgrading (DECISIONS #181). Images need a home in
   whichever is chosen.
2. **Which screens, and in what order.** Task order — "answer a form submission" — teaches better
   than screen order — "the Submissions screen". Screen order is easier to keep complete. The manual
   probably needs both, and the plan should say how they relate.
3. **Arabic.** `docs/ar/` exists. Committing to a bilingual manual doubles the writing and the
   capture; committing to English only should be stated plainly rather than left to be noticed.
4. **How staleness is caught.** Regeneration on demand is the floor. A CI check that regenerates and
   fails on drift is the honest version — and it needs a running WordPress, which only the browser
   and integration jobs currently have.
5. **Annotation.** Numbered callouts drawn onto a screenshot make steps far easier to follow and add
   a build step that must survive regeneration. Worth deciding before, not after.

## Assumptions

- **The existing Playwright harness is reused, not replaced.** `playwright.config.js`, the shared
  admin `storageState` and the CI fixture-seeding step already do most of this.
- **Screenshots are committed.** Otherwise the documentation build needs a running WordPress, which
  would make the docs undeployable from a clean checkout.
- **The manual describes the product, not the framework.** Where a screen is genuinely for
  developers, the manual points at the developer documentation instead of duplicating it.
- **This spec does not change any product code.** If writing it uncovers a screen that cannot be
  explained without changing it, that is a finding for another spec — and worth reporting, because a
  screen that cannot be documented usually cannot be used either.
