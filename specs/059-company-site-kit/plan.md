# Implementation Plan: Company Site Kit v1 — Structure and Page Coverage

**Branch**: `spec/059-company-site-kit` | **Date**: 2026-06-21 | **Spec**: [spec.md](./spec.md)

**Input**: spec.md; design input [M4 company site kit handoff](../../design/handoffs/company-kit.md); built on merged M2 tokens (Spec 057) and M3 nav/footer (Spec 058).

## Summary

Complete the **Company Site Kit v1** by extending the *existing* kit foundation rather than building a new one. The
`corex-kit-company` addon already provides a `CompanyBlueprint` (3 pages today), and `corex-core`'s provisioning
already implements preview/apply with safe conflict handling (`KitProvisioner`, `ApplyPreview`, `ApplyOutcome`,
`PageDisposition` = reset/adopt/skip/conflict). M4 (1) expands `CompanyBlueprint::pages()` to the full v1 page set,
composed from the confirmed `corex/*` section patterns + M3 nav/footer + core blocks; (2) adds **demo content levels**
(`minimal`/`standard`/`full`) with identical structure and increasing content; (3) adds **SEO starter metadata** per
page (editable, plugin-compatible); and (4) keeps everything token-only, RTL-correct, responsive, and WCAG 2.2 AA.

No page builder, no new broad block library (M5 selects only proven gaps), no Portfolio/Woo kits, no Pro.

## Technical Context

**Language/Version**: PHP 8.3+; HTML block markup; WordPress 7.0+ FSE.
**Primary Dependencies**: `corex-core` provisioning (`Corex\Provisioning\*`), `corex-kit-company` (`Corex\Kit\*`),
confirmed `corex-ui` patterns (`corex/hero|features|cta|testimonial|contact|faq|news|stats|content-split|section-header`),
M3 `corex/header-*`/`corex/footer-*`, M2 `theme.json` tokens. No new third-party dependency.
**Storage**: WordPress pages/options created at apply time; the blueprint itself is pure/read-only (no DB in the kit
manifest). Provisioning writes pages through existing core APIs.
**Testing**: Pest (blueprint page coverage, demo-level structure parity, SEO metadata presence, no raw literals,
required-pattern existence), reuse existing provisioning tests; Playwright/browser a11y where available else
ENVIRONMENT-GATED.
**Target Platform**: WordPress 7.0+ FSE block theme + the CoreX kit/provisioning foundations.
**Project Type**: WordPress framework add-on (kit) extension.
**Performance/Constraints**: token-only (Principle V), conditional assets (Principle VI, inherited from M2/M3),
RTL-first (Principle VIII), no optional-plugin hard dependency (Principle IX). Apply is preview-gated and idempotent.
**Scale/Scope**: ~21 v1 surfaces (pages + the universal templates already shipped), 3 demo levels, SEO starter.

## Constitution Check

- [x] **I. Theme is a skin** — pages compose theme patterns/parts; the kit manifest is read-only data in the addon;
  no business logic in the theme.
- [x] **II. Plugins boot themselves** — the company kit is an addon provider, gated/booted via the existing addon
  runtime; works without a specific theme beyond the CoreX presentation it composes.
- [x] **III/IV. Services/Injection** — provisioning logic stays in `corex-core` services resolved via the container;
  the blueprint adds no controllers/DB access; no `new` of dependencies in methods.
- [x] **V. Runtime tokens** — page markup is token-only; no raw hex/size/font; reuses M2 tokens via patterns/blocks.
- [x] **VI. Conditional assets** — no new global assets; pages reuse M2/M3 conditionally-loaded styles.
- [x] **VII. Declarative security** — apply runs through existing provisioning (cap/nonce via the existing
  setup/admin path); the blueprint is pure data; page content is escaped where dynamic.
- [x] **VIII. RTL-first** — all page markup uses logical properties via the underlying patterns/blocks; RTL verified.
- [x] **IX. No optional dep is hard** — contact/forms enhance but are not required; no ACF/Woo/Polylang dependency.
- [x] **X. Spec is source of truth** — traces to spec.md + the approved handoff.
- [x] **Guard Gate + Definition of Done** — wp-guard/clean-code-guard/test-guard/docs-guard per task; tests, i18n,
  RTL, WCAG 2.2 AA, docs + PROGRESS updates.

**Result: PASS.** No violations.

## Project Structure

```text
addons/corex-kit-company/src/
├── Company/CompanyBlueprint.php     # EXTEND: full v1 pages(), demo levels, SEO starter
├── Blueprint.php                    # EXTEND (base): optional demoLevel/seo hooks (default-safe)
└── Provisioning/BlueprintKitProvisioner.php  # reuse; extend only if SEO/level wiring needs it

plugins/corex-core/src/Provisioning/  # reuse preview/apply/PageDisposition (no rebuild)

tests/Unit/Kit/                       # blueprint coverage, demo-level parity, SEO, no-raw-literal
docs-app/.../design-system|guides/    # company-kit page + apply/demo-levels docs
```

**Structure Decision**: Extend the existing company-kit addon and reuse `corex-core` provisioning. The page set is
expressed as blueprint data composing real patterns; gaps with no existing pattern (services grid, team, logo cloud,
case-study/project grid, locations/map) are composed from `corex/section-header` + core blocks now and **recorded as
the M5 batch** rather than pre-built.

## Phase 0 — Research

See [research.md](./research.md): resolves (1) reuse vs. extend of the provisioning/conflict layer (reuse); (2) how
demo levels attach to the blueprint without duplicating structure; (3) SEO starter representation (per-page meta that
stays plugin-compatible); (4) which v1 surfaces are pages vs. existing universal templates; (5) the minimal M5 block
gaps M4 surfaces.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md): blueprint page entity, demo level, SEO starter, apply plan (reusing
  `ApplyPreview`/`PageDisposition`).
- [contracts/](./contracts/): page-coverage contract (the v1 set + required patterns), demo-level contract
  (structure parity across levels), apply-safety contract (preview + reset/adopt/skip/conflict), token/a11y/RTL
  contract.
- [quickstart.md](./quickstart.md): apply at each level, verify pages + conflict handling + SEO, with ENV-gated
  browser checks.

### Implementation phasing (mapped to user stories)

1. **US1 (P1)** — extend `CompanyBlueprint::pages()` to the core pages (Home/About/Services/Contact) + ensure the
   universal system templates (404/search/index) and legal pages; verify preview/apply + conflict on a clean site.
2. **US2 (P2)** — complete the full v1 page set (single service/case study/industries/faq/blog/team/testimonials/
   locations/cookie/maintenance), composing patterns + core blocks; record M5 gaps.
3. **US3 (P3)** — demo levels (`minimal`/`standard`/`full`), brand-aware setup mapping to M2 tokens/`brand.json`,
   SEO starter metadata, and the reset/adopt/skip/conflict UX surfaced in the summary.
4. **Polish** — docs, full gate, PROGRESS/ROADMAP/CHANGELOG, re-verify `wp corex make:site` with the richer kit.

## Complexity Tracking

No constitution violations. Not applicable.
