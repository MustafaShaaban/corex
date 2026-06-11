# Feature Specification: Front-end build pipeline & dynamic block editor registration

**Feature Branch**: `018-build-pipeline-blocks`

**Created**: 2026-06-11

**Status**: Draft (RETROSPECTIVE — documents already-delivered, tested, real-WP-verified code; reconciled to the implementation, not inventing new behavior)

**Input**: User description: "Front-end build pipeline + dynamic block editor registration — @wordpress/scripts compiles each block package's SCSS+JS to build/blocks; every Corex block is dynamic + server-rendered and registers in the editor via ServerSideRender (fixing 'block not supported'); conditional, token-only, RTL assets; a Corex inserter category; add-ons mapped + activated."

> **Retrospective note.** This spec is written after the corresponding code shipped (the autonomous "Finish Corex" items 1–2), to restore spec-first compliance (Principle X). Requirements describe the **existing** behavior in `packages/build-tools`, `plugins/corex-blocks`, the per-package `package.json` build scripts, and the block packages. Any divergence found between this spec and the code is a defect to reconcile, not new scope.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Blocks are recognised and editable in the Site Editor (Priority: P1)

A site builder opens the WordPress Site/Block Editor and inserts a Corex block. The block appears in the
inserter, is recognised by the editor (no "not supported" message), and shows a live preview of its
server-rendered output.

**Why this priority**: This is the headline failure the feature fixes — before it, every Corex block showed
"Your site doesn't include support for this block," making the framework unusable in FSE. Nothing else matters
if blocks can't be placed.

**Independent Test**: Activate the Corex plugins, build the assets, open the editor, insert a Corex block —
it renders a preview and is not flagged unsupported. Verifiable on a real install via the block-type registry
(each `corex/*` type has an editor script) and `do_blocks()` server render.

**Acceptance Scenarios**:

1. **Given** the Corex plugins are active and assets are built, **When** the editor loads, **Then** every
   `corex/*` block type is registered with an editor script and grouped under a "Corex" inserter category.
2. **Given** a Corex block is inserted, **When** the editor renders it, **Then** the preview is the block's
   own server-rendered markup (not a duplicated client implementation) and matches the front end.
3. **Given** a block has no compiled editor script, **When** the editor loads, **Then** that is treated as a
   build/setup defect (the build step is required), not a silent failure.

---

### User Story 2 - A developer builds a block's SCSS + JS with one workflow (Priority: P1)

A developer adds SCSS and JS to a block and compiles it with a single, standard build command; the output
loads only where the block renders, and right-to-left styling is produced automatically.

**Why this priority**: Without a build, there is no SCSS/JS workflow at all (the original gap). The build is
the foundation every later block/form/kit depends on.

**Independent Test**: Run the build for a package; confirm it emits, per block, a compiled stylesheet, an
auto-generated RTL stylesheet, an editor script, and a dependency manifest; confirm the block only enqueues
them when present on the page.

**Acceptance Scenarios**:

1. **Given** a block with `style.scss` imported by its `index.js`, **When** the build runs, **Then** it emits
   a compiled stylesheet, an auto-generated RTL stylesheet, the editor script, and its dependency manifest
   into the package's build output.
2. **Given** a built block, **When** a page renders without that block, **Then** the block's CSS/JS are not
   loaded (conditional assets — Principle VI).
3. **Given** a block's styling, **When** reviewed, **Then** it uses only `theme.json` design tokens and
   logical CSS properties (Principles V and VIII); no hardcoded colours/sizes/fonts.

---

### User Story 3 - The framework runs headlessly and the add-ons are visible (Priority: P2)

The PHP test suite and CLI run without a front-end build present, and all add-on plugins are mapped into the
install and activated so their blocks and patterns appear.

**Why this priority**: Headless testability protects the build-independent core; activating the add-ons is
what makes the wider block/pattern library actually visible to a site builder.

**Independent Test**: Run the Pest suite with no `build/` present (it registers from source and passes);
separately, with add-ons mapped + active, confirm their blocks/patterns are registered.

**Acceptance Scenarios**:

1. **Given** no build output exists, **When** block discovery runs, **Then** it registers from the source
   directory so headless tests pass.
2. **Given** build output exists, **When** block discovery runs, **Then** it registers from the build output
   (so the editor scripts + compiled assets load).
3. **Given** the add-on plugins are mapped into the install, **When** they are activated, **Then** their
   blocks and patterns appear with no PHP fatals.

### Edge Cases

- **Block declared but not built**: the source fallback registers the block, but its editor script/compiled
  style only exist after a build — so the build step is a documented prerequisite for the editor experience.
- **Duplicate block name across packages**: discovery keeps the first and logs the duplicate; the rest still
  register.
- **A renderer throws at render time**: the block yields empty output and a logged warning — never a fatal
  page (non-fatal render).
- **Older WordPress without an FSE editor**: blocks still register server-side and render on the front end.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The build MUST compile each block package's source SCSS + JS into a per-package build output,
  producing for each block: an editor script, a compiled stylesheet, an auto-generated right-to-left
  stylesheet, and a script-dependency manifest.
- **FR-002**: Every Corex block MUST be dynamic and server-rendered by its declared PHP renderer; the editor
  representation MUST preview that same server render rather than a separate client implementation.
- **FR-003**: Each block MUST register in the block editor (an editor script) so the editor recognises the
  block type and does not report it as unsupported.
- **FR-004**: A block's assets MUST load only when the block is present on the page (declared per block);
  no global CSS/JS library is loaded.
- **FR-005**: Block styling MUST consume only `theme.json` design tokens and use logical (RTL-correct) CSS;
  RTL stylesheets MUST be produced automatically by the build.
- **FR-006**: Block discovery MUST register from the build output when it exists and fall back to the source
  directory otherwise, so the headless test suite runs without a build.
- **FR-007**: Editor/view/front-end script handles MUST be wired for translation so their user-facing strings
  are translatable.
- **FR-008**: All `corex/*` blocks MUST be grouped under a single "Corex" inserter category.
- **FR-009**: The add-on plugins MUST be mappable into a WordPress install and activatable such that their
  blocks and patterns register with no PHP fatals.
- **FR-010**: Build artifacts MUST NOT be committed to version control (regenerated on checkout/CI).

### Key Entities

- **Block package**: a plugin/add-on that owns one or more blocks; has a source block directory and a build
  output directory.
- **Block**: a dynamic, server-rendered unit — `block.json` metadata (incl. its PHP renderer), an editor
  script, a token-only stylesheet, and an optional front-end view script.
- **Build output**: the compiled, conditional, RTL-aware assets + dependency manifest a block registers from.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of `corex/*` blocks are recognised in the editor (zero "block not supported" messages) on
  a built, activated install.
- **SC-002**: Every built block emits all four artifacts (editor script, stylesheet, RTL stylesheet,
  dependency manifest); a single command builds all packages.
- **SC-003**: A page without a given block loads none of that block's CSS/JS.
- **SC-004**: The full PHP test suite passes with no build output present (build-independent core).
- **SC-005**: With the add-ons activated, all their blocks and patterns are registered and the site boots
  with zero PHP fatals.
- **SC-006**: No hardcoded colour/size/font appears in any block stylesheet (token-only); each block ships an
  RTL stylesheet.

## Assumptions

- The "users" are developers building the framework and site builders using the editor; there is no
  end-customer-facing surface in this feature.
- A working WordPress 7.0+ / PHP 8.3+ install with the monorepo mapped into `wp-content` (Environment Gate).
- The Node/npm toolchain is available; `npm install && npm run build` is an accepted prerequisite for the
  editor experience (build artifacts are git-ignored).
- The block-engine discovery/registration primitives (BlockMap, DynamicBlockRegistrar, BlocksServiceProvider)
  from spec 004 exist and are reused; this feature adds the build + editor-registration layer on top.
- Visual/pixel correctness of blocks in a browser is verified manually (out of scope for automated tests here).
