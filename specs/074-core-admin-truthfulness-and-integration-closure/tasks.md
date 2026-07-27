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

- [x] T010 [RED] `tests/Unit/Forms/FormCatalogTest.php`: merge, slug precedence, malformed-entry
      rejection, unavailable submission count, empty-registry safety.
- [x] T011 `Corex\Forms\Catalog\FormSource` (enum-like constants) + `FormCatalogEntry` (immutable).
- [x] T012 `Corex\Forms\Catalog\FormCatalogProvider` interface — the third-party seam.
- [x] T013 `Corex\Forms\Catalog\FormCatalog`: merge flows + code forms + providers, dedupe by slug,
      deterministic precedence, normalise untrusted entries.
- [x] T014 Register the catalog in `FormsServiceProvider`.
- [x] T015 `FlowFilterOptions` sources from the catalog; `corex_submission_filter_options` applied
      after the merge; `id => 0` semantics preserved.
- [x] T016 [RED→GREEN] `tests/Integration/Forms/CodeFormDiscoveryTest.php`: a Perego-style
      `FormRegistry` registration appears in both filters and in the catalog **with no filter hook**.
- [x] T017 Forms & Flows renders one catalog: visual flows editable, code forms as read-only
      definitions (label, slug, source badge, fields, validation, submissions link, explanation).
- [x] T018 Overview form counts and any readiness/diagnostics surface read the catalog.
- [x] T019 Jest coverage for the catalog list rendering and the read-only code-form panel.

## Phase B — Submission Inbox heading rhythm (FR-2)

- [x] T020 Wrap the eyebrow/title/count in a `.corex-inbox__heading` stack.
- [x] T021 Token-only grid spacing in `submissions-admin.scss`; remove the fighting per-`p` margins;
      rebuild the `.css`.
- [x] T022 Playwright assertion: measured gaps at desktop, 360px, and 200% zoom, LTR and RTL.

## Phase C — Truthful Data Models (FR-3)

- [x] T030 [RED] `tests/Unit/Data/ManagedTableDeclarationTest.php`: declaration validation, read-only
      default, derived capability flags, rollback truthfulness.
- [x] T031 Extend `ManagedTable` with optional `writableFields`, `importAliases`, `validation`,
      `unknownColumns`, `migrations`. Existing call sites unchanged.
- [x] T032 `TableDataSource` derives `capabilities()` from the declaration and implements
      `WritableDataSource` / `MigrationAwareDataSource` only when declared.
- [x] T033 `WpTableWriteAdapter` — prepared, bounded, writes only declared fields.
- [x] T034 `ManagedTableMigrationProvider` — plans, snapshots, executes, rolls back only when a down
      path is declared.
- [x] T035 Newsletter declares `corex_subscribers` as the import- and migration-capable reference
      model.
- [x] T036 Tab eligibility: `allowedTabs()` requires permission **and** an eligible source;
      `DataModelsScreen` passes eligibility; `resolveTab`/`tabFromUrl` fall back correctly.
- [x] T037 `ModelsPanel` states capabilities in plain language; "adapter" leaves the user workflow.
- [x] T038 [RED→GREEN] Integration: dry run → mapping → rejections → commit → audit; migration
      preview → apply → history → rollback; permission denial; production-mode confirmation;
      failure states.
- [x] T039 Jest for tab eligibility and the capability copy.

## Phase D — Notification action center (FR-4)

- [x] T040 [RED] `tests/Unit/Notifications/NotificationStatusTest.php`: read ≠ resolved; reopening;
      dismissed; snoozed; expired.
- [x] T041 Pure status derivation shared by the screen and the drawer.
- [x] T042 Four-view IA (Action needed · Updates · History · Preferences) with category, source,
      severity, environment, and assignment as filters.
- [x] T043 Shared notification item component with the full FR-4.4 anatomy.
- [x] T044 Wire mark read / unread / dismiss / snooze / resolve / primary action / mark all read,
      hiding what the actor may not do.
- [x] T045 Rewrite the eight producers to outcome-first copy.
- [x] T046 Category icons from the existing icon system.
- [x] T047 Jest + Playwright across every listed state, drawer↔screen parity, keyboard, SR names.
      32 Jest specs in `plugins/corex-config/src/admin/notifications/__tests__/notificationItem.test.js`
      covering read-vs-resolved, the four closing statuses, control visibility by permission and by
      closed state, drawer↔screen parity, the meta anatomy, and the relative-time boundaries.
      `tests/e2e/notification-center.spec.js` rewritten off the retired five-tab IA onto the three
      views + Preferences, and now asserts the retired tabs are *absent* — the guard against a
      silent revert. Item content is deliberately not asserted in the browser: nothing seeds
      notification records, so it would test whatever the run happened to produce.

## Phase E — Capability diagnostics (FR-5)

- [x] T050 [RED] `tests/Unit/DataModels/CapabilityReportTest.php`: shape, allow-listing, no secrets.
- [x] T051 `CapabilityReport` aggregating forms, models, add-ons, producers, missing providers.
- [x] T052 Render it on the Models screen with an action or docs link per row.

## Phase F — Close

- [x] T060 Full gate: PHP lint, Pest unit, integration, Jest, builds, CSS lint, guards, token
      inventory, Playwright, CodeQL.
- [x] T061 Browser acceptance: dark/light, LTR/RTL, desktop/narrow, keyboard, 200% zoom.
- [x] T062 `evidence/after/` captured and referenced from `spec.md`.
- [x] T063 Guard Gate on the diff (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`).
- [x] T064 Docs, `DECISIONS.md`, `CHANGELOG.md`, `PROGRESS.md`.
- [x] T065 PR, CI green, merge, delete branch. PR **#130**, squash-merged as **`d243b7f`**, now the
      tip of `main` on both `origin` and `upstream` (Azure). All six checks green before merge:
      PHP 8.3 lint+unit, Jest, integration on a real WordPress, Playwright, and both CodeQL jobs.
