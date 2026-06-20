# Spec 057 Inventory Baseline

**Captured:** 2026-06-19 23:37 EEST

**Branch:** `spec/057-brand-tokens-logo-system`

**PR:** #54 (draft)

**Incoming commit:** `7b59923`

**Authorized tasks:** T001-T009 only

## Prerequisites and environment

| Check | Result | Evidence |
|---|---|---|
| Spec Kit prerequisites | PASS | `check-prerequisites.ps1 -Json -RequireTasks -IncludeTasks` resolved Spec 057 and `tasks.md`. |
| Requirements checklist | PASS | `requirements.md`: 16/16 complete. |
| Git repository/branch | PASS | Isolated feature worktree on `spec/057-brand-tokens-logo-system`; primary dirty dependency worktree was not touched. |
| WordPress recognition in this worktree | ENVIRONMENT-GATED | `wp --path=wp core version`, theme/plugin lists, and `wp corex --info` could not run because this isolated worktree has no `wp/` installation. |
| Docker/wp-env | ENVIRONMENT-GATED | Docker Desktop Linux engine pipe was unavailable. |
| Browser automation | ENVIRONMENT-GATED | Local Node is v22.14.0; the repository's current browser tooling requires a newer supported runtime. |
| Headless inventory tooling | PASS | PHP 8.3.6, Composer 2.4.2, Node v22.14.0, npm 10.9.2, Git, PowerShell, and `rg` were available. |

The environment gates do not convert to PASS. They do not block this inventory-only batch, which changes no
framework/runtime code. WordPress recognition must be re-established before a later runtime implementation batch.

## Repository setup verification

- `.gitignore` and `.dockerignore` exist.
- No ESLint, Prettier, npm-publish, Terraform, or Helm ignore file was added because the detected repository setup
  does not require a new one for this documentation-only inventory batch.
- `.specify/extensions.yml` has no executable `before_implement` or `after_implement` hook.

## Inventory results

- 53 canonical definitions; 53 unique IDs and 53 unique generated properties.
- 203 unique path/property consumer records across the authorized scan scope.
- Dark and Editorial palette/font replacement arrays are incomplete and remain planned implementation gaps.
- 33 repeated admin fallback chains and 101 raw admin values/functional constants were recorded for review.
- 14 legacy-reference records map to 11 unique legacy properties across five owner migration batches.
- 21 documentation surfaces reference `theme.json`, generated properties, or `brand.json`.
- No tracked production `brand.json` file exists; current resolver behavior and the incomplete documentation example
  are recorded explicitly.
- Font tasks T047-T049 remain BLOCKED on approved files/provenance.
- Logo tasks T059-T064 remain BLOCKED on the owner-approved vector package/provenance.

## Scope confirmation

No token value, CSS, theme style, PHP/JavaScript runtime, logo/font asset, release metadata, Spec 058/059, or later
milestone implementation changed. The next authorized batch is T010-T025 after review of this baseline.

## Foundational contract RED evidence — T010-T025

**Captured:** 2026-06-20 04:43 EEST

**Focused command:**

```text
vendor/bin/pest tests/Unit/Theme/TokenInventoryTest.php tests/Unit/Theme/TokenConsumerContractTest.php tests/Unit/Theme/ModeMappingTest.php tests/Unit/Theme/BrandResolverTest.php tests/Unit/Theme/BrandOverrideCompatibilityTest.php tests/Unit/Theme/TokenCompatibilityTest.php tests/Unit/Config/AdminTokenAdapterTest.php tests/Unit/Theme/FontAssetContractTest.php tests/Unit/Config/LogoAssetContractTest.php tests/Unit/Theme/ContrastMatrixTest.php --compact
```

**Expected RED result:** 40 tests, 329 assertions, 18 failures, 0 errors, 0 skipped. Exit code 1 is expected until
the corresponding implementation batches make the contracts green.

The 18 intentional failures cover:

- unresolved legacy custom-property consumers and raw design values outside approved allowances;
- missing semantic groups, incomplete dark mappings, and incomplete Dark/Editorial replacement arrays;
- missing complete-list validation/reporting and safe-default behavior;
- inactive compatibility aliases and placeholder deprecation versions;
- missing scoped CoreX admin adapter and screen-owned dependencies;
- missing font provenance manifest/approved typography roles (font assets remain owner-blocked);
- missing approved logo manifest/variants/accessibility records (logo assets remain owner-blocked); and
- four failing light contrast/focus pairs plus six missing dark semantic pairs.

The focused failures contain no runtime/test errors. `BrandResolverTest.php` passes independently: 10 tests and 12
assertions, confirming the established recursive-map/list-replacement behavior remains intact.

**Whole-suite evidence:** 654 tests, 2,560 assertions, the same 18 expected failures, 0 errors, 0 skipped, and no
failure outside the new Spec 057 contract suites. This is intentionally RED, not a passing full suite.

**Other checks:**

- `composer validate --no-check-publish`: PASS.
- PHP lint for every changed PHP test/support file: PASS.
- `npm.cmd run test:js -- --runInBand`: PASS, 16 suites and 88 tests.
- `npm.cmd run build`: PASS for all workspaces.
- Docker/wp-env, browser automation, and external deployment: ENVIRONMENT-GATED; not executed and not PASS.

No product/runtime implementation was added in T010-T025. T026 is the next task and must preserve this RED evidence
before the first `theme/theme.json` change.
