# Phase 0 Research: CLI Generators

All decisions resolve the Technical Context. No NEEDS CLARIFICATION (spec clarified 2026-06-08).

## R1 — WP-CLI detection & command registration

**Decision**: `CliServiceProvider::boot()` registers `wp corex make:*` only when `class_exists('WP_CLI')`
is true, via `WP_CLI::add_command('corex make:model', ...)` (and siblings). No `WP_CLI` symbol is
referenced anywhere outside that guarded boot path.

**Rationale**: `class_exists` is the canonical, dependency-free WP-CLI probe; gating registration keeps
WP-CLI optional (Principle IX, FR-012/013). The generator engine never touches `WP_CLI`.

**Alternatives**: `defined('WP_CLI')` (a constant WP-CLI sets — equivalent; `class_exists` is checked
because we call a class method); always-register (rejected — fatals without WP-CLI).

## R2 — Stub placeholder format

**Decision**: Double-brace, spaced tokens — `{{ class }}`, `{{ namespace }}`, `{{ prefix }}`,
`{{ post_type }}`, `{{ model }}`. The renderer replaces known tokens; any remaining `{{ … }}` after
substitution is an error (FR-003).

**Rationale**: Readable, unambiguous, easy to scan in a stub, and the leftover-token scan catches a
stub/placeholder mismatch loudly instead of shipping a broken file. Decided in the clarify session.

**Alternatives**: `%class%` / `__CLASS__` (collide with PHP magic constants); printf `%s` (positional,
error-prone).

## R3 — Path / namespace / prefix resolution

**Decision**: A `GeneratorContext` (base path, root namespace, prefix) is resolved from the Config
engine (`app.path`, `app.namespace`, `app.prefix`; shipped defaults, set by `wp corex init`). Each
generator appends its conventional sub-namespace + sub-path (`Models/`, `Repositories/`,
`Controllers/`, `Services/`) and the file name (`<Class>.php`). The context is injected into the engine
so tests pass a temp directory + test namespace.

**Rationale**: Reuses the spec 001 Config layer (the clarified decision); keeps the engine pure and
environment-agnostic; mirrors Laravel's `app/` + namespace resolution.

**Alternatives**: per-command `--path/--namespace` flags (verbose, error-prone); active-plugin
auto-detection (magical, hard to test).

## R4 — Idempotency & force

**Decision**: `GeneratorEngine::generate()` returns a `GeneratorResult` — `created`, `skipped` (file
exists, no force), or `error` (validation/write failure) — and never overwrites unless `force` is true
(FR-008). The target directory is created if missing; a write failure yields `error` with no partial
file.

**Rationale**: A value-typed result keeps the command layer thin (it just formats the outcome) and makes
the safety behavior unit-assertable without parsing console output.

## R5 — Name validation & normalization

**Decision**: `Naming::classNameFor(string $raw, string $suffix)` trims, strips an existing suffix,
validates the result against the PHP class-identifier rule (`^[A-Za-z_][A-Za-z0-9_]*$`, not a reserved
word), and re-applies the conventional suffix (`Repository`/`Controller`/`Service`; Model has none).
An invalid name throws a clear `InvalidNameException` before any write (FR-009/FR-010).

**Rationale**: Centralizes the naming rules so all four generators behave consistently; rejecting early
guarantees SC-004 (no file written on invalid input).

## R6 — Test split

**Decision**: Unit-test the pure pieces headlessly — `StubRenderer` (substitution + leftover detection),
`Naming` (normalize/validate), `GeneratorEngine` (writes to a temp dir; created/skipped/force/error),
and each generator's target path + placeholder map. Integration-test only that the `wp corex make:*`
commands register when WP-CLI is present (skipped otherwise).

**Rationale**: `WP_CLI` is a CLI-runtime class; isolating it in `MakeCommand`/`CliServiceProvider`
keeps the engine provable without it (FR-004, SC-006).

## Summary

| Concern | Choice |
|---|---|
| WP-CLI detection | `class_exists('WP_CLI')` gate in `CliServiceProvider::boot()` |
| Placeholders | `{{ token }}`; leftover token → error |
| Path/namespace | `GeneratorContext` from Config (`app.path/namespace/prefix`) + sub-path |
| Idempotency | `GeneratorResult` (created/skipped/error); no overwrite without `force` |
| Naming | `Naming` normalize + identifier validation; reject before write |
| Tests | pure engine/renderer/naming unit; command registration integration |

No new Composer dependencies; `config/app.php` gains `namespace`/`prefix`/`path` defaults.
