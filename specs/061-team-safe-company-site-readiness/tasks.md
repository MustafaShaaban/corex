# Tasks: Team-Safe Company-Site Readiness (spec 061)

Status key: `[x]` done in PR A (this branch) · `[ ]` deferred to a follow-up PR (reason in spec.md).

## PR A — Foundation + deployment builder (this branch)

### T001 — Spec Kit
- [x] T001a `spec.md`, `plan.md`, `tasks.md` for spec 061.
- [x] T001b Evidence file `acceptance-evidence.md` (M6 sweep status + dist-builder verification).

### T002 — Agent role gate (FR-061-01) + handoff format (FR-061-04)
- [x] T002a Four-mode role gate + rule hierarchy in root `AGENTS.md`.
- [x] T002b Same in `CLAUDE.md` (pointer to AGENTS.md to avoid drift).
- [x] T002c Same in `COREX-WORKING-GUIDE.md`.
- [x] T002d Required SUMMARY/…/NEXT STEP response format in the root agent files.

### T003 — Standard AI start prompts (FR-061-02)
- [x] T003a `docs/en/04-team-workflow/ai-agent-start-prompts.md` (Universal + 4 mode prompts).
- [x] T003b `docs/en/04-team-workflow/agent-roles.md` (the role gate, human-readable).
- [x] T003c `docs/en/04-team-workflow/client-site-workflow.md` (the `sites/<client>/` workflow).

### T004 — Team source layout (FR-061-03)
- [x] T004a Verify `dist/` is git-ignored (already: `/dist/`, `**/dist/`).
- [x] T004b README "Start here"/architecture note: repo = source, `dist/` = generated, `sites/<client>/` = client.
- [x] T004c docs-app team-workflow pages + nav (agent roles, start prompts, client-site workflow).
- [ ] T004d Arabic (`docs/ar/**`) mirror — backlog (translation note added; EN is canonical for this area).

### T005 — make:site governance stubs (FR-061-05)
- [x] T005a Update `site/AGENTS.md` + `site/CLAUDE.md` stubs with Client Site Mode role gate + handoff format +
  edit-boundary (no CoreX internals / `wp/wp-content/` / `dist/`).
- [x] T005b Update/confirm the scaffolder test asserts the stub guidance.

### T006 — Shared-host dist builder (FR-061-06)
- [x] T006a `scripts/build-shared-host-dist.sh` (`--client`, `--dry-run`, clean/overwrite, manifest).
- [x] T006b `scripts/verify-shared-host-dist.sh` (folders present, forbidden absent, manifest valid JSON).
- [x] T006c `npm run build:dist` + `npm run verify:dist` wiring in root `package.json`.
- [x] T006d Test/verification for the builder (dry-run plan + verifier on a fixture tree).

### T007 — Azure Pipelines (FR-061-07)
- [x] T007a `azure-pipelines.yml`: build dist + publish artifact + parameterised manual SFTP deploy stage
  (placeholders, secrets, runtime-file protection).

### T008 — Deployment docs (FR-061-08)
- [x] T008a `docs/en/05-deployment/shared-host-dist.md`.
- [x] T008b `docs/en/05-deployment/azure-pipelines.md`.
- [x] T008c docs-app deployment nav/cross-links.

### T009 — M6 manual acceptance sweep (Phase 16)
- [x] T009a Automated dark-mode acceptance (login/admin/add-ons) recorded in `acceptance-evidence.md`.
- [ ] T009b RTL mirroring / 200% zoom / full-keyboard/focus — **environment-gated** (recorded, not claimed passed).

### T010 — Consistency + validation + PR (Phases 19-21)
- [x] T010a PROGRESS / ROADMAP / DECISIONS updates.
- [x] T010b Validation gate (composer validate, PHP lint, Pest, Jest, docs-app build, build:dist dry-run + verify,
  `git diff --check`).
- [x] T010c Open PR A.

## PR B — Media/WebP (deferred; FR-061-09/10/11)
- [ ] T020 CoreX Media settings UI (enable/quality/JPEG/PNG/preserve-originals + server-support probe + sanitization +
  cap/nonce + filter seams + tests).
- [ ] T021 `wp corex media regenerate-webp` (dry-run/batch/limit, never deletes originals, counts, tests).
- [ ] T022 Frontend WebP delivery: CoreX UI image blocks use the `<picture>` helper; helper `<picture>`/`<img>`
  fallback tests; no global filter by default.

## PR C — Generator restructure (deferred; FR-061-12/13)
- [ ] T030 `make:site` emits `sites/<client>/<client>-site/` + `<client>-theme/` (SiteIdentity + SiteScaffolder +
  stubs + tests + migration/back-compat note).
- [ ] T031 Generated client-theme header/footer template-part override scaffolding + comments + tests.
- [ ] T032 Generated-client image pipeline (`src/images/` → built WebP, `npm run images`) + dependency-policy check.

## PR D — Font Library (deferred; FR-061-14)
- [ ] T040 Optional curated CoreX WP Font Library collection, or a precise backlog spec if not pursued.

## Cross-cutting
- [ ] T050 PR #60 (Astro 6→7): validate on a dedicated branch (`npm ci && npm run build`, breaking-change review,
  dep-inventory refresh). Held; does not block this milestone. (Handoff comment posted on the PR.)
- [ ] T060 Release v0.29.0 after PR A–C merge and the release gate passes (repo policy: stamp version, CHANGELOG,
  tag, GitHub release).
