---
title: Headless WordPress
description: Use Corex as a headless backend — an envelope-shaped, cap-gated read API authenticated by nonce or application password.
---

Corex works as a headless backend: its REST routes return the one [response envelope](/guides/frontend-runtime/)
and are capability-gated, so a decoupled frontend reads structured, predictable data.

## The exposed surface

The Corex REST API (under `corex/v1` and your app's `<prefix>/v1`) exposes read endpoints for content and the
framework's own data — form submissions and custom tables (the [Data screen](/guides/data/) routes), insights,
the registered blocks/abilities, and any resource you scaffold with
[`make:api-resource`](/guides/rest/). Run `wp corex routes:list` to see them and `wp corex api:docs` for an
OpenAPI document.

Every response is the envelope; every route exposes **only public or permitted data** — never a private field or
a secret. Writes go through the existing secured routes and forms, not an open write API.

## Authentication

| Scenario | Auth |
|---|---|
| Same-origin (a Corex theme/app on the same site) | the WordPress **REST nonce** (`X-WP-Nonce` header) |
| Server-to-server / external frontend | **Application Passwords** (WordPress core — HTTP Basic auth) |

A protected resource refuses an unauthenticated or under-permitted request with an envelope error (`401`/`403`).
Create an application password under **Users → Profile → Application Passwords**.

> **Out of scope (for now):** JWT and OAuth. They are a documented later increment; nonce + application passwords
> cover same-origin and server-to-server today.

## See also

- [REST resources](/guides/rest/) — scaffold, list, and document the API.
- [The response contract & runtime](/guides/frontend-runtime/) — the envelope shape.
