# Implementation Plan: Developer & operations handbook (028)

**Branch**: `feature/028-developer-handbook` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary

An in-repo, GitHub-native Markdown handbook under `/docs` for people who **set up, dockerize, deploy, and
contribute to** a Corex project. It owns the content `docs-app/` does not cover (multi-OS setup, Docker
dev/prod, deployment recipes + CI/CD, team workflow, cookbooks) and **links** to docs-app for architecture and
the generated class reference (single source of truth). Authored `en/` first with an `ar/` placeholder mirror,
a glossary, and a translation-memory for the future Arabic phase. Large → delivered in **phases (D1–D12), one
per session**, spec-first.

## Technical Context

**Format**: GitHub-Flavored Markdown + Mermaid (renders natively on GitHub — no build, no renderer dependency).
**Primary references**: `docs-app/` (spec 022), `wp corex docs:generate` (spec 019), `COREX-FRAMEWORK.md`
(architecture/§19 deploy-from-tags), `COREX-WORKING-GUIDE.md` (workflow). **Verification (the "test")**:
docs-guard per page (every referenced class/command/hook checked against source or marked `stability:
planned`); the multi-OS + deploy recipes are runnable on a clean target (phase D12). **Constraints**: no
duplication of architecture/reference (link only); no new runtime/build dependency (FR-004 guardrail); beginner-
first; expected-output after every command; ≥1 Mermaid per topology page.

## Constitution Check (v1.2.1)

- [x] **X (spec-first)** — PASS. This spec precedes all `docs/` content; the brief's "create files this session"
  is reordered into implementation phases that run *after* this spec.
- [x] **Source-of-truth hierarchy / no drift** — PASS. Architecture + class reference are **not** duplicated
  (FR-008); the handbook links to docs-app, and the reference stays **generated** (DECISIONS #50). The brief's
  hand-written class reference (its §F) is intentionally **not** built — surfaced + resolved in the checklist.
- [x] **Documentation (DoD) / Guard Gate** — the deliverable is documentation; **docs-guard** is the gate, run
  per page (verifies references; catches drift). No production code → clean-code/wp/test guards N/A except where
  a snippet ships.
- [x] **VIII (i18n/RTL)** — PASS. `en/` + a file-for-file `ar/` placeholder mirror; locked-term list; code
  identifiers never translated.
- [x] **IX (no optional dep as hard dep)** — PASS. redis/mailpit/nginx appear only as documented dev-stack
  options, never framework deps.
- [x] **Working Guide Part F** — `COREX-FRAMEWORK.md §4` (which reserves `docs/` for "supplementary" docs) is
  updated in the same PR that first lands handbook content, to reflect `docs/` now hosting the authored handbook.

**Gate**: PASS. (Azure-Pipelines-vs-GitHub-Actions for the repo's own CI is deferred to `/clarify`; the recipes
document the team pipeline regardless.)

## Phase plan (the brief's D1–D12, reconciled to our one-phase-per-session cadence)

Each phase is a session; each ends with PROGRESS/DECISIONS updates + a NEXT STEP block. **Only D1 is in scope to
*author* after this spec; D2+ are subsequent sessions.**

| Phase | Deliverable | FRs |
|---|---|---|
| **D1** | Templates (`_template.md` + the class-reference *link-stub* template), `_glossary.md`, `_translation-memory.md`, audience tiers, `docs/README.md` (entry point + language-picker stub) | FR-001/010/012 |
| **D2** | `00-getting-started/` — Windows+WAMP, Windows+XAMPP, Linux, macOS, wp-env (5 guides) | FR-002/003 |
| **D3** | `05-deployment/` Docker — dev compose (php-fpm/web/db/cache/mail) + monorepo mapping + multi-stage prod Dockerfile + up/down/reset/test commands + topology diagrams | FR-004/009 |
| **D4** | `05-deployment/` Azure — App Service + VM recipes (+ diagrams, secrets/backups/rollback/zero-downtime) | FR-005/009 |
| **D5** | `05-deployment/` AWS — Beanstalk + EC2+RDS recipes (+ diagrams) | FR-005/009 |
| **D6** | `05-deployment/` cPanel shared hosting (no-symlink strategy) + CI/CD wiring + secrets/backups/zero-downtime cross-page | FR-005 |
| **D7** | `04-team-workflow/` — onboarding, git-flow-lite, commits, PR review, Claude Code + Spec Kit loop, quality gates (links to authoritative docs) | FR-006 |
| **D8** | `06-cookbooks/` — Woo detect-and-defer, multisite, headless, AI-agent flows, paid add-ons (≥2 examples each) | FR-007 |
| **D9** | `07-troubleshooting/` + `08-contributing/` (links to CONTRIBUTING + Working Guide) | FR-006/007 |
| **D10** | `docs/ar/` file-for-file placeholder mirror | FR-010 |
| **D11** | Cross-link pass: every architecture/reference need points into docs-app (no duplication); `stability: planned` audit | FR-008/011 |
| **D12** | Verification pass — run each setup + deploy recipe on a clean target, fix gaps, add "verified on YYYY-MM-DD" footers | SC-001/002/003 |

> _Note: `01-architecture` and `03-class-reference` are intentionally **not** authored here — they are
> docs-app's (linked). The brief's D3/D5/D6 (architecture + class reference) are replaced by link-stubs._

## Folder layout (created in D1)

```text
docs/
├── README.md                # entry point + audience tiers + language-picker stub
├── _glossary.md             # domain term → plain-English (Arabic column later)
├── _translation-memory.md   # locked English terms (never translated)
├── en/
│   ├── 00-getting-started/   04-team-workflow/   05-deployment/
│   ├── 06-cookbooks/         07-troubleshooting/ 08-contributing/
│   └── _template.md          # the per-page template
└── ar/                      # mirror of en/, placeholders only (D10)
```

## Phase 0 / 1 artifacts

- `research.md` — the docs-app split, the Docker dev stack choice, the GitHub-native-Mermaid decision, the
  monorepo-mapping-in-Docker strategy.
- `data-model.md` — the page front-matter schema, audience tiers, the expected-output convention, glossary/TM
  shapes.
- `contracts/page-contract.md` — what every handbook page + each guide type must contain.
- `quickstart.md` — how to validate the handbook (docs-guard pass, link-check, render-on-GitHub).

## Complexity Tracking

No unjustified violations. The only structural change is `docs/` becoming the authored handbook home (an
update to FRAMEWORK §4, done in the first content PR). The brief's hand-written class reference is deliberately
dropped in favour of the existing generator (DECISIONS #50) — a removal of complexity, not an addition.
