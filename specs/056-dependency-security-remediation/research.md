# Research: Dependency Security Remediation

## Exposure-first triage

**Decision**: Treat raw audit output as evidence, then classify findings as shipped runtime, CI, local development server, build/test transitive, or unreachable.

**Rationale**: The root npm lockfile contains development tooling only, while the docs app produces a shipped static site. Raw counts therefore do not describe equivalent exposure. A reviewed classifier preserves visibility without applying unsafe forced updates.

**Alternatives considered**: `npm audit fix --force` was rejected because current suggestions include unrelated breaking downgrades. Ignoring development dependencies was rejected because CI and local servers remain part of the repository trust boundary.

## Current baseline

**Decision**: Use the post-Dependabot `main` state at `48ee6fe` as the initial baseline.

**Rationale**: PRs #36 through #45 were merged with green required checks. Root `npm audit --package-lock-only --audit-level=low` reports 50 findings (1 low, 45 moderate, 4 high). Docs-app reports 4 low findings through Astro's esbuild path. Composer audit was attempted but Packagist timed out, so it is unavailable evidence rather than a clean result.

**Alternatives considered**: Pre-merge lockfiles were rejected because they no longer represent the repository. Local `node_modules` trees were rejected until reinstalled because they still contain pre-merge direct versions.

## Exception policy

**Decision**: Store explicit exceptions keyed by advisory identifier and ecosystem, with exposure, severity ceiling, affected path, reason, compensating control, owner, review date, and upstream trigger.

**Rationale**: Exact keys make unexpected advisories fail closed. Review dates prevent indefinite acceptance. A severity ceiling prevents a changed advisory from silently remaining accepted.

**Alternatives considered**: Package-name allowlists were rejected because one package may have multiple advisories with different impact. Count-based thresholds were rejected because an advisory can disappear while a new one replaces it.

## Verification interface

**Decision**: Implement a Node standard-library verifier that normalizes npm and Composer JSON, evaluates the policy, and emits a stable JSON summary plus concise console output.

**Rationale**: Node is already required by the repository, works on Windows and Linux, and avoids adding another security-sensitive dependency. Separating the pure evaluator from process execution keeps tests deterministic.

**Alternatives considered**: Shell-only commands cannot validate exception metadata portably. Adding a third-party audit wrapper would enlarge the dependency surface being audited.

## CI behavior

**Decision**: Run the verifier on pull requests that change lockfiles, manifests, the policy, or verifier files, and on a weekly schedule. Audit-service unavailability fails the job with an explicit unavailable classification.

**Rationale**: Dependency changes receive immediate review while the schedule catches newly published advisories. Failing closed avoids reporting a network timeout as security success.

**Alternatives considered**: Running on every source-only pull request adds registry latency without changing dependency evidence. Warning-only service failures were rejected because they create false-green security status.

## Pest 4 disposition

**Decision**: Do not merge PR #35. Close it with a comment linking to Spec 056 and retain Pest 2 until a planned compatibility task captures intentional log output without suppressing unexpected output globally.

**Rationale**: PR #35 crosses two major versions. Its required CI check exits 1 with 20 risky tests even though 600 tests and 2239 assertions pass. Disabling output detection globally would conceal real regressions.

**Alternatives considered**: Merging on assertion count was rejected because required CI is red. Global output suppression was rejected because it weakens the suite.
