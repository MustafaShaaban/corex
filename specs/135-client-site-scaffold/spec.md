# Feature Specification: The client site scaffold

**Feature Branch**: `spec/135-client-site-scaffold` (this file), then one branch per slice

**Created**: 2026-11-21

**Status**: Reviewed on 2026-11-22 (`corex-reviewer`, against the constitution, `main` and the tag `v0.48.1`);
what it found is answered in the text below

**Input**: The Blackstone gap programme, wave 1 (DECISIONS #651, #652, #655). Four gaps the Blackstone eIT site found
in what `wp corex make:site` generates on v0.48.1: G-006 (the client theme), G-007 (the site plugin), G-002 (the
agent files), G-001 (which repository this is). The gap rows are in that site's `docs/corex-gaps.md`, which CoreX
sessions only read.

## Why this spec exists

A site generated today cannot start. Its theme is a theme of its own with an empty `theme.json`, so CoreX's block
styles read tokens nobody defines and the header, mega menu and footer patterns with their script and style do not
exist for it. Its plugin ends in a comment and boots nothing, so a block written by `make:block` is never
registered and has nothing to build it. Its agent files teach a workflow CoreX retired on 2026-11-11. And until
the new repository has an `origin`, `npm run verify:framework` takes it for the framework's own repository and
passes without comparing a file.

The owner decided the theme model on 2026-11-21 (DECISIONS #655): a generated client theme is a child of the
CoreX theme, unless a re-check showed the child model breaks something. That re-check is the first part of this
spec. The planning workspace's story US-BRD-11 covered the same gap with the opposite model (a standalone copy,
its decision BRD-D8); the owner's later answer reverses both, and the story is corrected there, not here.

### The re-check DECISIONS #655 asks for

Run on 2026-11-21 against `main` at `598fba79`, on a WordPress 7 install of this branch's own with every CoreX
plugin and add-on active and a hand-made child theme (`Template: corex`) switched on. Each row was read off the
running site (`wp_get_global_settings()`, `get_block_templates()`, the pattern registry, the page over HTTP),
except multisite, which was read in WordPress's source.

It was run on `main` only. Every row but one is WordPress's own inheritance and holds whatever the parent
contains. The one that leans on CoreX's code, the header system, was also read at the tag `v0.48.1`, where
`NavigationServiceProvider` finds its files with the same `get_theme_file_path`. The parent on
`release/gap-line` has other tokens, a second style variation and no `functions.php`, so the coordinating
session runs the same probe on the line before the tag (the probe is in the session's notes folder).

| Asked | Found | Verdict |
|---|---|---|
| `theme.json` inheritance | `settings.custom` merges key by key: the child changed `custom.color.accentHover` and `custom.nav.breakpoint` and kept the other 16 colour keys and every scale. Font sizes, spacing sizes, shadows and font families a child does not declare are the parent's. `styles` merges: the child changed the button radius and kept the button colour. **A preset list the child declares replaces the parent's whole list**: a child palette of two colours left the site with two, and `--wp--preset--color--surface` was gone. `customTemplates` merges by position: a child list of one entry overwrote the parent's first and "Landing page" lost its title | Holds, with one rule: a list the child declares must name every entry of the parent's. FR-011 and FR-014 |
| Templates | All 15 of the parent's templates resolve for the child. A file of the same name in the child wins (`front-page.html` did). A child needs no `index.html` | Holds |
| Parts | `header` and `footer` resolve from the parent; a child `parts/header.html` wins | Holds |
| Patterns | All 26 patterns of the theme register under the child, beside the child's own `patterns/` (36 `corex/*` in all with those `corex-ui` registers under any theme) | Holds |
| Style variations | The parent's `styles/dark.json` is offered under the child. A child file of the same name replaces it | Holds; the docs say a variation the parent ships keeps CoreX's colours until the child ships a file of the same name |
| The header system | `corex-navigation.css` and `corex-navigation.js` are found and served from the parent (`get_theme_file_path` falls back to it), and a child header that uses `corex/header-corporate` renders with both. `--wp--custom--nav--breakpoint` printed the child's value | Holds |
| Fonts | The parent's four families are served from the parent's folder, on the page and in the editor's canvas, whether the child leaves them out or lists them with the parent's `file:./` sources (a source the child does not hold falls back to the parent's folder). The heading preload follows them | Holds |
| The parent's directory name | `corex` everywhere it is installed: `scripts/setup-wordpress.ps1`, the `dist` builder, the Dockerfile, CI's provisioning. `Template: corex` is therefore right on every route | Holds |
| The coming-soon template | The child's `templates/coming-soon.html` replaces the default `corex-config` registers, as a standalone theme's does today | Holds |
| The `dist` layout | The builder already copies `theme/` to `wp-content/themes/corex` on every build and the client theme beside it. Nothing checks that a child's parent is in the artifact | Holds; FR-017 adds the check |
| Multisite | WordPress asks whether the active stylesheet is allowed on the site or the network (`WP_Theme::is_allowed()`); the parent only has to be installed | Holds; read, not run |
| CI: "A generated client site passes every root check" | The job activates the generated theme on an install that has the CoreX theme, builds the artifact with both, and serves the coming-soon page | Holds; the job gains the new checks |
| CI: "A release candidate merges into a client repository" | Unaffected by the theme model: it merges and runs the client plugin's tests | Holds |
| Found beside the list | The parent's `functions.php` runs under a child, so a client site with no site icon would show the CoreX favicon | FR-016 |

**Verdict: the child model breaks nothing, so the shared assets stay in the theme and no plugin route is built.**
The one trap, a partial list, is closed by generating full lists and by a check that keeps them full when a later
release adds a slug (DECISIONS #660).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A new client site looks finished and takes the client's values (Priority: P1)

A developer runs `wp corex make:site Acme --dir=sites/acme`, switches the generated theme on, and opens the site.
It has CoreX's header and footer, CoreX blocks draw with colour, and every token is listed in the theme's
`theme.json` with CoreX's value, ready to be changed to Acme's.

**Why this priority**: Without it nothing visible can be built on a generated site (G-006, High).

**Independent Test**: Generate a site on a scratch install, activate its theme, and read the palette, a page's
header and the navigation stylesheet off the running site.

**Acceptance Scenarios**:

1. **Given** a freshly generated site, **When** its theme is active, **Then** WordPress reports the CoreX theme as
   its parent and the site has every colour, size, family, shadow and `custom` token the CoreX theme has.
2. **Given** the same site, **When** a page with the header part is opened, **Then** the header is CoreX's and the
   navigation style and script are served.
3. **Given** the generated `theme.json`, **When** one palette value is changed, **Then** that colour changes on the
   site and no other token is lost.
4. **Given** a client theme whose palette is missing a slug the CoreX theme defines, **When**
   `npm run verify:client-theme` runs, **Then** it fails and names the theme, the list and the slug.
5. **Given** a site with no site icon running a child theme, **When** a page is opened, **Then** no CoreX favicon
   is linked.
6. **Given** `npm run build:dist -- --client=acme`, **When** it finishes, **Then** the artifact holds
   `wp-content/themes/corex` and `wp-content/themes/acme-theme`, and `npm run verify:dist` passes.

### User Story 2 - A site block registers and builds (Priority: P1)

In a generated site a developer runs `wp corex make:block Banner`, then `npm install` and `npm run build` in the
site plugin, and the block is in the editor and renders on a page.

**Why this priority**: A client site cannot have a block or any code of its own until its plugin boots (G-007,
High).

**Independent Test**: In CI, generate a site, generate a block in it, build it, activate the plugin, and ask
WordPress whether the block is registered and what it renders.

**Acceptance Scenarios**:

1. **Given** a generated site plugin and `corex-core` active, **When** WordPress loads, **Then** the site's service
   provider has registered and booted once, whichever of the two plugins WordPress loaded first.
2. **Given** `corex-core` is not active, **When** WordPress loads with the site plugin active, **Then** nothing
   boots and no PHP error is raised (WordPress's own notice about the unmet plugin requirement aside).
3. **Given** a block generated by `make:block` and built, **When** WordPress loads, **Then** the block is
   registered from `build/blocks`, its renderer is the generated class, and its script's translations are bound to
   the site's text domain.
4. **Given** a freshly generated site with no block, **When** `npm install && npm run build` runs in the plugin,
   **Then** it exits 0.
5. **Given** a site generated with `--starter`, **When** it is built and loaded, **Then** its example block is
   registered by the same route.

### User Story 3 - A new client repository is checked from its first commit (Priority: P2)

A developer creates a client repository from a CoreX release, generates the site, and runs
`npm run verify:framework` before any remote exists. The framework's files are compared with the recorded
release.

**Why this priority**: A false pass hides the one mistake the check exists for, on the first day (G-001, Low on
the Blackstone site because it lasts until `origin` is added).

**Independent Test**: A scratch repository with a baseline and no remote: the role is `client` and a changed
framework file is reported as drift.

**Acceptance Scenarios**:

1. **Given** a repository with `sites/acme/corex-baseline.json`, no `origin` and no CI variable, **When** the role
   is resolved, **Then** it is `client`.
2. **Given** CoreX's own repository (named by CI or by `origin`), **When** someone commits a `sites/` folder with
   a baseline, **Then** the role is still `framework` and the repository's own rule against `sites/` fails.
3. **Given** a checkout that resolves to `framework` from its name and holds a baseline, **When**
   `verify:framework` runs, **Then** it fails and says how to state the role, instead of passing with nothing
   compared. With `COREX_REPOSITORY_ROLE=framework` stated, it passes as before.

### User Story 4 - An agent in a new client site follows the CoreX loop (Priority: P3)

An agent opens a generated site, reads its `AGENTS.md` or `CLAUDE.md`, and works the way CoreX works today.

**Why this priority**: The generated files are the first thing an agent reads; today they teach retired commands
(G-002, Low: the Blackstone site rewrote its own by hand).

**Independent Test**: Read the two generated files: the loop's steps, the routing table and the UI steps are
there, and no retired name is.

**Acceptance Scenarios**:

1. **Given** a generated site, **When** its `AGENTS.md` is read, **Then** it names the loop's steps in order, the
   model routing table and the UI steps, and where each is described in full.
2. **Given** every file `make:site` writes, **When** searched for "Spec Kit" or "speckit", **Then** nothing is found.

### Edge Cases

- **The CoreX theme cannot be found at generation.** `make:site` stops before writing a file and names the path it
  looked in. A child with no token list would be the gap again.
- **`--plugin-only`** needs no parent theme and must not fail for the lack of one.
- **A standalone client theme** (Muva, Perego, any site generated before this spec; Mizzey has no client theme
  in a `sites/` folder): `verify:client-theme` says it is standalone and checks nothing in it; the `dist` builder
  ships it as before; nothing rewrites it.
- **`--force` on an existing site** rewrites what `make:site` generates, as today, with one exception: a theme
  that already has a `style.css` keeps its `style.css` and its `theme.json`, and the command says it kept them
  (FR-019). Before this spec `--force` replaced a client's tokens with the empty stub; after it, it would have
  turned a standalone theme into a child. It deletes nothing: a `templates/index.html`, `templates/front-page.html`,
  `parts/header.html` or `parts/footer.html` left by an older generation still wins over the parent's.
- **A release changes a token's value in the CoreX theme.** A child keeps the value it lists: that is the model.
  Only a new entry, or a font file that moved, needs a step, and `verify:client-theme` names both.
- **`brand.json`** in a client theme works as before (FR-020).
- **A child that declares `customTemplates` or `templateParts`** must repeat the parent's entries
  (`verify:client-theme` checks it); the generated `theme.json` declares neither.
- **A later CoreX release adds a palette slug.** The client's list no longer names every parent slug:
  `verify:client-theme` fails in the client's CI on the update branch and names the slug. The release's client
  impact line says so too.
- **The site plugin is loaded after CoreX has booted** (activated in the same request): the provider still boots
  once.
- **Two site plugins in one repository**: each boots its own provider and registers its own block folder.
- **A block folder with no `block.json`**, or a renderer class that does not exist: skipped or rendered empty as
  `corex-blocks` already does for its own; never fatal.
- **A framework developer's checkout with a scratch site**: the role stays `framework` when `origin` names CoreX's
  repository; `verify:framework` then fails with the message of FR-003, which is true: there is a baseline and
  nothing compared it.

## Requirements *(mandatory)*

### Functional Requirements

**Which repository this is (G-001)**

- **FR-001**: `resolveRole()` MUST decide in this order: an explicit `COREX_REPOSITORY_ROLE`; then a repository
  named by CI or by `origin`, which is `framework` when it is CoreX's own and `client` otherwise; then, for a
  repository nothing names, `client` when a baseline is recorded and `framework` when none is.
- **FR-002**: "A baseline is recorded" MUST have one definition, a `sites/<client>/corex-baseline.json` on disk,
  exported from `scripts/repository-ownership.mjs` and used by `scripts/verify-framework.mjs`,
  `scripts/client-merge-check.mjs` and `tests/repo-hygiene.test.js` alike.
- **FR-003**: `verify:framework` MUST fail, not pass, when the role resolves to `framework` from the repository's
  name and a baseline is recorded, with a message that names the baseline file and
  `COREX_REPOSITORY_ROLE=client`. A role stated in `COREX_REPOSITORY_ROLE` is taken at its word.

**The agent files (G-002)**

- **FR-004**: The generated `AGENTS.md` MUST describe the CoreX loop as a client site runs it (grill, research
  when the spec depends on outside facts, a spec with requirement ids and slices in the site's own
  `specs/NNN-name/spec.md`, owner-only steps as a checked script, one slice at a time test first, the Guard Gate,
  the measured UI review, the two-model review, the pull request, a retro after a release) and point at the root
  `AGENTS.md` for each step in full. For every file the root loop writes at the repository root (`specs/`,
  `DECISIONS.md`, `PROGRESS.md`, `docs/_glossary.md`, `design/handoffs/`) it MUST name the client's own path
  under `sites/<client>/` and say a file written at the root is drift.
- **FR-005**: It MUST carry the model routing table (who does what, on which model, at which effort, with the five
  agents in `.claude/agents/`), that agents never commit, and that max effort is the owner's call. It MUST say a
  brief to an agent names Client Site Mode, the site's folder and the site's spec. `.claude/agents/corex-builder.md`
  refuses `sites/<client>/` today; it MUST allow it when the brief says the work is a client site's, and then
  edit nothing outside that folder.
- **FR-006**: It MUST carry the UI steps: the client's approved design is the contract; what that design does not
  draw is designed first and approved before it is built; tokens from `theme.json`, logical properties; the
  rendered page is measured, then reviewed against the design; accessibility and copy are reviewed.
- **FR-007**: No file under `packages/cli/stubs/site/` may name Spec Kit, a `/speckit-*` command or "SPEC KIT
  STATUS". `CLAUDE.md` MUST mirror `AGENTS.md` and say where the loop and the routing table are.

**The client theme is a child (G-006)**

- **FR-010**: The generated `style.css` MUST declare `Template: corex`, and its description, the generator's
  comments and the generated `README.md` MUST say the theme is a child of the CoreX theme.
- **FR-011**: The generated `theme.json` MUST list, with the CoreX theme's values at the moment of generation,
  every preset list the CoreX theme declares (colours, font families, font sizes, spacing sizes, shadows; and
  gradients or duotones should it gain any), the whole of `settings.custom`, and `settings.layout`. It MUST
  declare no `styles`, `templateParts` or `customTemplates`. The lists are read from the CoreX theme's own
  `theme.json`; no second copy of them is kept in a stub.
- **FR-012**: The CoreX theme's `theme.json` is looked for at `theme/theme.json` in the repository the CLI runs
  from, then in the installed theme `corex`. When neither can be read, `make:site` MUST fail before writing
  anything and name both paths. `--plugin-only` MUST NOT need it.
- **FR-013**: The generator MUST write `templates/coming-soon.html` in the theme (the launch page is the
  client's from the first day, spec 101), and MUST NOT write `templates/index.html`, `templates/front-page.html`,
  `parts/header.html` or `parts/footer.html`: those are the parent's until the client's design replaces them.
  `--starter` adds what it adds today.
- **FR-014**: `npm run verify:client-theme` (Node built-ins only, like `verify:framework`) MUST check every client
  theme under `sites/`, in the flat layout and the older `themes/` one, that declares `Template: corex`. In its
  `theme.json` and in each of its `styles/*.json`: each preset list it declares names every slug of the CoreX
  theme's same list; a `customTemplates` or `templateParts` list it declares names every entry of the CoreX
  theme's; and every `file:./` font source it lists exists in the client theme or in the CoreX theme. It prints
  one line per finding, exits non-zero on any, reports a standalone theme as standalone, and passes when there
  is nothing to check.
- **FR-015**: The generated `site-<client>.yml` workflow, the generated `UPDATING-COREX.md` checklist and the
  framework's CI job "A generated client site passes every root check" MUST run that check.
- **FR-016**: The CoreX theme MUST link its favicon only when it is the active theme itself, never under a child.
- **FR-017**: The `dist` builder MUST ship the CoreX theme and the client theme together (it does; a test holds
  it for a child), and `verify:dist` MUST fail when a theme in the artifact names a `Template:` the artifact does
  not hold.
- **FR-018**: The docs MUST say what is true: `docs/en/04-team-workflow/client-site-workflow.md` (what a child
  inherits, what it overrides and how, the full-list rule, the variations the parent ships, the position rule
  for custom templates, where `brand.json` stands), `docs/en/05-deployment/shared-host-dist.md` (both themes ship; the parent stays installed and is
  not the active theme), `docs/en/05-deployment/updating-a-client-site.md` and the generated checklist (a release
  can change the parent; read the client impact, run `verify:client-theme`), `packages/cli/README.md`, and each
  Arabic page that mirrors one of these.
- **FR-019**: Nothing may convert or rewrite an existing site's theme. `make:site --force` MUST leave the
  `style.css` and `theme.json` of a theme that already has a `style.css` as they are, and say so. The docs MUST
  give the steps by which a site generated before this spec becomes a child by hand, and how it takes the new
  agent files (from a site generated into a scratch folder).
- **FR-020**: A `brand.json` in the client theme MUST keep working as today: read from the child first, applied
  at runtime to the child's own `theme.json` data before WordPress merges the parent's in, a palette or font
  family list in it that lacks a role CoreX's blocks need dropped with a warning (`ThemeServiceProvider`,
  `BrandOverrideValidator`). The docs say a generated site's values go in the child's `theme.json` and that a
  `brand.json` still wins over it.

**The site plugin boots and its blocks build (G-007)**

- **FR-030**: The generated plugin file MUST declare `Requires Plugins: corex-core`, autoload the site's namespace
  from `src/`, and boot the site's service provider through `Corex::onReady`, once, whichever plugin WordPress
  loads first and also when the site plugin is loaded after CoreX has booted. With `corex-core` inactive it MUST
  do nothing and raise nothing.
- **FR-031**: The generated provider MUST receive CoreX's container, keep `register()` and `boot()`, and register
  the site's blocks on `init`. It is a class of the site's own and does not extend
  `Corex\Foundation\ServiceProvider`, whose `subscribers()` and `controllerPaths()` only CoreX's own provider
  lifecycle reads.
- **FR-032**: `corex-blocks` MUST offer one public way to register a folder of blocks, a service resolved from
  the container: the built folder when it exists, the source folder otherwise; each directory with a
  `block.json` registered through `DynamicBlockRegistrar`. The generated provider calls it with `build/blocks`
  and `src/Blocks`, and does nothing when the container does not offer the service.
- **FR-033**: A block's script translations MUST be bound to the block's own `textdomain` from `block.json`, and
  to `corex` when it names none. Every CoreX block names `corex` today, so none changes. Muva and Perego register
  their blocks with `register_block_type` themselves, never through the registrar (read in both repositories on
  2026-11-22), so neither changes.
- **FR-034**: The generated site plugin MUST have a `package.json` with `build` and `start` scripts on the
  convention `packages/build-tools/README.md` documents (`src/Blocks` to `build/blocks`) and its own
  `@wordpress/scripts` dependency at the framework's range; a test holds that range, and the `--starter` theme's,
  equal to the root's. A site with no block MUST build with exit 0. The generated `.gitignore` MUST keep `node_modules` and `build` out of git at any
  depth.
- **FR-035**: `--starter` MUST use the same plugin file and the same block route: its example block is discovered,
  not registered by hand, and is named in the plugin's block namespace. Its provider binds and resolves its
  services through the container it is handed, never with `new` inside a method.
- **FR-036**: The framework's CI job "A generated client site passes every root check" MUST generate a block in
  the generated site with `make:block`, build the site plugin from a clean install, activate it, and assert the
  block is registered and renders.
- **FR-037**: The docs MUST say how a site gets a block (`packages/cli/README.md`,
  `packages/build-tools/README.md`, `client-site-workflow.md`, the generated `AGENTS.md` and `README.md`).
- **FR-038**: `verify:dist` MUST fail when a client plugin in the artifact holds a block source folder
  (`src/Blocks/*/block.json`) and no `build/blocks`, as it already fails for a theme that ships sources without
  their compiled output.

**Every slice**

- **FR-040**: A test of generated output MUST read what it compares against from the repository (the CoreX
  theme's `theme.json`, the root `package.json`), never from a number or a list written into the test, so the
  slice carries to `release/gap-line` where the theme's tokens differ.
- **FR-041**: No new literal English reaches a person untranslated, nothing is looked up by an English slug, and
  nothing requires Polylang or WPML (the rules that apply to every change from wave 1). The site stubs keep the
  client's own text domain.

### Key Entities

- **Client theme**: a block theme under `sites/<client>/` that is a child of the CoreX theme. It owns values
  (`theme.json`), and the templates, parts and patterns the client's design calls for.
- **Parent theme**: the CoreX theme, installed as `corex`. It owns the token names, the block styles and the
  header system. On a client site it changes only when the client takes a release.
- **Baseline**: `sites/<client>/corex-baseline.json`, the release a client repository's framework files are
  compared with.
- **Block folder**: a plugin's `src/Blocks` (source) or `build/blocks` (built), one directory per block, each with
  a `block.json`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a freshly generated site with its theme active, the number of colours, font sizes, spacing sizes,
  shadows and `custom` keys WordPress reports equals the CoreX theme's own, and the header renders with the
  navigation style and script served.
- **SC-002**: A block made by `make:block` in a generated site is registered and renders after `npm install`,
  `npm run build` and nothing else.
- **SC-003**: `verify:framework` in a repository with a baseline and no remote reports drift for a changed
  framework file.
- **SC-004**: A search of everything `make:site` writes finds no retired workflow name.
- **SC-005**: Muva's, Perego's and Mizzey's repositories merge the release with no new conflict and no changed
  page (`scripts/client-merge-check.mjs`, run by the coordinating session before the tag).

## Slices

One pull request each, built in this order. Every slice names the gap table rows it fills in `PROGRESS.md` and
writes its `### Client impact` lines under `[Unreleased]`, "Gap programme, wave 1".

| # | Slice | Requirements | Waits on | Principles | Rests on anything `main` gained after v0.48.1 |
|---|---|---|---|---|---|
| 1 | The repository role (G-001) | FR-001 to FR-003, FR-040 | nothing | X | No |
| 2 | The agent files (G-002) | FR-004 to FR-007, FR-040, FR-041 | nothing | X (the generated files teach spec first) | **Yes**: the loop in the root `AGENTS.md`, the constitution v2.0.0 and `.claude/agents/` all arrived with #458 (`fe8bcfad`), after the tag. Carried only together with that commit (docs and process files), or it waits for `main`: the coordinating session's call under #651 point 4. The Blackstone site rewrote its own pair by hand, so it does not wait on this |
| 3 | The client theme is a child (G-006) | FR-010 to FR-015, FR-017 to FR-020, FR-040, FR-041 | 2 (same stubs and tests) | I, V, VIII | No. It edits `ci.yml`, `package.json`, the `dist` verifier and docs that `main` has also changed since the tag, so the carry may need a hand-resolved hunk |
| 4 | The CoreX favicon stays off a child theme | FR-016 | 3 | I | **Yes**: `theme/inc/favicon.php` is spec 134's. Not carried to the line, where the theme links no favicon |
| 5 | The site plugin boots and its blocks build (G-007) | FR-030 to FR-038, FR-040, FR-041 | 3 | II, IV, VI | No. `DynamicBlockRegistrar.php` is unchanged since the tag and is also changed by spec 136 (another method). It adds a step to the `ci.yml` job `main` has changed since the tag, so the carry may need a hand-resolved hunk |

Nothing here is seen by a visitor, so no slice has a design step. Slice 4 removes a link from a page's head and
draws nothing.

## Assumptions

- The CoreX theme is installed as `corex` on every site (measured above), so `Template: corex` needs no option.
- A client repository on `main`'s line carries the framework's `.claude/agents/`, so the routing table's agents
  exist there. At the tag `v0.48.1` there are none, which is why slice 2 is not carried alone.
- `packages/build-tools` stays a convention and a README, not a package a client installs: a client package must
  build from a clean install of its own folder (the generated workflow installs no npm package at the repository root, on purpose).
- The block stub (`packages/cli/stubs/block/`) is spec 136's. This spec registers whatever folder holds a
  `block.json` and does not edit the stub. The `--starter` example block (`packages/cli/stubs/starter/`) is this
  spec's and follows the registration route only; bringing it to spec 136's editor standard is a follow-up for
  whichever lands second.
- `make:block` in a site names blocks `<plugin slug>/<block>` (`acme-site/banner`) and uses the site's text
  domain, as `ClientSitePlugin` derives them today. This spec does not change the name.

## Out of scope

- Converting Muva, Perego or any existing site (DECISIONS #655).
- A generated `styles/dark.json`, header or footer for the client: the header system is spec 137's, and the dark
  variation is the parent's until a client's design has one.
- A generator for patterns, templates, post types or taxonomies (G-018, spec 151).
- Loading a site provider's `subscribers()` or `controllerPaths()` through CoreX's provider lifecycle: a site
  wires its hooks and routes in `boot()`. The `--starter` example's controller registers its route by hand with
  its own permission callback, short of principle VII's declared middleware; that is as it was before this spec
  and is a follow-up, not part of G-007.
- The Site Health probe's sentence about a site with no `brand.json` ("neutral default tokens"), and
  `docs/en/05-deployment/updates-and-distribution.md`, which says the same: both describe `brand.json`, which
  this spec leaves as it is. Rewording them for a child theme is a follow-up.
