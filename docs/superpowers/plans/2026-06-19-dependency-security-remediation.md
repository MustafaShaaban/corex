# Dependency Security Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fail-closed, exposure-aware dependency-security gate for all Corex lockfiles and resolve the incompatible Pest 4 bot pull request safely.

**Architecture:** A pure Node policy module normalizes audit payloads and validates exact advisory exceptions. A thin CLI runs the three ecosystem audits and maps unavailable services to exit code 2. A focused workflow runs on dependency changes and weekly; tests use fixtures and never call registries.

**Tech Stack:** Node.js 20 standard library, Jest via `@wordpress/scripts`, npm audit JSON, Composer audit JSON, GitHub Actions, JSON policy, Markdown governance.

---

### Task 1: Establish deterministic policy tests

**Files:**
- Create: `tests/Fixtures/dependency-security/npm-audit.json`
- Create: `tests/Fixtures/dependency-security/composer-audit.json`
- Create: `tests/dependency-security-policy.test.js`

- [ ] Add fixtures containing one accepted development-only advisory, one forbidden runtime advisory, and one Composer advisory.
- [ ] Add Jest cases proving unknown, expired, stale, severity-mismatched, and runtime/CI-high exceptions fail.
- [ ] Run `npm.cmd run test:js -- --runTestsByPath tests/dependency-security-policy.test.js` and confirm RED because the policy module does not exist.

### Task 2: Implement the pure evaluator

**Files:**
- Create: `scripts/dependency-security-policy.mjs`

- [ ] Export `normalizeNpmAudit`, `normalizeComposerAudit`, and `evaluatePolicy`.
- [ ] Normalize advisory identity, package, severity, paths, and ecosystem without reading files or spawning commands.
- [ ] Validate exact advisory keys, required exception metadata, severity ceilings, exposure restrictions, review dates, and stale entries.
- [ ] Run the focused Jest suite and confirm GREEN.

### Task 3: Implement the audit command

**Files:**
- Create: `scripts/verify-dependency-security.mjs`
- Modify: `package.json`

- [ ] Spawn root npm audit, docs npm audit, and Composer audit with JSON output and explicit working directories.
- [ ] Distinguish advisory exit code 1 from command/service failure; return 0 for a valid policy, 1 for violations, and 2 for unavailable evidence.
- [ ] Add `verify:dependencies` to `package.json`.
- [ ] Exercise the command against live registries and record exact results.

### Task 4: Record bounded current exceptions

**Files:**
- Create: `.github/dependency-security-policy.json`
- Modify: `SECURITY.md`
- Modify: `CONTRIBUTING.md`

- [ ] Classify every current npm and Composer advisory from live JSON evidence.
- [ ] Remediate compatible lockfile resolutions; do not use forced downgrade suggestions.
- [ ] Add only non-runtime/non-CI exceptions with owner, control, review date, and upstream trigger.
- [ ] Document the verifier, exit codes, and local-development server network boundary.

### Task 5: Enforce the policy in GitHub Actions

**Files:**
- Create: `.github/workflows/dependency-security.yml`

- [ ] Run on weekly schedule, manual dispatch, and pull requests changing manifests, lockfiles, policy, verifier, or workflow.
- [ ] Install Node, PHP, npm, and Composer dependencies from lockfiles.
- [ ] Run `npm run verify:dependencies` and retain fail-closed exit semantics.

### Task 6: Resolve the Pest 4 bot PR

**External state:** GitHub PR #35

- [ ] Comment with the observed 20 risky tests, the no-global-suppression decision, and the Spec 056 tracking path.
- [ ] Close PR #35 unmerged so it cannot be mistaken for a routine safe update.
- [ ] Record the future migration trigger in `DECISIONS.md`.

### Task 7: Verify and close the feature

**Files:**
- Modify: `PROGRESS.md`
- Modify: `DECISIONS.md`
- Modify: `specs/056-dependency-security-remediation/tasks.md`

- [ ] Run the dependency verifier, focused Jest, full Jest, npm build, Composer validation, full Pest, docs build, readiness command, and `git diff --check`.
- [ ] Run `clean-code-guard`, `test-guard`, and `docs-guard`; run `wp-guard` only if WordPress runtime code changes.
- [ ] Record environment-gated Docker/browser checks honestly.
- [ ] Update task checkboxes and durable handoff with branch, spec, owned files, commands, results, guard status, and next action.
