# Implementation Plan: Kit Apply Must Never Leave a Blank Front Page

**Branch**: `feature/041-kit-front-page` | **Date**: 2026-06-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/041-kit-front-page/spec.md`

## Summary

Applying a kit can leave a blank front page: `Corex\Kit\KitPagePlanner::toCreate()` drops every declared
page whose slug already exists, and `Corex\Kit\BlueprintActivator::seedPages()` sets the front page only
inside the loop over newly-created pages. So a pre-existing empty "Home" is skipped entirely — never
populated, never assigned as the front page (verified live: page 2511 = 0 blocks, set as front page).

**Approach.** Replace the binary "create vs skip" with a pure three-way classification — **create** (slug
absent), **adopt** (slug exists but the page is empty or an un-populated kit placeholder), **skip** (slug
exists with real user content) — computed by the pure `KitPagePlanner` from per-slug signals the WP boundary
supplies. `BlueprintActivator` then creates or **populates** each page accordingly, records the disposition
(`_corex_kit_page` = `created` | `adopted`), and sets the front page **after** the loop whenever the declared
home was created or adopted. The CLI soft reset (`ResetExecutor`) branches on the recorded disposition:
**created → delete** (as today), **adopted → empty the kit content + untrack** (never delete a page the user
owned). Apply returns a per-page **outcome** the Setup Wizard surfaces as a summary notice (and spec 042
reuses). No kit pattern/block change; no new dependency.

## Technical Context

**Language/Version**: PHP 8.3.

**Primary Dependencies**: WordPress core only — `wp_insert_post`, `wp_update_post`, `wp_delete_post`,
`get_page_by_path`, `get_post_field`, `get_post_meta`/`update_post_meta`/`delete_post_meta`, options
(`show_on_front`, `page_on_front`, `corex_kit_seeded_pages`). No new dependency.

**Storage**: WP `page` posts; post meta `_corex_kit_page` (value extended from `'1'` to `created`|`adopted`);
option `corex_kit_seeded_pages` (the index of kit-touched page ids, unchanged shape).

**Testing**: Pest unit — the classifier and the blank-content predicate are pure (headless); `BlueprintActivator`
apply behavior and `ResetExecutor` disposition branching are tested at the WP boundary with Brain Monkey.

**Target Platform**: WordPress ≥ 7.0, any mount; runs in admin (Setup Wizard / spec-042 prompt) and CLI (reset).

**Project Type**: WordPress framework — the shared `Corex\Kit` kit-apply path (in `corex-kit-company`, the host
of the kit framework that Portfolio/Woo blueprints extend) + the CLI reset (`packages/cli`).

**Performance Goals**: Negligible — a handful of pages per kit; one `get_page_by_path` + content read per
declared page at apply time.

**Constraints**: Idempotent + non-destructive (never overwrite content present at apply time, FR-006); pure
classifier (FR-007); behavior-preserving for the create path and for already-applied kits; no new dep (FR-010).

**Scale/Scope**: 2–3 declared pages per kit across the Company + Portfolio (+ future) blueprints; one pure
classifier change, one activator change, one reset-executor branch, the apply outcome return + wizard notice.

## Constitution Check

*GATE: Must pass before Phase 0. Re-checked after Phase 1 — still PASS.*

- [x] **I. Theme is a skin** — N/A. No theme code; kit apply creates `page` posts whose content composes
  existing patterns (presentation already lives in the theme/patterns, unchanged).
- [x] **II. Plugins boot themselves** — N/A/PASS. No boot/registration change; the apply path runs from an
  admin action and the CLI, not page load.
- [x] **III. Thin controllers, fat services** — PASS. The decision is the pure `KitPagePlanner` (classifier);
  `BlueprintActivator` is the orchestration/boundary service; `SetupWizardScreen` stays a thin render+gate.
- [x] **IV. Everything injected** — PASS. `KitPagePlanner` is injected into `BlueprintActivator` (constructor);
  no dependency is `new`-ed inside a method. (The existing constructor-default `KitPagePlanner` is a pure,
  stateless helper and remains overridable for tests — kept consistent with the current code.)
- [x] **V. Runtime tokens** — N/A. No styling; seeded content uses the kit patterns' existing token-only markup.
- [x] **VI. Conditional assets** — N/A. No assets added.
- [x] **VII. Declarative security** — N/A for new routes (none). Apply is triggered by the existing
  `SetupWizardScreen` (already `AdminGuard`-gated) and the CLI reset (already behind `ResetGate` + the typed
  safeguard). This feature adds no unguarded entry point.
- [x] **VIII. RTL-first** — N/A beyond the apply-summary admin notice text (i18n via `corex`, logical layout).
- [x] **IX. No optional dep is hard** — N/A. WordPress core only.
- [x] **X. Spec is source of truth** — PASS. Traces to `specs/041-kit-front-page/spec.md`.
- [x] **Guard Gate + Definition of Done** — acknowledged: `clean-code-guard` + `wp-guard` (post/meta/option
  writes) + `test-guard` (Pest) + `docs-guard` (READMEs/docs-app). Tests, i18n notice string, PROGRESS/DECISIONS
  updated.

**Result: PASS — no violations. Complexity Tracking not required.**

## Project Structure

### Documentation (this feature)

```text
specs/041-kit-front-page/
├── plan.md              # This file
├── spec.md
├── research.md          # Phase 0 — classification model, emptiness rule, reset-disposition design
├── data-model.md        # Phase 1 — declared page, page signal, disposition, apply outcome, tracking record
├── quickstart.md        # Phase 1 — reproduce blank-home → apply → populated front page; reset behavior
├── contracts/
│   └── kit-apply.md     # KitPagePlanner + BlueprintActivator apply + ResetExecutor disposition contracts
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (NOT created here)
```

### Source Code (repository root)

```text
plugins/corex-core/                            # the pure, domain-neutral provisioning seam (shared with spec 042)
└── src/Provisioning/
    ├── PageContent.php           # NEW  — pure: isBlank(string $content): bool (emptiness predicate)
    ├── PagePlanner.php           # NEW  — pure: plan(declared, signals): list<PageDisposition> (create/adopt/skip)
    ├── PageDisposition.php       # NEW  — value object: slug, title, action(create|adopt|skip), reason
    └── ApplyOutcome.php          # NEW  — value object: list<PageResult>, modules, flags, frontPageId

addons/corex-kit-company/                      # the kit framework (consumes the core Provisioning seam)
├── src/
│   ├── BlueprintActivator.php    # EDIT — build per-slug signals, call core PagePlanner; create/populate per
│   │                             #        disposition; set front page after the loop for created|adopted home;
│   │                             #        record `_corex_kit_page`=created|adopted; return core ApplyOutcome
│   ├── KitPagePlanner.php        # REMOVE — superseded by corex-core PagePlanner (update callers)
│   └── SetupWizardScreen.php     # EDIT — render the returned ApplyOutcome as an admin-notice summary

packages/cli/
└── src/Reset/
    └── ResetExecutor.php         # EDIT — per tracked kit page, branch on _corex_kit_page disposition:
                                  #        created → delete (today); adopted → empty content + delete meta + untrack

tests/ (Pest) → repo-root tests/Unit/Provisioning/ + tests/Unit/Kit/ + tests/Unit/Reset/
├── PagePlannerTest.php           # NEW — create / adopt-empty / adopt-placeholder / skip-user-content (headless)
├── PageContentTest.php           # NEW — blank string / whitespace / empty paragraph → blank; real block → not
├── BlueprintActivatorApplyTest.php # NEW — populates adopted home + sets front page; skips user content; idempotent
└── ResetDispositionTest.php      # NEW — created page deleted; adopted page emptied + retained
```

**Structure Decision**: The page-classification logic is pure and domain-neutral, so it lives in **corex-core**
as a new `Corex\Provisioning\` seam — `PagePlanner` (create/adopt/skip), `PageContent::isBlank`, and the
`PageDisposition` / `ApplyOutcome` value objects — unit-tested headlessly (FR-007, SC-005). Placing it in
corex-core (not the kit addon) lets spec 042's corex-config consumers reuse the same representation through a
core interface **without a core→addon dependency** (Principle IX). `BlueprintActivator` (in corex-kit-company)
stays the single WP boundary that reads page signals, writes pages/meta/options, sets the front page, and
returns a core `ApplyOutcome` — and it is the **shared** apply path every blueprint (Company, Portfolio, future)
already routes through, so the fix lands for all kits at once (FR-010). The old `Corex\Kit\KitPagePlanner` is
removed in favour of the core `PagePlanner`. The CLI `ResetExecutor` is the only other WP boundary touched, to
honor the new created-vs-adopted disposition (FR-008). `SetupWizardScreen` changes only to render the returned
outcome as a notice (FR-009); the richer prompt/preview/dashboard is spec 042.

## Complexity Tracking

> No Constitution violations — section intentionally empty.
