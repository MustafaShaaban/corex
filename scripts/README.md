# scripts/

Developer tooling for the Corex monorepo.

## `setup-wordpress.ps1`

Reproduces the local WordPress dev environment around the framework source — idempotent, and the
**one command to run after cloning or renaming the repo folder** (it recreates the `wp-content`
junctions for the repo's current path).

```powershell
# from the repo root
pwsh ./scripts/setup-wordpress.ps1

# override any value (all are parameters)
pwsh ./scripts/setup-wordpress.ps1 -SiteUrl http://corex.local -AdminEmail you@example.com -AdminPassword 's3cret'
```

What it does: installs WordPress into `./wp` (gitignored) → generates `wp-config.php` → creates the
DB → installs the site → junctions `theme/` and `plugins/*` into `wp/wp-content/` → activates the
Corex theme + plugins → verifies. It auto-detects the WAMP MySQL client and puts it on `PATH`.

Requirements: WP-CLI with the command bundle (`wp core`/`wp db` available), a running WAMP MySQL,
and the vhost (e.g. `corex.local`) with its docroot pointing at `<repo>/wp` plus a matching
`127.0.0.1 corex.local` hosts entry. See `DECISIONS.md` #18 and the constitution "Environment Gate".

## Reusing Corex for a new website

Corex is a **framework**, not a site. Two ways to reuse it:

1. **Build a client site *on* Corex (normal case).** Create a *separate* project; Corex is the
   shared framework, and each site supplies its own brand (`theme.json` + `brand.json`) and content
   — design is *data*, not a fork (COREX-FRAMEWORK.md §10, §24). One framework, many brands.
2. **Spin up another dev copy of the framework.** `git clone` this repo, then run
   `./scripts/setup-wordpress.ps1`.

Do **not** copy this repo to make a website, and do **not** move `theme/`/`plugins/` physically
into `wp-content` — that breaks the Composer/npm-workspace layout and would bury the framework
source inside the gitignored `./wp`. The junctions (or wp-env in Docker) are the bridge.
