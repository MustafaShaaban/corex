# Tasks: Site readiness & performance dashboard (037)

**Forward, TDD-ordered.** The pure pieces (Grade, the two normalisers, ReadinessScorer, InsightStore) are the
headless core (Pest); the fetch, REST, and admin cards are thin boundaries. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the corex-config wiring points (ConfigServiceProvider register/boot, the `AdminGuard` + REST + settings precedents) and the `insights.*` settings keys.

## Phase 2: Foundational — result, grade, store (blocking)
- [x] T002 Write `tests/Unit/Insights/GradeTest.php` (RED) + implement `Insights/{InsightResult,Grade}.php`: score 0–100 → A–F + good/recommended/critical thresholds.
- [x] T003 Write `tests/Unit/Insights/InsightStoreTest.php` (RED) + implement `Insights/InsightStore.php`: latest per provider + bounded history (pure state transformer).

## Phase 3: US1 — Performance (PSI) (P1) 🎯 MVP
- [x] T004 [US1] Write `tests/Unit/Insights/PsiNormalizerTest.php` (RED): a sample PSI payload → score + CWV metrics + top opportunities; a malformed payload → a graceful recommended result.
- [x] T005 [US1] Implement `Insights/Normalizers/PsiNormalizer.php` + `Insights/Providers/PerformanceProvider.php` (wp_remote_get; key optional; unreachable → recommended).

## Phase 4: US2 — Readiness (native + Cloudflare) (P1)
- [x] T006 [US2] Write `tests/Unit/Insights/ReadinessScorerTest.php` (RED): native signal booleans → score + per-signal recommendations; all-pass → 100.
- [x] T007 [US2] Write `tests/Unit/Insights/CloudflareNormalizerTest.php` (RED): a sample URL-scan payload → signals/score; pending → recommended.
- [x] T008 [US2] Implement `ReadinessScorer.php`, `Normalizers/CloudflareNormalizer.php`, `Providers/ReadinessProvider.php` (native signals always; Cloudflare fetch only when configured).

## Phase 5: US3 — REST, registry, screen (P1/P2)
- [x] T009 Write `tests/Unit/Insights/InsightsControllerTest.php` (RED): `canManage` / `verifiedNonce`; the run payload omits secrets; unknown provider → null/404.
- [x] T010 Implement `Insights/{InsightRegistry,InsightsController}.php`: GET list (cap), POST run (cap+nonce, store, never echo secrets); wire `rest_api_init` in ConfigServiceProvider::boot.
- [x] T011 Implement `Insights/InsightsScreen.php` (submenu + AdminGuard + enqueue) + `assets/insights.{js,css}` (apiFetch the list on load, Run posts + re-renders) + token-fallback accessible card CSS; register `insights.*` settings fields (spec 032).

## Phase 6: Polish
- [x] T012 Guard Gate: wp-guard (remote get/post, cap+nonce on run, escape output, never echo a secret, conditional enqueue), clean-code, test-guard; fix.
- [x] T013 [P] `composer test` green (368); the run endpoint gates on cap+nonce and the response carries no secret; cards render a "configure me" state with no keys.
- [x] T014 Docs: a `guides/insights` page (docs-app) + corex-config README; PROGRESS + DECISIONS #71; NEXT STEP.
