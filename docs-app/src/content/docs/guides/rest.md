---
title: REST resources
description: Scaffold a complete secured REST resource, list your routes, and emit an OpenAPI document.
---

Corex makes REST Laravel-like but WordPress-native: one command scaffolds a complete, secured,
envelope-shaped resource, and two more let you discover and document the API.

## Scaffold a resource

```bash
wp corex make:api-resource Project
```

This generates, under your app's `Api/` namespace, a full set:

- a **thin controller** (`ProjectController`) — route → service → resource → the
  [response envelope](/guides/frontend-runtime/); it hand-rolls no security.
- a **routes** class (`ProjectRoutes`) — registers the routes under your app's REST namespace
  (`<prefix>/v1`) with a **permission callback** (reads are public by default; a write route must add a
  capability check — never a public callback).
- a **request** shape (`ProjectRequest`) — the fields + rules the spec-005 middleware validates.
- a **resource** (`ProjectResource`) — shapes output to **only the declared fields** (no raw model, no secret).
- a **test** scaffold.

Register the generated `Routes` class on `rest_api_init` and fill in the service — the boilerplate is done.
`--force` overwrites an existing resource.

## List your routes

```bash
wp corex routes:list
```

Prints every Corex/app REST route grouped by namespace — method, path, and whether it is **guarded** (a
non-public permission callback) or **public** — so the API surface is discoverable.

## Emit an API document

```bash
wp corex api:docs
```

Emits an **OpenAPI 3** document of the registered routes — paths, methods, the envelope response schema, and the
auth schemes (nonce / application password). It contains no secret. Pipe it to a file to share with consumers.

## See also

- [The response contract & runtime](/guides/frontend-runtime/) — the envelope every resource returns.
- [Headless WordPress](/guides/headless/) — exposing this API to a decoupled frontend.
