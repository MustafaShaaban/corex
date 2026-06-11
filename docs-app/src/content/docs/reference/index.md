---
title: Internals reference
description: The per-class reference, grouped by layer — generated from the source.
---

This section is the **internals reference**: what each class is, what it does, why it
exists, its public API, and how it collaborates with the others — grouped by layer (core,
blocks, forms, config, add-ons).

:::note[Generated from code]
The per-class pages are produced by `wp corex docs:generate`, which reads the actual
source (classes, public methods, PHPDoc). That keeps the reference from drifting: when the
code changes, regenerate, and the docs update in the same change. Run:

```bash
wp corex docs:generate --path=wp
```
:::

Until you generate them locally, use the per-plugin `README.md` files in the repository as
the authoritative class notes:

- `plugins/corex-core/README.md` — Boot, Container, Config, FeatureFlags, hooks, the data
  layer + QueryBuilder, middleware, the Mail seam.
- `plugins/corex-blocks/README.md` — block discovery, the dynamic registrar, connectors.
- `plugins/corex-forms/README.md` — schema, validation (front + back), the secured submit
  lifecycle, the form block.
- `packages/cli/README.md` — every `make:*` command.
- `packages/build-tools/README.md` — the SCSS/JS build pipeline.
- `addons/*/README.md` — each add-on.
