# Feature Specification: New Design Gap Implementation

**Feature Branch**: `spec/063-new-design-gap-implementation`

**Created**: 2026-07-02

**Status**: Draft

**Input**: Finish the implementation-ready CoreX design gaps from the new "Corex Final Design
Gap-Closure" package (`F:\Work\CoreX.zip`) and apply the approved design language consistently across
the admin product surfaces and the company-site theme. Design intake recorded at
`design/handoffs/063-new-design-gap-implementation.md`.

## Overview

CoreX has an approved, frozen visual foundation (brand tokens, logo, typography — M2) and a landed
admin shell + login + Add-ons/Data/Settings surface (M6). The new design package audits the whole
product and, per its own truthfulness rule, marks each area **frozen**, **owner-review**,
**needs-another-pass**, or **future-only**. This feature closes the *implementation-ready* gaps in
priority order while keeping one hard invariant: **every screen, card, badge, metric, and integration
communicates its real state** — no fake data, charts, records, integrations, Pro behavior, marketplace
behavior, or licensing flows.

The work is one parent goal split into safe, independently shippable **batches** (Phase 0–8). Each
batch is spec-first, guard-gated, tested, i18n/RTL/accessibility-verified, and documented before it
ships. A batch that cannot be built truthfully this cycle is **hidden or honestly gated** (no dead
entry points) and recorded as deferred — it is never faked.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Truthful admin at a glance (Priority: P1)

A site operator opens the CoreX Overview and immediately sees the true operational state of the site:
its environment/operations mode, launch readiness, security/login-protection status, add-on health,
and — only where the underlying data really exists — forms/submissions/email/captcha/media/data/insights
summaries and setup-wizard progress. Where a capability is not implemented or not configured, the
screen says so honestly (empty / not-configured / setup-required) rather than inventing numbers.

**Why this priority**: It is the highest-trust surface and the anchor for every later batch. It reuses
the merged M6 shell, so it is the lowest-risk, highest-value slice and delivers a viable MVP alone.

**Independent Test**: Load the Overview under each required state (fresh install, development, staging,
production, maintenance, coming-soon, loading, empty, error, permission-denied) and confirm every panel
shows a truthful value or an honest empty/gated state, with no fabricated metric.

**Acceptance Scenarios**:

1. **Given** a fresh install with no submissions and no activity log, **When** the operator opens
   Overview, **Then** the activity panel shows an honest empty state and no submissions/traffic numbers
   are invented.
2. **Given** the site is in a non-production operations mode, **When** Overview renders, **Then** an
   environment/mode badge and operations-mode status card reflect the real mode.
3. **Given** a user without the CoreX admin capability, **When** they reach a CoreX screen, **Then** a
   permission-denied state is shown (via `AdminGuard`), not a broken or partial screen.

---

### User Story 2 - Forms, submissions, and transactional email (Priority: P2)

An operator manages contact/inquiry flows: defining a flow's form schema and routing, reviewing real
submissions in an inbox (filter/search/detail/status/export-gated-by-capability), and managing branded
transactional email templates whose real sends are **suppressed in development/staging** and logged.
No fake submissions or fake sends are ever displayed.

**Why this priority**: The roadmap lists Forms and Email Experience (M7) as the next planned milestone;
the package marks Forms & Flows / Submissions / Email Studio owner-review-ready.

**Independent Test**: Create a flow, submit through it in a test environment, confirm the submission
appears in the inbox with a truthful email-delivery status (suppressed/logged in dev), and confirm
export requires the capability.

**Acceptance Scenarios**:

1. **Given** a defined flow, **When** a visitor submits, **Then** the submission is stored and appears
   in the inbox with its real fields and a truthful email status.
2. **Given** development/staging delivery mode, **When** an email would send, **Then** it is suppressed
   and logged, and the UI states that real sending is off.
3. **Given** a user lacking the export capability, **When** they open the inbox, **Then** the export
   action is hidden or disabled with a truthful reason.

---

### User Story 3 - Safe data, operations, security, and access (Priority: P3)

An operator manages CoreX-owned data models (records CRUD only where the model supports it; CSV import
with dry-run + validation before any mutation; capability-gated export), switches operations mode with
guardrails and production confirmation, configures login protection with a reversible recovery path,
and assigns CoreX capability groups via a role matrix that cannot lock the last administrator out.

**Why this priority**: These are powerful, dangerous surfaces. They must be truthful, reversible, and
lockout-safe, so they come after the trust-anchor and forms batches.

**Independent Test**: Run a CSV import dry-run that reports invalid/duplicate rows and mutates nothing
until confirmed; attempt to remove your own critical access and be prevented; toggle a login-protection
setting and confirm the documented CLI/config recovery path exists.

**Acceptance Scenarios**:

1. **Given** a CSV with invalid rows, **When** the operator runs import, **Then** a dry-run validation
   report is shown and no records are written until explicit confirmation.
2. **Given** the operator is the only administrator, **When** they try to remove their own CoreX admin
   ability, **Then** the change is blocked with a clear message.
3. **Given** a dangerous production action, **When** it is triggered, **Then** a nonce+capability-checked
   confirmation step is required before it proceeds.

---

### User Story 4 - Truthful readiness, setup, and public company-site surfaces (Priority: P4)

An operator uses Insights (only real checks; disconnected/setup-required/planned states for anything not
connected) and a Setup Wizard (welcome/brand/dependencies with skipped/completed/blocked states, a
launch checklist, and a "not safe to go live" state), and the company site gains Blog/News with
privacy-friendly social sharing plus the remaining company-kit page/block coverage — all neutral,
accessible, RTL-correct, and reduced-motion-aware.

**Why this priority**: Public-facing and readiness polish build on the earlier admin foundations and the
company kit; they are valuable but lower-risk to defer within the batch.

**Independent Test**: Open Insights with no external providers connected and confirm every widget shows
a truthful disconnected/not-configured/planned state with no fabricated score; render the blog single
post and confirm the share bar is keyboard-accessible, RTL-correct, and shows no fake counts.

**Acceptance Scenarios**:

1. **Given** no connected analytics/performance provider, **When** Insights renders, **Then** widgets
   show disconnected/setup-required/planned states and never a fake score.
2. **Given** a launch checklist with an unmet requirement, **When** the wizard renders, **Then** a
   "not safe to go live" / production-blocked state is shown.
3. **Given** a single blog post, **When** it renders, **Then** the social share bar is present, has
   accessible labels, mirrors correctly in RTL, and displays no share counts.

### Edge Cases

- A future-only area (Blog Pro, Portfolio, WooCommerce, Pro/commercial, Auth/Account) must never appear
  as a live, actionable capability — only as a hidden or clearly reference/deferred surface.
- WooCommerce-dependent surfaces must stay dual-gated (Woo available AND CoreX add-on active) and degrade
  honestly when Woo is absent.
- A capability that is gated off, a dependency that is missing, or a secret that is unset must each
  render a distinct, truthful state (disabled / dependency-missing / secret-missing).
- A destructive operation with no real rollback support must not claim a rollback exists.
- A batch that cannot be completed truthfully this cycle is hidden/gated and deferred — never stubbed as
  working.

## Requirements *(mandatory)*

### Functional Requirements

**Truthfulness & gating (cross-cutting — every phase)**

- **FR-001**: The system MUST NOT display fabricated data, charts, records, integrations, Pro behavior,
  marketplace behavior, or licensing flows anywhere; where real data is absent, a truthful
  empty/not-configured/setup-required/planned state MUST be shown.
- **FR-002**: The system MUST NOT present dead entry points; any unimplemented or out-of-batch area MUST
  be hidden or honestly gated (disabled with a truthful reason).
- **FR-003**: Repository copy MUST NOT claim CoreX is commercial-, marketplace-, Pro-purchase-,
  ThemeForest-, or license-flow-ready; freeze language MUST match the corrected wording in the design
  intake.
- **FR-004**: Future-only areas (Blog Pro, Portfolio, WooCommerce, Docs/Marketing productization,
  Pro/commercial, Auth/Account, Advanced Access Manager) MUST be documented and surfaced as
  future/reference only, not as active UI.

**Admin Overview & state language (Phase 1)**

- **FR-010**: The Overview MUST present truthful summary panels for environment/operations mode, launch
  readiness, security/login-protection, add-on health, and — only where real data exists —
  forms/submissions, email delivery mode/status, captcha, media/WebP, data/model, insights/readiness,
  and setup-wizard progress, plus documentation/help links.
- **FR-011**: The Overview MUST render honestly across fresh-install, development, staging, production,
  maintenance, coming-soon, loading, empty, error, and permission-denied states.
- **FR-012**: Recent activity MUST show real logged activity or an honest empty state; it MUST NOT invent
  activity when activity logging is not implemented.

**Forms & Flows / Submissions / Email (Phase 2)**

- **FR-020**: Operators MUST be able to define a flow (form schema, field types, validation, routing,
  email routing) and preview its visitor/empty/loading/error/permission states, with a test mode.
- **FR-021**: The Submissions Inbox MUST list real submissions with filters/search, a detail drawer,
  status/read/archive/spam handling where supported, and capability-gated export; it MUST show honest
  empty states and never fake records.
- **FR-022**: Email Studio MUST manage templates/layouts with a token/variable browser, preview, and
  test send/log; it MUST be delivery-mode aware and MUST suppress real sends in development/test mode.
- **FR-023**: Email variable output MUST be escaped and MUST NOT execute arbitrary code; secrets MUST NOT
  leak into logs or previews.

**Data Models / import-export / migrations (Phase 3)**

- **FR-030**: The Data Models admin MUST list models and records and allow create/edit/view/delete only
  where the underlying model supports it, showing honest disabled/read-only states otherwise, each gated
  by capability.
- **FR-031**: CSV import MUST provide column mapping and a dry-run validation report (invalid/duplicate/
  skipped rows) and MUST apply changes only after explicit confirmation.
- **FR-032**: Export MUST be capability-gated (selected/filtered/all where supported) and MUST warn on
  personal data; migrations MUST show pending list + dry-run/plan + production warning and MUST NOT claim
  rollback support that does not exist.

**Operations / Security / Access (Phase 4)**

- **FR-040**: Operations Mode MUST display the real environment/mode and only offer a mode change when it
  is real and safe, applying per-mode guardrails and a production-launch confirmation; it MUST NOT claim a
  mode changed if it did not.
- **FR-041**: The Security Center MUST present login-protection settings (custom login URL/path only if
  implemented safely), rate-limit/failed-login and captcha status where implemented, hardening checks, and
  audit/log states; it MUST NEVER rename or move WordPress core files and MUST provide a reversible
  config/CLI recovery path if a login guard is implemented.
- **FR-042**: Access & Abilities MUST provide CoreX capability groups and a role matrix for `corex_*`
  abilities with an audit log and access-denied screen, MUST detect third-party role/capability plugins
  and avoid conflicts, and MUST prevent removing the last administrator's critical access. It MUST NOT be
  a full access-manager clone.

**Settings (Phase 5)**

- **FR-050**: Settings MUST cover general/appearance/brand/operations/security/captcha/email/media/
  retention/advanced with truthful states (saved/dirty/validation-error/secret-stored/secret-missing/
  test-success/test-failure/dependency-missing/locked-by-config/permission-denied/reset-confirm).
- **FR-051**: Captcha settings MUST be provider-specific (None, Honeypot, reCAPTCHA, hCaptcha, Cloudflare
  Turnstile) showing only the selected provider's fields/links, and secrets MUST be write-only.

**Insights & Setup Wizard (Phase 6)**

- **FR-060**: Insights MUST run only real checks and readiness widgets; anything not connected MUST show
  disconnected/setup-required/planned states, with a "last checked" indicator where applicable and no
  fabricated scores.
- **FR-061**: The Setup Wizard MUST present welcome/brand/dependency steps with skipped/completed/blocked
  states, a launch checklist with a "not safe to go live" state, confirmation states, and resume-later,
  using preview-then-apply before any mutation.

**Blog / Site Kit / Blocks (Phase 7)**

- **FR-070**: The theme MUST provide native-WordPress-post Blog/News coverage (index, single, archive/
  category, search/no-results, comments where enabled, related posts where supported) and a
  privacy-friendly social share component with accessible labels, RTL-correct ordering, and no share
  counts. No custom blog engine.
- **FR-071**: The Company Site Kit MUST fill missing M4 page/template coverage with neutral content and
  safe apply/reset/adopt/skip/conflict behavior; the prioritized company/blog blocks (services grid,
  service detail, process/steps, icon box, logo cloud/trust badges, case study, rich tabs, testimonial,
  gallery, featured posts, newsletter signup, contact/map, social share) MUST support keyboard, RTL, and
  reduced motion, with no global slider JS and no autoplay by default.

**Cross-cutting quality (every UI change)**

- **FR-080**: All visible UI MUST support dark mode, light mode, RTL, keyboard operation, reduced motion,
  and WCAG-conscious focus/contrast; all styling MUST use tokens/`theme.json`/CSS variables and logical
  properties (no hardcoded colors/fonts/sizes/directional CSS unless unavoidable and documented).
- **FR-081**: Every dangerous mutation MUST enforce nonce + capability checks and a confirmation step
  where appropriate; every new secret MUST be write-only.
- **FR-082**: Optional add-ons MUST remain optional and self-disabling; WooCommerce features MUST stay
  gated behind WooCommerce availability and CoreX add-on activation.

### Key Entities *(include if feature involves data)*

- **Operations Mode**: The site's declared operating mode (development, staging, production, maintenance,
  coming-soon, private/internal, read-only, forms-paused) and the behaviors that truthfully change with it.
- **Flow**: A form schema + validation + routing + email routing + success behavior around a submission.
- **Submission**: A stored form response with fields, metadata, status, assignment, email status, consent
  snapshot, and retention info.
- **Email Template / Layout**: Reusable transactional email content and layout with variables, delivery
  mode, and logs.
- **Data Model / Record**: A CoreX-owned model, its schema/fields, and its records (CRUD only where
  supported).
- **Capability Group / Role Assignment**: CoreX `corex_*` ability groups mapped to WordPress roles.
- **Readiness Check**: A real, verifiable site-readiness or insight signal with a connection/config state.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Across every implemented admin and theme surface, 100% of panels/cards/widgets show either
  real data or a truthful empty/not-configured/gated state — zero fabricated values (verified by tests
  and render evidence).
- **SC-002**: 100% of the required Overview states (fresh/dev/staging/production/maintenance/coming-soon/
  loading/empty/error/permission-denied) render without a fabricated metric or a broken/dead panel.
- **SC-003**: 100% of data mutations reachable from the UI are protected by capability + nonce and, where
  dangerous, a confirmation step (verified by tests).
- **SC-004**: No CSV import writes any record before an explicit confirmation following a dry-run
  validation report (verified by tests).
- **SC-005**: The last administrator can never be stripped of critical CoreX access through the role
  matrix (verified by a lockout-prevention test).
- **SC-006**: Every new visible UI passes RTL, dark, light, reduced-motion, and keyboard-focus checks and
  uses only token-based styling (no hardcoded colors/sizes/fonts; verified by lint + review).
- **SC-007**: 100% of secrets are write-only — never rendered back to the screen or a log (verified by
  tests).
- **SC-008**: Every future-only area is documented and surfaced as future/reference only, with no active,
  actionable UI (verified by review + tests where entry points exist).

## Assumptions

- The frozen brand/token/logo foundation (M2) and the merged admin shell + login + Add-ons/Data/Settings
  surface (M6) are reused, not rebuilt.
- Batches ship independently and in priority order; a batch that cannot be built truthfully this cycle is
  hidden/gated and deferred rather than faked, and each batch is separately guard-gated, tested, and
  documented.
- Existing CoreX foundations (container, services/repositories, events, security `AdminGuard`, settings
  registry, add-on status model, data tooling, forms/mail packages, block engine, company blueprint) are
  the substrate; new work extends them via the PSR-11 container.
- CSV is the first import/export format; XLSX is future. Rollback/snapshot messaging appears only where
  technically supported.
- Neutral placeholder content only (e.g. "Acme Company"); no real client/company content enters CoreX.
- This is CoreX Framework Mode; `sites/<client>/` is out of scope.
- Owner sign-off is still required for the Operations Mode model, Security Center scope, Access & Abilities
  scope, the Forms-vs-Flow model + extension points, the Email Studio upgrade + layout-builder boundary,
  the safe Data-model-manager scope, and Company Site Kit page coverage (recorded in the design intake).
  Phases that depend on an unresolved owner decision stop for sign-off rather than inventing scope.
