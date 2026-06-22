---
title: WAMP / Apache + WP-CLI
description: Install WordPress into ./wp on a local WAMP stack with WP-CLI, step by step.
---

This is the setup used by this repository: WordPress installed into `./wp` (git-ignored),
served by your local Apache, driven by WP-CLI.

## 1. Install PHP dependencies

```bash
composer install
```

## 2. Create the database

Create an empty MySQL database (this repo uses `corex`). With the MySQL client on your
`PATH`:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS corex CHARACTER SET utf8mb4;"
```

:::caution
Creating/dropping a database and writing `wp-config.php` are the steps you do yourself —
they are outside the framework. The commands below assume DB `corex` exists.
:::

## 3. Download + configure + install WordPress

Run from the repo root; WordPress core goes into `./wp` (git-ignored):

```bash
wp core download --path=wp --version=7.0
wp config create --path=wp --dbname=corex --dbuser=root --dbpass= --dbhost=127.0.0.1 --dbprefix=cx_
wp core install --path=wp \
  --url=http://corex.local --title="Corex" \
  --admin_user=admin --admin_password=123456 --admin_email=you@example.com
```

Point an Apache vhost `corex.local` at the **`wp/`** directory (DocumentRoot), then add
`127.0.0.1 corex.local` to your hosts file.

## 4. Starting services without admin rights (WAMP)

If the WAMP tray can't elevate, launch MySQL directly:

```powershell
Start-Process "C:\wamp64\bin\mysql\mysql8.3.0\bin\mysqld.exe" `
  -ArgumentList '--defaults-file="C:\wamp64\bin\mysql\mysql8.3.0\my.ini"' -WindowStyle Hidden
```

WP-CLI and the test suites only need MySQL. A browser smoke test needs Apache — start
full WAMP from the tray.

## Creating a named local company site

The values above (`corex.local`, DB `corex`, title `Corex`) are just a **default dev example** — `corex` is not
a required database or site name. For a real client, use a **named** local site so the URL, database, and title
read like the project. Using the neutral placeholder **Acme**:

```bash
wp core download --path=wp --version=7.0
wp config create --path=wp --dbname=acme --dbuser=root --dbpass= --dbhost=127.0.0.1 --dbprefix=acme_wp_
wp core install --path=wp \
  --url=http://acme.local --title="Acme Website" \
  --admin_user=admin --admin_password=123456 --admin_email=you@example.com
```

Create the database first (`mysql -u root -e "CREATE DATABASE IF NOT EXISTS acme CHARACTER SET utf8mb4;"`), add
`127.0.0.1 acme.local` to your hosts file, and point the Apache vhost `acme.local` `DocumentRoot` at
`C:\wamp64\www\corex\wp`.

> The local site URL/DB/title are only how the framework *runs* locally. Your client's brand, structure, and
> content live in the **generated client site** (`wp corex make:site Acme`), not in these install values. See
> [Start your first company site](/getting-started/company-site/).

### If you already ran the default `corex` setup

Switching an existing install to a named site means changing its database and config. Do it deliberately:

1. **Inspect first.** Check whether `wp/wp-config.php` already exists and which DB it points at:
   ```bash
   wp config get DB_NAME --path=wp
   ```
2. **Back up anything you need** — export the current DB (`wp db export backup.sql --path=wp`) and copy
   `wp/wp-content/uploads/` if it holds work you want to keep.
3. **Safe reset.** The simplest clean path is a fresh install into a fresh database with the named values above.
   Only after your backup: drop/recreate the DB and re-run the install. **Deleting the `wp/` directory or
   dropping a database is destructive and irreversible** — confirm the target name before running it, and never
   run it against a staging/production database.

## Next

Map the monorepo into this install → **[Wiring the monorepo](/getting-started/monorepo-wiring/)**, then follow
**[Start your first company site](/getting-started/company-site/)**.
