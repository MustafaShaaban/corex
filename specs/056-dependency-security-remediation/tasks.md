# Tasks: Dependency Security Remediation

**Input**: Design documents from `specs/056-dependency-security-remediation/`

**Tests**: Required by the Corex constitution and Spec 056. Test tasks precede implementation tasks.

## Phase 1: Setup

**Purpose**: Establish current environment and reproducible audit evidence.

- [ ] T001 Confirm `feature/056-dependency-security-remediation`, working-tree state, spec pointer, and WordPress environment gate; record exact evidence in `PROGRESS.md`
- [X] T002 [P] Reinstall root dependencies from `package-lock.json` and capture root `npm audit --package-lock-only --json` evidence without changing manifests
- [X] T003 [P] Reinstall docs dependencies from `docs-app/package-lock.json` and capture docs `npm audit --package-lock-only --json` evidence without changing manifests
- [X] T004 [P] Run `composer audit --locked --format=json`; record findings or exact advisory-service unavailability in `PROGRESS.md`

---

## Phase 2: Foundational

**Purpose**: Define the repository-owned policy contract used by all user stories.

- [ ] T005 [P] Create representative npm audit fixture in `tests/fixtures/dependency-security/npm-audit.json`
- [ ] T006 [P] Create representative Composer audit fixture in `tests/fixtures/dependency-security/composer-audit.json`
- [ ] T007 Add RED Jest coverage for normalization, exact advisory identity, and deterministic finding order in `tests/dependency-security-policy.test.js`
- [ ] T008 Implement audit normalization in `scripts/dependency-security-policy.mjs` and make T007 GREEN

**Checkpoint**: Audit payloads have a stable internal shape independent of registry output ordering.

---

## Phase 3: User Story 1 - Remove actionable dependency exposure (Priority: P1) MVP

**Goal**: Block unbounded findings and all high/critical shipped-runtime or CI exposure.

**Independent Test**: Fixture-driven evaluation rejects unknown and forbidden findings, and live verification accounts for all findings from all three lockfiles.

- [ ] T009 [US1] Add RED Jest cases for unknown findings, package/severity mismatch, forbidden high runtime/CI exceptions, and clean audit payloads in `tests/dependency-security-policy.test.js`
- [ ] T010 [US1] Implement policy evaluation and severity/exposure validation in `scripts/dependency-security-policy.mjs` and make T009 GREEN
- [ ] T011 [US1] Implement the thin audit runner with exit codes 0/1/2 and `--json` output in `scripts/verify-dependency-security.mjs`
- [ ] T012 [US1] Add `verify:dependencies` to `package.json` and prove live root, docs-app, and Composer audits are all invoked
- [ ] T013 [US1] Apply only compatible dependency/lockfile remediations supported by current direct dependency ranges in `package.json`, `package-lock.json`, `docs-app/package.json`, and `docs-app/package-lock.json`

**Checkpoint**: Runtime/CI high and critical exposure cannot pass the repository verifier.

---

## Phase 4: User Story 2 - Bound development-tool exceptions (Priority: P2)

**Goal**: Make every remaining development-only finding explicit, complete, and time-bounded.

**Independent Test**: Missing, expired, stale, or incomplete exception entries fail fixture-driven validation and the live policy contains a matching disposition for every remaining advisory.

- [ ] T014 [US2] Add RED Jest cases for missing fields, expired review dates, stale advisory entries, changed severity, and changed package identity in `tests/dependency-security-policy.test.js`
- [ ] T015 [US2] Implement exception metadata, expiry, and stale-entry validation in `scripts/dependency-security-policy.mjs` and make T014 GREEN
- [ ] T016 [US2] Create the fully classified current exception set in `.github/dependency-security-policy.json` from live audit JSON and Spec 056 exposure rules
- [ ] T017 [P] [US2] Document audit scope, verifier exits, exception requirements, and the no-untrusted-network dev-server control in `SECURITY.md`
- [ ] T018 [P] [US2] Add maintainer remediation and review commands to `CONTRIBUTING.md`
- [ ] T019 [US2] Create `.github/workflows/dependency-security.yml` for weekly, manual, and dependency-change verification

**Checkpoint**: Every remaining advisory is visible and bounded; new or changed advisories fail closed.

---

## Phase 5: User Story 3 - Isolate major test-runner migration (Priority: P3)

**Goal**: Resolve the misleading open bot update without weakening unexpected-output detection.

**Independent Test**: PR #35 is closed unmerged with the CI evidence and Spec 056 migration rationale recorded durably.

- [ ] T020 [US3] Record the Pest 4 compatibility decision, expected-output requirement, and future migration trigger in `DECISIONS.md`
- [ ] T021 [US3] Comment on and close GitHub PR #35 unmerged with its 20-risky-test evidence and the Spec 056 tracking reference

**Checkpoint**: The Dependabot queue contains no red major-version PR presented as routine maintenance.

---

## Phase 6: Polish & Cross-Cutting

**Purpose**: Prove the complete policy, preserve existing behavior, and create a durable handoff.

- [ ] T022 Run focused and full verification: dependency-policy Jest, `npm.cmd run verify:dependencies`, `npm.cmd run test:js`, `npm.cmd run build`, Composer validation, `composer test`, docs-app build, readiness command, and `git diff --check`
- [ ] T023 Record Docker/wp-env and browser checks as passed or environment-gated with exact evidence in `PROGRESS.md`
- [ ] T024 Run `clean-code-guard`, `test-guard`, and `docs-guard`; run `wp-guard` only if WordPress runtime files changed; fix every blocking finding
- [ ] T025 Update `PROGRESS.md` with branch, spec, completed task IDs, owned/released files, audit counts, verification results, guard status, GitHub PR disposition, and the next action
- [ ] T026 Reconcile all Spec 056 requirements and success criteria against current files, command output, CI state, and GitHub state; leave any unproven task unchecked

---

## Dependencies & Execution Order

- Phase 1 establishes authoritative current audit evidence.
- Phase 2 blocks all stories because normalization is shared.
- US1 (T009-T013) is the MVP and must precede exceptions.
- US2 (T014-T019) depends on US1's evaluator and live audit paths.
- US3 (T020-T021) is externally independent after the design is committed, but follows policy work so its handoff can point to durable artifacts.
- Phase 6 depends on every selected story.

## Parallel Opportunities

- T002, T003, and T004 can run in parallel after T001.
- T005 and T006 can run in parallel.
- T017 and T018 can run in parallel after the policy shape is stable.

## Parallel Example: Audit Baseline

```text
Task: "Reinstall and audit the root npm lockfile"
Task: "Reinstall and audit the docs-app npm lockfile"
Task: "Audit the Composer lockfile"
```

## Implementation Strategy

### MVP First

Complete T001-T013. This produces a tested evaluator and a live command that refuses runtime/CI high or critical risk.

### Incremental Delivery

1. Normalize and evaluate audits with fixtures.
2. Add the live three-ecosystem command.
3. Remediate compatible findings.
4. Bound remaining development-only findings.
5. Add scheduled/PR enforcement.
6. Close the incompatible Pest bot PR and complete full guards.

## Format Validation

All 26 tasks use the required checkbox and sequential task ID format. User-story tasks carry `[US1]`, `[US2]`, or `[US3]`; parallel tasks use `[P]`; every task names a concrete file or verification surface.
