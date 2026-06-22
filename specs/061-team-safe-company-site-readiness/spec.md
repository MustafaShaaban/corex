# Feature Specification: Team-Safe Company-Site Readiness

**Feature Branch**: `spec/061-team-safe-company-site-readiness`

**Created**: 2026-06-22

**Status**: In progress — PR A (foundation) + the shared-host `dist` builder implemented; Media/WebP, generated-client
image pipeline, and the `make:site` `sites/<client>/` restructure are spec'd task groups deferred to follow-up PRs.

**Input**: The owner-approved final pre-company-site goal (`COREX-FINAL-PRE-SITE-GOAL.md`, a handoff file — not
committed). Merges the v0.28.0 deferred backlog with the team-safe company-site workflow decisions. Builds on the
v0.28.0 baseline (M6 admin design, M2/M3/M4 foundations, Company Site Kit v1, docs URL resolver, add-on tiers).

## Overview

CoreX v0.28.0 is feature-complete enough to start the first real company website, but the **team workflow** around
it is not yet locked: an AI agent or developer can't reliably tell whether they're working *on the framework* or *on
a client site*, where client source belongs, how to package a deployable artifact, or how deployment happens. This
feature makes CoreX **team-safe for company-site implementation** and finishes the remaining post-readiness backlog,
without weakening any existing truthful-state, security, or accessibility contract.

Privacy rule (non-negotiable): no real client/project name appears anywhere in the framework repo, docs, examples,
tests, fixtures, changelog, roadmap, release notes, or specs. The neutral placeholder is **Acme**
(`Acme Company` / `Acme Website` / `http://acme.local` / DB `acme` / prefix `acme_wp_` / slug `acme-site` / source
`sites/acme/`).

## User Scenarios

### A developer or AI agent starts a session (role clarity)
A contributor (human or agent) opens the repo. Before editing, they must classify the session into one of four modes
— **CoreX Framework**, **Client Site**, **Deployment**, **Docs/Planning** — which decides *where* they may edit. The
mode is documented in the root agent files and enforced by copy/paste start prompts.

### A team builds a client site (source layout)
Client/company-site source lives under `sites/<client>/` (`<client>-site/` plugin + `<client>-theme/` theme + its own
governance + `specs/`/`docs/`). Framework work stays in the root structure (`plugins/`, `addons/`, `packages/`,
`theme/`, root `specs/`, `docs/`, `docs-app/`). `wp/wp-content/` and `dist/` are runtime/build output — never edited
as source. `dist/` is generated and git-ignored.

### A release is packaged and deployed (dist + Azure)
A flat, deployable WordPress tree is built into `dist/` by a first-class builder (`npm run build:dist`), excluding
dev-only files and runtime state. GitHub Actions runs PR/code-quality gates; **Azure Pipelines** builds `dist/` and
deploys it (SFTP) to shared hosting, from release tags, with credentials in Azure secrets and production runtime files
protected from overwrite.

## Requirements

### Functional — implemented in this milestone's first PR(s)

- **FR-061-01 — Agent role gate.** Root `AGENTS.md`, `CLAUDE.md`, and `COREX-WORKING-GUIDE.md` define four session
  modes, each with its allowed edit areas, source-of-truth files, and forbidden areas. The rule hierarchy is: Role
  Gate decides *where*; Spec Kit decides *what*; Guard Gate decides *whether it's safe to ship*; UI/UX ProMax decides
  *whether visible UI is good enough*.
- **FR-061-02 — Standard AI start prompts.** Copy/paste prompts (Universal + one per mode) in
  `docs/en/04-team-workflow/ai-agent-start-prompts.md`, each forcing: check repo first → classify mode → read the
  correct source-of-truth files → stay in scope → Spec Kit → relevant guards → UI/UX ProMax for UI → update
  PROGRESS/DECISIONS → end with SUMMARY + NEXT STEP.
- **FR-061-03 — Team source layout docs.** `docs/en/04-team-workflow/client-site-workflow.md` + `agent-roles.md`
  document the `sites/<client>/` layout, the runtime/output exclusions, and where framework vs client work belongs.
  README "Start here" + docs-app nav reflect it. (`dist/` is already git-ignored — verified.)
- **FR-061-04 — Required response/handoff format.** A standard SUMMARY/WORKSPACE/MODE/SPEC-KIT/VERIFICATION/…/NEXT
  STEP format is documented in the root agent files and the generated client-site `AGENTS.md`/`CLAUDE.md` stubs.
- **FR-061-05 — make:site governance stubs carry the role gate.** The generated client `AGENTS.md`/`CLAUDE.md` stubs
  state: this is Client Site Mode; edit only this client source; don't edit CoreX internals, `wp/wp-content/`, or
  `dist/`; use `sites/<client>/specs/`; follow Spec Kit / Guard Gate / UI/UX ProMax; for framework bugs, switch to a
  CoreX Framework Mode task.
- **FR-061-06 — Shared-host `dist` builder.** `scripts/build-shared-host-dist.sh` (+ `npm run build:dist`) assembles a
  flat WordPress tree in `dist/`: real (de-symlinked) framework plugins/addons/theme + the selected client
  plugin/theme from `sites/<client>/`, production vendor/assets, and a `corex-release.json` manifest. It excludes
  `.git`, `node_modules`, `tests`, dev tooling, env/secrets, uploads, `wp-config.php`, and agent/`.claude` state.
  Supports `--client`, `--dry-run`, and safe clean/overwrite. `scripts/verify-shared-host-dist.sh` asserts the
  expected folders exist, forbidden paths are absent, and the manifest is valid JSON.
- **FR-061-07 — Azure Pipelines deployment.** `azure-pipelines.yml` builds `dist/` and publishes it as an artifact,
  then a parameterised, manual-by-default SFTP deploy stage uses Azure secret variables and protects production
  runtime files (`wp-config.php`, `.htaccess`, `uploads/`, `cache/`, `upgrade/`, `debug.log`). No real credentials,
  hosts, or keys — placeholders only. GitHub Actions stays the PR/quality gate; Azure does build+deploy.
- **FR-061-08 — Deployment docs + checklists.** `docs/en/05-deployment/shared-host-dist.md` and `azure-pipelines.md`
  cover local→staging/production, what's deployed, DB/uploads migration, `wp-config.php`, URL search-replace,
  rollback, runtime-file protection, Azure secrets, SFTP exclusions, and why `dist/` is generated and never committed.

### Functional — spec'd here, deferred to follow-up PRs (with reasons)

- **FR-061-09 — CoreX Media settings UI** (Phase 9): a guarded settings surface (enable/disable WebP, quality, JPEG/PNG
  toggles, "originals always preserved" notice, server-support probe) with sanitization + filter seams + tests.
  *Deferred:* it changes `corex-media`'s real conversion behavior and adds a settings surface — a self-contained PR
  (PR B) that deserves its own focused review and media-capability test matrix.
- **FR-061-10 — WebP regeneration CLI** (Phase 10): `wp corex media regenerate-webp` with dry-run/batch/limit, never
  deletes originals, reports counts. *Deferred:* pairs with FR-061-09 (PR B); needs a WP-CLI command + batched I/O.
- **FR-061-11 — Frontend WebP delivery hardening** (Phase 11): ensure CoreX UI image blocks use the `<picture>` helper;
  helper emits `<picture>` when a WebP sibling exists, falls back to `<img>` otherwise; no global image filter by
  default. *Deferred:* PR B, with render tests.
- **FR-061-12 — Generated-client image pipeline** (Phase 12): `make:site` emits `src/images/` → built WebP via a
  project-local `npm run images`. *Deferred:* it's a generator-template change (PR C) and may add a build dependency
  subject to the dependency policy.
- **FR-061-13 — make:site `sites/<client>/` layout + header/footer override scaffolding** (Phases 7-8): the generator
  emits `sites/<client>/<client>-site/` + `<client>-theme/` (with header/footer template-part overrides + comments).
  *Deferred:* it restructures a tested generator (`SiteScaffolder`/`SiteIdentity` + stubs + tests) and needs a
  backward-compatibility/migration note — a focused PR (PR C).
- **FR-061-14 — WordPress Font Library integration** (Phase 17): optional curated CoreX font collection via the WP
  Font Library APIs. *Deferred:* the documented production path (source-controlled client-theme fonts) already exists;
  a curated collection is an additive enhancement, not a readiness blocker — backlog spec item.

### Non-functional

- **NFR-061-01** — No existing truthful-state, security (cap+nonce), accessibility (WCAG 2.2 AA, RTL, reduced-motion),
  or asset-scoping contract is weakened.
- **NFR-061-02** — `dist/` is never committed; `wp/wp-content/` is never edited as source.
- **NFR-061-03** — Guard Gate: run the relevant guard skill(s) on each diff before it ships; where a named guard
  command is unavailable in this environment, the actual fallback validation is documented, not faked.
- **NFR-061-04** — All examples use Acme placeholders; no real client name anywhere in the framework repo.

## Acceptance

- The four modes, their boundaries, and the rule hierarchy are documented in the root agent files + start prompts.
- `sites/<client>/` is the documented client-source location; framework vs client edit areas are unambiguous.
- `npm run build:dist` produces a flat `dist/` with the expected layout + `corex-release.json`, excludes the forbidden
  paths, and `verify-shared-host-dist` passes; `dist/` stays git-ignored.
- `azure-pipelines.yml` builds + publishes `dist/` and has a safe, parameterised SFTP deploy stage (placeholders only).
- Deferred FRs are recorded as task groups here + in DECISIONS, and no doc implies they are already built.
- The M6 manual acceptance status (RTL/200%/keyboard) is recorded truthfully (environment-gated where unverified).

## Out of scope

A page builder; commercial/Pro features; the real client site itself; running a live Azure deployment (only the
pipeline definition + docs ship); changing WordPress core media behavior (originals always remain the attachment).
