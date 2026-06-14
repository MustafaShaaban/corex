# Feature Specification: make:site — client-site platform

**Feature Branch**: `feature/049-make-site`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Make it easy to start a real client website with a team. `wp corex make:site Acme`
generates a client **site plugin** (app/business code) + a **site theme** (presentation), with the right
namespaces/prefixes (AcmeSite\, acme-site, acme/v1, --acme-), a generated config so the `make:*` generators write
into the client plugin/theme (not the framework), governance + workflow docs (AGENTS.md, CLAUDE.md, README,
PROGRESS, .gitignore that ignores local AI/cache), and an optional starter vertical slice (one working
model→service→controller→block→settings example). Team members edit only the client plugin/theme, never the Corex
framework."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generate a client site from one name (Priority: P1) 🎯 MVP

A developer runs `wp corex make:site Acme` and gets a **client site plugin** (`acme-site`) for app/business code and
a **client site theme** (`acme`) for presentation, each wired to the site's own namespace + prefixes —
`AcmeSite\`, text domain `acme-site`, REST namespace `acme/v1`, CSS prefix `--acme-`, option/CPT prefix `acme_` —
**distinct from** Corex's own (`Corex\`, `corex/v1`, `--corex-`). The client code imports Corex base classes but
lives under the client namespace, so the framework and the client work never collide.

**Why this priority**: This is the headline — the leap from "a framework" to "a platform you build client sites
on." Without the generated, correctly-namespaced site structure, team/agency work has nowhere clean to live.

**Independent Test**: Run the generator for a name; a site plugin + theme are created under the client slug with
the derived namespace/prefixes (valid PHP, valid theme), distinct from Corex's, and the framework still loads.

**Acceptance Scenarios**:

1. **Given** a site name, **When** `make:site` runs, **Then** it creates a site **plugin** (`<slug>-site`) and a
   site **theme** (`<slug>`) with the derived namespace (`<Name>Site\`), text domain, REST namespace, and CSS/
   option/CPT prefixes — all distinct from Corex's.
2. **Given** the generated plugin, **When** inspected, **Then** it has a service provider, the app folders
   (Models/Services/Controllers/Api/Blocks/Options), valid PHP, and imports Corex base classes (not the `Corex\`
   namespace for its own classes).
3. **Given** the generated theme, **When** inspected, **Then** it is a valid block theme (style.css, theme.json,
   templates/parts) consuming tokens via the client's CSS prefix.
4. **Given** the generated **site config**, **When** the `make:*` generators run, **Then** they write into the
   client plugin/theme (the client namespace/path/prefix), **not** the Corex framework folders.

---

### User Story 2 - The repo is team- and AI-ready out of the box (Priority: P1)

The generated site includes the files a team + AI agents need to work safely: an **AGENTS.md**/**CLAUDE.md** that
say "this is a Corex client site — edit only the client plugin/theme, never the Corex framework; one feature = one
branch = one spec = one PR; run guards before pushing," a **README**, a **PROGRESS.md**/**DECISIONS.md**, a
**.gitignore** that ignores local AI/cache/notes (`.corex/`, `.ai/`, `.claude/local/`, …) while keeping the
committed project memory, and a `specs/`/`docs/` scaffold.

**Why this priority**: The governance is what makes a *team + AI* workflow safe — without it, agents edit framework
files and local AI history gets committed. It ships with the structure (US1) or it isn't trustworthy.

**Independent Test**: The generated site contains AGENTS.md/CLAUDE.md stating the client-only edit boundary + the
one-feature-one-PR workflow, a README, PROGRESS/DECISIONS, a `.gitignore` ignoring the local AI/cache folders, and
`specs/`/`docs/` scaffolding — accurate to the generated structure.

**Acceptance Scenarios**:

1. **Given** the generated site, **When** opened, **Then** `AGENTS.md` + `CLAUDE.md` state: this is a Corex client
   site; write app code in the client plugin and presentation in the client theme; do **not** edit Corex framework
   folders; use Spec Kit; one feature = one branch = one spec folder = one PR; run guards before pushing; never
   push directly to develop/main.
2. **Given** the generated `.gitignore`, **When** inspected, **Then** it ignores local AI/cache/notes
   (`.corex/docs/`, `.corex/cache/`, `.ai/`, `.claude/local/`, `.codex/local/`) and standard build artifacts, while
   the committed project memory (AGENTS.md, specs, PROGRESS, DECISIONS, docs) is kept.
3. **Given** the generated site, **When** inspected, **Then** it includes a README, PROGRESS.md, DECISIONS.md, and
   `specs/` + `docs/` scaffolding.

---

### User Story 3 - An optional working starter slice (Priority: P2)

With `--starter`, the generated site plugin includes **one small working vertical slice** — a model, repository,
service, a REST/API controller answering the spec-043 envelope, a dynamic block, and a settings/option example,
plus a test and a README explaining how to rename/remove it — so a developer sees the correct Corex way to build,
not empty folders or fake boilerplate.

**Why this priority**: A starter slice teaches the architecture and gives an immediate working example. P2 because
the empty (correctly-namespaced) structure (US1) already delivers a usable site.

**Independent Test**: `make:site Acme --starter` produces a site plugin with a working model→repository→service→
controller(envelope)→block→option example + a test + a "how to remove this" README; the slice uses the client
namespace/prefixes.

**Acceptance Scenarios**:

1. **Given** `--starter`, **When** `make:site` runs, **Then** the plugin includes one vertical slice (model,
   repository, service, REST controller using the envelope, a dynamic block, an option example) wired to the
   client namespace/prefixes, with a test.
2. **Given** the slice, **When** read, **Then** a README explains how to rename/remove it (it is an example, not
   load-bearing).
3. **Given** no `--starter`, **When** `make:site` runs, **Then** the plugin has the correct empty structure (no
   fake/unused boilerplate beyond the minimal provider).

---

### User Story 4 - Generation flags (Priority: P2)

`make:site` accepts flags to shape the output: `--plugin-only` / `--theme-only` (generate one side), `--minimal`
(empty structure) / `--starter` (the vertical slice), and is idempotent/safe (no overwrite without `--force`).

**Why this priority**: Real projects need to (re)generate just the plugin or theme, or choose minimal vs starter.
P2 because the default (`make:site Name`) covers the common case.

**Independent Test**: Each flag produces the expected subset; re-running without `--force` does not overwrite.

**Acceptance Scenarios**:

1. **Given** `--plugin-only` (or `--theme-only`), **When** run, **Then** only that side is generated.
2. **Given** `--minimal` vs `--starter`, **When** run, **Then** the plugin is empty-structured vs carries the
   vertical slice.
3. **Given** an existing site, **When** `make:site` re-runs without `--force`, **Then** it is skipped (no
   overwrite); with `--force` it regenerates.

---

### Edge Cases

- A site name that collides with Corex (`Corex`) or an existing site → rejected/skipped with a clear message.
- An invalid name (spaces, symbols) → normalised to a valid namespace/slug, or rejected with guidance.
- The generated namespace/prefixes MUST NOT equal Corex's (`Corex\`, `corex/v1`, `--corex-`, `corex_`).
- Generated PHP MUST be valid (`php -l` clean); the theme MUST be a valid block theme.
- Re-running with `--force` MUST NOT clobber the developer's own added files outside the generated set (the
  generator writes only its known files).
- The generated AGENTS.md/CLAUDE.md MUST accurately name the client plugin/theme paths it generated.

## Requirements *(mandatory)*

### Functional Requirements

**Generation + naming (US1)**

- **FR-001**: `wp corex make:site <Name>` MUST generate a client **site plugin** (`<slug>-site`) and a client
  **site theme** (`<slug>`), each with the derived identity: namespace `<Name>Site\`, text domain `<slug>-site`,
  REST namespace `<slug>/v1`, CSS prefix `--<slug>-`, option/CPT prefix `<slug>_` — all **distinct from Corex's**.
- **FR-002**: The plugin MUST have a service provider + the app folders (Models/Services/Controllers/Api/Blocks/
  Options) and import Corex base classes (the client code MUST NOT use the `Corex\` namespace for its own classes).
- **FR-003**: The theme MUST be a valid FSE block theme (style.css, theme.json, templates/parts) using the client
  CSS prefix.
- **FR-004**: The generator MUST write a **site config** so the `make:*` generators target the client plugin/theme
  (the client namespace/path/prefix), not the Corex framework.

**Governance + workflow (US2)**

- **FR-005**: The generated site MUST include `AGENTS.md` + `CLAUDE.md` stating: this is a Corex client site; app
  code in the client plugin, presentation in the client theme; **do not edit Corex framework folders**; use Spec
  Kit; one feature = one branch = one spec = one PR; run guards before pushing; never push directly to develop/main.
- **FR-006**: The generated `.gitignore` MUST ignore local AI/cache/notes (`.corex/docs/`, `.corex/cache/`, `.ai/`,
  `.claude/local/`, `.codex/local/`) + build artifacts, while keeping the committed project memory.
- **FR-007**: The generated site MUST include a README, PROGRESS.md, DECISIONS.md, and `specs/`/`docs/` scaffolding.

**Starter slice (US3)**

- **FR-008**: With `--starter`, the plugin MUST include one working vertical slice (model, repository, service, a
  REST controller using the spec-043 envelope, a dynamic block, an option example) wired to the client namespace/
  prefixes, with a test, and a README explaining how to rename/remove it.

**Flags + safety (US4)**

- **FR-009**: `make:site` MUST support `--plugin-only`/`--theme-only`, `--minimal`/`--starter`, and `--force`; it
  MUST be idempotent (no overwrite without `--force`) and write only its known files.

**Cross-cutting**

- **FR-010**: All generated PHP MUST be valid (`php -l` clean); generated REST controllers MUST use the spec-043
  envelope + declare middleware; generated styling MUST be token-only (client prefix) + RTL; no secret in any
  generated file.
- **FR-011**: The generator engine MUST be **pure** (render + plan + naming), the WP-CLI command a thin gated
  boundary (the spec-003 pattern) — so it is unit-tested headlessly. No new hard dependency.

### Key Entities *(include if feature involves data)*

- **Site identity**: derived from the site name — class namespace, plugin slug, theme slug, text domain, REST
  namespace, CSS prefix, option/CPT prefix — all distinct from Corex's. Pure.
- **Site plugin**: the generated client app package (provider + app folders + optional starter slice).
- **Site theme**: the generated client block theme (style.css, theme.json, templates/parts).
- **Governance set**: AGENTS.md, CLAUDE.md, README, PROGRESS, DECISIONS, .gitignore, specs/docs scaffold.
- **Starter slice**: one working model→repository→service→controller(envelope)→block→option example + test + README.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer scaffolds a complete, correctly-namespaced client site (plugin + theme) with **one
  command**, distinct from Corex, with **valid PHP** and a valid block theme.
- **SC-002**: A team member (or AI agent) reading the generated `AGENTS.md`/`CLAUDE.md` knows to edit **only** the
  client plugin/theme and to follow one-feature-one-PR — the client/framework boundary is unambiguous.
- **SC-003**: Local AI/cache/notes are **never committed** (the generated `.gitignore` excludes them) while the
  project memory (specs/PROGRESS/DECISIONS/AGENTS) is kept.
- **SC-004**: With `--starter`, the developer gets **one working** vertical slice (not empty folders, not fake
  boilerplate) using the client namespace/prefixes, with a removal README.
- **SC-005**: The generator never overwrites without `--force`, the framework runs unchanged, and a name colliding
  with Corex is refused.

## Assumptions

- Builds on and **reuses** the spec-003 generator engine (`StubRenderer`/`Naming`/`GeneratorContext`/the multi-file
  scaffolder pattern from `BlockScaffolder`/`ApiResourceScaffolder`), the existing `wp-content/corex-app` app-path
  convention + `app.namespace`/`app.path`/`app.prefix` config, the spec-043 envelope, and the existing theme/plugin
  shapes — this feature adds a `SiteScaffolder` + stubs + a site-identity deriver + the `make:site` command; it does
  not re-spec them.
- The generator engine is **pure**, the WP-CLI command a thin `class_exists('WP_CLI')`-gated boundary.
- Default output: a site **plugin** (`<slug>-site`) + a child/site **theme** (`<slug>`) under the standard plugins/
  themes paths; the `wp/` repo layout + Azure DevOps pipeline are documented and the generated config points the
  `make:*` generators at the client plugin (the `wp/`-layout repo mode is a documented option, the default writes
  to the standard paths).
- The site identity deriver guarantees distinctness from Corex; a name normalising to `corex` is refused.
- Out of scope (explicitly): the Azure DevOps **pipeline/branch-policy automation** + the Corex update packaging
  (spec 050 — team ops & distribution), the generated **design-system SCSS kit** depth (spec 051 DLS), the full
  multi-repo provisioning, and actually creating a git repo / pushing.
- Live confirmation (the generated site loading in WP, the starter slice rendering) requires a running server; per
  the environment gate, the pure generator/naming/render logic is unit-tested headlessly (incl. `php -l` of
  generated PHP) and the live activation runs when the environment is available.
