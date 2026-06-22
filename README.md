# Corex

A professional, Laravel-inspired framework for building WordPress sites — corporate, e-commerce,
multisite, headless, or AI-agent-driven — on one clean, documented, spec-first foundation.

- **Target:** WordPress 7.0+, PHP 8.3+, FSE block themes.
- **Namespace:** `Corex\` · **CLI:** `wp corex` · **CSS prefix:** `--corex-`.
- **Stack:** Composer (PHP) + npm workspaces (JS), one monorepo.
- **Status:** Actively developed; latest release **v0.28.0**. The engine, block/forms/config layers, CLI
  generators, site kits, and the docs app are implemented and unit-tested. The **CoreX admin design (spec 060,
  milestone M6) has landed** (merged via PR #59) and is render-verified dark + light: the real `wp-login.php`
  carries the CoreX login design, every CoreX admin screen uses the full-bleed designed shell, and Captcha settings
  are provider-specific (None, Honeypot, reCAPTCHA, hCaptcha, Cloudflare Turnstile). See `PROGRESS.md` for the
  authoritative, module-by-module status.

> This README is the public entry point. For where the project stands in detail (including any in-progress
> tails), `PROGRESS.md` is the source of truth — not this file.

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
  corex-email/       Corex Mail — templated, queueable transactional email.
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

## Read first (agents and humans)

1. `specs/constitution.md` → `.specify/memory/constitution.md` — the non-negotiable rules.
2. `PROGRESS.md` — current status and the recommended next step.
3. `COREX-FRAMEWORK.md` (architecture) and `COREX-WORKING-GUIDE.md` (how we work).
   `COREX-EMAIL-ADDON.md` is the Corex Mail spec; `COREX-SPECKIT-START.md` the build order.
   `CLAUDE.md` / `AGENTS.md` orient any coding agent.

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

## Client readiness

Before starting a real client site, run the Spec 055 readiness report:

```bash
wp corex readiness 0.27.0
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

## Contributing

Corex is built **spec-first** (Spec Kit) under a strict constitution, with guard skills as the quality gate
and a NEXT STEP handoff on every change. See `CONTRIBUTING.md` and `COREX-WORKING-GUIDE.md` before opening a PR
— including the rule that every feature PR updates its documentation in the same change.

## License

See `LICENSE`.
