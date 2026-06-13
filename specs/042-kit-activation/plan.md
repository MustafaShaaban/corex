# Implementation Plan: Unified Kit Activation — Prompt-to-Apply with a "What Changed" Summary

**Branch**: `feature/042-kit-activation` | **Date**: 2026-06-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/042-kit-activation/spec.md` · **Depends on**: spec 041.

## Summary

Enabling a kit (Addon Manager) only flips a plugin + flag and creates no content, while seeding lives
separately in the Company Setup Wizard — two uncoordinated surfaces, so enabling a kit changes nothing visible.
This feature converges them on one path and adds transparency: enabling a kit records a **pending apply** and
surfaces a dismissible prompt with a **read-only preview** (per-page create/populate/skip from spec 041, the
front-page target, required modules/flags); choosing **Apply** runs the **single shared apply** (spec 041's
`BlueprintActivator` via a core `KitProvisioner` seam) and shows a **"what changed" summary**; a Corex dashboard
**"Site status" card** shows applied kits, the live submission count (linked to Data), and the front-page status.

**Approach.** Introduce a domain-neutral `Corex\Provisioning\KitProvisioner` **interface in corex-core**
(`applicableKits()` / `preview($kit)` / `apply($kit)`), implemented by a corex-kit-company adapter bound in the
container; **corex-config depends only on the interface** and resolves it **optionally** (graceful no-op when no
kit framework is active — Principle IX). The preview is a pure read-only computation reusing spec 041's
`PagePlanner` over current-site signals; apply reuses spec 041's `BlueprintActivator` (one apply path, no
duplicated seeding). All apply actions are `AdminGuard`-gated; everything server-rendered (no new build).

## Technical Context

**Language/Version**: PHP 8.3; server-rendered admin (no JS build, consistent with existing dashboard/wizard).

**Primary Dependencies**: WordPress core (admin menus/notices, options, `wp_count_posts`/the submissions reader,
`get_option('page_on_front')`); Corex PSR-11 container; the shared `AdminGuard` (spec P5/v1.2.1); spec-030
submissions data source; spec 041 `PagePlanner`/`BlueprintActivator`/`ApplyOutcome`. **No new dependency.**

**Storage**: options only — extend kit tracking with a **pending-apply** record (enabled-but-unapplied) and an
**applied-kits** record; reuse `corex_kit_seeded_pages` + `_corex_kit_page` (spec 031/041). No new tables.

**Testing**: Pest — pure `ApplyPreview` builder + the `SiteStatusCard` view model are headless; the provisioner
adapter, the AddonsScreen prompt/apply handler, and `AdminGuard` gating are tested at the WP boundary
(Brain Monkey). One integration check that enable→preview→apply seeds via the same path as the wizard.

**Target Platform**: WordPress admin (Add-ons screen, Corex dashboard); CLI unaffected.

**Project Type**: WordPress framework — corex-core (the provisioner seam), corex-kit-company (the adapter +
wizard reuse), corex-config (Add-ons prompt + dashboard card).

**Performance Goals**: Negligible — preview/summary are computed on demand for one kit; the dashboard card reads
a count + a couple of options per view.

**Constraints**: No content change without explicit Apply (FR-002); one shared apply path (FR-004); cap+nonce
gating (FR-007); graceful degradation when an add-on/kit framework is inactive (FR-010); no auto-apply; no new
runtime/build dependency (FR-012). **Hard dependency on spec 041** (create/adopt/skip + ApplyOutcome).

**Scale/Scope**: 2–3 kits; one core interface + 3 core VOs, one adapter, one prompt/apply controller, one
dashboard card, small edits to AddonsScreen + AdminDashboard + (reuse) SetupWizardScreen summary.

## Constitution Check

*GATE: Must pass before Phase 0. Re-checked after Phase 1 — still PASS.*

- [x] **I. Theme is a skin** — N/A. Admin-side activation + a dashboard card; seeded content uses existing patterns.
- [x] **II. Plugins boot themselves** — PASS. The provisioner seam is bound in corex-core; the adapter in the kit
  framework; the screens register on `admin_menu`/`admin_init`. Works without a theme.
- [x] **III. Thin controllers, fat services** — PASS. Pure `ApplyPreview` builder + `SiteStatusCard` view model
  hold the logic; `AddonsScreen`/the prompt controller/`AdminDashboard` only render + gate + delegate.
- [x] **IV. Everything injected** — PASS. corex-config resolves `KitProvisioner` + the submissions reader from
  the container; the adapter is injected with the registry/activator. No `new` of a dependency in a method.
- [x] **V. Runtime tokens** — PASS. The prompt + card use existing admin classes / token-only styles; no raw
  hex/size (reuse the spec-033 `.card` admin conventions already used by these screens).
- [x] **VI. Conditional assets** — N/A. No block assets; server-rendered, no new global library.
- [x] **VII. Declarative security** — PASS (admin-screen rule). The apply action + dismiss are cap-gated and
  nonce-verified via the shared `AdminGuard` (`authorized()` + `verifiedPost()`); the preview is read-only;
  output escaped. No hand-rolled checks.
- [x] **VIII. RTL-first** — PASS. Prompt + card use logical properties; all strings i18n via `corex`.
- [x] **IX. No optional dependency is hard** — PASS (central to the design). corex-config depends only on the
  corex-core `KitProvisioner` **interface** and resolves it optionally; with no kit framework active, no prompt
  shows and the card degrades to an actionable empty state. corex-config never references a kit-addon class.
- [x] **X. Spec is source of truth** — PASS. Traces to `specs/042-kit-activation/spec.md`; depends on 041.
- [x] **Guard Gate + Definition of Done** — acknowledged: `clean-code-guard` + `wp-guard` (admin/options/escaping)
  + `test-guard` + `docs-guard`. Tests, i18n, RTL, WCAG (admin notices/cards), PROGRESS/DECISIONS updated.

**Result: PASS — no violations. Complexity Tracking not required.**

## Project Structure

### Documentation (this feature)

```text
specs/042-kit-activation/
├── plan.md  ├── spec.md  ├── research.md  ├── data-model.md  ├── quickstart.md
├── contracts/kit-provisioner.md      # the core seam + preview/summary + dashboard card contracts
└── checklists/requirements.md        # (done)
```

### Source Code (repository root)

```text
plugins/corex-core/
└── src/Provisioning/
    ├── KitProvisioner.php        # NEW (interface) — applicableKits(): list<KitSummary>;
    │                             #   preview(string $kit): ApplyPreview;  apply(string $kit): ApplyOutcome
    ├── KitSummary.php            # NEW (VO) — name, label, applied(bool), pageCount, requiredModules
    ├── ApplyPreview.php          # NEW (VO) — list<PageDisposition> (read-only) + frontTargetSlug + modules + flags
    └── (PagePlanner/PageDisposition/ApplyOutcome from spec 041 reused)

addons/corex-kit-company/
└── src/Provisioning/
    ├── BlueprintKitProvisioner.php  # NEW — implements Corex\Provisioning\KitProvisioner over BlueprintRegistry
    │                                #   + SetupWizard (plan) + BlueprintActivator (apply); preview = read-only
    │                                #   PagePlanner pass; tracks pending/applied via options
    └── (KitServiceProvider.php      # EDIT — bind KitProvisioner => BlueprintKitProvisioner in the container)
        (SetupWizardScreen.php       # EDIT — render the shared "what changed" summary from ApplyOutcome)

plugins/corex-config/
└── src/
    ├── Addons/AddonsScreen.php       # EDIT — on enable of a kit add-on: record pending-apply; render the prompt
    │                                 #   (preview) + handle the Apply/Dismiss POST (AdminGuard); show summary
    ├── Addons/KitActivationView.php  # NEW (pure) — builds the prompt + summary view model from ApplyPreview/Outcome
    ├── Dashboard/SiteStatusCard.php   # NEW (pure) — view model: applied kits, submission count, front-page status
    └── Settings/AdminDashboard.php    # EDIT — render the SiteStatusCard (resolves provisioner + submissions reader)

tests/ (Pest) → repo-root tests/Unit/Provisioning + tests/Unit/Addons + tests/Unit/Dashboard + tests/Integration
├── ApplyPreviewTest.php             # NEW — preview classifies pages read-only; no writes; matches 041 rules
├── KitActivationViewTest.php        # NEW — prompt/summary view model (created/populated/skipped + links)
├── SiteStatusCardTest.php           # NEW — counts, applied kits, front-page states incl. empty state
├── BlueprintKitProvisionerTest.php  # NEW — preview is read-only; apply delegates to the shared activator
├── AddonsScreenKitPromptTest.php    # NEW — kit enable → prompt; non-kit → none; apply gated by AdminGuard
└── KitActivationIntegrationTest.php # NEW — enable→apply seeds via the SAME path as the wizard (one outcome shape)
```

**Structure Decision**: The cross-plugin seam is a corex-core **interface** (`Corex\Provisioning\KitProvisioner`)
returning the spec-041 value objects, so corex-config consumes one stable contract and never depends on a kit
addon (Principle IX). The kit framework provides the concrete `BlueprintKitProvisioner` adapter (the only place
that knows blueprints), bound in the container; both the Add-ons prompt and the Setup Wizard call it, giving the
single shared apply path + one preview/summary representation (FR-004/FR-008). Pure pieces (`ApplyPreview`
builder, `KitActivationView`, `SiteStatusCard`) are headless-tested; the screens stay thin and `AdminGuard`-gated.

## Complexity Tracking

> No Constitution violations — section intentionally empty.
