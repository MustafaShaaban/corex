# Tasks: Documentation web app (022)

**Retrospective spec** — the site exists and builds green (19 authored pages → 213 pages with the generated
reference; Pagefind index produced). These are **reconciliation/verification** tasks: confirm each FR against
the mapped file/area (most already satisfied, marked `[x]`), plus the tracked debt (a formal docs-guard re-run
across the authored pages, remediation **P2**). The FR→file map is in `plan.md`. The build is the test (no
code-unit tests apply to a content site).

**No new implementation work** beyond the P2 docs-guard pass — flag any mismatch found as a defect rather
than scope.

## Phase 1: Setup (verification context)

- [x] T001 Confirm the project resolves: `docs-app/{astro.config.mjs,package.json}` present; `npm install` in `docs-app/` installs `astro` + `@astrojs/starlight`.
- [x] T002 Confirm `docs-app/.gitignore` excludes `node_modules/`, `dist/`, and the generated `reference/*/` pages (build-from-source).

## Phase 2: Foundational (the site shell — blocks the content)

- [x] T003 Verify FR-001 + FR-004: `astro.config.mjs` configures Starlight (title, sidebar tree, Pagefind search, `defaultLocale: en` RTL-ready) and `src/content.config.ts` declares the docs collection.

## Phase 3: User Story 1 — Learn and use Corex from a real docs site (P1) 🎯 MVP

**Goal**: a built, searchable docs site with the authored core set + the reference, all describing real code.
**Independent test**: `npm run build` green → `dist/` with the authored + reference pages + search index.

- [x] T004 [US1] Verify FR-002: the authored set exists — `index.mdx`, `getting-started/{overview,wamp-apache,wp-env-docker,monorepo-wiring,first-run}`, `guides/{forms,blocks,queries,branding,cli,configuration,mail,mvc}`, `architecture/overview`, `reference/index`, `faq`, `troubleshooting`.
- [x] T005 [US1] Verify FR-003 + SC-004: authored pages describe real code; the Mail guide uses `MessageBuilder::template()->with()` (verified against source).
- [x] T006 [US1] Verify FR-005: generated `reference/<layer>/*` pages are present and surfaced in the reference nav, with the hand-written `reference/index.md` kept.
- [x] T007 [US1] Verify FR-001 + FR-006 + SC-001/SC-003/SC-005: `npm run build` emits `dist/` (authored + reference pages + Pagefind index); a fresh checkout builds after `npm install` (no committed `node_modules`/`dist`).

## Phase 4: Polish & cross-cutting

- [ ] T008 **(P2)** Run `docs-guard` formally across every authored page (getting-started, guides, architecture, FAQ, troubleshooting) — verify each referenced command/API/path against the source; fix any drift (as it did for the Mail guide). _Tracked as remediation P2._
- [x] T009 Confirm docs: `docs-app/README.md` documents `npm run dev` / `npm run build` / Apache serve; DECISIONS #49 records the approach; PROGRESS reflects completion. _(Cosmetic: add `site` to silence the sitemap warning at public-deploy time.)_

## Dependencies

- The site shell (Phase 2) precedes the content (US1). The generated reference pages depend on spec 019's
  `docs:generate` (a separate feature, already done).
- T008 (P2) is the only **open** task; it is already tracked as a remediation item.

## Implementation strategy

This spec is retrospective: the site (US1) is already delivered and build-verified. The remaining work is the
one tracked debt (T008 → P2 formal docs-guard pass) — **not** new feature work. Visual/browser polish and a
public deployment (set `site`/`base`, add the AR locale) are documented follow-ups, not in scope here.

## Parallel opportunities

- The authored-page verification tasks (T004–T006) touch independent content areas and can be checked in
  parallel; T007 (build) gates on all content being present.
