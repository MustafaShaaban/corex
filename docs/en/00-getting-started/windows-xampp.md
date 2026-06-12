---
title: Local setup — Windows + XAMPP
description: Get a working Corex development environment on Windows using XAMPP.
audience: setup
stability: stable
last_verified: null
---

# Local setup — Windows + XAMPP

This is the **XAMPP** variant of the Windows setup. The same bootstrap script is used; only the server stack
and a couple of paths differ from the [Windows + WAMP](./windows-wamp.md) guide.

> If you are choosing between them: WAMP and XAMPP both bundle Apache + MySQL/MariaDB + PHP. Use whichever you
> already run. The Corex steps are identical apart from where the MySQL client lives and how you add a virtual
> host.

## Before you start — prerequisites

### XAMPP

**XAMPP** bundles Apache, MariaDB, and PHP for Windows.

- Install from <https://www.apachefriends.org/>. Pick a build with **PHP 8.3+**. Open the XAMPP Control Panel
  and **Start** Apache and MySQL.

Verify the bundled PHP:

```powershell
C:\xampp\php\php.exe -v
```

Expected output (must be 8.3+):

```text
PHP 8.3.6 (cli) (built: ...)
```

### Git, Composer, Node.js + npm, WP-CLI

These install identically on Windows regardless of the server stack. Follow the **same four sections** in the
[Windows + WAMP prerequisites](./windows-wamp.md#before-you-start--prerequisites), then verify:

```powershell
git --version; composer --version; node --version; wp --info
```

```text
git version 2.43.0.windows.1
Composer version 2.7.0 ...
v20.11.0
WP-CLI version: 2.11.0
```

> When you create the WP-CLI `wp.bat`, point its `@php` at XAMPP's PHP:
> `@php "C:\xampp\php\php.exe" "C:\path\to\wp-cli.phar" %*`.

## Step 1 — Get the code (into XAMPP's web root)

```powershell
cd C:\xampp\htdocs
git clone https://github.com/MustafaShaaban/corex.git corex
cd corex
```

```text
Cloning into 'corex'...
Resolving deltas: 100% ... done.
```

## Step 2 — Install dependencies and build

```powershell
composer install
npm install
npm run build
```

```text
Generating optimized autoload files
added 1400 packages in 45s
webpack compiled successfully
```

## Step 3 — Bootstrap WordPress (script + the XAMPP MySQL path)

The bootstrap script auto-detects **WAMP's** MySQL client. On XAMPP, tell it where MySQL lives with
`-MysqlBin`:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-wordpress.ps1 -MysqlBin "C:\xampp\mysql\bin"
```

```text
== Corex WordPress setup ==  repo: C:\xampp\htdocs\corex
Downloading WordPress core into ./wp ...
Database 'corex' created.
Installing WordPress ...
Wiring monorepo -> wp-content:
  junction  corex  ->  C:\xampp\htdocs\corex\theme
  ...
== Verification ==
| corex | active | none   | 0.1.0   |
Site : http://corex.local
Done.
```

The script does the same work as on WAMP: downloads WordPress into `./wp`, creates the `corex` database,
installs WP, and **junctions** the monorepo (`theme/`, `plugins/*`, `addons/*`) into `wp/wp-content`. It is
idempotent — re-run it after moving or renaming the repo folder.

## Step 4 — Map the hostname (XAMPP virtual host)

1. Add the host (Notepad as **Administrator**) to `C:\Windows\System32\drivers\etc\hosts`:

   ```text
   127.0.0.1 corex.local
   ```

2. Add a virtual host to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

   ```apache
   <VirtualHost *:80>
       ServerName corex.local
       DocumentRoot "C:/xampp/htdocs/corex/wp"
       <Directory "C:/xampp/htdocs/corex/wp">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. Restart Apache from the XAMPP Control Panel.

## Step 5 — Verify it boots

```powershell
wp theme list --path=wp
wp plugin list --path=wp --status=active --field=name
```

```text
| corex | active | none   | 0.1.0   |
corex-core
corex-blocks
corex-config
corex-forms
...
```

Open **http://corex.local** and **http://corex.local/wp-admin/** (`admin` / `changeme`).

Trouble? See [Troubleshooting](../07-troubleshooting/).

## Where to next

- [Docker dev stack](../05-deployment/) · [Team workflow](../04-team-workflow/) · the **docs-app** site for the
  framework reference ([what lives where](../../README.md#what-lives-where)).

## See also

- [Windows + WAMP](./windows-wamp.md) (the sibling guide) ·
  [`scripts/setup-wordpress.ps1`](../../../scripts/setup-wordpress.ps1) · [Glossary](../../_glossary.md)
