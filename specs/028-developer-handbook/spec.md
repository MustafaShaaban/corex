# Feature Specification: Developer & operations handbook (in-repo docs/)

**Feature Branch**: `feature/028-developer-handbook`

**Created**: 2026-06-12

**Status**: Draft (forward spec — precedes content; full Spec Kit flow)

**Input**: A beginner-first, GitHub-native documentation set for people who **set up, operate, deploy, and contribute to** a Corex project — multi-OS local setup, Docker dev/prod, deployment recipes (Azure/AWS/cPanel + CI/CD), team workflow, and cookbooks — built in-repo as Markdown with Mermaid, scaffolded for future Arabic translation.

> **Scope decision (split-by-audience, user-approved 2026-06-12).** The published product/API docs and the
> **generated class reference** stay in `docs-app/` (Astro + Starlight, spec 022; `wp corex docs:generate`,
> DECISIONS #50). This module is the **contributor & operations handbook** under `/docs` — GitHub-native
> Markdown that renders without a build, holding the content docs-app does **not** cover. It **does not
> duplicate** architecture or the class reference; it **links** to docs-app for those. This fits what
> `COREX-FRAMEWORK.md §4` already reserves `docs/` for (derived/supplementary docs).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A new developer gets Corex running on their machine (Priority: P1)

A developer with **zero** prior Corex experience opens `/docs`, picks their OS, and follows a step-by-step
guide that gets a working Corex install on their machine — every tool they need is introduced (what it is +
install per OS + a verification command), every command is copy-paste-ready and followed by its expected
output.

**Why this priority**: The first hour decides whether someone can use the framework at all. docs-app today
only covers WAMP + wp-env; real teams run XAMPP, Linux, and macOS too.

**Independent Test**: On a clean machine for each target OS, follow the matching guide top-to-bottom; the site
boots (`wp theme list` shows `corex`, `wp plugin list` shows the core plugins) with no undocumented step.

**Acceptance Scenarios**:

1. **Given** a chosen OS (Windows+WAMP, Windows+XAMPP, Linux, macOS, wp-env/Docker), **When** the guide is
   followed, **Then** Corex is installed and the site boots, with each prerequisite tool introduced inline
   (description + per-OS install + verify command + expected output).
2. **Given** any command in the guide, **When** it is shown, **Then** it has an explicit language tag and is
   followed by its **expected output** in a separate fenced block.
3. **Given** a first-time reader, **When** they read a page, **Then** no step is skipped as "obvious" and the
   words "simply"/"just" do not appear.

---

### User Story 2 - The team spins the project up with one command (Docker) (Priority: P1)

A developer runs one command and gets the whole stack (php-fpm, web server, database, cache, mail catcher)
running, with the Corex monorepo correctly mapped into WordPress — and knows the exact commands to tear it
down, reset the DB, and run the tests inside the container.

**Why this priority**: "Works on my machine" dies when the stack is one command. The monorepo's
junction/symlink mapping is the part teams get wrong.

**Independent Test**: From a clean checkout, `docker compose up` brings the stack up; the site is reachable;
the documented teardown/reset/test commands work; the monorepo plugins/theme are mapped (not copied).

**Acceptance Scenarios**:

1. **Given** the Docker dev setup, **When** the bring-up command runs, **Then** the stack starts and the site
   is reachable, with the monorepo `plugins/`, `theme/`, and `addons/` mounted into `wp-content/` (the
   volume/mapping strategy is documented and respects the monorepo layout).
2. **Given** the running stack, **When** the documented commands run, **Then** the reader can tear it down,
   reset the database, and run the Pest/JS tests **inside** the container — each command followed by expected
   output.
3. **Given** production, **When** the multi-stage Dockerfile is built, **Then** it produces a lean runtime
   image (no dev tooling/source maps) per the framework's build→package stages (§19).

---

### User Story 3 - An operator deploys Corex to their target (Priority: P1)

An operator picks their deployment target and follows a complete recipe — provisioning, config, deploy, SSL,
secrets, backups, rollback, zero-downtime, and the CI/CD wiring — with the commands and expected output for
each step.

**Why this priority**: Deployment is where undocumented frameworks fail in production. This is the largest
gap docs-app does not address at all.

**Independent Test**: For each target, the recipe is complete enough that an operator with no prior Corex
DevOps experience can deploy a release tag end-to-end without external guesswork.

**Acceptance Scenarios**:

1. **Given** a target (Azure App Service, Azure VM, AWS Beanstalk, AWS EC2+RDS, cPanel shared hosting),
   **When** the recipe is followed, **Then** a tagged Corex release is deployed and serving over HTTPS.
2. **Given** the deploy, **When** the recipe is read, **Then** it covers secrets management, backups, rollback,
   and zero-downtime — and the CI/CD wiring to automate it.
3. **Given** shared hosting (no symlinks), **When** the cPanel recipe is followed, **Then** the monorepo is
   deployed via the documented no-symlink strategy (and the constraint is called out, not assumed away).

---

### User Story 4 - A contributor learns how the team works (Priority: P2)

A new contributor reads one section and understands the team's way of working: onboarding, the git branching
model + commit conventions + PR review, how the team uses Claude Code + Spec Kit on this repo, and the quality
gates (guard skills, Pest, Playwright).

**Why this priority**: The workflow is the project's durable value; a contributor must be able to follow it
without a mentor. It restates the authoritative process docs for a newcomer audience (linking, not forking).

**Independent Test**: A new contributor, after reading the team-workflow section, can open a correctly-named
branch, make a conventional commit, run the right guard, and open a PR that passes CI — unaided.

**Acceptance Scenarios**:

1. **Given** the team-workflow section, **When** read, **Then** it explains onboarding, branching (git-flow-
   lite), Conventional Commits, PR review, the Spec Kit loop, and the Guard Gate — **linking** to the
   authoritative `COREX-WORKING-GUIDE.md` / constitution rather than duplicating their rules.
2. **Given** the quality gates, **When** described, **Then** each gate names when it runs and how to run it.

### Edge Cases

- A page that would repeat architecture or class-reference content instead **links** to the docs-app page —
  single source of truth (no drift).
- A referenced class/command that is **planned but not yet built** is marked `stability: planned` and linked to
  the Spec Kit module that will produce it (never invented).
- Diagrams use Mermaid that **renders natively on GitHub** (no external image build).
- The handbook introduces **no** new runtime dependency or build tool not already approved in
  `COREX-FRAMEWORK.md` (redis/mailpit/etc. appear only as documented dev-stack options, never as framework
  deps).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The handbook MUST live in-repo at `/docs` as GitHub-native Markdown (renders on GitHub with no
  build), organized `docs/{README.md, _glossary.md, _translation-memory.md, en/, ar/}` with the agreed section
  folders under `en/` (00-getting-started, 04-team-workflow, 05-deployment, 06-cookbooks, 07-troubleshooting,
  08-contributing — and links into docs-app for 01-architecture / 03-class-reference).
- **FR-002**: It MUST provide separate, complete getting-started guides for **Windows+WAMP, Windows+XAMPP,
  Linux (Ubuntu/Debian), macOS, and wp-env/Docker** — each introducing every prerequisite tool inline
  (one-line description + install per OS + verify command + expected output).
- **FR-003**: Every command block MUST have an explicit language tag and MUST be followed by its **expected
  output** in a separate fenced block; pages MUST be beginner-first (no "simply"/"just", no skipped steps).
- **FR-004**: It MUST document a one-command **Docker dev stack** (php-fpm, web server, database, cache, mail
  catcher) with a **volume/mapping strategy that respects the monorepo** (plugins/theme/addons → wp-content),
  plus up/down/reset-DB/run-tests-in-container commands; and a **multi-stage production Dockerfile**.
- **FR-004 guardrail**: the Docker stack MUST NOT add any service as a framework runtime dependency — it is a
  documented development convenience only.
- **FR-005**: It MUST provide complete, step-by-step **deployment recipes** for: Azure App Service, Azure VM,
  AWS Elastic Beanstalk, AWS EC2+RDS, and cPanel shared hosting — each covering provisioning, config, deploy,
  HTTPS, **secrets, backups, rollback, zero-downtime**, and **CI/CD** wiring; deploys are from **release tags**
  (per `COREX-FRAMEWORK.md §19`).
- **FR-006**: It MUST include a **team-workflow** section (onboarding, git-flow-lite, Conventional Commits, PR
  review, the Claude Code + Spec Kit loop, the quality gates) that **links** to the authoritative
  `COREX-WORKING-GUIDE.md` + constitution rather than duplicating their rules.
- **FR-007**: It MUST include **cookbooks** for complex scenarios (e.g. WooCommerce detect-and-defer,
  multisite, headless mode, AI-agent-driven flows, paid add-ons) — each its own page with ≥2 examples of
  different shapes — and a **troubleshooting** section.
- **FR-008**: Architecture and class-reference content MUST NOT be duplicated here; pages that need them MUST
  **link to `docs-app/`** (single source of truth; the class reference stays generated — DECISIONS #50).
- **FR-009**: Every architecture/lifecycle/deployment-topology page MUST include at least one **Mermaid**
  diagram that renders natively on GitHub.
- **FR-010**: It MUST be i18n-ready: `docs/en/` authored first, with a **file-for-file `docs/ar/` mirror**
  holding placeholders (`> TODO: translation pending`), a `_glossary.md`, and a `_translation-memory.md`
  listing locked English terms. Code identifiers, inline code, and command flags are **never** translated.
- **FR-011**: Any referenced class/command/hook that is **not yet built** MUST be marked `stability: planned`
  and linked to the Spec Kit module that will produce it — **never invented** (Guard Gate: docs-guard verifies
  every reference against the source).
- **FR-012**: Reusable **page templates** MUST exist (`_template.md`, plus a class-reference template under the
  path that links to docs-app) so every page follows the same structure (front-matter, audience tier, expected-
  output convention).

### Key Entities

- **Handbook page**: a Markdown file under `docs/en/<section>/` with front-matter (title, `stability`, audience
  tier), beginner-first prose, copy-paste commands + expected output, and Mermaid where applicable.
- **Glossary / translation memory**: living files mapping domain terms to plain-English definitions and listing
  the locked (never-translated) English terms.
- **Deployment recipe**: a self-contained, step-by-step page per target, with a topology diagram.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer with no prior Corex experience can get the site booting on **each** of the five
  supported OS setups using only its guide.
- **SC-002**: A team can bring the full stack up with **one** command and run the tests inside the container,
  with the monorepo correctly mapped (not copied).
- **SC-003**: Each of the five deployment targets has a recipe complete enough to deploy a release tag end-to-
  end, including secrets/backups/rollback/zero-downtime + CI/CD.
- **SC-004**: **Zero** architecture or class-reference content is duplicated from docs-app; every such need is a
  link (verified — no drift).
- **SC-005**: Every command block is language-tagged with expected output; every topology page has a
  GitHub-rendering Mermaid diagram; `docs/ar/` mirrors `docs/en/` file-for-file with placeholders.
- **SC-006**: docs-guard verifies every referenced class/command/hook against the source (or it is marked
  `stability: planned`).

## Assumptions

- Built alongside `docs-app/` (spec 022) and `wp corex docs:generate` (spec 019) — this module owns the
  contributor/ops handbook; those own the product docs + generated reference.
- The module is large and is delivered in **phases** (see plan.md, mapping the brief's D1–D12), **one phase per
  session**, spec-first — no content before this spec.
- `COREX-FRAMEWORK.md §4` is updated in the same PR that first lands `docs/` content, to reflect `docs/` now
  hosting the authored handbook (Working Guide Part F).
- CI/CD recipes document the team's chosen pipeline; the repo's own gate stays GitHub Actions unless a separate
  decision adopts Azure Pipelines (tracked in `/clarify`).
