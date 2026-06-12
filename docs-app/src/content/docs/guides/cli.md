---
title: The CLI
description: wp corex make:* — remove repetitive file creation.
---

The Corex CLI registers `wp corex make:*` when WP-CLI is present. The generator engine is
pure PHP (headless-testable); only the command layer touches WP-CLI.

Target the install with `--path` when not run from the WordPress root.

## Commands

| Command | Creates |
|---|---|
| `wp corex make:model <Name>` | a read-only `Corex\Models\Model` subclass → `<base>/Models/<Name>.php` |
| `wp corex make:repository <Name>` | a repository → `<base>/Repositories/<Name>Repository.php` |
| `wp corex make:controller <Name>` | a thin controller → `<base>/Controllers/<Name>Controller.php` |
| `wp corex make:service <Name>` | a service → `<base>/Services/<Name>Service.php` |
| `wp corex make:block <Name>` | a complete dynamic block → `<base>/Blocks/<slug>/` + renderer |

```bash
wp corex make:model Invoice
wp corex make:repository Invoice        # → InvoiceRepository (suffix applied once)
wp corex make:service Billing --force   # overwrite if it exists
wp corex make:block Pricing             # then: npm run build
```

Existing files are **skipped** unless `--force` is passed.

## Operational commands

Beyond the generators, the CLI ships two operational commands:

| Command | Does |
|---|---|
| `wp corex docs:generate` | Regenerates the per-class internals reference from the source (AST-based, no class loading) into the docs site's `reference/`. Run it after code changes so the reference can't drift. |
| `wp corex reset` | Returns a Corex site to a clean state. **Soft** (default) deactivates add-ons, clears `corex_*` options + feature flags, and removes the wizard-seeded demo. **Full** (`--hard`) wipes the database to a fresh Corex starter. |

```bash
wp corex docs:generate                              # refresh the class reference

wp corex reset --dry-run                            # preview the soft reset (changes nothing)
wp corex reset                                      # soft reset
wp corex reset --hard --yes-i-mean-it --yes         # FULL reset — DB wipe (gated)
```

The full reset is **destructive and irreversible**, so it refuses unless the typed safeguard `--yes-i-mean-it`
is passed in addition to WP-CLI's `--yes`. Without it, `--hard` prints what it *would* do and changes nothing.

## Where files go

Targets come from the Config engine (`config/app.php`, overridable by options or `.env`):

| Key | Default | Used for |
|---|---|---|
| `app.path` | `WP_CONTENT_DIR/corex-app` | base directory |
| `app.namespace` | `App` | namespace of generated classes |
| `app.prefix` | `corex` | post-type / block-name / text-domain prefix |

See **[Create a block](/guides/blocks/)** for the `make:block` output in detail.
