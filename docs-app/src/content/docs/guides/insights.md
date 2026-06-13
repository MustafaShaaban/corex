---
title: Insights — performance & readiness
description: The Corex → Insights dashboard — a PageSpeed/Lighthouse performance card and an agent-readiness card, each run on demand.
---

**Corex → Insights** is an admin dashboard with two result cards, each with a **Run check** button:

- **Performance** — Google **PageSpeed Insights / Lighthouse**: a 0–100 performance score, the Core Web Vitals
  (LCP, INP, CLS, FCP, TBT), and the highest-impact opportunities to fix.
- **Readiness** — your site's **agent- & delivery-readiness**: HTTPS, an `llms.txt`, an XML sitemap, robots that
  allow agents, and exposed **MCP abilities** — scored, with a recommendation per failing signal. When a
  Cloudflare token is configured it also folds in a URL-scan security signal.

Every result is **scored, graded (A–F), cached, and history-kept**, so the dashboard loads instantly and you can
see whether a change helped.

## Run a check

Open **Corex → Insights** and click **Run check** on a card. The button calls a capability- and nonce-gated REST
endpoint that runs the provider server-side, stores the result, and re-renders the card with the score, metrics,
and recommendations — plus when it was last checked.

## Configure the providers

Set the optional credentials in **Corex → Settings → Insights** (write-only password fields):

| Setting | Used by | Notes |
|---|---|---|
| `insights.psi.key` | Performance | A Google PageSpeed Insights API key. Optional — PSI allows low-volume keyless use, but a key is more reliable. |
| `insights.cloudflare.token` | Readiness | A Cloudflare API token (URL Scanner scope). Optional. |
| `insights.cloudflare.account_id` | Readiness | Your Cloudflare account ID. Required alongside the token. |

**Nothing is required to start.** With no credentials, the Performance card still runs (keyless PSI) and the
Readiness card still scores your native signals — each card simply notes what to configure for more. A provider
that's unconfigured, unreachable, or returns a bad response degrades to a clear "configure me" state and **never
errors** (constitution Principle IX).

## How it's built

Insights is a pluggable `InsightProvider` seam. The judgement lives in **pure, unit-tested** pieces — `Grade`
(score → A–F + status), the `PsiNormalizer` and `CloudflareNormalizer`, the `ReadinessScorer`, and the
`InsightStore` (cache + bounded history). The HTTP fetch, the `corex/v1/insights[/run]` REST routes (cap +
nonce; **secrets never appear in a response**), and the two cards are thin boundaries over them. Add a new card
by registering another provider — security, SEO, or accessibility — with no UI changes.

## See also

- [Settings & feature flags](./configuration.md) — where the Insights credentials live.
- [The CLI](./cli.md) — `wp corex doctor` runs the framework health check (distinct from these site audits).

## Diagnosing a failed performance check

When PageSpeed can't be read, Corex classifies the cause instead of showing a generic error
(spec 044): a **local/private URL** (`localhost`, `*.local`, `127.0.0.1`, private ranges) is caught
before the call and explained — PageSpeed can only crawl a public URL — and other failures are
distinguished as `http_error`, `quota`, `invalid_key`, or `invalid_response`, each with a next
action. The PageSpeed API key is *recommended* (not required); raw diagnostic detail is shown to
`manage_options` admins only and is scrubbed of any key/token.
