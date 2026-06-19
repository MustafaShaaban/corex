# Feature Specification: Stable Client Readiness

**Feature Branch**: `feature/055-stable-client-readiness`  
**Created**: 2026-06-18  
**Status**: Draft  
**Input**: User request to continue Corex after the verified v0.26.1 release audit and prepare the framework for two real company-identity websites and safer multi-agent development.

## User Scenarios & Testing

### User Story 1 - Stabilize the framework before client-site work (Priority: P1)

As the project owner, I need a clear readiness track that identifies and closes framework-level risks before the first two company websites start, so client branding and delivery do not hide unresolved Corex runtime, release, or governance problems.

**Why this priority**: The upcoming company websites depend on Corex being predictable. Runtime leakage from disabled add-ons, inconsistent metadata, or unclear release state would create client-site risk.

**Independent Test**: From a clean checkout, a maintainer can run the documented readiness checks and see pass/fail/blocker status for add-on runtime gating, metadata consistency, CI/security posture, make:site readiness, deployment readiness, UI coverage, Free/Pro boundaries, and multi-agent workflow.

**Acceptance Scenarios**:

1. **Given** a disabled first-party add-on, **When** Corex boots, **Then** the disabled add-on does not register unsafe hooks, routes, REST endpoints, blocks, admin menus, assets, migrations, tables, or cron jobs.
2. **Given** WooCommerce is unavailable, **When** the WooCommerce kit is inactive or its dependency is missing, **Then** Woo-specific behavior does not boot.
3. **Given** a release checkout, **When** version and release references are checked, **Then** package metadata, plugin metadata, README, CHANGELOG, PROGRESS, and docs references agree or report precise mismatches.

---

### User Story 2 - Make multi-agent work safe and auditable (Priority: P2)

As a maintainer coordinating multiple AI agents, I need the repo workflow, ownership, handoff, and guard expectations to be explicit and machine-checkable where possible, so agents do not overwrite each other or contradict the release state.

**Why this priority**: Corex already uses Spec Kit and durable repo memory. Client-site work will increase parallel activity, so branch, spec, progress, and decision discipline must be enforced before more agents operate on the repo.

**Independent Test**: A new agent can read the required entry files, identify the current feature, see who owns the work, know the expected checks, and produce the required final report without relying on chat memory.

**Acceptance Scenarios**:

1. **Given** an agent starts from the repo, **When** it follows the entry instructions, **Then** it learns not to work on `main`, how to inspect uncommitted changes, and where to record progress and decisions.
2. **Given** two agents are assigned different tasks, **When** they follow the documented workflow, **Then** their branch/spec ownership and handoff notes prevent overlapping edits without coordination.
3. **Given** a task claims completion, **When** the guard and verification requirements are reviewed, **Then** the claim includes exact commands, results, skipped checks, and reasons.

---

### User Story 3 - Validate client-site generation and deployment readiness (Priority: P3)

As the person building the first company websites, I need generated client sites to be isolated from the Corex framework and deployment-ready for realistic environments, so each client can own branding and content without editing framework folders.

**Why this priority**: The first two websites should prove Corex can produce isolated client projects with their own namespace, theme tokens, governance files, and compliance checks.

**Independent Test**: Running the documented make:site validation produces or verifies a client plugin, client theme, governance files, token placeholders, and a compliance result that proves client branding does not modify Corex framework paths.

**Acceptance Scenarios**:

1. **Given** a new client-site scaffold, **When** it is inspected, **Then** it contains isolated client plugin/theme folders, namespace/prefix placeholders, `AGENTS.md`, `CLAUDE.md`, `PROGRESS.md`, `DECISIONS.md`, `specs/`, `brand.json`, and a theme token strategy.
2. **Given** a client wants custom logo, color, font, or identity tokens, **When** those values are added, **Then** the edits remain inside the client plugin/theme and client token files.
3. **Given** a deployment target is selected, **When** readiness is checked, **Then** the relevant profile documents required build/package expectations and known blockers.

---

### User Story 4 - Scope native-first UI readiness without redesigning Corex (Priority: P4)

As the project owner, I need a component coverage matrix that shows what Corex already supports for company-identity websites and what is missing, deferred, or Pro-only, so the first websites can ship with native WordPress/FSE patterns instead of triggering a full visual redesign.

**Why this priority**: The final Corex visual redesign is explicitly later. The immediate need is a minimal, accessible, token-based company-site surface.

**Independent Test**: A reviewer can inspect the matrix and see every item classified as a Corex block, WordPress core block style, pattern, form field, admin component, utility, missing, deferred, or Pro candidate, with no hardcoded brand styling or external design-system copying.

**Acceptance Scenarios**:

1. **Given** a company identity page need such as home, about, services, contact, careers, or portfolio, **When** the matrix is reviewed, **Then** it identifies the native Corex or WordPress mechanism to use or marks the gap.
2. **Given** an item is proposed as a custom block, **When** WordPress core blocks/styles/patterns can satisfy it, **Then** the matrix classifies it as native-first instead of adding unnecessary block scope.
3. **Given** a UI readiness change is planned, **When** styling is reviewed, **Then** it uses theme tokens, logical CSS, accessibility requirements, and no Corex visual redesign.

---

### User Story 5 - Keep Free/Core and Pro boundaries clear (Priority: P5)

As the product owner, I need adoption and security basics to remain in Free/Core while advanced commercial capabilities are clearly marked as Pro candidates, so client readiness does not accidentally paywall essential trust features.

**Why this priority**: Security-critical basics, accessibility, RTL, i18n, and basic site generation are core adoption requirements. Advanced automation and vertical kits can be deferred or commercialized without weakening the free framework.

**Independent Test**: A maintainer can review the boundary list and confirm each capability is classified as Free/Core, Pro candidate, deferred, or out of scope, with security-critical basics remaining free.

**Acceptance Scenarios**:

1. **Given** a basic contact form, captcha/honeypot, media field, accessibility, RTL, i18n, config, or make:site requirement, **When** the boundary list is reviewed, **Then** it remains Free/Core.
2. **Given** an advanced capability such as bookings, advanced email providers, Data Manager Pro, white-label admin, Azure automation, or governance dashboards, **When** the boundary list is reviewed, **Then** it can be marked as a Pro candidate without blocking client-site readiness.

## Requirements

### Functional Requirements

- **FR-001**: The readiness work MUST remain framework-stability focused and MUST NOT implement a full Corex visual redesign.
- **FR-002**: The readiness work MUST NOT introduce product features before the Spec Kit plan and tasks are approved.
- **FR-003**: The readiness report or checks MUST inventory add-on runtime gating, metadata/version consistency, CI/security hardening, make:site validation, deployment readiness, component coverage, Free/Pro boundaries, and multi-agent safety.
- **FR-004**: Disabled add-ons MUST NOT register unsafe runtime behavior, including hooks, routes, REST endpoints, blocks, admin menus, assets, custom tables, migrations, or cron jobs, unless a behavior is explicitly documented as safe while disabled.
- **FR-005**: Active add-ons MUST continue to register their expected behavior after runtime gating is introduced.
- **FR-006**: The WooCommerce kit MUST remain gated by both WooCommerce availability and the Corex feature/add-on activation state.
- **FR-007**: Automated or documented tests MUST prove active add-on behavior, inactive add-on non-registration, WooCommerce dependency gating, and existing stable behavior.
- **FR-008**: Metadata checks MUST cover `package.json`, `composer.json`, plugin headers, version constants, update URI, README, CHANGELOG, PROGRESS, and docs version references.
- **FR-009**: Security/governance hardening MUST assess or add Dependabot configuration, CODEOWNERS, CodeQL, branch protection documentation, security documentation, and contribution guidance where missing or stale.
- **FR-010**: CI hardening MUST cover composer validation, PHP lint, Pest, available integration tests, npm build, Jest, ESLint when configured, docs-app build, guard/compliance checks, and environment-gated Playwright smoke coverage where possible.
- **FR-011**: CI MUST support at least one stable WordPress target and MAY also keep trunk compatibility; it MUST NOT depend only on WordPress trunk/master.
- **FR-012**: make:site validation MUST prove generated client sites include an isolated client plugin, isolated client theme, namespace/prefix placeholders, governance files, `specs/`, `brand.json`, theme token strategy, guard/spec bootstrap, and a compliance check that blocks direct edits to Corex framework folders for client branding.
- **FR-013**: Deployment readiness MUST document or validate profiles for minimal, standard, full, Woo, client-site, shared-host, Azure/container, local Docker, wp-env stable, and wp-env trunk compatibility.
- **FR-014**: The component coverage matrix MUST classify each item as CoreX block, WordPress core block style, pattern, form field, admin component, utility, missing, deferred, or Pro candidate.
- **FR-015**: UI readiness MUST be native WordPress/FSE first, token-only through `theme.json` or CSS custom properties, logical CSS/RTL-first, WCAG 2.2 AA oriented, responsive, and accessible by keyboard.
- **FR-016**: The UI scope MUST build only what is needed for the first two company-identity websites and MUST defer the final Corex visual redesign.
- **FR-017**: Free/Core boundaries MUST include adoption and security basics: core framework, basic blocks/DLS, basic forms/contact form, basic config/options, basic media fields, basic captcha/honeypot, accessibility, RTL, i18n, basic make:site, and basic docs/deployment docs.
- **FR-018**: Pro candidate boundaries MAY include advanced newsletter, bookings, careers/ATS, WooCommerce kit, advanced email queue/logs/templates/providers, advanced media CDN/offload/optimization, Data Manager Pro, white-label admin, starter kits, Azure/DevOps automation, AI-agent governance dashboards/reports, multi-company identity kit, and client portal/dashboard.
- **FR-019**: Security-critical basics MUST NOT be classified as Pro-only.
- **FR-020**: Multi-agent readiness MUST document or check one branch per task/agent, never work on `main`, git status first, no overlapping edits without coordination, progress handoff format, decisions log, spec ownership, required CI checks, CODEOWNERS, guard gates, and final report format.
- **FR-021**: Any implementation phase MUST update PROGRESS accurately and log non-trivial decisions in DECISIONS.
- **FR-022**: Environment-gated checks such as Docker/wp-env or browser E2E MUST be reported as gated when unavailable, not treated as framework failure.

### Key Entities

- **Readiness Finding**: A pass, fail, warning, or environment-gated result for a readiness category, with command evidence and owner.
- **Add-on Runtime State**: The active, inactive, dependency-missing, or safe-disabled state that determines whether an add-on may register behavior.
- **Client Site Scaffold**: The generated client plugin/theme/governance/token/spec structure produced for a company website.
- **Component Coverage Item**: A UI or content capability classified by mechanism, accessibility expectations, token strategy, and Free/Pro status.
- **Deployment Profile**: A target environment with expected package shape, build checks, dependencies, and blockers.
- **Free/Pro Boundary Item**: A capability classification that protects adoption/security basics while reserving advanced commercial scope.
- **Agent Work Unit**: A branch/spec/task ownership record with handoff, verification, guard, and final-report requirements.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Every required readiness category has a documented pass, fail, warning, or environment-gated status with exact evidence.
- **SC-002**: Runtime gating tests prove disabled first-party add-ons register no unsafe behavior while active add-ons continue to work.
- **SC-003**: WooCommerce kit tests prove no Woo-specific boot behavior occurs when WooCommerce is unavailable or the kit is inactive.
- **SC-004**: Version consistency checks either pass across all required metadata surfaces or report exact mismatched files and values.
- **SC-005**: make:site validation proves client scaffolds are isolated and include governance/spec/token placeholders and framework-folder protection.
- **SC-006**: CI/security governance coverage is documented and any missing repository-only or GitHub-settings-only controls are clearly distinguished.
- **SC-007**: The component coverage matrix covers existing Corex DLS items and the minimum content needs for two company-identity websites.
- **SC-008**: No deliverable in this spec performs the final Corex visual redesign or adds client-specific branding to framework folders.

## Assumptions

- v0.26.1 is the current released baseline at `e30b1fe`, as verified by the Phase 0 audit.
- The first two client websites are company-identity/FSE-style sites, not complex application portals.
- Local Docker/wp-env and browser automation may be unavailable; WAMP/local checks may be used when documented.
- This spec authorizes planning and subsequent approved implementation phases, not immediate product-code changes.
