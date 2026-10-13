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

**Symptom:** with `WP_DEBUG` on, every request adds this to `wp-content/debug.log`:

```text
PHP Notice:  Function _load_textdomain_just_in_time was called incorrectly. Translation
loading for the `corex` domain was triggered too early.
```

DECISIONS #55 records "headers already sent" warnings beside it.

**Cause:** something read a translated string, or the theme's settings, while CoreX was
booting on `plugins_loaded`. WordPress has not settled the request's language by then, nor
which way its text runs. CoreX did this itself twice: before DECISIONS #55, and again in
v0.45.1 and v0.45.2, where an email drawn through the shared layout was also left-to-right
on a right-to-left site (DECISIONS #351).

**Fix:** update to the release after v0.45.2. A request then boots without the notice, and
`tests/Integration/Foundation/BootTranslationsTest.php` fails if a string of the `corex`
domain is read before `init` again.

If the notice names your own plugin's text domain, or still names `corex` after the update,
your code is asking for something translated too early. Do not make an object in a service
provider's `boot()` only to hook one action, because the object brings whatever its
constructor needs. Hook the action with a closure and make the object there:

```php
add_action('admin_post_my_action', function (): void {
    $this->container->make(MyHandler::class)->handle();
});
```

## See also

- [Getting started](../00-getting-started/) · [Deployment](../05-deployment/) · the deploy recipe for your
  target.
