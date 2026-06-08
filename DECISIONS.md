# Corex — Decision Log

> Records *why*, so no future agent re-litigates a settled choice. One entry per decision.
> Format: COREX-WORKING-GUIDE.md §A.6. Newest decisions appended at the bottom.

---

## #1 — Project identity: Corex
Date: 2026-06-07
Decision: Framework name **Corex**. PHP namespace `Corex\`, CLI `wp corex`, CSS custom-property
prefix `--corex-`.
Why: A single short, ownable identity that doubles as the namespace others build add-ons on
(the prefix becomes a vendor namespace — §14).
Alternatives considered: longer descriptive names (rejected: poor as a namespace/CLI verb).
Status: Final.

## #2 — Target platform: WordPress 7.0+, PHP 8.3+, FSE block themes
Date: 2026-06-07
Decision: Baseline WordPress 7.0+, PHP 8.3+, Full Site Editing (block) themes only.
Why: WP 7.0 closes the editability gap (full design tools, PHP-only dynamic blocks, Abilities
& Connectors APIs), removing the need for a page builder. PHP 8.3 enables attributes, enums,
readonly, typed clarity throughout the framework.
Alternatives considered: classic themes + page builder (rejected: bloat, conflicts with the
token system and clean architecture); lower PHP floor (rejected: loses modern language features).
Status: Final.

## #3 — Monorepo with Composer + npm workspaces
Date: 2026-06-07
Decision: Single monorepo; PHP via Composer (PSR-4 autoload for `Corex\`), JS via npm
workspaces. One `composer install` and one `npm run build` from the root.
Why: Theme, core plugins, add-ons, and packages evolve together and share contracts; a
monorepo keeps them versioned and buildable as one unit (§4, §20).
Alternatives considered: multi-repo per package (rejected: contract drift, release overhead).
Status: Final.

## #4 — Layered architecture: Service/Repository + DI + Event Bus + Middleware
Date: 2026-06-07
Decision: Layered Architecture with a Service layer (Fowler) — thin Controllers, fat Services,
Repositories for all data access, Models as value objects, a PSR-11 DI Container, an Event Bus
(Observer) for side effects, and declarative Middleware for cross-cutting concerns.
Why: Mirrors Laravel so developers are productive immediately (§3); isolates responsibilities
so each layer is testable alone; keeps WordPress's procedural sprawl behind clean seams.
Alternatives considered: fat controllers / direct WP_Query in controllers (rejected: untestable,
unmaintainable); full ORM (rejected — see #6).
Status: Final.

## #5 — Runtime CSS custom properties over build-time tokens
Date: 2026-06-07
Decision: Design tokens are runtime CSS custom properties generated from `theme.json`
(the single source of truth), with per-site `brand.json` overrides resolved at runtime.
No build-time token systems.
Why: The HHEO lesson — build-time tokens block per-client/per-blog_id theming and add page
weight. Runtime tokens make a rebrand a *configuration* task, not a recompile (§5, §10).
Alternatives considered: Tailwind tokens, Sass variables (both rejected: build-time, per-client
rebuilds, weight).
Status: Final.

## #6 — No CSS framework in shipped output
Date: 2026-06-07
Decision: Ship no CSS framework (no Bootstrap, no Tailwind) in output. Styling flows
`theme.json` → CSS variables → blocks, using logical properties.
Why: Frameworks fight the token system, add weight, and impose opinions that conflict with FSE.
Behavior comes from the Interactivity API, not jQuery/Bootstrap JS (§9, §23).
Alternatives considered: Bootstrap (rejected: weight + JS dependency), Tailwind (rejected:
build-time tokens — see #5).
Status: Final.

## #7 — ACF-optional field-driver abstraction
Date: 2026-06-07
Decision: Models declare fields abstractly; a `FieldResolver` picks a driver at runtime —
ACF (via committed Local JSON) when present, native `register_post_meta()` when absent.
Application code (`$career->salary`) never knows which driver answered.
Why: ACF must never be a hard dependency; the same model/controller code must run with or
without it. One `$fields` declaration also feeds the QueryBuilder, REST schema, JSON-LD, and
the Abilities schema (§6).
Alternatives considered: hard ACF dependency (rejected: §1 rule 9); hand-rolled meta only
(rejected: loses ACF's editor UX when available).
Status: Final.

## #8 — WordPress 7.0 Abilities API + MCP Adapter for the agent layer
Date: 2026-06-07
Decision: Build the agent-ready layer on WP 7.0's native **Abilities API** plus the official
**MCP Adapter**. Do **not** build a custom MCP server. Abilities derive their schema from the
model `$fields`; permissions are inherited from the WordPress user.
Why: Native, secure-by-default, and reuses the one field definition. A custom server would
duplicate WordPress's auth and maintenance burden (§15).
Alternatives considered: bespoke MCP server (rejected: security + maintenance cost).
Status: Final.

## #9 — i18n: Polylang now, WPML-compatible via abstraction; RTL-first
Date: 2026-06-07
Decision: Bilingual AR/EN, RTL-first. An `I18nHandler` abstraction wraps the translator;
Polylang is the current driver, switchable to WPML via config (`MWP_I18N_DRIVER`) with no
controller changes. Logical CSS properties everywhere; `postcss-rtlcss` for edge cases only.
Why: Controllers must never import a specific plugin's functions; RTL must be correct by
default, not patched afterward (§1 rule 8, §16). Polylang has lower query cost than WPML.
Alternatives considered: hard-coding Polylang calls (rejected: §1 rule 9); WPML as default
(rejected: higher query cost — kept compatible, not default).
Status: Final.

## #10 — Testing: Pest + Jest + Playwright
Date: 2026-06-07
Decision: PHP unit/integration via **Pest** (on PHPUnit) + Brain Monkey / wp-phpunit; JS block
components via **Jest** (`@wordpress/jest-preset`); E2E via **Playwright**. CI gates every merge.
Why: Pest's cleaner syntax means more tests actually get written; the trio covers unit →
integration → JS → E2E including RTL flows (§18).
Alternatives considered: raw PHPUnit (rejected: more friction, fewer tests written).
Status: Final.

## #11 — Releases via tags, not long-lived environment branches
Date: 2026-06-07
Decision: git-flow-lite — `main` (tagged releases only) + `develop` (integration) +
short-lived `feature/*` and `hotfix/*`. Environments deploy from **tags** (e.g. `v1.4.0`),
never from long-lived `staging`/`production`/`qc` branches. SemVer + Conventional Commits.
Why: Avoid the HHEO trap where environment branches diverge and cause merge hell; the tag is
the single source of truth for what's live (§19).
Alternatives considered: per-environment branches (rejected: divergence, merge hell).
Status: Final.

## #12 — Deploy target — OPEN
Date: 2026-06-07
Decision: Deferred. Deployment destination/host (and therefore the CI deploy stage specifics)
is an open decision. The tag-based release model (#11) holds regardless of target.
Why: Per PROJECT FACTS, treat as an open decision; do not block foundation work on it.
Alternatives considered: (to be evaluated when the target is chosen).
Status: **Open** — revisit before building the CI deploy stage.

---

## Tooling decisions (this bootstrap session)

## #13 — Constitution canonical location: `.specify/memory/constitution.md`, `specs/` is a pointer stub
Date: 2026-06-07
Decision: The canonical constitution lives at `.specify/memory/constitution.md` (Spec Kit's
convention, written/regenerated by `/speckit-constitution`). `specs/constitution.md` is a thin
**pointer stub** linking to it — NOT a content copy. The documented hierarchy and entry files
keep referencing `specs/constitution.md` and resolve through the stub.
Why: Keeps a single source of truth (no two files to keep in sync → no drift, per docs-guard
rule 8 "link, don't duplicate") while satisfying the documented path (§A.1) and Spec Kit's
machinery. Chosen over a full mirror after the root-cleanliness instruction.
Alternatives considered: full content mirror into `specs/` (rejected: drift risk, two files to
clean up); relocating Spec Kit's path (rejected: fights the tool); pointing the hierarchy only
at `.specify/` (rejected: less discoverable for humans/other agents).
Status: Final.

## #14 — Spec Kit integration + guard skills (Claude Code), PowerShell scripts
Date: 2026-06-07
Decision: Initialized Spec Kit with `--integration claude --script ps --no-git` (Windows host;
git initialized separately so `.gitignore` was in place first). Installed the five guard skills
(`wp-/woo-/clean-code-/test-/docs-guard`) into `.claude/skills/`. `.claude/skills/` and
`.specify/` are committed; local/secret agent state is gitignored.
Why: Matches PROJECT FACTS (spec-driven, guard gate) and the Windows dev host; keeps tooling
version-controlled and reproducible for the next agent.
Alternatives considered: bash scripts (rejected: host is Windows PowerShell); letting Spec Kit
init git (rejected: wanted `.gitignore` committed before the first commit).
Status: Final.

---

## Repo-structure decisions (Phase 4)

## #15 — Namespaces + single-root autoload (Corex\ owns core)
Date: 2026-06-07
Decision: PSR-4 namespaces — `Corex\` → `plugins/corex-core/src/` (core owns the root namespace,
matching the framework doc's `Corex\Models`, `Corex\Services` usage), `Corex\Blocks\` →
`plugins/corex-blocks/src/`, `Corex\Config\` → `plugins/corex-config/src/`, `Corex\Cli\` →
`packages/cli/src/`, and `Corex\Tests\` → `tests/` (autoload-dev). The **root `composer.json` is
the single authoritative autoload map**; per-package `composer.json` files declare identity only
(name/type/license/php), no autoload, to avoid duplication/drift.
Why: One source of truth for autoload; namespaces match COREX-FRAMEWORK.md examples so generated
code needs no rewrite; longest-prefix PSR-4 matching lets `Corex\Blocks\…` and `Corex\Models\…`
coexist cleanly. Verified: `composer install` generates all five prefixes; `vendor/autoload.php`
loads.
Alternatives considered: `Corex\Core\` segment for core (rejected: contradicts the doc's
`Corex\Models`); per-package autoload blocks (rejected: duplication/drift); Composer path
repositories (rejected: heavier, symlinks into vendor/ complicate WP plugin loading).
Status: Final.

## #16 — Plugin self-containment over DRY for the autoloader bootstrap
Date: 2026-06-07
Decision: Each plugin's main file carries its own ~12-line guarded autoloader-resolution closure
(prefers a standalone `__DIR__/vendor/autoload.php`, falls back to the monorepo-root vendor),
duplicated across the three plugins rather than extracted to a shared file.
Why: Extracting to a shared sibling (e.g. `plugins/_corex-bootstrap.php`) would make every plugin
hard-depend on an external path two levels up — breaking standalone installability (§14: add-ons
ship as self-contained Composer packages) and the "never fatal" guarantee if a plugin is copied
out alone. Per clean-code-guard Rule 12 ("the wrong abstraction is worse than duplication"), the
self-containment constraint wins; the repeat is justified duplication. The closure runs at include
time before any autoloading exists (chicken-and-egg), so it cannot itself be a loaded class.
Alternatives considered: shared bootstrap include (rejected: breaks standalone + never-fatal);
mu-plugin loader (rejected: out of scope, still external dependency).
Status: Final.

## #17 — Reference docs stay at repo root; docs/ holds derived docs
Date: 2026-06-07
Decision: The four `COREX-*.md` references remain at the repo root (they are agent entry points per
COREX-WORKING-GUIDE.md §A.2). `docs/` holds derived/supplementary docs (HOOKS.md, MODES.md, and
`wp corex docs:generate` output) and currently just an index README.
Why: Moving them would break the documented entry-point convention and every relative reference in
CLAUDE.md/AGENTS.md. Keeps the root the single obvious starting point for any human or agent.
Alternatives considered: moving all docs into `docs/` (rejected: breaks §A.2 + entry links).
Status: Final.

---

## Environment decisions (Phases C–D — WordPress bootstrap)

## #18 — WordPress install + how the monorepo maps into it
Date: 2026-06-07
Context: The monorepo had no WordPress core (Layout A — theme/, plugins/ at repo root). Resolved
without touching any Corex file.
Decision:
- **Install method:** direct **WP-CLI on WAMP** (not wp-env/Docker). WP-CLI here was a partial
  composer-global install missing the command bundle, so `wp-cli/wp-cli-bundle` was installed
  globally (`composer global require wp-cli/wp-cli-bundle -W`, upgraded wp-cli 2.11 → 2.12).
- **Core location:** WordPress **7.0** downloaded into **`./wp/`** (`--skip-content`), `wp-config.php`
  generated there. `./wp/` is gitignored — core is NOT part of the monorepo source.
- **Database:** `corex` on the running WAMP **MySQL 8.3.0**, user `root`, empty password, prefix
  **`cx_`** (hardened from `wp_`).
- **Site URL:** **`http://corex.local`** — the user's Apache vhost docroot points directly at
  `…\blackstone-new-site\wp`, so WP is served at the host root (siteurl/home = `http://corex.local`,
  admin at `/wp-admin/`). Requires `127.0.0.1 corex.local` in the Windows hosts file (present).
- **Monorepo mapping:** **directory junctions** (`mklink /J`, no elevation needed) from
  `wp/wp-content/themes/corex` → `theme/` and `wp/wp-content/plugins/corex-{core,blocks,config}`
  → `plugins/corex-*`. The repo stays the single source of truth; edits are live in WP. (Real
  symlinks `mklink /D` need admin/Developer Mode and failed in the non-elevated shell.) Add-on
  junctions are created under `addons/` when add-ons exist.
- **MySQL client on PATH:** WP-CLI `db` subcommands shell out to the `mysql` client, which WAMP
  does not put on PATH. Prepend `C:\wamp64\bin\mysql\mysql8.3.0\bin` to PATH for any `wp db …`
  command (the install/`option`/`plugin`/`theme` commands use PHP/mysqli and do not need it).
Why: Matches the developer's actual machine (WAMP, repo already in the webroot) per FRAMEWORK §20's
allowance for solo WAMP work; junctions avoid elevation; keeping core in `./wp/` and gitignored
preserves a clean monorepo with one source of truth.
Alternatives considered: wp-env/Docker (the §20 team default — deferred; needs Docker Desktop;
remains the CI parity target); core at repo root (rejected: collides with theme/ & plugins/);
copy/build step instead of junctions (rejected: duplicates files, drifts).
Status: Final.

---

## Phase 5 decisions (corex-core foundation)

## #19 — Config resolution engine lives in corex-core, not corex-config
Date: 2026-06-08
Context: Spec 001 + PROGRESS Phase 5 placed the Config layer (.env → options → defaults) inside
corex-core, but COREX-FRAMEWORK.md §2's layer table assigned ".env resolution" to the corex-config
plugin. Source-of-truth hierarchy puts the framework doc above the spec, so the conflict had to be
settled before planning.
Decision: Split the concern.
- **corex-core/Support** owns the low-level **config resolution engine** (precedence .env → WP
  options → defaults), the `Config` contract, and the `Config::get()` facade. This is what spec 001
  builds. Core thus self-boots and reads config without depending on any other plugin (Principle II).
- **corex-config** (always active) owns the **management** surface on top: admin settings UI,
  feature flags, GTM, security headers — i.e. editing/persisting the values the core engine reads.
- COREX-FRAMEWORK.md §2 table amended + a clarifying note added so the doc and spec agree.
- `.env` parsing uses `vlucas/phpdotenv` (spec Clarifications, 2026-06-08).
Why: Principle II (constitution, outranks the framework doc) requires core to run fully on its own;
a config engine that lived only in a separate deactivatable plugin would violate that. The user
confirmed corex-config is always active and chose the "engine in core, management in config" split.
Alternatives considered: (a) all config in corex-config per the literal doc — rejected: breaks core
self-sufficiency; (b) all config in core including the admin UI — rejected: settings UI is config's
job per the doc and keeps core presentation-free.
Status: Final.

## #20 — ABSPATH direct-access guard on every src class file
Date: 2026-06-08
Context: wp-guard Rule 8 (and WP Plugin Check) expect every PHP file to block direct web access.
PSR-4 autoloaded class files have no top-level side effects, but Corex targets professional
distribution, so we follow the WooCommerce convention.
Decision: every `plugins/*/src/**/*.php` class file begins (after `declare` + `namespace`) with
`defined('ABSPATH') || exit;`. To keep the headless Pest suite runnable without WordPress, the test
bootstrap (`tests/bootstrap.php`) defines `ABSPATH` before any Corex class autoloads — exactly as
WooCommerce's own test suite does.
Why: matches a battle-tested professional WP framework (WooCommerce), passes Plugin Check, and costs
one line per file; defining ABSPATH in the test bootstrap removes the only downside (broken headless
tests). Applies to all future foundation/module code.
Alternatives considered: no guard on autoloaded classes (rejected — Plugin Check flags it, less
defensive); guard without the test define (rejected — exits the unit-test process on autoload).
Status: Final.

## #21 — Custom DI container instead of league/container
Date: 2026-06-08
Context: plan/research (R1) chose `league/container` as the PSR-11 engine behind our wrapper. While
implementing T011 I read the installed source (clean-code-guard Rule 17) and found league 4.2.5 does
NOT detect circular dependencies (its ReflectionContainer recurses until the process dies) and its
`has()` cannot tell an explicit binding from any autowirable class — making FR-010 (circular
detection) and FR-007a/FR-009 (precise unbound-interface / unhinted-scalar messages) impossible to
layer on cleanly. Coordinating our autowiring with league's delegate was fragile.
Decision: ship a focused custom container `Corex\Container\Container` (~130 lines, PHP Reflection)
implementing `Corex\Container\ContainerInterface extends Psr\Container\ContainerInterface`. Keep
`psr/container` (PSR-11 interop); remove `league/container` from root + corex-core composer.json.
The public contract (PSR-11 + bind/singleton/instance/make) is unchanged — internal engine swap only.
`tag`/`tagged` dropped from the interface for now (YAGNI; add with first real use).
Why: full control over resolution, cycle detection, and error messages for ~130 owned/tested lines;
no runtime container dependency; Laravel itself ships a custom container. The spec's correctness bar
(FR-007a/009/010) is met precisely rather than approximately.
Alternatives considered: keep league + catch \Error for cycles (rejected — unreliable, ugly messages);
league for bindings + our autowiring hybrid (rejected — fragile two-system coordination);
illuminate/container or php-di (rejected — heavier coupling than a foundation needs).
Impact: research.md R1 + plan.md Technical Context updated in the same change.
Status: Final.

## #22 — Config engine aggregates all config/*.php files
Date: 2026-06-08
Context: spec 002's QueryBuilder cap must be configurable via the Config engine (`query.max`), but
CoreServiceProvider loaded only config/app.php into the defaults layer.
Decision: CoreServiceProvider now globs `config/*.php` and keys each by basename (config/app.php →
`app`, config/query.php → `query`), so every shipped config file is exposed through `Config::get()`
(Laravel-style). Consequently config/app.php was **unwrapped** — it returns its keys directly
(`['name'=>...]`) rather than `['app'=>[...]]`, since the filename now provides the namespace.
Why: the right, scalable home for per-concern config; `Config::get('query.max')` and
`Config::get('app.name')` both resolve. Add-ons can ship their own config files later.
Impact: behavior of `Config::get('app.name')` unchanged; the spec-001 integration test still passes.
Status: Final.
