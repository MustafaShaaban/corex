# Contributing to Corex

Corex is built spec-first and guard-gated. Read `CLAUDE.md`, `specs/constitution.md`, and
`COREX-WORKING-GUIDE.md` before contributing — they override anything here on conflict.

## Branching model (git-flow-lite)

Per `COREX-FRAMEWORK.md` §19:

| Branch | Purpose |
|---|---|
| `main` | Production-ready history only. Every merge is a tagged release. |
| `develop` | Integration branch. Features merge here first. |
| `feature/NNN-slug` | Short-lived work off `develop` (e.g. `feature/007-forms-engine`). |
| `hotfix/slug` | Urgent fix off `main`; merged to both `main` and `develop`. |

- Branch features from `develop`; open a PR back into `develop` with a green pipeline.
- Merge `develop` → `main` only at a stable release checkpoint, and **tag** it.
- Environments deploy from tags, never branches.

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

CI (`.github/workflows/ci.yml`) runs `composer validate`, a PHP lint, and the headless unit
suite on every push/PR to `main` and `develop`. The integration suite needs a provisioned
WordPress (wp-env) and is run locally for now.

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

These run automatically in CI on PRs + nightly via `.github/workflows/e2e.yml` (it provisions wp-env, activates
Corex, installs Playwright, and runs the suite). To run locally:

```bash
npm run env:start          # wp-env (Docker)
npx playwright install     # the browser, once
npm run test:e2e
```
