# Contributing to Corex

Corex is built spec-first and guard-gated. Read `specs/constitution.md` and
`docs/internal/COREX-WORKING-GUIDE.md` before contributing.

**This file holds the mechanics** — branching, commit messages, versioning, how to run each suite,
what the guard gate runs. The rules those mechanics serve are in the constitution, and the precedence
between all four instruction files is stated once in [`AGENTS.md`](AGENTS.md). Where a rule and its
command disagree, the rule wins and the command is the bug.

## Branching model (git-flow-lite)

Per `docs/internal/COREX-FRAMEWORK.md` §19:

| Branch | Purpose |
|---|---|
| `main` | The only long-lived branch. Releases are tagged from it. |
| `spec/NNN-slug` | Short-lived work for one spec (e.g. `spec/087-guides-support-and-admin-controls`). |
| `hotfix/slug` | Urgent fix off `main`, merged straight back. |

- Branch from `main`, open a PR back into `main` with a green pipeline, squash-merge.
- Tag a release from `main` when a set of specs is ready to ship, not on every merge.
- Environments deploy from tags, never branches.

> **This replaced a `develop` integration branch.** That branch no longer exists, and the
> `feature/NNN-slug` naming went with it — work is named for the spec it implements, because the
> spec is the durable artifact and the branch is not.

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `docs:`,
`chore:`, `test:`, `refactor:`, `perf:`, optionally scoped (`feat(forms): …`). This keeps an
automated changelog and version bump possible. End commit bodies with the project's
co-author trailer.

## Versioning

[Semantic Versioning](https://semver.org/). Pre-1.0 the public API may still move
(`0.MINOR.PATCH`). `v1.0.0` is reserved for "usable for a real client website end-to-end".

## Release notes: the Client impact section

Every release section in `CHANGELOG.md` carries a `### Client impact` heading, from 0.43.0 on, and so
does `[Unreleased]` as soon as anything is written under it. `tests/repo-hygiene.test.js` enforces both.

It lists what in the release can change how a **client site** behaves or builds — changed defaults,
changed validation, changed markup or asset handles, removed or renamed commands and options, and
dependencies that were removed or moved a major version. A client repository takes a release by merging
it, and a clean merge with green suites says nothing about any of those: two releases broke a client that
way before this rule existed (spec 102).

A release with nothing to list says so in one line under the heading. An absent heading reads exactly
like "nobody checked", which is why it is not allowed to be absent.

From the release after 0.47.0, the section also names each running client site, **Muva**, **Perego** and
**Mizzey**, with "nothing changes", what changes, or the upgrade step; or it says in one sentence that no
client site is affected. The same test fails a section that does neither (spec 116, US-NFR-08).

## Definition of Done

A change ships only when all hold (constitution "Definition of Done"):

- [ ] Follows the constitution.
- [ ] Unit tests written and green (Pest); the integration suite green where it applies.
- [ ] The relevant **guard gate** ran clean on the diff (see below).
- [ ] WCAG 2.2 AA for any UI; strings translation-ready (i18n); RTL verified.
- [ ] Docs updated in the same change.
- [ ] `PROGRESS.md` updated; non-trivial choices logged in `DECISIONS.md`.

## The guard gate

No diff is presented, committed, or merged until the relevant guard skill runs clean on it:

| The diff changed | Guard |
|---|---|
| Any production code | `clean-code-guard` |
| WP plugin/theme/block/REST/AJAX/query | `wp-guard` |
| WooCommerce code | `woo-guard` (on top of `wp-guard`) |
| Test code | `test-guard` |
| Docs / README / docstrings | `docs-guard` |

The guards are run by the coding agent on each diff; CI enforcement is planned.

## Running the tests

```bash
composer install
composer test               # headless unit suite (Pest + Brain Monkey) — runs in CI
composer test:integration   # boots the real ./wp install (local; needs WordPress + MySQL)
```

CI (`.github/workflows/ci.yml`) gates **four suites plus CodeQL** on every pull request: the headless
PHP unit suite, the JavaScript suite, an integration suite against a WordPress it provisions itself,
and Playwright in a real browser. Beside them run the quality checks every release is measured by
([Quality checks](#quality-checks-spec-116)): the screen matrix and the other browser checks, the PHP
translation lint, the completion audit, the performance budgets and the client merge check.

```bash
composer test:performance   # the admin read and form acceptance budgets (local; needs WordPress)
php scripts/i18n-lint.php   # the PHP translation lint
npm run audit:completion    # the product completion audit
npm run test:quality        # the browser quality checks (a scratch site; see Quality checks)
```

**CI is the authority for the integration and browser runs.** A long-lived development install
accumulates state a freshly provisioned one does not, which is why a handful of integration specs
fail locally and pass in CI. If a suite fails only on your machine, check that before assuming a
regression.

## Reviewing dependency changes

Run the repository-owned dependency gate whenever a manifest, lockfile, audit policy, or dependency workflow
changes:

```bash
npm run verify:dependencies
```

Review the raw npm/Composer advisory before changing `.github/dependency-security-policy.json`. Prefer a compatible
upgrade within the current direct dependency ranges. Never apply `npm audit fix --force` or a suggested downgrade
without a separate reviewed compatibility migration.

If no compatible fix exists, an exception is allowed only for a demonstrated non-runtime/non-CI exposure. Record
the exact advisory and dependency path, severity ceiling, exposure evidence, compensating control, owner, review
date, and upstream trigger. New, incomplete, expired, stale, path-changed, or severity-changed findings must fail
the gate; high or critical runtime/CI findings cannot be excepted. Registry or advisory-service errors are
unavailable evidence, not a pass.

The `Dependency Security` workflow runs this check weekly, on demand, and on pull requests that change dependency
or policy files.

## Authorship metadata

Framework plugin and theme headers credit a single owner/brand — `Author: Mustafa Shaaban` —
not a non-existent "team". New `corex-*` plugins and the theme follow the same convention;
client sites generated from Corex set their own agency/company name (see the site-generator
docs when available).

## Browser verification (Definition of Done)

A UI change is not done until it is **browser-verified** — "env-gated" is a CI gate, not an open excuse (spec 052):

- The **E2E smoke** (`tests/e2e/smoke.spec.js`) exercises three core flows in a real browser: find a `corex/*`
  block in the editor's inserter, submit the front-end contact form, and reach a real kit in the setup wizard.
- The **console-error sweep** (`tests/e2e/console.spec.js`) fails on any console **error** (not warning) on the
  block editor, the Corex settings screen, or the front page — catching item-20-class JS/asset
  regressions. A tiny, documented allow-list (`tests/e2e/helpers.js`) exempts known third-party noise.

Both are part of the browser suite: every spec under `tests/e2e/`, run by `npm run test:e2e`.

### In CI

The suite is the `e2e` job, **Browser tests (Playwright)**, in `.github/workflows/ci.yml`. It runs on every pull
request, on every push to `main` or `develop`, and nightly against `main` (05:17 UTC, in the framework's
repository only), and it is a required check on `main`. `ci.yml` declares no `workflow_dispatch`, so there is no
on-demand run: to run the job again, re-run it from the pull request's checks.

The job does not use wp-env. `.github/actions/provision-wordpress` installs the latest WordPress into `./wp`,
links the theme, the plugins and the add-ons into it, activates them, and installs the CoreX schema. WP-CLI comes
from setup-php, which provides the latest; on a runner where setup-php could not fetch it, the action installs
WP-CLI 2.12.0 itself and the run carries a warning that it did. The job then
copies the must-use fixtures, seeds a `/contact/` page, three stored submissions and five users who are not
administrators, builds the bundles, and serves the install with nginx and php-fpm at `http://127.0.0.1:8080`.
A php-fpm for PHP 8.3 is on the runner once setup-php has run, on Ubuntu 24.04 and on 26.04; the job asks apt for
`php8.3-fpm` only on a runner that has none.
php-fpm runs with OPcache on and the JIT off, and the job checks that the JIT is off before the specs start.

The job sets `COREX_E2E_FRESH_INSTALL`, which skips three tests by title: `CANNOT_RUN_ON_A_FRESH_INSTALL` in
`tests/e2e/playwright.config.js` lists them and says why. Two of the three are the block-editor tests of the
smoke and of the sweep, so those two are checked only by a local run.

**When a spec fails on an HTTP 500**, what threw is in the server's log and not in the spec's output. CoreX
answers an exception behind a REST route with `Request could not be processed.` and writes the exception's
class, its message and the file and line to PHP's error log. The job prints the last 40 lines of that log, and
what php-fpm's OPcache reports about itself, in the step **Print the server's errors and the state of PHP's
opcode cache**. That step runs on every run, passing or not. When a spec fails, the same files are in the
`playwright-report` artifact under `test-results/server-logs/`.

### Locally

The suite starts no site. It drives one that is already served, with the CoreX theme, the plugins and the
add-ons active, which is the install `scripts/setup-wordpress.ps1` makes. Then, from the repository root:

```bash
npm run build                        # the bundles are not committed
npx playwright install chromium      # the browser, once
npm run test:e2e
```

The suite reads where the site is, and who signs in, from the environment:

| Variable | Default | Set it when |
|---|---|---|
| `COREX_BASE_URL` | `http://corex.local` | the install is served at another address |
| `COREX_ADMIN_USER`, `COREX_ADMIN_PASS` | `admin`, `password` | the install's administrator is not that pair. `scripts/setup-wordpress.ps1` has a different default for `-AdminPassword`, so set `COREX_ADMIN_PASS` for an install it made |
| `COREX_LOGIN_PATH` | `/wp-login.php`, then `/corex-login/` | login protection moved the login to another address |
| `COREX_WP_PATH` | `./wp` | `COREX_BASE_URL` serves an install somewhere else. A few specs reach the install through WP-CLI, and skip those steps where WP-CLI is not available |

**The must-use fixtures.** Every `tests/e2e/fixtures/corex-e2e-*.php` has to be in `wp-content/mu-plugins/` of
the install the suite drives. `scripts/setup-wordpress.ps1` copies them into `./wp` on every run, the same copy
the CI job makes. It copies none into the `-Multisite` install, and none once a client site under `sites/` is
linked: there it prints the commands instead. For an install made any other way, make the copy yourself:

```bash
mkdir -p wp/wp-content/mu-plugins
cp tests/e2e/fixtures/corex-e2e-*.php wp/wp-content/mu-plugins/
```

They are copies, so run the script, or make the copy, again after editing a fixture. A must-use plugin is
active as soon as the file is there, and one of these adds a guide to the Guides screen, so copy them only
into an install kept for development.

**Other installs.** The Docker entrypoint (`docker/php/entrypoint.sh`) and the manual steps of the
[Linux](docs/en/00-getting-started/linux.md) and [macOS](docs/en/00-getting-started/macos.md) guides link the
add-ons, activate four plugins and no add-on, and copy no fixture. Before running the suite against one of
those installs, activate the rest and copy the fixtures yourself.

**What CI seeds.** The `Seed browser-test fixtures` step of the `e2e` job creates the `/contact/` page, the
stored submissions and the users named above. A new install has none of them. The smoke's contact-form test
needs the page, and each spec that signs in as one of those users fails at the sign-in until the user exists.
Run that step's `wp` commands against a new install, or set the environment variables read at the top of each
such spec to users of your own.

No workflow runs the suite against wp-env, and `wp-env.json` maps the theme and three plugins and none of the
add-ons whose screens the specs open, so `npm run env:start` is not a way to run it.

A local run is a check before you push. CI is the authority, for the reason given under
[Running the tests](#running-the-tests).

**What a run leaves behind** (spec 116, US-NFR-09). Before the first test, the suite prints the site it will
write to, and refuses one whose CoreX operations mode is declared Production unless it is served from a local
development host (localhost, a loopback address, `.localhost`, `.test`, `.local`), where the specs themselves
switch the mode. It then removes the submissions earlier runs left that carry a test's marker: a flow whose slug contains `e2e`, a `utm_source` of `playwright`,
or a sender at `example.com` or `example.test`, for a submission the run made itself. After the last test it
removes the run's own marked submissions, and fails if the run made any submission that is still there,
naming each. A spec that makes a submission marks it or removes it. All of this needs WP-CLI on the install
`COREX_BASE_URL` serves; where WP-CLI answers for another install, the suite says so and touches nothing.

What earlier runs left on a development site is removed with:

```bash
wp eval-file scripts/clean-test-residue.php
```

It lists the submissions by form and asks before removing them (`yes` as the script's argument skips the
question). It refuses a site whose operations mode is Production or that sets `WP_ENVIRONMENT_TYPE` to
`production`. The fixture plug-ins say "Test fixture" in what a person sees.

## Quality checks (spec 116)

Every release is measured by the same checks: the ones the definition of done names. Each runs on every pull
request and fails on what it finds, except what is recorded as a known failure.

| Check | Where | What fails it |
|---|---|---|
| Screen matrix | `tests/e2e/quality/screen-matrix.spec.js` | Every CoreX admin screen and tab in WordPress's menu, and every theme template, at 375 and 1280px, light and dark, left to right and right to left: a page that does not answer as expected, sideways scroll, any WCAG 2.0 to 2.2 A or AA rule axe detects (contrast and target size included), raw developer keys in visible text, an animation repeating at rest; at 375px a table wider than the screen, a primary control under 44px, more than one rail control above the heading, and text at 200% cut off or overlapping |
| Keyboard | `keyboard.spec.js` | A stop that is invisible when focused or whose focus indicator is under 3:1; an expanding control that Escape does not close, or that does not get focus back |
| Motion | `motion.spec.js` | With reduced motion requested, anything but opacity moving |
| States | `states.spec.js` | A screen with no state table in `screen-states.json`; a screen that says "nothing" before its answer, shows no loading, shows a raw error code, never stops loading, does not say it failed, or shows a blank area for empty lists; a refusal that is not CoreX's access-denied page; a failed Settings save that loses what was typed |
| Words | `pseudo-locale.spec.js`, `scripts/i18n-lint.php` | A CoreX word on screen that skipped translation (every `corex` string is marked by the pseudo-locale fixture); PHP output that is not translated, or a translation call outside the `corex` domain |
| Public form | `public-form.spec.js` | A field without a label; a failed submit that does not move focus to an error summary linking to each field |
| Front-end budget | `front-end-budget.spec.js` | Layout shift over 0.05, an image without dimensions, an image below the first screen loaded at once or the first screen's main image loaded lazily, CoreX bytes more than 10% over `byte-budget.json`, a block's asset on a page without the block |
| Admin contrast | `tests/Unit/Theme/ContrastMatrixTest.php` | An admin token pair below its threshold, dark or light (`admin-contrast-matrix.json`) |
| Completion audit | `npm run audit:completion` | "Planned", "coming soon" as a promise, "sample data", "reference only" and similar in shipped source |
| Client impact | `tests/repo-hygiene.test.js` | A Client impact section that names no client site |
| Client merge | `client-merge` job, `scripts/client-merge-check.mjs` | A candidate that does not merge into a client repository, or whose client suites fail |
| Performance budgets | `composer test:performance` | An admin read over its budget at 10,000 records, or a form acceptance over two seconds |

The browser checks are the `quality` job, on a WordPress of their own prepared by
`tests/e2e/quality/seed-quality-site.php` (the Company kit at Full, a page for each template, a subscriber). CI's
run is the reference. To run them locally, against a scratch site prepared the same way and never against a site
that matters:

```bash
wp eval-file tests/e2e/quality/seed-quality-site.php
COREX_BASE_URL=http://127.0.0.1:8916 COREX_ADMIN_PASS=changeme npm run test:quality
```

The screens and templates come from the site, so a screen added later is checked without an edit. A new theme
template needs its address in `tests/e2e/quality/templates.json`; a new admin screen needs its state table in
`screen-states.json`. Both checks fail until they are there.

**Known failures.** `tests/e2e/quality/known-failures.json` holds what the checks found on `main` when they were
written: each entry has the check, the screen, the rule, a signature of the element, the date and the story that
fixes it. The list only shrinks. A pull request that fixes a screen removes its entries; the job's output lists
the entries no finding matched (`stale-known-failures.json` in the `quality-findings` artifact). A defect a pull
request adds is fixed in it, not added to the list. One that a later story owns may be added, with that story's
id, in the same pull request, and the description says why. To record from a CI run's artifact:

```bash
node scripts/quality-known-failures.mjs --record <the unpacked quality-findings folder>
node scripts/quality-known-failures.mjs --budget <the same folder>   # only to set the byte budget
```

The static checks keep their own lists: `knownFailures` in `admin-contrast-matrix.json` and
`tests/Fixtures/i18n-lint-baseline.json`. Both fail on an entry that no longer matches, since only the file they
read can make it stale.

### Before a tag

1. **Each client's code.** Run the merge check against each running client's repository, at its recorded
   baseline, with that client's suites, and put the report in the release record:

   ```bash
   node scripts/client-merge-check.mjs --client <the client repository's URL> --candidate <the release branch> --suite "<the client's test command>" --report client-merge-muva.md
   ```

   It works on a throwaway clone and never writes to the repository it is given. A conflict in a file the client
   records as a local edit, or a failing suite, stops the tag until that client's session has agreed.
2. **The known failures.** Print the list into the release notes, under Client impact:
   `node scripts/quality-known-failures.mjs`.
3. **What a person checks.** Look through the `quality-findings` artifact's screenshots (four per screen) of the
   release candidate's run. Use the screens the release changed with a screen reader (NVDA with Chrome) and
   record what was heard in the release record. A screen with no recorded pass is not called accessible in the
   release notes.
4. **The translation template.** A published release gets `corex.pot` attached by
   `.github/workflows/release-assets.yml`; check that it is there.
