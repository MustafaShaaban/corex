# Tasks: Developer & operations handbook (028)

**Forward spec, phased delivery.** This is a large content module — built **one phase per session** (D1–D12,
per plan.md). Each phase ends with a docs-guard pass on its pages (quickstart §1–3), PROGRESS/DECISIONS
updates, and a NEXT STEP block. The class reference stays **generated** (docs-app); architecture is **linked**,
never duplicated. FR→phase map is in `plan.md`; page rules are in `contracts/page-contract.md`.

> **This session produced the spec only** (specify + plan + tasks + artifacts). **No `docs/` content is
> authored yet** — D1 is the first implementation session.

## Phase D1: Scaffolding (templates, glossary, README) — 🎯 first implementation session

- [x] T001 Create `docs/en/_template.md` (front-matter + section skeleton + the command/expected-output + tool-intro conventions from `data-model.md`/`contracts/page-contract.md`).
- [x] T002 Create the **class-reference link-stub** template (a short stub that links to the generated docs-app reference; `stability: planned` when the class isn't built) — replaces the brief's hand-written class page.
- [x] T003 Create `docs/_glossary.md` seeded with the domain terms (Corex, Service Provider, Repository, Container, Event Bus, Middleware, Block, Blueprint/Kit, Feature flag, Guard Gate, Spec Kit, AdminGuard, Mailer seam, Field driver) + an empty Arabic column.
- [x] T004 Create `docs/_translation-memory.md` listing the locked (never-translated) English terms (class names/namespaces, methods, env vars, hooks, CLI flags, paths, product names).
- [x] T005 Create `docs/README.md` — entry point, the three audience tiers, the section map, and a language-picker stub (en / ar-pending).
- [x] T006 Update `COREX-FRAMEWORK.md §4` + the existing `docs/README.md` note to reflect `docs/` now hosting the authored handbook (Working Guide Part F); update PROGRESS + add a DECISIONS entry (the split-by-audience decision); NEXT STEP → D2.

## Phase D2: `00-getting-started/` (5 OS guides)

- [x] T007 Author the five guides — Windows+WAMP, Windows+XAMPP, Linux (Ubuntu/Debian), macOS, wp-env/Docker — each per the getting-started contract (tool intros, command→expected-output, monorepo mapping, boot verification).

## Phase D3: `05-deployment/` — Docker dev + prod

- [x] T008 Author the Docker dev `docker compose` (php-fpm/web/db/cache/mail) + monorepo bind-mount mapping + up/down/reset/test commands; the multi-stage prod Dockerfile; dev + prod **Mermaid** topology diagrams.

## Phase D4: `05-deployment/` — Azure

- [x] T009 Author Azure App Service + Azure VM recipes (provision→config→deploy-from-tag→HTTPS→secrets→backups→rollback→zero-downtime→CI/CD→topology diagram).

## Phase D5: `05-deployment/` — AWS

- [x] T010 Author AWS Elastic Beanstalk + EC2+RDS recipes (same recipe shape + topology diagrams).

## Phase D6: `05-deployment/` — shared hosting + CI/CD

- [ ] T011 Author the cPanel shared-hosting recipe (no-symlink strategy) + the CI/CD wiring page + the cross-cutting secrets/backups/zero-downtime page.

## Phase D7: `04-team-workflow/`

- [ ] T012 Author onboarding + git-flow-lite + Conventional Commits + PR review + the Claude Code/Spec Kit loop + the Guard Gate — **linking** to `COREX-WORKING-GUIDE.md` + the constitution (no rule duplication).

## Phase D8: `06-cookbooks/`

- [ ] T013 Author cookbooks — Woo detect-and-defer, multisite, headless mode, AI-agent-driven flows, paid add-ons — each its own page with ≥2 examples of different shapes.

## Phase D9: `07-troubleshooting/` + `08-contributing/`

- [ ] T014 Author the troubleshooting section (the real errors) + the contributing on-ramp (links to CONTRIBUTING + Working Guide).

## Phase D10: `docs/ar/` mirror

- [ ] T015 Scaffold `docs/ar/` as a file-for-file placeholder mirror of `docs/en/` (front-matter + `> TODO: translation pending`).

## Phase D11: Cross-link + planned audit

- [ ] T016 Verify every architecture/reference need is a link into docs-app (zero duplication); audit `stability: planned` markers against the specs that will produce them.

## Phase D12: Verification pass

- [ ] T017 Run each getting-started + deployment recipe on a clean target; fix gaps; stamp `last_verified: YYYY-MM-DD` footers (env-gated where a cloud target/Apache is unavailable — note which were verified vs deferred).

## Dependencies

- D1 (scaffolding) precedes all content phases. D2–D9 are largely independent content phases (different
  section folders) and can be reordered by need. D10 mirrors whatever `en/` exists; D11/D12 run last.

## Implementation strategy

One phase per session, spec-first, each ending with a docs-guard pass + NEXT STEP. The brief's D1–D12 is
preserved; its architecture/class-reference phases are replaced by **links** into docs-app (the class reference
stays generated — DECISIONS #50). No new runtime/build dependency is introduced.

## Parallel opportunities

- Within a deployment phase, the per-target recipes are independent pages.
- D8 cookbooks are independent pages.
