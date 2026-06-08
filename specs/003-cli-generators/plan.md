# Implementation Plan: CLI Generators (`wp corex make:*`)

**Branch**: `003-cli-generators` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-cli-generators/spec.md`

## Summary

Deliver stub-based generators (`make:model`, `make:repository`, `make:controller`, `make:service`) on a
`wp corex` command surface. The core is a **WP-CLI-independent generator engine** — render a stub by
substituting `{{ }}` placeholders, resolve the target path/namespace/prefix from Config, and write the
file (idempotent, `--force` to overwrite, name-validated). The WP-CLI commands are a thin wrapper that
registers only when WP-CLI is present (Principle IX). Keeping the engine separate from WP-CLI makes the
render+write+validation logic fully unit-testable headlessly.

## Technical Context

**Language/Version**: PHP 8.3.

**Primary Dependencies**:
- corex-core (container, `ServiceProvider`, `Config`).
- WP-CLI (`WP_CLI::add_command`) — used **only** in the command-registration layer, detected via
  `class_exists('WP_CLI')`; never a hard dependency (Principle IX, FR-013).
- No new Composer packages. Stubs are plain text files.

**Storage**: Writes source files into the configured app source tree. No database, no options beyond
existing Config.

**Testing**: Pest + Brain Monkey. The engine, renderer, and naming are pure → unit-tested with a temp
directory (no WP-CLI, no WordPress). A thin integration check confirms command registration when WP-CLI
is present.

**Target Platform**: WordPress ≥ 7.0, PHP ≥ 8.3; WP-CLI optional.

**Project Type**: Framework CLI package (`Corex\Cli` → `packages/cli/src`; stubs in `packages/cli/stubs`).

**Performance Goals**: Generation is a single file render+write — negligible.

**Constraints**: No overwrite without `--force` (SC-003); invalid names rejected before any write
(SC-004); engine works with no WP-CLI (SC-005/SC-006); generated code passes the guards (SC-002).

**Scale/Scope**: v1 = the four MVC generators + the engine; `--cpt`/`--rest`/`--ability` flags and the
other generators/runtime commands are out of scope. No NEEDS CLARIFICATION (spec clarified 2026-06-08).

## Constitution Check

*GATE: Must pass before Phase 0. Re-check after Phase 1.*

- [x] **I. Theme is a skin** — N/A (developer tooling; no presentation).
- [x] **II. Plugins boot themselves** — PASS: registered via a `ServiceProvider`; commands register on
  the CLI boot only when WP-CLI is present.
- [x] **III. Thin controllers, fat services** — PASS: the subsystem adds no controllers/business logic;
  the *generated* code follows the layering (FR-006).
- [x] **IV. Everything injected** — PASS: the engine, renderer, naming, and generators resolve through
  the container.
- [x] **V/VI/VIII** — N/A (no styling/assets/UI).
- [x] **VII. Declarative security** — N/A (no request handling); the generator only writes to the
  configured local source tree.
- [x] **IX. No optional dep is hard** — **PASS (headline)**: WP-CLI is detected via `class_exists` and
  used only in the command layer; SC-005/006 prove full operation without it.
- [x] **X. Spec is source of truth** — PASS: traces to spec 003 (clarified); implements FRAMEWORK §11.
- [x] **Guard Gate + Definition of Done** — every task runs the guards; the *generated stubs* are
  written to also pass `clean-code-guard`/`wp-guard` (FR-007); Pest tests; PROGRESS/DECISIONS updated.

**Result: PASS** — no violations.

## Project Structure

```text
packages/cli/
├── src/
│   ├── Generators/
│   │   ├── StubRenderer.php       # render {{ }} placeholders; error on any leftover/unprovided token
│   │   ├── GeneratorEngine.php    # resolve path, render, write (idempotent + --force) → Result
│   │   ├── GeneratorResult.php    # created | skipped(exists) | error(reason) + path
│   │   ├── Generator.php          # abstract: stub name, target sub-path, suffix, placeholders(name,ctx)
│   │   ├── ModelGenerator.php · RepositoryGenerator.php · ControllerGenerator.php · ServiceGenerator.php
│   │   └── GeneratorContext.php   # base path + namespace + prefix (resolved from Config)
│   ├── Support/
│   │   └── Naming.php             # normalize(name, suffix) + validate (PHP identifier) → ClassName
│   ├── Commands/
│   │   └── MakeCommand.php        # WP-CLI command: parse args/flags, invoke a generator, report
│   └── CliServiceProvider.php     # bind engine/generators; register WP_CLI commands when present
└── stubs/
    ├── model.stub · repository.stub · controller.stub · service.stub

packages/cli/composer.json         # (Corex\Cli already PSR-4-mapped in the root autoload)

tests/
├── Unit/Cli/                      # StubRenderer, Naming, GeneratorEngine (temp dir), each Generator's path/placeholders
└── Integration/Cli/              # command registration when WP-CLI present (or skipped)
```

**Structure Decision**: Fills `packages/cli` (`Corex\Cli`). `CliServiceProvider` joins `Boot`'s core
provider list; its `boot()` registers `wp corex make:*` only when `class_exists('WP_CLI')`.

## Key design decisions

1. **Engine vs command split** — `GeneratorEngine` (render+resolve+write) and `Naming`/`StubRenderer`
   are pure and unit-testable with a temp dir; `MakeCommand` is the only WP-CLI-touching class. This is
   the same "pure core, thin platform seam" split used for the QueryBuilder/QueryExecutor in spec 002.
2. **Config-resolved context** — `GeneratorContext` (base path, namespace, prefix) comes from the Config
   engine, injected into the engine; tests inject a temp context. Defaults ship in `config/app.php`
   (`namespace`, `prefix`, `path`); `wp corex init` sets them per project.
3. **Idempotent + explicit force** — the engine returns a `GeneratorResult` (created / skipped-exists /
   error); it never overwrites unless `force` is passed (FR-008).
4. **Leftover-token guard** — after rendering, any remaining `{{ ... }}` is an error, not a silent write
   (FR-003) — so a stub/placeholder mismatch fails loudly.
5. **Guard-clean stubs** — the four stubs are authored so their rendered output passes `clean-code-guard`
   and `wp-guard` (ABSPATH guard, injectable, i18n-ready), satisfying FR-007/SC-002.

## Phase 0 — Research

See [research.md](./research.md): WP-CLI detection + command registration, stub placeholder format,
path/namespace resolution, idempotency/force semantics, name validation, and the engine/command test
split. No open NEEDS CLARIFICATION.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md) — Stub, GeneratorContext, Generator, GeneratorEngine, GeneratorResult,
  Naming, MakeCommand: fields, relationships, lifecycle, error paths.
- [contracts/cli-contracts.md](./contracts/cli-contracts.md) — the PHP public API + contract→test matrix.
- [quickstart.md](./quickstart.md) — runnable validation scenarios mapped to success criteria.

## Complexity Tracking

No constitution violations — section intentionally empty.
