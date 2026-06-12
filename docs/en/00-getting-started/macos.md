---
title: Local setup — macOS
description: Get a working Corex development environment on macOS with Homebrew.
audience: setup
stability: stable
last_verified: null
---

# Local setup — macOS

This sets up Corex natively on macOS using **Homebrew**. The WordPress install + monorepo wiring use the same
manual WP-CLI steps and **symlinks** as the [Linux guide](./linux.md) — only the prerequisite installs differ.

## Before you start — prerequisites

### Homebrew

**Homebrew** is the package manager for macOS — you install the rest of the tools with it.

- Install from <https://brew.sh> (paste the one-line command on that page).

```bash
brew --version
```

```text
Homebrew 4.2.0
```

### PHP 8.3, Composer, Node.js, MariaDB, WP-CLI

Install them all with Homebrew:

```bash
brew install php@8.3 composer node mariadb wp-cli
brew services start mariadb
```

Verify each (PHP must be 8.3+):

```bash
php -v && composer --version && node --version && wp --version
```

```text
PHP 8.3.6 (cli) ...
Composer version 2.7.0 ...
v20.11.0
WP-CLI 2.11.0
```

> If `php -v` shows an older version, link the new one: `brew link --overwrite --force php@8.3`, then re-check.

Confirm MariaDB is running:

```bash
brew services list | grep mariadb
```

```text
mariadb   started   you   ~/Library/LaunchAgents/...
```

## Step 1 — Get the code and install dependencies

```bash
git clone https://github.com/MustafaShaaban/corex.git
cd corex
composer install
npm install
npm run build
```

```text
Generating optimized autoload files
added 1400 packages in 45s
webpack compiled successfully
```

## Step 2 — Create the database

On a default Homebrew MariaDB the `root` user has no password and needs no `sudo`:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS corex; CREATE USER IF NOT EXISTS 'corex'@'localhost' IDENTIFIED BY 'corex'; GRANT ALL ON corex.* TO 'corex'@'localhost'; FLUSH PRIVILEGES;"
```

```text
(no output on success)
```

## Step 3 — Install WordPress + wire the monorepo

These steps are **identical to the Linux guide** — run
[Linux · Step 3 (install WordPress)](./linux.md#step-3--download--configure--install-wordpress-into-wp),
[Step 4 (symlink the monorepo)](./linux.md#step-4--wire-the-monorepo-into-wp-content-symlinks), and
[Step 5 (activate)](./linux.md#step-5--activate-the-theme--plugins). In short:

```bash
wp core download --path=wp --skip-content --locale=en_US
wp config create --path=wp --dbname=corex --dbuser=corex --dbpass=corex --dbhost=localhost --dbprefix=cx_ --locale=en_US
wp db create --path=wp 2>/dev/null || true
wp core install --path=wp --url=http://localhost:8080 --title=Corex --admin_user=admin --admin_email=admin@example.com --admin_password=changeme --skip-email

mkdir -p wp/wp-content/themes wp/wp-content/plugins
ln -sfn "$(pwd)/theme" wp/wp-content/themes/corex
for dir in plugins/* addons/*; do
  [ -d "$dir" ] && ln -sfn "$(pwd)/$dir" "wp/wp-content/plugins/$(basename "$dir")"
done

wp theme activate corex --path=wp
wp plugin activate corex-core corex-blocks corex-config corex-forms --path=wp
```

```text
Success: WordPress installed successfully.
Success: Switched to 'Corex' theme.
Plugin 'corex-core' activated.
...
```

## Step 4 — Serve and verify

```bash
wp server --path=wp --host=0.0.0.0 --port=8080
```

```text
Launching the PHP built-in web server at http://0.0.0.0:8080
```

In another terminal:

```bash
wp theme list --path=wp
wp plugin list --path=wp --status=active --field=name
```

```text
| corex | active | none   | 0.1.0   |
corex-core
corex-blocks
corex-config
corex-forms
```

Open **http://localhost:8080** and **/wp-admin/** (`admin` / `changeme`).

## Where to next

- [Docker dev stack](../05-deployment/) · [Team workflow](../04-team-workflow/) · the **docs-app** site.

## See also

- [Linux (Ubuntu/Debian)](./linux.md) (the sibling guide whose WP-CLI steps this shares) ·
  [Glossary](../../_glossary.md)
