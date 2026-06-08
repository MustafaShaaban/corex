# Feature Specification: CLI Generators (`wp corex make:*`)

**Feature Branch**: `003-cli-generators`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "CLI generators (wp corex make:*) — stub-based generators (make:model/repository/controller/service) on a WP-CLI command surface, registered only when WP-CLI is present (never a hard dependency); idempotent (no overwrite without --force), name-validated, constitution-compliant output; generator logic unit-testable headlessly (pure render+write separated from WP-CLI). Built on corex-core + the data layer."

## Overview

Give developers Laravel-Artisan-style scaffolding: `wp corex make:model Career` (and `make:repository`,
`make:controller`, `make:service`) generate code that already follows the Corex constitution — thin
controllers, fat services, repositories that own data access, everything container-injectable — so a
developer starts from a correct skeleton instead of a blank file. Generators are stub-based: a template
with placeholders is rendered with the developer's class name, namespace, and prefix, then written into
the right location.

The command surface lives under `wp corex` and is built on WP-CLI, but WP-CLI is **not** a hard
dependency — the framework runs fully without it; the commands simply do not register when WP-CLI is
absent. The generator engine (render a stub → write a file) is separated from the WP-CLI layer so it is
unit-testable headlessly. The "users" are Corex application developers.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Scaffold a class from a stub (Priority: P1)

A developer runs a generator command with a name (e.g. a Model named "Career"). The framework reads the
matching stub, substitutes the placeholders (class name, namespace, prefix, derived values like a post
type), and writes a ready-to-use file to the conventional location for that artifact type. The generated
file is constitution-compliant and immediately usable.

**Why this priority**: The stub-render-and-write engine plus one working generator is the irreducible
core — every other generator is the same engine with a different stub and target. Without it nothing
scaffolds.

**Independent Test**: Run the Model generator with a name into a temp location; confirm a file is created
at the expected path whose contents are the stub with every placeholder replaced (no `{{...}}` left) and
that the result is valid, constitution-shaped code.

**Acceptance Scenarios**:

1. **Given** a stub and a valid class name, **When** the generator runs, **Then** a file is written at
   the conventional path for that artifact with all placeholders substituted.
2. **Given** the generated file, **When** it is inspected, **Then** no placeholder tokens remain and the
   declared class name/namespace match the request.
3. **Given** the generated file, **When** it is checked against the project's quality guards, **Then** it
   passes (constitution-compliant, injectable, i18n-ready where applicable).

### User Story 2 - The first generator set (Priority: P1)

A developer can scaffold each of the foundational MVC artifacts — `make:model`, `make:repository`,
`make:controller`, `make:service` — each producing code in the shape specs 001–002 established: a Model
as a read-only value object, a Repository extending the post-backed base, a thin Controller, and a
Service that holds logic, all resolved through the container.

**Why this priority**: The point of the generators is to scaffold the framework's own patterns; the four
core artifacts are what every module starts from. They share the US1 engine and differ only by stub +
target.

**Independent Test**: Run each of the four generators with a name; confirm each writes the correct
artifact to its conventional location, with the right base class/interface and the dependency-injection
shape (constructor injection, no inline `new`).

**Acceptance Scenarios**:

1. **Given** `make:model Career`, **When** it runs, **Then** a read-only Model is generated under the
   models location.
2. **Given** `make:repository CareerRepository`, **When** it runs, **Then** a repository extending the
   post-backed base (and bound to a Model) is generated under the repositories location.
3. **Given** `make:controller CareerController`, **When** it runs, **Then** a thin controller is
   generated under the controllers location.
4. **Given** `make:service CareerService`, **When** it runs, **Then** a service is generated under the
   services location.
5. **Given** any of the four, **When** the generated code is reviewed, **Then** it follows the layering
   rules (thin controllers, fat services, repositories own data access, everything injected).

### User Story 3 - Safe and ergonomic generation (Priority: P2)

A developer is protected from accidental data loss and from invalid input. Re-running a generator for an
artifact that already exists does not overwrite it unless the developer explicitly opts in; an invalid
name is rejected with a clear message; every run reports clearly what was created (or why it was not).

**Why this priority**: Generators write to the filesystem; without overwrite protection and validation
they are dangerous and frustrating. This builds on US1/US2.

**Independent Test**: Run a generator twice for the same name; confirm the second run does not overwrite
(and says so) unless an explicit force option is given; run with an invalid name and confirm a clear
rejection with no file written.

**Acceptance Scenarios**:

1. **Given** a file already exists for the target artifact, **When** the generator runs again without the
   force option, **Then** the existing file is left unchanged and the developer is told it already exists.
2. **Given** the same situation, **When** the generator runs with the force option, **Then** the file is
   overwritten and the developer is told it was overwritten.
3. **Given** an invalid name (empty, not a valid class identifier), **When** the generator runs, **Then**
   it is rejected with a clear message and no file is written.
4. **Given** a successful run, **When** it completes, **Then** the developer sees a clear success message
   naming the created file and its location.

### User Story 4 - WP-CLI optional, framework independent (Priority: P2)

The generator commands are available through the framework's CLI when WP-CLI is present, but the
framework — and the generator engine — work and load with no errors when WP-CLI is absent.

**Why this priority**: Constitution Principle IX (no optional dependency is a hard dependency). WP-CLI is
an optional runtime; the framework and its non-CLI features must not depend on it.

**Independent Test**: Load the framework in an environment without WP-CLI and confirm no error and no
command registration; in an environment with WP-CLI confirm the `wp corex make:*` commands are
registered; exercise the generator engine directly (no WP-CLI) and confirm it renders + writes.

**Acceptance Scenarios**:

1. **Given** WP-CLI is not present, **When** the framework loads, **Then** there is no error and no
   command registration is attempted.
2. **Given** WP-CLI is present, **When** the framework boots in the CLI context, **Then** the
   `wp corex make:*` commands are registered.
3. **Given** the generator engine, **When** it is invoked directly (without WP-CLI), **Then** it renders
   a stub and writes a file — provable in a headless test.

### Edge Cases

- **Name normalization**: a name given with or without a suffix (e.g. "Career" vs "CareerController") is
  normalized to the correct class name for the artifact; a fully-qualified or path-like name is rejected
  or sanitized.
- **Missing target directory**: the conventional location does not exist yet → it is created before the
  file is written.
- **Unwritable location**: the target cannot be written → a clear error, no partial file.
- **Existing file + no force**: never overwritten; reported.
- **Invalid identifier**: names that are not valid class identifiers (digits-first, reserved words,
  illegal characters) are rejected before any write.
- **WP-CLI absent**: commands silently do not register; the engine still works when called directly.
- **Unknown placeholder**: a stub referencing a placeholder the generator does not provide is reported as
  an error rather than written with a leftover token.

## Requirements *(mandatory)*

### Functional Requirements

**Generator engine**

- **FR-001**: The system MUST render a stub by substituting named placeholders (at minimum: class name,
  namespace, project prefix, and artifact-derived values such as a post type) with the requested values.
- **FR-002**: The system MUST write the rendered result to the conventional location for the artifact
  type, creating the target directory if it does not exist.
- **FR-003**: A rendered file MUST contain no remaining placeholder tokens; an unprovided placeholder
  referenced by a stub MUST be reported as an error, not written.
- **FR-004**: The generator engine (render + path resolution + write) MUST be separable from the CLI
  layer and exercisable in a headless automated test (FR-022 cross-cutting).

**First generator set**

- **FR-005**: The system MUST provide `make:model`, `make:repository`, `make:controller`, and
  `make:service`, each writing the correct artifact to its conventional location.
- **FR-006**: Generated code MUST follow the constitution: Models are read-only value objects; controllers
  are thin; services hold logic; repositories own data access; dependencies are constructor-injected (no
  inline instantiation of dependencies).
- **FR-007**: Generated, user-facing strings MUST be translation-ready; generated code MUST pass the
  project's quality guards.

**Safety & ergonomics**

- **FR-008**: A generator MUST NOT overwrite an existing target file unless an explicit force option is
  supplied; without it, the existing file is left unchanged and the outcome reported.
- **FR-009**: A generator MUST validate the supplied name and reject one that is not a valid class
  identifier, with a clear message and no file written.
- **FR-010**: A generator MUST normalize the supplied name to the correct class name for the artifact
  (adding/expecting the conventional suffix as appropriate).
- **FR-011**: Every run MUST report a clear outcome: the created file and location on success; the reason
  on a no-op or failure.

**WP-CLI optionality**

- **FR-012**: The `wp corex make:*` commands MUST register only when WP-CLI is present; when WP-CLI is
  absent the framework MUST load with no error and register nothing.
- **FR-013**: WP-CLI MUST NOT be a hard dependency — no framework code path outside the CLI registration
  may require WP-CLI classes/functions.

**Cross-cutting**

- **FR-014**: The generator subsystem MUST be registered through a corex-core service provider and resolve
  its collaborators through the container.
- **FR-015**: Every behavior MUST be exercisable headlessly with no optional plugin (ACF/Woo/Polylang) and
  with no WP-CLI required for the engine tests.
- **FR-016**: The subsystem MUST add no presentation and no business logic — it is developer tooling that
  emits code.

### Key Entities

- **Stub**: a template file with named placeholders for one artifact type.
- **Generator**: pairs a stub with a target-path rule and the placeholder values for one artifact
  (`make:model`, etc.).
- **Generator engine**: renders a stub and writes the file (the reusable, CLI-independent core).
- **Command**: the WP-CLI-facing wrapper that parses input, invokes a generator, and reports the outcome.
- **Naming**: the rules that normalize/validate a requested name into a class name + target path.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can scaffold any of the four artifacts with a single command, producing a
  ready-to-use file with **zero** leftover placeholder tokens.
- **SC-002**: Generated code passes the project's quality guards (clean-code + WordPress) in **100%** of
  the four artifact types, with no manual edits required to pass.
- **SC-003**: Re-running a generator never overwrites an existing file unless forced — **0** accidental
  overwrites across repeated runs.
- **SC-004**: An invalid name is rejected with a clear message and **no** file is written, in 100% of
  invalid-name attempts.
- **SC-005**: With WP-CLI absent, the framework loads with **zero** errors and registers **no** commands;
  with WP-CLI present, all four `make:*` commands are registered.
- **SC-006**: The generator engine is covered by headless automated tests that pass with no WP-CLI and no
  optional plugins present.

## Assumptions

- **Audience**: Corex application developers; no end-user UI.
- **Output location**: generated files are written into the consuming application/module's source tree at
  each artifact's conventional sub-path (models/, repositories/, controllers/, services/). The base path
  and the namespace/prefix come from the framework's configuration (set during `wp corex init`) with a
  sensible default; the precise base-path resolution is a planning-phase detail.
- **Stub format**: stubs are plain template files with delimited placeholders (e.g. `{{ class }}`); they
  live under `packages/cli/stubs` and the generator code under `Corex\Cli` (`packages/cli/src`).
- **Scope of flags**: this feature generates the class skeleton; richer options shown in the framework
  doc (`--cpt`, `--rest`, `--ability`, etc.) and the other generators (block, migration, seeder,
  middleware, ability, form, child-theme) and the migrate/seed runtime commands are **out of scope** here.
- **Force option**: a single explicit opt-in (e.g. `--force`) governs overwriting.
- **Foundation dependency**: built on corex-core (container, providers, config); registered via a service
  provider; reuses the Config engine for namespace/prefix/base-path.
- **WP-CLI**: detected via WP-CLI's presence at boot in the CLI context; never required elsewhere.
- **No new storage**: the feature writes source files only; it introduces no database tables or options
  beyond existing config.
- **Environment**: developed against the working WordPress install (Environment Gate satisfied); WP-CLI is
  available there for the integration check.
