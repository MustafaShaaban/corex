# Implementation Plan: make:site — client-site platform

**Branch**: `feature/049-make-site` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/049-make-site/spec.md`

## Summary

`wp corex make:site <Name>` scaffolds a client **site plugin** + **site theme** with their own namespace/prefixes
(distinct from Corex), governance/workflow files (AGENTS.md/CLAUDE.md/README/PROGRESS/DECISIONS/.gitignore +
specs/docs), a site config pointing the `make:*` generators at the client plugin, and — with `--starter` — one
working vertical slice (model→repository→service→controller(envelope)→block→option). Built on the spec-003 generator
engine + the multi-file scaffolder pattern (`BlockScaffolder`/`ApiResourceScaffolder`): a pure `SiteIdentity`
(name → namespace/slugs/prefixes, Corex-distinctness guard) + a pure `SiteScaffolder` (render-all-before-write) +
stubs, with a thin WP-CLI command. Generated PHP is `php -l`-clean; generated routes use the envelope; styling is
client-token-only.

## Technical Context

**Language/Version**: PHP 8.3. The identity deriver + scaffolder are pure; the `make:site` command is a gated boundary.

**Primary Dependencies**: existing only — spec-003 `StubRenderer`/`Naming`/`GeneratorContext` + the multi-file
scaffolder pattern, the `corex-app`/`app.*` convention, the spec-043 envelope, the existing theme/plugin shapes. No
new dependency.

**Storage**: none — generates files on disk.

**Testing**: Pest — `SiteIdentity` (derivation + distinctness), `SiteScaffolder` (renders the plugin/theme/governance
set, `php -l` of generated PHP, idempotent, flags). Live activation env-gated.

**Target Platform**: WP-CLI (generation).

**Project Type**: WordPress framework monorepo — `packages/cli` (scaffolder/stubs/command).

**Performance Goals**: one-shot generation.

**Constraints**: generated namespace/prefixes distinct from Corex (FR-001/edge); valid PHP + valid block theme;
generated routes envelope+middleware; token-only client styling; no secret; pure engine (Principle III/XI).

**Scale/Scope**: 1 `SiteIdentity` + 1 `SiteScaffolder` + `SiteScaffoldResult` + the stub set (plugin main +
provider + theme style.css/theme.json + AGENTS/CLAUDE/README/PROGRESS/DECISIONS/.gitignore + optional starter slice)
+ the `make:site` command.

## Constitution Check

*GATE: pass before Phase 0; re-check after Phase 1.* (Corex Constitution v1.2.1.)

- [x] **I. Theme is a skin** — PASS. The generated **theme** holds presentation only; the generated **plugin** holds
  app logic — the boundary the whole feature enforces. Stubs encode this separation.
- [x] **II. Plugins boot themselves** — PASS. The generated plugin has a self-initialising provider.
- [x] **III. Thin controllers, fat services** — PASS. The generated controller (starter slice) is thin + envelope;
  the scaffolder/identity are pure; the command is a thin gated boundary.
- [x] **IV. Everything injected** — PASS. The generated provider wires services; the scaffolder is container-wired.
- [x] **V. Runtime tokens** — PASS. The generated theme uses theme.json tokens via the client CSS prefix; no
  hardcoded values in stubs.
- [x] **VI. Conditional assets** — PASS. The generated block follows the dynamic/conditional pattern.
- [x] **VII. Declarative security** — PASS. The generated REST controller uses the spec-043 envelope + declares
  middleware + a permission callback; no secret in any generated file.
- [x] **VIII. RTL-first** — PASS. Generated styling uses logical properties.
- [x] **IX. No optional dep is hard** — PASS. WP-CLI-gated; the framework runs without it; the generated client
  imports Corex base classes but never hard-requires an optional add-on.
- [x] **X. Spec is source of truth** — PASS. Traces to spec 049; reuses 003/043 + the app-path convention.
- [x] **Guard Gate + DoD** — clean-code (pure engine), wp-guard (generated route/escaping/no-secret), test-guard
  (Pest + generated-`php -l`), docs-guard (the make:site guide + the generated governance accuracy); PROGRESS/
  DECISIONS; NEXT STEP.

**Result: PASS — no violations.**

## Project Structure

```text
packages/cli/
├── src/Site/
│   ├── SiteIdentity.php          # NEW — pure: name → {namespace, pluginSlug, themeSlug, textDomain, restNamespace, cssPrefix, optionPrefix}; refuses 'corex'
│   ├── SiteScaffolder.php        # NEW — pure multi-file: plugin + theme + governance (+ optional starter slice), render-all-before-write
│   └── SiteScaffoldResult.php    # NEW — created/skipped/error result
├── stubs/site/
│   ├── plugin.php · provider.stub
│   ├── theme-style.css · theme-json.stub
│   ├── AGENTS.md · CLAUDE.md · README.md · PROGRESS.md · DECISIONS.md · gitignore.stub
│   └── starter/ (model · repository · service · controller · block · option · test · README)  # NEW (US3)
├── src/Commands/MakeCommand.php  # CHANGE — wire `make:site` (flags: --plugin-only/--theme-only/--minimal/--starter/--force)
└── src/CliServiceProvider.php    # CHANGE — bind SiteScaffolder; register make:site

docs-app/.../guides/client-site.md   # NEW — build a client site + the team/AI workflow
tests/Unit/Cli/ (Pest)               # NEW — SiteIdentity, SiteScaffolder
```

**Structure Decision**: Follow the **spec-003/046 multi-file scaffolder pattern** — a pure `SiteScaffolder`
(render-all-before-write, like `ApiResourceScaffolder`) + a pure `SiteIdentity` deriver + stubs, with a thin
`class_exists('WP_CLI')`-gated `make:site` command. The stubs encode the framework's own principles (thin
controller + envelope, theme = skin, token-only) so a generated site is correct-by-construction. The `wp/` repo
layout + Azure pipeline are **documented** (the generated config points the `make:*` generators at the client
plugin); the pipeline automation + update packaging are **spec 050**, the design-system SCSS depth **spec 051**.

## Complexity Tracking

> No Constitution Check violations — section intentionally empty.
