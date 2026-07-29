# Feature Specification: Two open items, both recorded in the wrong place

**Feature Branch**: `spec/091-rtl-overflow`

**Created**: 2026-07-29

**Status**: Draft

**Input**: `PROJECT-STATUS.md` *Known open items*, after v0.40.0 — the last two entries that were
concrete enough to act on.

## Why this spec exists

Two items had been carried across several releases:

> `corex-access` still overflows 1px in RTL at 375px.

> `clearPendingRequests` in `access-request.spec.js` only clears the current requester's, so failed
> runs accumulate rows.

**Both descriptions named the wrong cause**, and in each case the wrong cause is what kept the item
open: one pointed at a screen with nothing wrong with it, the other at a helper that was already
behaving correctly.

### The overflow is not the access screen, and not CoreX

Bisecting the document at 375px in RTL walks to
`#wp-admin-bar-menu-toggle > a.ab-item`, at `left: -1`. It carries `margin-left: -1px` beside a 1px
left border — a border-overlap trick WordPress writes for LTR — inside an `li` that floats left. In
an RTL document that lands the anchor one pixel outside the viewport, and one pixel outside the
viewport is one pixel of document scroll.

It is WordPress's own admin bar, and it happens on **every** CoreX admin screen in RTL. Access was
simply the screen somebody happened to measure. Measured on `corex-settings`, `corex-access`,
`corex-submissions`, `corex-notifications`, `corex-forms` and `corex-guides`: all six overflow by
exactly 1px, all six in RTL only.

### The accumulating rows come from the integration suite, not the browser spec

`clearPendingRequests` filters to the current run's requester, and its comment explains why: other
specs create access requests, and a cleanup that denied everything pending would break them. That
reasoning is right and the helper is right.

The 311 stuck `corex-079-requester-*` users on the development install come from
`tests/Integration/Access/AccessRequestFormTest.php`, which creates a subscriber in `beforeEach` and
never removes it. Each leaves a pending access request behind, and the denied surface renders its
*pending* state when a request exists — so the accumulation eventually makes the browser spec find a
confirmation where it expects a form.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — An Arabic-reading operator opens any CoreX screen on a phone (Priority: P1)

Nothing scrolls sideways.

**Independent Test**: at 375px, in both directions, `scrollWidth <= clientWidth` on every CoreX
admin screen.

### User Story 2 — The suite is as clean after a run as before it (Priority: P2)

**Independent Test**: count the fixture users, run the integration file, count again — unchanged.

### Edge Cases

- **Fixing the overflow by deleting WordPress's rule** would remove the border overlap it exists for.
  Flipped to the inline-end side instead, so the effect still happens on the correct side.
- **Deleting a user while authenticated as them** leaves the request with a current user that no
  longer exists. `wp_set_current_user(0)` first.

## Requirements *(mandatory)*

- **FR-001**: No CoreX admin screen may scroll horizontally at 375px in either direction.
- **FR-002**: The fix MUST preserve the intent of the rule it corrects, not remove it.
- **FR-003**: The regression test MUST cover **every** CoreX screen and **both** directions — a
  single-screen test is what let this be mis-attributed for several releases.
- **FR-004**: `AccessRequestFormTest` MUST delete the user it creates.
- **FR-005**: `clearPendingRequests` MUST keep sparing other specs' requests.

## Success Criteria *(mandatory)*

- **SC-001**: 12 measurements (6 screens × 2 directions) report zero overflow, and the six RTL ones
  **fail** without the fix — verified, not assumed.
- **SC-002**: Running `AccessRequestFormTest` leaves the fixture-user count unchanged.
- **SC-003**: The full suites stay green.

## Out of scope

- **Deleting the 311 users already on the development install.** They are somebody's environment,
  not repository state, and removing user accounts is not something a spec should do on their behalf.
  The command is recorded in `PROJECT-STATUS.md` instead.
- The three excluded browser specs and Arabic typography — both need real investigation, not a fix.
