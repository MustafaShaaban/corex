# Corex documentation site (`docs-app/`)

The Corex reference site — built with [Astro](https://astro.build) +
[Starlight](https://starlight.astro.build). Client-side search (Pagefind), left-nav by
category, breadcrumbs, prev/next, light/dark, RTL-ready, and copy buttons on every code
block. It ships **with** the framework and is decoupled so it can later move to a dedicated
public website unchanged.

## Run it

```bash
npm install            # once (from this docs-app/ directory)

npm run dev            # instant dev server → http://localhost:4321
npm run build          # static build → ./dist
npm run preview        # preview the built site
```

## Serve the static build on Apache (WAMP)

`npm run build` outputs static HTML to `dist/`. Two options:

1. **Repo subpath** — browse `http://localhost/corex/docs-app/dist/`. For correct asset
   paths at this URL, set `base: '/corex/docs-app/dist'` in `astro.config.mjs` before
   building.
2. **Dedicated vhost** (recommended, future-proof) — point a vhost `docs.corex.local` at
   `docs-app/dist` (DocumentRoot) and add `127.0.0.1 docs.corex.local` to your hosts file.
   `base` stays `/`, so the same build moves to a public site unchanged.

## Content

- `src/content/docs/` — the pages (Markdown/MDX). Guides and getting-started are
  hand-written; **Internals Reference** (`reference/`) is generated from the code by
  `wp corex docs:generate` so it never drifts.
- `astro.config.mjs` — title, sidebar, search.

The docs describe the **real code** in this repository. When code changes, update the
relevant page (or regenerate the reference) in the same change, and run `docs-guard`.

`node_modules/` and `dist/` are git-ignored — rebuild on checkout.
