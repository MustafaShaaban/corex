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

## #23 — Dynamic block renderer declared in block.json (not by folder convention)
Date: 2026-06-08
Context: block folders are kebab-case (`entity-field`) per the block.json `name` convention, but
kebab-case is not a valid PHP namespace segment, so a renderer class cannot be PSR-4-autoloaded from
inside the block folder.
Decision: a dynamic block declares its renderer's FQCN in `block.json` under `corex.renderer`; the
renderer lives in a PSR-4-valid namespace (e.g. `Corex\Blocks\Examples\EntityFieldRenderer`) and is
resolved from the container by `DynamicBlockRegistrar`. The block folder stays kebab; the renderer is
decoupled from the folder name.
Why: keeps WP/block.json conventions (kebab names) and PHP autoloading (PSR-4) both correct, with an
explicit, greppable renderer reference; avoids fragile folder-name→namespace transforms.
Status: Final.

---

## Spec 007 decisions (forms engine)

## #24 — The event seam lives in corex-core (`Corex\Events`), not in corex-forms
Date: 2026-06-09
Context: forms needs to dispatch a submission to multiple listeners (store, email). The dispatcher
could live in the forms plugin, but Corex Mail and future add-ons need the same seam.
Decision: `ListenerProvider` + `EventDispatcher` + the `Event` marker live in corex-core
(`EventServiceProvider`, registered in `Boot`). The forms plugin consumes them. Dispatch is ordered
(registration order), once-each, and best-effort (a throwing listener is caught + logged via
`BootLogger`; the rest still run).
Why: a shared, foundational concern belongs in the engine so every module reuses one registry; it
keeps forms as an *application* of the architecture, not the owner of cross-cutting plumbing.
Status: Final.

## #25 — Submissions are a non-public `corex_submission` CPT, persisted via the data layer
Date: 2026-06-09
Context: a submission must be stored and queryable by form slug; its fields are dynamic per form.
Decision: `Submission` (a Model with `postType() = corex_submission`, empty static `fields()`) +
`SubmissionRepository extends PostRepository`. The repository creates a private post and writes the
form slug and each validated value as `corex_field_*` meta via the injected `FieldDriver` — so
Principle III holds (the repository is the only data-source layer; no `wp_insert_post` in listeners).
Dynamic field names preclude a static `Model::fields()` map, hence the meta is written explicitly.
Why: keeps persistence behind the repository while supporting arbitrary per-form fields; queryable by
`corex_form_slug` meta. No custom table needed for v1.
Status: Final.

## #26 — Validator bails per field; rules return i18n message keys
Date: 2026-06-09
Context: a field with several rules could accumulate many errors; messages must be translatable
without a WordPress runtime (the validator is pure).
Decision: the validator records at most one error per field — the first failing rule in declared
order (bail per field). Rules return an i18n message **key** (`required`, `email`, `max`, …) or null,
never a sentence; the presentation layer owns the translated text. Field names normalize to a
canonical key (used for the input name and `corex_field_*` meta); two names that normalize to the
same key, and unknown rules, are rejected at schema resolution (fail closed, developer-visible).
Why: predictable, minimal error payloads; pure/headless validation; translation stays at the edge.
Status: Final.

## #27 — `Response::reject` gains an optional payload (cross-spec, spec-005)
Date: 2026-06-09
Context: a 422 must carry per-field validation errors, but the spec-005 `Response::reject` produced a
null-valued rejection.
Decision: `reject(string $reason, int $status = 403, mixed $payload = null)` — the payload populates
the existing `value`. Backward compatible: all prior two-argument callers are unchanged (value stays
null). The forms controller maps a rejection's array payload to the `errors` body.
Why: the smallest additive change that lets any endpoint return a structured rejection body; avoids a
forms-local result type duplicating `Response`.
Status: Final.

## #28 — The public submit endpoint is secured by middleware, not a capability
Date: 2026-06-09
Context: a contact form is submitted by anonymous visitors, so `current_user_can` cannot gate it; a
writing REST route still must prove intent.
Decision: `register_rest_route` uses `permission_callback => '__return_true'`; identity/intent are
enforced by the declarative middleware pipeline (`nonce` on the WP REST nonce via `X-WP-Nonce` →
form-shaped `sanitize` → `throttle`) plus a `corex_hp` honeypot. The controller hand-writes no
security checks (Principle VII). The generic `sanitize` alias carries no shape, so the controller
supplies a form-shaped sanitizer derived from the schema.
Why: the correct model for a public submission — a nonce + rate limit + honeypot, not a capability;
keeps security automatic and declarative.
Status: Final.

---

## Spec 008 decisions (Corex Mail MVP)

## #29 — A neutral `Corex\Mail\Mailer` seam in corex-core; Corex Mail is a consumer, not a Forms dep
Date: 2026-06-09
Context: Forms must send templated mail when Corex Mail is present and fall back otherwise, without a
hard dependency; in a monorepo `class_exists` is unreliable (all classes autoload regardless of activation).
Decision: corex-core defines `Corex\Mail\Mailer` (interface) + `MailRequest` (a primitive value object —
scalars/arrays only). The Corex Mail add-on binds a `RequestMailer` implementation; Forms checks
`container->has(Mailer::class)` (the real activation signal) and delegates, else `wp_mail`. The seam carries
no Corex Mail types, so neither side hard-depends on the other (Principle IX) — the same pattern as the
spec-007 event seam.
Why: container binding is the true detect-and-defer switch; keeps both add-ons decoupled.
Status: Final.

## #30 — Email templates: code-registered, flat `{{ path }}` whitelisted merge, escape on output
Date: 2026-06-09
Context: merge variables are the classic email template-injection/XSS vector.
Decision: templates are PHP classes (`name`/`subject`/`body`) returning straight-line text with flat
`{{ path }}` placeholders. The renderer resolves each path only from a pre-assembled, whitelisted
`MailContext` (out-of-whitelist/absent → empty), and escapes every body value with `htmlspecialchars`
(pure, no WP) before wrapping it in the brand layout. No control structures, no PHP-eval. The layout's
brand color/logo/name come from the resolved `theme.json` (incl. `brand.json`) at runtime; email-client
limits force inline styles, whose only literals are functional structure (600px width), never design tokens.
Why: closes injection/XSS by construction while staying pure and headless-testable; rebrand stays config.
Status: Final.

## #31 — The email audit log is a `corex_email_log` CPT via the data layer
Date: 2026-06-09
Context: every send must be recorded and queryable by status; custom tables are not yet a framework capability.
Decision: a non-public `corex_email_log` CPT through a `PostRepository` (implementing the `EmailLogStore`
interface so the service stays headless-testable). Status/recipients/subject are **declared** model fields
(`corex_mail_*` meta) so the log is queryable via the QueryBuilder (`byStatus`). Swappable for a custom-table
store later without changing the engine. Retention/pruning deferred.
Why: reuses the only persistence layer that exists today; an interface keeps the orchestrator pure.
Status: Final.

## #32 — Default delivery via `wp_mail`; from-identity from Config; provider drivers deferred
Date: 2026-06-09
Context: storing SMTP/provider credentials safely needs the (unbuilt) `Cryptor`; the engine must ship securely now.
Decision: the default `WpMailDriver` delegates to `wp_mail` (honoring the site's existing SMTP), behind a
`MailDriver` interface. The from-identity + reply-to come from the Config engine (`mail.from.*`, `mail.reply_to`)
and are `sanitize_*`'d into headers. Sending is synchronous + best-effort (`send()` never throws; a failure is
caught + logged). The queue (Action Scheduler), retries, rate limiting, attachments, and provider drivers are
deferred — additive changes behind the same `MailService`/`MailDriver` seams.
Why: ships a secure MVP without credential storage; the abstractions make every deferred piece additive.
Status: Final.

---

## Post-0.8 roadmap decisions

## #33 — Roadmap restructure + packaging: features are add-ons, foundations are core, designs are blocks
Date: 2026-06-09
Context: planning toward the first real consumer (Blackstone EIT). The user expanded scope — a reusable
block library, a full company-website kit, professional newsletter + careers + call-request flows, Corex's
own brand identity, and an admin settings/dashboard — and asked that designs be composed of Corex blocks
and that feature modules be add-ons where appropriate.
Decision: adopt `ROADMAP.md` (specs 009–017) with this packaging rule — `plugins/` = free core
(engine/data/blocks/config), `addons/` = optional **features** (the commercial / marketplace layer),
`theme/` = the neutral skin. "Everything is blocks": a **Corex UI block library** (`corex-ui`, spec 009)
is the foundation, and the **Company Website Kit** (`corex-kit-company`, spec 010) composes those blocks
into patterns + universal FSE templates — neutral/un-branded so client sites (Blackstone) apply their
Figma via `brand.json` + a style variation. **Custom tables** (011, core) precede the data-heavy features.
**Newsletter** (013), **Careers** (014), **Call Request** (015) are feature add-ons built on forms + mail
+ events + tables, with **captcha drivers + secure uploads** (012) as shared anti-spam/security enablers.
Corex gets its **own product identity** (navy `#0B1F3B` + cyan `#00C2FF`, geometric sans, a layered-core
SVG mark) + **admin branding** (016) and a **React/DataViews admin dashboard** (017, `corex-config`),
kept separate from client branding (Principle: the client base stays neutral).
Why: matches the framework's plugin/addon philosophy and the free-core/paid-add-on marketplace strategy;
"blocks first" makes every design reusable; doing custom tables before subscribers/applications avoids a
CPT-scale dead end. The premature spec 009 "starter-kit" draft is superseded — the kit returns as spec 010
composing the block library.
Status: Final (sequence adjustable per project need).

## #34 — Corex UI MVP is no-JS-build: server-rendered dynamic blocks + section patterns
Date: 2026-06-09
Context: "everything is blocks", but this environment has no browser and no verified JS block build, and a
rich custom-edit block library needs `@wordpress/scripts` + an editor to author and verify.
Decision: the `corex-ui` MVP ships **server-rendered dynamic blocks** (`corex/posts`/`breadcrumbs`/
`copyright`, via the spec-004 engine, PHP-testable) for live data, and **block patterns** (core-block
compositions under a "Corex" category) for content sections — both token-only, RTL, accessible, i18n,
headless-verifiable. Custom JS-edit blocks + the build pipeline are a later spec (need a browser/build env).
The `UiManifest` reads the actual `block.json` files so it cannot drift from what is registered.
Why: delivers a real, fully-tested block/pattern library now without unverifiable JS/editor work; the
build-based rich blocks layer on additively when an authoring/verification environment exists.
Status: Final.

## #35 — Kit architecture: FSE templates in the theme, the Blueprint manifest in the add-on
Date: 2026-06-09
Context: a "kit" must compose modules into a deployable company site, but FSE templates/parts are
inherently theme files, while the kit should be a discoverable, swappable unit.
Decision: the **universal FSE templates + parts live in the theme** (`theme/templates`, `theme/parts`) —
the constitution's home for presentation, so they remain when the kit add-on is deactivated. The **Blueprint
manifest + registry** are the add-on's only code (`corex-kit-company`, `Corex\Kit`): `CompanyBlueprint`
declares required/recommended modules + the templates/parts/patterns it relies on. The `front-page` composes
the spec-009 section patterns via `wp:pattern` refs; the footer composes the `corex/copyright` block. All
token-only/RTL/accessible; visual/editor validity is browser-verified, not claimed. Future kits add their
own patterns + a Blueprint without touching the theme skeleton.
Why: keeps FSE conventions (templates are the theme's) while making kits discoverable/swappable; the theme
stays the durable skin, the kit a thin composition manifest.
Status: Final.

## #36 — Custom tables: dbDelta + a typed TableRepository in core, the only query layer
Date: 2026-06-10
Context: subscribers/applications/bookings are many queryable rows with relations/status — a poor fit for
CPTs (scale, query, filtering). Spec 011 adds the custom-table layer the data-heavy features need.
Decision: a pure `Schema\Table` builder (fluent columns → `CREATE TABLE`) + a `Casts\Caster` (both
directions); a `Schema\Migrator` that creates/drops idempotently via WordPress's **`dbDelta`** under
`{prefix}corex_`; and an abstract `Repositories\TableRepository` (typed insert/find/update/delete/where).
**Every variable query is `$wpdb->prepare`d**; table/column identifiers are code-defined (never request
input) and the `where` column is validated against `^[a-z0-9_]+$`. The repository is the sole query layer
(Principle III). Modules create their tables on activation. Deferred: extra indexes, foreign keys,
cross-table relations, a fluent query builder, and migration versioning/rollback history.
Why: gives the Laravel-like custom-table experience securely, on the conventions WordPress already ships
(dbDelta), without overbuilding; unblocks Newsletter (013) and Careers (014).
Status: Final.

## #37 — Captcha as a fail-closed driver add-on; upload validation in core
Date: 2026-06-10
Context: public Newsletter/Careers submissions need anti-spam + (careers) safe file uploads — shared
enablers, so they precede those features.
Decision: a `Captcha` interface (addon `corex-captcha`) with `none`/`honeypot`/remote drivers; the remote
driver covers reCAPTCHA/Turnstile/hCaptcha (all `{success}`-shaped) by verify-URL + secret, selected by
`captcha.driver`/`captcha.secret` config. **Remote verification is fail-closed** (missing secret/token,
transport error, or non-success → false) and the secret is never logged. The **upload validator** lives in
corex-core (`Security\Upload`): it rejects upload errors, empty/oversized files, disallowed MIME types, and
mismatched extensions on the descriptor only (no caller path → traversal-safe); the boundary store
(`wp_handle_upload`) re-checks the real MIME. Deferred: v3 score thresholds, Akismet, virus scanning, image
processing.
Why: provider-agnostic anti-spam + safe uploads, both fully unit-testable (only the provider HTTP call is a
boundary), shipped before the features that need them.
Status: Final.

## #38 — Newsletter: double opt-in with HMAC-signed links; on-publish via the event/post hook
Date: 2026-06-10
Context: a professional, GDPR-correct newsletter must not trust unconfirmed emails, must allow secure
one-click unsubscribe from an email (where nonces don't fit), and must email subscribers when relevant
content publishes.
Decision: a pure `SubscriptionService` (consent required; subscribe → `pending`; no duplicate/enumeration)
over an injected `SubscriberStore` (custom table `corex_subscribers`, spec 011) + the Mailer seam (008).
Confirm/unsubscribe use **HMAC-signed tokens** (`TokenSigner`) — the token is the authenticator, so the GET
email links carry their own auth (no nonce, the accepted email-link pattern); a tampered token is rejected
(fail-closed). The subscribe REST route is honeypot + captcha (012) gated. Publishing a post in a
`newsletter_topic` fires `transition_post_status` → `PublishNotifier` emails the confirmed subscribers whose
topics intersect. **Deferred:** the Action Scheduler **queue** (bounded synchronous send for now), bounce
handling, campaigns/segments, and the subscriber admin screen (spec 017).
Why: the secure, standards-correct shape of subscriptions, fully unit-testable at the core, reusing the
custom-table + mail + captcha + event seams already built.
Status: Final.

## #39 — Careers: jobs as a CPT, applications in a custom table, file-safe apply
Date: 2026-06-10
Context: careers needs job content (low volume) + many application rows (queryable, with a pipeline) + the
single most dangerous input (a CV upload).
Decision: jobs are a `corex_job` CPT with department/location/type taxonomies + a `corex/jobs` block;
applications are a `corex_applications` custom table (spec 011). The pure `ApplicationService` validates the
required fields + the CV via the spec-012 `UploadValidator` (allowed type/ext + size; descriptor-only),
stores, and notifies HR + the applicant via Mail — **zero side effects on rejection**. The pure `StatusFlow`
permits only adjacent pipeline transitions. The apply REST route is honeypot + captcha gated; the validated
CV is moved by the boundary (`wp_handle_upload` to a protected location), never a caller path. Deferred: the
recruiter admin screen (spec 017), CV virus scanning, scheduled interviews.
Why: the right storage per data shape (CPT vs table), with file safety reusing the spec-012 validator, and a
fully unit-testable application core.
Status: Final.

## #40 — Call request: configured leaders + a custom-table request flow
Date: 2026-06-10
Context: a "book a call with a leader" flow that stores the request and notifies the right person, reusing
the now-built table + mail + captcha seams.
Decision: leaders are configured (`bookings.leaders`: `{id,name,email}`) via a pure `LeaderDirectory` (the
public list omits emails); a pure `CallRequestService` validates the leader + contact, stores in
`corex_call_requests` (spec 011), and notifies the leader + confirms the visitor via Mail — zero side
effects on rejection. The request REST route is honeypot + captcha gated. Deferred: a leaders CPT/screen,
real availability calendars, time-zone handling, and reminders.
Why: the smallest correct shape that completes the Blackstone feature set, reusing every prior seam.
Status: Final.

## #41 — Corex product brand (navy + cyan SVG) in corex-config, separate from client branding
Date: 2026-06-10
Context: Corex had no identity; the user asked for one, applied in wp-admin, and kept distinct from client
sites (which stay neutral).
Decision: Corex's identity is **navy `#0B1F3B` + cyan `#00C2FF`** with a scalable **SVG** layered-core mark
+ wordmark, bundled in `corex-config/assets`. A pure `BrandingService` resolves the logo URL (config
`brand.logo_url` override → bundled default) + the login CSS + the configured footer/login-url; `AdminBranding`
applies them via `login_head`/`login_headerurl`/`admin_footer_text`. This is the **product** brand (#12A),
in core (`corex-config`), never client-site styling (#12B stays neutral, overridden by the client's
`brand.json`). Deferred: the admin-bar logo node, a Corex admin color scheme, and the React settings UI (017).
Why: gives Corex a real, configurable identity now (fully unit-testable), without bleeding into client sites.
Status: Final.

## #42 — Admin settings persist to the Config option layer; React UI deferred to a build env
Date: 2026-06-10
Context: Corex needs a control panel; the constitution favors a React/DataViews admin, but this environment
has no Node build and no browser to author or verify a React app — building one unseen would be unverifiable.
Decision: ship the **verifiable server-rendered foundation** — a `SettingsRegistry` (schema) + `SettingsForm`
(escaped HTML, pure) + `SettingsStore` that persists each setting to the **prefixed option the Config engine's
option layer already reads** (`brand.footer_text` → `corex_brand_footer_text`), so saved settings flow into
the framework with **no extra wiring** + an `AdminDashboard` (menu + nonce/capability/sanitized save). The
**React/DataViews/DataForm UI** (DataViews tables for submissions/subscribers/applications, the setup wizard,
a health-check runner) is the **deferred upgrade** — explicitly flagged as needing a Node build + a browser,
not claimed as done.
Why: delivers a working, fully-tested admin now and keeps settings wiring trivial (one option namespace);
honest about the React layer rather than shipping unverifiable JS.
Status: Final.

## #43 — Front-end build pipeline: @wordpress/scripts, src→build/blocks, ServerSideRender editor
Date: 2026-06-11
Context: the blocks were server-rendered only (no editor JS), so the editor reported "your site doesn't
include support for this block"; there was no SCSS/JS build at all (0 .scss files, no node_modules, empty
build-tools). The user flagged blocks + missing build as blockers.
Decision: adopt **@wordpress/scripts** (the WordPress-standard webpack/Babel/Sass/PostCSS toolchain) over a
bespoke webpack or Vite config. Each block package keeps block sources in its own folder and builds to a
package-level **`build/blocks/`**; the service providers register from `build/blocks` when present, falling
back to the source dir headlessly (`is_dir($built) ? $built : <source>`). Every dynamic block gains an
`index.js` that `registerBlockType()`s and previews the PHP render via **`<ServerSideRender>`** — one source
of truth (the server renderer), never a duplicated JS implementation. SCSS is imported in `index.js`
(`import './style.scss'`), compiled to `style-index.css` + an auto-generated `style-index-rtl.css` (Principle
VIII satisfied by the toolchain). `DynamicBlockRegistrar` wires `wp_set_script_translations()` per editor
handle (i18n). `build/` stays git-ignored — a generated artifact rebuilt on checkout/CI.
Why: the editor-registration gap is exactly what ServerSideRender solves without violating "one renderer";
wp-scripts gives SCSS, minification, RTL, and asset.php dependency extraction for free, with no config to
drift. **Commercial-plan note:** committing only sources (not `build/`) keeps Free/Pro packaging clean — the
release step runs `npm ci && npm run build` to produce distributable zips; the same pipeline serves the
open-source edition.
Status: Final.

## #44 — make:block scaffolds a complete dynamic block; renderer beside the block in one Blocks/ dir
Date: 2026-06-11
Context: blocks were the most repetitive thing to hand-write (block.json + index.js + style.scss + a PHP
renderer, all wired consistently). `make:block` had to make a new block configuration, not reinvention.
Decision: add a dedicated **`BlockScaffolder`** (separate from the single-file `Generator`/`GeneratorEngine`
abstraction, which produces one class) that renders 4 stubs into `<base>/Blocks/<slug>/` (block.json +
index.js + style.scss) plus the renderer at `<base>/Blocks/<Name>Renderer.php`. It renders **all** files
before writing **any** (an unresolved placeholder fails loudly, never a half-written block) and is idempotent
(skip unless `--force`). The renderer lives **beside** the block folder in a single `Blocks/` dir (the
corex-ui convention) rather than a sibling `blocks/` + `Blocks/` split — which would **collide on
case-insensitive filesystems** (Windows, macOS) and diverge on Linux. The generated block follows the
item-1 pattern exactly (apiVersion 3, `category:"corex"`, `editorScript`, compiled `style-index.css`,
`corex.renderer` FQCN, ServerSideRender editor) so it is registered + working after `npm run build`. The
scaffolder is pure/headless (8 Pest tests incl. a `php -l` lint of the generated renderer); `MakeCommand`
gained a `block` branch; verified live via `wp corex make:block`.
Why: one command replaces the most error-prone multi-file boilerplate; keeping the renderer in one dir is
the only cross-platform-correct layout. **Commercial-plan note:** the same generator serves Free/Pro — kit
authors scaffold blocks the identical way.
Status: Final.

## #45 — Shared validation: one schema exported PHP→JS; AJAX-default; server authoritative
Date: 2026-06-11
Context: validation lived only in PHP; the brief requires ONE schema driving both client and server, with
forms submitting via AJAX by a single reusable handler (not bespoke per form), never trusting the client.
Decision: keep the PHP form definition (`Form` → `SchemaResolver` → `FieldSchema`) as the **single source of
truth**. Add a pure `SchemaExporter` that serializes the resolved schema to a JSON-able list; the form block
embeds it on the `<form>` as `data-corex-schema` (`esc_attr(wp_json_encode(...))`). The block's `view.js`
(now a real built module, possible since item 1) reads that schema and validates with `validation.js` — rule
functions that **mirror the PHP rules exactly** (bail-per-field, empty passes non-required, max/min on
number-or-length). It renders per-field `role="alert"` errors (message keys → `@wordpress/i18n`), focuses the
first invalid control, then posts JSON to the **unchanged** secured REST route (nonce+sanitize+throttle+
honeypot), where the server **re-validates the identical schema** and stays authoritative. Both sides are
unit-tested: PHP (SchemaExporterTest) + JS (Jest `validation.test.js` via `npm run test:js`, wired through
`@wordpress/scripts test-unit-js`). The registrar now also wires `wp_set_script_translations()` for view +
front-end script handles (not just editor), so the front-end messages are translatable.
Why: the only way to guarantee front/back never drift is to generate the client checks from the same schema
the server enforces, while the server remains the trust boundary. Reuses the entire spec-005/007 secured
lifecycle unchanged. **Commercial-plan note:** the schema/handler are generic — every Free/Pro form (contact,
newsletter, careers, call) gets shared validation for free, no per-form JS.
Status: Final.

## #46 — Flexible form builder: extended field schema + per-type FieldRenderer; a new form is config
Date: 2026-06-11
Context: the form system handled only text/email/textarea with a fixed layout. The brief requires ALL input
types + layout/label/class/attr control, composing to complex forms — as configuration, not reinvention.
Decision: extend the field definition (and `FieldSchema`) with `options`, `label_mode` (visible/hidden/inline),
`width` (full/half/third/two-thirds/quarter on a 12-col grid → columns or rows), `class`, and `attrs`;
`SchemaResolver` parses + normalizes them, **whitelisting `attrs`** (drops `name/id/type/class/required/value/
aria-describedby` and any `on*` handler) so a definition can never override structural/security wiring or
inject inline JS. Extract a dedicated **`FieldRenderer`** (SRP, unit-tested) that renders the whole field per
type: text-like inputs, textarea, select (options), radio + checkbox-group (accessible `<fieldset><legend>`,
group submits `name[]`), single checkbox + toggle — all token-only, RTL, escaped. `FormBlockRenderer` is now
thin (shell + schema embed) and delegates to it. The client `collect()` maps radio/checkbox/`name[]` to the
canonical key so the shared validator sees the right shape. 7 new Pest tests (all types + label modes + width
+ attr safety); 217 unit green; contact form still renders on real WP.
Why: one renderer + an extended schema turns every future form into a field map; the attr whitelist keeps the
flexibility from becoming an XSS/override foot-gun. Deferred (documented, not blocking): true multi-section
`<fieldset>` grouping of consecutive fields, and multi-value (`checkbox-group` array) server sanitize beyond
the default text sanitizer. **Commercial-plan note:** richer field types are exactly what Pro form templates
need; the schema stays the single contract.
Status: Final.

## #47 — QueryBuilder hardened for complex scenarios; custom-table joins stay a TableRepository boundary
Date: 2026-06-11
Context: the QueryBuilder handled only single where/orderBy/limit/with. The brief requires nested meta + tax,
AND/OR groups, search, pagination, ordering by meta, date ranges, eager loading (no N+1), and custom-table
joins, each proven by tests.
Decision: extend the builder (still a **pure arg-builder** — never instantiates WP_Query) with `orWhere`,
`whereMeta` (typed), `whereBetween` (NUMERIC range), `metaRelation`, `whereTax`/`taxRelation`, `whereDate`
(date_query), `search`, `orderBy(..., numeric)` (meta_value_num), and `paginate` (capped per-page + paged +
found-rows on). Backward compatible: a single AND meta clause stays a bare list (no `relation` noise), so the
existing exact-shape tests pass unchanged; `relation` is added only for >1 clause or an explicit OR. Eager
loading (already batched via `post__in` in QueryExecutor) confirmed no-N+1. **Custom-table joins are NOT
forced through WP_Query** — they remain the spec-011 `TableRepository` boundary (typed CRUD + prepared
`where`), a deliberate seam, documented as such. 11 new unit tests (one per scenario + a compose-all);
execution + hydration covered by the existing integration test; 227 unit + integration green.
Why: WP_Query is the right engine for post-backed entities and prepares every value clause; faking arbitrary
SQL joins through it would be unsafe and unidiomatic, so custom tables keep their own repository. Reuses the
spec-002 builder/executor split (unit-test the args, integration-test the run).
Status: Final.

## #48 — Feature flags as a typed layer over Config; the Free/Pro gate rides on features.pro
Date: 2026-06-11
Context: the Config engine (Dotenv→Options→Defaults) was solid but "basic"; the brief wants comprehensive
config (env, feature flags, settings UI). The settings UI (017) + env already exist; feature flags were the
gap — and the commercial plan needs a clean Free/Pro gate.
Decision: add a `config/features.php` registry + a `FeatureFlags` service over `ConfigInterface`:
`enabled($flag)`/`disabled()`/`all()`, **truthy-only** coercion (`1/true/on/yes` → on; everything else,
incl. absent, off) so a stray string never enables a flag. Because it reads through Config, every flag is
overridable per-site by a WP option (`corex_features_<flag>`, the same layer the settings UI writes) or
`.env` (`FEATURES_<FLAG>`) with **zero extra wiring** — verified on real WP (option set → `enabled('pro')`
true; deleted → false). Added `Config::enabled()` facade convenience; bound `FeatureFlags` in
CoreServiceProvider. 17 unit tests (coercion table + default + layered override + all()); 244 unit green.
The **Free/Pro split rides on `features.pro`** — Free leaves it off, Pro enables it (env or bundled option);
deferred capabilities (`mail_queue`, `dataviews_admin`, `woocommerce_kit`) are pre-registered flags.
Why: flags must layer exactly like the rest of config (no parallel mechanism), and truthy-only coercion is
the safe default; gating the commercial edition on one flag keeps a single Free/Pro codebase.
Status: Final.

## #49 — Documentation web app: Astro + Starlight under docs-app/, static, decoupled
Date: 2026-06-11
Context: there was no usable documentation (only a stub README). The brief (PART 2) requires a reference-grade
docs site shipping with the repo, runnable locally on Apache now, decoupled to move to a public site later.
Decision: build it with **Astro + Starlight** (over VitePress) for its out-of-the-box client-side search
(**Pagefind** — instant/fuzzy/keyboard, fully client-side, indexed at build), left-nav/breadcrumbs/prev-next,
light/dark, RTL-readiness (per-locale), and copy buttons — all static HTML Apache serves with no runtime.
Lives at **docs-app/**; `base` stays `/` so the build moves to a dedicated site unchanged (documented vhost
`docs.corex.local` → `docs-app/dist`, plus the repo-subpath option and the instant `npm run dev`). Authored
19 pages describing the REAL code: Getting Started (WAMP + wp-env, monorepo junctions, first-run), Guides
(forms, blocks-via-CLI, queries, branding, CLI, settings+feature-flags, mail, MVC), Architecture, Internals
Reference (hand-written index; per-class detail generated by item 9 `docs:generate`), FAQ, Troubleshooting
(the real errors: "block not supported", missing WP, broken junctions, mysqld start). Docs verified against
source (e.g. the Mail builder API corrected to `template()->with()` after reading MessageBuilder). Build is
green (19 pages, Pagefind index). `node_modules`/`dist` git-ignored.
Why: Starlight gives a professional docs experience with zero bespoke search/nav code, and static output is
the simplest thing that runs on WAMP and later on any host. Generating the class reference from code (item 9)
keeps it from drifting.
Status: Final.

## #50 — docs:generate reads the source via php-parser (no class loading) → per-class reference
Date: 2026-06-11
Context: PART 2 requires the class/API reference generated FROM the code so it never drifts; hand-writing 190+
class pages would rot instantly.
Decision: build a headless `docs:generate` on **nikic/php-parser** (already a transitive dep). `ClassDocReader`
parses each PHP file to an AST and extracts the namespace, kind, class-docblock summary, and public method
signatures + summaries — **without loading the class**, so it is pure, side-effect-free, and works on code
with unmet runtime deps. `MarkdownDocRenderer` emits a Starlight page (YAML-safe frontmatter + Public API
list); `DocsGenerator` walks the configured layer→dir map and writes `reference/<layer>/<class>.md`, skipping
unparseable files. `DocsCommand` wires `wp corex docs:generate` (gated on `class_exists('WP_CLI')`, like
make:*). Ran it: **194 reference pages** across Core/Blocks/Forms/Config/CLI/Add-ons; the docs site rebuilds
to **213 pages**, all Pagefind-indexed. The generated pages are **git-ignored** (`reference/*/`) — regenerated,
not committed — keeping the hand-written `reference/index.md`. 4 Pest tests on a fixture class (reader +
renderer + generator); 248 unit green.
Why: parsing > Reflection here (no autoload, no fatals on missing deps, pure + testable). Generating from the
source is the only way the reference can't drift; ignoring the output keeps commits clean while the mechanism
ships. **Keep-alive:** run `docs:generate` whenever code changes; the same PR rebuilds the docs.
Status: Final.

## #51 — Portfolio site kit: new corex-kit-portfolio add-on (Corex\Portfolio), CPT + dynamic grid block
Date: 2026-06-11
Context: the second showcase kit. Needed a projects domain + a gallery/grid block + portfolio templates,
reusing the now-proven blocks/build/blueprint machinery.
Decision: new add-on `addons/corex-kit-portfolio` under a **`Corex\Portfolio\`** PSR-4 prefix (NOT `Corex\Kit\`,
which already maps to the company kit — a sub-namespace there would collide). `PortfolioServiceProvider`
registers a public `corex_project` CPT (thumbnail + REST + `/projects` archive) + a hierarchical `project_type`
taxonomy on `init` (domain lives in the add-on, never the theme), the `corex/projects` dynamic block (engine
discovery, build-or-source fallback), and a `PortfolioBlueprint`. The block mirrors the corex-ui pattern:
`ProjectsRenderer` (bounded 1–24, escaped, empty-state, lazy thumbnail) takes an injected `ProjectsProvider`
(headless-testable); `WpProjectsProvider` is the sole `WP_Query` caller (bounded, no_found_rows). Portfolio
FSE templates (`archive-project` grid query, `single-project`) added to the theme as a skin, token-only.
Registered the provider in Boot's list + the PSR-4 prefix + the npm workspace. **Verified on real WP**: plugin
active (0 fatals), CPT + taxonomy registered, `corex/projects` dynamic with an editor script, server render
OK. 4 Pest tests (renderer + manifest accuracy); 255 unit green. README added.
Why: a new prefix avoids the namespace collision cleanly; the injected-provider split keeps the renderer pure
while the CPT/query stay at the boundary — exactly the spec-009 shape, so the kit is consistent with the rest.
**Commercial-plan note:** Portfolio is a strong Free-tier showcase; it adds no hard dependency.
Status: Final.

## #52 — WooCommerce kit gated behind features.woocommerce_kit + class_exists; self-disables, HPOS-safe
Date: 2026-06-11
Context: the commercial flagship kit + the Pro story. WooCommerce must never become a hard dependency
(Principle IX), and any Woo code must be HPOS-safe (woo-guard).
Decision: new add-on `addons/corex-kit-woo` (`Corex\Woo\`). The kit runs only when **WooCommerce is active
AND the `woocommerce_kit` feature flag is on** — the decision is a pure `WooKitGate::isEnabled(bool $wooActive)`
so the "never a hard dependency" guarantee is unit-tested without Woo loaded; `WooServiceProvider::boot()`
passes `class_exists('WooCommerce')` in and is a **no-op otherwise** (self-disable). Default flag is off, so a
fresh install with Woo active still leaves the kit dormant until opted in. The plugin **declares HPOS
compatibility** (`custom_order_tables`) on `before_woocommerce_init`; the kit is presentation + a `WooBlueprint`
and never reads orders by direct meta, so the woo-guard surface is minimal and HPOS-safe. Storefront display
reuses **WooCommerce's own blocks/templates** composed with Corex patterns — not re-implemented. Installed
WooCommerce 10.8.1 into the dev site (standard dep add, not a DB drop). **Verified on real WP**: active (0
fatals), self-disabled with the flag off, gate true with flag on + Woo active. 3 Pest tests; 258 unit green.
Wired Boot list + PSR-4 + README.
Why: a feature flag + runtime detection is the only way to ship a Woo kit in one codebase that also runs
without Woo; declaring HPOS compat (rather than touching orders) keeps it future-proof and guard-clean. The
flag IS the Pro/edition lever for commerce.
Status: Final.

## #53 — Deferred-spec closeout: mail queue, WP 7.0 Abilities/MCP, setup wizard — all gated + tested
Date: 2026-06-11
Context: the final build-order item — three deferred capabilities, each to be best-practice and never a hard
dependency.
Decision (three sub-items, one pattern — a pure decision + a thin, gated WP boundary):
1. **Mail queue** — a `QueuedMailer` decorator on the corex-core `Mailer` seam queues a send only when
   `MailQueueGate` says so (Action Scheduler present AND `features.mail_queue` on), else sends inline. The AS
   boundary (`ActionSchedulerDispatcher`) enqueues a scalar/array MailRequest and a worker reconstructs +
   sends it via the immediate engine. Default flag off ⇒ behaviour unchanged. AS ships with the now-installed
   WooCommerce. 4 unit tests (gate + routing + payload round-trip); Mailer resolves to QueuedMailer on real WP.
2. **WP 7.0 Abilities/MCP** — `AbilitiesProvider` registers read-only, capability-gated, REST-exposed abilities
   (`corex/list-blocks`, `corex/site-info`) on the API's `wp_abilities_api_init`/`_categories_init` hooks,
   guarded by `function_exists('wp_register_ability')` so it degrades on older cores. The data logic
   (`CorexAbilities`) is pure (reads passed-in registries) — 3 unit tests; both abilities registered on real WP.
3. **Setup wizard + demo content** — a pure `SetupWizard` turns the `BlueprintRegistry` into a choosable kit
   list + an activation `plan(name)` (de-duped modules + the kit's `featureFlags()`); a thin, admin-only
   `SetupWizardScreen` (nonce + manage_options) runs the plan: enable flags, activate module plugins, seed an
   idempotent demo Home page. Added `Blueprint::featureFlags()` (Woo → woocommerce_kit). 4 unit tests; wizard
   lists company+portfolio on real WP. 269 unit green.
Why: the gate-plus-thin-boundary shape (used for Woo, feature flags, the mail seam) keeps every new capability
optional, testable headlessly, and guard-clean; the wizard reuses the spec-017 server-rendered admin pattern
(React stepper deferred). **This completes the 13-item build order.**
Status: Final.

## #54 — Constitution v1.2.0: the Pre-Implementation Confirmation Rule (spec-first is non-negotiable)
Date: 2026-06-11
Context: a compliance review found the 13-item "Finish Corex" autonomous initiative delivered working, tested,
documented code that was **verified on real WordPress** — but it **bypassed the Spec Kit flow**: no spec files
(specs/018+) were written before the code, and the Guard Gate was run formally on only some items (self-review
elsewhere). The work was driven directly from the prose brief; the agent did not flag that "autonomous
implement-and-continue" conflicts with Principle X (spec before code) and the documented workflow.
Decision: amend the canonical constitution (`.specify/memory/constitution.md`, which `specs/constitution.md`
points to) to **v1.2.0**, adding "The Pre-Implementation Confirmation Rule (mandatory)" under Operating Rules:
confirm every request against the constitution + specs first (surface conflicts and stop); spec-before-code via
the Spec Kit flow for non-trivial work; guard-before-diff; update PROGRESS/DECISIONS + end with NEXT STEP; and
any skip requires the user's explicit, logged exception (autonomy is not itself an exception). Sync Impact
Report + version footer updated.
Why: the authority hierarchy already puts the constitution above any brief; this makes "confirm → spec → build"
the enforced default so a future prose instruction cannot silently override spec-first or the Guard Gate. The
amendment is the standing-rule prevention for the root cause this review surfaced.
Status: Final.

## #55 — Fix: mail-queue worker must register lazily (regression — textdomain loaded too early)
Date: 2026-06-11
Context: a WP debug-log audit (user-requested) found 34× "Translation loading for the corex domain was
triggered too early" notices (+ a 14× "headers already sent" cascade). The stack traced to the item-13 mail
queue: `MailServiceProvider::boot()` called `make(MailQueueDispatcher::class)` at `plugins_loaded` to register
the worker, which eagerly resolved `RequestMailer → TemplateRenderer → Layout → brand() → wp_get_global_settings()`,
loading the `corex` textdomain before `init`. A regression I introduced.
Decision: register the Action Scheduler worker **lazily** — `add_action(ActionSchedulerDispatcher::HOOK,
[$this,'runQueuedSend'])` (referencing a class constant, no resolution) and resolve the dispatcher only inside
the handler, which fires during queue processing (after init). Removed the now-dead `ActionSchedulerDispatcher::
register()`. Verified: a normal request now boots with **zero** errors/notices; 269 unit + the 3 mail
integration tests (queue worker + send) green. The other log lines were artifacts (a manual `do_action('init')`
in a debug eval) or expected (the header-injection test's security rejection), not real errors.
Why: nothing mail-related should resolve at boot; Principle II (self-boot) does not mean "do heavy/i18n work at
plugins_loaded". This is the kind of regression the Guard Gate + a debug-log check should catch — folded into
remediation P2 (formal guard re-run) and the retrospective spec 024.
Status: Final.

## #56 — Retrospective spec backfill (019–024): author artifacts directly from the Spec Kit templates
Date: 2026-06-11
Context: P1 requires retrospective specs for the already-delivered, verified code. Spec 018 was taken through
the full `/speckit-specify→/plan→/tasks` slash-command flow. Specs 019–024 are the same shape (reconcile
existing code to a spec).
Decision: for 019–024, author each spec's artifacts (spec.md + checklists/requirements.md + plan.md + tasks.md,
with research/data-model/contracts/quickstart only where they add value) **directly from the Spec Kit
templates** rather than re-orchestrating each slash command. The orchestrators mainly scaffold files from those
same templates; producing the artifacts in the identical structure IS the spec-first deliverable and keeps the
trace, while making the backfill of near-identical retrospectives tractable. `.specify/feature.json` is updated
per spec; CLAUDE.md SPECKIT pointer tracks the active one.
Why: the compliance fix is the existence of reviewed specs that match the code, not the invocation mechanism;
this stays within the Spec Kit flow (same templates, same artifacts) while finishing the backfill efficiently.
Forward (non-retrospective) specs 025–027 will use the full slash-command flow since they precede new code.
Status: Final.

## #57 — Remediation P2/P3: formal Guard Gate run + the five clean-code fixes applied
Date: 2026-06-11
Context: the compliance review tracked a formal Guard Gate re-run (P2) and five clean-code findings (P3) across
the 13-item initiative. With the P1 spec backfill complete (018–024), P2 ran clean-code-guard on the new
production code and P3 applied the fixes — preserving behavior (269 unit green before and after).
Decision: (1) `QueryBuilder::orderBy($field,$dir,bool $numeric)` split into `orderBy()` + `orderByNumeric()`
(no boolean flag argument; CQS/Clean-Code Ch.3); the only callers were tests + docs, updated. (2) Extracted
`Corex\Kit\BlueprintActivator` (enable flags / activate modules / seed demo) out of `SetupWizardScreen` (SRP:
the screen now only renders + gates + delegates; container autowires the activator). (3) Replaced the
inline-style `1.5rem` spacing fallback in `SetupWizardScreen` with WordPress core's admin `.card` class
(core-API-first, token-free, no new stylesheet). (4) Extracted `AbilitiesProvider::registerReadOnlyAbility()`
— the shared ability shape (category + edit_posts permission + readonly/REST meta) — so each call site supplies
only what differs. (5) Documented the `FieldSchema` 10-parameter constructor as an explicit, justified
value-object exception (immutable, independent, fully-defaulted presentation attributes built with named args).
Why: the constitution's Guard Gate does not accept self-review; these are the audit's exact findings, fixed
without changing observable behavior. Finding-set source: PROGRESS "COMPLIANCE REVIEW" clean-code list 1–5.
Status: Final. (The admin-screen Principle-VII policy — hand-rolled nonce/cap vs declarative middleware — remains
P5, decided separately.)

## #58 — Remediation P5: admin-menu screens are exempt from the route middleware but use a shared AdminGuard
Date: 2026-06-11
Context: Principle VII requires routes to declare middleware and forbids controllers hand-writing security
checks. `AdminDashboard` (settings) and `SetupWizardScreen` both hand-rolled the same `current_user_can` +
`isset($_POST[...])` + `wp_verify_nonce(sanitize_text_field(wp_unslash(...)))` dance. The question (P5): are
admin-menu screens "routes" under Principle VII?
Decision: **No — admin-menu screens are exempt from the declarative middleware Pipeline**, because that
pipeline is built for the Corex REST/AJAX controller lifecycle (it carries a `Request`/`Response` through the
onion `Pipeline`); WordPress `admin_menu`/`admin_init` page callbacks have no Corex `Request`. **But they MUST
NOT hand-roll the check** — a new thin `Corex\Security\Admin\AdminGuard` (corex-core `Security/Admin/`)
centralizes it: `authorized($cap='manage_options')` and `verifiedPost($field,$action,$cap)` (the cap → field
presence → unslash+sanitize+verify gate, in one place). `AdminDashboard` + `SetupWizardScreen` now inject it
(container-autowired) and call `guard->authorized()` / `guard->verifiedPost(...)`; the duplicated security logic
is deleted. 5 Pest tests (`AdminGuardTest`) cover every branch.
Why: two real callers today (not speculative), identical duplicated security knowledge (DRY), and a single
place to harden later. Forcing the request/response pipeline onto a non-request admin callback would be the
wrong abstraction. Constitution amended to **v1.2.1** with the Principle VII scope clarification.
Status: Final.

## #59 — `wp corex reset`: a pure planner + a fail-closed gate, destructive wipe behind a typed safeguard
Date: 2026-06-11
Context: spec 025 — a reset command with a reversible-ish soft mode and a destructive full mode (DB wipe). The
risk is a wipe firing by accident or from an automated path.
Decision: split the command into a pure `ResetPlanner` (mode + gathered `ResetInventory` → ordered `ResetPlan`
of `ResetAction`s, no WP), a pure `ResetGate` (`permits()` — soft always, full only when `confirmed`), a thin
WP-CLI `ResetCommand` (gathers the inventory, plans, dry-runs/refuses/executes), and a `ResetExecutor` (the WP
boundary). The destructive `db-wipe` is reachable only through three independent checks — the typed safeguard
`--yes-i-mean-it` sets `confirmed`, the gate permits only then, and the planner emits `db-wipe` only for full
mode — with the decisive check being the pure, unit-tested gate. Soft mode deactivates **add-ons** only (the
framework plugins + theme stay), clears `corex_*` options/flags, and removes the wizard-seeded demo, touching
nothing else. "Fresh Corex starter" = clean WP + Corex theme + Corex core, no add-ons/options/flags/demo.
Why: fail-closed (Principle VII) for an irreversible action, with the safety property provable at the pure
layer (no WP/DB needed to test it). Verified live: soft + full dry-runs preview correctly, and `--hard` without
the safeguard refuses with zero changes. 7 unit + 2 integration tests; wp-guard + clean-code clean.
Status: Final. (The wipe itself is not run against the dev DB — its gate is proven by the refusal path + units.)

## #60 — Add-on manager: dependency-aware toggles that refuse + explain (no silent cascade)
Date: 2026-06-11
Context: spec 026 — a "Corex Add-ons" admin screen to enable/disable each corex-* add-on (plugin + feature
flag) with dependency awareness. The question: what happens on a dependency conflict?
Decision: **refuse + explain, never cascade.** Disabling an add-on an active add-on requires is refused
(naming the dependent); enabling an add-on whose required dependency is inactive is refused (naming the
missing dependency); the rendered list shows the reason on each blocked add-on. The decisions live in a pure
`AddonRegistry` (the known add-ons + their `requires` edges — kits require corex-ui, mirroring the blueprints)
+ a pure `AddonManager` (`canEnable`/`canDisable` + `missingDependencies`/`blockingDependents`), so the safety
property is unit-tested with no WP. The `AddonsScreen` renders + gates (shared `AdminGuard`, cap + nonce) and
delegates plugin/flag writes to `AddonActivator`; a single toggle keeps the plugin activation and the feature
flag in sync. Lives in corex-config beside AdminDashboard + SetupWizardScreen (same menu, guard, discipline).
Why: silent cascades (auto-activating deps, auto-disabling dependents) cause surprise side effects; deterministic
refusal keeps the admin in control and the state always consistent. 9 unit + 1 integration tests; wp-guard +
clean-code clean; the screen hook is confirmed wired on real WP (the menu render is the Apache-gated smoke).
Status: Final.

## #61 — Block library expansion: scalar-attribute server-rendered component blocks; accordion via native <details>
Date: 2026-06-11
Context: spec 027 — grow the corex/* library with the component blocks kits need. The design tension: rich
multi-item blocks usually need bespoke repeater editor UI (React), which is heavy.
Decision: ship four new server-rendered dynamic blocks — `corex/stat`, `corex/testimonial`, `corex/pricing`,
`corex/accordion` — driven by **scalar/text attributes** edited via sidebar `TextControl`/`TextareaControl` +
`ServerSideRender` preview, not bespoke repeaters. Multi-item blocks read a **simple per-line attribute**
(pricing features = one per line; accordion items = `Title | Content` per line). The **accordion uses native
`<details>`/`<summary>`** — fully accessible + keyboard-operable with **no JavaScript**. Each block drops into
`addons/corex-ui/src/Blocks/` and is **auto-discovered** by the corex-blocks engine (no registration change);
each renderer is a pure `BlockRenderer` (attributes → escaped, token-only HTML), unit-tested headlessly. JS
multi-panel tabs + a media-repeater gallery are deferred to a later Interactivity-API increment.
Why: keeps the editor UX simple and the render server-authoritative + testable, while still covering the
common component vocabulary; native `<details>` gives accessible disclosure for free. 5 unit tests; token-only
scan clean; built + verified live (all four register dynamic, in the Corex category, with compiled style +
RTL). wp-guard + clean-code clean.
Status: Final.

## #62 — Developer/ops handbook: split-by-audience (in-repo docs/) vs docs-app; class reference stays generated
Date: 2026-06-12
Context: a large "official documentation" brief (multi-OS setup, Docker, deployment recipes, CI/CD, team
workflow, cookbooks, class reference, en/ar i18n). It overlapped the released docs-app/ (Astro+Starlight, spec
022) and proposed a hand-written per-class reference — conflicting with DECISIONS #50 (the reference is
generated by `wp corex docs:generate` so it can't drift) and with FRAMEWORK §4 (which reserves docs/ for
supplementary docs). Per the brief's own STEP 0 + the source-of-truth hierarchy, the conflicts were surfaced
and the user chose the structure.
Decision (user-approved): **split by audience.** `docs-app/` stays the published product/API docs + the
**generated** class reference. A new in-repo `/docs` GitHub-native Markdown handbook owns only the content
docs-app lacks — multi-OS setup, Docker dev/prod, deployment recipes (Azure/AWS/cPanel) + CI/CD, team workflow,
cookbooks, troubleshooting — and **links** to docs-app for architecture + the class reference (zero
duplication). The brief's hand-written class reference is **dropped** in favour of the generator (#50). i18n via
an `en/` + file-for-file `ar/` placeholder mirror + glossary + translation-memory (code identifiers never
translated); docs-app keeps its own Starlight locale system (independent surfaces). Delivered spec-first
(Principle X) as spec 028, in phases D1–D12 (one per session); FRAMEWORK §4 is updated in the first content PR
(Working-Guide Part F). No new runtime/build dependency (redis/mailpit/nginx are documented dev-stack options
only — Principle IX). Mermaid diagrams (GitHub-native, no image pipeline). Repo CI stays GitHub Actions;
Azure-Pipelines-for-the-repo is deferred to /clarify.
Why: a second copy of getting-started/architecture/reference would drift (the failure docs-guard exists to
prevent); splitting by audience gives each surface a home with no overlap, and in-repo Markdown renders on
GitHub where operators/contributors read ops docs. Honors specs 019/022 + #50.
Status: Final (structure). Open: repo-CI choice (GitHub Actions vs Azure Pipelines) — /clarify.

## #63 — Inline-editable blocks: the dynamic-and-RichText hybrid; form selector over free-text slug
Date: 2026-06-12
Context: spec 029. Every Corex block was server-rendered + edited only via InspectorControls (right pane) — no
inline canvas editing, and the form block made you type a slug ("contact"). The tension: the constitution wants
dynamic/server-rendered blocks (Principle VI), but modern inline editing usually implies static save-markup.
Decision: use the **hybrid** — the block's `edit` renders `RichText` (inline on the canvas) bound to block
**attributes**; `save: () => null`; the PHP `render_callback` reads those attributes and outputs the markup.
The block stays dynamic AND gains inline editing (one source of truth). Rich attributes render with
**`wp_kses_post`** (safe inline HTML), plain fields keep `esc_url`/`esc_html`. The four component blocks
(stat/testimonial/pricing/accordion) are converted; pricing `features` and accordion `items` become **array**
attributes (repeatable RichText rows), with the renderers keeping the legacy delimited-string parse as a
**fallback** so already-placed blocks still render. The form block replaces its free-text `formSlug` with a
**SelectControl** populated from a new cap-gated read-only route `GET corex/v1/forms` (slug + label only).
Why: fixes the #1 editor-UX complaint (edit in the canvas like a page builder; pick data from a list) without
abandoning the dynamic-block principle or the server-render contract. 23 Jest + renderer/REST Pest tests;
300 unit green; wp-guard clean (kses for rich, cap-gated REST). Browser-visual is env-gated. This establishes
the inline-block architecture the library expansion (spec 035) builds on.
Status: Final.

## #64 — Admin data management: a DataSource abstraction behind one DataViews screen
Date: 2026-06-12
Context: spec 030. Form submissions were stored (corex_submission CPT) but invisible in admin, and custom tables
(TableRepository) had no admin UI — the user couldn't find or manage their data.
Decision: a **Corex → Data** React screen renders a `@wordpress/dataviews` table of a selected **DataSource**
(`key/label/columns/rows/total/delete`). Form submissions are the reference `SubmissionsSource` (shaped via an
injected `SubmissionsReader` so the row-shaping is unit-tested; `WpSubmissionsReader` is the WP_Query boundary);
any add-on registers a custom-table source implementing the same interface to appear in the same screen. A
cap-gated `DataController` serves it: `GET corex/v1/data/<source>` (`manage_options`) and
`DELETE .../<id>` (`manage_options` + REST nonce). DataViews is accessed from the runtime `wp.dataviews` global
(declared as a `wp-dataviews` script dep) with a plain-table fallback, so the bundle stays lean and the screen
works across WP versions. Lives in corex-config beside the other admin screens (shared AdminGuard).
Why: one generic screen serves both submissions and custom tables; the abstraction is what makes custom-table
data manageable without per-table UI. 8 unit tests; live-verified the controller shapes 33 real submissions
(cols=3); both block/admin bundles build; wp-guard clean (cap+nonce REST). React-visual is env-gated.
Status: Final.

## #65 — Kits build a real site: Blueprint::pages() + idempotent, tracked, reversible seeding
Date: 2026-06-12
Context: spec 031. Applying a kit created no pages — only the wizard seeded a single demo Home, once. The site
stayed empty; kits looked broken.
Decision: `Blueprint::pages()` declares a kit's pages (`{title,slug,content,front?}`), composing the kit's
existing corex/* patterns/blocks (never invented). A pure `KitPagePlanner::toCreate()` skips slugs that already
exist (idempotent — re-applying never duplicates). `BlueprintActivator::seedPages()` creates each planned page
(`wp_insert_post` published), marks it `_corex_kit_page`, records its id in `corex_kit_seeded_pages`, and sets
the front page where declared — replacing the old single `seedDemoHome`. The wizard's `plan()` now carries
`pages`. The soft reset (spec 025) reads `corex_kit_seeded_pages` and removes **exactly** those pages (a list<int>
in the inventory → remove actions), so a reset cleans up kit content without touching user content. Company kit:
home(front)/about/contact; Portfolio: home(front)/projects.
Why: a kit must produce a visible site; idempotency-by-slug + tracking-by-marker make it safe to re-apply and
exactly reversible. 3 unit + 311 PHP total green; verified live (about/contact created, home skipped as
pre-existing, 2nd run no-dup, reset dry-run lists the kit pages). wp-guard clean. Visual is env-gated.
Status: Final.

## #66 — Modern settings UX: per-field-type rendering + media picker + branding in the header
Date: 2026-06-12
Context: spec 032. The settings screen rendered every field as a bare input — you pasted a logo URL instead of
uploading, the captcha driver was free text, and the branding was hard to find.
Decision: `SettingsForm` renders per **field type** (text/email/url/password input, `media` picker, `select`,
`checkbox`) via a `control()` switch — registry-driven, every value escaped per type (esc_url for media,
esc_attr for value, options validated). The registry marks `brand.logo_url` as `media` and `captcha.driver` as
a `select`. A tiny vanilla `assets/settings.js` wires the WordPress media frame to media fields (set value +
preview), enqueued only on the settings screen (+ `wp_enqueue_media()`); the field **degrades to an editable
URL input** with no JS, so saving still works and the stored value stays the image URL `BrandingService`
reads. `AdminDashboard` shows the configured logo in the screen header (escaped, only when set) so the branding
is findable. Saving stays nonce + cap gated (AdminGuard, unchanged).
Why: uploading-not-URL and the right control per field are basic modern UX; storing the URL keeps the branding
service unchanged; the header logo answers "where's the branding". 4 form-rendering unit tests; 315 PHP total
green; live-verified the controls render + AdminDashboard resolves with BrandingService. wp-guard clean
(escaping per type, no inline px — the logo uses the HTML height attribute). Visual is env-gated.
Status: Final.

## #67 — Design system overhaul: richer tokens (shadows/radii/state colors) + element styles + a variation
Date: 2026-06-12
Context: spec 033. The design looked bare/flat — only 4 colors, 3 font sizes, 3 spacing steps, no shadows, no
radii, no element styling. Blocks looked unstyled.
Decision: expand `theme/theme.json` **additively** (every existing slug preserved, so nothing breaks): palette
+ surface-alt/border/ink-soft/primary-dark/accent-dark + state colors (success/warning/error/info); a real type
scale (xs/base/xl/2xl + sm/lg/hero); a full spacing scale (10/20/40/60/70 + 30/50/80); **shadow presets**
(sm/md/lg under `settings.shadow.presets`); and **radius tokens** (`settings.custom.radius` sm/md/lg/full →
`--wp--custom--radius--*`). Add `styles.elements` for button/link/heading (token colours + radius + spacing) and
a base line-height/block-gap. The card blocks (posts/testimonial/pricing/accordion) now use the shadow + radius
tokens for depth + rounded corners (token-only, logical CSS). A new **Editorial** style variation ships
alongside Dark. The token-only discipline is enforced: the styles test now forbids hex colours + px/rem size
literals (allowing `var(--wp--…)` tokens + unitless line-height/font-weight).
Why: a framework needs a real design system out of the box; additive expansion avoids breaking existing
blocks/patterns. 6 token tests + 320 total green; SCSS builds; token-only scans clean. Visual is env-gated.
Status: Final.

## #68 — Self-update: WP-native plugin-update flow, fail-safe, with a documented safe-edit boundary
Date: 2026-06-12
Context: spec 034. Users asked how they'd be notified of new releases and — critically — whether an update
would overwrite their work. WordPress already has a first-class plugin-update UX; reinventing it would be both
more work and less trustworthy.
Decision: Corex routes its own updates through WordPress's plugin-update flow. A pure `UpdateChecker`
(`check(currentVersion, manifest): ?array`) decides via `version_compare` whether the manifest advertises a
newer version. An `UpdateService` (corex-core) declares an `Update URI` header (so WP checks Corex, not
wordpress.org), hooks `pre_set_site_transient_update_plugins` + `plugins_api`, fetches a JSON manifest from a
configured endpoint (`updates.endpoint`, default empty) via `wp_remote_get`, and injects a standard update
object — WP's own updater installs the package. **Fail-safe:** empty/unreachable/malformed source → silent
no-op (Principle IX: the update source is optional config, never a hard dependency; Corex never phones home
unless you configure a source you control). The **safe-edit boundary** is documented + true by construction:
an update replaces framework files only (`plugins/corex-*`, framework add-ons, theme scaffold/tokens) and
never `corex-app/`, `brand.json`, content, or data — because everything you author lives outside the framework
plugins. A deployment guide documents publishing a manifest + package (GitHub Releases / static host).
Why: trustworthy, familiar, and safe-by-design updates; the pure checker keeps the version logic headless and
tested while WP does the signed install. 8 update tests + 328 total green; wp-guard clean (wp_remote_get with
timeout, ABSPATH guards, i18n'd popup string, no secret in the check). Install-from-admin round-trip is
env-gated (needs a published release + browser).
Status: Final.

## #69 — Block library v2: five marketing/layout blocks on the inline architecture (hero/cta/team/gallery/tabs)
Date: 2026-06-12
Context: spec 035. Users said there weren't enough custom blocks and the existing ones were too simple — a real
site needs hero/CTA/team/gallery/tabbed sections, editable like a modern page builder.
Decision: add five new dynamic, server-rendered blocks in corex-ui, all on the spec-029 inline-editing hybrid
(RichText `edit` → attributes; `save: () => null`; PHP `<Name>Renderer` via `corex.renderer`; auto-discovered by
the corex-blocks engine + the spec-018 build): **hero** (eyebrow/title/subtitle + gated CTA + optional
media-library background), **cta** (heading/text + gated button), **team** (repeatable members with media-library
photo + name/role/bio), **gallery** (repeatable media-library images + captions), **tabs** (repeatable label/
content). Two deliberate choices: (1) image blocks use the **WordPress media library** (`MediaUpload`/
`MediaPlaceholder`, store `{id,url,alt}`, render real `<img>` with alt + lazy/async) — never pasted URLs;
(2) **tabs ship zero view JavaScript** — an accessible CSS-only `:checked` radio/label disclosure (focusable,
arrow-key navigable), preserving Principle VI even for an interactive widget. Renderers degrade gracefully
(empty/partial input → the documented "renders nothing"/skip rules) and stay token-only (spec-033 shadow/radius/
spacing; logical CSS; structural `rem` grid tracks carry a justifying comment, the posts-block precedent).
"stats-grid" is intentionally NOT a new block — it's several `corex/stat` in a grid container.
Why: enough blocks to build a full landing page (hero → stats → team → gallery → cta) with no theme code, all
edited on-canvas, all accessible/RTL/i18n. 7 Pest renderer tests + 27 Jest (10 suites) + 335 total green; all 12
blocks build; token-only scan clean; wp-guard clean (escaping per field, esc_url media, lazy img). Editor/visual
behavior is env-gated.
Status: Final.
