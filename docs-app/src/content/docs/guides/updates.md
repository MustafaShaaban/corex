---
title: Updates & distribution
description: How Corex notifies sites of a new release, how to publish one, and exactly what an update touches (and never).
---

Corex updates the way WordPress plugins update: a site checks a source **you** control, and when a
newer release is published it appears in **Plugins → Updates** in wp-admin — one click to install.
Corex never phones home unless you configure a source (spec 034).

:::tip[The one rule that makes updates safe to click]
A framework update replaces **framework files only**. Your application code (`corex-app/`), your brand
(`brand.json`), your content, and your data are never touched.
:::

## How a site checks for updates

The Corex Core plugin declares an `Update URI` header, so WordPress routes its update check to Corex
instead of wordpress.org. On each check:

1. Corex reads `updates.endpoint` from config.
2. It fetches that URL (`wp_remote_get`) and reads the JSON manifest.
3. A pure `UpdateChecker` asks: is the manifest's `version` newer than the installed one (semver)?
4. If yes, a standard plugin-update object is injected into WordPress's update transient — the update
   shows in wp-admin like any plugin, and WP's own updater installs the package.

**Fail-safe.** An empty, unreachable, error, or malformed source is a silent no-op — the site reports
no update. A broken update source can never break a site (constitution Principle IX: the source is
optional config, never a hard dependency).

## Configure the endpoint

```dotenv
# .env (project root) — or a WordPress option
COREX_UPDATES_ENDPOINT=https://updates.example.com/corex/manifest.json
```

The shipped default is empty, so a fresh install never checks any remote source until you opt in.

## Publish a release

Host two artifacts where your sites can reach them (a static host, S3/CloudFront, or **GitHub Releases**):

**1. The package** — a zip of the framework plugin at the new version (plugin folder at the zip root),
built from a clean release tag.

**2. The manifest** — a small JSON document:

```json
{
  "version":  "0.22.0",
  "package":  "https://github.com/bseit/corex/releases/download/v0.22.0/corex-core.zip",
  "url":      "https://github.com/bseit/corex/releases/tag/v0.22.0",
  "requires": "7.0",
  "tested":   "7.0"
}
```

An update is offered only when `version` is **newer** than the installed version. Point `package`/`url`
at the new tag, upload, and every site with `updates.endpoint` set surfaces the update on its next check.

## The safe-edit boundary

This contract is what makes updating safe. An update is a plugin reinstall: WordPress replaces the
**framework plugin's files** and nothing else.

| Updated by an update (framework) | **Never** touched (yours) |
|---|---|
| `plugins/corex-*` (Core, Blocks, Forms, Config…) | `corex-app/` — your application code |
| Framework add-ons you installed | `brand.json` — your brand tokens |
| `packages/*` framework libraries | Database content — posts, pages, options |
| The theme scaffold/tokens shipped by Corex | Your data — custom tables, form submissions |

**Why your work survives:** everything *you* author lives outside the framework plugins — code in
`corex-app/`, identity in `brand.json` (deep-merged over the theme tokens), content/data in the database.
An update swaps framework files underneath that boundary; your layer sits on top, untouched.

**Stay upgrade-safe:** never edit framework files in place. Extend through the seams (service providers,
config overrides, `brand.json`, child customizations) — anything you change inside a framework plugin is
what an update overwrites.

## See also

- [Apply a brand](./branding.md) — the `brand.json` layer that updates never touch.
- [The CLI](./cli.md) — `wp corex reset` returns a site to a clean Corex starter (separate from updates).
