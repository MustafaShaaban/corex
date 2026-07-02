---
description: "Task list for New Design Gap Implementation (Spec 063)"
---

# Tasks: New Design Gap Implementation

**Input**: `specs/063-new-design-gap-implementation/` (spec.md, plan.md)

**Tests**: REQUIRED (Corex constitution DoD — Pest/Jest/Playwright, Guard Gate, i18n, RTL, WCAG 2.2 AA).

**Organization**: Tasks are grouped by batch (Phase 0–8). Each batch is independently shippable; ship one
reviewed batch at a time. Owner-gated batches (P2–P4) STOP for sign-off before implementation code.

## Format: `[ID] [P?] [Batch] Description`

- **[P]**: can run in parallel (different files, no dependency)
- Owner-gated batches are marked **⛔ OWNER SIGN-OFF** and must not start until the recorded decision lands.

---

## Phase 0 — Intake, truthfulness, and gates (cross-cutting; unblocks all)

- [x] T001 Design intake handoff `design/handoffs/063-new-design-gap-implementation.md` (path, files, bands, scope).
- [x] T002 Update `design/INVENTORY.md` to the new package's seven-state model.
- [x] T003 Spec Kit artifacts: `spec.md`, `plan.md`, `tasks.md`, `checklists/requirements.md`.
- [ ] T004 ROADMAP: add the Spec 063 row + record the truthful design-gap program; correct any overstated wording.
- [ ] T005 Neutralize active marketplace/Pro/ThemeForest/license/purchase wording across repo copy; mark future-only.
- [ ] T006 Record DECISIONS #110 (design-gap program, batches, truthfulness invariant, owner-gates).
- [ ] T007 Update `PROGRESS.md` RESUME HERE to the Spec 063 state and exact next step.

**Checkpoint**: intake + spec + gates recorded; no fake/marketplace/Pro language remains active.

---

## Phase 1 — Admin Overview + global state language (US1) 🎯 MVP

**Goal**: a truthful Overview that summarizes real operational state and gates honestly across all states.

### Tests (write first, RED)

- [ ] T010 [P] [P1] Pest: `tests/Unit/Overview/OverviewSummaryTest.php` — pure summary model returns truthful
  states for env/mode, security, add-on health, forms/submissions, email, captcha, media, data, insights,
  wizard progress; honest empty where data absent.
- [ ] T011 [P] [P1] Pest: environment/mode resolver returns the real mode (dev/staging/production/…);
  never claims a mode that is not set.

### Implementation

- [ ] T012 [P1] `plugins/corex-config/src/Overview/OverviewSummary.php` — pure view model composing real
  signals (reuses `ControlPanelStatus`, `AddonStatusResolver`, `SubmissionsReader`, media/insights readers).
- [ ] T013 [P1] `plugins/corex-config/src/Overview/EnvironmentMode.php` — resolves `wp_get_environment_type()`
  + any Corex operations-mode option into a truthful badge model (no fabricated modes).
- [ ] T014 [P1] `plugins/corex-config/src/Overview/OverviewRenderer.php` — escapes/lays out the summary
  sections (env/mode badge, launch readiness, security status, add-on health, forms/submissions/email/
  captcha/media/data/insights summaries, wizard progress, docs/help links); honest empty/loading/error/
  permission states; icon+text (never color alone).
- [ ] T015 [P1] Wire into `AdminDashboard::render()`; register services in `ConfigServiceProvider`.
- [ ] T016 [P1] Scoped CSS in `plugins/corex-config/assets/` for the new summary sections (tokens only, RTL,
  dark+light, reduced motion); enqueue conditionally on the Overview hook.

### Verify

- [ ] T017 [P1] Pest green; render harness dark+light for the Overview across states; guards clean; token
  inventory synced.

**Checkpoint**: Overview is truthful across fresh/dev/staging/production/maintenance/coming-soon/loading/
empty/error/permission-denied; no fabricated metric.

---

## Phase 2 — Forms & Flows + Submissions Inbox + Email Studio (US2) ⛔ OWNER SIGN-OFF

**Gate**: approve the form-vs-flow model + extension points, retention/anonymize behavior, and the
"Email Templates → Email Studio" upgrade + safe layout-builder boundary (design intake §10).

- [ ] T020 [P2] Flow model + schema (field types, validation, routing, email routing) — service + repository.
- [ ] T021 [P2] Forms & Flows admin: flow list/editor/field-schema UI/validation display/routing panels/test
  mode/preview + empty/loading/error/permission states.
- [ ] T022 [P2] Submissions Inbox: list/filters/search, detail drawer, status/read/archive/spam where
  supported, capability-gated export, honest empty state (real data only).
- [ ] T023 [P2] Email Studio: template list/editor, layout/header/footer controls, token/variable browser,
  preview, test send/log, delivery-mode awareness, dev/test suppression, escaped variable output.
- [ ] T024 [P2] Tests: schema validation, routing, permissions, email suppression/logging, UI states.

---

## Phase 3 — Data Models, CRUD, import/export, migrations (US3) ⛔ OWNER SIGN-OFF

**Gate**: approve the safe Data-model-manager scope and CSV-first / XLSX-future import-export.

- [ ] T030 [P3] Data Models admin: model list, record table, create/edit/view/delete where supported;
  honest disabled/read-only where not; schema/fields panel; capability gates.
- [ ] T031 [P3] CSV import: column mapping, dry-run validation report (invalid/duplicate/skipped), apply only
  after confirmation; personal-data warning.
- [ ] T032 [P3] Export (capability-gated selected/filtered/all where supported); export logging if real.
- [ ] T033 [P3] Migrations: pending list, dry-run/plan, production warning, rollback messaging only if
  technically supported (no fake rollback).
- [ ] T034 [P3] Tests: capabilities, dry-run, validation, safe mutation, logs.

---

## Phase 4 — Operations Mode + Security Center + Access & Abilities (US3) ⛔ OWNER SIGN-OFF

**Gate**: approve the Operations Mode 8-mode model + real behavior changes; Security Center scope beyond
"hide wp-admin" + reversible recovery contract; Access & Abilities CoreX-native (not full AAM) scope.

- [ ] T040 [P4] Operations Mode: environment/mode display, safe mode selector (only if real), per-mode
  guardrails, production launch confirmation, maintenance/coming-soon behavior; never false "mode changed".
- [ ] T041 [P4] Security Center: login protection, custom login URL/path (safe), failed-login/rate-limit
  status if implemented, captcha status, recovery instructions, read-only hardening checks, audit empty
  states; never rename/move WP core; reversible config/CLI recovery if a login guard ships.
- [ ] T042 [P4] Access & Abilities (AAM-lite): CoreX capability groups, role matrix for `corex_*`, admin
  lockout protection, third-party permission-plugin detection/conflict avoidance, audit log, denied screen.
- [ ] T043 [P4] Tests: security, capability, lockout-prevention, confirmation-gated mutations.

---

## Phase 5 — Settings, media, retention, advanced (FR-050..051)

- [ ] T050 [P5] Apply the new settings design/taxonomy; complete Media/WebP settings UX.
- [ ] T051 [P5] Data retention settings (if supported); advanced/developer settings with warnings + safe
  disabled/locked-by-config states.
- [ ] T052 [P5] Provider-specific captcha (None/Honeypot/reCAPTCHA/hCaptcha/Turnstile) — only the selected
  provider's fields/links; no mixing; secrets write-only.
- [ ] T053 [P5] Tests: settings persistence, provider switching, secret write-only, UI state.

---

## Phase 6 — Insights + Setup Wizard (US4)

- [ ] T060 [P6] Insights: only real checks/readiness widgets; connected/disconnected/not-configured/
  setup-required/planned states; last-checked; no fabricated scores.
- [ ] T061 [P6] Setup Wizard: welcome/brand/dependency steps; skipped/completed/blocked; launch checklist +
  "not safe to go live"; confirmation; resume-later; preview-then-apply.
- [ ] T062 [P6] Tests: gating, progress, dependencies, skipped/completed states, launch readiness.

---

## Phase 7 — Blog, social sharing, Company Site Kit gaps, blocks (US4)

- [ ] T070 [P7] Blog/News (native WP posts): index, single, archive/category, search/no-results, comments
  where enabled, related posts where supported; privacy-friendly social share (accessible labels, RTL, no
  counts). No custom blog engine; Blog Pro stays future-only/reference.
- [ ] T071 [P7] Company Site Kit: fill missing M4 page/template coverage (neutral content); safe
  apply/reset/adopt/skip/conflict behavior.
- [ ] T072 [P7] Prioritized blocks (services grid, service detail, process/steps, icon box, logo cloud/trust
  badges, case study, rich tabs, testimonial, gallery, featured posts, newsletter, contact/map, social
  share) — keyboard, RTL, reduced motion; no global slider JS; no autoplay by default.
- [ ] T073 [P7] Tests: block rendering (Pest), JS (Jest), accessibility-focused checks.

---

## Phase 8 — Docs, verification, PR (SC verification)

- [ ] T080 [P8] Update docs + docs-app nav/content for every implemented area; README status only if needed.
- [ ] T081 [P8] Update `PROGRESS.md`; log decisions in `DECISIONS.md`; add/refresh visual-evidence + screenshots.
- [ ] T082 [P8] Ensure all future-only items are documented as future-only, not active UI.
- [ ] T083 [P8] Verification suite: `composer validate`, `composer test`, `npm run lint:js`, `npm run lint:css`,
  `npm run test:js`, `npm run build`, `npm run verify:dependencies`, `npm run build:dist`, `npm run verify:dist`,
  `git diff --check`, the render-admin harness, and all required guards.
- [ ] T084 [P8] Push branch; open/update PR; final handoff (SUMMARY/WORKSPACE/MODE/SPEC KIT/VERIFICATION/
  GUARDS/FILES CHANGED/BLOCKERS/RECOMMENDED NEXT STEP/NEXT STEP).

---

## Dependencies & Execution Order

- **Phase 0** first (no code) → unblocks everything.
- **Phase 1** (MVP) after Phase 0 — reuses merged Dashboard/ControlPanel; no owner gate.
- **Phases 2–4** each require the recorded owner sign-off (design intake §10) before implementation code.
- **Phases 5–7** after their prerequisite admin foundations; independently shippable.
- **Phase 8** after each batch (docs/verify per batch) and as the final close.

### Truthfulness gate (all phases)

No batch ships a fabricated metric, fake integration, dead entry point, or Pro/marketplace/licensing
behavior. A batch that cannot be built truthfully this cycle is hidden/gated and deferred (recorded in
PROGRESS/DECISIONS), never stubbed as working.

## Notes

- One reviewed batch at a time (constitution §16 / Pre-Implementation Confirmation Rule).
- Each implementation task owes its test task(s), guard run, i18n/RTL/WCAG check, and docs update (DoD).
- Commit after each batch or logical group; keep the PR branch the working source of truth.
