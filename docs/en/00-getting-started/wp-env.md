---
title: Local setup — wp-env (Docker)
description: Run Corex with wp-env — a one-command WordPress in Docker, no local PHP or database.
audience: setup
stability: stable
last_verified: null
---

# Local setup — wp-env (Docker)

This is the **zero-install** option: `wp-env` runs WordPress in Docker for you, mapping the Corex source in
automatically from the committed [`wp-env.json`](../../../wp-env.json). You do not install PHP, a database, or
WordPress yourself — only Docker and Node.

> **Scope note (be aware):** `wp-env.json` maps the **theme** and the three framework plugins
> (`corex-core`, `corex-blocks`, `corex-config`). The optional add-ons (`corex-ui`, `corex-email`, the kits,
> …) are **not** mapped here — wp-env is ideal for core framework work. To run the full add-on set, add their
> paths to `wp-env.json` `plugins`, or use one of the native setups
> ([WAMP](./windows-wamp.md) / [Linux](./linux.md) / [macOS](./macos.md)).

## Before you start — prerequisites

### Docker

**Docker** runs the WordPress stack in containers so you do not install PHP/MySQL on your machine.

- **Windows / macOS:** install **Docker Desktop** from <https://www.docker.com/products/docker-desktop/> and
  start it.
- **Linux:** install Docker Engine — <https://docs.docker.com/engine/install/> — then
  `sudo usermod -aG docker $USER` and re-log in.

Verify the daemon is running:

```bash
docker --version && docker ps
```

```text
Docker version 25.0.3, build ...
CONTAINER ID   IMAGE   COMMAND   CREATED   STATUS   PORTS   NAMES
```

### Node.js + npm

**Node.js** provides `npx`, which runs `wp-env`. Install the LTS (≥ 20) from <https://nodejs.org/>.

```bash
node --version && npm --version
```

```text
v20.11.0
10.2.4
```

## Step 1 — Get the code and install dependencies

```bash
git clone https://github.com/MustafaShaaban/corex.git
cd corex
npm install
```

```text
added 1400 packages in 45s
```

> `npm install` brings in `@wordpress/env`; the repo's `package.json` exposes it as `npm run env:start` /
> `npm run env:stop`.

## Step 2 — Start the environment

```bash
npm run env:start
```

```text
> corex-framework@0.1.0 env:start
> wp-env start

WordPress development site started at http://localhost:8888
WordPress test site started at http://localhost:8889
...
✔ Done!
```

The first run downloads the WordPress image and configures it (PHP 8.3, `WP_DEBUG` on) per `wp-env.json` — it
takes a few minutes. Subsequent starts are fast.

## Step 3 — Build the block assets

The blocks ship as source; compile them once (re-run after editing block JS/SCSS):

```bash
npm run build
```

```text
webpack compiled successfully
```

## Step 4 — Verify it boots

`wp-env` runs WP-CLI inside the container via `npx wp-env run cli wp …`:

```bash
npx wp-env run cli wp theme list
```

```text
| corex | active | none   | 0.1.0   |
```

```bash
npx wp-env run cli wp plugin list --status=active --field=name
```

```text
corex-core
corex-blocks
corex-config
```

Open **http://localhost:8888** (site) and **http://localhost:8888/wp-admin/** — the wp-env default admin is
`admin` / `password`.

## Everyday commands

```bash
npm run env:start          # start (or resume) the environment
npm run env:stop           # stop the containers
npx wp-env clean all       # reset the database to a fresh install
npx wp-env run cli wp ...  # run any WP-CLI / wp corex command inside the container
npx wp-env destroy         # remove the environment entirely
```

```text
✔ Done!
```

## Where to next

- The full custom **Docker dev stack** (php-fpm + nginx + MariaDB + redis + mailpit), if you need more than
  wp-env provides: [Deployment → Docker](../05-deployment/).
- [Team workflow](../04-team-workflow/) · the **docs-app** site for the framework reference.

## See also

- [`wp-env.json`](../../../wp-env.json) — the mapping this guide uses ·
  [wp-env docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) ·
  [Glossary](../../_glossary.md)
