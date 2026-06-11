---
title: Cookbook — headless Corex
description: Drive a Corex backend from a separate front end over REST.
audience: contributor
stability: stable
last_verified: null
---

# Cookbook — headless Corex

**The problem.** You want WordPress + Corex as a **backend only** — content, forms, and data — with a separate
front end (Next.js, a mobile app, …) talking to it over HTTP. Corex's controllers are already REST/AJAX entry
points behind the declarative middleware, so the backend is headless-capable without changes.

## Example 1 — submit a Corex form from an external front end

The forms engine exposes a secured REST route per form (`corex/v1/forms/{slug}`). A front end fetches the
form's schema (embedded in the block, or expose it via your own endpoint), validates client-side against the
**same** schema, then POSTs — the server re-validates and is authoritative.

```bash
curl -X POST https://corex.example.com/wp-json/corex/v1/forms/contact \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{"name":"Sam","email":"sam@example.com","message":"Hi"}'
```

```json
{ "ok": true, "message": "Thanks — we'll be in touch." }
```

The nonce + throttle + sanitize middleware still apply (Principle VII) — a headless client is only another
caller of the same secured route.

## Example 2 — read site/structure via the WP 7.0 Abilities surface

Corex registers read-only, capability-gated **abilities** (`corex/list-blocks`, `corex/site-info`) exposed in
REST. A headless client (or an AI agent) can discover the site without scraping HTML.

```bash
curl https://corex.example.com/wp-json/wp/v2/abilities/corex/site-info \
  -H "X-WP-Nonce: <nonce>"
```

```json
{ "name": "Corex", "version": "0.19.0", "corex_blocks": 9 }
```

The two examples are different shapes: Example 1 **writes** through a secured form route; Example 2 **reads**
through a cap-gated ability. Both are ordinary HTTP — no special headless mode to enable.

## Pitfalls

- **Auth**: REST writes need a nonce (cookie auth) or an application password / JWT for a decoupled client.
  Never expose a writing route with `permission_callback => __return_true`.
- **CORS**: a browser front end on a different origin needs CORS headers — add them at your edge/server, scoped
  to your front-end origin.
- **Rendering**: dynamic blocks render server-side; a headless front end that bypasses the theme must render
  block content itself (or call the block-renderer output via REST).

## See also

- [AI-agent flows](./ai-agent-flows.md) (the Abilities surface in depth) · the generated `SubmitController` /
  `AbilitiesProvider` references in docs-app.
