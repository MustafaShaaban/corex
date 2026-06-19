---
title: Cookbooks
description: Recipes for complex, real-world Corex scenarios — each with two worked examples.
audience: contributor
stability: stable
last_verified: null
---

# Cookbooks

Task-oriented recipes for the harder scenarios. Each page states the problem, gives **two worked examples of
different shapes**, lists the pitfalls, and links to the relevant generated reference in docs-app (rather than
restating it).

## Recipes

| Recipe | The problem it solves |
|---|---|
| [WooCommerce detect-and-defer](./woocommerce-detect-and-defer.md) | Use WooCommerce when present, never make it a hard dependency. |
| [Multisite](./multisite.md) | Per-site options/flags + which capability gates network actions. |
| [Headless](./headless.md) | Drive the Corex backend from a separate front end over REST. |
| [AI-agent flows](./ai-agent-flows.md) | Expose safe, read-only, cap-gated abilities to agents (WP 7.0 Abilities). |
| [Paid (Pro) add-ons](./paid-add-ons.md) | Gate commercial features on the Pro edition flag without forking. |
| [Free/Core vs Pro boundaries](./free-core-vs-pro-boundaries.md) | Keep adoption and security basics in Free/Core while marking advanced capabilities as Pro candidates. |

Every recipe is grounded in a real Corex pattern (the Woo gate, feature flags, the Abilities provider, the
forms REST route) — see each page's "See also" for the generated class reference.
