# Feature Specification: Making this repository something a stranger can adopt

**Feature Branch**: `spec/088-public-release-readiness`

**Created**: 2026-07-28

**Status**: Draft

**Input**: Owner direction — *"we need to prepare this repo to be public and used by other developers
for the current state. So I need to clean up the repo. Update all possible documents and docs app.
List the non completed features, and what is the upcoming features and so on. I need the roadmap to
be very clear to be able to announce about this project."*

## Why this spec exists

The repository is already clean of the things that usually block going public. There is no `sites/`
directory on disk or in the index, the only tracked env file is `.env.example`, `azure-pipelines.yml`
is fully parameterised and says so, a repo-wide search for `bseit.visualstudio.com` and
`dev.azure.com` returns nothing, and the only strings that look like secrets are labelled test
fixtures. **This is not a scrubbing job.**

What is actually wrong is that the repository does not tell the truth about itself to somebody who
has never seen it.

### The plugins point update checks at a repository that does not exist

`plugins/corex-core/corex-core.php` carried `Update URI: https://github.com/bseit/corex`, and the
same organisation appeared in `composer.json`, `theme/style.css` and three more plugin headers —
while `docs-app` and the release links point at `github.com/MustafaShaaban/corex`. `Update URI` is
not documentation: WordPress uses it to decide where an update comes from. This is a functional
defect that ships in every installed copy, and it is the one item here that is a bug rather than a
description.

### The roadmap describes a version that is three releases old

`ROADMAP.md` names v0.35.x as the latest and says *"Active now: nothing"* directly above a §17 list
that runs to spec 085. `README.md` and `docs-app/src/version.ts` both say **v0.38.1**. A public
reader meeting a stale roadmap next to a current README learns that the project does not keep its own
records, which is the opposite of what this repository's history actually shows.

### Nothing states what is unfinished

The information exists — it is spread across ROADMAP §15 and §17, the "Open, and worth picking up"
block in `PROGRESS.md`, 24 bounded exceptions in `.github/dependency-security-policy.json`, three
excluded browser specs carrying their ruled-out causes in `tests/e2e/playwright.config.js`, and the
`future` row in `design/INVENTORY.md`. A developer deciding whether to adopt this cannot be asked to
assemble that themselves, and a project that hides it reads as one that has not looked.

### The internal record looks like clutter until it is introduced

`PROGRESS.md` is 424 KB, `DECISIONS.md` is 313 KB, and `specs/` holds 40 directories. To somebody who
does not know the working method these read as files that should have been cleaned up. They are the
opposite: they are the argument for the project. They need one paragraph that says so.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A developer decides in five minutes whether to adopt this (Priority: P1)

They open the README, learn what CoreX is, what it requires, what state it is in, what works and what
does not, and where to start.

**Independent Test**: hand the README to somebody who has never seen the repository and ask them to
state the project's maturity and name three things it does not yet do. Both answers must come from
the README and its immediate links.

### User Story 2 — Somebody installing the plugins gets updates from the right place (Priority: P1)

The `Update URI` in every plugin header names the repository that actually publishes releases.

**Independent Test**: grep every plugin header, `composer.json` and `theme/style.css` for a
repository URL; every one resolves to the same public home.

### User Story 3 — An operator can see what is unfinished without reading the git history (Priority: P1)

One page lists every module with a status and, where it is not complete, one line saying what is
missing.

**Independent Test**: for each item on the status page, find the repository artifact that records it
(a spec, a policy entry, a config exclusion, an inventory row). No item is unsourced, and no known
gap recorded in those artifacts is absent from the page.

### User Story 4 — The roadmap can be announced (Priority: P2)

The milestone table matches the released version, names what is in flight, and separates *planned*
from *authorized*.

**Independent Test**: the version the roadmap describes equals `docs-app/src/version.ts`, and every
milestone row's status is traceable to a merged PR or an open spec.

### Edge Cases

- A reader who takes the roadmap as a commitment → the existing §16 governance rule ("roadmap
  presence does not authorize implementation") stays, and is stated where a public reader meets it.
- A reader who assumes "not complete" means "broken" → the status page distinguishes *stable*,
  *partial* and *planned*, and says what partial means for each.
- The dependency advisories → recorded as a known open item with its existing policy, not hidden and
  not silently resolved. Resolving them is dependency work under the bounded-exception gate, and is
  explicitly **not** this spec.

## Requirements *(mandatory)*

### Functional

- **FR-001**: Exactly one repository URL MUST appear across `composer.json`, `theme/style.css` and
  every plugin header, and it MUST be the repository that publishes releases.
- **FR-002**: Every plugin's `Update URI` MUST resolve to that repository.
- **FR-003**: `ROADMAP.md` MUST describe the currently released version.
- **FR-004**: A feature-status page MUST exist listing every shipped module with a status of stable,
  partial or planned, and MUST name what is missing for anything not stable.
- **FR-005**: Every entry on that page MUST be traceable to a repository artifact. Nothing is listed
  on memory.
- **FR-006**: `README.md` MUST state what CoreX is, its requirements, its maturity, how to install
  it, and where the status page is.
- **FR-007**: `README.md` MUST explain what `PROGRESS.md`, `DECISIONS.md` and `specs/` are, so the
  working record reads as a method rather than as clutter.
- **FR-008**: The known-open items MUST include the dependency advisories, the excluded browser
  specs, and the RTL overflow on `corex-access` — each with its existing source.
- **FR-009**: `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md` MUST match the workflow the
  repository actually enforces.
- **FR-010**: The docs site MUST build, and MUST carry the same status information as the repository.

### Non-functional

- **NFR-001**: No document may claim a capability the code does not have. This is the failure specs
  083, 085 and 087 each closed one instance of; a public README is the largest surface for it.
- **NFR-002**: Nothing tracked is deleted to make the repository look tidier. The internal record is
  introduced, not hidden.

## Success Criteria *(mandatory)*

- **SC-001**: A repository-wide search for the old organisation returns zero results outside
  `PROGRESS.md`'s historical narrative.
- **SC-002**: The version named in `ROADMAP.md` equals `docs-app/src/version.ts`.
- **SC-003**: Every status-page entry cites a repository artifact, and a reviewer can open each one.
- **SC-004**: `npm run build` in `docs-app` succeeds and the reference regenerates with no drift.
- **SC-005**: The full test suite is unchanged — this spec changes no runtime behaviour except the
  plugin header URLs.

## Out of scope

- **Resolving the 24 bounded dependency exceptions.** They are governed by
  `.github/dependency-security-policy.json` and its review dates. Recorded here as a known open item;
  fixing them is dependency work, and doing it inside a documentation spec would hide a
  behaviour-changing upgrade inside a docs diff.
- **The Astro 7 migration**, which is one of those exceptions' blockers.
- **Enabling GitHub Pages.** The workflow builds and uploads the docs; publishing is a repository
  setting only the owner can change. Flagged, not pretended.
- **Deleting or trimming `PROGRESS.md` / `DECISIONS.md` / `specs/`.** They are public-safe and are
  the project's best evidence. NFR-002.
