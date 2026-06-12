# Feature Specification: Site readiness & performance dashboard (037)

**Created**: 2026-06-12 · **Status**: Draft · **Input**: "A feature for *is the website agent-ready* and *Google
insights for performance* — two widgets showing the results and a button to run a check. One from Cloudflare and
one from Lighthouse/Google. Make it genuinely useful, not just the literal ask."

A **Corex → Insights** admin dashboard with two result cards and an on-demand "Run check" button each:

1. **Performance** — Google **PageSpeed Insights / Lighthouse**: a 0–100 performance score, the Core Web Vitals,
   and the top opportunities to improve.
2. **Readiness** — the site's **agent- & delivery-readiness**: a 0–100 score from a Cloudflare URL-scan signal
   **and** Corex-native checks (an `llms.txt`, a sitemap, robots that allow agents, MCP abilities exposed, HTTPS),
   so the card is useful even before Cloudflare is configured.

Both are built on a **pluggable provider** abstraction so more checks (security, SEO, accessibility) can be added
later. Results are **scored, graded, cached, and history-kept**, degrade gracefully when no API key/token is set
(a clear "configure me" state, never an error), and every run is **capability- + nonce-gated** (Principle VII).

## User Scenarios & Testing

### US1 — "How fast is my site?" (P1) 🎯 MVP
As a site owner, I open **Corex → Insights**, see the Performance card, click **Run check**, and within seconds
get a graded performance score, the Core Web Vitals (LCP/CLS/INP/FCP/TBT), and a short list of the highest-impact
fixes — sourced from Google PageSpeed Insights for my site's URL.

**Acceptance**: clicking Run calls a cap+nonce-gated REST endpoint that fetches PSI, normalises it to a scored
result, stores it, and returns it; the card renders score + grade + metrics + recommendations + "checked at"; a
missing PSI key still works (PSI allows keyless low-volume) but the card notes that a key is recommended.

### US2 — "Is my site ready?" (P1)
As a site owner, I click **Run check** on the Readiness card and get a graded readiness score assembled from
Corex-native signals (HTTPS, an `llms.txt`, a sitemap, robots allowing agents, MCP abilities exposed) enriched by
a Cloudflare URL-scan when a token is configured — each signal shown as pass/fail with a concrete recommendation.

**Acceptance**: the readiness run always produces a result from the native signals; if a Cloudflare token +
account are configured it adds the scan signals; with neither, the card still scores the native signals and tells
me how to add Cloudflare for more.

### US3 — Configure, cache, and history (P2)
As a maintainer, I set the PSI key and Cloudflare token in **Corex → Settings**; results are cached so the
dashboard loads instantly without re-running, and the last few runs are kept so I can see whether a change helped.

**Acceptance**: the dashboard loads the last stored result per provider with no network call; a bounded run
history is retained; secrets are stored as settings (write-only in the UI) and never echoed back.

## Requirements

- **FR-001**: A pure `InsightResult` value (provider id, label, score 0–100, letter **grade** A–F, status
  good/recommended/critical, summary, metrics[], recommendations[], checkedAt) with a pure `Grade` mapping
  (score → A–F + status).
- **FR-002**: An `InsightProvider` interface (`id()`, `label()`, `run(string $url): InsightResult`). Each
  provider's **normaliser** (raw API/probe data → `InsightResult`) is **pure and unit-tested**; the HTTP fetch is
  a thin seam using `wp_remote_get`/`wp_remote_post`.
- **FR-003**: A **Performance** provider over **PageSpeed Insights** (normalises the Lighthouse performance
  category + CWV audits + opportunities). A **Readiness** provider that scores **Corex-native** signals (HTTPS,
  `llms.txt`, sitemap, robots, MCP abilities) and folds in a **Cloudflare** URL-scan signal when configured.
- **FR-004**: **Graceful degradation** (Principle IX): an unconfigured/unreachable/malformed provider returns a
  `recommended` result with a "how to configure" recommendation — never throws, never a fatal, never blocks the
  page.
- **FR-005**: An `InsightStore` caches the latest result per provider and a **bounded history** (last N) in an
  option; the dashboard reads the cache with no network call.
- **FR-006**: A REST controller: `GET corex/v1/insights` (stored results, `manage_options`) and
  `POST corex/v1/insights/run` (run a provider + store; `manage_options` **and** a valid REST nonce — Principle
  VII). Secrets come from settings and are never returned in any response.
- **FR-007**: A **Corex → Insights** admin screen (shared `AdminGuard`) with two accessible, token-styled result
  cards + a Run button each; assets enqueued only on its screen; the score dial + signals are WCAG 2.2 AA, i18n,
  and RTL-correct.
- **FR-008**: The engines (`Grade`, both normalisers, `InsightStore`, the readiness scorer) are headless
  **Pest**-tested; the providers' fetch + the REST callbacks + the screen are thin boundaries.

## Success Criteria

- **SC-001**: From **Corex → Insights**, one click returns a graded performance result with CWV + recommendations.
- **SC-002**: One click returns a graded readiness result from native signals, enriched by Cloudflare when set.
- **SC-003**: With no keys configured, both cards render a useful "configure me" state and never error.
- **SC-004**: The normalisers, grade, scorer, and store have passing Pest tests; the full suite stays green;
  secrets are never exposed in a REST response; every run is cap+nonce-gated.

## Assumptions

- PageSpeed Insights is called server-side (`wp_remote_get`) with an optional API key from settings; the result
  is the public Lighthouse JSON.
- Cloudflare uses the **URL Scanner** API (account-scoped, Bearer token); because scans can be asynchronous, a
  pending scan yields a `recommended` "scan in progress" result rather than blocking.
- "Agent-ready" is interpreted as the modern delivery checklist for AI agents + crawlers: HTTPS, an `llms.txt`,
  an XML sitemap, agent-permitting robots, and exposed **MCP abilities** (Corex already ships WP 7.0 abilities).

## Dependencies

Spec 001 (Config/providers), spec 005 (middleware/nonce/cap — Principle VII), spec 017/030 (the corex-config admin
+ REST + `AdminGuard` precedent), spec 032 (settings UX for the secret fields), spec 036 (the probe pattern reused
for native readiness signals).
