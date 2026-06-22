# Implementation Plan: Team-Safe Company-Site Readiness (spec 061)

## Approach

A milestone split into reviewable, PR-sized task groups. PR A (this branch) ships the **team-safe foundation** (pure
docs/instructions — no runtime risk) plus the **shared-host `dist` builder + Azure pipeline** (self-contained
scripting/config). The remaining runtime (Media/WebP, generated-client image pipeline, the `make:site`
`sites/<client>/` restructure) ships in focused follow-up PRs because each changes tested runtime/generator code and
deserves its own review and test matrix.

## Architecture decisions

- **Repo root is the source of truth.** The team develops in the repo (`corex/`). The deployable artifact is
  generated into `dist/` (already git-ignored: `.gitignore` `/dist/` + `**/dist/`). The server receives only the
  contents of `dist/`. Git commits source only.
- **Client source lives under `sites/<client>/`** — outside the framework `plugins/`/`addons/`/`theme/` so framework
  and client work never collide. `wp/wp-content/` and `dist/` are runtime/output, never source.
- **Role gate before edit.** Four modes (Framework / Client Site / Deployment / Docs-Planning). Documented in the root
  agent files; enforced socially by start prompts and the generated client stubs. Rule hierarchy: Role Gate (where) →
  Spec Kit (what) → Guard Gate (safe to ship) → UI/UX ProMax (UI good enough).
- **Deployment split:** GitHub Actions = PR/code-quality gates (unchanged). Azure Pipelines = build `dist/` + deploy
  to hosting (new `azure-pipelines.yml`, deploy stage manual/parameterised, secrets in Azure).
- **`dist` builder is one builder.** `npm run build:dist` → `scripts/build-shared-host-dist.sh`. A future
  `wp corex package:site` (deferred) would call the same builder, not duplicate it.

## PR sequence

- **PR A (this branch):** spec 061 + agent role gate + start prompts + team-layout docs + handoff format + make:site
  governance stubs + shared-host `dist` builder/verifier + Azure pipeline + deployment docs + roadmap/progress/
  decisions. Validation: composer validate, PHP lint, Pest, Jest, docs-app build, `build:dist` dry-run + verify.
- **PR B:** Media/WebP settings UI + regeneration CLI + frontend delivery hardening + tests (FR-061-09/10/11).
- **PR C:** `make:site` `sites/<client>/` layout + header/footer override scaffolding + generated-client image
  pipeline + generator tests + migration note (FR-061-12/13).
- **PR D (optional):** WP Font Library curated collection if pursued (FR-061-14).
- **Release:** v0.29.0 after the runtime/generator/deployment milestone is merged and the release gate passes.

## Guard Gate / fallback validation

Named guard skills (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`) are applied as a review pass over each
diff. This repo's executable validation is the real gate: `composer validate`, `php -l`, `vendor/bin/pest`,
`npx jest`, `npm run lint:css`, `npm run lint:js`, `npm run build`, `docs-app` `npm run build`,
`npm run verify:dependencies`, `node scripts/generate-token-inventory.mjs`, and `git diff --check`. Where a guard
skill has no executable command, that real validation is the documented fallback (NFR-061-03).

## UI/UX ProMax

PR A is docs/scripts/config — no new product UI. The deferred Media settings UI (PR B) is the next UI-facing surface
and must consume only the scoped `--corex-admin-*` adapter, be keyboard/focus/RTL/reduced-motion correct, and pass an
acceptance pass. The M6 manual acceptance sweep (RTL/200%/keyboard) is recorded in this spec's evidence with
environment-gated status where a browser/WP sweep is unavailable.
