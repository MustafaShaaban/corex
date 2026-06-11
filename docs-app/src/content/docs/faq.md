---
title: FAQ
description: How do I… — common tasks and answers.
---

## How do I start a new site?

Install WordPress into `./wp`, wire the monorepo, activate, build. The full path is in
[Getting Started](/getting-started/overview/).

## How do I create a form?

Define a `Form` subclass (slug + field map) and register it; place the **Corex Form**
block. Server + client validation come from the one schema. See
[Create a form](/guides/forms/).

## How do I add a new block?

`wp corex make:block <Name>` then `npm run build`. The block is dynamic and registered.
See [Create a block](/guides/blocks/).

## How do I query data without N+1?

Use the [QueryBuilder](/guides/queries/) with `->with('relation')` — belongs-to relations
are eager-loaded in a bounded number of queries.

## How do I change colours/fonts?

Override tokens in a per-site `brand.json` (deep-merged over `theme.json` at runtime).
Never hardcode values. See [Apply a brand](/guides/branding/).

## How do I enable an add-on?

Activate its plugin (`wp plugin activate corex-<name>`). No add-on is a hard dependency;
the core runs without any of them.

## How do I gate a Pro-only feature?

Wrap it in a [feature flag](/guides/configuration/): `Config::enabled('pro')`. Free builds
leave `features.pro` off.

## How do I run the tests?

```bash
composer test               # PHP unit (headless)
composer test:integration   # PHP integration (real ./wp)
npm run test:js             # Jest (shared validator, block JS)
```

## How do I keep these docs accurate?

The class reference is generated from code with `wp corex docs:generate`; guides are
hand-written and checked against the source before publishing.
