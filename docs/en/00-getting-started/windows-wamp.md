---
title: Local setup — Windows + WAMP
description: Get a working Corex development environment on Windows using WAMP, end to end.
audience: setup
stability: stable
last_verified: null        # stamped by the phase-D12 verification pass
---

# Local setup — Windows + WAMP

By the end of this page you will have the Corex framework source checked out, a local WordPress install in
`./wp`, the monorepo wired into WordPress, and the site booting — verified with two commands.

> **Why a separate WordPress install?** Corex is a *framework*, not a site. This repository holds the framework
> **source** (`theme/`, `plugins/*`, `addons/*`); a throwaway WordPress lives in `./wp` (git-ignored) so you
> have something to run the framework inside. See the [glossary → Monorepo mapping](../../_glossary.md).

This is the **scripted** path: one PowerShell script does the whole bootstrap and is safe to re-run.

## Before you start — prerequisites

Install each tool below. Each is introduced once; if you already have it, run its verify command to confirm.

### WAMP

**WAMP** bundles Apache (web server), MySQL/MariaDB (database), and PHP for Windows — the stack WordPress runs on.

- Download and install from <https://www.wampserver.com/en/>. Start it and wait for the tray icon to turn **green**.

Verify PHP is the bundled 8.3+:

```powershell
C:\wamp64\bin\php\php8.3.6\php.exe -v
```

Expected output (version may differ, must be 8.3+):

```text
PHP 8.3.6 (cli) (built: ...)
```

### Git

**Git** is the version-control tool you use to clone the repository.

- Install from <https://git-scm.com/download/win>.

```powershell
git --version
```

```text
git version 2.43.0.windows.1
```

### Composer

**Composer** is PHP's dependency manager — it installs the PHP packages Corex needs.

- Install from <https://getcomposer.org/download/> (the Windows installer finds WAMP's PHP automatically).

```powershell
composer --version
```

```text
Composer version 2.7.0 ...
```

### Node.js + npm

**Node.js** runs JavaScript tooling; **npm** (bundled with it) installs the JS packages and builds the block
assets.

- Install the LTS from <https://nodejs.org/> (≥ 20).

```powershell
node --version
npm --version
```

```text
v20.11.0
10.2.4
```

### WP-CLI

**WP-CLI** is the command-line interface for WordPress — you run Corex's `wp corex …` commands and the setup
script through it.

- Download `wp-cli.phar` from <https://wp-cli.org/>, then create `C:\wamp64\bin\wp.bat` containing
  `@php "C:\path\to\wp-cli.phar" %*` and add its folder to your `PATH`.

```powershell
wp --info
```

```text
PHP binary:     C:\wamp64\bin\php\php8.3.6\php.exe
PHP version:    8.3.6
WP-CLI version: 2.11.0
```

## Step 1 — Get the code

```powershell
cd C:\wamp64\www
git clone https://github.com/MustafaShaaban/corex.git corex
cd corex
```

```text
Cloning into 'corex'...
remote: Enumerating objects: ...
Resolving deltas: 100% ... done.
```

> Cloning into `C:\wamp64\www\corex` means WAMP serves it and the local hostname below resolves to it.

## Step 2 — Install dependencies

```powershell
composer install
npm install
```

```text
Installing dependencies from lock file ...
Generating optimized autoload files
...
added 1400 packages in 45s
```

## Step 3 — Build the block assets

The dynamic blocks ship as source; this compiles their editor scripts + styles.

```powershell
npm run build
```

```text
> corex-framework@0.1.0 build
...
webpack compiled successfully
```

## Step 4 — Bootstrap WordPress (the one script)

This single script downloads WordPress into `./wp`, creates the database, installs WP, **junctions** the
monorepo into `wp-content`, and activates the theme + plugins. It is **idempotent** — safe to re-run, and the
command to run again if you ever rename or move the repo folder.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-wordpress.ps1
```

```text
== Corex WordPress setup ==  repo: C:\wamp64\www\corex
Downloading WordPress core into ./wp ...
Creating wp-config.php ...
Database 'corex' created.
Installing WordPress ...
Wiring monorepo -> wp-content:
  junction  corex  ->  C:\wamp64\www\corex\theme
  junction  corex-core  ->  C:\wamp64\www\corex\plugins\corex-core
  ...
== Verification ==
+-------+--------+--------+---------+
| name  | status | update | version |
+-------+--------+--------+---------+
| corex | active | none   | 0.19.0  |
+-------+--------+--------+---------+
Site : http://corex.local
Done.
```

> **What "junction" means here:** a Windows directory junction (`mklink /J`) points `wp/wp-content/themes/corex`
> at the repo's `theme/` folder, so WordPress runs the *source* directly — no copying. Real symlinks need an
> elevated shell; junctions do not. (DECISIONS #18.)

## Step 5 — Map the hostname (one time)

The script installs the site at `http://corex.local`. Point that name at your machine:

1. Open Notepad **as Administrator** → open `C:\Windows\System32\drivers\etc\hosts` → add:

   ```text
   127.0.0.1 corex.local
   ```

2. Add a WAMP virtual host for `corex.local` pointing at `C:\wamp64\www\corex\wp` (WAMP tray →
   *Your VirtualHosts*, or edit `httpd-vhosts.conf`), then restart WAMP.

## Step 6 — Verify it boots

```powershell
wp theme list --path=wp
```

```text
+-------+--------+--------+---------+
| name  | status | update | version |
+-------+--------+--------+---------+
| corex | active | none   | 0.19.0  |
+-------+--------+--------+---------+
```

```powershell
wp plugin list --path=wp --status=active --field=name
```

```text
corex-core
corex-blocks
corex-config
corex-forms
...
```

Open **http://corex.local** (the site) and **http://corex.local/wp-admin/** (admin: `admin` / `changeme`).

If anything failed, see [Troubleshooting](../07-troubleshooting/).

## Where to next

- Run it in containers instead: [Docker dev stack](../05-deployment/) (no local PHP/MySQL needed).
- Learn the workflow: [Team workflow](../04-team-workflow/).
- Learn the framework + look up classes: the published **docs-app** site (see
  [What lives where](../../README.md#what-lives-where)).

## See also

- The script itself: [`scripts/setup-wordpress.ps1`](../../../scripts/setup-wordpress.ps1) — the source of truth
  for this guide.
- [Glossary](../../_glossary.md) · [Translation memory](../../_translation-memory.md)
