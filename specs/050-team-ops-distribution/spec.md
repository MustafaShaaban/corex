# Feature Specification: Team ops & distribution

**Feature Branch**: `feature/050-team-ops-distribution`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Professional team ops + distribution. A Corex update **packaging** command that builds
the release ZIP + manifest the spec-034 self-update mechanism consumes. A **compliance check** that fails when a
client PR touches Corex framework folders without approval. Local **docs access** commands (`docs:serve`/`docs:sync`/
`docs:open`) so a team reads docs without a hosted site. Azure DevOps / App Service **deployment + branch-policy
docs** (per-site repo). The update *mechanism* already exists (spec 034); this adds the packaging + the ops layer."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Package a Corex release (Priority: P1) 🎯 MVP

A maintainer runs `wp corex package:update` and gets a **release ZIP** of the Corex framework (the framework
plugins/theme only — never client code, content, or uploads) plus an updated **`manifest.json`** (version, requires,
download URL, changelog) — exactly what the spec-034 self-update mechanism reads to offer an in-admin update. The
package contents are deterministic and contain no secret or client file.

**Why this priority**: The update *mechanism* ships (034) but there's no way to *produce* the package it consumes;
the packaging command closes the distribution loop.

**Independent Test**: Run the packaging plan for a version; it lists exactly the framework files to include (the
`corex-*` plugins + theme, excluding tests/specs/node_modules/client code) and produces a manifest matching the
spec-034 format; no client/secret file is included.

**Acceptance Scenarios**:

1. **Given** a target version, **When** `package:update` plans the package, **Then** it includes only the Corex
   framework plugins + theme (excludes tests, specs, node_modules, vendor dev, client/app code, uploads, secrets).
2. **Given** the package, **When** the manifest is produced, **Then** it matches the spec-034 manifest format
   (version, requires, requires_php, tested, download_url, sections/changelog).
3. **Given** the manifest, **When** inspected, **Then** it contains **no secret** and no client-specific value.

---

### User Story 2 - Block client PRs that touch the framework (Priority: P1)

A CI step runs `wp corex compliance:check` against a PR's changed files and **fails** if any change touches a
forbidden Corex framework path (the framework plugins/theme) — so a client-site team can't accidentally edit the
framework — while allowing the client plugin/theme/docs/specs. The failure names the offending files.

**Why this priority**: This is what makes the client/framework boundary (spec 049) *enforced*, not just documented;
without it, the boundary is advisory.

**Independent Test**: Given a list of changed files, the check passes when they're all under the client plugin/theme/
docs/specs and fails (naming them) when any is under a Corex framework folder.

**Acceptance Scenarios**:

1. **Given** changed files all under the client plugin/theme/docs/specs, **When** `compliance:check` runs, **Then**
   it passes.
2. **Given** a change under a Corex framework folder, **When** it runs, **Then** it **fails** (non-zero exit) and
   names the offending file(s).
3. **Given** an explicit override (a flag/marker for an approved framework change), **When** present, **Then** the
   check allows it — the boundary is enforced by default, overridable on purpose.

---

### User Story 3 - Read the docs locally (Priority: P2)

A developer with no hosted docs site reads the Corex docs from their installed copy: `wp corex docs:serve` serves
the docs app locally, `wp corex docs:sync` copies the installed version's docs into a local `.corex/docs/`
(git-ignored), and `wp corex docs:open` opens them — so a team reads/annotates docs offline without hosted
infrastructure.

**Why this priority**: The brief's "docs access without a hosted site." P2 because the docs *exist* (spec 022/028);
this is convenience access.

**Independent Test**: `docs:sync` copies the docs into `.corex/docs/` (git-ignored); `docs:serve` reports how to
view them locally; both work from the installed files with no hosted dependency.

**Acceptance Scenarios**:

1. **Given** the installed docs, **When** `docs:sync` runs, **Then** they are copied into `.corex/docs/` (which the
   generated client `.gitignore` already excludes).
2. **Given** the docs app, **When** `docs:serve` runs, **Then** it reports how to view the docs locally (the run
   command / URL), needing no hosted infrastructure.
3. **Given** no docs app present, **When** the commands run, **Then** they degrade with a clear message, not a fatal.

---

### User Story 4 - Documented Azure deployment & branch policy (Priority: P2)

A team reads a documented deployment model: a **per-site Azure DevOps repo**, **App Service + Azure Database for
MySQL**, deploy from `develop` (staging) / `main` (production) or tags, **branch policies** (protect main/develop,
require PR review + a green pipeline + the compliance check), secrets via App Service settings (no `.env` in repo),
uploads not committed, and a rollback note — building on the spec-028 handbook.

**Why this priority**: The professional team needs the deployment + branch-policy model written down. P2 because it
is documentation over the packaging + compliance primitives (US1/US2).

**Independent Test**: The docs describe a per-site Azure DevOps repo + App Service deployment, the branch policies
(PR review + pipeline + compliance), secrets handling, and rollback — accurate to the shipped commands.

**Acceptance Scenarios**:

1. **Given** the deployment docs, **When** followed, **Then** a team can set up a per-site Azure DevOps repo + App
   Service deployment from `develop`/`main`/tags, with branch policies requiring review + a green pipeline + the
   compliance check.
2. **Given** the docs, **When** read, **Then** secrets (App Service settings, no committed `.env`), uploads (not
   committed), and rollback are covered.

---

### Edge Cases

- `package:update` on a tree with uncommitted client files → the package still excludes them (it includes only the
  known framework paths, never a wildcard of the working tree).
- `compliance:check` with no changed files → passes (nothing to violate).
- `compliance:check` path matching MUST not be fooled by a client path that merely contains a framework name as a
  substring (match by path prefix, not substring).
- `docs:sync` when `.corex/docs/` exists → refreshed, not duplicated.
- The manifest's `download_url` is a configured value, never a secret; an unset URL yields a clear placeholder, not
  a broken manifest.
- No command writes a secret into a package, manifest, or synced docs.

## Requirements *(mandatory)*

### Functional Requirements

**Packaging (US1)**

- **FR-001**: `wp corex package:update <version>` MUST plan a release package containing **only** the Corex
  framework plugins + theme, excluding tests, specs, node_modules, dev vendor, client/app code, uploads, and
  secrets, and produce a **`manifest.json`** in the spec-034 format (version, requires, requires_php, tested,
  download_url, sections/changelog).
- **FR-002**: The packaging plan (which paths are included/excluded) MUST be **pure** (the file list computed from
  rules, not the live tree), so it is unit-tested; writing the ZIP/manifest is a thin boundary.
- **FR-003**: The package + manifest MUST contain **no secret** and **no client-specific** value.

**Compliance (US2)**

- **FR-004**: `wp corex compliance:check` MUST evaluate a list of changed files and **fail (non-zero)** if any is
  under a forbidden Corex framework path, **passing** client plugin/theme/docs/specs changes; it MUST **name** the
  offending files.
- **FR-005**: Path matching MUST be by **path prefix** (not substring) so a client path containing a framework name
  is not a false positive; an explicit, documented **override** MUST allow an approved framework change.
- **FR-006**: The classification (forbidden vs allowed) MUST be **pure** (the changed-file list injected), so it is
  unit-tested; reading the diff (e.g. from git) is a thin boundary.

**Docs access (US3)**

- **FR-007**: `wp corex docs:sync` MUST copy the installed docs into `.corex/docs/` (git-ignored); `docs:serve`
  MUST report how to view them locally; `docs:open` MUST open them — all from the installed files, no hosted
  dependency, degrading clearly when the docs app is absent.

**Deployment docs (US4)**

- **FR-008**: The deployment model MUST be **documented** — a per-site Azure DevOps repo, App Service + Azure
  Database for MySQL, deploy from `develop`/`main`/tags, branch policies (PR review + green pipeline + the
  compliance check), secrets via App Service settings (no committed `.env`), uploads not committed, and rollback —
  building on the spec-028 handbook.

**Cross-cutting**

- **FR-009**: All commands MUST be WP-CLI-gated (the framework runs without WP-CLI); the pure cores (package plan,
  compliance classification) MUST be headless-testable. No new hard dependency. No secret in any output.

### Key Entities *(include if feature involves data)*

- **Release package plan**: the set of framework paths to include + the exclusion rules + the spec-034 manifest —
  pure, no secret, no client file.
- **Update manifest**: version, requires, requires_php, tested, download_url, sections/changelog (spec-034 format).
- **Compliance result**: pass/fail + the offending files, from a changed-file list classified by path prefix
  (forbidden framework vs allowed client/docs/specs), with an override.
- **Local docs**: the installed docs copied to `.corex/docs/` (git-ignored) + the serve/open affordances.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A maintainer produces a Corex release package + a spec-034-format manifest with **one command**,
  containing **only** framework files — **zero** client/secret files.
- **SC-002**: A CI compliance check **fails** any PR touching a Corex framework folder (naming the files) and
  **passes** client plugin/theme/docs/specs changes — **100%** correct by path prefix, overridable on purpose.
- **SC-003**: A developer reads the Corex docs **locally** (synced to a git-ignored `.corex/docs/`) with **no**
  hosted docs site.
- **SC-004**: A team can follow the documented Azure DevOps per-site repo + App Service deployment + branch policies
  (review + pipeline + compliance) end-to-end.
- **SC-005**: No command emits a secret into a package, manifest, or synced docs; every command is WP-CLI-gated and
  the pure cores are unit-tested headlessly.

## Assumptions

- Builds on and **reuses** the spec-034 self-update mechanism + manifest format, the spec-036 `wp corex version`
  release tooling, the spec-049 client/framework boundary (the same framework paths the compliance check forbids),
  the spec-022/028 docs, and the spec-003 pure-core + gated-CLI pattern — this feature adds the packaging plan, the
  compliance classifier, the docs commands, and the deployment docs; it does not re-spec them.
- The pure cores (package plan, compliance classification) are headless-testable; the ZIP/manifest write, the git
  diff read, and the docs copy/serve are thin WP-CLI-gated boundaries.
- The forbidden framework paths are the `corex-*` plugins + the Corex theme; allowed paths are the client plugin/
  theme + `docs/` + `specs/` (the spec-049 boundary).
- The manifest `download_url` is a configured value (a Corex config/option), never a secret.
- Out of scope (explicitly): a hosted release server / CI/CD provider automation beyond the documented pipeline, a
  Docker base-image distribution, and the multisite/CDN deployment variants (later increments). Actually building
  the ZIP on a live tree + pushing a GitHub Release is the boundary the command performs; this spec's tested core is
  the plan + the classifier.
- Live behavior (a real ZIP, a real git diff, serving the docs) depends on the environment; per the environment
  gate, the pure plan/classifier are unit-tested headlessly and the live commands run where the tools exist.
