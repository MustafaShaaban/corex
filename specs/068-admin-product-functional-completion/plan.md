# CoreX Product Functional Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use `superpowers:executing-plans` to implement this plan task-by-task. Subagent execution is not authorized for this work unit. Steps are tracked in `tasks.md`.

**Branch**: `fix/067-admin-shell-and-completion` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Goal:** Turn every approved CoreX admin and product surface into real, secure, persisted, tested behavior with visual parity and no fake, planned, reference-only, read-only, or dead-control state.

**Architecture:** Preserve WordPress-native content domains and the existing CoreX layered architecture. Add shared product foundations for activity, abilities, write-capable data sources, bounded jobs, result-bearing email, and versioned workflow state, then deliver independently testable vertical slices. Controllers remain thin, services own orchestration, repositories own all persistence, and optional providers stay behind registries/adapters.

**Tech Stack:** WordPress 7.0+, PHP 8.3+, PSR-11 container, WordPress REST/admin APIs, CoreX route middleware and AdminGuard, WordPress packages/React for rich admin interactions, vanilla Interactivity API for front-end behavior, token-only CSS, Pest/Brain Monkey, Jest, Playwright, WP-CLI, and the existing CoreX build/distribution pipeline.

---

## Summary

Spec 068 is a completion program, not a cosmetic pass. It replaces the earlier presentation-only boundaries with one product contract and implements it in vertical batches. Shared foundations land first so screens do not invent competing audit logs, permission models, write adapters, background jobs, or email results. Each subsequent batch must complete a real user workflow, update docs, pass its focused tests and guards, and add rendered evidence before the next batch is considered complete.

The active untracked `plugins/corex-config/src/Insights/InsightWidgets.php` is adopted as prior in-progress work. It may be refactored or replaced, but its real provider/local-signal mapping is preserved and its `Planned` state is removed.

## Technical Context

**Language/Version**: PHP 8.3+; JavaScript supported by `@wordpress/scripts` 32; CSS compiled by the existing workspace build

**Primary Dependencies**: WordPress 7.0 APIs, PSR-11 container, CoreX data/middleware/event/form/mail/provisioning foundations, optional Action Scheduler, optional WooCommerce and external insight/mail/captcha providers through adapters

**Storage**: WordPress posts/meta for native content and revision-oriented configuration; CoreX managed tables for high-volume operational events, timelines, requests, analytics, and job state; options only for bounded configuration and secrets

**Testing**: Pest unit and integration tests, Brain Monkey boundaries, Jest UI/state tests, Playwright browser/E2E/visual checks, WP-CLI smoke, token/RTL/dependency/distribution contracts

**Target Platform**: WordPress 7.0+ on PHP 8.3+ under standard web, REST, admin, CLI, cron, and block-theme contexts

**Project Type**: WordPress framework monorepo with plugins, optional add-ons, packages, FSE theme, docs app, and generated distribution

**Performance Goals**: interactive admin reads return within one second at the 95th percentile on the reference environment for 10,000-record sources; front-end form acceptance completes within two seconds excluding external-provider latency; list pages render at most 100 rows; long work uses bounded resumable batches

**Constraints**: no raw design values, no global asset library, no theme business logic, no optional hard dependency, no direct controller persistence, no unconfirmed destructive change, no personal-data leakage, no administrator lockout, and no fake success

**Scale/Scope**: fourteen approved product areas, forty-four primary design artifacts, ten user journeys, 167 functional requirements, and complete dark/light/LTR/RTL/mobile/a11y state coverage

## Constitution Check

*GATE: PASS before research and PASS after design.*

- [x] **I. Theme is a skin** — all domain state, authentication orchestration, analytics, forms, and data behavior live in plugins/add-ons; theme contains templates, parts, patterns, and token consumption only.
- [x] **II. Plugins boot themselves** — every new provider registers independently and works in CLI, REST, admin, and cron contexts without theme bootstrapping.
- [x] **III. Thin controllers, fat services** — route/admin boundaries validate and delegate; services orchestrate; repositories/adapters own data access.
- [x] **IV. Everything injected** — new dependencies are container bindings; construction inside methods is prohibited.
- [x] **V. Runtime tokens** — admin uses `--corex-admin-*`; front end uses `theme.json` variables; no raw shipped design values or build-time token system.
- [x] **VI. Conditional assets** — blocks declare assets in `block.json`; admin assets remain limited to matching CoreX screens; no public global UI bundle.
- [x] **VII. Declarative security** — REST/AJAX routes use middleware; admin-menu mutations use `AdminGuard`; all output/input/query safety is enforced at the correct boundary.
- [x] **VIII. RTL-first** — logical properties and direction-aware component behavior are required in every task and browser acceptance set.
- [x] **IX. No optional dependency is hard** — WooCommerce, captcha, mail, analytics, ACF, and role plugins are detected through registries/adapters.
- [x] **X. Spec is source of truth** — Spec 068 supersedes conflicting deferral language and all tasks trace to requirement IDs.
- [x] **Guard Gate + Definition of Done** — every implementation batch includes focused tests, relevant guards, i18n, RTL, WCAG 2.2 AA, docs, decisions, and progress evidence.
- [x] **Environment Gate** — WordPress 7.0, CoreX theme, and required plugins boot successfully on `corex.local`; recheck before each implementation batch.

## Architecture Decisions

1. **One shared activity stream**: `Corex\Activity` contracts live in core, while a CoreX-managed table stores product events. Domain services publish events only after authoritative outcomes. Screens query through a repository with capability-aware redaction.
2. **One ability catalog**: CoreX declares `corex_*` abilities grouped by product area. A service owns grant/revoke/request/approve rules and lockout prevention. Native capabilities remain compatibility inputs, not the editable product model.
3. **Capability-oriented data sources**: replace boolean assumptions with explicit read/query/schema/create/update/delete/bulk/import/export declarations and adapters. UI derives actions from the source contract.
4. **Versioned workflow configuration**: flows and email templates are persisted domain records with immutable published/version snapshots. Historical submissions and deliveries keep the version identifiers used.
5. **Result-bearing side effects**: mail, exports, imports, migrations, kit apply, retention, and security changes return typed result objects. No UI infers success from a void dispatch.
6. **Bounded jobs**: long work stores a job record with cursor, counts, state, retries, actor, and result. Action Scheduler is used when available; a WP-Cron/CLI-compatible dispatcher remains available without a hard dependency.
7. **Native content stays native**: posts, comments, users, roles, schedules, taxonomies, and media remain WordPress-owned. Blog Pro layers editorial metadata and analytics without replacing core records.
8. **Design state contract**: every interactive component implements default, hover, focus-visible, active, disabled-with-reason, loading, empty, error, success, and permission states where applicable. Browser evidence covers dark/light/LTR/RTL/mobile/reduced-motion.

## Delivery Sequence

### Phase 0 — Governance and Baseline

- Finalize Spec 068 artifacts, requirement traceability, existing-state audit, and screenshot matrix.
- Record the owner override that required current design equals functionality and supersedes former deferrals.
- Capture baseline runtime/storage migrations and compatibility requirements.

### Phase 1 — Shared Product Foundations

- Activity event model/repository/service and cross-domain subscribers.
- CoreX ability catalog, policy service, access request/grant workflow, and lockout rules.
- Data-source capability/write/import/export contracts and bounded job contract.
- Shared confirmation/audit/result value objects and admin/REST error envelope.
- Result-bearing mail contract and environment delivery policy.

### Phase 2 — Email Studio Vertical Slice

- Persisted templates, layouts, partials, variables, routing, previews, plain text, test sends, logs, health, and resend.
- Development capture and Production provider gates.
- Forms and access integrations consume the new contracts after this phase.

### Phase 3 — Forms, Flows, and Submissions Vertical Slice

- Versioned flow repository, registries, field/rule/action extensions, editor REST/UI, lifecycle, and blocks.
- Submission pipeline stages, version/consent/test metadata, routing, email, status/owner/notes/timeline, bulk actions, reply/resend, export, and retention.
- End-to-end visitor-to-inbox browser proof.

### Phase 4 — Data Explorer and Data Models Vertical Slice

- Real query/sort/filter/pagination, permission-aware schema/detail, write adapters, previews, bulk actions.
- CSV mapping/dry-run/report/commit, CSV/XLSX export/history, migrations/snapshot/transaction/rollback/history.

### Phase 5 — Operations, Security, and Access Vertical Slice

- Production readiness block/override, maintenance behavior, full Security Center, login rate limits, custom route, activity, retention, recovery constant and command.
- Editable CoreX ability matrix, external-role-plugin coexistence, access request/grant/notification/audit, real forbidden state.

### Phase 6 — Blog Pro Vertical Slice

- First-party privacy-aware counters, ranges/charts/top posts/authors.
- Native editorial transitions, assignment/notes/due date/scheduling; native comment moderation; sharing settings/logging.
- Complete blog templates and visitor workflow.

### Phase 7 — Overview, Add-ons, Insights, Setup, and Settings

- Overview consumes real summaries and unified activity/readiness.
- Add-ons completes installation/update/dependency/edition/registration state and safe toggles.
- Insights replaces planned widgets with real provider/local checks, run/retry/setup/history/recommendations.
- Nine-step setup with persisted progress, backup/apply/conflicts/rollback/reset/final readiness.
- Complete settings domains, unsaved/discard, media regeneration, retention, diagnostics, and danger actions.

### Phase 8 — Blocks, Theme, Authentication/Profile, and Docs

- Finish approved component inventory and interaction engines.
- Complete headers/navigation/search/drawer/mega-menu and all approved theme states/templates.
- Implement approved front-office account flows through a plugin/add-on layer, with theme presentation only.
- Complete Docs navigation/search/command/version/copy/reference behavior.

### Phase 9 — Completion Audit and Release Readiness

- Remove all stale prohibited language and dead-control branches.
- Run requirement-by-requirement source/test/runtime/render evidence audit.
- Run full tests, builds, guards, browser matrices, distribution checks, docs updates, and final owner report.

## Project Structure

### Documentation

```text
specs/068-admin-product-functional-completion/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── rest-api.md
│   ├── extension-api.md
│   ├── cli.md
│   └── ui-state.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code

```text
plugins/corex-core/src/
├── Activity/                 # event value, repository contract, service
├── Access/                   # ability catalog/policy value contracts
├── Jobs/                     # bounded resumable job contracts/results
├── Data/                     # source capability and write-adapter contracts
├── Http/                     # shared response/error/confirmation contracts
└── Mail/                     # result-bearing transport-neutral contract

plugins/corex-config/src/
├── Activity/                 # managed-table activity repository/admin query
├── Access/                   # matrix/request/grant/audit workflows
├── Addons/                   # installed/update/dependency actions
├── Blog/                     # analytics/editorial/comments/authors/settings
├── Data/                     # Explorer adapters/controllers/exports
├── DataModels/               # write/import/export/migration workflows
├── Forms/                    # builder screen and admin client boundary
├── Insights/                 # widget state/history/recommendations
├── Operations/ and Security/ # mode/readiness/login/recovery
├── Overview/                 # real command-center projection
├── Settings/                 # complete settings UX/actions
└── Submissions/              # Inbox/detail/bulk/export/notes/timeline

plugins/corex-forms/src/
├── Flow/                     # flow aggregate/repository/version/lifecycle
├── Schema/                   # field types/options/versioned schema
├── Routing/                  # ordered rules/targets/fallback
├── Success/                  # success-state registry
├── Submission/               # staged pipeline/store/status/assignment
├── Timeline/                 # submission event repository
└── Block/                    # six bound dynamic blocks

addons/corex-email/src/
├── Capture/                  # development capture store
├── Template/                 # persisted templates/layouts/partials/variables
├── Routing/                  # trigger binding and recipient rules
├── Delivery/                 # provider result/log/resend/health
└── Queue/                    # bounded dispatch and retries

addons/corex-ui/src/          # approved components and front-end interactions
addons/corex-kit-company/src/ # nine-step setup/apply/backup/rollback
packages/cli/src/             # security recovery and supported job commands
theme/                        # presentation-only templates/parts/patterns/tokens
docs/ and docs-app/           # accurate guides and complete docs UI
tests/                        # unit, integration, contracts, E2E, visual evidence
```

**Structure Decision**: Extend existing package boundaries. Shared cross-domain interfaces belong in `corex-core`; optional domain behavior stays in its add-on; `corex-config` owns admin product management; the FSE theme remains presentation-only. Large legacy screen classes are split as they are touched so rendering, commands, services, and persistence remain independently testable.

## Testing and Evidence Strategy

1. Write a failing focused test before each behavior change.
2. Verify the test fails for the intended missing behavior.
3. Implement the minimum complete domain behavior through the declared layer.
4. Run the focused test, then the affected package suite.
5. Exercise the live WordPress path through CLI/HTTP/browser as applicable.
6. Run the relevant guards and documentation checks.
7. Capture dark/light/LTR/RTL/mobile/hover/focus/state evidence for changed UI.
8. Update task IDs, `PROGRESS.md`, decisions, roadmap, design inventory, and relevant docs before commit.

The final audit uses [quickstart.md](quickstart.md) plus a requirement traceability table in `tasks.md`. A requirement is complete only when direct evidence exists; passing unrelated tests or absence of search matches is insufficient.

## Rollback Strategy

- All schema changes are additive until data migration and compatibility tests pass.
- Flow/template versions are immutable once published or used; edits create new versions.
- Managed-table migrations take a pre-change snapshot/backup where rollback is supported.
- Existing `corex_submission` and `corex_email_log` records remain readable throughout migration.
- New feature activation is per-domain and can be disabled without hiding or corrupting stored data.
- Production, login, access, setup, import, retention, and destructive changes have explicit reverse/recovery paths.

## Complexity Tracking

No constitution violations are planned. The large scope is owner-mandated and is controlled through vertical slices, shared contracts, bounded jobs, requirement traceability, and per-batch gates rather than duplicated one-off implementations.
