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

## PR B — Media/WebP (FR-061-09/10/11)
- [x] T020 CoreX Media settings UI — Media section (enable/quality/JPEG/PNG) + live server-support read-out (`info`
  field via the `corex_media_support_summary` filter; section hidden/disabled until installed/active) + filter
  seams + sanitization (quality clamp, flag coercion); save via the existing guarded (cap+nonce) path; "originals
  always preserved" is not a setting. Tests: MediaSettings, info-field render, tab order.
- [x] T021 `wp corex media regenerate-webp [--dry-run] [--limit] [--attachment]` — pure `WebpRegenerator` planner
  (convert/skip-exists/skip-unsupported + counts) + `MediaCommand` WP-CLI boundary; never deletes/overwrites
  originals; respects settings; batched query (no unbounded `-1`). Tests: WebpRegenerator.
- [x] T022 Frontend WebP delivery: `MediaImage::pictureForUrl` + the `corex_media_optimize_image` opt-in filter seam
  (no hard dependency); `<picture>` when a sibling exists, `<img>` fallback otherwise; no global WP image filter.
  Tests: pictureForUrl with/without sibling (existing PictureRenderer tests retained).
  - [x] T022b Retrofit the built-in CoreX UI image blocks (hero/gallery/team) to the `corex_media_optimize_image`
    seam — DONE (Priority 2): PictureRenderer now preserves class+loading, the blocks opt in, and their styles set
    `picture { display: contents }` so the optional wrapper never changes layout. Tests added.

## PR C — Generator restructure (FR-061-12/13)
- [x] T030 `make:site` emits the flat `<slug>-site/` + `<slug>-theme/` under the output dir (e.g.
  `sites/acme/acme-site`, `acme-theme`) — SiteIdentity (themeSlug → `-theme`), SiteScaffolder, SiteScaffoldValidator
  (suffix discovery + base-slug prefixes), and governance stubs updated; tests across SiteIdentity/Scaffolder/
  Starter/Validation/Readiness. Back-compat: older nested `plugins/`/`themes/` sites keep working; the dist builder
  packages either shape (documented in client-site-workflow.md).
- [x] T031 Generated client-theme header/footer/front-page override scaffolding (`parts/header.html`,
  `parts/footer.html`, `templates/front-page.html`) with brand-vs-structure ownership comments + tests.
- [x] T032 Generated-client image pipeline (`--starter`): `assets/src/images/` → built `assets/images/` (incl.
  `.webp`) via a project-local `scripts/optimize-images.mjs` (sharp) wired into `npm run images` + `build` + tests.
- [x] T020b (folded in) Reflect the Media defaults (on; quality 82) in the unsaved settings form.
- [x] T022b Retrofit the built-in CoreX UI image blocks to the `corex_media_optimize_image` seam — DONE
  (Priority 2): PictureRenderer preserves class+loading; Hero/Gallery/Team opt in; `picture { display: contents }`
  keeps the wrapper layout-transparent. Tests added.

## PR D — Font Library (deferred; FR-061-14)
- [ ] T040 Optional curated CoreX WP Font Library collection, or a precise backlog spec if not pursued.

## Cross-cutting
- [ ] T050 PR #60 (Astro 6→7): validate on a dedicated branch (`npm ci && npm run build`, breaking-change review,
  dep-inventory refresh). Held; does not block this milestone. (Handoff comment posted on the PR.)
- [ ] T060 Release v0.29.0 after PR A–C merge and the release gate passes (repo policy: stamp version, CHANGELOG,
  tag, GitHub release).
