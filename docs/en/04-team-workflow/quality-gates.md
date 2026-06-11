---
title: Quality gates — guards, Pest, Playwright
description: The automated and agent-run checks every change must pass before it ships.
audience: contributor
stability: stable
last_verified: null
---

# Quality gates — guards, Pest, Playwright

No diff ships until it passes these gates. The authoritative definition is the constitution's **Guard Gate** +
**Definition of Done** and [`COREX-WORKING-GUIDE.md` Parts B & D](../../../COREX-WORKING-GUIDE.md); this page is
the practical summary.

## The Guard Gate (run on every diff, before it is presented)

The agent runs the relevant **guard skill** on the diff and fixes any violation before showing or committing it.
A missing guard is never an excuse to skip — it is installed first.

| After you changed… | Run this guard |
|---|---|
| Any production code | `clean-code-guard` |
| WordPress code (plugin/theme/block/REST/AJAX/query) | `wp-guard` |
| WooCommerce code | `woo-guard` (on top of `wp-guard`) |
| Test code | `test-guard` |
| Docs / README / docstrings | `docs-guard` |

The guards enforce what a linter cannot judge: escaping + nonces + capabilities, prepared queries, token-only
styling, RTL, i18n-readiness, no swallowed errors, no hand-rolled security, SRP, and the AI-specific failure
modes.

## Tests (must be green)

```bash
composer test            # Pest — PHP unit (headless)
composer test:integration  # Pest — integration against ./wp (needs MySQL)
npm run test:js          # Jest — block editor JS + the shared form validator
npm run test:e2e         # Playwright — browser smoke (needs a running site)
```

```text
Tests:    295 passed (829 assertions)
```

- **Pest** covers the pure cores (planners, validators, renderers, gates) headlessly and the WP boundary via
  integration.
- **Jest** covers block `index.js` registration + the shared validator.
- **Playwright** (`tests/e2e/`) is the browser smoke — insert a block, submit a form, apply a kit. It is
  **environment-gated**: it needs a running site (Apache/WAMP up + `npx playwright install`).

## Continuous integration

The repo gate runs automatically on every push/PR to `main`/`develop`
([`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml)): `composer validate` → `php -l` →
`composer test`. A PR cannot merge until it is green. See [CI/CD](../05-deployment/ci-cd.md) for the deployment
pipeline (separate from this gate).

## Definition of Done (per feature)

A change is done when it: follows the constitution · is generated via the CLI where applicable · has green
unit + E2E tests · passed its guard · is WCAG 2.2 AA for any UI · is i18n-ready + RTL-verified · updated the
docs · updated `PROGRESS.md` · ended with a `NEXT STEP` block.

## See also

- [The Spec Kit loop](./spec-kit.md) · [Branching & commits](./branching-and-commits.md) ·
  [`COREX-WORKING-GUIDE.md` Part D.4](../../../COREX-WORKING-GUIDE.md)
