# Tasks: Site kits — Company, Portfolio, Woo (023)

**Retrospective spec** — the three kits exist, are unit-tested, and verified on real WP (all active, 0 fatals;
Portfolio CPT+block registered; Woo gate true only with Woo active + flag on). These are
**reconciliation/verification** tasks: confirm each FR against the mapped file/behaviour (most already
satisfied, marked `[x]`), plus the tracked debt (a formal Guard Gate re-run incl. **woo-guard** on the Woo
kit, remediation **P2**). The FR→file map is in `plan.md`.

**No new implementation work** beyond the P2 guard pass — flag any mismatch found as a defect rather than
scope. (`SetupWizard*` lives in the company kit but is **spec 024**, not here.)

## Phase 1: Setup (verification context)

- [x] T001 Confirm the kit base is present: spec-010 `Blueprint`/`BlueprintRegistry`, spec-009 UI `PatternLibrary`, the theme templates dir.
- [x] T002 Confirm wiring: each kit provider is in the Boot provider list, the root `composer.json` declares `Corex\Portfolio\` + the Woo PSR-4, and the portfolio block is an npm workspace.

## Phase 2: Foundational (kits are pure manifests — no shared runtime blocker)

- [x] T003 Confirm each kit is a composition manifest (no re-implemented blocks/patterns) and that the projects block's sole `WP_Query` is `WpProjectsProvider` (injected, `no_found_rows`).

## Phase 3: User Story 1 — A company-site kit that can't drift (P1) 🎯 MVP

**Goal**: the Company blueprint cannot reference a non-existent template/part or an unprovided pattern.
**Independent test**: cross-check the blueprint against theme files + the PatternLibrary.

- [x] T004 [US1] Verify FR-001 + SC-001: every declared template, every declared part, and every composed pattern is real (`tests/Unit/Kit/CompanyKitManifestTest.php` — "declares only templates that exist", "…parts that exist", "composes only patterns the UI library provides").

## Phase 4: User Story 2 — A portfolio kit with a projects block (P1)

**Goal**: a `corex_project` CPT + `corex/projects` block + FSE templates + a drift-checked blueprint.
**Independent test**: render the block (cards/bounded/empty state); validate the blueprint.

- [x] T005 [US2] Verify FR-002: `PortfolioServiceProvider` registers a public `corex_project` CPT (thumbnail/REST/`/projects`) + `project_type` taxonomy under `Corex\Portfolio\` (verified registered on real WP).
- [x] T006 [US2] Verify FR-003 + SC-002: `corex/projects` renders accessible linked cards with lazy thumbnails (`tests/Unit/Portfolio/PortfolioTest.php` — "renders projects as accessible linked cards…"), bounds the count to the max ("bounds the project count to the max"), and shows an accessible empty state ("renders an accessible empty state…").
- [x] T007 [US2] Verify FR-004: `theme/templates/{archive-project,single-project}.html` exist (token-only) and `PortfolioBlueprint` declares only templates/parts that exist and patterns the library provides (PortfolioTest — "declares only templates/parts that exist…").

## Phase 5: User Story 3 — A WooCommerce store kit that self-disables (P2)

**Goal**: the Woo kit runs only when Woo is active AND its flag is on; otherwise a no-op; declares HPOS.
**Independent test**: resolve the gate across the four (Woo?)×(flag?) combinations; confirm HPOS declaration.

- [x] T008 [US3] Verify FR-005 + SC-003/SC-004: `WooKitGate::isEnabled()` is true only when Woo is active AND `woocommerce_kit` is on; the provider self-disables otherwise (`tests/Unit/Woo/WooKitTest.php` — "enables the kit only when WooCommerce is active and the flag is on"); verified on real WP (active 0 fatals; self-disabled with flag off).
- [x] T009 [US3] Verify FR-006: the plugin declares HPOS (`custom_order_tables`) and `WooBlueprint` declares only templates/parts that exist (WooKitTest — "describes the store kit it provides", "declares only templates/parts that exist in the theme").

## Phase 6: Polish & cross-cutting

- [ ] T010 [P] **(P2)** Run the Guard Gate formally on this feature's diff: `clean-code-guard` + `wp-guard` (CPT/block/escaping/bounds) on all three kits + **`woo-guard`** on `corex-kit-woo` (HPOS-safety, no direct order/meta) + `test-guard` + `docs-guard` (each kit README); fix any reported violation. _Tracked as remediation P2._
- [x] T011 Confirm docs + wiring: each kit has a README ("Manifest accuracy" for company); DECISIONS #51 (portfolio) + #52 (woo) record the approach; PROGRESS reflects completion; all three active on real WP.

## Dependencies

- The three kits are independent add-ons (different `addons/*` dirs + PSR-4 prefixes) and independently
  verifiable. They share the spec-010 Blueprint + spec-009 PatternLibrary bases.
- T010 (P2) is the only **open** task; it is already tracked as a remediation item.

## Implementation strategy

This spec is retrospective: the Company drift-protection (US1), the Portfolio kit (US2), and the gated Woo kit
(US3) are already delivered, unit-tested (3 + 4 + 3 cases), and verified on real WP. The remaining work is the
one tracked debt (T010 → P2 formal guard run, incl. woo-guard) — **not** new feature work. Visual/editor
validity of templates/patterns is a documented browser follow-up.

## Parallel opportunities

- The three kits touch independent directories — US1/US2/US3 verification can proceed in parallel.
- Within P2, clean-code/wp-guard/woo-guard/test/docs runs target different surfaces and can be parallelized.
