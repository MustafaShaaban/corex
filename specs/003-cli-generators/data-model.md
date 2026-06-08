# Phase 1 Data Model: CLI Generators

Runtime objects of the generator subsystem. No persisted entities — the feature writes source files.

## Entity map

```text
MakeCommand (WP-CLI, only when WP_CLI present)
  └─ invokes ──> GeneratorEngine
                   ├─ uses ──> Generator (Model|Repository|Controller|Service)
                   │             ├─ Naming   → validated ClassName (+ suffix)
                   │             ├─ Stub     → template text
                   │             └─ placeholders(name, GeneratorContext)
                   ├─ StubRenderer → render {{ }}; leftover token = error
                   └─ writes file (idempotent + force) ──> GeneratorResult
GeneratorContext ← resolved from Config (base path, namespace, prefix)
```

## 1. GeneratorContext  *(FR-002)*

- **Fields**: `string $basePath`, `string $namespace`, `string $prefix`.
- **Source**: Config (`app.path`, `app.namespace`, `app.prefix`); injected.
- **Role**: where generated files go and under what namespace/prefix.

## 2. Naming  *(FR-009, FR-010)*

- **Behavior**: `classNameFor(string $raw, string $suffix = ''): string` — trim, strip an existing
  suffix, validate `^[A-Za-z_][A-Za-z0-9_]*$` and not a reserved word, re-apply the suffix.
- **Errors**: `InvalidNameException` (empty / illegal identifier / reserved word) — thrown before any
  write.
- **Derived**: `postTypeFor(string $class): string` — snake/lowercase of the model name.

## 3. Stub  *(FR-001)*

- **Role**: a template file under `packages/cli/stubs/<type>.stub` with `{{ token }}` placeholders.
- **Tokens**: `class`, `namespace`, `prefix`, and type-specific (`post_type`, `model`).

## 4. StubRenderer  *(FR-001, FR-003)*

- **Behavior**: `render(string $stub, array<string,string> $values): string` — replace each `{{ key }}`;
  after substitution, **any remaining `{{ … }}` raises `UnresolvedPlaceholderException`** (FR-003).

## 5. Generator (abstract) + 4 concretes  *(FR-005, FR-006)*

- **Declares**: `stub(): string` (stub name), `suffix(): string`, `subPath(): string` (e.g. `Models`),
  `placeholders(string $class, GeneratorContext $ctx): array`.
- **Concretes**: `ModelGenerator` (no suffix, `Models/`), `RepositoryGenerator` (`Repository`,
  `Repositories/`), `ControllerGenerator` (`Controller`, `Controllers/`), `ServiceGenerator` (`Service`,
  `Services/`). Each produces constitution-shaped code (FR-006).

## 6. GeneratorEngine  *(FR-002, FR-008)*

- **Behavior**: `generate(Generator $g, string $rawName, bool $force = false): GeneratorResult` —
  resolve `ClassName` (Naming) → target path (`ctx.basePath/subPath/Class.php`) → if exists and not
  `force` return `skipped`; else render the stub (StubRenderer) and write (creating the dir),
  returning `created`; a write/validation failure returns/raises `error`.
- **Invariant**: never overwrites without `force`; never writes a file with a leftover token.

## 7. GeneratorResult  *(FR-011)*

- **Shape**: `status` ∈ {created, skipped, error}, `path`, optional `message`.
- **Role**: the structured outcome the command formats for the developer.

## 8. MakeCommand  *(FR-011, FR-012)*

- **Role**: WP-CLI command; parses the name argument + `--force`, picks the generator for the
  subcommand, calls the engine, prints success/skip/error via `WP_CLI::success/warning/error`.
- **Registration**: by `CliServiceProvider::boot()` only when `class_exists('WP_CLI')`.

## 9. CliServiceProvider  *(FR-012, FR-014)*

- **register()**: bind `StubRenderer`, `Naming`, `GeneratorEngine`, the four generators, and
  `GeneratorContext` (from Config).
- **boot()**: if `class_exists('WP_CLI')`, register the `wp corex make:*` commands; otherwise no-op.
- Added to `Boot`'s core provider list.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| invalid name | `InvalidNameException`, no write | FR-009 |
| stub leftover token | `UnresolvedPlaceholderException`, no write | FR-003 |
| target exists, no force | `skipped`, file unchanged | FR-008 |
| target exists, force | overwritten, `created` | FR-008 |
| missing target dir | created before write | FR-002 |
| WP-CLI absent | commands not registered; engine still works | FR-012, SC-005 |
