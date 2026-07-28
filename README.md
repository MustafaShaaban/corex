# Corex

A professional, Laravel-inspired framework for building WordPress sites — corporate, e-commerce,
multisite, headless, or AI-agent-driven — on one clean, documented, spec-first foundation.

- **Target:** WordPress 7.0+, PHP 8.3+, FSE block themes.
- **Namespace:** `Corex\` · **CLI:** `wp corex` · **CSS prefix:** `--corex-`.
- **Stack:** Composer (PHP) + npm workspaces (JS), one monorepo.
- **License:** GPL-2.0-or-later.

## Status: v0.40.0, actively developed, honest about its gaps

The foundation is stable and used: the engine, the block/forms/config layers, the admin product, the
CLI generators, Corex Mail, and the docs site. Verification runs on every pull request — **1704 PHP
unit tests, 356 integration tests against a WordPress that CI provisions itself, 431 JavaScript
tests, and 120 browser tests**.

Several add-ons are partial, one site kit is a reserved seam rather than a kit, and three browser
specs are excluded from a fresh-install run. None of that is hidden:

### 👉 **[PROJECT-STATUS.md](PROJECT-STATUS.md) — what works, what is partial, what is not built**

Every entry there names the file that records it, so you can check any line of it. Read that before
adopting this for anything real. `ROADMAP.md` is the durable plan behind it.

## What's in the box

```
plugins/
  corex-core/        MVC engine — Boot, PSR-11 container, providers, controllers, services,
                     repositories, Model + Field driver, QueryBuilder, middleware pipeline,
                     events, Config + feature flags, the response envelope + window.Corex runtime.
  corex-blocks/      block engine — auto-discovery, Block-Bindings connectors, conditional assets.
  corex-config/      settings + .env resolution, the admin control panel, Data screen, Insights.
  corex-forms/       forms engine — schema + shared validation, secured REST submit, form block.
addons/              optional, self-disabling packages (never hard dependencies):
  corex-ui/          server-rendered corex/* blocks + patterns + the Design Language System catalog.
  corex-captcha/     pluggable captcha (honeypot + remote providers) with a verification endpoint.
  corex-media/       WebP-on-upload + <picture> helper + image-support probe.
  corex-email/       Corex Mail — versioned Email Studio, safe routing/capture, attempts, and queued mail.
  corex-newsletter/  double-opt-in subscriber capture.
  corex-careers/     jobs CPT + application flow.
  corex-bookings/    booking/call-request flow.
  corex-kit-company/ corex-kit-portfolio/ corex-kit-woo/   site kits (blueprints + content).
packages/
  cli/               wp corex commands (make:*, make:site, reset, docs:generate, routes:list, …) + stubs.
  build-tools/       shared @wordpress/scripts build configuration.
theme/               parent FSE block theme — presentation only (theme.json tokens, templates, parts).
docs-app/            Astro + Starlight documentation site (guides + generated class reference).
docs/               bilingual (EN/AR) getting-started, team-workflow, deployment, and cookbook docs.
specs/              Spec Kit specs (constitution pointer → .specify/memory/).
tests/              Pest (Unit, Integration), Jest, Playwright (e2e).
```

## How this repository records itself

If the file sizes look strange — `PROGRESS.md` at 420 KB, `DECISIONS.md` with 201 numbered entries,
`specs/` with 86 directories — that is the working method, not debris left behind.

| File | What it is |
|---|---|
| `specs/` | The reviewed contract for every feature, **written before its code**. Nothing non-trivial is built without one. |
| `.specify/memory/constitution.md` | The non-negotiable rules. Where code and constitution disagree, the code is wrong. |
| `DECISIONS.md` | Why anything non-obvious is the way it is — including the decisions that turned out wrong. |
| `PROGRESS.md` | The running session log. Detailed and long; `PROJECT-STATUS.md` is the summary you want first. |
| `CLAUDE.md` / `AGENTS.md` | Orientation for coding agents. |

These are kept deliberately. Several defects this project has closed were found by reading what it
had previously *claimed* about itself — a framework can describe a capability it does not have, and
the written record is what makes that catchable.

### Read first (agents and humans)

1. `PROJECT-STATUS.md` — what is finished and what is not.
2. `specs/constitution.md` → `.specify/memory/constitution.md` — the non-negotiable rules.
3. `COREX-FRAMEWORK.md` (architecture) and `COREX-WORKING-GUIDE.md` (how we work).
   `COREX-EMAIL-ADDON.md` is the Corex Mail spec; `COREX-SPECKIT-START.md` the build order.

## Start here: your first company site

New to Corex and building a real site? Follow this path (the full walkthrough is the docs-app guide
**Start your first company site**, source: `docs-app/src/content/docs/getting-started/company-site.md`):

1. **Install Corex locally** — pick one stack: WAMP/XAMPP **or** Docker/wp-env (Docker is optional).
2. **Use a named local URL + database** for the site (e.g. `acme.local` / DB `acme`) — `corex` is only the
   default dev example, not a required name.
3. **Verify Corex boots** — `wp --path=wp corex doctor`.
4. **Required foundation** (always active, not toggleable): `corex-core`, `corex-blocks`, `corex-config`,
   `corex-forms`.
5. **Optional add-ons** — enable by need; recommended for a company site: `corex-ui`, `corex-kit-company`,
   `corex-media`. You don't need them all.
6. **Generate the site** — `wp corex make:site Acme` (a client plugin + theme with its own namespace).
7. **Apply the Company Site Kit** where appropriate.
8. **Customize the generated client theme** — brand via tokens; structural header/footer changes via client
   theme template-part overrides. **Never edit Corex framework internals for one client.**
9. **Build & deploy** a flat `dist/` artifact — never the local symlinked `wp/`.

> **Privacy:** keep real client names out of the Corex framework repo/docs — use a neutral placeholder
> (this project uses **Acme**). The real name belongs only in the generated client site.

## Local development

Corex is a WordPress framework: it runs inside a WordPress install. The monorepo is mapped into
`wp-content/` (junctions/symlinks), with WordPress core in a `wp/` subdirectory — the repo stays the single
source of truth and core is never committed. Two supported setups:

```bash
# 1. Docker (matches CI)
composer install        # wires the root PSR-4 autoloader (Corex\)
npm install             # links the npm workspaces
npm run build           # compiles blocks + admin JS
npx wp-env start        # Docker WordPress matching CI (see wp-env.json)

# 2. Local WAMP/XAMPP — map the repo into wp/wp-content via junctions/symlinks
# (see docs/en/00-getting-started/ and DECISIONS.md for the exact mapping procedure)
```

Verify the environment before building: `wp theme list` shows `corex`; `wp plugin list` shows
`corex-core`, `corex-blocks`, `corex-config`, `corex-forms`; the site boots with no PHP fatals
(the constitution's Environment Gate).

## Documentation

The docs-app is **optional** — a searchable team docs site, not required to run Corex or to start a site. Read
the docs whichever way suits you:

- **No docs app:** read `README.md`, `docs/en/**`, and the docs-app Markdown sources in the repo / on GitHub.
- **Team guide (dev server):** `cd docs-app && npm install && npm run dev` (→ http://localhost:4321).
- **Static WAMP vhost:** `cd docs-app && npm run build`, then point an Apache vhost `docs.corex.local` at
  `docs-app/dist`. Tell the admin where docs live via the `docs.base_url` config key (or the
  `corex_docs_base_url` filter) so Add-ons → Documentation links target your docs site; with none configured
  they open the docs source on GitHub.
- **Bilingual handbook:** `docs/en/` and `docs/ar/` (getting started, team workflow, deployment, cookbooks).
- **API reference:** generated from source via `wp corex docs:generate`.

## Building a client site

Corex is the framework; each client site is a separate plugin + theme generated by the CLI — you edit the
generated client code, never the framework internals:

```bash
wp corex make:site Acme            # plugin + theme + governance scaffold
wp corex make:site Acme --starter  # the above + a runnable example slice to learn from and delete
```

### Team-safe architecture (Role Gate)

CoreX separates framework work from client work so a team (and AI agents) never collide. Classify every session
into one mode before editing — **CoreX Framework / Client Site / Deployment / Docs-Planning**
([agent roles](docs/en/04-team-workflow/agent-roles.md), [start prompts](docs/en/04-team-workflow/ai-agent-start-prompts.md)):

- **Repo root = source of truth.** Framework source: `plugins/`, `addons/`, `packages/`, `theme/`, `specs/`,
  `docs/`, `docs-app/`. Client source: `sites/<client>/` (`<client>-site` + `<client>-theme` + governance + specs).
- **`dist/` is generated, never committed** — the server receives only its contents. `wp/wp-content/` and `dist/`
  are never edited as source.
- **Build & deploy:** `npm run build:dist -- --client=acme` → flat artifact in `dist/`; `npm run verify:dist`.
  GitHub Actions runs PR gates; Azure Pipelines (`azure-pipelines.yml`) builds + deploys. See
  [shared-host dist](docs/en/05-deployment/shared-host-dist.md) and [Azure Pipelines](docs/en/05-deployment/azure-pipelines.md).

The rule hierarchy: **Role Gate** (where) → **Spec Kit** (what) → **Guard Gate** (safe to ship) → **UI/UX ProMax**
(UI good enough).

## Client readiness

Before starting a real client site, run the Spec 055 readiness report:

```bash
wp corex readiness 0.40.0
```

It reports runtime gating, release metadata, CI/security controls, `make:site` validation, deployment profiles,
native-first component coverage, Free/Core vs Pro boundaries, and multi-agent safety. Local infrastructure checks
such as Docker/wp-env, browser E2E, and external deployment profiles are reported as environment-gated unless they
have been verified in their owning environment. GitHub branch protection, the required CI context, Dependabot
security updates, and secret scanning were verified for the v0.27.0 release cycle.

## Dependency security

Run the exposure-aware dependency gate after changing any Composer/npm manifest, lockfile, or audit policy:

```bash
npm run verify:dependencies
```

The gate audits Composer, root npm, and docs-app npm together. New, changed, expired, stale, or unbounded findings
fail closed. Development-only exceptions live in `.github/dependency-security-policy.json` with exact dependency
paths, compensating controls, review dates, and upstream removal triggers; high or critical shipped-runtime/CI
findings cannot be excepted. See `SECURITY.md` for the policy and exit-code contract.

**There are currently no open exceptions.** The 24 that stood at v0.39.0 were closed in v0.40.0 —
see [PROJECT-STATUS.md](PROJECT-STATUS.md), under *Known open items*, for how.

## Contributing

Corex is built **spec-first** (Spec Kit) under a strict constitution, with guard skills as the quality gate
and a NEXT STEP handoff on every change. See `CONTRIBUTING.md` and `COREX-WORKING-GUIDE.md` before opening a PR
— including the rule that every feature PR updates its documentation in the same change.

## License

See `LICENSE`.
