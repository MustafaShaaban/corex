# Feature Specification: A client site the framework can be updated underneath

**Feature Branch**: `spec/102-update-safe-client-sites`

**Created**: 2026-10-04

**Status**: Draft

**Input**: Owner requirement, stated while starting a new client site — "if CoreX has new updates I
need to make sure that I can do these updates in the client site without missing anything of the
client's implementation, and make sure CoreX allows this."

## Why this spec exists

CoreX tells a team where a client site goes. `README.md` says client source is `sites/<client>/`,
`make:site` generates it there, and the client-site guides are written around it. The site lives in
the client's own repository, which is a copy of the framework repository with the framework as a
second remote; a framework release reaches the client by merging it. One client site has taken five
framework updates this way, v0.33.0 through v0.41.0, so the model is proven.

What is not proven is that **the framework permits the layout it prescribes**. That client site's
history records each place it did not:

- **`tests/repo-hygiene.test.js` forbids `sites/`** — "a client site implementation — those live in
  their own repository". In the client's own repository, that rule fails the suite on the client's
  deliverable. The client had to edit a framework test to remove it.
- **The root linters and test runner sweep `sites/`.** `lint:css` reported 4870 errors on a client
  theme's stylesheet; the root Jest run picked up client suites it cannot resolve. `.stylelintignore`,
  `eslint.config.js` and `jest.config.js` each needed a client-side edit.
- **Framework-only CI runs in the client repository.** The documentation deploy to GitHub Pages
  arrived with a merge and is harmless there only because Pages happens not to be enabled; the
  client recorded it and deliberately did not patch it out, because that would be one more edit to
  a framework file. The nightly run added in v0.42.0 will arrive with that client's next update and
  run the framework's whole suite, every night, in a repository whose subject is one site.
- **The local setup script maps `theme/`, `plugins/*` and `addons/*` into WordPress and stops.** The
  client plugin and theme under `sites/<client>/` are linked by hand, and no document says how.

Every one of those is an edit to a file the framework owns. A file edited on both sides is a file
that can conflict on every future update, which is the opposite of what the owner is asking for.

Three further gaps are about the update itself:

- **No document describes it.** No page under `docs/` or `docs-app/` explains how a client
  repository takes a framework release. `docs/en/05-deployment/updates-and-distribution.md`
  describes a different mechanism — a wp-admin plugin update from a manifest — and draws its
  safe-edit boundary around `corex-app/`, the generators' fallback path. It never mentions
  `sites/<client>/`, which is where `make:site` puts a client site. It also carries
  `last_verified: null`.
- **Nothing proves the framework came through unmodified.** The client verifies it with a
  hand-typed `git diff` against the framework remote. `wp corex compliance:check` exists, but it
  only judges a list of file names passed to it by hand.
- **An update can break a client with no conflict and no failing test.** v0.40.0 changed three
  behaviours a client depended on. v0.41.0 removed a transitive npm package a client build had been
  resolving from the framework's root, and every suite stayed green because none of them builds the
  client. Both were found afterwards, by hand.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A new client repository passes the framework's checks untouched (Priority: P1)

**Why this priority**: every edit a client makes to a framework file to get a green build is a
conflict waiting in the next update. Removing the need for them is what makes the rest possible.

**Independent Test**: take a clean copy of the framework at a release, generate a client site with
a stylesheet, a script and a test of its own, commit it, and run every root check. All pass, and no
file outside `sites/` has changed.

**Acceptance Scenarios**:

1. **Given** a repository containing a generated client site, **When** the repository-hygiene suite
   runs, **Then** it passes, and it still fails on a committed secret, archive, build output or
   dependency directory anywhere — inside `sites/` included.
2. **Given** a client site with its own stylesheets, scripts and tests, **When** the root lint and
   test commands run, **Then** they cover the framework only and report nothing from `sites/`.
3. **Given** the framework's own repository, **When** a file under `sites/` is committed to it,
   **Then** a check fails, because the framework ships no client site.
4. **Given** a client repository, **When** its CI runs, **Then** framework-only jobs — the nightly
   schedule and the documentation deploy — do not run, without the client editing a workflow file.

---

### User Story 2 — Taking a framework release is a written procedure with one outcome (Priority: P1)

**Why this priority**: this is the owner's requirement in one sentence.

**Independent Test**: a client repository at release N follows the documented procedure to release
N+1. The merge reports no conflict in any framework-owned path, nothing under `sites/` changes, and
the procedure ends with a recorded baseline naming N+1.

**Acceptance Scenarios**:

1. **Given** a client repository with no edits to framework-owned paths, **When** it merges the next
   framework release, **Then** there are no conflicts, and `sites/` is byte-identical before and
   after.
2. **Given** the procedure, **When** a developer who has never done an update follows it, **Then**
   every step is a command or a named check with a stated expected result, in English and Arabic.
3. **Given** a completed update, **When** anyone asks which framework release the site is on,
   **Then** one file in the client's own directory answers with the release and its commit.
4. **Given** a framework release that changes the database schema, **When** the procedure is
   followed, **Then** it includes the migration step and states how to confirm it ran.

---

### User Story 3 — One command says whether the framework is unmodified (Priority: P1)

**Why this priority**: "we never edited the framework" is the precondition for a clean update, and
today it is a belief. The hand-typed diff covers the framework directories and nothing else: the
update it verified also committed a 17 MB archive at the repository root, which nobody noticed
until a later release's hygiene suite caught it.

**Independent Test**: on an untouched client repository the command reports the framework as
unmodified and exits successfully. Change one character in one framework file and it names that
file and exits with a failure.

**Acceptance Scenarios**:

1. **Given** a client repository whose framework-owned paths match its recorded baseline, **When**
   the check runs, **Then** it reports no drift and exits successfully.
2. **Given** a framework-owned file that differs from the baseline, **When** the check runs,
   **Then** it lists every such file and exits with a failure, so CI can gate on it.
3. **Given** a deliberate, recorded exception — a framework defect patched locally while the fix is
   awaited upstream — **When** the check runs, **Then** the exception is reported as an exception,
   with its reason, and anything not on the list still fails.
4. **Given** no recorded baseline, **When** the check runs, **Then** it says so and fails, rather
   than comparing against a guess.

---

### User Story 4 — An update that breaks the client is caught before it ships (Priority: P2)

**Why this priority**: a clean merge is necessary and not sufficient. The two regressions on record
both arrived through a clean merge.

**Independent Test**: on a client site that imports a package it does not declare, the client's
build from a clean install fails in CI, before any update. On a release whose notes flag a
behaviour change, the update procedure directs the developer to the flagged items.

**Acceptance Scenarios**:

1. **Given** a generated client site, **When** its CI runs, **Then** the client plugin and theme
   are built from a clean install and the client's own tests run, in a workflow the framework never
   ships a file with the same name as.
2. **Given** a framework release that changes behaviour a client site could depend on, **When** its
   release notes are read, **Then** those changes are listed under a heading reserved for them.
3. **Given** the update procedure, **When** it is followed, **Then** it requires the client build
   and the client's suites to pass after the merge, not only the framework's.

---

### User Story 5 — The local environment maps the client site as well as the framework (Priority: P2)

**Independent Test**: run the setup script in a repository containing `sites/<client>/`. The client
plugin and theme appear in the WordPress install without a manual step, and re-running the script
after moving the repository repairs them.

**Acceptance Scenarios**:

1. **Given** a repository with one client site, **When** the setup script runs, **Then** the client
   plugin and client theme are mapped into the install alongside the framework.
2. **Given** a repository with no client site, **When** the setup script runs, **Then** it behaves
   exactly as it does today.

---

### User Story 6 — The documentation describes the mechanism that exists (Priority: P3)

**Independent Test**: every path named in the updates documentation exists in a generated client
repository, and the page says which update route applies to a client repository under version
control.

**Acceptance Scenarios**:

1. **Given** the updates documentation, **When** it names the boundary between framework files and
   client files, **Then** it names `sites/<client>/`, the location `make:site` generates.
2. **Given** a reader with a client repository under version control, **When** they read the page,
   **Then** it tells them the merge procedure is their route and the in-admin updater is not.

### Edge Cases

- **A root document changed on both sides.** `README.md`, `PROGRESS.md`, `DECISIONS.md`,
  `CHANGELOG.md` and the rest are the framework's. The client's equivalents live in
  `sites/<client>/`, the generated agent files say so, and the unmodified-framework check covers the
  root documents too, so an accidental edit is caught before the next update rather than during it.
- **The client needs a root-level setting the framework also owns** — an ignore entry for a design
  handoff folder, for example. The procedure names where client-only additions go so that they do
  not sit in a file the framework rewrites.
- **A framework defect that blocks the client.** It is reported and fixed in the framework, then
  taken by update. Where the client cannot wait, the local patch is a recorded exception with the
  upstream reference, and the procedure says to delete it — not re-apply it — once upstream has it.
- **An update that spans several releases.** The procedure is the same; the release notes for each
  release in between are read.
- **Two client sites in one repository.** Both are client-owned; the setup script maps both.
- **Agent worktrees and scratch output inside the repository.** The root tooling ignores them, so a
  local run means what a CI run means.
- **The release being merged is not yet tagged.** The procedure names a published tag as the
  baseline and says what to record if a commit past the tag is taken on purpose.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The framework MUST define, in one place that tools read, which paths are
  framework-owned and which are client-owned. `sites/**` MUST be client-owned.
- **FR-002**: Repository hygiene MUST accept a client site under `sites/` in a client repository,
  MUST continue to reject one in the framework's own repository, and MUST apply every other rule —
  secrets, archives, build output, dependency directories — to `sites/` unchanged.
- **FR-003**: The root lint, format and unit-test commands MUST exclude client-owned paths, and
  agent worktree and scratch directories, without any client-side edit.
- **FR-004**: Framework-only CI — the scheduled run and the documentation deploy — MUST NOT run in
  a repository that is not the framework's own, without the client editing a workflow file.
- **FR-005**: `make:site` MUST generate a client CI workflow under a client-specific file name that
  no framework release will ever ship, which builds the client plugin and theme from a clean
  install and runs the client's own tests and the unmodified-framework check.
- **FR-006**: `make:site` MUST record the framework release and commit the site was generated
  against, in a file inside the client's directory.
- **FR-007**: A single command MUST compare every framework-owned path with the recorded baseline,
  list each differing file, and exit non-zero when any is not a recorded exception.
- **FR-008**: Recorded exceptions MUST carry a reason and an upstream reference, MUST be reported
  on every run, and MUST live in the client's directory.
- **FR-009**: The check MUST fail, not guess, when no baseline is recorded or the baseline cannot
  be found.
- **FR-010**: A documented update procedure MUST exist in English and Arabic, covering: fetching
  the release, the working branch, the merge, dependency installation, the framework build, the
  client build, the database migration, the framework suites, the client suites, the
  unmodified-framework check, a before-and-after comparison of public pages, and recording the new
  baseline.
- **FR-011**: The procedure MUST state the rule that keeps it conflict-free — client work never
  edits a framework-owned path — and what to do when a framework defect makes that impossible.
- **FR-012**: `make:site` MUST generate the client's copy of the update checklist and a statement
  of the rule in the generated agent files.
- **FR-013**: Release notes MUST carry a reserved heading listing changes that can alter behaviour
  a client site depends on, including removed or changed dependencies. A release with none MUST say
  so, so that an absent heading is never read as "nothing".
- **FR-014**: The local setup script MUST map every client plugin and theme under `sites/` into the
  WordPress install, and MUST be unchanged in behaviour when there is none.
- **FR-015**: The updates documentation MUST draw its boundary at `sites/<client>/`, and MUST say
  which update route applies to a client repository under version control.
- **FR-016**: The framework's own suite MUST prove FR-002, FR-003 and FR-007 against a generated
  client site, so a later change that re-breaks the layout fails in the framework rather than in a
  client.
- **FR-017**: Nothing in this spec MUST require a client repository that already exists to change
  its layout.

### Found by the work rather than specified

Declared here rather than fixed quietly, because each is a behaviour change and none was in the
spec as approved.

- **FR-018**: `make:site` MUST take the site directory through an option WP-CLI does not consume,
  and the documented command MUST be one that runs. `--path` is a WP-CLI global and is taken as the
  WordPress install before any command sees its arguments, so `make:site --path=sites/acme` — the
  form in the README, three guides and the readiness report — never ran. The option is `--dir`, by
  the owner's decision. Without this FR-005 could not be met from the documented command at all.
- **FR-019**: The setup script MUST leave a linked client plugin or theme in the state it found it,
  on every run and not only the first. Meeting FR-014's "linked, not activated" required activating
  the framework's plugins by name instead of with `--all`, and not re-activating the Corex theme
  over a linked client theme that is already active.
- **FR-020**: The job that checks a generated client site MUST run in the framework's repository
  only. In a client repository it would generate a second site recording a different baseline.
- **FR-021**: Ignore patterns derived from the ownership map MUST apply in a checkout whose own path
  contains a dot-directory. Jest's `<rootDir>` substitution does not survive one on Windows.

**Not done, by decision:** linking client sites into the WordPress that CI provisions. It appeared
in the plan and was dropped, because the provisioning step activates every plugin it finds and the
framework's own suites would then have run with a client's plugin active.

### Out of scope

- Shipping CoreX as an installable dependency instead of a merged remote. It would remove the merge
  entirely, and it is a different distribution model that the build, the autoloader and the setup
  script all assume against today. Recorded as a direction, not started.
- Automating the merge itself. The procedure is run by a person who reads the result.
- Any change to the in-admin updater beyond documenting when it applies.
- Linting or testing client code from the root. Each client site owns its own toolchain.
- Migrating the existing client repository. It can adopt this by deleting its local edits at its
  next update; that is its own change.

### Key Entities

- **Ownership map**: the list of framework-owned and client-owned paths. One source, read by
  hygiene, the linters, the test runner and the unmodified-framework check.
- **Baseline record**: the framework release and commit a client site is synchronised to. Lives in
  the client's directory; written at generation and at the end of each update.
- **Exception record**: a framework-owned file a client has deliberately changed, with its reason
  and upstream reference. Expected to be empty.
- **Client-impact notes**: the reserved section of each release's notes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A client site generated at one release and updated to the next shows 0 conflicts in
  framework-owned paths, and nothing under `sites/` changes except the baseline record, which is
  rewritten to name the new release. *(Amended during implementation: the criterion first read "0
  changed bytes under `sites/`", which the record's own purpose contradicts.)*
- **SC-002**: A newly generated client repository passes every root check with 0 edits to
  framework-owned files.
- **SC-003**: The unmodified-framework check detects a one-character change to any framework-owned
  file, and names the file.
- **SC-004**: The four local edits the existing client repository carries for hygiene, the two
  linters and the test runner are each unnecessary against a release containing this spec —
  reverting each to the framework's version leaves that repository's checks green.
- **SC-005**: A developer who has not done an update before completes one from the documentation
  alone.
- **SC-006**: A client build that imports an undeclared package fails in the client's CI on the day
  it is written, not on the day a framework update removes the package.

## Assumptions

- **The merged-remote model stays.** It is the one in use, it has worked five times, and the owner
  asked for updates to be safe, not for a new distribution model.
- **"The framework's own repository" is identifiable** without configuration — by its repository
  identity in CI and by the absence of any client site locally.
- **A client edits nothing at the root.** Client records, specs, documentation and CI live in
  `sites/<client>/` or in client-named files. Where the existing client repository edits root
  files today, that is treated as the cost this spec removes, not as a pattern to preserve.
- **The baseline is a published tag.** A client that takes an untagged commit records the commit and
  says so.
- **This spec lands before the next client site is created**, so that site starts with no local
  edits to carry.
