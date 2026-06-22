---
title: Shared-host dist artifact
audience: deployment
stability: stable
---

# Shared-host `dist` artifact

The local `wp/` directory is a **dev runtime** — it contains git-ignored WordPress core and **symlinks/junctions**
that map the monorepo into `wp-content`. It is **not** a deployment artifact. Deploy a flat, built tree from `dist/`
instead. Examples use the neutral **Acme** placeholder.

> **Mode:** building/deploying `dist/` is [Deployment Mode](../04-team-workflow/agent-roles.md#3-deployment-mode).

## Build

```bash
# framework only
npm run build:dist

# include a client site from sites/<client>/
npm run build:dist -- --client=acme

# preview the plan without writing anything
npm run build:dist -- --client=acme --dry-run
```

Under the hood: `scripts/build-shared-host-dist.mjs` (bash wrapper: `scripts/build-shared-host-dist.sh`). It
assembles, into `dist/`:

```text
dist/
  wp-admin/            # from wp/ (core)
  wp-includes/         # from wp/ (core)
  index.php …          # core loaders (NOT wp-config.php)
  wp-content/
    plugins/
      corex-core/ corex-blocks/ corex-config/ corex-forms/ corex-* (add-ons)
      acme-site/       # client plugin, from sites/acme/
    themes/
      corex/           # parent theme, from theme/
      acme-theme/      # client theme, from sites/acme/
  vendor/              # production autoloader
  corex-release.json   # build manifest (version, client, plugins, themes)
```

It builds from **repo source**, never from the symlinked `wp/wp-content/`.

## What is excluded

`.git`, `.github`, `node_modules`, `tests`, dev tooling (phpunit/phpcs/eslint/jest/playwright configs, lockfiles),
`.env`/secrets, `wp-config.php`, `debug.log`, caches, uploads, and agent/`.claude`/`.agents`/`.specify` state.
`dist/` is git-ignored and **must never be committed**.

## Verify

```bash
npm run verify:dist
```

`scripts/verify-shared-host-dist.mjs` asserts the required folders exist, no forbidden path slipped in,
`corex-release.json` is valid JSON, and — **client-asset completeness** — that any theme shipping
`assets/src/scss` (or `assets/src/js`) also ships the compiled `assets/css/*.css` (or `assets/js/*.js`). The build
entry runs this automatically and exits non-zero on failure, so a deploy can never package a half-built client
theme.

> **Build client theme assets first.** Before `npm run build:dist`, build each client theme that has an asset
> pipeline: `cd sites/<client>/<client>-theme && npm ci && npm run build`. In CI, run this as a step before the
> `build:dist` step (the Azure pipeline's build stage is the place for it).

## First deploy checklist

1. `composer install --no-dev --optimize-autoloader` and `npm run build`.
2. `npm run build:dist -- --client=acme` then `npm run verify:dist`.
3. Upload **the contents of `dist/`** to the host (see [Azure Pipelines](./azure-pipelines.md) for the automated path).
4. Create the target `wp-config.php` on the server (its own DB creds + fresh salts) — never ship the local one.
5. Import the database (`wp db export` locally → import on target) and run a URL search-replace:
   `wp search-replace http://acme.local https://acme.example --all-tables`.
6. Sync `wp-content/uploads/` separately (it is intentionally excluded from `dist/`).
7. Set file permissions; confirm the site boots; smoke-test the homepage + login.

## Update deploy checklist

1. Rebuild + verify `dist/` from the release tag.
2. Deploy `dist/` **excluding** runtime files (see protection list below).
3. Run any pending DB migrations; clear caches.
4. Smoke-test; keep the previous release available for rollback.

## Rollback checklist

1. Re-deploy the previous release's `dist/` artifact (excluding runtime files).
2. Restore the DB snapshot taken before the deploy if schema/content changed.
3. Clear caches; verify.

## Runtime files — never overwrite on deploy

```text
wp-config.php
.htaccess
wp-content/uploads/
wp-content/cache/
wp-content/upgrade/
wp-content/debug.log
```

These hold target-specific config, user uploads, and caches. The Azure SFTP deploy excludes them; do the same for
any manual upload.
