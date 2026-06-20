# Quickstart: Validate Brand Tokens and Logo System

This is the implementation validation runbook. It does not authorize code changes before `tasks.md` is reviewed.

## Prerequisites

- Active branch is `spec/057-brand-tokens-logo-system`, never `main`.
- Spec, plan, and tasks are reviewed.
- PHP 8.3+, Composer dependencies, Node.js 20+, and npm dependencies are installed.
- For implementation work, WordPress 7.0+ recognizes the CoreX theme and required plugins.
- Logo-specific tasks remain blocked until the owner-approved vector package and provenance record exist.

## 1. Verify planning artifacts

```powershell
.specify/scripts/powershell/check-prerequisites.ps1 -Json
git diff --check
```

Expected: Spec 057 resolves as the active feature and planning documents contain no unresolved placeholders.

## 2. Run headless token and compatibility checks

```powershell
composer validate --no-check-publish
composer test -- --filter "Theme|Brand|Token|Contrast"
npm.cmd run build
npm.cmd run test:js -- --runInBand
git diff --check
```

Expected:

- theme/style JSON is valid;
- inventory contains every definition and consumer;
- no undefined production reference or competing authority remains;
- aliases/deprecations and complete style-variation lists satisfy the token contract;
- brand associative/list behavior and invalid replacement fixtures match the compatibility contract; and
- contrast/focus matrices meet their thresholds.

After CSS/SCSS implementation, also run:

```powershell
npm.cmd run lint:css
```

## 3. Verify fonts

Inspect the font manifest/tests and built network references.

Expected:

- no more than four self-hosted WOFF2 files;
- Space Grotesk 500–700 Latin, JetBrains Mono 400–600 Latin, IBM Plex Sans Arabic 400/600 Arabic;
- Latin body/interface uses the system stack;
- every face uses `font-display: swap`;
- no preload exists without recorded evidence; and
- missing-font fallback remains readable.

## 4. Verify logo gate

Before owner approval, expected status is `BLOCKED`, not failed or passed. Confirm the legacy navy/cyan SVG is not
presented as the new source artwork.

After approval, validate the package against [contracts/asset-contract.md](./contracts/asset-contract.md), then run
focused branding tests and rendered size/contrast/accessibility checks.

## 5. Run WordPress and browser evidence

Where Docker/WordPress/browser support is available:

```powershell
npm.cmd run env:start
npm.cmd run test:e2e
```

Run the direction, mode, focus, forced-colors, 200% zoom, text-resizing, admin-scope, font-network, and approved-logo
matrices from [contracts/verification-contract.md](./contracts/verification-contract.md).

If Docker, the mapped WordPress install, or browser automation is unavailable, record the relevant checks as
`ENVIRONMENT-GATED`. Do not record them as passing.

## 6. Run full release-quality checks

```powershell
composer validate --no-check-publish
composer test
npm.cmd run build
npm.cmd run test:js -- --runInBand
npm.cmd run verify:dependencies
git diff --check
```

Build the docs app when design-system or branding documentation changes. Run `clean-code-guard`, `wp-guard`,
`test-guard`, and `docs-guard` on the applicable diff before commit. Record actual checks and environment gates in
`PROGRESS.md`.

## Failure and rollback checks

- Undefined property: restore/add the documented alias; do not patch a component with a literal.
- Incomplete client list: report it and retain safe defaults; do not merge by slug.
- Contrast/focus failure: reject the token values and update the semantic mapping.
- Admin leakage: remove global scope and restore the CoreX-root adapter boundary.
- Font regression: remove the new face declaration/files and restore system fallbacks.
- Logo regression: restore the prior approved default asset mapping; do not alter stored client content.
