# Feature Specification: Dependency Security Remediation

**Feature Branch**: `feature/056-dependency-security-remediation`

**Created**: 2026-06-19

**Status**: Draft

**Input**: Continue from the stable-client readiness handoff by triaging merged Dependabot updates, remediating dependency security exposure, and separating incompatible major toolchain migrations.

## User Scenarios & Testing

### User Story 1 - Remove actionable dependency exposure (Priority: P1)

As a maintainer, I need dependency findings classified by actual Corex exposure and actionable runtime or CI risks remediated, so a passing update queue does not conceal security debt.

**Why this priority**: Runtime and CI exposure can affect shipped sites, release integrity, or repository trust and therefore takes precedence over development-only noise.

**Independent Test**: From a clean checkout, a maintainer can run the documented audits and verify that no unresolved high or critical finding is reachable in shipped runtime or CI paths.

**Acceptance Scenarios**:

1. **Given** a dependency advisory, **When** it is triaged, **Then** its severity, dependency path, exposure class, remediation state, and evidence are recorded.
2. **Given** a high or critical runtime/CI-reachable advisory with a compatible fix, **When** remediation completes, **Then** the affected audit no longer reports it and existing quality gates remain green.
3. **Given** an automated fix proposes a forced downgrade or unrelated breaking change, **When** it is evaluated, **Then** it is rejected unless a reviewed migration proves compatibility.

---

### User Story 2 - Bound development-tool exceptions (Priority: P2)

As a maintainer, I need development-only findings to be either fixed or explicitly bounded, so accepted risk is visible, time-limited, and distinguishable from shipped exposure.

**Why this priority**: Tooling findings still matter, but treating every transitive development advisory as production exposure encourages unsafe forced updates and obscures meaningful risk.

**Independent Test**: A reviewer can trace every remaining audit finding to an exception that identifies its exposure boundary, upstream constraint, compensating control, and re-evaluation trigger.

**Acceptance Scenarios**:

1. **Given** a finding reachable only through a local development server, **When** no compatible upstream fix exists, **Then** the exception states that boundary and prohibits untrusted network exposure.
2. **Given** a deferred finding becomes fixable within supported direct dependency ranges, **When** audits are rerun, **Then** the deferral fails validation until the fix is applied or re-reviewed.

---

### User Story 3 - Isolate major test-runner migration (Priority: P3)

As a maintainer, I need the Pest major upgrade evaluated as a compatibility migration rather than auto-merged, so stricter output handling does not make Corex CI unreliable.

**Why this priority**: The current bot pull request crosses two major versions and changes test semantics. It is not a routine security patch.

**Independent Test**: The Pest upgrade has a documented disposition, and no pull request is mergeable while the required test check exits nonzero or reports unreviewed risky tests.

**Acceptance Scenarios**:

1. **Given** the existing Pest 4 pull request reports passing assertions but risky tests, **When** it is triaged, **Then** it remains unmerged and links to the planned compatibility work.
2. **Given** a future Pest migration, **When** it is proposed, **Then** intentional logging is captured or asserted without suppressing unrelated unexpected output.

### Edge Cases

- An advisory affects a package present in a lockfile but absent from shipped artifacts.
- The advisory service is temporarily unavailable or times out.
- A transitive fix exists only through a direct dependency downgrade or unsupported major version.
- Audit counts change between runs because an advisory is published, withdrawn, or reclassified.
- A dependency update changes generated lockfile content across multiple workspaces.
- A test intentionally writes to stderr while the runner treats all unexpected output as risky.

## Requirements

### Functional Requirements

- **FR-001**: The remediation record MUST cover the Composer lockfile, root npm lockfile, and docs-app npm lockfile.
- **FR-002**: Every reported advisory MUST be classified as shipped runtime, CI, local development server, build/test transitive, or unreachable.
- **FR-003**: Every advisory record MUST include severity, affected dependency path, exposure evidence, disposition, and verification evidence.
- **FR-004**: All high and critical findings reachable through shipped runtime or CI MUST be remediated before completion.
- **FR-005**: Compatible non-breaking remediations MUST be preferred over forced or unrelated breaking changes.
- **FR-006**: Automated forced downgrade recommendations MUST NOT be applied without a reviewed compatibility migration.
- **FR-007**: A deferred finding MUST record an exposure boundary, upstream constraint, compensating control, owner, and re-evaluation trigger.
- **FR-008**: Development-server exceptions MUST state that the affected server is not exposed to untrusted networks.
- **FR-009**: Audit unavailability MUST be reported as environment-gated or blocked evidence and MUST NOT be represented as a clean result.
- **FR-010**: The Pest 4 update MUST be treated as a major compatibility migration and MUST NOT merge while required CI exits nonzero or reports unreviewed risky tests.
- **FR-011**: Test-runner migration work MUST preserve detection of unexpected output while providing explicit handling for expected log output.
- **FR-012**: Dependency and workflow changes MUST preserve existing build, test, lint, documentation, and readiness behavior.
- **FR-013**: The repository MUST provide a repeatable verification path that distinguishes clean audits, bounded exceptions, and unavailable audit services.
- **FR-014**: Security documentation and durable progress MUST state current audit results without flattening development-only findings into production claims.
- **FR-015**: The work MUST NOT add product features or change the Corex WordPress runtime architecture.

### Key Entities

- **Dependency Finding**: An advisory with severity, affected package path, exposure class, disposition, and evidence.
- **Risk Exception**: A bounded deferral with an upstream constraint, compensating control, owner, and expiry trigger.
- **Audit Snapshot**: A dated result for one dependency ecosystem that records success, findings, or service unavailability.
- **Compatibility Migration**: A major dependency update whose changed behavior requires explicit tests and review before merging.

## Success Criteria

### Measurable Outcomes

- **SC-001**: One hundred percent of findings from all three dependency lockfiles have an exposure classification and disposition.
- **SC-002**: Zero unresolved high or critical findings are reachable through shipped runtime or CI paths.
- **SC-003**: One hundred percent of deferred findings include all required exception fields and a re-evaluation trigger.
- **SC-004**: No required build, test, lint, documentation, or readiness check regresses after remediation.
- **SC-005**: No major dependency pull request is marked ready while its required checks fail or its changed behavior lacks explicit acceptance evidence.
- **SC-006**: A maintainer can reproduce the dependency-security status using documented commands without manually interpreting raw lockfiles.

## Assumptions

- Dependency audits include development dependencies because CI and local tooling are part of the repository trust boundary.
- Shipped WordPress packages do not include root development dependencies unless packaging evidence proves otherwise.
- Findings with no compatible upstream remediation may be deferred only under the bounded exception requirements.
- Pest 2 remains supported temporarily while Pest 4 compatibility is handled explicitly.
- GitHub and package advisory services may be intermittently unavailable; local evidence must record that state honestly.
