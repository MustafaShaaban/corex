# Implementation Plan: Team ops & distribution

**Branch**: `feature/050-team-ops-distribution` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

## Summary

Close the distribution loop + enforce the team boundary, reusing spec-034 (self-update mechanism + manifest),
spec-036 (version tooling), spec-049 (the client/framework boundary), and spec-028 (handbook). Two **pure cores** —
`ReleasePackagePlan` (which framework paths the release ZIP includes/excludes + the spec-034 manifest) and
`ComplianceCheck` (a changed-file list classified by **path prefix** into forbidden-framework vs allowed) — wrapped
by thin WP-CLI-gated commands (`package:update`, `compliance:check`, `docs:sync`/`docs:serve`/`docs:open`). Plus
documented Azure DevOps per-site repo + App Service deployment + branch policies. No secret in any package/manifest/
docs; the framework runs without WP-CLI.

## Technical Context

PHP 8.3; pure plan/classifier cores + thin gated commands. Reuses 034/036/049/028/003. No new dependency. Pest for
the cores; the ZIP write / git diff / docs copy are env-gated boundaries.

## Constitution Check

PASS — III (pure cores + thin gated boundary), VII (no secret in package/manifest/docs — FR-003/FR-009), IX (WP-CLI
gated, no hard dep), X (traces to spec 050; reuses 034/036/049/028). Guard Gate acknowledged (clean-code, wp-guard,
test-guard, docs-guard).

## Project Structure

```text
packages/cli/src/Release/
├── ReleasePackagePlan.php   # NEW — pure: includes(path) + manifest(version, downloadUrl, changelog) [spec-034 format]
└── ComplianceCheck.php      # NEW — pure: evaluate(changedFiles, forbiddenPrefixes, allowFramework) -> {passed, violations}
packages/cli/src/Commands/   # CHANGE — package:update + compliance:check + docs:{sync,serve,open} (WP-CLI-gated)
docs-app/.../guides/deployment.md  # NEW — Azure DevOps per-site repo + App Service + branch policies
tests/Unit/Release/ (Pest)         # NEW — ReleasePackagePlan, ComplianceCheck
```

**Structure Decision**: spec-003 pure-core + gated-CLI pattern. The package plan + compliance classifier are pure
(headless-tested); the ZIP/manifest write, git diff, and docs copy/serve are thin boundaries. Forbidden paths = the
`corex-*` framework plugins + theme (the spec-049 boundary); deployment is documented over these primitives.

## Complexity Tracking

> No violations.
