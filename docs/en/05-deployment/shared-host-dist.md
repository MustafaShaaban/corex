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

The builder needs `node` and `composer` on the `PATH`, and the verifier needs `php`. Composer downloads the
production packages the first time it builds a package, so that build needs the network.

```bash
# framework only
npm run build:dist

# include a client site from sites/<client>/
npm run build:dist -- --client=acme

# preview the plan without writing anything
npm run build:dist -- --client=acme --dry-run

# keep every font the PDF library ships (see below)
npm run build:dist -- --client=acme --pdf-fonts=all
```

### The PDF library's fonts

An export can be a PDF, and the library that writes it ships a font for every script it can write: 87MB, of
which 54MB is six fonts for Chinese, Korean and three ancient scripts. A package is uploaded by hand, so by
default it keeps the fonts for Latin, Greek, Cyrillic and Hebrew text (DejaVu) and for Arabic-script
languages (XB Riyaz and Lateef), with every font licence. Measured on the framework alone, 2026-10-08:

| Package | Unpacked | Zipped |
|---|---|---|
| The default fonts | 117MB | 33MB |
| `--pdf-fonts=all` | 191MB | 71MB |

With the default fonts a PDF is still written whatever an answer is written in: text in a script whose font
is not in the package prints as empty boxes, and the same export as a workbook or a CSV holds it as it is.
Build with `--pdf-fonts=all` for a site that collects answers in Chinese, Japanese, Korean, Thai or an
Indic script and files them as PDF. `corex-release.json` records which was built, as `pdf_fonts`.

Under the hood: `scripts/build-shared-host-dist.mjs` (bash wrapper: `scripts/build-shared-host-dist.sh`). It
empties `dist/` and assembles, into it:

```text
dist/
  wp-admin/            # from wp/ (core)
  wp-includes/         # from wp/ (core)
  index.php …          # core loaders (NOT wp-config.php, NOT .htaccess)
  wp-content/
    plugins/
      corex-core/ corex-blocks/ corex-config/ corex-forms/ corex-* (add-ons)
      acme-site/       # client plugin, from sites/acme/
    themes/
      corex/           # parent theme, from theme/
      acme-theme/      # client theme, from sites/acme/
    packages/
      cli/             # the CLI package, from packages/cli/: `wp corex` and its generator stubs
    vendor/            # production Composer packages and the autoloader, generated for this layout
  corex-release.json   # build manifest (version, client, plugins, themes, autoload)
```

It builds from **repo source**, never from the symlinked `wp/wp-content/`.

## How the package loads the framework

In the package, **`wp-content/` is the project root**. Each CoreX plugin's main file looks for
`vendor/autoload.php` in its own directory and then two directories up. In the repository two directories up from
`plugins/corex-core/` is the repository root. In the package, two directories up from
`wp-content/plugins/corex-core/` is `wp-content/`, so that is where the autoloader is. The CLI package is at
`wp-content/packages/cli/` for the same reason: its code measures the project root from its own location.

Composer's namespace map cannot be copied, because it names the repository's directories and the package puts
add-ons somewhere else (`addons/corex-email/src/` becomes `plugins/corex-email/src/`). So the builder:

1. translates every `psr-4` entry of the root `composer.json` to where the package holds that directory;
2. writes that as a `composer.json` in `dist/wp-content/`, beside a copy of the root `composer.lock`;
3. runs `composer install --no-dev --optimize-autoloader` there, which installs the locked production packages and
   generates the autoloader in `dist/wp-content/vendor/`;
4. removes the two Composer files from the package again.

**The checkout's own `vendor/` is not read and not changed.** Do not run `composer install --no-dev` on a checkout
to prepare a package: it is not needed, and it removes the test tools the checkout depends on.

What stops the build instead of producing a package that cannot load:

- no `composer.json` or `composer.lock` at the repository root;
- an `autoload` section with anything other than `psr-4` (`files`, `classmap`, `psr-0`);
- a namespace mapped to more than one directory;
- a framework plugin, an add-on or the CLI package that has a `src/` directory and no namespace mapped to it;
- `composer install` failing.

A namespace whose directory is not in the package (another client's site, for example) is left out of the packaged
autoloader, with a warning. A client plugin generated by `wp corex make:site --starter` registers its own autoloader
in its main file and needs no entry.

> **`.env`.** CoreX reads an optional `.env` from the project root. In the package that is `wp-content/`, which is
> inside the web root. The builder never packages one. Do not create one there on a shared host.

## What is excluded

`.git`, `.github`, `node_modules`, `tests`, dev tooling (phpunit/phpcs/eslint/jest/playwright configs, lockfiles),
`.env`/secrets, `wp-config.php`, the `.htaccess` of the development install, `debug.log`, caches, uploads, and
agent/`.claude`/`.agents`/`.specify` state. The Composer dev packages (Pest, Brain Monkey and everything they
require, PHPUnit and Mockery among them) are never installed into the package. `dist/` is git-ignored and **must
never be committed**.

`README.md`, `composer.json` and `package.json` are left out of CoreX's plugins, add-ons, theme and command-line
package, and out of a client's plugin and theme: a web server would hand them to anybody, and nothing on a running
site reads them. WordPress core's own are packaged as WordPress ships them.

`wp-content/vendor/` is packaged as Composer installs it, third-party `README.md` and `composer.json` files and
`vendor/composer/installed.json` included. Nothing in it is meant to be requested: refuse requests into
`wp-content/vendor/` in the host's own server configuration. The package ships no `.htaccess` of its own.

## Verify

```bash
npm run verify:dist
```

`scripts/verify-shared-host-dist.mjs` fails, with a non-zero exit code, unless all of this holds:

- the required folders exist and `corex-release.json` is valid JSON;
- no forbidden path slipped in, and there is no `.htaccess` in the package root;
- **client-asset completeness**: any theme shipping `assets/src/scss` (or `assets/src/js`) also ships the compiled
  `assets/css/*.css` (or `assets/js/*.js`);
- `wp-content/vendor/` was installed without dev packages;
- **the package loads the framework.** `scripts/shared-host-dist-probe.php` runs in a PHP process of its own, with
  nothing of the checkout loaded. It includes `wp-content/plugins/corex-core/corex-core.php` as WordPress would,
  and checks that this file loaded `wp-content/vendor/autoload.php`, that one class from every namespace listed in
  `corex-release.json` (`autoload.psr4`: every framework plugin, every add-on and the CLI package) loads from the
  packaged directory it is mapped to, and that no file was loaded from outside the package.

The build entry runs the same verification and exits non-zero on failure.

The probe does not start WordPress: it has no database. CoreX's own CI goes one step further on every pull
request. The `client-site-layout` job builds the package for a generated client site, points it at that job's
database and runs `wp eval` and `wp corex routes:list` from the package.

> **Build the assets first.** Before `npm run build:dist`, run `npm ci && npm run build` at the repository root
> (the admin and block bundles are git-ignored build output, and the builder copies what is there), and build each
> client theme that has an asset pipeline: `cd sites/<client>/<client>-theme && npm ci && npm run build`. In CI,
> run these as steps before the `build:dist` step (the Azure pipeline's build stage is the place for it).

## First deploy checklist

1. `npm ci && npm run build`, and build the client theme (see above).
2. `npm run build:dist -- --client=acme` then `npm run verify:dist`.
3. Upload **the contents of `dist/`** to the host (see [Azure Pipelines](./azure-pipelines.md) for the automated path).
4. Create the target `wp-config.php` on the server (its own DB creds + fresh salts) — never ship the local one.
5. Import the database (`wp db export` locally → import on target) and run a URL search-replace:
   `wp search-replace http://acme.local https://acme.example --all-tables`.
6. Sync `wp-content/uploads/` separately (it is intentionally excluded from `dist/`).
7. Set file permissions; confirm the site boots; smoke-test the homepage + login.

The package contains no `.htaccess`. On a new Apache or LiteSpeed host, WordPress writes one when permalinks are
saved (**Settings → Permalinks → Save**) if it may write to the site root. Otherwise create it by hand from the
WordPress documentation.

`wp corex` is part of the package. On a host with no shell, where WP-CLI runs from a scheduled job, the commands
are there as soon as the upload is complete and the plugins are active.

## Update deploy checklist

1. Rebuild + verify `dist/` from the release tag.
2. Deploy `dist/` **excluding** runtime files (see protection list below).
3. Run any pending DB migrations (`wp corex migrate`); clear caches.
4. Smoke-test; keep the previous release available for rollback.

> **Coming from a package built by v0.43.3 or earlier.** That builder put `vendor/` in the package root, with the
> checkout's dev packages in it, and no plugin ever read it. If such a package was uploaded, delete `vendor/` from
> the site root on the host. The current layout has no root `vendor/`.

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

These hold target-specific config, user uploads, and caches. The builder leaves `wp-config.php` and `.htaccess`
out of the package, and never packages the other four. The SFTP example in `azure-pipelines.yml` excludes all
six; do the same for any manual upload, so a mirror with `--delete` does not remove them from the host either.
