---
title: wp-env / Docker
description: Run Corex in a throwaway containerised WordPress with @wordpress/env.
---

For a disposable, reproducible WordPress, use [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
(Docker). The repo already ships a `wp-env.json` mapping the theme and plugins.

## Prerequisites

- Docker Desktop running
- `npm install` done at the repo root (`@wordpress/env` is a dev dependency)

## Start / stop

```bash
npm run env:start    # boots WordPress at http://localhost:8888 (admin/password)
npm run env:stop
```

`wp-env` mounts the mapped plugins/theme from `wp-env.json`, so your source edits are live
in the container.

## Build assets + run WP-CLI

```bash
npm run build                          # compile blocks (SCSS + JS)
npx wp-env run cli wp plugin list      # WP-CLI inside the container
```

:::note
The default admin for wp-env is `admin` / `password` at `http://localhost:8888/wp-admin/`.
Activate the Corex plugins as in [First run](/getting-started/first-run/) (via
`npx wp-env run cli wp plugin activate …`).
:::
