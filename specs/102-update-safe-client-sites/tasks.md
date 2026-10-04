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

- [x] **T019** `FrameworkBaseline` value object and `FrameworkBaselineSource` interface in
      `packages/cli/src/Site/`.
- [x] **T020** `GitFrameworkBaselineSource` — the only git call in the package. Returns the
      release tag's commit when the tag exists, otherwise `HEAD` with the release labelled
      `(untagged)`, otherwise an empty commit.
- [x] **T021** Pest: `FrameworkBaselineSourceTest` — each of the three outcomes, against real
      repositories built in a temporary directory.
- [x] **T022** Stubs: `site/baseline`, `site/updating`, `site/workflow`. The workflow contains no
      `${{ … }}` expression. (FR-005, FR-006, FR-012)
- [x] **T023** `SiteScaffolder` — emits the record and the checklist with the governance set, and
      the workflow only when given a repository root. Still no WordPress and no git. It takes a
      `SiteRepository` (the baseline, and the root when there is one) as a fourth argument.
- [x] **T024** `MakeCommand` — passes the repository root when the site root is `<repo>/sites/<c>`;
      says so when the workflow was skipped, and when the commit could not be resolved. The
      decision lives in `SiteRepositoryResolver`, which is unit-tested; the command only prints.
      It reads a relative site directory against the working directory. (The note that stood
      here said this was for `--path=sites/acme`. That command cannot run — see T052.)
- [x] **T025** `CliServiceProvider` — bindings.
- [x] **T026** `site/AGENTS.md` and `site/CLAUDE.md` stubs — the rule that client work never edits
      a framework-owned path, what to do about a framework defect, and a corrected account of
      where the framework lives. `site/README.md` links the checklist. (FR-011, FR-012)
- [x] **T027** Starter: `phpunit.xml.dist` and `tests/bootstrap.php`, so the starter's Pest test
      runs from the client plugin's directory. The generated `.gitignore` now ignores
      `.phpunit.cache/`, which the first run creates there.
- [x] **T028** `SiteScaffoldValidator` — the record and the checklist are required governance.
- [x] **T029** Pest: `SiteScaffolderUpdateSafetyTest` and `SiteRepositoryResolverTest` — the new
      files, the skipped workflow, `--plugin-only` and `--theme-only` generating none of it, and
      a rendered workflow containing neither `{{` nor `}}`. The existing scaffolder suites pass
      unchanged.
- [x] **T030** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`, applied by hand.

      Verified by hand as well, with a real starter site generated into this checkout through
      the same classes `make:site` uses: the record named the `v0.42.0` tag's commit exactly;
      the workflow parsed as YAML with its three jobs; the starter's example test ran from the
      plugin directory the way the workflow runs it (1 passed); `lint:css` and `lint:js` exited
      0 and Jest listed no test under `sites/`. Full Pest afterwards: 1853 passed.

      Two forward references in the generated checklist are owed by Phase 6 and are not yet
      true: the documentation page it points to (T040) and the `Client impact` heading in
      `CHANGELOG.md` (T045).

## Phase 4 — CI (US1, US4)

- [x] **T031** Schedule guard on the jobs of `ci.yml`, `codeql.yml` and
      `dependency-security.yml`. (FR-004)
- [x] **T032** Repository guard on both jobs of `docs.yml`. (FR-004)
- [x] **T033** `tests/repository-ownership.test.js`: every job of every workflow with a schedule
      or a Pages deploy carries the guard, and the repository it names is the map's
      `frameworkRepository`. It read as nine unguarded jobs before the guards were added. The
      workflows are read as text: the only YAML parser in the tree is somebody else's dependency.
- [~] **T034** Not done, by decision. Linking `sites/*/*-site` and `sites/*/*-theme` into the
      provisioned WordPress would hand them to the `wp plugin activate --all` two steps later,
      so in a client repository the framework's own integration and browser suites would run
      with the client's plugin active — a different environment from the one they assert
      against, in the one place those suites are supposed to mean the same thing everywhere.
      Nothing in this spec needs CI's WordPress to load a client site: the generated workflow's
      tests are headless, and the job in T036 only needs WordPress in order to run `make:site`.
      `plan.md` is amended to match.
- [x] **T035** The generated client workflow's steps, exercised by T036: the check, a clean
      install and build inside the client theme with no root `node_modules`, and the client's
      tests run from the plugin directory. (FR-005, SC-006)
- [x] **T036** `ci.yml` job `client-site-layout` — generate a starter site, commit it in the
      runner, run the client's tests and build, the two root linters, the root Jest suite and
      `verify:framework` in the client role, then change one byte of `README.md` and require
      exit 1 with the file named. (FR-016, SC-002, SC-003)
- [x] **T037** Recorded in `DECISIONS.md` #230: making `client-site-layout` a required check is an
      owner setting in branch protection, not a file in this change.

## Phase 5 — The setup script (US5)

- [x] **T038** `scripts/setup-wordpress.ps1` — junction every client plugin and theme under
      `sites/`, in both the flat layout and the older nested one. No activation. (FR-014)

      Two things had to change beyond adding the links, and both were found by running it:

      - **`wp plugin activate --all` became an explicit list.** `--all` activates whatever is in
        the plugins directory, which now includes a linked client plugin — so a re-run would have
        switched on a plugin its owner had left off. The framework's plugins are activated by
        name instead. In a repository with no client site that is the same set as before; the
        one difference is that a plugin somebody installed into `./wp` by hand is no longer
        swept in.
      - **The Corex theme is no longer activated unconditionally.** The first version linked the
        client theme, printed "left as they were", and then switched the active theme back to
        Corex on every re-run. It now leaves a linked client theme active if it already is.

      `-Multisite` links nothing: that install is the multisite suite's fixture and mirrors CI.
- [x] **T039** Verified by hand, twice over.

      *Discovery and junctions, with no WordPress:* a throwaway harness loaded only the script's
      functions through the PowerShell parser and ran them against temporary directories — no
      `sites/`, an empty `sites/`, one flat site, two sites in mixed layouts, a junction exposing
      the client plugin's file, and a second run leaving one working junction and nothing nested
      inside the source. Seven checks, all passing; it failed first, on the function not existing.
      Read-only against the existing client repository, the same function names its one plugin
      and one theme.

      *The whole script, for real:* run in this checkout against a throwaway database, since
      dropped.

      | State before the run | After |
      |---|---|
      | No `sites/` | Corex theme and all 16 framework plugins active — as before |
      | One site, generated by the real `make:site` | plugin and theme linked, both inactive, activation commands printed |
      | That site's plugin and theme switched on | both still on; "Leaving the client theme 'acme-theme' active." |
      | Both switched off again | both still off; Corex theme active |
      | A second site in the nested layout | all four linked, none activated, 16 framework plugins active |

      That run was also `wp corex make:site` going through WP-CLI on Windows for the first time
      in this work: the record named the `v0.42.0` tag's commit and the workflow landed at the
      repository root.

## Phase 6 — Documentation and release notes (US2, US4, US6)

- [x] **T040** `docs/en/05-deployment/updating-a-client-site.md` — creating the client repository,
      and every step of FR-010 as a command or a named check with its expected result. (FR-010,
      FR-011) Every command on it was run before it was written: the repository creation against a
      scratch remote, and the update — a release merged with no conflict, the check failing against
      the old baseline and passing once the new one was recorded, and a conflict over a locally
      edited framework file resolved by taking the framework's version.
- [x] **T041** The Arabic page — translated, not a placeholder. (FR-010)
- [x] **T042** `docs/en/05-deployment/updates-and-distribution.md` — the boundary at
      `sites/<client>/`, and which of the two update routes applies. (FR-015) Its Arabic counterpart
      is a "translation pending" placeholder that points at the English source, as every Arabic page
      in that section is, so it needed no change.
- [x] **T043** `docs/en/04-team-workflow/client-site-workflow.md` — links the update page, states
      the rule, and says what `compliance:check` does and does not answer beside
      `verify:framework`. Arabic counterpart: a placeholder, as above.
- [x] **T044** `docs-app`: `guides/updating-a-client-site.md` (new), `guides/updates.md`,
      `guides/client-site.md`, and the sidebar entry.
- [x] **T045** `CHANGELOG.md` — spec 102's entries under Unreleased, with a `### Client impact`
      section stating this release's own. `CONTRIBUTING.md` — the rule, including that a release
      with none says so. (FR-013)
- [x] **T046** Hygiene suite: every release section from 0.43.0 onward carries the heading, and so
      does Unreleased as soon as anything is written there. Seen to fail on the entries before
      the section was added.
- [x] **T047** The docs site was built and `tests/docs-links.test.js` ran as two passes, not two
      skips. The new guide is in the build and its internal links carry the base path.
- [x] **T048** SC-004, checked without touching the existing client repository: in a scratch copy
      of it, this branch's versions of the four files it had edited — the hygiene suite,
      `.stylelintignore`, `eslint.config.js`, `jest.config.js` — were laid over its own. With the
      role resolved from its remote, the hygiene suite accepted all 653 tracked files under
      `sites/`; Jest listed none of the 47 test files tracked there; stylelint ignored the client
      theme's stylesheet and ESLint a client script. The copy was removed. (FR-017) One guard test
      failed there, correctly: it expects a `0.42.0` section that repository's older changelog does
      not have, and will once it takes this release.
- [x] **T049** `PROGRESS.md`, `DECISIONS.md` #230, `PROJECT-STATUS.md` and its generated docs-site
      copy. `README.md` gained a pointer to the update page.
- [x] **T051** `scripts/README.md`, "Reusing Corex for a new website" — no longer says "do not copy
      this repo to make a website". A client repository is a copy of it, by design; the section now
      says so and links the update page.
- [x] **T052** `make:site` takes the site directory as `--dir`. Watched through real WP-CLI before
      and after: before, `--dir` was ignored and the site landed in `./acme`; after, it lands in
      `sites/acme` with its workflow. The default form still works, and a directory outside
      `sites/` is generated with the warning that no workflow could be placed. Every documented use
      of the `--path` form is corrected — the README of the CLI package, three guides, the
      deployment index and the readiness report — and `client-site-layout` now runs the command
      exactly as documented. Recorded in the spec as FR-018.
- [x] **T050** Guard Gate — `docs-guard` on every page, applied by hand; the full Pest, Jest and
      lint runs. Results are in the pull request.
