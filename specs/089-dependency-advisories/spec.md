# Feature Specification: Closing the dependency advisories

**Feature Branch**: `spec/089-dependency-advisories`

**Created**: 2026-07-29

**Status**: Draft

**Input**: Owner direction, after v0.39.0 — close the 24 bounded dependency exceptions before
announcing the project publicly.

## Why this spec exists

`PROJECT-STATUS.md` shipped in v0.39.0 listing **24 bounded dependency exceptions, 6 of them high
severity**, as the largest known-open item. Listing them honestly was the right call for a
documentation spec. It is not a resting place: they are the first thing a security-minded adopter
checks, and "governed rather than ignored" is an argument that weakens every month it is repeated.

### What the policy said, and what was actually true

The policy file recorded the npm-root exceptions as held by a pinned `@wordpress/scripts`
constraint, and the npm-docs ones as held by the Astro 7 migration, which was in turn held by a
lockfile packaging question. Both descriptions were approximately right and neither was precise
enough to act on.

**What `npm audit` proposes for the root workspace is a *downgrade*.** `@wordpress/scripts` is
`^33.0.0`; the "fix" npm offers is `@wordpress/scripts@19.2.4`, and for `@wordpress/env` it is
`11.8.0` against an installed `^11.11.0`. `CONTRIBUTING.md` already forbids applying a suggested
downgrade, so "npm says a fix is available" was never the same as "a fix is available". That is the
precise reason those exceptions existed, and the policy did not say it.

**The real fix is `overrides`.** Every vulnerable package in the root tree is a *transitive* one
whose parent pins it below the patched version. npm's `overrides` field resolves exactly this: it
raises the transitive dependency without touching the parent, and it is neither `--force` nor a
downgrade. `npm audit fix` cannot do it, which is why the count did not move when it ran.

**The docs blocker had already dissolved and nobody had re-measured.** The recorded reason was that
regenerating `docs-app/package-lock.json` makes npm expand the `corex-framework "file:.."` workspace
root into the docs tree, taking npm-docs from 5 advisories to 17 — because it would then audit the
root's dev tooling. That was true *while the root tooling had 64 findings*. Once the root is clean
there is nothing for the expansion to drag in, so **fixing the root unblocks the docs migration**.
The two exceptions were never independent; the policy recorded them as if they were.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A security-minded developer evaluates the project (Priority: P1)

They check the repository's advisories before adopting it and find none.

**Independent Test**: `npm run verify:dependencies` reports PASS with **zero** findings and **zero**
accepted exceptions across composer, npm-root and npm-docs.

### User Story 2 — The build, test and docs toolchains still work (Priority: P1)

Raising transitive dependencies under a parent that pinned them is exactly the change that breaks a
toolchain quietly.

**Independent Test**: the full verification set — block build, Jest, both lints, the PHP suites, the
`dist` builder and verifier, and the docs site build — runs green on the changed tree.

### Edge Cases

- **An override breaks its parent.** Backed out and recorded as an exception with the real reason,
  not kept because it improved a number. This happened once, and the entry below says so.
- **An override silently does nothing** because a nested copy remains. Caught by re-auditing after
  every change rather than trusting the field was written.
- **A new advisory lands after this ships.** The gate fails closed on any unbounded finding; the
  empty exception list is a state, not a promise.

## Requirements *(mandatory)*

- **FR-001**: `npm run verify:dependencies` MUST report PASS with zero findings in all three
  ecosystems.
- **FR-002**: The policy file's exception list MUST be empty, because a stale exception is itself a
  gate failure.
- **FR-003**: No fix may be a downgrade of a direct dependency, and `npm audit fix --force` MUST NOT
  be used (`CONTRIBUTING.md`).
- **FR-004**: Every override MUST raise a transitive dependency to a version that is API-compatible
  with the parent that depends on it, proven by the parent's own tooling passing.
- **FR-005**: Any advisory that cannot be closed MUST remain an exception with a reason that names
  the specific blocker, not a category.
- **FR-006**: The docs site MUST build to the same page count after the Astro major upgrade.
- **FR-007**: `PROJECT-STATUS.md`, `ROADMAP.md` and `README.md` MUST stop describing open advisories
  that are closed.

## Success Criteria *(mandatory)*

- **SC-001**: `verify:dependencies` → PASS, 0 findings, 0 exceptions.
- **SC-002**: Root npm advisories go from **64 to 0**; docs npm from **7 to 0**.
- **SC-003**: Jest, both lints, the block build, Pest, the `dist` builder/verifier and Playwright all
  pass unchanged.
- **SC-004**: The docs site builds **286 pages** on Astro 7, the same as on Astro 6.
- **SC-005**: No direct dependency is at a lower version than before.

## Out of scope

- **Upgrading `@wordpress/scripts` or `@wordpress/env` themselves.** Both are already *ahead* of what
  npm proposes. There is no upgrade to take.
- **Removing `markdownlint-cli`** from the toolchain. It is `@wordpress/scripts`' dependency, not
  ours; the nested override fixes it where it sits.
