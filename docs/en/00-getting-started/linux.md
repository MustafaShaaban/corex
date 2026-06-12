---
title: Local setup — Linux (Ubuntu / Debian)
description: Get a working Corex development environment on Ubuntu or Debian.
audience: setup
stability: stable
last_verified: null
---

# Local setup — Linux (Ubuntu / Debian)

This sets up Corex on a native Linux LAMP-style stack. The Windows bootstrap script
(`scripts/setup-wordpress.ps1`) is PowerShell-only, so on Linux you run the same steps **manually** — they are
all here, with expected output, and the monorepo is wired with **symlinks** instead of Windows junctions.

> Commands assume a `sudo`-capable user on Ubuntu 22.04+/Debian 12+. `apt` is the package manager.

## Before you start — prerequisites

### PHP 8.3 + extensions

**PHP** runs WordPress and Corex. Install 8.3 with the extensions WordPress needs.

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd
```

Verify:

```bash
php -v
```

```text
PHP 8.3.6 (cli) (built: ...) ( NTS )
```

### MariaDB (database)

**MariaDB** is the database server (a MySQL-compatible drop-in).

```bash
sudo apt install -y mariadb-server
sudo systemctl enable --now mariadb
```

Verify it is running:

```bash
sudo systemctl is-active mariadb
```

```text
active
```

### Composer

**Composer** is PHP's dependency manager.

```bash
sudo apt install -y composer
```

```bash
composer --version
```

```text
Composer version 2.7.0 ...
```

### Node.js + npm

**Node.js** runs the JS build; **npm** installs JS packages. Install the LTS (≥ 20).

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

```bash
node --version && npm --version
```

```text
v20.11.0
10.2.4
```

### WP-CLI

**WP-CLI** is WordPress's command line — you run Corex's `wp corex …` commands through it.

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp
```

```bash
wp --version
```

```text
WP-CLI 2.11.0
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

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS corex; CREATE USER IF NOT EXISTS 'corex'@'localhost' IDENTIFIED BY 'corex'; GRANT ALL ON corex.* TO 'corex'@'localhost'; FLUSH PRIVILEGES;"
```

```text
(no output on success)
```

## Step 3 — Download + configure + install WordPress into ./wp

```bash
wp core download --path=wp --skip-content --locale=en_US
wp config create --path=wp --dbname=corex --dbuser=corex --dbpass=corex --dbhost=localhost --dbprefix=cx_ --locale=en_US
wp db create --path=wp 2>/dev/null || true
wp core install --path=wp --url=http://localhost:8080 --title=Corex --admin_user=admin --admin_email=admin@example.com --admin_password=changeme --skip-email
```

```text
Success: WordPress downloaded.
Success: Generated 'wp-config.php' file.
Success: WordPress installed successfully.
```

## Step 4 — Wire the monorepo into wp-content (symlinks)

On Linux you map the framework source into WordPress with **symlinks** — the equivalent of the Windows
junctions the script makes. Run this from the repo root:

```bash
mkdir -p wp/wp-content/themes wp/wp-content/plugins

# theme/  ->  wp-content/themes/corex
ln -sfn "$(pwd)/theme" wp/wp-content/themes/corex

# every plugins/* and every addons/* that is plugin-shaped  ->  wp-content/plugins/<name>
for dir in plugins/* addons/*; do
  [ -d "$dir" ] && ln -sfn "$(pwd)/$dir" "wp/wp-content/plugins/$(basename "$dir")"
done
```

```text
(no output on success)
```

Confirm the links resolve:

```bash
ls -l wp/wp-content/themes/corex
```

```text
lrwxrwxrwx 1 you you 25 ... wp/wp-content/themes/corex -> /home/you/corex/theme
```

## Step 5 — Activate the theme + plugins

```bash
wp theme activate corex --path=wp
wp plugin activate corex-core corex-blocks corex-config corex-forms --path=wp
```

```text
Success: Switched to 'Corex' theme.
Plugin 'corex-core' activated.
...
```

## Step 6 — Serve and verify

For a quick check you can use WP-CLI's built-in server (or configure nginx/Apache for a real vhost):

```bash
wp server --path=wp --host=0.0.0.0 --port=8080
```

```text
Launching the PHP built-in web server at http://0.0.0.0:8080
```

In another terminal, verify the framework loaded:

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

> **Tip:** save Steps 2–5 as `scripts/setup-wordpress.sh` for your machine so you can re-run them after moving
> the repo (symlinks store absolute paths, like the Windows junctions — see
> [Troubleshooting](../07-troubleshooting/)).

## Where to next

- [Docker dev stack](../05-deployment/) (no native PHP/MariaDB needed) · [Team workflow](../04-team-workflow/) ·
  the **docs-app** site for the framework reference.

## See also

- [`scripts/setup-wordpress.ps1`](../../../scripts/setup-wordpress.ps1) (the Windows equivalent these steps
  mirror) · [Glossary](../../_glossary.md)
