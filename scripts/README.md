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

## `verify-framework.mjs`

Answers one question in a **client repository**: are the framework's files still the ones this
repository recorded? A client repository carries a copy of the framework and takes each release by
merging it, which only stays conflict-free while client work leaves framework-owned files alone.

```bash
npm run verify:framework                      # compare, one line per finding
node scripts/verify-framework.mjs --json      # the same result as one JSON object, and nothing else
npm run verify:framework -- --record v1.2.3   # write that release into every baseline record
```

It needs git and Node and nothing installed, so `node scripts/verify-framework.mjs` runs in CI
before any `npm ci`. Call it through `node` when the output is to be parsed: `npm run` prints its own
banner ahead of the script's.

What it compares: every path that is **not** client-owned, against the commit named in
`sites/<client>/corex-baseline.json`. Client-owned paths are the patterns in
`.github/repository-ownership.json`; everything else is the framework's, the repository root
included. Uncommitted changes and untracked files count.

| Line | Meaning | Fails the check |
|---|---|---|
| `DRIFT <path>` | A framework-owned path differs from the baseline. | yes |
| `EXCEPTION <path> <reason> <upstream>` | It differs, and the record lists it as a deliberate exception. | no |
| `STALE <path>` | An exception whose path no longer differs. Delete it from the record. | yes |
| `WARN <message>` | The recorded release tag exists and points at a different commit from the recorded one. | no |
| `FAIL <message>` | No record, an invalid record, two records naming different commits, a commit that is not in this repository, or `--record` given no release or one that does not exist. | yes |

Exit code `0` on a pass and `1` otherwise. In the framework's own repository there is no baseline
to compare against, and it reports that and passes.

A baseline record:

```json
{
  "release": "v1.2.3",
  "commit": "<the full 40-character commit hash>",
  "recorded": "2026-10-04",
  "exceptions": [
    {
      "path": "plugins/corex-core/src/Example.php",
      "reason": "Why this framework file is changed here.",
      "upstream": "https://github.com/MustafaShaaban/corex/issues/<number>"
    }
  ]
}
```

The comparison uses `commit`; `release` is the name people read. A shallow clone does not contain
the baseline commit, so CI has to check out full history.

`repository-ownership.mjs` and `framework-baseline.mjs` are the two pure modules behind it — the
first decides which paths are a client's and whether this checkout is the framework's own
repository, the second validates a record and divides changed paths into the lines above.

## Reusing Corex for a new website

Corex is a **framework**, not a site. Two ways to reuse it:

1. **Build a client site *on* Corex (normal case).** Create a *separate* project; Corex is the
   shared framework, and each site supplies its own brand (`theme.json` + `brand.json`) and content
   — design is *data*, not a fork (../docs/internal/COREX-FRAMEWORK.md §10, §24). One framework, many brands.
2. **Spin up another dev copy of the framework.** `git clone` this repo, then run
   `./scripts/setup-wordpress.ps1`.

Do **not** copy this repo to make a website, and do **not** move `theme/`/`plugins/` physically
into `wp-content` — that breaks the Composer/npm-workspace layout and would bury the framework
source inside the gitignored `./wp`. The junctions (or wp-env in Docker) are the bridge.
