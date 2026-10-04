# Implementation Plan: A client site the framework can be updated underneath

**Spec**: [spec.md](spec.md) · **Branch**: `spec/102-update-safe-client-sites`

## Constitution Check

| Principle | How this change satisfies it |
|---|---|
| I–II, V–IX | Not engaged. Nothing here runs in a page load: the change is repository tooling, the site generator, CI, the setup script and documentation. No theme logic, no asset, no route, no optional dependency. |
| III thin controllers / fat services | `MakeCommand` stays a printer. What the generator newly writes is decided in `SiteScaffolder`; where the framework baseline comes from is a separate source behind an interface. |
| IV everything injected | `SiteScaffolder` receives a `FrameworkBaseline` value. It does not run git, and it stays constructible with no WordPress and no repository — which is what its existing unit tests rely on. |
| X spec is source of truth | Spec 102 precedes this plan. FR numbers below are the spec's. |
| Guard Gate / Definition of Done | `clean-code-guard` on the scripts and PHP, `wp-guard` on the CLI change, `test-guard` on the new suites, `docs-guard` on every page. EN and AR pages land in the same change. |

## The architecture, in one sentence

**One file says which paths are the client's; everything else is the framework's, and every tool
asks that file instead of keeping its own list.**

## Why the complement, and not a list of framework directories

The existing client repository verifies its updates with
`git diff upstream/main --name-only -- addons plugins packages theme tests`. That is a list of
framework directories, and a list is wrong in the direction that matters: anything not on it is
unchecked. Neither the root nor `scripts/` is on it, which is how a 17 MB archive and a scratch
script were committed during one update and found a release later.

Defining **client-owned** by pattern and **framework-owned** as *everything else* inverts that. A
new framework directory is covered the day it is added, and a stray root file is drift by
definition. The client-owned set is two patterns and is expected to stay that small:

```
sites/**
.github/workflows/site-*.yml
```

The second exists because a workflow only runs from `.github/workflows/`. It is the single place a
client must own a file outside `sites/`, so the file name carries the ownership.

## Decisions

Each is a choice between alternatives that were actually on the table.

**1. The map lives at `.github/repository-ownership.json`.** Beside
`.github/dependency-security-policy.json`, which is the precedent: a policy file read by the script
that enforces it. JSON, because JavaScript tooling and PHP both have to be able to read it. *Rejected:* a
constant in a script (PHP cannot import it), and a root dotfile (the root is deliberately kept small).

**2. "Is this the framework's own repository?" is resolved in a fixed order, and defaults to yes.**
An explicit `COREX_REPOSITORY_ROLE`, then `GITHUB_REPOSITORY` compared with the map's
`frameworkRepository`, then the `origin` remote, then *framework*. The default is the strict one:
a repository that cannot say what it is gets the rule that forbids `sites/`. CI is the authority,
since that is where the hygiene suite is a required check. *Rejected:* "a baseline record exists,
therefore client" — it would let a client site be committed to the framework by also committing
its record.

**3. The unmodified-framework check is a Node script, `npm run verify:framework`, not a
`wp corex` command.** It needs git and nothing else. A WP-CLI command needs a booted WordPress and
a database, which a client's CI would have to provision just to run a file comparison. It follows
the shape already in the repository: a pure module (`scripts/framework-baseline.mjs`), a runner
that owns git and the exit code (`scripts/verify-framework.mjs`), and a Jest suite for the pure
part — as `dependency-security-policy.mjs` / `verify-dependency-security.mjs` do. Node built-ins
only, so it runs with no `npm ci`.

**4. The check compares against a commit, and treats the release name as a label.** The baseline
record holds both. The commit is what is diffed; if the release tag exists and points elsewhere,
that is reported as a warning. This is what lets a client record a commit past a tag on purpose
(the spec's last edge case) without the check lying about it.

**5. A recorded exception that no longer differs is a failure.** The procedure says to delete a
local patch once upstream has it. An exception list nobody is forced to prune becomes the list of
things nobody remembers the reason for — the same reasoning the dependency policy already applies
to stale exceptions.

**6. `make:site` records the baseline through an injected source.** No command in `packages/cli`
shells out today. `GitFrameworkBaselineSource` is the one place that runs `git`, behind
`FrameworkBaselineSource`. When git cannot answer (no repository, a zip download) the site is still
generated, the record is written with the release and an empty commit, and the command says so —
after which `verify:framework` fails with the FR-009 message until a baseline is recorded. A
generator that refuses to generate because git is missing would be the worse failure.

**7. The generated workflow contains no `${{ … }}` expression.** `StubRenderer` treats any
`{{ word }}` left in its output as an unresolved placeholder and throws, and its pattern matches
`${{ github.repository }}`. Literal files bypass the renderer but cannot carry the client slug.
The workflow therefore uses shell conditionals (`if [ -f package.json ]`) where an expression
would normally go. *Rejected:* teaching the renderer an escape syntax, which changes a contract
every other stub depends on, for one file.

**8. The client's build runs with no root `npm ci`.** That is the whole of SC-006. The regression on
record happened because the client build resolved a package out of the framework's hoisted
`node_modules`. Installing only inside the client's own directory makes an undeclared import fail
on the day it is written.

**9. Framework CI keeps running on a client's pull requests; only scheduled runs and the docs
deploy are switched off.** The spec's FR-004 names those two. The pull-request suites stay because
they are the "framework suites pass" step of the update procedure, already automated. The guard is
`github.repository == 'MustafaShaaban/corex'` in the workflow, which cannot read a JSON file — so a
test asserts every guarded workflow names the same repository the map does.

**10. `wp corex compliance:check` is left alone.** It answers a different question — did this list
of file names touch a framework folder — and needs no git. The documentation will point from it to
`verify:framework`. Rewiring it onto the map is a change nobody asked for.

## What the check does

```
role = framework            → "framework repository: nothing to compare", exit 0
no sites/*/corex-baseline.json            → FAIL  no baseline recorded
record unreadable / commit empty          → FAIL  names the file and the field
commit not in this repository             → FAIL  says to fetch full history and the framework remote
two records naming different commits      → FAIL  names both

changed  = paths differing between the baseline commit and the working tree, plus untracked files
drift    = changed − client-owned
for each drift path:   in exceptions → EXCEPTION <path> <reason>     else → DRIFT <path>
for each exception not in drift      → STALE <path>

exit 1 if any DRIFT, STALE or FAIL; otherwise 0.   --json for CI, --record <ref> to write a baseline.
```

## What `make:site` newly generates

| File | Requirement | Note |
|---|---|---|
| `sites/<c>/corex-baseline.json` | FR-006 | release, commit, date, `exceptions: []` |
| `sites/<c>/UPDATING-COREX.md` | FR-012 | the client's copy of the checklist, with its own slug in the commands |
| `.github/workflows/site-<c>.yml` | FR-005 | only when the site root is `<repo>/sites/<c>`; otherwise skipped and reported |
| `AGENTS.md` / `CLAUDE.md` (changed) | FR-012 | the rule, and a corrected description of where the framework lives |
| `<c>-site/phpunit.xml.dist`, `tests/bootstrap.php` (`--starter`) | FR-005 | the starter ships a Pest test and no configuration that runs it |

The workflow has three jobs: the unmodified-framework check (full-history checkout, no install);
a clean install and build inside each client directory that has a `package.json`; and the client's
own PHP and JavaScript tests where they exist. `--plugin-only` and `--theme-only` generate none of
this, as they generate no governance today.

## Files

```
.github/repository-ownership.json                  NEW   frameworkRepository, clientOwned, localOnly
.github/workflows/ci.yml                           schedule guard; NEW job "client-site-layout"
.github/workflows/codeql.yml                       schedule guard
.github/workflows/dependency-security.yml          schedule guard
.github/workflows/docs.yml                         repository guard on build and deploy
.github/actions/provision-wordpress/action.yml     unchanged — see tasks.md T034 for why the link was dropped

scripts/repository-ownership.mjs                   NEW   pure: load map, match, classify, resolve role
scripts/framework-baseline.mjs                     NEW   pure: validate a record, evaluate drift
scripts/verify-framework.mjs                       NEW   runner: git, output, exit code, --record
scripts/setup-wordpress.ps1                        junction client plugins and themes; no activation
scripts/README.md                                  the two new scripts
package.json                                       "verify:framework"

.stylelintignore  eslint.config.js  jest.config.js client-owned and local-only paths
.prettierignore                                    NEW   same list — only if step 1 confirms `npm run format` reaches sites/

tests/repo-hygiene.test.js                         role-aware sites/ rule; ignore files and workflow
                                                   guards agree with the map; "Client impact" heading
tests/repository-ownership.test.js                 NEW   matching and role resolution
tests/framework-baseline.test.js                   NEW   every row of "What the check does"
tests/verify-framework.test.js                     NEW   the runner against a throwaway git repository

packages/cli/src/Site/FrameworkBaseline.php        NEW   value object
packages/cli/src/Site/FrameworkBaselineSource.php  NEW   interface
packages/cli/src/Site/GitFrameworkBaselineSource.php   NEW   the only git call
packages/cli/src/Site/SiteScaffolder.php           emits the record, checklist, workflow, test config
packages/cli/src/Site/SiteScaffoldValidator.php    requires the record and the checklist
packages/cli/src/Commands/MakeCommand.php          passes the repository root; reports a skipped workflow
packages/cli/src/CliServiceProvider.php            bindings
packages/cli/stubs/site/                           baseline, updating, workflow NEW; AGENTS, CLAUDE, README changed
packages/cli/stubs/starter/                        phpunit-xml, test-bootstrap NEW
tests/Unit/Cli/SiteScaffolder*Test.php  SiteScaffoldValidationTest.php  + FrameworkBaselineSourceTest.php

docs/en/05-deployment/updating-a-client-site.md    NEW   create the client repository; take a release
docs/en/05-deployment/updates-and-distribution.md  boundary at sites/<client>/; which route applies
docs/en/04-team-workflow/client-site-workflow.md   links the two
docs/ar/…                                          the same three, translated — not placeholders
docs-app/src/content/docs/guides/                  updating-a-client-site.md NEW; updates.md, client-site.md
docs-app/astro.config.*                            sidebar entry
CHANGELOG.md  CONTRIBUTING.md                      the "Client impact" heading and its rule
PROGRESS.md  DECISIONS.md  PROJECT-STATUS.md       at the end
```

## Order of work

Each step leaves the suite green and is useful without the next.

1. **The map and the tools that read it** (US1 · FR-001–003). Map, `repository-ownership.mjs`, the
   four ignore files, the role-aware hygiene rule, their tests.
2. **The check** (US3 · FR-007–009). `framework-baseline.mjs`, the runner, both suites.
3. **The generator** (US1, US2, US4 · FR-005, 006, 012). Baseline source, new stubs, validator,
   Pest suites.
4. **CI** (US1, US4 · FR-004, FR-016). Workflow guards and the proof job.
5. **The setup script** (US5 · FR-014).
6. **Documentation and release notes** (US2, US4, US6 · FR-010, 011, 013, 015).

## How it is proved

**In Jest, on every pull request.** The hygiene rule is exercised in both roles against a synthetic
file list, so one run proves a client site is accepted in a client repository and rejected in the
framework's. The check's pure module is tested row by row. The runner is tested against a real git
repository built in a temporary directory: an untouched tree exits 0; one changed character in one
framework file exits 1 and names that file (SC-003); a recorded exception is reported and passes;
a stale one fails; a missing baseline fails.

**In CI, against a real generated site** (FR-016). A new job in `ci.yml`, `client-site-layout`:

```
provision WordPress
wp corex make:site Acme --path=sites/acme --starter
commit the result in the runner's workspace
COREX_REPOSITORY_ROLE=client  npm run lint:css && npm run lint:js && npm run test:js
COREX_REPOSITORY_ROLE=client  npm run verify:framework          → exit 0
change one byte of README.md
COREX_REPOSITORY_ROLE=client  npm run verify:framework          → exit 1, names README.md
build the generated theme from a clean install, with no root node_modules
```

That is SC-002 and SC-003 against the generator's real output rather than a fixture, which matters
because the generator is the thing that changes.

**By hand, once** (SC-001, SC-004, SC-005). SC-001 needs two releases, so it is verified when the
release after this one exists; until then the Jest runner suite stands in for it with two commits.
SC-004 is checked in the existing client repository by reverting each of its four local edits to
this branch's version and running its checks, with nothing committed there (FR-017). SC-005 is the
documentation being followed start to finish on a new client site — the next one is the test.

## Risks, and what was done about each

- **The repository identity is written in two kinds of place** — the map, and `if:` conditions in
  four workflows. A hygiene test fails if they disagree. If the framework repository is ever
  renamed, that test is what says where.
- **The check needs the baseline commit, and CI checkouts are shallow by default.** The generated
  workflow checks out full history, and the failure message for a missing commit says exactly that.
- **`client-site-layout` is a seventh CI job, and it provisions WordPress as the integration job
  does.** It is not a required check until the owner adds it to branch protection; that is an
  owner setting, not a file in this change.
- **The existing client repository will conflict once** on the four files it edited, when it takes
  this release. The resolution is to take the framework's version of each. That is SC-004, and it
  is the last time.
- **A site generated outside `<repo>/sites/`** gets no workflow, because the repository root cannot
  be inferred. The command says so rather than guessing a location.

## Deliberately not done

- `.gitignore` gains nothing client-specific. Client material — design handoffs included — belongs
  under `sites/<client>/`, which has its own generated `.gitignore`. The update page says so.
- `wp-env.json` is a static list and cannot discover client sites. The page documents the two lines
  to add in `.wp-env.override.json`, which is already git-ignored.
- No Spec Kit agent-context edit to `CLAUDE.md`. Its marker block still points at spec 068; a
  second spec branch editing the same lines would conflict with spec 101's, and `CLAUDE.md` must
  not diverge from `AGENTS.md`.
