# Implementation Plan: Admin Correctness & Login Hiding Parity

**Branch**: `fix/069-admin-correctness-and-login-parity` | **Date**: 2026-07-16 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/069-admin-correctness-and-login-parity/spec.md`

## Summary

Correct seven defects an owner review found in the admin that Spec 068 shipped. The load-bearing one is login hiding: it was specified to behave like WPS Hide Login and does not — it fingerprints itself with a bare `wp_die`, runs its rules too late, holds two rulesets that disagree, and has two reachable owner-lockout paths. The rest are consolidation (one redundant screen), usability (a numeric filter no user can use), honesty (two inert controls), and presentation (a missing CSS base layer, a live dark-mode bug, an incoherent Insights grid).

Approach: rewrite `LoginRouteGuard` against WPS's actual mechanics rather than patch it; close the lockout paths at the validation boundary; delete the redundant screen behind a redirect; reuse `FlowService::all()` through the existing lazy-resolution pattern for the filters; and fix presentation at the shared base layer instead of adding a twelfth per-sheet override.

## Technical Context

**Language/Version**: PHP 8.3+, JavaScript (ES2020+, `@wordpress/element`)

**Primary Dependencies**: WordPress 7.0+; `@wordpress/scripts` (admin bundle); no new dependencies

**Storage**: WordPress options (`corex_login_protection_settings`, autoload off); custom login-attempt/lockout tables; post meta on `corex_submission`

**Testing**: Pest (unit + integration), Jest (JS), Playwright (E2E), stylelint, `composer validate --strict`

**Target Platform**: WordPress admin (single-site and multisite), modern evergreen browsers

**Project Type**: WordPress framework monorepo — plugins under `plugins/`, packages under `packages/`

**Performance Goals**: No regression against the 068 contracts (10k-record admin read clamped to ≤100 rows within 1s p95). Login hiding runs on every request, so it must stay allocation-light and do no database work before its decision.

**Constraints**: Login hiding must add no measurable latency to unaffected requests. Base-layer CSS changes reach all 11 admin sheets.

**Scale/Scope**: 8 workstreams, ~25 source files across `corex-config`, `corex-core`, `packages/cli`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Derived from `.specify/memory/constitution.md` (Corex Constitution v1.2.1).

- [x] **I. Theme is a skin** — N/A. No theme changes. The 404 path *reads* the theme's 404 template through core's ordinary template hierarchy; it adds no logic to the theme.
- [x] **II. Plugins boot themselves** — PASS, and materially improved. The guard currently registers on `wp_loaded`; moving it to `plugins_loaded` prio 1 puts it *on* the constitutional boot hook. Hiding must not engage for CLI/cron/REST contexts (FR-007).
- [x] **III. Thin controllers, fat services** — PASS. `SecuritySettingsController` gains input validation only (its job); slug rules live in a service/value object; lockout reads go through the existing store, not the controller.
- [x] **IV. Everything injected** — PASS, and a live violation gets fixed: `ConfigServiceProvider` L304-308 constructs `LoginProtectionSettings` on every `make()`, so stored data can throw *from the container*. The binding must not be able to fatal.
- [x] **V. Runtime tokens** — PASS. Every spacing/colour value comes from existing `--corex-admin-*` tokens. No raw hex/size/font is introduced. Workstream 7 exists precisely to stop per-sheet hardcoding.
- [x] **VI. Conditional assets** — PASS. Admin sheets stay per-screen and enqueued by their screen; no global library. `InsightsScreen.php` L75's hardcoded `'1.1.0'` version is corrected to `filemtime` to match every other sheet.
- [x] **VII. Declarative security** — PASS. REST routes gain a real `args` schema with `sanitize_callback`/`validate_callback` (they have none today — a standing violation). Admin screens keep routing cap checks through `AdminGuard` per the v1.2.1 clarification, not hand-rolled.
- [x] **VIII. RTL-first** — PASS. All new CSS uses logical properties; the accordion and filters are verified RTL.
- [x] **IX. No optional dep is hard** — PASS. `corex-forms` is read for the filter lists via the documented lazy container resolution in `try/catch` (`InsightWidgetFacts.php` L143-158); both screens must work with forms absent (FR-023, SC-008).
- [x] **X. Spec is source of truth** — PASS. `spec.md` written and validated before this plan; no code written yet.
- [x] **Guard Gate + Definition of Done** — acknowledged. `wp-guard` + `clean-code-guard` on all PHP/JS, `test-guard` on tests, `docs-guard` on docs. Tests, i18n, RTL, WCAG 2.2 AA, docs, PROGRESS, DECISIONS all required before any diff is presented.

**Environment Gate**: must be re-verified before implementation — `wp theme list` shows `corex`, `wp plugin list` shows `corex-core`/`corex-blocks`/`corex-config` active, `http://corex.local` boots with no fatals.

## Project Structure

### Documentation (this feature)

```text
specs/069-admin-correctness-and-login-parity/
├── spec.md              # Written
├── plan.md              # This file
├── checklists/
│   └── requirements.md  # Written — validated, one open risk carried here (resolved below)
└── tasks.md             # /speckit-tasks output
```

`research.md`, `data-model.md`, and `contracts/` are **not** produced. This is a correction spec against a merged codebase: the research is the exploration already recorded here, the data model is unchanged (no new entities — the lockout table already exists), and the one REST contract that changes is a schema tightening documented under Workstream 1b. Inventing those files would be ceremony, not information.

### Source Code (repository root)

```text
plugins/corex-core/
├── src/Admin/AdminPage.php                    # slug→ability map (L325-328)
└── assets/css/
    ├── corex-admin-tokens.css                 # --corex-admin-space-* scale (L83-89)
    └── corex-admin-shell.css                  # WS6 select rules; WS7 base layer

plugins/corex-config/src/
├── Security/
│   ├── LoginProtection/
│   │   ├── LoginRouteGuard.php                # WS1 — rewritten
│   │   ├── LoginProtectionSettingsStore.php   # WS1b — shared sanitiser
│   │   └── LoginProtectionSettings.php        # WS1b — must not throw from container
│   ├── SecuritySettingsController.php         # WS1b — args schema + slug rejection
│   ├── OperationsSecurityScreen.php           # WS2 — real lockouts (L287)
│   ├── SecurityCenter.js                      # WS1c/WS2 — login URL, warning, Recovery
│   └── securityCenterState.js                 # WS1b — default drift
├── Data/DataAdminScreen.php                   # WS3 — removed + redirect
├── DataModels/
│   ├── DataModelsScreen.php                   # WS3 — either-ability gate
│   ├── DataModelsApp.js                       # WS3 — ?tab= sync, per-tab gating
│   └── ModelsPanel.js                         # WS4 — accordion
├── admin/data/QueryBar.js                     # WS5 — form select (by slug)
├── Submissions/
│   ├── index.js                               # WS5 — flow select (by id)
│   └── SubmissionsInboxScreen.php             # WS5 — localize flow list
└── Insights/InsightsScreen.php                # WS8 — filemtime version (L75)

plugins/corex-config/assets/                   # data-models, insights, control-panel, … (WS6/7/8)
packages/cli/src/Commands/SecurityResetLoginCommand.php  # WS1b — reset slug too
```

**Structure Decision**: Existing monorepo layout; no new directories. All work lands in `corex-config` (screens/security), `corex-core` (shared shell CSS + ability map), and one CLI command.

## Resolved risk — the FR-019 permission gap

The requirements checklist carried this open into planning. It is real, and it is worse than it looked.

`MANAGE_DATA` and `MANAGE_DATA_MODELS` are **independent** abilities — `CorexAbilityCatalog.php` L157-178 gives neither an implication of the other; only `MANAGE_ADMIN` implies both (L114-123). Today:

- `corex-data` gates on `MANAGE_DATA` (`DataAdminScreen.php` L41, L50). Every source's read ability is also `MANAGE_DATA` (`DataSourceService.php` L124, `DataRegistry.php` L75, `TableDataSource.php` L127-131). Coherent.
- `corex-data-models` gates on `MANAGE_DATA_MODELS` (`DataModelsScreen.php` L42, L51) but its Records tab renders the same explorer, whose sources still read via `MANAGE_DATA`. **So a `MANAGE_DATA_MODELS`-only user can already open the Records tab and see an empty explorer** — a pre-existing defect this spec inherits.

Naively removing `corex-data` would leave a `MANAGE_DATA`-only user unable to reach records at all. That fails FR-019.

**Resolution** (follows existing precedent — `DataRestGateway.php` L58-59 and `DataManagementController.php` L393-394 both already accept *either* ability):

1. Gate the Data Models **screen** on `MANAGE_DATA || MANAGE_DATA_MODELS`.
2. Gate **each tab** on the ability it actually needs: Records → `MANAGE_DATA`; Models/Import/Export/Migrations → `MANAGE_DATA_MODELS`. Render only permitted tabs; default to the first permitted one.
3. `AdminPage.php` L325-328's slug→ability map updates to match.

This preserves every user's current access, fixes the empty-explorer defect, and keeps one destination. It is not a scope expansion — FR-019 mandates it.

## Workstreams

### WS1 — Login hiding parity (P1)

Rewrite `LoginRouteGuard`:

| Concern | Now | Target |
|---|---|---|
| Fingerprinting | `wp_die('Not found.')` L108-117 — a bare page no real 404 produces | `$wp_query->set_404()` + `status_header(404)` + theme 404 template |
| Timing | `wp_loaded` prio 1 (L36); `$pagenow` set at L59 | `plugins_loaded` prio 1: set `$pagenow` **and** rewrite `$_SERVER['REQUEST_URI']` first |
| Two rulesets | `decision()` L164-184 allows logged-in; `hidesDefaultEndpoint()` L83-106 blocks everyone; only the former is tested | One pure, tested predicate |
| `wp_redirect` | Unfiltered | Filtered — any redirect to the default login rewrites to the slug |
| Shortcuts | core's `wp_redirect_admin_locations` leaks `/login`, `/dashboard`, `/admin` | `remove_action('template_redirect', 'wp_redirect_admin_locations', 1000)` |
| Rewriting | `str_replace` L138-141 — no scheme, no query args, no `admin_url` | Parse + `add_query_arg`, honour `is_ssl()`, filter `admin_url`/`network_admin_url` |
| Logged-in | Hard 404 for everyone (L100-102) — breaks interim login and `action=postpass` | Served, as WPS does (FR-006) |
| Multisite | `network_site_url` only | + `site_option_welcome_email`, `wp-signup.php`/`wp-activate.php` |

Delete the dead `normalizedPath()` no-op ternary (L203) and the superseded `login_init`/`maybeBlockDefaultEndpoint` path.

### WS1b — Close the lockout paths (P1)

Both traps were **reproduced on `corex.local`** before implementation; the evidence is recorded in `DECISIONS.md`.

- **Trap 1 — total lockout. CONFIRMED as predicted.** `LoginProtectionSettingsStore::current()` L52 sanitises without the `?: 'corex-login'` fallback `save()` L30 has. Stored slug `!!!` ⇒ `sanitize_title()` returns `''` ⇒ the empty string skips the value object's `!== ''` guard (so it does *not* throw) ⇒ `register()` bails at L27, so no custom slug is served, **while** the unconditional `login_init` registration at L25 still 404s `wp-login.php` via `decision()`. Measured: `/wp-login.php` → 404, `/corex-login/` → 404. **No login URL exists at all.** The front page still returns 200, so the site looks healthy while nobody can ever log in. Share one sanitiser between both paths (FR-010).

- **Trap 2 — silent fail-open. WORSE than the predicted fatal.** `LoginProtectionSettings` L39 enforces `/^[a-z0-9][a-z0-9-]{2,80}$/` and throws; the container constructs it on every `make()` (`ConfigServiceProvider` L304-308) and `boot()` L651 resolves it unguarded. But provider boot is wrapped in a try/catch at `ProviderRepository.php:91` that logs and continues — so a 1–2 char slug does **not** fatal. Instead:
  - The **entire `ConfigServiceProvider` fails to boot** — every screen and REST route it registers disappears (measured: `corex/v1/insights/widgets` and `corex/v1/data/sources` → 404).
  - **Login protection fails open**: `/wp-login.php` → **200** while the stored setting still reads `enabled: true`. The owner is told they are protected and is not.
  - There is **no visible symptom**. Front page 200; the only trace is one `debug.log` line, and `wp-config.php` redefines `WP_DEBUG` to `false`, so production is silent.

  This reframes the requirement: FR-011's "must not prevent the site from loading" is already satisfied by accident. The real defect is that an invalid stored value **silently disables a security control the owner switched on, and a whole plugin with it**. The fix must (a) make the binding fault-tolerant so one bad value cannot take down the provider, and (b) ensure a protection failure is *loud* — never a silent downgrade to unprotected.
- Add a real `args` schema to `SecuritySettingsController::register()` (L31-47 has none) and reject empty/short/reserved/colliding slugs in `save()` (L59-88) with a reason (FR-009, Principle VII).
- Fix default drift in `securityCenterState.js`: `enabled` true (L41) vs PHP false; `windowSeconds` 900 (L45) vs PHP 300 (FR-014).
- `SecurityResetLoginCommand::restore()` L29-42 does not reset the slug — it should, or recovery can leave a broken slug in place (FR-013).

### WS1c — Make the policy legible (P1)

`SecurityCenter.js` `LoginPolicy()` L105-180: show the resulting login URL prominently after save; warn before the default is hidden (FR-012). WPS does both; Corex does neither.

### WS2 — Delete the inert controls (P1)

- `OperationsSecurityScreen.php` L287 hardcodes `'lockouts' => []` — wire to the real store (FR-015).
- `SecurityCenter.js` `Recovery()` L210-232 — the button only flips a label. Make it real or remove it; no inert control survives (FR-016).

### WS3 — One records destination (P2)

Remove `DataAdminScreen`'s submenu; redirect `page=corex-data` → `page=corex-data-models&tab=records`; add `?tab=` sync to `DataModelsApp.js` (tabs are `useState` only, L24 — the redirect depends on this); apply the permission resolution above. Keep `DataExplorer` and `data.css` — Data Models already loads both.

### WS4 — Models accordion (P3)

`ModelsPanel.js` L31: header becomes the trigger; fields table + capability chips collapse. First expanded. Keyboard-operable, state announced, WCAG 2.2 AA (FR-024).

### WS5 — Filter by form name (P2)

Reuse `FlowService::all()` (`plugins/corex-forms/src/Flow/FlowService.php` L60-64) → `id`/`name`/`slug`, resolved lazily in `try/catch` per Principle IX.

**The two screens key differently and must not be conflated (FR-022):**

| Screen | Posts | Server filters on |
|---|---|---|
| Submissions | flow **id** | meta `corex_flow_id` NUMERIC (`WpSubmissionsReader.php` L402-404) |
| Records | form **slug** | meta `corex_form_slug` `=` (`WpSubmissionsReader.php` L343-346) |

Submissions: replace the numeric `Flow ID` input (`index.js` L79) with a name select; keep free-text search (L78). Server path unchanged — `SubmissionInboxQuery.php` L58 already coerces to int.
Records: `QueryBar.js` renders the first two `equals`-capable fields as free text (L11, L19-24); give `form` a select, keep free text alongside.
Prefer the existing `.corex-select` widget (`corex-admin-shell.css` L485-561) — it already solves the dark-mode option problem.

### WS6 — The dark-mode select bug (P3)

`cab984d` added `select option { color/background }` (L445-448) and `select:hover { border-color }` (L451-453). **Neither is the reported bug.** No `select option:hover` or `option:checked` rule exists anywhere; the popup highlight is still whatever the OS paints over `--corex-admin-surface`.

The fix already exists for the enhanced widget — `corex-admin-shell.css` L552-555 documents this exact failure ("surface-alt is darker than the list's surface-raised in dark mode, so the hovered/active option was almost invisible") and solves it with `is-active` → `--corex-admin-action-subtle`, `is-selected` → `--corex-admin-action`. Apply the same tokens to the native path; delete the redundant `.corex-data__form option` override (`data.css` L287-290).

**Native option popups are OS-rendered and only partly styleable.** Verify in a real browser (FR-026). If the highlight will not take, migrate the remaining native selects to `.corex-select` rather than ship a rule that silently does nothing.

### WS7 — The missing spacing base layer (P3)

Tokens are fine (`--corex-admin-space-{2xs…2xl}`, tokens L83-89). The base layer is absent:

- `corex-admin-shell.css` L258-266 styles `h1`–`h6` for **colour and font only — no margins**, so rhythm falls through to wp-admin and each sheet re-neutralises it (`control-panel.css` L14-20, `insights.css` L43-48 & L133-136, shell L588-590, L682-684, L699-700).
- **No `.corex-admin p` rule exists at all.**
- The muted-description pattern is spelled four times with three different top margins: `.corex-admin__description` `sm` (shell L247-252), `.corex-field-help` `2xs` (control-panel L243-247), `.corex-insight-widget__sub` `2xs` (insights L138-143), `.corex-addon-card__desc` (addons L150).
- Identical label→value→detail stacks disagree: stat-card `xs` (control-panel L37-59) vs overview tile `2xs` (L501-522).

Add heading rhythm, a paragraph rule, and one shared description component to the shell; remove the per-sheet resets and duplicate spellings; reconcile the stacks (FR-025). Touches all 11 sheets — budget a full visual pass.

### WS8 — Insights coherence (P3)

Two grids that should be one: `.corex-insights` `minmax(min(100%, 24rem), 1fr)` + `gap: lg` (L3-7) vs `.corex-insights__widgets` `minmax(18rem, 1fr)` + `gap: md` (L108-113). Unify onto shared tokens. Reorder by state urgency rather than registration order (`InsightWidgets::widgets()` L53-64) — critical first, `disconnected`/`empty` last (FR-027). Tighten internals against the WS7 base. Fix the hardcoded `'1.1.0'` version (`InsightsScreen.php` L75) to `filemtime` — otherwise this very redesign ships stale (FR-029). Keep vanilla JS.

## Out of scope

`data.scss`, `email-studio.scss`, `submissions-admin.scss`, `forms-admin.scss`, `data-models.scss` are byte-identical to their `.css` twins and referenced by nothing — 2,109 lines that will drift the moment WS7 edits the real sheets. Flagged for a separate owner decision; not removed here.

## Complexity Tracking

No constitutional violations to justify. The plan **removes** three standing violations rather than adding any: a container binding that can fatal (IV), REST routes with no declarative validation (VII), and a hardcoded asset version (VI).
