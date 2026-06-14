# Tasks: make:site — client-site platform

**Feature**: 049-make-site · **Branch**: `feature/049-make-site`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)

**Tests**: REQUIRED (Pest, incl. `php -l` of generated PHP; live activation env-gated).

**Story legend**: US1 = plugin+theme generation + naming (P1, MVP) · US2 = governance/workflow files (P1) ·
US3 = starter slice (P2) · US4 = flags (P2).

---

## Phase 2: Foundational — site identity (blocks the stories)

- [x] T001 [P] Pest `tests/Unit/Cli/SiteIdentityTest.php` — `Acme` → namespace `AcmeSite\`, pluginSlug `acme-site`, themeSlug `acme`, textDomain `acme-site`, restNamespace `acme/v1`, cssPrefix `--acme-`, optionPrefix `acme_`; all distinct from Corex; a name normalising to `corex` is refused.
- [x] T002 Implement `Corex\Cli\Site\SiteIdentity` (pure deriver + distinctness guard) until T001 green.

## Phase 3: US1 + US2 — plugin + theme + governance (P1, MVP)

- [x] T003 [US1] Author the stubs under `packages/cli/stubs/site/` — plugin main + provider; theme `style.css` + `theme.json`; AGENTS.md, CLAUDE.md, README.md, PROGRESS.md, DECISIONS.md, gitignore.stub (governance) — encoding the client-only edit boundary, one-feature-one-PR, token-only/RTL, the local-AI/cache ignores.
- [x] T004 [P] [US1] Pest `tests/Unit/Cli/SiteScaffolderTest.php` — generates plugin (`acme-site`) + theme (`acme`) with the derived identity; generated PHP passes `php -l`; AGENTS/CLAUDE state the client-only boundary; `.gitignore` ignores the AI/cache folders; idempotent without `--force`.
- [x] T005 [US1] Implement `Corex\Cli\Site\SiteScaffolder` (+ `SiteScaffoldResult`) — pure render-all-before-write (plugin + theme + governance) until T004 green.
- [x] T006 [US2] The governance stubs name the generated client plugin/theme paths accurately + include specs/docs scaffolding; the site config points the `make:*` generators at the client plugin.

## Phase 4: US3 — starter vertical slice (P2)

- [ ] T007 [US3] Author the `starter/` stubs (model · repository · service · controller using the spec-043 envelope · dynamic block · option · test · "how to remove" README), client-namespaced; `--starter` emits them; default/`--minimal` omits them. Pest asserts the slice generates + `php -l` clean.

## Phase 5: US4 — flags + command (P2)

- [x] T008 [US4] Wire `make:site` into `MakeCommand` + `CliServiceProvider` (WP-CLI-gated) with `--plugin-only`/`--theme-only`/`--minimal`/`--starter`/`--force`; verify `wp corex make:site Acme` live.

## Phase 6: Polish

- [x] T009 [P] Docs: docs-app `guides/client-site.md` (build a client site + the team/AI workflow + the client/framework boundary). CLI README.
- [x] T010 Guard Gate (clean-code, wp-guard — generated route/envelope/escaping/no-secret, test-guard incl. generated php -l, docs-guard incl. generated-governance accuracy).
- [x] T011 Suites green (`composer test`); record counts. Live activation env-gated.
- [ ] T012 Update `PROGRESS.md` + `DECISIONS.md` #83; NEXT STEP. Commit → PR → CI → merge.

---

## Dependencies & order

- Foundational (T001–T002) blocks the stories. **MVP = US1+US2** (plugin+theme+governance). US3 (starter) builds on
  the identity; US4 (flags/command) wires it. Polish last.
- TDD: T001→T002, T004→T005.
- **Parallel**: T001/T004 (`[P]`), docs T009.
