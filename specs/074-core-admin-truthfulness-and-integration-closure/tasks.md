# Spec 074 — Tasks

## Phase 0 — Reconciliation and evidence

- [x] T001 Reconcile `PROGRESS.md` with GitHub reality (Spec 073 merged, PR #129, `9b5939f`).
- [x] T002 Point `.specify/feature.json` at this spec.
- [x] T003 Update `ROADMAP.md` §17: 073 done, 074 active, 075 queued, Capability Inspector recorded
      as a later candidate.
- [x] T004 Add the Data workspace tabs to `tests/e2e/render-admin.mjs` so dead-end tabs appear in
      evidence.
- [x] T005 Capture `evidence/before/` on the real install.

## Phase A — Unified form catalog (FR-1)

- [ ] T010 [RED] `tests/Unit/Forms/FormCatalogTest.php`: merge, slug precedence, malformed-entry
      rejection, unavailable submission count, empty-registry safety.
- [ ] T011 `Corex\Forms\Catalog\FormSource` (enum-like constants) + `FormCatalogEntry` (immutable).
- [ ] T012 `Corex\Forms\Catalog\FormCatalogProvider` interface — the third-party seam.
- [ ] T013 `Corex\Forms\Catalog\FormCatalog`: merge flows + code forms + providers, dedupe by slug,
      deterministic precedence, normalise untrusted entries.
- [ ] T014 Register the catalog in `FormsServiceProvider`.
- [ ] T015 `FlowFilterOptions` sources from the catalog; `corex_submission_filter_options` applied
      after the merge; `id => 0` semantics preserved.
- [ ] T016 [RED→GREEN] `tests/Integration/Forms/CodeFormDiscoveryTest.php`: a Perego-style
      `FormRegistry` registration appears in both filters and in the catalog **with no filter hook**.
- [ ] T017 Forms & Flows renders one catalog: visual flows editable, code forms as read-only
      definitions (label, slug, source badge, fields, validation, submissions link, explanation).
- [ ] T018 Overview form counts and any readiness/diagnostics surface read the catalog.
- [ ] T019 Jest coverage for the catalog list rendering and the read-only code-form panel.

## Phase B — Submission Inbox heading rhythm (FR-2)

- [ ] T020 Wrap the eyebrow/title/count in a `.corex-inbox__heading` stack.
- [ ] T021 Token-only grid spacing in `submissions-admin.scss`; remove the fighting per-`p` margins;
      rebuild the `.css`.
- [ ] T022 Playwright assertion: measured gaps at desktop, 360px, and 200% zoom, LTR and RTL.

## Phase C — Truthful Data Models (FR-3)

- [ ] T030 [RED] `tests/Unit/Data/ManagedTableDeclarationTest.php`: declaration validation, read-only
      default, derived capability flags, rollback truthfulness.
- [ ] T031 Extend `ManagedTable` with optional `writableFields`, `importAliases`, `validation`,
      `unknownColumns`, `migrations`. Existing call sites unchanged.
- [ ] T032 `TableDataSource` derives `capabilities()` from the declaration and implements
      `WritableDataSource` / `MigrationAwareDataSource` only when declared.
- [ ] T033 `WpTableWriteAdapter` — prepared, bounded, writes only declared fields.
- [ ] T034 `ManagedTableMigrationProvider` — plans, snapshots, executes, rolls back only when a down
      path is declared.
- [ ] T035 Newsletter declares `corex_subscribers` as the import- and migration-capable reference
      model.
- [ ] T036 Tab eligibility: `allowedTabs()` requires permission **and** an eligible source;
      `DataModelsScreen` passes eligibility; `resolveTab`/`tabFromUrl` fall back correctly.
- [ ] T037 `ModelsPanel` states capabilities in plain language; "adapter" leaves the user workflow.
- [ ] T038 [RED→GREEN] Integration: dry run → mapping → rejections → commit → audit; migration
      preview → apply → history → rollback; permission denial; production-mode confirmation;
      failure states.
- [ ] T039 Jest for tab eligibility and the capability copy.

## Phase D — Notification action center (FR-4)

- [ ] T040 [RED] `tests/Unit/Notifications/NotificationStatusTest.php`: read ≠ resolved; reopening;
      dismissed; snoozed; expired.
- [ ] T041 Pure status derivation shared by the screen and the drawer.
- [ ] T042 Four-view IA (Action needed · Updates · History · Preferences) with category, source,
      severity, environment, and assignment as filters.
- [ ] T043 Shared notification item component with the full FR-4.4 anatomy.
- [ ] T044 Wire mark read / unread / dismiss / snooze / resolve / primary action / mark all read,
      hiding what the actor may not do.
- [ ] T045 Rewrite the eight producers to outcome-first copy.
- [ ] T046 Category icons from the existing icon system.
- [ ] T047 Jest + Playwright across every listed state, drawer↔screen parity, keyboard, SR names.

## Phase E — Capability diagnostics (FR-5)

- [ ] T050 [RED] `tests/Unit/DataModels/CapabilityReportTest.php`: shape, allow-listing, no secrets.
- [ ] T051 `CapabilityReport` aggregating forms, models, add-ons, producers, missing providers.
- [ ] T052 Render it on the Models screen with an action or docs link per row.

## Phase F — Close

- [ ] T060 Full gate: PHP lint, Pest unit, integration, Jest, builds, CSS lint, guards, token
      inventory, Playwright, CodeQL.
- [ ] T061 Browser acceptance: dark/light, LTR/RTL, desktop/narrow, keyboard, 200% zoom.
- [ ] T062 `evidence/after/` captured and referenced from `spec.md`.
- [ ] T063 Guard Gate on the diff (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`).
- [ ] T064 Docs, `DECISIONS.md`, `CHANGELOG.md`, `PROGRESS.md`.
- [ ] T065 PR, CI green, merge, delete branch.
