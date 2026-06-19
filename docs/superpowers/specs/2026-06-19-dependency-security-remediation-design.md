# Dependency Security Remediation Design

## Decision

Corex will remediate dependency advisories by exposure, not by blindly applying every forced upgrade. Shipped and CI-reachable risks are handled first. Local development-only and transitive toolchain findings may be deferred only when their exposure is bounded, the upstream constraint is recorded, and the repository has a concrete follow-up.

The open Pest 2 to Pest 4 pull request is a separate compatibility migration. It must not be merged while CI reports risky tests, even though assertions pass.

## Classification model

Each advisory is classified as one of:

- shipped runtime: code or assets delivered to WordPress or documentation users;
- CI: code executed with repository or workflow privileges;
- local development server: tooling reachable only while a maintainer runs a local server;
- build/test transitive: tooling used during linting, testing, or packaging without shipped output;
- false positive or unreachable: a dependency path that cannot exercise the vulnerable behavior in Corex.

Every deferred finding records severity, affected path, exposure boundary, upstream constraint, compensating control, and the condition that ends the deferral.

## Remediation flow

1. Capture reproducible Composer, root npm, and docs-app npm audit baselines.
2. Apply non-breaking lockfile resolutions where supported by direct dependency constraints.
3. Upgrade direct dependencies only when their supported release line removes the affected path and the full project gates remain green.
4. Refuse forced downgrade suggestions from automated audit tooling.
5. Separate incompatible major migrations, including Pest 4, into explicit tasks with tests and migration evidence.
6. Add an auditable policy so future findings cannot be silently accepted or hidden.

## Verification

The work is complete only when runtime and CI-reachable high or critical findings are zero, remaining findings have bounded documented exceptions, Composer and both npm lockfiles are audited, existing builds/tests pass, and the relevant clean-code, WordPress, test, and documentation guards report no blocking findings.

## Scope boundaries

This work does not add product features, alter WordPress runtime architecture, or use `npm audit fix --force`. It may update direct development dependencies, lockfiles, CI policy, tests affected by supported dependency behavior, and release/security documentation.
