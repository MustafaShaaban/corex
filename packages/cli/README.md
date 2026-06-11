# Corex CLI (`wp corex make:*`)

Stub-based generators that remove repetitive file creation. The generator **engine**
(render + write) is pure PHP and headless-testable; the **WP-CLI layer**
(`MakeCommand`, `CliServiceProvider`) is the only part that touches `WP_CLI` and is
registered only when WP-CLI is present (`class_exists('WP_CLI')`) — Principle IX.

## Requirements

WP-CLI, with the Corex plugins active. Target the install with `--path` when WP-CLI
isn't run from the WordPress root:

```bash
wp corex make:model Invoice --path=wp
```

## Where generated files go

Targets resolve from the Config engine (`config/app.php`, overridable by options or
`.env`):

| Config key | Default | Used for |
|---|---|---|
| `app.path` | `''` → falls back to `WP_CONTENT_DIR/corex-app` | base dir for generated files |
| `app.namespace` | `App` | namespace of generated classes |
| `app.prefix` | `corex` | post-type prefix, block name prefix, text domain |

## Commands

### Single-class generators

| Command | Creates | Path |
|---|---|---|
| `wp corex make:model <Name>` | a read-only `Corex\Models\Model` subclass | `<base>/Models/<Name>.php` |
| `wp corex make:repository <Name>` | a repository class | `<base>/Repositories/<Name>Repository.php` |
| `wp corex make:controller <Name>` | a thin controller | `<base>/Controllers/<Name>Controller.php` |
| `wp corex make:service <Name>` | a service class | `<base>/Services/<Name>Service.php` |

The name is normalized to a valid class identifier and the conventional suffix is
applied once (`make:repository Invoice` and `make:repository InvoiceRepository` both
produce `InvoiceRepository`). Existing files are **skipped** unless `--force` is
passed.

```bash
wp corex make:model Invoice
wp corex make:repository Invoice          # → InvoiceRepository
wp corex make:service Billing --force     # overwrite if it exists
```

### `make:block` — a complete dynamic block

```bash
wp corex make:block TeamMember
```

Scaffolds a **complete, registered, working dynamic block** — the same shape the
build pipeline expects (see `packages/build-tools/README.md`):

```
<base>/Blocks/
  team-member/
    block.json     # apiVersion 3, category "corex", editorScript + style wired,
                   #   corex.renderer → App\Blocks\TeamMemberRenderer
    index.js       # registerBlockType + <ServerSideRender>; imports style.scss
    style.scss     # token-only, RTL-correct
  TeamMemberRenderer.php   # implements Corex\Blocks\BlockRenderer (server render)
```

The renderer PHP lives **beside** the block folder in one `Blocks/` directory (the
corex-ui convention) — a single dir name also avoids a `blocks/`-vs-`Blocks/`
collision on case-insensitive filesystems. The name is given in PascalCase; the slug
(`team-member`), title (`Team Member`), block name (`<prefix>/team-member`), CSS
class (`<prefix>-team-member`), and renderer FQCN are all derived from it.

After scaffolding, compile the assets:

```bash
npm run build        # or: npm run start  (watch mode)
```

Existing blocks are **skipped** unless `--force` is passed.

## Adding a generator

Single-class generators extend `Corex\Cli\Generators\Generator` (a stub name, a
suffix, a sub-path, and the placeholder values) and pair with a `*.stub` under
`packages/cli/stubs/`. Register the instance in `CliServiceProvider::boot()`'s
`make:*` map. The multi-file block path is `BlockScaffolder` + the stub set under
`packages/cli/stubs/block/`.
