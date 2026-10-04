# Tasks: A client site the framework can be updated underneath

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md)

Six phases, in the plan's order. Each leaves every suite green and is useful without the next.

## Phase 1 — The map and the tools that read it (US1)

- [x] **T001** `.github/repository-ownership.json` — `frameworkRepository`, `clientOwned`
      (`sites/**`, `.github/workflows/site-*.yml`) and `localOnly` (directories that exist on a
      developer's machine and never in CI). (FR-001)
- [x] **T002** `scripts/repository-ownership.mjs` — pure: load the map, match a path against a
      pattern (`**` and `*` only), `isClientOwned(path)`, and `resolveRole({ env, originUrl })` in
      the plan's order: explicit override, `GITHUB_REPOSITORY`, `origin`, then *framework*.
- [x] **T003** Jest: `tests/repository-ownership.test.js` — matching (a file under `sites/`, the
      client workflow, `ci.yml`, a file merely *named* `sites`), and every branch of role
      resolution including both remote URL forms and an invalid override.
- [x] **T004** `tests/repo-hygiene.test.js` — the offender computation takes `(files, role)`. The
      `sites/` rule applies in the framework role only; a secret, an archive, build output and a
      dependency directory are still rejected *inside* `sites/` in the client role. Both roles are
      asserted against a synthetic list, and the live run uses the resolved role. (FR-002)
- [x] **T005** `eslint.config.js` — ignores built from the map's client-owned and local-only paths.
      (FR-003)
- [x] **T006** `jest.config.js` — `testPathIgnorePatterns` built from the same, anchored to this
      directory. (FR-003) **Not** through `<rootDir>` — see the finding under T009.
- [x] **T007** `.stylelintignore` — `sites/**`. A static file cannot read the map, so
      `tests/repository-ownership.test.js` asserts the entry is present for every client-owned
      directory. (FR-003)
- [x] **T008** Establish whether `npm run format` reaches `sites/`. **It did.** `wp-scripts format`
      uses a project `.prettierignore` when one exists and otherwise its own, which lists only
      `build` and `vendor`; under that default prettier reported a file under `sites/`. A project
      `.prettierignore` now exists, repeats those defaults (a project file replaces them rather
      than extending them), and is held to the map by the same test. (FR-003)
- [x] **T009** Verified by hand with one stylesheet, one script and one test that each fail, placed
      both under a throwaway `sites/acme/acme-theme/` and under a control directory inside
      `tests/`. `lint:css` and `lint:js` reported the control files and nothing from `sites/`.
      **`test:js` ran the failing test under `sites/`** — while the unit test for exactly that
      was green.

      The cause is not in the map. On Windows, Jest rewrites path separators inside each ignore
      pattern and leaves a backslash that precedes a dot alone, taking it for an escape. A
      checkout whose own path holds a dot-directory therefore turns every `<rootDir>` pattern
      into one that matches nothing, and this work is being done in exactly such a checkout — an
      agent session worktree under `.claude/worktrees/`. The first unit test substituted
      `<rootDir>` itself and so could not see it. The patterns are now anchored to an escaped,
      forward-slashed absolute path, and the test asks Jest for its resolved configuration
      instead of predicting it. After the change Jest listed the control test and not the one
      under `sites/`.

      The older `<rootDir>/wp/`, `<rootDir>/dist/` and `<rootDir>/docs-app/` entries have the
      same weakness in such a checkout. They are left as they are: they belong to tooling this
      spec does not own, and a worktree has none of those directories to mis-sweep.

      Full runs on the clean tree afterwards: `lint:css` exit 0, `lint:js` exit 0, Jest 55 suites,
      486 passed, 2 skipped (the docs link check, which skips when the docs site is not built).
- [x] **T010** Guard Gate — `clean-code-guard` and `test-guard`, applied by hand against the diff.
      Three changes came out of it: the explicit-role check moved out of `resolveRole` into its
      own function, an unused `version` field left the map, and a defensive null-coalesce left
      `repositoryFromRemoteUrl`, whose only caller always passes a string.

## Phase 2 — The check (US3)

- [x] **T011** `scripts/framework-baseline.mjs` — pure: validate a baseline record (release,
      commit, exceptions with path, reason and upstream reference) and evaluate drift from a list
      of changed paths, returning DRIFT, EXCEPTION and STALE entries. (FR-007, FR-008)
- [x] **T012** Jest: `tests/framework-baseline.test.js` — one case per row of the plan's "What the
      check does", including an exception with no reason and an exception that no longer differs.
- [x] **T013** `scripts/verify-framework.mjs` — the runner: role short-circuit, find the records,
      confirm the commit exists, collect changed and untracked paths, print one line per finding,
      exit 1 on any DRIFT, STALE or FAIL. `--json` for CI. Node built-ins only. (FR-007, FR-009)
- [x] **T014** `--record <ref>` — resolve the ref, write release and commit into every record,
      refuse a ref that does not resolve. Also refuses `--record` with no release named, and
      decodes every record before writing any, so one unreadable file changes none of them.
- [x] **T015** Warn, without failing, when the recorded release tag exists and points at a
      different commit from the one recorded.
- [x] **T016** Jest: `tests/verify-framework.test.js` — against a git repository built in a
      temporary directory: untouched tree exits 0; one changed character exits 1 and names the
      file; a recorded exception passes and is reported; a stale one fails; no baseline fails; a
      commit absent from the repository fails; two records that disagree fail. (SC-003) Also: a
      committed change, an added root file and a deleted framework file are each drift; client
      changes are not; the framework role passes with nothing to compare. 18 cases, nothing mocked.
- [x] **T017** `package.json` `verify:framework`; `scripts/README.md` describes the script, its
      output lines, the record format and the two pure modules.
- [x] **T018** Guard Gate — `clean-code-guard`, `test-guard` and `docs-guard`, applied by hand. The
      runner was restructured once after the first green: results always carry their lists, which
      removed a run of defensive fallbacks from the reporter, and the reporter split in two.
      Every claim in the `scripts/README.md` section was checked against the script's behaviour
      in the test run.

## Phase 3 — The generator (US1, US2, US4)

- [ ] **T019** `FrameworkBaseline` value object and `FrameworkBaselineSource` interface in
      `packages/cli/src/Site/`.
- [ ] **T020** `GitFrameworkBaselineSource` — the only git call in the package. Returns the
      release tag's commit when the tag exists, otherwise `HEAD`, otherwise an empty commit.
- [ ] **T021** Pest: `FrameworkBaselineSourceTest` — each of the three outcomes.
- [ ] **T022** Stubs: `site/baseline`, `site/updating`, `site/workflow`. The workflow contains no
      `${{ … }}` expression. (FR-005, FR-006, FR-012)
- [ ] **T023** `SiteScaffolder` — emits the record and the checklist with the governance set, and
      the workflow only when given a repository root. Still no WordPress and no git.
- [ ] **T024** `MakeCommand` — passes the repository root when the site root is `<repo>/sites/<c>`;
      says so when the workflow was skipped, and when the commit could not be resolved.
- [ ] **T025** `CliServiceProvider` — bindings.
- [ ] **T026** `site/AGENTS.md` and `site/CLAUDE.md` stubs — the rule that client work never edits
      a framework-owned path, what to do about a framework defect, and a corrected account of
      where the framework lives. `site/README.md` links the checklist. (FR-011, FR-012)
- [ ] **T027** Starter: `phpunit.xml.dist` and `tests/bootstrap.php`, so the starter's Pest test
      runs from the client plugin's directory.
- [ ] **T028** `SiteScaffoldValidator` — the record and the checklist are required governance.
- [ ] **T029** Pest: `SiteScaffolderTest`, `SiteScaffolderStarterTest`, `SiteScaffoldValidationTest`
      — the new files, the skipped workflow, `--plugin-only` and `--theme-only` generating none of
      it, and a rendered workflow containing neither `{{` nor `${{`.
- [ ] **T030** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`.

## Phase 4 — CI (US1, US4)

- [ ] **T031** Schedule guard on the jobs of `ci.yml`, `codeql.yml` and
      `dependency-security.yml`. (FR-004)
- [ ] **T032** Repository guard on both jobs of `docs.yml`. (FR-004)
- [ ] **T033** Hygiene suite: every workflow with a schedule or a Pages deploy carries the guard,
      and the repository it names is the map's `frameworkRepository`.
- [ ] **T034** `.github/actions/provision-wordpress/action.yml` — link `sites/*/*-site` and
      `sites/*/*-theme`.
- [ ] **T035** The generated client workflow, exercised: full-history checkout, the check, a clean
      install and build inside each client directory, the client's tests. (FR-005, SC-006)
- [ ] **T036** `ci.yml` job `client-site-layout` — generate a starter site, commit it in the
      runner, run the three root checks and `verify:framework` in the client role, change one byte
      of a framework file and require exit 1, then build the generated theme with no root
      `node_modules`. (FR-016, SC-002, SC-003)
- [ ] **T037** Record in `DECISIONS.md` that making `client-site-layout` a required check is an
      owner setting in branch protection, not a file in this change.

## Phase 5 — The setup script (US5)

- [ ] **T038** `scripts/setup-wordpress.ps1` — junction every client plugin and theme under
      `sites/`, in both the flat layout and the older nested one. No activation. (FR-014)
- [ ] **T039** Verify by hand, and record here: a repository with one client site, with two, and
      with none — the last behaving exactly as before.

## Phase 6 — Documentation and release notes (US2, US4, US6)

- [ ] **T040** `docs/en/05-deployment/updating-a-client-site.md` — creating the client repository,
      and every step of FR-010 as a command or a named check with its expected result. (FR-010,
      FR-011)
- [ ] **T041** The Arabic page — translated, not a placeholder. (FR-010)
- [ ] **T042** `docs/en/05-deployment/updates-and-distribution.md` and its Arabic counterpart —
      the boundary at `sites/<client>/`, and which route applies to a repository under version
      control. (FR-015)
- [ ] **T043** `docs/en/04-team-workflow/client-site-workflow.md` and its Arabic counterpart —
      link both pages; point from `compliance:check` to `verify:framework`.
- [ ] **T044** `docs-app`: `guides/updating-a-client-site.md`, `guides/updates.md`,
      `guides/client-site.md`, and the sidebar entry.
- [ ] **T045** `CHANGELOG.md` — a `### Client impact` section under Unreleased, stating this
      release's own. `CONTRIBUTING.md` — the rule, including that a release with none says so.
      (FR-013)
- [ ] **T046** Hygiene suite: every release section from 0.43.0 onward carries the heading.
- [ ] **T047** Build the docs site and run `tests/docs-links.test.js` as a pass, not a skip.
- [ ] **T048** SC-004, read-only: in the existing client repository, confirm each of its four local
      edits is unnecessary against this branch. Commit nothing there. (FR-017)
- [ ] **T049** `PROGRESS.md`, `DECISIONS.md`, `PROJECT-STATUS.md` and its generated docs-site copy.
- [ ] **T051** `scripts/README.md`, "Reusing Corex for a new website" — it says "Do **not** copy
      this repo to make a website", which is the opposite of the client-repository model this
      spec documents and the existing client repository uses. Found while adding the
      `verify-framework.mjs` section beside it. Reconcile it with T040.
- [ ] **T050** Guard Gate — `docs-guard` on every page; the full Pest, Jest and lint runs.
