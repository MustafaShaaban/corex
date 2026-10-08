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
and Playwright in a real browser.

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
links the theme, the plugins and the add-ons into it, activates them, and installs the CoreX schema. The job then
copies the must-use fixtures, seeds a `/contact/` page, three stored submissions and five users who are not
administrators, builds the bundles, and serves the install with nginx and php-fpm at `http://127.0.0.1:8080`.

The job sets `COREX_E2E_FRESH_INSTALL`, which skips three tests by title: `CANNOT_RUN_ON_A_FRESH_INSTALL` in
`tests/e2e/playwright.config.js` lists them and says why. Two of the three are the block-editor tests of the
smoke and of the sweep, so those two are checked only by a local run.

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
