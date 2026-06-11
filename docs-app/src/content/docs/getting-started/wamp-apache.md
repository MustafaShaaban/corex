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

## Next

Map the monorepo into this install → **[Wiring the monorepo](/getting-started/monorepo-wiring/)**.
