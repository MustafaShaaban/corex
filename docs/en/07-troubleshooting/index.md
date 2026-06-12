---
title: Troubleshooting
description: The real errors you may hit during setup, building, and deployment — and how to fix them.
audience: setup
stability: stable
last_verified: null
---

# Troubleshooting

Concrete problems and fixes — the issues that actually come up, with the cause and the resolution. If your
problem is not here, check the deploy recipe for your target and the
[getting-started guide](../00-getting-started/) for your OS.

## The site broke after I moved or renamed the repo folder

**Cause:** the monorepo is mapped into `wp-content` with junctions (Windows) or symlinks (Linux/macOS) that
store the repo's **absolute path**. Moving/renaming the repo breaks all of them.

**Fix (Windows):** re-run the bootstrap script — it recreates the junctions for the current path:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-wordpress.ps1
```

```text
Wiring monorepo -> wp-content:
  junction  corex  ->  C:\new\path\corex\theme
  ...
```

**Fix (Linux/macOS):** re-create the symlinks from the repo root:

```bash
ln -sfn "$(pwd)/theme" wp/wp-content/themes/corex
for d in plugins/* addons/*; do ln -sfn "$(pwd)/$d" "wp/wp-content/plugins/$(basename "$d")"; done
```

```text
(links now point at the new path)
```

## The editor says "Your site doesn't include support for this block"

**Cause:** the block's compiled assets are missing — the dynamic blocks ship as source and must be built.

**Fix:**

```bash
npm run build
```

```text
webpack compiled successfully
```

Then reload the editor. (In Docker: `docker compose exec php npm run build`.)

## "Error establishing a database connection"

**Cause:** the database is not running, or `wp-config.php` has the wrong host/credentials.

**Fix:** confirm the DB service is up and the values match your setup.

```bash
# native Linux/macOS:
systemctl is-active mariadb      # or: brew services list | grep mariadb
# any: test the credentials WordPress is using
wp db check --path=wp
```

```text
Success: Database checked.
```

On Docker, the DB host is the service name `db`, not `localhost` (see
[docker.md](../05-deployment/docker.md)). On WAMP, ensure the tray icon is **green**.

## Permission or file-ownership errors (Linux / Docker)

**Cause:** the web-server user cannot read/write `wp-content`.

**Fix:** give ownership to the web user:

```bash
sudo chown -R www-data:www-data wp/wp-content
```

```text
(no output on success)
```

## WP-CLI: "This does not seem to be a WordPress installation"

**Cause:** you ran `wp` without pointing it at the `./wp` directory.

**Fix:** add `--path=wp` (Corex installs WordPress in `./wp`, not the repo root):

```bash
wp plugin list --path=wp
```

```text
| corex-core | active | none | ... |
```

## HTTPS / certificate problems on a VM deploy

**Cause:** the domain's DNS does not yet point at the server, or ports 80/443 are closed, so Certbot cannot
validate.

**Fix:** confirm DNS resolves to the server and the firewall allows web traffic, then re-run Certbot:

```bash
sudo ufw allow 'Nginx Full'
sudo certbot --nginx -d corex.example.com
```

```text
Congratulations! ... https://corex.example.com
```

## Boot notices / "headers already sent" in the debug log

**Cause:** historically, eagerly building the mail stack at `plugins_loaded` loaded the textdomain too early.
This is fixed (DECISIONS #55 — lazy worker registration); a normal request boots with **zero** notices.

**Fix:** ensure you are on a current version; if you see early-boot textdomain notices in a custom add-on,
register hooks lazily (on `init`, not `plugins_loaded`).

## See also

- [Getting started](../00-getting-started/) · [Deployment](../05-deployment/) · the deploy recipe for your
  target.
