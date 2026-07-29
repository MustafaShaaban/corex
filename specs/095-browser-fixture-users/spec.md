# Feature Specification: One fixture user per spec that signs in

**Feature Branch**: `spec/095-browser-fixture-users`

**Created**: 2026-07-29

**Status**: Draft

**Input**: Two pull requests blocked by a Playwright failure neither of them caused.

## Why this spec exists

`admin-errors.spec.js` and `guides.spec.js` both sign in as **`corex-editor`**. CoreX locks an
account out after repeated logins from one address — correctly; that is the brute-force protection
working. From a CI runner, every spec shares one IP, so two files signing in as the same fixture trip
it between them.

The failure lands in whichever file runs second, as `signed in as corex-editor: false`. On PR #167 —
which changed no browser test at all — it surfaced in `admin-errors.spec.js`. On PR #168 it surfaced
in `guides.spec.js`. Neither PR was the cause, and neither failure named it.

### The repository predicted this and did not generalise the fix

`guides.spec.js` already carries this comment, about a different user:

> *"`corex-requester` is signed in several times by `access-request.spec.js`, and login protection
> locks an account out after enough attempts from one address. Adding another sign-in here pushed
> that suite over the threshold and failed it in CI — a test in one file breaking a test in another,
> reported as 'could not sign in' nowhere near the cause."*

The diagnosis was right, and the remedy was applied to one spec instead of becoming a rule. This spec
makes it the rule.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A pull request fails only for its own reasons (Priority: P1)

**Independent Test**: run the full browser suite twice in a row; no spec fails on a sign-in.

### Edge Cases

- **A developer's existing install** has no `corex-guides-editor`. The user name is overridable by
  `COREX_GUIDES_EDITOR_USER`, and the spec's own sign-in helper already fails loudly rather than
  skipping — so a missing fixture is reported as a missing fixture.

## Requirements *(mandatory)*

- **FR-001**: No two spec files may sign in as the same fixture user.
- **FR-002**: The CI fixture step MUST seed one user per spec that signs in.
- **FR-003**: Names MUST stay overridable by environment variable, as the existing ones are.
- **FR-004**: The lockout policy MUST NOT be weakened to make tests pass. It is the product behaving
  correctly, and turning it down to suit the suite would remove a real protection from the thing
  under test.

## Success Criteria *(mandatory)*

- **SC-001**: `guides.spec.js` signs in as `corex-guides-editor`; `admin-errors.spec.js` keeps
  `corex-editor`.
- **SC-002**: The full browser suite passes twice consecutively.

## Out of scope

- **Sharing one signed-in session between spec files.** Playwright runs specs in parallel workers
  with independent contexts; making one session shared would trade a login collision for a state
  collision, which is harder to see.
- **`corex-requester`**, already handled by the comment above. Listed so the next person knows it was
  considered rather than missed.
