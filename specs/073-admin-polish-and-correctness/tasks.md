# Spec 073 — Tasks

All complete on `spec/073-admin-polish-and-correctness`. Test IDs name the covering test.

| ID | Task | Files | Tests | Status |
|----|------|-------|-------|--------|
| T001 | Add-ons state buckets (filter/counts, exhaustive & badge-consistent) | `Addons/AddonCatalogService.php` | `Addons/AddonCatalogServiceTest.php` — splits into mutually exclusive buckets · installed-not-running is inactive · unknown filter shows all | ✅ |
| T002 | Add-ons filter tab strip + distinct empty-view state | `Addons/AddonsScreen.php` | (rendered via `render-admin.mjs`) | ✅ |
| T003 | Stop `wp_kses_post()` stripping chrome SVGs | `DataModels/DataModelsScreen.php` | manual render (Data Models icons restored) | ✅ |
| T004 | Remove inert client mode preview; single server mode control | `Security/OperationsSecurityScreen.php`, `Security/SecurityCenter.js`, `Security/securityCenterState.js` | `Security/__tests__/securityCenter.test.js` (obsolete preview test removed; state tests kept) | ✅ |
| T005 | Readiness badge reads readiness snapshot, not `modeActionState` | `Security/SecurityCenter.js` | securityCenter state suite | ✅ |
| T006 | Record-authoritative detail rows | `admin/data/recordRows.js` (new), `admin/data/RecordDetail.js` | `admin/__tests__/recordRows.test.js` — 6 cases (submission answers, absent declared field, flat table, undeclared value, false-vs-empty, null record) | ✅ |
| T007 | Settings dropdowns → CorexSelect | `Settings/SettingsForm.php` | manual render | ✅ |
| T008 | Notifications severity → CorexSelect, translated labels | `admin/notifications/NotificationsApp.js` | `admin/__tests__/corexSelect.test.js` (existing) | ✅ |
| T009 | Toolbar states count once (visible), counted phrasing on meta.title | `Notifications/NotificationToolbar.php` | `Notifications/NotificationToolbarTest.php` — states the count once | ✅ |
| T010 | Baseline admin prose rhythm + `.corex-state` margin + notifications nav icon | `corex-admin-shell.css`, `assets/icons/nav-notifications.svg` (new) | `tests/token-inventory.test.js` | ✅ |
| T011 | Notifications page identity/labels + e2e screen | `core/src/Admin/AdminPage.php`, `tests/e2e/render-admin.mjs` | render harness | ✅ |
| T012 | Regenerate token inventory | `057/inventories/{admin-and-aliases,consumers}.json` | `scripts/generate-token-inventory.mjs` reproduces with no further diff | ✅ |

## Verification summary
- Pest unit **1457/0** (6315 assertions) · Jest **311/0** (58 suites).
- Guards clean: `wp-guard`, `clean-code-guard`, `test-guard`.
- Integration suite is CI's authority (order-flaky locally); not run locally for this checkpoint.
