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

Beyond the generators, the CLI ships these operational commands:

| Command | Does |
|---|---|
| `wp corex docs:generate` | Regenerates the per-class internals reference from the source (AST-based, no class loading) into the docs site's `reference/`. Run it after code changes so the reference can't drift. |
| `wp corex reset` | Returns a Corex site to a clean state. **Soft** (default) deactivates add-ons, clears `corex_*` options + feature flags, and removes the wizard-seeded demo. **Full** (`--hard`) wipes the database to a fresh Corex starter. |
| `wp corex doctor` | Runs the Corex **health check** — the same probes shown in Tools → Site Health (PHP/WordPress version, a block theme active, brand tokens present, uploads writable) — and **exits non-zero on a critical finding**, so it works in CI and over SSH. |
| `wp corex version <semver>` | Stamps a release version across every framework plugin/theme header + `COREX_*_VERSION` constant in one step, so the headers never drift from the release tag. `--dry-run` previews the changes without writing. |

```bash
wp corex docs:generate                              # refresh the class reference

wp corex reset --dry-run                            # preview the soft reset (changes nothing)
wp corex reset                                      # soft reset
wp corex reset --hard --yes-i-mean-it --yes         # FULL reset — DB wipe (gated)

wp corex doctor                                     # health report (non-zero exit if critical)
wp corex version 0.22.0 --dry-run                   # preview the version stamp
wp corex version 0.22.0                             # stamp all framework headers/constants
```

The full reset is **destructive and irreversible**, so it refuses unless the typed safeguard `--yes-i-mean-it`
is passed in addition to WP-CLI's `--yes`. Without it, `--hard` prints what it *would* do and changes nothing.

### Health check (Site Health + `wp corex doctor`)

Corex registers its probes into WordPress's **Tools → Site Health** screen, so site owners see the same checks
in the admin. Probes are advisory where appropriate (a classic theme or a missing `brand.json` is a
recommendation, not a failure — Principle IX) and critical where they must be (an unsupported PHP/WordPress
version, a non-writable uploads directory). The pure engine (`HealthReport` + the probes) is unit-tested; the
Site Health registration and the CLI command are thin boundaries over it.

### Translations (`.pot`)

All Corex plugins and add-ons share the single literal `corex` text domain, loaded on `init` by corex-core.
Regenerate the template with `composer i18n:pot` (requires WP-CLI); it writes
`plugins/corex-core/languages/corex.pot`. Drop `corex-{locale}.po`/`.mo` files alongside it.

## Where files go

Targets come from the Config engine (`config/app.php`, overridable by options or `.env`):

| Key | Default | Used for |
|---|---|---|
| `app.path` | `WP_CONTENT_DIR/corex-app` | base directory |
| `app.namespace` | `App` | namespace of generated classes |
| `app.prefix` | `corex` | post-type / block-name / text-domain prefix |

See **[Create a block](/guides/blocks/)** for the `make:block` output in detail.
