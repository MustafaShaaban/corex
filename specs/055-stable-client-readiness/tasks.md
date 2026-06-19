# Tasks: Stable Client Readiness (055)

**Feature**: `specs/055-stable-client-readiness` · **Branch**: `feature/055-stable-client-readiness`
**Inputs**: plan.md · spec.md (US1-US5) · research.md (D1-D10) · data-model.md · contracts/{runtime-gating,
metadata-consistency, make-site-validation, readiness-report, component-boundaries}.md · quickstart.md

**Story legend**: US1 = framework/runtime/release readiness (P1, MVP) · US2 = multi-agent safety (P2) · US3 =
make:site + deployment readiness (P3) · US4 = native-first component coverage (P4) · US5 = Free/Core vs Pro
boundaries (P5).

**Conventions**: `[P]` = parallelizable (different files, no incomplete-task dependency). Tests are REQUIRED
(constitution DoD): Pest for PHP/CLI/runtime validators, docs guard for governance/matrices, Playwright/wp-env
reported as environment-gated when unavailable. Every story ends with a Guard Gate task; no diff ships until its
guards run clean.

---

## Phase 1: Setup

**Purpose**: Confirm current branch/state and collect the readiness baseline before implementation.

- [X] T001 Confirm branch and feature pointer with `git status --short --branch` and `.specify/feature.json`; record
  that `feature/055-stable-client-readiness` owns `specs/055-stable-client-readiness` in `PROGRESS.md`.
- [X] T002 [P] Run the headless baseline commands from `specs/055-stable-client-readiness/quickstart.md`:
  `composer validate --no-check-publish`, `composer test`, `npm run build`, and `npm run test:js`; record counts
  or exact environment blockers in `PROGRESS.md`. Partial 2026-06-18: composer validate passed; composer test
  passed (566 tests, 1879 assertions, 1 existing Brain Monkey warning); `npm.cmd run build` passed after sandbox
  escalation because Node could not lstat `C:\Users\pc` inside the sandbox; `npm.cmd run test:js` not run because
  the required escalation was rejected by the automatic approval reviewer due account usage limit. Completed after
  permissions changed: `npm.cmd run test:js` passed (15 suites, 55 tests).
- [X] T003 [P] Inspect the existing runtime and readiness surfaces before code changes:
  `plugins/corex-core/src/Boot.php`, `plugins/corex-config/src/Addons/AddonRegistry.php`,
  `plugins/corex-config/src/Addons/AddonManager.php`, `addons/corex-kit-woo/src/WooKitGate.php`,
  `packages/cli/src/Site/SiteScaffolder.php`, `.github/workflows/ci.yml`, `.github/workflows/e2e.yml`, and
  `.github/workflows/docs.yml`; note drift or blockers in `specs/055-stable-client-readiness/research.md`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Create shared contracts and test fixtures used by multiple stories.

**CRITICAL**: No user story implementation starts until these tasks are complete.

- [X] T004 Create shared first-party provider metadata in `plugins/corex-core/src/Foundation/AddonProvider.php`
  and `plugins/corex-core/src/Foundation/AddonProviderRegistry.php` for slug, provider class, plugin file,
  dependencies, feature flag, and external gate names.
- [X] T005 [P] Add reusable runtime test fixtures in `tests/Unit/Foundation/AddonProviderFixtures.php` for active,
  inactive, dependency-missing, not-installed, and Woo-missing provider cases.
- [X] T006 [P] Create the readiness report skeleton in `packages/cli/src/Release/ReadinessFinding.php` and
  `packages/cli/src/Release/ReadinessReport.php` using the fields from `data-model.md`.
- [X] T007 Create `tests/Unit/Release/ReadinessReportTest.php` proving every required readiness category is
  represented and `environment-gated` findings require evidence and a next action.

**Checkpoint**: Shared models exist; user story implementation can proceed.

---

## Phase 3: User Story 1 - Stabilize the framework before client-site work (P1) MVP

**Goal**: A maintainer can run readiness checks that prove runtime gating, Woo gating, metadata consistency,
CI/security posture, and existing stable behavior.

**Independent Test**: From a clean checkout, the readiness command/report returns pass/fail/warning/gated entries
with exact evidence for the required categories, and Pest proves inactive add-ons do not boot unsafe providers.

### Tests first (TDD)

- [X] T008 [P] [US1] Add `tests/Unit/Foundation/AddonProviderResolverTest.php` proving active providers are
  included, inactive providers are excluded, dependency-missing providers are excluded with reasons, and core
  providers remain included.
- [X] T009 [P] [US1] Extend `tests/Unit/Woo/WooKitTest.php` or add `tests/Unit/Woo/WooProviderGateTest.php`
  proving `Corex\Woo\WooServiceProvider` is excluded when WooCommerce is unavailable or the kit state is inactive.
- [X] T010 [P] [US1] Add `tests/Unit/Release/MetadataConsistencyCheckTest.php` for matching metadata, plugin
  header mismatch, `COREX_*_VERSION` mismatch, README/CHANGELOG/PROGRESS mismatch, and explicit policy exceptions.
- [X] T011 [P] [US1] Add `tests/Unit/Release/CiSecurityReadinessTest.php` proving CI/security findings distinguish
  repo-file controls from GitHub-settings-only controls and report missing CODEOWNERS/Dependabot/CodeQL coverage.

### Implementation

- [X] T012 [US1] Implement `Corex\Foundation\AddonProviderResolver` in
  `plugins/corex-core/src/Foundation/AddonProviderResolver.php` using provider metadata, activation state,
  dependency state, installed-file checks, and external gates.
- [X] T013 [US1] Update `plugins/corex-core/src/Boot.php` to pass core providers plus resolver-included optional
  providers into `Application`, preserving core self-boot on `plugins_loaded`.
- [X] T014 [US1] Adapt `plugins/corex-config/src/Addons/AddonRegistry.php` and
  `plugins/corex-config/src/Addons/AddonManager.php` to read or mirror the shared provider metadata without making
  `corex-config` the runtime authority.
- [X] T015 [US1] Wire Woo gating into provider resolution using `addons/corex-kit-woo/src/WooKitGate.php` so
  `Corex\Woo\WooServiceProvider` requires both WooCommerce availability and Corex activation state.
- [X] T016 [US1] Implement `Corex\Cli\Release\MetadataConsistencyCheck` in
  `packages/cli/src/Release/MetadataConsistencyCheck.php` and expose exact file/value mismatches per
  `contracts/metadata-consistency.md`.
- [X] T017 [US1] Implement `Corex\Cli\Release\CiSecurityReadiness` in
  `packages/cli/src/Release/CiSecurityReadiness.php` to inspect `.github/workflows/ci.yml`,
  `.github/workflows/e2e.yml`, `.github/workflows/docs.yml`, `.github/CODEOWNERS`, Dependabot, CodeQL,
  `SECURITY.md`, and `CONTRIBUTING.md`.
- [X] T018 [US1] Add or extend a WP-CLI command in `packages/cli/src/Commands/DoctorCommand.php` or a dedicated
  readiness command to output a readiness report covering runtime gating, Woo gating, metadata, CI/security,
  make:site, deployment, component coverage, Free/Core boundaries, and multi-agent safety.
- [X] T019 [US1] Update `docs/en/04-team-workflow/quality-gates.md` and
  `docs-app/src/content/docs/guides/deployment.md` with the readiness command, metadata check, and environment-gated
  reporting rules.
- [X] T020 [US1] Guard Gate for US1: run `composer test`, `clean-code-guard`, `wp-guard`, `test-guard`, and
  `docs-guard`; fix all findings and record exact results in `PROGRESS.md`.

**Checkpoint**: US1 is independently shippable as the MVP readiness gate.

---

## Phase 4: User Story 2 - Make multi-agent work safe and auditable (P2)

**Goal**: A new agent can read the repo, identify current work ownership, avoid `main`, avoid overlapping edits,
and produce a final report with exact verification/guard evidence.

**Independent Test**: Starting from the entry files, a new agent can identify the active spec/branch, ownership,
required checks, handoff format, and guard expectations without chat context.

### Tests first (TDD)

- [X] T021 [P] [US2] Add `tests/Unit/Release/AgentWorkUnitTest.php` proving branch, spec path, task IDs, files
  owned, verification, guards, and status are required for completed work units.
- [X] T022 [P] [US2] Add `tests/Unit/Release/MultiAgentReadinessTest.php` proving `main` is rejected as a work
  branch, overlapping file ownership is reported, and missing guard evidence blocks completion.

### Implementation

- [X] T023 [US2] Implement `Corex\Cli\Release\AgentWorkUnit` and `MultiAgentReadinessCheck` in
  `packages/cli/src/Release/AgentWorkUnit.php` and `packages/cli/src/Release/MultiAgentReadinessCheck.php`.
- [X] T024 [US2] Add `.github/CODEOWNERS` with maintainership for core plugins, add-ons, CLI, docs, specs, and
  workflows.
- [X] T025 [US2] Update `AGENTS.md`, `CLAUDE.md`, and `COREX-WORKING-GUIDE.md` with branch/spec ownership,
  git-status-first, no-main-work, no-overlap, handoff, verification, guard, and final-report requirements.
- [X] T026 [US2] Update `docs/en/04-team-workflow/branching-and-commits.md`,
  `docs/en/04-team-workflow/spec-kit.md`, and `docs/en/04-team-workflow/onboarding.md` with the multi-agent
  workflow and example handoff.
- [X] T027 [US2] Guard Gate for US2: run `composer test`, `clean-code-guard`, `test-guard`, and `docs-guard`;
  fix findings and record exact results in `PROGRESS.md`.

**Checkpoint**: Multi-agent work has explicit, auditable repo-owned rules.

---

## Phase 5: User Story 3 - Validate client-site generation and deployment readiness (P3)

**Goal**: Generated client sites are isolated from Corex framework folders and deployment profiles are documented
or validated with blockers.

**Independent Test**: make:site validation proves generated client plugin/theme/governance/token/spec structure and
framework-folder protection; deployment profiles show pass/warning/gated status.

### Tests first (TDD)

- [X] T028 [P] [US3] Add `tests/Unit/Cli/SiteScaffoldValidationTest.php` proving minimal and starter scaffolds
  include isolated plugin/theme folders, namespace/prefix placeholders, governance files, `specs/`, and token
  strategy.
- [X] T029 [P] [US3] Add `tests/Unit/Release/ClientBrandingComplianceTest.php` proving client-specific edits under
  `plugins/corex-*`, `addons/corex-*`, `packages/`, or the Corex theme are flagged.
- [X] T030 [P] [US3] Add `tests/Unit/Release/DeploymentProfileTest.php` proving required profiles exist and each
  profile records package shape, build commands, dependencies, secrets, and blockers.

### Implementation

- [X] T031 [US3] Implement `Corex\Cli\Site\SiteScaffoldValidator` in
  `packages/cli/src/Site/SiteScaffoldValidator.php` against `contracts/make-site-validation.md`.
- [X] T032 [US3] Extend `packages/cli/src/Release/ComplianceCheck.php` or add
  `packages/cli/src/Release/ClientBrandingComplianceCheck.php` to flag client branding edits in Corex framework
  folders.
- [X] T033 [US3] Implement `Corex\Cli\Release\DeploymentProfile` and `DeploymentReadinessCheck` in
  `packages/cli/src/Release/DeploymentProfile.php` and `packages/cli/src/Release/DeploymentReadinessCheck.php`.
- [X] T034 [US3] Wire make:site validation into `packages/cli/src/Commands/MakeCommand.php` or the readiness
  command so a maintainer can validate minimal and starter scaffolds without manually inspecting every file.
- [X] T035 [US3] Add deployment profile docs to `docs/en/05-deployment/index.md`,
  `docs/en/05-deployment/docker.md`, `docs/en/05-deployment/cpanel-shared-hosting.md`,
  `docs/en/05-deployment/azure-vm.md`, and `docs/en/05-deployment/azure-app-service.md`.
- [X] T036 [US3] Update `docs-app/src/content/docs/guides/client-site.md` and
  `docs-app/src/content/docs/guides/deployment.md` with make:site validation and deployment profiles.
- [X] T037 [US3] Guard Gate for US3: run `composer test`, `clean-code-guard`, `wp-guard`, `test-guard`, and
  `docs-guard`; fix findings and record exact results in `PROGRESS.md`.

**Checkpoint**: Client-site generation and deployment readiness are independently verifiable.

---

## Phase 6: User Story 4 - Scope native-first UI readiness without redesigning Corex (P4)

**Goal**: The minimum company-site UI/content needs are classified by native Corex/WordPress mechanism, with gaps
or deferred items explicit and no full visual redesign.

**Independent Test**: A reviewer can inspect the matrix and confirm every need has a mechanism, accessibility
expectation, token strategy, RTL strategy, and Free/Core or Pro/deferred classification.

### Tests first (TDD)

- [X] T038 [P] [US4] Add `tests/Unit/Release/ComponentCoverageMatrixTest.php` proving required company-site needs
  are classified and no item has an unknown mechanism.
- [X] T039 [P] [US4] Add `tests/Unit/Release/NativeFirstUiReadinessTest.php` proving core block/style/pattern
  mechanisms are preferred over new custom block scope and final visual redesign is absent.

### Implementation

- [X] T040 [US4] Implement `Corex\Cli\Release\ComponentCoverageItem` and `ComponentCoverageMatrix` in
  `packages/cli/src/Release/ComponentCoverageItem.php` and `packages/cli/src/Release/ComponentCoverageMatrix.php`.
- [X] T041 [US4] Seed the matrix from existing Corex DLS and company-site needs in
  `packages/cli/src/Release/ComponentCoverageDefaults.php`, covering home, about, services, contact, careers,
  portfolio, forms, listings, cards, testimonials, CTAs, media, navigation, and page templates.
- [X] T042 [US4] Add `docs-app/src/content/docs/design-system/client-readiness.md` with the component coverage
  matrix and links to existing DLS component/pattern/template pages.
- [X] T043 [US4] Update `docs-app/src/content/docs/design-system/index.md` and
  `docs-app/src/content/docs/guides/design-system.md` to link the client-readiness matrix.
- [X] T044 [US4] Guard Gate for US4: run `composer test`, `clean-code-guard`, `test-guard`, and `docs-guard`;
  fix findings and record exact results in `PROGRESS.md`.

**Checkpoint**: UI readiness is scoped, native-first, and not a visual redesign.

---

## Phase 7: User Story 5 - Keep Free/Core and Pro boundaries clear (P5)

**Goal**: Adoption/security basics remain Free/Core, while advanced commercial capabilities are marked Pro
candidates without blocking client-site readiness.

**Independent Test**: Boundary tests and docs prove security-critical basics cannot be classified Pro-only.

### Tests first (TDD)

- [X] T045 [P] [US5] Add `tests/Unit/Release/FreeProBoundaryTest.php` proving security-critical basics cannot be
  `pro-candidate`, required Free/Core items exist, and advanced items can be Pro candidates.

### Implementation

- [X] T046 [US5] Implement `Corex\Cli\Release\FreeProBoundaryItem` and `FreeProBoundaryMatrix` in
  `packages/cli/src/Release/FreeProBoundaryItem.php` and `packages/cli/src/Release/FreeProBoundaryMatrix.php`.
- [X] T047 [US5] Seed Free/Core and Pro candidate defaults in
  `packages/cli/src/Release/FreeProBoundaryDefaults.php` from FR-017 and FR-018.
- [X] T048 [US5] Add `docs/en/06-cookbooks/free-core-vs-pro-boundaries.md` and
  `docs-app/src/content/docs/guides/free-core-vs-pro.md` documenting Free/Core basics, Pro candidates, deferred
  items, and out-of-scope items.
- [X] T049 [US5] Integrate Free/Core boundary findings into the readiness command/report from US1.
- [X] T050 [US5] Guard Gate for US5: run `composer test`, `clean-code-guard`, `test-guard`, and `docs-guard`;
  fix findings and record exact results in `PROGRESS.md`.

**Checkpoint**: Product boundaries are explicit and protect trust basics.

---

## Phase 8: Polish & Cross-Cutting

**Purpose**: Full-suite verification, environment-gated reporting, and durable handoff.

- [X] T051 [P] Update `README.md`, `CHANGELOG.md`, `PROGRESS.md`, and `DECISIONS.md` for the completed spec 055
  scope and exact readiness status.
- [X] T052 [P] Update `.github/dependabot.yml` and `.github/workflows/codeql.yml` if US1 found them missing and
  repo-file controls are appropriate; otherwise document GitHub-settings-only blockers in `PROGRESS.md`.
- [X] T053 Run full headless verification: `composer validate --no-check-publish`, PHP lint over
  `plugins/`, `packages/`, and `addons/`, `composer test`, `npm run build`, `npm run test:js`, and
  `Push-Location docs-app; npm run build; Pop-Location`; record exact results in `PROGRESS.md`.
- [X] T054 Run or record environment-gated browser verification: `npm run env:start`, `npm run test:e2e`,
  `npm run env:stop`; if unavailable, record the exact Docker/browser/Apache blocker in `PROGRESS.md`.
- [X] T055 Run final Guard Gate for the whole diff: `clean-code-guard`, `wp-guard`, `test-guard`, and
  `docs-guard`; fix findings before presenting, committing, or merging.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (T001-T003)**: no dependencies.
- **Foundational (T004-T007)**: depends on Setup; blocks user stories.
- **US1 (T008-T020)**: depends on Foundational and is the MVP.
- **US2 (T021-T027)**: depends on Foundational; can run after or beside US1 if file ownership is coordinated, but
  recommended after US1 because it reuses readiness report concepts.
- **US3 (T028-T037)**: depends on Foundational; recommended after US1 so the readiness command/report exists.
- **US4 (T038-T044)**: depends on Foundational; independent of runtime gating but benefits from readiness report
  integration.
- **US5 (T045-T050)**: depends on Foundational; should complete before final readiness report closure.
- **Polish (T051-T055)**: depends on all selected stories.

### User Story Dependencies

- **US1**: no dependency on other stories; recommended MVP.
- **US2**: independent policy/workflow story; integrates with readiness reporting from US1.
- **US3**: independent validation/deployment story; integrates with readiness reporting from US1.
- **US4**: independent matrix story; no implementation of visual redesign.
- **US5**: independent boundary story; integrates with US4 and readiness reporting.

### Within Each User Story

- Tests first and must fail before implementation.
- Pure models/value objects before service/check classes.
- Service/check classes before CLI/report integration.
- Docs and guard gate at the end of each story.

---

## Parallel Opportunities

- Setup: T002 and T003 can run in parallel.
- Foundation: T005, T006, and T007 can run in parallel after T004 shape is known.
- US1: T008-T011 can run in parallel; T016 and T017 can run in parallel after tests exist.
- US2: T021 and T022 can run in parallel; T024 and T026 can run in parallel after T023.
- US3: T028-T030 can run in parallel; T035 and T036 can run in parallel after T033.
- US4: T038 and T039 can run in parallel; T042 and T043 can run in parallel after T041.
- US5: T045 can run while docs outline for T048 is drafted, but T048 must align with T046-T047.

### Parallel Example: US1

```text
Task: "Add tests/Unit/Foundation/AddonProviderResolverTest.php"
Task: "Add tests/Unit/Woo/WooProviderGateTest.php"
Task: "Add tests/Unit/Release/MetadataConsistencyCheckTest.php"
Task: "Add tests/Unit/Release/CiSecurityReadinessTest.php"
```

### Parallel Example: US3

```text
Task: "Add tests/Unit/Cli/SiteScaffoldValidationTest.php"
Task: "Add tests/Unit/Release/ClientBrandingComplianceTest.php"
Task: "Add tests/Unit/Release/DeploymentProfileTest.php"
```

---

## Implementation Strategy

### MVP First (US1 Only)

1. Complete Phase 1 setup.
2. Complete Phase 2 foundation.
3. Complete US1 runtime/metadata/CI readiness.
4. Stop and validate US1 independently with Pest, readiness report output, and Guard Gate.

### Incremental Delivery

1. Ship US1 as the readiness MVP.
2. Add US2 for safe multi-agent workflow.
3. Add US3 for client-site scaffold and deployment readiness.
4. Add US4 for native-first component coverage.
5. Add US5 for Free/Core vs Pro boundaries.
6. Finish Phase 8 full verification and environment-gated reporting.

### Parallel Team Strategy

After Phase 2, assign one branch/task owner per story. Agents must record claimed task IDs, expected file surfaces,
verification, and guard evidence in `PROGRESS.md` before handoff.

---

## Format Validation

All tasks use `- [ ] T### [P?] [US?] description with file path`; Setup/Foundational/Polish carry no story label;
US1-US5 tasks carry story labels; test tasks precede implementation tasks; every implementation task names exact
file paths.
