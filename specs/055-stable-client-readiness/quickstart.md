# Quickstart: Stable Client Readiness (055)

This guide describes how to validate the feature once `/speckit-tasks` has created implementation tasks. It is not
an instruction to implement before tasks exist.

## Prerequisites

- Branch: `feature/055-stable-client-readiness`
- WordPress environment mapped as documented in `PROGRESS.md` when running integration or browser checks
- PHP 8.3+, Composer dependencies, Node 20+ dependencies
- For browser/wp-env checks: Docker or the local WAMP site plus a browser-capable environment

## 1. Confirm the spec state

```powershell
git status --short --branch
Get-Content -Raw .specify/feature.json
Get-Content -Raw specs/055-stable-client-readiness/spec.md
```

Expected:

- Branch is `feature/055-stable-client-readiness`
- `.specify/feature.json` points at `specs/055-stable-client-readiness`
- No implementation work is started before `tasks.md`

## 2. Headless verification baseline

```powershell
composer validate --no-check-publish
composer test
npm run build
npm run test:js
```

Expected:

- Composer metadata validates
- Pest and Jest pass
- Workspace builds complete
- Any unavailable dependency is reported honestly, not hidden

## 3. Runtime gating validation

Planned checks should cover these scenarios:

```text
Given corex-careers is inactive
When Corex boots
Then the careers provider is excluded and no careers hooks/routes/blocks/assets/admin surfaces register

Given corex-careers is active
When Corex boots
Then the careers provider is included and its expected behavior remains available

Given corex-kit-woo is active but WooCommerce is unavailable
When Corex boots
Then Woo-specific behavior is excluded

Given corex-kit-woo is active and WooCommerce is available
When Corex boots
Then Woo kit behavior is included
```

Expected evidence:

- Pest tests for provider inclusion/exclusion
- Tests or diagnostics proving unsafe disabled behavior is absent
- Woo tests proving both dependency-missing and active paths

## 4. Metadata consistency validation

Run the planned metadata check once implemented, or inspect the surfaces manually during planning:

```powershell
Get-Content -Raw package.json
Get-Content -Raw composer.json
Select-String -Path plugins/*/*.php,addons/*/*.php -Pattern "Version:|Update URI|COREX_.*VERSION"
Get-Content -Raw README.md
Get-Content -Raw CHANGELOG.md
Get-Content -TotalCount 40 PROGRESS.md
```

Expected:

- The check reports `pass` or exact mismatches by file/value
- Policy exceptions are named explicitly
- No release claim depends on chat memory

## 5. make:site validation

Use a temporary output directory when tasks add or expose the validation command.

```powershell
wp corex make:site Acme --path=wp
wp corex make:site Acme --starter --path=wp
```

Expected:

- Generated client plugin and theme are isolated from Corex framework folders
- Client namespace, REST namespace, CSS prefix, option prefix, governance files, `specs/`, docs placeholders, and
  token strategy are present
- Starter mode includes the removable example slice
- Compliance check flags direct client-brand edits under `plugins/corex-*`, `addons/corex-*`, `packages/`, or
  the Corex theme

## 6. CI/security readiness validation

Review or run the workflow-equivalent commands:

```powershell
composer validate --no-check-publish
Get-ChildItem plugins,packages,addons -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
composer test
npm run build
npm run test:js
Push-Location docs-app; npm run build; Pop-Location
```

Expected:

- Fast checks are PR-suitable
- wp-env/Playwright checks are either runnable or reported as environment-gated
- GitHub-settings-only controls, if any, are documented separately from repo-file controls

## 7. Component and Free/Core boundary validation

Review the generated matrices from the implementation tasks.

Expected:

- Every minimum company-site need is classified by native Corex/WordPress mechanism or marked missing/deferred
- No final visual redesign is included
- Security-critical basics are classified Free/Core
- Pro candidates are advanced, vertical, or automation features and do not block the first two company sites

## 8. Environment-gated browser validation

When wp-env or the WAMP browser environment is available:

```powershell
npm run env:start
npm run test:e2e
npm run env:stop
```

Expected:

- Playwright smoke and console sweep pass
- If Docker/browser/Apache is unavailable, record `environment-gated` with the exact blocker and command

## 9. Guards and continuity

Before presenting, committing, or merging a diff:

```text
Run all applicable guards:
- clean-code-guard for production code
- wp-guard for WordPress/plugin/theme/route/query behavior
- test-guard for tests
- docs-guard for docs and Spec Kit artifacts
```

Expected:

- Guard output is clean or remediation is complete
- `PROGRESS.md` is updated
- `DECISIONS.md` logs non-trivial choices
- Final report names exact commands, results, skipped checks, and environment gates
