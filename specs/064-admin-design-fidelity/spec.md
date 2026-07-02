# Feature Specification: Admin Design Fidelity & Dashboard Completion

**Feature Branch**: `spec/064-admin-design-fidelity`

**Created**: 2026-07-02

**Status**: Draft

**Input**: The owner reviewed the v0.32.0 admin and found the Overview/Dashboard incomplete, visually unfaithful to
the approved design, confusing, and full of unintended white space. Audit the shipped admin (Spec 063 baseline)
against the approved design files and fix what is actually wrong, missing, or visually inconsistent — without
rebuilding Spec 063 and without adding any fake feature. Audit recorded at
`design/audits/064-admin-dashboard-fidelity-audit.md`.

## Overview

The Spec 063 admin surfaces are truthful but the **Overview layout** does not match the approved
`Corex Admin Overview.dc.html` (a dense two-column readiness grid); it renders as sparse stacked panels with
duplicated read-outs, and the **rail** does not give the new screens icons or active states. This feature makes the
admin **visually faithful, truthful, and non-confusing** — same hard invariant as Spec 063: every surface shows
real data, a real config state, an honest empty/unavailable/gated state, or an explicitly deferred future
capability. **No fake records, counts, integrations, scans, activity, security, mode-switching, migrations, or Pro
features.**

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A faithful, truthful Overview (Priority: P1)

An operator opens CoreX → Overview and sees a coherent, dense readiness dashboard matching the approved design: an
environment badge in the header, a Launch-readiness checklist (N of M) built from real signals, real data sources
with counts, an Analytics & Security integrations panel with honest connected/not-connected chips, a Forms & Flows
summary, and an honest Recent-activity empty state — with no unintended white space and no duplicated read-outs.

**Independent Test**: Load the Overview; confirm the two-column grid, that every card shows a real value or an
honest empty/gated state, that no submission read-out is duplicated, and that no fabricated metric/activity/health
card appears.

**Acceptance Scenarios**:
1. **Given** a fresh dev install, **When** Overview renders, **Then** the environment badge reads the real
   environment, the readiness checklist shows real Done/Pending states, and Recent activity shows an honest empty
   state (no fabricated event bus).
2. **Given** no analytics provider is connected, **When** the Analytics & Security card renders, **Then** it shows
   "Not connected" — never a fabricated score.
3. **Given** any card whose data source does not exist, **When** it renders, **Then** it shows an honest
   empty/unavailable/deferred state, never fake data.

### User Story 2 - Trustworthy navigation (Priority: P2)

An operator uses the CoreX rail and every entry leads to a real screen with a correct icon and a correct active
state; future-only capabilities are absent or clearly badged, never dead links implying completeness.

**Independent Test**: Visit each rail entry; confirm the active item is highlighted with a real icon and the target
screen exists; confirm no entry implies a feature that is only informational.

**Acceptance Scenarios**:
1. **Given** the operator is on Submissions, **When** the rail renders, **Then** the Submissions entry is active
   with a real icon (not the generic option-page fallback).
2. **Given** a future-only capability, **When** it appears in the rail, **Then** it carries a truthful future badge
   or is absent — never a working-looking dead link.

### User Story 3 - Consistent, designed states across screens (Priority: P3)

Every CoreX admin screen shares consistent spacing and uses the designed empty/error/permission state components
(not raw text), with dark/light/RTL parity.

**Independent Test**: Visit each screen's empty and permission-denied paths; confirm designed state components and
consistent spacing.

### Edge Cases
- The stale `Corex Admin Dashboard.dc.html` concepts (event bus, repo stats, "healthy" cards) must not be applied —
  the truthful Overview wins.
- A capability the design expects visible but which has no backend shows an honest "Coming later / Not configured /
  Unavailable / Requires add-on" state, not a silent hide and not a fake.

## Requirements *(mandatory)*

- **FR-001**: The Overview MUST match the approved `Corex Admin Overview.dc.html` two-column readiness-grid layout
  (launch readiness, data sources, analytics & security, forms summary, recent activity) with an environment badge
  in the header.
- **FR-002**: Every Overview value MUST be real or an honest empty/unavailable/deferred state; no fabricated
  records, counts, integrations, activity, health, or scores. Duplicated read-outs MUST be removed.
- **FR-003**: The Overview MUST NOT apply the stale/fake dashboard concepts from `Corex Admin Dashboard.dc.html`.
- **FR-004**: White space, density, card alignment, shell/content width, and top/bottom gaps MUST match the design;
  no dead canvas on wide screens.
- **FR-005**: The rail MUST give every registered CoreX screen a real icon and a correct active state, in a logical
  order, with no dead entry points; future-only items are absent or truthfully badged.
- **FR-006**: All admin styling MUST use the scoped `--corex-admin-*` tokens and logical CSS (RTL/dark/light);
  status is conveyed by text + tone, never colour alone; focus is visible; motion respects reduced-motion.
- **FR-007**: No new mutation is introduced; export stays capability + nonce gated; secrets stay write-only.
- **FR-008**: Deferred capabilities (operations-mode switching, login guard, capability editor, data import,
  migrations, retention pruner, Blog Pro, Portfolio, Woo, Pro/commercial, Auth) remain deferred and honestly
  labelled; none are faked.

## Success Criteria *(mandatory)*

- **SC-001**: 100% of Overview cards show a real value or an honest empty/gated state; zero fabricated values
  (verified by tests + render evidence).
- **SC-002**: The Overview renders as the approved two-column grid with no duplicated submission read-out (verified
  by tests over the rendered markup).
- **SC-003**: 100% of rail entries resolve to a real screen with a real icon; the active screen is highlighted
  (verified by tests).
- **SC-004**: All new/changed admin UI passes token-only styling and logical-CSS checks (lint + token contract).
- **SC-005**: No fake feature is introduced; all Spec 063 deferrals remain deferred (verified by review + tests).

## Assumptions

- The existing truthful data providers are reused (EnvironmentMode, OverviewSummary/ControlPanelStatus signals,
  SiteStatusCard, DataRegistry sources, AddonManager, InsightRegistry) — not rebuilt.
- Rendered browser evidence is environment-gated (recorded honestly via `tests/e2e/render-admin.mjs` when a live WP
  + browser runtime is available); headless PHP/JS contracts verify structure, truthful state, and tokens.
- CoreX Framework Mode; no `sites/<client>/` edits; no new backend/mutation without a separate reviewed spec.
