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

- The **E2E smoke** (`tests/e2e/`) exercises the three core flows in a real browser: insert a `corex/*` block in
  the editor, submit the front-end contact form, and apply a kit.
- The **console-error sweep** (`tests/e2e/console.spec.js`) fails on any console **error** (not warning) on the
  block editor, the Corex admin, or a front-end page with Corex blocks — catching item-20-class JS/asset
  regressions. A tiny, documented allow-list (`tests/e2e/helpers.js`) exempts known third-party noise.

These run in CI nightly + on-demand (workflow_dispatch) via `.github/workflows/e2e.yml` — PRs stay gated by the fast unit CI, and you trigger the browser job before a release or to confirm a UI change. It (it provisions wp-env, activates
Corex, installs Playwright, and runs the suite). To run locally:

```bash
npm run env:start          # wp-env (Docker)
npx playwright install     # the browser, once
npm run test:e2e
```
