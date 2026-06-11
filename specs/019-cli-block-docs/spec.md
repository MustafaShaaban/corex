# Feature Specification: CLI block scaffolder & code→docs generator

**Feature Branch**: `019-cli-block-docs`

**Created**: 2026-06-11

**Status**: Draft (RETROSPECTIVE — documents delivered, tested, real-WP-verified code; items 3 + 9 of the "Finish Corex" initiative; reconciled to the implementation in `packages/cli`)

**Input**: "wp corex make:block scaffolds a complete working dynamic block from one name; wp corex docs:generate produces the per-class internals reference from the source so it can't drift."

> **Retrospective note.** Written after the code shipped, to restore spec-first compliance (Principle X). It
> extends the spec-003 CLI generator engine (`make:model/repository/controller/service`) with two new commands;
> the base generators remain spec 003's. Requirements describe the existing `packages/cli` code.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Scaffold a complete dynamic block from one name (Priority: P1)

A developer runs one command and gets a complete, registered, working dynamic block — block metadata, an
editor script, a token-only stylesheet, and a PHP renderer — so creating a block is configuration, not
boilerplate.

**Why this priority**: Blocks are the most repetitive multi-file artifact; without this, every block is
hand-assembled and easy to get subtly wrong (wrong asset wiring → "block not supported").

**Independent Test**: Run the command for a name; confirm the four files appear in the right place, the
metadata is valid (apiVersion 3, Corex category, renderer FQCN), and the renderer is valid PHP. Verifiable
headlessly (a temp dir) and live via WP-CLI.

**Acceptance Scenarios**:

1. **Given** a PascalCase name, **When** `make:block` runs, **Then** it writes `Blocks/<slug>/{block.json,
   index.js, style.scss}` + `Blocks/<Name>Renderer.php`, deriving slug/title/block-name/CSS-class/renderer
   FQCN from the name.
2. **Given** the generated `block.json`, **When** inspected, **Then** it is apiVersion 3 with `category:"corex"`,
   `editorScript`, the compiled `style` name, and `corex.renderer` set to the renderer's FQCN.
3. **Given** an existing block of the same name, **When** the command runs again, **Then** it is skipped unless
   `--force`.
4. **Given** an invalid name, **When** the command runs, **Then** it fails clearly and writes nothing.

---

### User Story 2 - Generate the internals reference from the source (Priority: P1)

A maintainer runs one command and the per-class API reference is regenerated from the actual source, so the
documentation can't drift from the code.

**Why this priority**: Hand-writing ~190 class pages would rot immediately; "keep the docs alive" requires
generation from the code.

**Independent Test**: Run the generator over the source trees; confirm one Markdown page per class is written,
grouped by layer, with the class summary + public method signatures; confirm a file with no class is skipped.

**Acceptance Scenarios**:

1. **Given** the Corex source trees, **When** `docs:generate` runs, **Then** it writes one reference page per
   class under `reference/<layer>/<class>.md` (class summary + public method signatures + summaries).
2. **Given** a file with no named class, **When** read, **Then** it is skipped (returns nothing), never fatal.
3. **Given** an unparseable file, **When** the run encounters it, **Then** it is skipped and the run continues.
4. **Given** generated pages, **When** the docs site builds, **Then** they integrate into the reference nav.

### Edge Cases

- A block name that is a reserved word or non-identifier → rejected (no files written).
- `make:block` renders all files before writing any — an unresolved placeholder fails without leaving a
  half-written block.
- The renderer + block folder share one `Blocks/` dir (cross-platform-safe; no `blocks/` vs `Blocks/` collision).
- `docs:generate` reads via AST parsing (no class loading) so it works on code with unmet runtime deps.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `make:block <Name>` MUST scaffold a complete dynamic block (block metadata + editor script +
  token-only stylesheet + PHP renderer) following the build-pipeline block contract (spec 018).
- **FR-002**: The block's identifiers (slug, title, block name, CSS class, renderer FQCN, text domain) MUST be
  derived from the single name + the configured namespace/prefix.
- **FR-003**: `make:block` MUST render all files before writing any, be idempotent (skip unless `--force`), and
  reject an invalid name without writing.
- **FR-004**: The renderer + its block folder MUST live in one directory (no case-only sibling that collides on
  case-insensitive filesystems).
- **FR-005**: `docs:generate` MUST produce one reference page per class, grouped by layer, from the **source**
  (parsing, not class loading), capturing the class summary + public method signatures + summaries.
- **FR-006**: `docs:generate` MUST skip files with no named class and unparseable files without aborting.
- **FR-007**: Both commands MUST register only when WP-CLI is present (`class_exists('WP_CLI')`); their engines
  MUST be pure/headless-testable, separate from the WP-CLI layer (Principle IX-style separation).
- **FR-008**: Generated reference pages MUST be git-ignored (regenerated), keeping the hand-written index.

### Key Entities

- **Block scaffold**: the 4-file output of `make:block` (metadata, editor script, stylesheet, renderer).
- **ClassDoc**: a class's extracted name/kind/summary/public-method signatures.
- **Reference page**: one generated Markdown page per class, under a layer folder.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `make:block <Name>` produces a block that registers + renders after `npm run build`, with zero
  manual edits to wire it.
- **SC-002**: A generated renderer is valid PHP (`php -l` clean) and implements the block-renderer contract.
- **SC-003**: `docs:generate` writes a reference page for every class in the configured trees (≈190 today) and
  the docs site builds with them included.
- **SC-004**: Re-running `docs:generate` after a code change updates the reference with no manual editing.
- **SC-005**: Both commands' engines are unit-tested headlessly (no WP-CLI, no class loading).

## Assumptions

- Built on the spec-003 generator engine (StubRenderer/Naming/GeneratorContext) and the spec-018 block
  contract; this feature adds the block scaffolder + the docs reader/renderer.
- `nikic/php-parser` is available (a transitive dependency) for AST parsing.
- The docs site (spec 022) consumes the generated `reference/` pages.
