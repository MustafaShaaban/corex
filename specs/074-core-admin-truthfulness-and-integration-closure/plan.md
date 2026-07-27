# Spec 074 — Implementation plan

Baseline `main` @ `9b5939f`. Branch `spec/074-core-admin-truthfulness`.

## Architecture decisions

**A. The catalog belongs to corex-forms, not corex-config.**
`FormRegistry` and `FlowRepository` both live in `corex-forms`; a catalog in `corex-config` would
have to reach across the boundary for both. `Corex\Forms\Catalog\*` is pure (no WordPress), so it
stays unit-testable, and `corex-config` consumes it through the container exactly as
`FlowFilterOptions` already resolves `FlowRepository` — lazily, inside `try/catch`, so an absent
Forms add-on degrades instead of fatalling (Principle IX).

**B. Capability is declared on the model, derived everywhere else.**
`DataSourceCapabilities` is already granular and complete — the gap is that nothing can opt in.
Rather than add a parallel mechanism, `ManagedTable` gains an optional declaration and
`TableDataSource` *derives* its capabilities from it. Every other layer (tabs, panels, REST gate,
diagnostics) reads the derived capabilities, so there is one source of truth and read-only stays
the default for anything that says nothing.

**C. Read vs resolved is a derived status, computed once.**
The data already supports the distinction (`resolvedAt` on the shared record, read state per user).
A single pure `NotificationStatus` derivation turns record + user state into one of
`action_needed` / `update` / `history`, and both the screen and the drawer consume it. No consumer
re-derives.

**D. One notification item component.**
The drawer and the screen render the same component so FR-4.9 holds by construction.

## Work order

Sequential; each step green before the next.

1. **Tracking reconciliation** — `PROGRESS.md`, `.specify/feature.json`, `ROADMAP.md` §17. *(done)*
2. **Evidence capture** — `evidence/before/`, plus the Data tab screens added to
   `tests/e2e/render-admin.mjs`. *(done)*
3. **074-A form catalog** — domain → consumers → tests.
4. **074-B inbox heading** — markup + SCSS + built CSS + browser assertion.
5. **074-C data models** — `ManagedTable` declaration → `TableDataSource` derivation → write adapter
   → migration provider → newsletter reference model → tab eligibility → panel copy.
6. **074-D notifications** — status derivation → producers → item component → screen → drawer.
7. **074-E diagnostics** — capability summary on the Models screen.
8. **Gate + browser acceptance + `evidence/after/`.**
9. **Docs, DECISIONS, CHANGELOG, PROGRESS.**

## Files

| Step | Files |
|---|---|
| A | `plugins/corex-forms/src/Catalog/{FormCatalog,FormCatalogEntry,FormCatalogProvider,FormSource}.php`; `plugins/corex-forms/src/FormsServiceProvider.php`; `plugins/corex-config/src/Forms/{FlowFilterOptions,FormsFlowsScreen,FormsOverview}.php`, `Forms/FlowList.js`, `Forms/index.js` |
| B | `plugins/corex-config/src/Submissions/index.js`; `plugins/corex-config/assets/submissions-admin.scss` + built `.css`; `tests/e2e/submissions-inbox.spec.js` |
| C | `plugins/corex-core/src/Database/Schema/ManagedTable.php`; `plugins/corex-core/src/Data/{DataSourceCapabilities,DataWriteAdapter}.php`; `plugins/corex-config/src/Data/{TableDataSource,WpTableDataReader,TableDataReader}.php`; `plugins/corex-config/src/DataModels/{modelClient.js,ModelsPanel.js,ImportPanel.js,MigrationsPanel.js,DataModelsScreen.php,DataModelsCatalog.php}`; `plugins/corex-config/src/ConfigServiceProvider.php`; `addons/corex-newsletter/src/NewsletterServiceProvider.php` |
| D | `plugins/corex-core/src/Notifications/*`; `plugins/corex-config/src/Notifications/Producers/*`; `plugins/corex-config/src/admin/notifications/*`; `plugins/corex-config/src/admin/components/{NotificationDrawer,NotificationCenter}.js`; `plugins/corex-config/assets/*` |
| E | `plugins/corex-config/src/DataModels/{CapabilityReport.php,CapabilityPanel.js}`; `plugins/corex-config/src/Addons/AddonCatalogService.php` (reused) |

## Risks

| Risk | Mitigation |
|---|---|
| Submission counts per form become an N+1 query | One grouped meta query, or report the count unavailable rather than issue per-form queries |
| Changing `ManagedTable`'s constructor breaks the eight existing call sites | New parameters are optional with read-only defaults; existing calls compile unchanged |
| Replacing the notification IA removes views someone uses | Every removed view survives as a filter; recorded in DECISIONS |
| Newsletter add-on inactive on a given site | The reference model registers only when the add-on is active; tabs then hide, which is the correct truthful behaviour |
| Import into a live table | Dry run is mandatory, unknown columns rejected by default, commit needs a confirmation token, activity recorded |
