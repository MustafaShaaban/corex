# Specification Quality Checklist: Site kits — Company, Portfolio, Woo (023)

**Purpose**: Validate specification completeness and quality before proceeding to planning.
**Created**: 2026-06-11
**Feature**: [spec.md](../spec.md)

> Retrospective spec — the checklist confirms the written requirements faithfully describe the shipped
> `addons/corex-kit-{company,portfolio,woo}` code (blueprints, the projects block, the Woo gate).

## Content Quality

- [x] No implementation details leak into requirements beyond the named seams (FR→file map lives in plan.md)
- [x] Focused on user value (trustworthy, drift-proof, gated site kits)
- [x] Written for a framework-consumer audience
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No `[NEEDS CLARIFICATION]` markers remain
- [x] Requirements are testable — each FR maps to a Pest test that exists
- [x] Success criteria are measurable (manifest cross-check; bounded/accessible render; gate truth table; no fatals)
- [x] Success criteria are technology-agnostic at the outcome level
- [x] All acceptance scenarios map to existing tests (CompanyKitManifestTest, PortfolioTest, WooKitTest)
- [x] Edge cases identified (pure manifest, no direct Woo meta, PSR-4 prefix, bounded count)
- [x] Scope is bounded (the three kits; the setup wizard is spec 024)
- [x] Dependencies/assumptions stated (specs 007/009/010/018; wizard → 024; browser for visuals)

## Feature Readiness

- [x] Every FR has a verifying test in `tests/Unit/{Kit,Portfolio,Woo}/`
- [x] User stories prioritized (P1 company, P1 portfolio, P2 woo)
- [x] Measurable outcomes defined
- [x] No leakage of unverifiable claims (visual validity explicitly deferred to a browser)

## Notes

Quality: **PASS**. Retrospective; describes real, tested code (CompanyKitManifest 3 + Portfolio 4 + WooKit 3,
plus the existing Blueprint/ThemeTemplates tests; all three kits active on real WP, 0 fatals). No clarifications
needed.
