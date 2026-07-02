# Admin Dashboard Fidelity Audit (Spec 064)

**Date:** 2026-07-02 · **Mode:** CoreX Framework Mode · **Baseline:** v0.32.0 (Spec 063 shipped)

## Design source of truth (this audit)

The **truthful** admin direction wins over any older/fake dashboard concept:

- **Primary:** `Corex Admin Overview.dc.html` (the truthful Overview), `Corex Design Audit & Handoff.dc.html`,
  `Corex Final Design Gap-Closure Package.dc.html`, `Corex Design Closure & Freeze Pack.dc.html`,
  `Corex Final Design Inventory.dc.html`, `Corex Admin - Add-ons & Data.dc.html`,
  `Corex Admin Login & Settings.dc.html`, plus the per-area screens (Operations Mode & Security, Access &
  Abilities, Data Models, Email Studio, Forms & Flows Foundation, Submissions Inbox, Insights, Setup Wizard,
  Settings — Media/Retention/Advanced).
- **Superseded / do not apply:** `Corex Admin Dashboard.dc.html` contains **older, fabricated** dashboard
  concepts (live event bus, fake repository stats, "everything healthy" cards). Where it conflicts with
  `Corex Admin Overview.dc.html`, the **truthful Overview wins**. Any Pro/commercial/license/marketplace/
  ThemeForest language in any file is **not** implementation scope.

## What the approved Admin Overview actually is

A **dense two-column status grid** (not a stack of disparate full-width panels):

1. **Environment badge** in the page header (e.g. `DEVELOPMENT` pill with a status dot) — real
   `wp_get_environment_type()`.
2. **Launch readiness** card — a checklist with an `N of M` badge; each row is icon + label + note + a truthful
   state chip (Done / Pending / Not configured / Planned).
3. **Data sources** card — the real registered `DataRegistry` sources with row counts, an "Explorer →" link.
4. **Analytics & security** integrations card — PageSpeed/Insights provider, Cloudflare, Login protection,
   Captcha, Corex Mail — each an honest **connected / not connected / not configured** chip (never a fake score).
5. **Forms & Flows** summary card — labelled `COMING SOON` where the builder is future.
6. **Recent activity** card — an honest `EMPTY` dashed empty state (no fabricated event bus).

## Screen-by-screen audit

| Screen | Design source | Current file(s) | Status | Issue | Fix needed | Priority | Safe now |
|---|---|---|---|---|---|---|---|
| **Overview / Dashboard** | `Corex Admin Overview.dc.html` | `Config/Settings/AdminDashboard::render`, `Config/Overview/OverviewRenderer`, `Config/Dashboard/SiteStatusCardRenderer`, `Config/ControlPanel/ControlPanelView`, `assets/control-panel.css` | **partial / wrong layout** | Renders 4 **stacked full-width** sections (summary strip → site-status → control-panel domains → activity). Sparse, white-space-heavy, no 2-column grid; content overlaps (submissions shown 2–3×). Does **not** match the dense readiness-grid design. Content is truthful but the *layout* and *density* are wrong. | Restructure into the approved 2-column readiness grid: Launch readiness checklist (N of M), Data sources, Analytics & security integrations, Forms & Flows summary, Recent activity empty. Remove duplicate submission read-outs. Fix top/bottom gaps, card alignment, shell width. Keep every value truthful. | **P0** | yes (reuses existing truthful providers) |
| **Admin shell** | admin captures | `Core/Admin/AdminPage::open/close/rail`, `assets/control-panel.css` shell rules | **partial** | Shell + rail exist and are correct in principle; content max-width/padding contribute to the Overview white space; header/title area spacing to reconcile with the grid. | Tune `__content` gap/padding; ensure the grid fills content width; no dead canvas on wide screens. | P1 | yes |
| **Rail / sidebar nav** | Overview capture rail | `Core/Admin/AdminPage::railItems` | **wrong** | Rail iterates `$submenu['corex-settings']`, but `$meta` only maps the original 6 slugs → the 5 new screens (`corex-forms`, `corex-submissions`, `corex-data-models`, `corex-operations-security`, `corex-email-studio`) fall through to the generic `option-page` icon and **never show an active state**. Design also wants future items (e.g. Forms & Flows) badged `SOON` where the builder is future. | Map the new slugs to real nav icons + correct active section; keep logical order; no dead entry points; truthful future badges where applicable. | **P0** | yes |
| **Add-ons** | `Corex Admin - Add-ons & Data.dc.html` | `Config/Addons/AddonsScreen`, `assets/addons.css` | **match** (Spec 060) | Truthful 7-state badges, install-only, summary bar; already render-verified in M6. | Spacing parity with the shared shell only. | P2 | yes |
| **Data explorer** | `Corex Admin - Add-ons & Data.dc.html` | `Config/Data/DataAdminScreen` + React `admin/index.js` | **match** (Spec 060) | Rail-driven explorer, real schema, bulk delete, drawer — render-verified in M6. | None (spacing parity). | P2 | yes |
| **Settings** | `Corex Settings - Media, Retention & Advanced.dc.html` | `Config/Settings/AdminDashboard::renderSettings`, `SettingsForm` | **partial** | Provider-specific captcha + write-only secrets correct (M6). Retention/Advanced sections from the design are honestly absent (no backend). | Confirm section taxonomy + honest "coming later" where the design expects visibility; spacing parity. | P2 | yes (display only) |
| **Forms & Flows** | `Corex Forms & Flows Foundation.dc.html` | `Config/Forms/FormsFlowsScreen`, `assets/forms-admin.css` | **match / read-only** (Spec 063) | Real read-only form inventory; builder labelled future. | Spacing parity; confirm empty/permission states designed. | P1 | yes |
| **Submissions Inbox** | `Corex Submissions Inbox.dc.html` | `Config/Submissions/SubmissionsInboxScreen`, `assets/submissions-admin.css` | **match / read surface** (Spec 063) | Real records, detail, cap+nonce export, honest empty/not-found. | Spacing parity; verify empty/error states designed (not raw text). | P1 | yes |
| **Email Studio** | `Corex Email Studio.dc.html` | `Config/Email/EmailStudioScreen`, `assets/email-studio.css` | **match / overview** (Spec 063) | Add-on-gated real template list + delivery advisory; editor future. | Spacing parity. | P1 | yes |
| **Data Models** | `Corex Data Models.dc.html` | `Config/DataModels/DataModelsScreen`, `assets/data-models.css` | **match / catalog** (Spec 063) | Real schema catalog + per-model export; import/migrations honestly deferred. | Spacing parity. | P1 | yes |
| **Operations & Security** | `Corex Operations Mode & Security.dc.html` | `Config/Security/OperationsSecurityScreen`, `assets/operations-security.css` | **match / read-only** (Spec 063) | Real env + hardening checks; mode-switch/login-guard/AAM honestly deferred. | Spacing parity. | P1 | yes |
| **Insights** | `Corex Insights.dc.html` | `Config/Insights/InsightsScreen` + React | **impl (pre-063)** | Readiness/PSI providers; truthful states. | Confirm no fake scores; spacing parity. | P2 | yes |
| **Setup Wizard** | `Corex Setup Wizard.dc.html` | `Config` setup (gated behind `corex-kit-company`) | **impl / gated** | Present when the company kit is active. | Confirm rail label + gating truthful. | P2 | yes |
| **Login** | `Corex Admin Login & Settings.dc.html` | `wp-login` renderer (M6) | **match** (Spec 060) | Dark-first branded login, honest disabled SSO. | None. | P3 | yes |
| **Access & Abilities** | `Corex Access & Abilities.dc.html` | — | **deferred** | AAM-lite capability editor has no backend. | Keep deferred; not a rail entry until built. | — | no (needs spec + backend) |
| Empty/loading/error/permission states | states captures | `Core/Admin/AdminPage::state`, `permissionDenied` | **match** | Shared designed state components exist. | Ensure every screen uses them (not raw text). | P1 | yes |
| Dark / light / RTL / focus | states captures | scoped `--corex-admin-*` + logical CSS | **match (env-gated visual)** | Token-driven, logical properties, focus rings present. | Re-verify after the Overview restructure. | P1 | env-gated |

## Fix plan (this pass)

- **P0 — Overview layout + truthfulness:** restructure into the approved 2-column readiness grid; remove duplicate
  submission read-outs and any stale "everything healthy" framing; keep every value truthful (real or honest
  empty/gated). Fix white space, density, card alignment, shell/content width, top/bottom gaps.
- **P0 — Rail nav:** map the 5 new screens to real icons + correct active states; truthful ordering; no dead
  entry points.
- **P1 — Shared spacing + state parity** across the six Spec 063 screens and the shell.
- **Deferred (unchanged, honest):** operations-mode switching, login-URL/rate-limit guard, capability editor /
  advanced AAM, data import, migrations UI/history, retention pruner, Blog Pro analytics, Portfolio, WooCommerce,
  Pro/commercial, Auth/portal, marketplace/license. **Not built** without a separate reviewed spec + real backend.

## Still Left After This Pass — classification

**A. Must fix now (this spec):** Overview grid + white space; rail active/icons for new screens; shared spacing +
state parity.

**B. Safe now only if backend supports it:** truthful readiness checklist rows (from real signals), real data-source
counts, real add-on/integration status chips, better designed empty states.

**C. Deferred (needs separate reviewed spec + real backend):** operations-mode switching, hidden/custom login URL +
rate limiting, full capability editor / advanced AAM, data import, generic migrations UI + history, retention
pruner, Blog Pro analytics, Portfolio, WooCommerce kit, Pro/commercial layer, Auth/profile/portal, marketplace/
license flows.

## Visual verification status

Rendered browser evidence (dark/light/RTL/200%/keyboard) is **environment-gated** and recorded honestly — it is
captured with the repo's `tests/e2e/render-admin.mjs` harness only when a live WP + browser runtime is available.
Headless PHP/JS contracts (below) verify structure, truthful state, escaping, and token usage.
