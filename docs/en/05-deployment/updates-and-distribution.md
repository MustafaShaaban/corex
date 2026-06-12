---
title: Updates & distribution — self-update, manifests, and the safe-edit boundary
description: How Corex notifies sites of a new framework release, how to publish a manifest + package, and exactly what an update does (and never) touches.
audience: ops
stability: stable
last_verified: null
---

# Updates & distribution

Corex updates the way WordPress plugins update: a site checks a source you control, and when a newer
release is published the update appears in **Plugins → Updates** in wp-admin, one click to install. There
is no separate update mechanism to learn and no auto-pinging of any Corex server — Corex never phones home
unless **you** configure a source (spec 034).

> **The one rule that makes updates safe to click:** a framework update replaces **framework files only**.
> Your application code, your brand, your content, and your data are never touched. The
> [safe-edit boundary](#the-safe-edit-boundary) below is exact about which is which.

## How a site checks for updates

The Corex Core plugin declares an `Update URI` header, which tells WordPress to route its update check to
Corex instead of wordpress.org. On each check (the normal twice-daily WP cron, or a manual "Check again"):

1. Corex reads the configured **manifest endpoint** from config (`updates.endpoint`).
2. It fetches that URL with `wp_remote_get` and reads the JSON manifest.
3. A pure version comparison (`UpdateChecker`) asks: is the manifest's `version` newer than the installed
   version (semver)?
4. If yes, Corex injects a standard plugin-update object into WordPress's update transient — so the update
   shows in wp-admin exactly like any plugin update. WordPress's own updater downloads and installs the
   package over the framework plugin.

**Fail-safe by design.** If `updates.endpoint` is empty, unreachable, returns an error, or returns
malformed JSON, the check is a silent no-op — the site simply reports no update available. A broken or
absent update source can never break a site. The update source is optional configuration, never a hard
dependency (Constitution Principle IX).

## Configure the endpoint

Set `updates.endpoint` to the URL of your manifest. As with all Corex config, the value can come from a
WordPress option or the project-root `.env` (the latter wins):

```dotenv
# .env (project root)
COREX_UPDATES_ENDPOINT=https://updates.example.com/corex/manifest.json
```

The shipped default is empty (`plugins/corex-core/config/app.php`), so a fresh install never checks any
remote source until you opt in.

## Publish a manifest + package

You need two artifacts on a host you control (a static file host, S3/CloudFront, or **GitHub Releases** all
work — the manifest is just a JSON file and the package is just a zip):

### 1. The package

A zip of the framework plugin at the new version — the same layout WordPress expects for any plugin zip
(the plugin folder at the zip root). Build it from a clean release tag.

### 2. The manifest

A small JSON document describing the latest release:

```json
{
  "version":  "0.22.0",
  "package":  "https://github.com/bseit/corex/releases/download/v0.22.0/corex-core.zip",
  "url":      "https://github.com/bseit/corex/releases/tag/v0.22.0",
  "requires": "7.0",
  "tested":   "7.0"
}
```

| Field | Meaning |
|---|---|
| `version` | The release version. An update is offered only when this is **newer** than the installed version. |
| `package` | Direct download URL of the release zip. WordPress fetches and installs this. |
| `url` | Human-facing release page (shown in the "View details" popup). |
| `requires` | Minimum WordPress version. |
| `tested` | WordPress version tested against. |

To cut a release: tag the framework, build the zip, upload both the zip and an updated `manifest.json`,
and point `package`/`url` at the new tag. Every site with `updates.endpoint` set will surface the update
on its next check.

> **Versioning note.** Aligning the plugin/theme header versions to the release tag is handled in spec 036
> (versioning alignment). Until then, the manifest `version` is the source of truth for what "newer" means.

## The safe-edit boundary

This is the contract that makes updating safe. A framework update is a plugin reinstall: WordPress replaces
the **framework plugin's files** and nothing else.

| Updated by a framework update (framework files) | **Never** touched by an update (yours) |
|---|---|
| `plugins/corex-*` (Core, Blocks, Forms, Config…) | `corex-app/` — your application code |
| `addons/corex-*` framework add-ons you installed | `brand.json` — your brand tokens |
| `packages/*` framework libraries | Your theme's content & customizations |
| The theme **scaffold/tokens** shipped by Corex | **Database content** — posts, pages, options |
| | **Your data** — custom tables, form submissions |
| | WordPress core & third-party plugins |

**Why your work survives.** Corex is built so that everything *you* author lives outside the framework
plugins — your code in `corex-app/`, your identity in `brand.json` (deep-merged over the theme tokens, see
[theme tokens](../00-getting-started/)), and all content/data in the database. An update swaps framework
files underneath that boundary; your layer sits on top, untouched.

**The rule for staying upgrade-safe:** never edit framework files in place. Don't patch `plugins/corex-*`
or the shipped theme tokens directly — extend through the seams Corex gives you (service providers, config
overrides, `brand.json`, child customizations). Anything you change inside a framework plugin is what an
update will overwrite. Everything you build through the documented extension points is yours to keep.

## Verification status

The version logic (`UpdateChecker`) and the transient injection + fail-safe (`UpdateService`) are covered by
unit tests (`tests/Unit/Update/`). The full install-from-admin round-trip is environment-gated: it needs a
published manifest + package and a browser, so `last_verified` stays `null` until run against a real release.

## See also

- [CI/CD](./ci-cd.md) — how releases are built and tagged.
- [Secrets, backups, zero-downtime](./secrets-backups-zero-downtime.md) — back up before you update.
- `wp corex reset` — return a site to a clean Corex starter (separate from updates).
