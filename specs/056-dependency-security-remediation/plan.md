# Implementation Plan: Dependency Security Remediation

**Branch**: `feature/056-dependency-security-remediation` | **Date**: 2026-06-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/056-dependency-security-remediation/spec.md`

## Summary

Create a repository-owned dependency-security gate that audits Composer, root npm, and docs-app npm lockfiles, classifies each advisory by Corex exposure, rejects unbounded or expired exceptions, and blocks high/critical runtime or CI findings. Keep raw upstream audit results visible while allowing only documented development-tool exceptions. Treat the open Pest 4 pull request as a separate compatibility migration and close it with a link to this tracked work rather than weakening test output detection.

## Technical Context

**Language/Version**: Node.js 20+ for the cross-platform audit policy verifier; JSON and Markdown for policy/evidence; YAML for GitHub Actions; PHP 8.3+ and Pest remain verification dependencies.

**Primary Dependencies**: npm audit v2 JSON, Composer audit JSON, Node standard library only, existing npm workspaces, `@wordpress/scripts`, Astro/Starlight, Pest, and GitHub Actions.

**Storage**: Repository files only: lockfiles, a reviewed exception policy, generated command output, specs, progress, and decisions. No runtime database state.

**Testing**: Jest unit tests for normalization/policy evaluation; fixture audit payloads; live Composer/npm audit commands; existing Pest, JavaScript, build, docs, readiness, and environment-gated E2E checks.

**Target Platform**: Windows and Linux contributor environments plus GitHub Actions; WordPress 7.0+ remains the product target but receives no runtime behavior change.

**Project Type**: WordPress framework monorepo with PHP, npm workspaces, documentation app, and repository automation.

**Performance Goals**: Policy evaluation completes in under one second after audit payloads are available; live verification adds only the package-registry response time.

**Constraints**: No forced audit downgrade, no hidden advisories, no product feature or WordPress runtime change, no exception for high/critical runtime or CI exposure, and audit service failures must fail closed with an explicit unavailable result.

**Scale/Scope**: Three lockfiles, the current advisory set, one policy file, one verifier entry point, one CI workflow, one Jest suite, and the Pest 4 pull-request disposition.

## Constitution Check

*GATE: Passed before Phase 0 and re-checked after Phase 1 design against Corex Constitution v1.2.1.*

- [x] **I. Theme is a skin** - PASS/N/A. No theme files or runtime responsibilities change.
- [x] **II. Plugins boot themselves** - PASS/N/A. Plugin boot behavior is unchanged and remains verified by the existing suite.
- [x] **III. Thin controllers, fat services** - PASS/N/A. No controller, service, repository, or model layer changes.
- [x] **IV. Everything injected** - PASS/N/A. The verifier is repository tooling with no WordPress runtime dependencies.
- [x] **V. Runtime tokens** - PASS/N/A. No styling changes.
- [x] **VI. Conditional assets** - PASS/N/A. No shipped assets change.
- [x] **VII. Declarative security** - PASS. The work strengthens repository security controls and does not add routes or admin screens.
- [x] **VIII. RTL-first** - PASS/N/A. No UI changes.
- [x] **IX. No optional dep is hard** - PASS. No runtime dependency is added; the verifier uses Node standard-library APIs.
- [x] **X. Spec is source of truth** - PASS. Spec 056 and its approved design precede implementation.
- [x] **Guard Gate + Definition of Done** acknowledged. Tests, docs, progress, decisions, and all applicable guards are required before delivery.

## Project Structure

### Documentation (this feature)

```text
specs/056-dependency-security-remediation/
|-- spec.md
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- dependency-security-status.md
|-- checklists/
|   `-- requirements.md
`-- tasks.md
```

### Source Code (repository root)

```text
.github/
|-- dependency-security-policy.json
`-- workflows/dependency-security.yml
scripts/
|-- dependency-security-policy.mjs
`-- verify-dependency-security.mjs
tests/
|-- dependency-security-policy.test.js
`-- fixtures/dependency-security/
    |-- npm-audit.json
    `-- composer-audit.json
package.json
SECURITY.md
CONTRIBUTING.md
PROGRESS.md
DECISIONS.md
```

**Structure Decision**: Keep policy evaluation in a pure, importable Node module and process execution in a thin CLI entry point. Tests use small fixtures rather than live registries. CI and maintainers call one root npm script; the policy remains human-reviewable JSON under `.github/`.

## Complexity Tracking

No constitution violations or additional architectural complexity are required.
