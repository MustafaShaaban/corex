# Implementation Plan: Site readiness & performance dashboard (037)
**Branch**: `feature/037-insights-dashboard` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
A **Corex → Insights** dashboard in corex-config. A pluggable `InsightProvider` produces a scored, graded
`InsightResult`; two providers ship — **Performance** (PageSpeed Insights / Lighthouse) and **Readiness** (Corex-
native agent-readiness signals + an optional Cloudflare URL-scan). The pure pieces (`Grade`, the PSI + Cloudflare
normalisers, the native `ReadinessScorer`, the `InsightStore`) are unit-tested; the HTTP fetch, the REST run
(cap + nonce, no secret leak), and the two admin cards are thin boundaries. Unconfigured providers degrade to a
"configure me" `recommended` result — never an error (Principle IX). Results are cached + history-kept.

## Technical Context
PHP 8.3; `wp_remote_get`/`wp_remote_post` (PSI keyless-friendly; Cloudflare Bearer + account-scoped). Secrets from
spec-032 settings (`insights.psi.key`, `insights.cloudflare.token`, `insights.cloudflare.account_id`). Admin via
spec-030 `AdminGuard` + a vanilla `insights.js` (apiFetch). Tests: Pest. Constraints: Principle VII (cap+nonce on
run, escape output, never echo a secret), V (token-only cards), VIII (a11y/RTL/i18n), IX (optional → graceful).

## Constitution Check (v1.2.1)
- [x] III/IV — `Grade`/normalisers/`ReadinessScorer`/`InsightStore` pure; fetch + REST + screen thin.
- [x] V/VIII — cards token-only, logical CSS, accessible (score meter has text + aria), i18n/RTL.
- [x] VII — run endpoint is `manage_options` + REST nonce; output escaped; secrets write-only, never in a response.
- [x] IX — Cloudflare (and even PSI key) optional; absent → a useful recommended state, never a hard failure.
- [x] X — implements spec 037.
- [x] Guard Gate/DoD — wp-guard (remote get/post, nonce/cap, escaping, no secret echo) + clean-code + test-guard;
  Pest engines; docs + docs-app.

**Gate**: PASS.

## Design (in `plugins/corex-config/src/Insights/`)
- `InsightResult` (value) + `Grade` (pure: score 0–100 → A–F + good/recommended/critical, with thresholds).
- `InsightProvider` (interface: `id()`, `label()`, `run(string $url): InsightResult`).
- `Normalizers/PsiNormalizer` (pure: PSI JSON → result: perf score, CWV metrics, top opportunities).
- `Normalizers/CloudflareNormalizer` (pure: URL-scan JSON → signals/score; pending → recommended).
- `ReadinessScorer` (pure: native signal booleans `{https, llms_txt, sitemap, robots_agents, mcp_abilities}` →
  score + per-signal recommendations).
- `Providers/PerformanceProvider` (PSI fetch + PsiNormalizer; key optional).
- `Providers/ReadinessProvider` (ReadinessScorer over injected native signals + optional Cloudflare fetch/normalise).
- `InsightStore` (latest + bounded history per provider in the `corex_insights` option).
- `InsightRegistry` (the configured providers).
- `InsightsController` (REST: GET list, POST run — cap+nonce; never returns secrets).
- `InsightsScreen` (admin submenu under `corex-settings`; two cards + Run buttons; enqueues `assets/insights.js`).
- Settings: register `insights.*` fields (spec 032) — PSI key + Cloudflare token/account (password type).

## FR → component map
| FR | Built in |
|---|---|
| FR-001 result + grade | `InsightResult.php`, `Grade.php` |
| FR-002 provider seam + pure normalisers | `InsightProvider.php`, `Normalizers/*` |
| FR-003 PSI + readiness/Cloudflare | `Providers/*`, `ReadinessScorer.php` |
| FR-004 graceful degradation | each provider's `run()` catch → recommended result |
| FR-005 cache + history | `InsightStore.php` |
| FR-006 REST run cap+nonce, no secret | `InsightsController.php` |
| FR-007 admin cards | `InsightsScreen.php` + `assets/insights.js` + card CSS |
| FR-008 tested | `tests/Unit/Insights/*` |

## Project Structure
```text
plugins/corex-config/src/Insights/{InsightResult,Grade,InsightProvider,InsightRegistry,InsightStore,InsightsController,InsightsScreen}.php
plugins/corex-config/src/Insights/Normalizers/{PsiNormalizer,CloudflareNormalizer}.php
plugins/corex-config/src/Insights/ReadinessScorer.php
plugins/corex-config/src/Insights/Providers/{PerformanceProvider,ReadinessProvider}.php
plugins/corex-config/assets/insights.js  + card styles
tests/Unit/Insights/{GradeTest,PsiNormalizerTest,CloudflareNormalizerTest,ReadinessScorerTest,InsightStoreTest,InsightsControllerTest}.php
docs/en + docs-app (guides/insights)
```

## Complexity Tracking
The pure normalisers/scorer/grade/store carry the logic and the tests; the network + REST + UI are thin. The
async Cloudflare scan is handled as a `pending` result (no blocking). Live PSI/Cloudflare runs are env-gated.
