# Feature Specification: Admin Product Completion — Required Scope

**Feature Branch**: `spec/065-admin-product-completion`

**Created**: 2026-07-02

**Status**: Draft

**Input**: Owner correction after v0.32.1. Spec 063 shipped truthful read-only surfaces; Spec 064 corrected the
Overview grid + rail but did not finish the product. Spec 065 is the **required product-completion milestone**: the
CoreX admin/dashboard must be finished — real data/state or an honest empty/error/unavailable state on every
surface. **Company-site recommendations are paused.** Only **WooCommerce**, **advanced AAM / full capability-editor**,
and **commercial/Pro/marketplace/licensing** may remain deferred. **Blog is required.** Portfolio is lower priority
than Blog but stays planned. No safe feature may remain a vague "future" placeholder.

## Milestone framing (must be recorded in docs)

- **Spec 063** — truthful-surface milestone (read-only/overview surfaces). *Not product-complete.*
- **Spec 064** — Overview fidelity + rail fix. *Fixed only part of the design/fidelity issue.*
- **Spec 065** — the required completion pass (this spec).
- Company-site recommendations are **paused** until the owner accepts the admin/dashboard/product experience. A
  stable company-site base remains available at **v0.31.0** separately.

## User Scenarios & Testing *(mandatory)*

### US1 — Global admin visual fidelity (P1)
Every CoreX admin page (Overview, Forms & Flows, Submissions, Data Models, Operations & Security, Email Studio,
Settings, Insights, Setup Wizard, Blog admin) feels like one product: no top/bottom white space, consistent card
density, section spacing, content width, header/action rows, table/list density, rail spacing, and dark/light/RTL
parity. **Independent test:** render each screen dark+light and confirm no unfinished blank body, no
placeholder-looking screen, no raw-WP tables, no unstyled links, consistent density.

### US2 — Consistent interactions & micro-states (P1)
Consistent link/nav/card/row/button hover, `:focus-visible`, active, disabled (with a reason), loading, empty,
error, permission-denied, and reduced-motion-safe transitions. Every clickable row/card looks clickable; every
disabled/future action explains why. No plain blue links in CoreX cards.

### US3 — Real Operations Mode switching (P1)
An operator switches the operating mode (Development / Staging / Production, and Maintenance where safe). The change
**persists**, is nonce + capability protected, requires **confirmation for Production and risky public-behavior
changes**, shows a mode badge on Overview and Operations & Security, records a mode-change audit entry, and surfaces
mode-specific warnings. **Never fakes a mode change, never renames wp-admin, never creates lockout risk.**

### US4 — Data Models completion (P2)
Model list, model detail, per-model record list, record detail, schema summary, capability-aware actions, CSV
(and safe JSON) export, a CSV **import with a real dry-run** (validation preview + rejected-rows report; no write
before confirmation), and a truthful migration overview/registry/history. Destructive execution stays disabled where
unsafe, but the registry/history/overview are truthful — not faked.

### US5 — Submissions / Forms / Email completion (P2)
Submissions Inbox: designed inbox, list density, row hover, detail view, source/status, read state where safe,
CSV export, honest states. Forms & Flows: form list/detail, fields, validation, email/notification + captcha/honeypot
display, submission connection, honest states (builder editing labelled with the exact backend needed). Email Studio:
template list/detail/preview, layout preview, delivery/provider status, environment warning, test-send where safe,
logs where available, honest states (editing disabled with the exact reason if unsafe).

### US6 — Settings / retention / advanced (P2)
Media/WebP controls, provider-specific captcha, **real retention settings with real behavior** (safe scheduled or
manual prune with a **dry-run/preview before any deletion**), advanced settings with clear risk labels — all
capability + nonce protected. No retention setting that does nothing; no deletion without preview/confirmation.

### US7 — Insights / Setup Wizard (P2)
Insights: real provider/readiness/security signals with honest not-connected/empty states, no fake charts. Setup
Wizard: first-run/start, readiness checklist, blockers, completed/incomplete, links to mode/security/settings,
honest unavailable states, no dead steps.

### US8 — Blog (required) + Portfolio (planned) + Access baseline (P3)
Blog: archive + single design, card/list components, categories/tags, social sharing, newsletter connection, empty +
no-results states, author/date/category metadata, related posts where safe, Blog Pro basics + analytics/readiness
where real data exists (no fake analytics). Portfolio: kept in the roadmap with an exact next scope, implemented
after Blog if safe. Access & Abilities: a **safe baseline** — role/capability visibility matrix, current-user
permissions, screen access requirements, protected-action explanations, permission-denied states. Advanced AAM /
full capability mutation editor may wait.

### Edge Cases
- A capability with no backend shows an honest disabled state **with the reason**, never a vague future card.
- Operations Mode must never lock out the owner; logged-in admins always retain access; recovery is documented.
- Import never writes without a real dry-run + confirmation; retention never deletes without preview + confirmation.

## Requirements *(mandatory)*

- **FR-001**: Every admin surface shows real data/state or an honest empty/error/unavailable/permission-denied state.
  No fabricated records/counts/integrations/scans/activity/charts/analytics.
- **FR-002**: Global fidelity — fix top/bottom white space, page-height gaps, card padding, grid density, section
  spacing, content width, header/action rows, table/list density, rail spacing across **all** new admin pages, with
  dark/light/RTL parity and 200%-zoom safety.
- **FR-003**: Consistent hover/focus-visible/active/disabled/loading/empty/error/permission states, reduced-motion
  safe; clickable rows/cards look clickable; disabled/future actions explain why; no plain blue links in cards.
- **FR-004**: Operations Mode is a real persistent setting (development/staging/production; maintenance where safe),
  nonce + capability gated, with production/risky-change confirmation, Overview + Operations badges, a mode-change
  audit entry, and mode-specific warnings. No fake switch, no wp-admin rename, no lockout.
- **FR-005**: Data Models — model/record list + detail, schema summary, capability-aware actions, CSV/JSON export
  (nonce+cap), CSV import with a real dry-run (validation + rejected rows; no write before confirmation), and a
  truthful migration overview/registry/history (destructive execution disabled where unsafe).
- **FR-006**: Submissions Inbox and Forms & Flows are product-complete read/detail surfaces with designed states;
  Email Studio has list/detail/preview + delivery status + environment warning + safe test-send/logs. Editing that
  needs a builder backend is disabled with the exact backend requirement stated (not a vague future).
- **FR-007**: Settings — media/WebP + provider captcha + **real retention** (safe prune with dry-run/preview) +
  advanced with risk labels, all cap+nonce gated. No do-nothing retention; no deletion without preview.
- **FR-008**: Insights + Setup Wizard are useful truthful surfaces (no fake charts/analytics; no dead steps).
- **FR-009**: Blog is implemented (archive/single/components/social-share/states/metadata + Blog Pro basics +
  analytics/readiness where real). Portfolio stays planned with an exact next scope. Access & Abilities safe baseline
  (matrix + current-user perms + protected-action explanations + denied states).
- **FR-010**: All styling uses scoped `--corex-admin-*` tokens (admin) / `theme.json` tokens (theme) + logical CSS;
  every dangerous mutation is capability + nonce protected with confirmation; secrets stay write-only.
- **FR-011**: Docs corrected — Spec 063 not product-complete; 064 partial; 065 is the completion pass; only Woo/
  advanced-AAM/commercial-Pro deferred; Blog required; Portfolio lower-priority-but-planned; **all company-site
  next-step recommendations removed/corrected**.

## Success Criteria *(mandatory)*

- **SC-001**: No new admin page has unintended white space / blank body / placeholder look (render-verified).
- **SC-002**: Operations Mode can be changed and persists, gated by cap+nonce, with production confirmation and an
  audit entry (tested); a fake or unprotected change is impossible.
- **SC-003**: Data Models import performs a real dry-run and writes nothing before confirmation; export is cap+nonce
  gated (tested).
- **SC-004**: Retention prunes only after a preview/confirmation and only what the preview showed (tested); a
  retention setting always has real behavior.
- **SC-005**: Blog archive/single/social-share render with real posts or honest empty/no-results states (tested).
- **SC-006**: 100% of surfaces are truthful (real or honest state); every deferred item is limited to Woo / advanced
  AAM / commercial-Pro, or a specifically-blocked unsafe subfeature with a stated reason.
- **SC-007**: Docs contain no company-site next-step recommendation.

## Assumptions

- Reuse the existing real providers/foundations (settings store/registry, AddonManager, DataRegistry, SubmissionsReader,
  FormRegistry, TemplateRegistry, InsightRegistry, HardeningChecks/Facts, provisioning, the FSE theme, social-share +
  newsletter blocks, the M6 admin shell). Extend via the PSR-11 container.
- Delivered in safe, independently shippable batches (one per phase group), each spec-first, guard-gated, tested,
  render-verified where the browser harness is available (`tests/e2e/render-admin.mjs`), and documented.
- CoreX Framework Mode; no `sites/<client>/` edits; no generated/dist edits as source.
- Deferred by owner: WooCommerce kit/screens; advanced AAM / full capability-editor / complex role mutation;
  commercial/Pro/marketplace/licensing. A specifically unsafe subfeature may be disabled with a stated reason + safe
  foundation, never a vague future card.
