---
title: Getting started
description: Get Corex running on your machine — one guide per operating system.
audience: setup
stability: stable
last_verified: null
---

# Getting started

Local setup for a developer with **zero** prior Corex experience. Pick your operating system — each guide
introduces every tool it needs and ends with a booting site.

## Guides

| Guide | For | When to choose it |
|---|---|---|
| [Windows + WAMP](./windows-wamp.md) | Windows + WAMP | You run WAMP; one script does the whole bootstrap. |
| [Windows + XAMPP](./windows-xampp.md) | Windows + XAMPP | You run XAMPP; same script, XAMPP paths. |
| [Linux (Ubuntu / Debian)](./linux.md) | native Linux | apt-based stack; manual WP-CLI flow + symlinks. |
| [macOS](./macos.md) | native macOS | Homebrew stack; shares the Linux WP-CLI flow. |
| [wp-env (Docker)](./wp-env.md) | any OS, zero install | You have Docker + Node and want one command — best for core framework work. |

Each follows the getting-started shape in
[`contracts/page-contract.md`](../../../specs/028-developer-handbook/contracts/page-contract.md): tool intros,
command → expected output, the monorepo → `wp-content/` mapping (junctions on Windows, symlinks on Linux/macOS,
auto-mapped by Docker), and a boot verification (`wp theme list` shows `corex`).

> **Not sure which?** If you already run WAMP or XAMPP, use that. If you want the least setup and have Docker,
> use [wp-env](./wp-env.md). For the full add-on set with native performance, use
> [Linux](./linux.md) / [macOS](./macos.md) / [WAMP](./windows-wamp.md).

## After setup

- Learn how the team works: [Team workflow](../04-team-workflow/).
- Deploy it: [Deployment](../05-deployment/).
- Learn the framework + look up classes: the **docs-app** site
  ([what lives where](../../README.md#what-lives-where)).
