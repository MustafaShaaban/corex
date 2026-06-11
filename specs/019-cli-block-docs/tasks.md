# Tasks: CLI block scaffolder & code→docs generator (019)

**Retrospective spec** — the implementation exists, is unit-tested, and is verified live via WP-CLI
(`wp corex make:block Spotlight`, `wp corex docs:generate` → ~194 pages). These are
**reconciliation/verification** tasks: confirm each FR against the mapped file/behaviour (most already
satisfied, marked `[x]`), plus the tracked debts (a formal Guard Gate re-run incl. `docs:generate`,
remediation **P2**; the `MakeCommand` block-param SRP tidy, remediation **P3**). The FR→file map is in
`plan.md`; the engines are pure/headless (no WP, no class loading).

**No new implementation work** beyond the tracked P2/P3 debts — flag any mismatch found as a defect
rather than scope.

## Phase 1: Setup (verification context)

- [x] T001 Confirm the CLI engine resolves: `packages/cli/src/Generators/{StubRenderer,Naming,GeneratorContext}.php` (spec-003 base) are present and the new `BlockScaffolder`/`BlockScaffoldResult` sit beside them.
- [x] T002 Confirm `nikic/php-parser` is available (transitive dep) so `docs:generate` can parse AST without class loading.

## Phase 2: Foundational (engines — block all stories)

**⚠️ Both engines are pure classes, separate from the WP-CLI command layer (FR-007).**

- [x] T003 Verify `packages/cli/src/Generators/BlockScaffolder.php` derives slug/title/block-name/CSS-class/renderer-FQCN/text-domain from one name + the configured namespace/prefix, and renders all files before writing any (`UnresolvedPlaceholderException` aborts without partial output).
- [x] T004 Verify `packages/cli/src/Docs/{ClassDocReader,MarkdownDocRenderer,DocsGenerator}.php` read source via php-parser AST (no class loading), emit one Markdown page per class into a layer subfolder, and skip files with no named class / unparseable files without aborting.

## Phase 3: User Story 1 — Scaffold a complete dynamic block from one name (P1) 🎯 MVP

**Goal**: `make:block <Name>` produces a complete, registered, working dynamic block (4 files) with zero manual wiring.
**Independent test**: run the command for a name in a temp dir; the four files appear in the right place, `block.json` is valid (apiVersion 3, `category:"corex"`, `editorScript`, renderer FQCN), and the renderer is valid PHP.

- [x] T005 [US1] Verify FR-001/FR-002: `BlockScaffolder` scaffolds `Blocks/<slug>/{block.json,index.js,style.scss}` + `Blocks/<Name>Renderer.php` from one name (`tests/Unit/Cli/BlockScaffolderTest.php` — "scaffolds a complete dynamic block", "derives slug and title", "honours the configured namespace and prefix").
- [x] T006 [US1] Verify FR-001 (contract): `block.json` is apiVersion 3 with `category:"corex"`, `editorScript`, the compiled `style` name, and `corex.renderer` = renderer FQCN; `index.js` imports the SCSS so the build compiles a conditional stylesheet (BlockScaffolderTest — "writes a valid block.json with the dynamic renderer wired", "imports the SCSS in index.js").
- [x] T007 [US1] Verify FR-004 + SC-002: the renderer + block folder share one `Blocks/` dir (no case-only collision) and the generated renderer implements `Corex\Blocks\BlockRenderer` and is `php -l` clean (BlockScaffolderTest — "writes a renderer that implements BlockRenderer and is valid PHP").
- [x] T008 [US1] Verify FR-003: `make:block` is idempotent (skips an existing block unless `--force`) and rejects an invalid name without writing (BlockScaffolderTest — "skips an existing block unless forced", "rejects an invalid block name").
- [x] T009 [US1] Verify FR-007: `make:block` registers only under `class_exists('WP_CLI')` via `MakeCommand` (block branch) + `CliServiceProvider`; live-verified `wp corex make:block Spotlight` (SC-001).

## Phase 4: User Story 2 — Generate the internals reference from the source (P1)

**Goal**: `docs:generate` regenerates one Markdown page per class from the actual source, so docs can't drift.
**Independent test**: run the generator over the source trees; one page per class is written, grouped by layer, with class summary + public method signatures; a file with no class is skipped.

- [x] T010 [US2] Verify FR-005: `ClassDocReader` reads a class into a `ClassDoc` (namespace, summary, public-method signatures); `MarkdownDocRenderer` renders it to a Starlight page (`tests/Unit/Cli/DocsGeneratorTest.php` — "reads a class into a ClassDoc…", "renders a ClassDoc to Starlight markdown").
- [x] T011 [US2] Verify FR-005 (walk): `DocsGenerator` writes one page per class into `reference/<layer>/<class>.md` across the configured layer→dir map (DocsGeneratorTest — "generates a page per class into a layer subfolder"); live-verified ~194 pages, docs site rebuilds to 213 pages.
- [x] T012 [US2] Verify FR-006: a file with no named class returns null and is skipped; unparseable files are skipped and the run continues (DocsGeneratorTest — "returns null for a file with no named class").
- [x] T013 [US2] Verify FR-007: `docs:generate` registers only under `class_exists('WP_CLI')` via `DocsCommand` + `CliServiceProvider`; the `DocsGenerator`/reader/renderer engines run headlessly (SC-005).
- [x] T014 [US2] Verify FR-008 + SC-003/SC-004: generated `reference/*/` pages are git-ignored (`docs-app/.gitignore`), the hand-written index is kept, and re-running updates the reference with no manual edits.

## Phase 5: Polish & cross-cutting

- [ ] T015 [P] **(P2)** Run the Guard Gate formally on this feature's diff: `clean-code-guard` (engines) + `docs-guard` (`packages/cli/README.md` + the `docs:generate` output shape); fix any reported violation. _Tracked as remediation P2._
- [ ] T016 **(P3)** Address the `MakeCommand` SRP smell — its optional nullable block params blur the model/block branches; extract the block branch (or split the command) so each command path has a single responsibility. _Tracked as remediation P3 (clean-code audit finding); not a spec drift._
- [x] T017 Confirm docs: `packages/cli/README.md` documents all five `make:*` commands + `docs:generate` with examples; DECISIONS #44 (make:block) + #50 (docs:generate) record the approach; PROGRESS reflects completion.

## Dependencies

- Phase 2 (engines) precedes both user stories. US1 (`make:block`) and US2 (`docs:generate`) are independent — different commands, different engines — and independently verifiable.
- T015 (P2) and T016 (P3) are the only **open** tasks; both are already tracked as remediation items.

## Implementation strategy

This spec is retrospective: the MVP (US1 — `make:block`) and US2 (`docs:generate`) are already delivered,
unit-tested (8 + 4 Pest), and live-verified via WP-CLI. The remaining work is the two tracked debts
(T015 formal guard run → P2; T016 `MakeCommand` SRP tidy → P3), executed when their remediation phase runs
— **not** new feature work.

## Parallel opportunities

- T015 [P] (guard run) is independent of T016 (code tidy) and can proceed alongside it during P2/P3.
- US1 and US2 verification tasks touch different files (`Generators/` + `BlockScaffolderTest` vs `Docs/` +
  `DocsGeneratorTest`) and can be verified in parallel.
