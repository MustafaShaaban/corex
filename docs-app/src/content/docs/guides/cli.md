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

## Where files go

Targets come from the Config engine (`config/app.php`, overridable by options or `.env`):

| Key | Default | Used for |
|---|---|---|
| `app.path` | `WP_CONTENT_DIR/corex-app` | base directory |
| `app.namespace` | `App` | namespace of generated classes |
| `app.prefix` | `corex` | post-type / block-name / text-domain prefix |

See **[Create a block](/guides/blocks/)** for the `make:block` output in detail.
