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
