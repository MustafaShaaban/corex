# Implementation Plan: CLI block scaffolder & code→docs generator (019)

**Branch**: `019-cli-block-docs` (uncommitted on `develop`) | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

> Retrospective plan — maps each FR to the file that already satisfies it and flags drift. No new architecture.

## Summary

Two new `wp corex` commands on the spec-003 generator engine: `make:block` (a multi-file dynamic-block
scaffolder following the spec-018 block contract) and `docs:generate` (an AST-based, class-loading-free
reader that emits the per-class internals reference). Both keep the engine pure/headless and confine WP-CLI to
a thin command layer.

## Technical Context

**Language/Version**: PHP 8.3. **Primary Dependencies**: `nikic/php-parser` (AST, transitive dep), the spec-003
generator engine (StubRenderer, Naming, GeneratorContext), WP-CLI (optional). **Testing**: Pest (incl. a
`php -l` lint of generated output). **Project Type**: CLI tooling within the monorepo. **Constraints**: pure
engines (no WP, no class loading); generated reference git-ignored.

## Constitution Check (v1.2.0)

- [x] **III/IV (layering + DI)** — PASS. Engines (`BlockScaffolder`, `DocsGenerator`/`ClassDocReader`/
  `MarkdownDocRenderer`) are pure classes; the WP-CLI commands (`MakeCommand`, `DocsCommand`) are the only
  WP-CLI surface, bound in `CliServiceProvider`.
- [x] **IX (optional dep)** — PASS. WP-CLI confined to `class_exists('WP_CLI')`; engines run headlessly.
- [x] **V/VI/VIII (tokens/assets/RTL)** — PASS via spec 018: `make:block` scaffolds a token-only, RTL,
  conditional block (the contract it emits).
- [x] **X (spec)** — reconciled by this retrospective spec.
- [x] **Guard Gate / DoD** — PARTIAL. clean-code-guard run on the `make:block` diff (item 3); a formal full
  re-run incl. `docs:generate` is **P2**. Tests: 8 (BlockScaffolder) + 4 (DocsGenerator) Pest, green. Docs:
  `packages/cli/README.md`. No i18n/RTL/UI surface here (CLI).

**Gate**: PASS (P2 formal guard re-run tracked).

## FR → implementation map

| FR | Satisfied by |
|---|---|
| FR-001/002/003/004 make:block | `packages/cli/src/Generators/{BlockScaffolder,BlockScaffoldResult}.php` + stubs `packages/cli/stubs/block/{block.json,index.js,style.scss,renderer}.stub`; `Naming::{blockSlugFor,titleFor}`; renderer + folder in one `Blocks/` dir |
| FR-005/006 docs:generate | `packages/cli/src/Docs/{ClassDocReader (php-parser AST), MarkdownDocRenderer, DocsGenerator (walk+skip)}.php` |
| FR-007 WP-CLI confinement | `packages/cli/src/Commands/{MakeCommand (block branch), DocsCommand}.php`; `CliServiceProvider` binds engines + registers commands under `class_exists('WP_CLI')` |
| FR-008 generated docs ignored | `docs-app/.gitignore` `src/content/docs/reference/*/` |

**Drift found:** none. (Clean-code audit noted `MakeCommand`'s optional nullable block params as a mild SRP
smell — tracked under P3, not a spec drift.)

## Project Structure (already implemented)

```text
packages/cli/src/
├── Generators/{BlockScaffolder,BlockScaffoldResult,StubRenderer,Naming…}.php
├── Docs/{ClassDoc,ClassDocReader,MarkdownDocRenderer,DocsGenerator}.php
├── Commands/{MakeCommand,DocsCommand}.php
└── CliServiceProvider.php
packages/cli/stubs/block/{block.json,index.js,style.scss,renderer}.stub
tests/Unit/Cli/{BlockScaffolderTest,DocsGeneratorTest}.php
```

## Complexity Tracking

No unjustified violations. P2 (guards) and P3 (MakeCommand SRP tidy) are remediation, not complexity.
