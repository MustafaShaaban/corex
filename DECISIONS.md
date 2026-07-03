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

## #70 — Release readiness: Site Health probes, one-step version stamping, shared i18n domain, OSS hygiene
Date: 2026-06-12
Context: spec 036, the "Finish Corex" release-readiness bundle. A site couldn't self-diagnose; the plugin/theme
headers drifted from the release tag (read `0.1.0`); the text domain wasn't loaded; and the repo lacked the
open-source files contributors/researchers expect.
Decision: ship two pure engines + hygiene. (1) **Health** — a `HealthProbe` interface + small concrete probes
(PHP/WP version, block theme active, brand present, uploads writable) folded by a pure `HealthReport` (overall =
worst status; `hasCritical()`); a `HealthModule` registers them into WordPress **Site Health** (`site_status_tests`)
and `wp corex doctor` renders the same report with a non-zero exit on critical (CI/SSH-friendly). Probes are
advisory where appropriate (classic theme / missing brand → recommended, never a hard failure — Principle IX).
(2) **Versioning** — a pure `VersionPlan` computes per-file header + `COREX_*_VERSION` edits for a target semver
(rewrites only the first/header `Version:` line + every constant; returns only changed files → idempotent);
`wp corex version <semver> [--dry-run]` applies/previews across the framework plugins, theme, and add-ons.
(3) **i18n** — one shared literal `corex` text domain loaded on `init` by corex-core; a `composer i18n:pot` step
writes `plugins/corex-core/languages/corex.pot`. (4) **Hygiene** — `LICENSE` (GPL-2.0-or-later, assembled from the
bundled WP license text), `CODE_OF_CONDUCT.md` (Contributor Covenant, linked not reproduced), `SECURITY.md`,
`.editorconfig`, and GitHub issue/PR templates. "Demo content" from the roadmap line was already delivered by
spec 031 (kits seed real pages), so it is not re-added here.
Why: a 1.0-track framework must self-diagnose, keep versions aligned automatically, ship translation-ready, and
carry standard OSS files. The two engines stay pure + unit-tested; Site Health + WP-CLI are thin boundaries. 15
new tests (HealthReport 4 + Probes 6 + VersionPlan 5) + 350 total green; composer valid; wp-guard clean (Site
Health escaping, ABSPATH guards, real WP hooks). `.pot` generation + Site Health UI are env-gated.
Status: Final.

## #71 — Insights dashboard: a pluggable, scored, graceful provider seam (PSI performance + agent-readiness/Cloudflare)
Date: 2026-06-12
Context: spec 037 (user-requested). The user wanted "is the website agent-ready" + "Google insights for
performance" as two admin widgets with a Run button — one Cloudflare, one Lighthouse — and asked for it to be
genuinely useful, not just the literal ask.
Decision: build a **Corex → Insights** dashboard in corex-config on a pluggable `InsightProvider` seam. Two
providers ship: **Performance** (Google PageSpeed Insights / Lighthouse → score + Core Web Vitals + top
opportunities) and **Readiness** (the site's agent-readiness — HTTPS, `llms.txt`, sitemap, agent-permitting
robots, exposed MCP abilities — scored natively, enriched by a Cloudflare URL-scan when a token is configured).
Beyond the literal ask: (1) a pure scoring vocabulary (`Grade`: 0–100 → A–F + good/recommended/critical, shared
with the health screen); (2) every provider's normaliser/scorer is **pure + unit-tested** (`PsiNormalizer`,
`CloudflareNormalizer`, `ReadinessScorer`), the fetch/REST/cards thin; (3) results are **cached + history-kept**
(`InsightStore`); (4) **graceful degradation** (Principle IX) — no key/token → a useful "configure me" state, an
async Cloudflare scan → a `pending` result, never an error/fatal; (5) **security** (Principle VII) — runs are
`manage_options` + REST nonce and **secrets never appear in a response** (the `InsightResult` value carries only
scores/metrics/recommendations); (6) the readiness card is useful with **zero** third-party config because the
native signals always score. The admin cards are a small vanilla `apiFetch` script (no build) with token-fallback
admin-palette CSS (wp-admin context, where Corex theme tokens aren't loaded). Secrets are set as write-only
password fields in Settings (spec 032). A new card = one more registered provider, no UI change.
Why: a trustworthy, extensible, self-contained insights surface that works out of the box and never blocks the
page. 18 new tests (Grade 3 + Store 3 + PSI 3 + Readiness 3 + Cloudflare 3 + Controller 3) + 368 total green;
wp-guard clean (remote get/post + timeout, cap+nonce on run, escaped output, no secret echo, conditional
enqueue). Live PSI/Cloudflare runs are env-gated.
Status: Final.

## #72 — Custom tables in the admin: opt-in ManagedTable → auto-registered DataSource (no new UI)
Date: 2026-06-12
Context: spec 038 (user-requested, raised repeatedly). The Data screen (spec 030) could show any DataSource, but a
custom table only appeared if you hand-registered one — the user wanted "if I created a custom table I should find
the table view for it in the admin like post types."
Decision: a Corex-managed table now appears in Corex → Data automatically. A pure `ManagedTable` (name + label +
ordered columns) registered in a `ManagedTables` registry (corex-core, bound in DataServiceProvider) is turned by
corex-config into a `TableDataSource` (key `table-<name>`) that implements the spec-030 `DataSource`; the
ConfigServiceProvider seeds the `DataRegistry` with one per managed table, so the existing screen + REST +
AdminGuard render it with **no new UI**. The `$wpdb` access is a thin `WpTableDataReader` boundary — every query
**prepared** (`%i` identifiers, `%d` ids), the page read **bounded** (`LIMIT/OFFSET`), the count prepared — while
the row/column shaping (drop extra columns, default missing, id + declared columns) is the pure, unit-tested
`TableDataSource`. **Opt-in by design** (Principle IX): Corex never enumerates arbitrary tables; only those an app
explicitly marks managed appear, behind a namespaced `table-` key. Read + delete only (matching submissions);
row editing is out of scope.
Why: the most-requested gap from the deep review — custom data visible + manageable in the admin like a post type,
safely, with one declaration and zero UI code. 5 new tests (ManagedTable/registry 2 + TableDataSource 3) + 373
total green; wp-guard clean (prepared + bounded). Live admin view is env-gated.
Status: Final.

## #73 — Easy option pages: a declarative OptionPage reusing the settings form via a FieldSections seam
Date: 2026-06-12
Context: spec 039 (user-requested). The user wanted it easy to create a custom admin option page. The settings
screen (spec 032) already had per-field-type controls + secured save, but they were tied to the one global
SettingsRegistry — no way to reuse them for a custom page.
Decision: a declarative `OptionPage` value (slug, title, menu label, capability, parent, fields) registered in an
`OptionPageRegistry` becomes a real admin settings screen. The reuse is enabled by extracting a tiny
`FieldSections` interface (`sections()` + `keys()`) that **both** `SettingsRegistry` and `OptionPage` satisfy, and
retyping `SettingsForm` to it (no behaviour change — existing settings tests stay green). The `OptionPageScreen`
adds each page's menu (top-level or a submenu of its parent), renders with the shared `SettingsForm` controls, and
saves on `admin_init` — verifying the page **capability** + a **per-page nonce**, unslashing + sanitising each
value by its field type (Principle VII), persisting via the existing `SettingsStore` (so values are readable via
`Config`). `password` fields stay write-only. A `wp corex make:option-page <Name>` generator scaffolds a page
definition (the spec-003 engine + a new stub, WP-CLI-gated). The pure pieces (OptionPage, registry, the generator
output) are unit-tested; the screen + CLI command are thin boundaries.
Why: one declaration + one register call gives a developer a secured, token-styled, fully-functional option page
with zero form/nonce/save code — reusing the settings controls rather than reinventing them. 6 new tests
(OptionPage 4 + registry 1 + generator 1) + 379 total green; wp-guard clean (cap + nonce + sanitize + escape,
prefixed menus). Live admin render/save is env-gated.
Status: Final.

## #74 — Junction/symlink-safe block asset URLs + a health probe (spec 040)
Date: 2026-06-13
Context: a deep review worried that add-on block assets 404 with malformed URLs
(`…/wp-content/plugins/C:/wamp64/www/corex/addons/…`). Verified: under the current Windows-junction mount all
33 asset URLs are correct (0 malformed). The failure only appears if a block dir is realpath-resolved outside
WP_PLUGIN_DIR (POSIX symlink mounts, a realpath() call, or the PHP realpath cache), where `plugins_url()` can't
strip the prefix. So this is preventive hardening + observability, not a live bug fix.
Decision: a pure `Corex\Blocks\BlockPathResolver` maps any discovered block dir back to its WP_PLUGIN_DIR-relative
mount location before `register_block_type`, applied at the single `DynamicBlockRegistrar` chokepoint every
provider routes through (no per-provider change). `PluginMountMap` is the realpath boundary (scandir + realpath
per plugin entry → realTarget⇒mount-name). Already-under-plugins paths return byte-for-byte unchanged (no
regression). A `BlockAssetsProbe` (+ pure `AssetUrlHealth`) folds into the spec-036 health seam and flags any
registered `corex/*` block whose asset URL embeds a filesystem path, in Site Health + `wp corex doctor`.
Why: the bug's worst trait was silence (a 404 editor asset with nothing in the log); the framework must not rely
on the junction accident across dev/CI/Linux mounts. Verified against a synthetic realpath path (headless) and
live (0/17 malformed, probe = good). 11 tests; 415 total green. wp-guard clean.
Status: Final.

## #75 — Kit apply never leaves a blank front page: create/adopt/skip + reset safety (spec 041)
Date: 2026-06-13
Context: `KitPagePlanner::toCreate()` skipped any slug that already existed and `BlueprintActivator::seedPages()`
set the front page only inside the create loop — so a pre-existing empty page at a kit slug was skipped and never
populated/assigned. (Note: the live "Home" page was NOT actually blank — it holds a `wp:pattern` reference that
renders; the headline live symptom was a measurement error. The fix is still correct and it created the genuinely
missing About/Contact pages.)
Decision: a pure `Corex\Provisioning\PagePlanner` classifies each declared page **create** (slug absent) /
**adopt** (exists but empty or an un-populated kit placeholder → populate in place) / **skip** (exists with user
content → never touch), from per-slug signals (`PageContent::isBlank`) the WP boundary supplies. `BlueprintActivator`
populates adopted pages, sets the front page **after** the loop for a created|adopted home, records the disposition
in `_corex_kit_page` (`created`|`adopted`), and returns a value-object `ApplyOutcome`. The CLI `ResetExecutor`
branches on that meta: **created → delete** (as before); **adopted → empty + untrack** (never delete a page the
user owned). The pure provisioning value objects live in **corex-core** (`Corex\Provisioning\`) so spec 042 reuses
them without a core→add-on dependency.
Why: a site kit must produce a populated front page and must never overwrite user content or delete a user's page
on reset. Pure classifier is headlessly testable; full suite green. wp-guard clean. DECISIONS supersedes the old
binary KitPagePlanner (removed).
Status: Final.

## #76 — Unified prompt-to-apply kit activation + Site-status card (spec 042)
Date: 2026-06-13
Context: the real "disconnect" — enabling a kit (Addon Manager) only flipped a plugin + flag and created no
content; seeding lived in a separate wizard, so enabling a kit changed nothing visible, and submissions (though
present + served) were buried.
Decision: a corex-core `KitProvisioner` interface (+ `NullKitProvisioner` default, `KitSummary`, `ApplyPreview`)
is the seam corex-config depends on — resolved optionally so it degrades gracefully when no kit framework is
active (Principle IX); the kit framework binds the real `BlueprintKitProvisioner`. The user chose **prompt-to-apply**
(not auto-apply): enabling a kit add-on queues a pending prompt (`PendingKits`); `KitActivationNotice` renders a
dismissible banner previewing create/populate/skip + front page (read-only, reusing spec-041's classifier via
`BlueprintActivator::classify`), with Apply / Not-now gated by the shared `AdminGuard`, then a "what changed"
summary. Apply routes through the one shared `BlueprintActivator` (no duplicated seeding). A Corex dashboard
"Site status" card (`SiteStatusCardRenderer` + pure `SiteStatusCard`) shows applied kits, the live submission
count linked to Corex → Data, and the front-page status, with an actionable empty state.
Why: makes activation visible, consensual, and transparent — the fix for "enabling does nothing / can't find my
data." Pure view models + adapter unit-tested; 404→415 total green across 040/041/042. wp-guard clean. Live-verified
the provisioner resolves to the real adapter and previews read-only. Browser-visual confirmation is env-gated.
Status: Final.

## #77 — One response envelope + a buildless window.Corex runtime (spec 043)
Date: 2026-06-13
Context: spec 043, the keystone of the 043–052 roadmap. Forms, admin actions (Insights/Data), and future
REST/headless each shaped their own JSON and hand-rolled their own fetch/nonce/error plumbing (the form's
`view.js`, the vanilla `insights.js`, the Data React app). The brief asked for a unified response contract
(item 8) + a vanilla frontend kit (item 9).
Decision: a pure, immutable `Corex\Http\ResponseEnvelope` value object (corex-core) is the one wire shape —
success `{ ok, message, data }`, error `{ ok, code, message, errors?, details }` — built via `success()`/
`validation()`/`error()` and never carrying a secret; a thin `EnvelopeResponder` maps it to a WP_REST_Response
(200/422/403/400). The client half is `corex-runtime`, a **buildless** `window.Corex` (no jQuery, no build step;
modelled on the existing `insights.js` IIFE) registered by a new `HttpServiceProvider` and **enqueued only where a
form/screen declares it** (Principle VI) — `Corex.api` (nonce-attaching request that always resolves to a
normalised-envelope Result, timeout/network/non-JSON → error, never throws — a documented contract, not a swallowed
error), `Corex.forms.bind` (schema-mirrored validation reusing the spec-020 `data-corex-schema` + the existing DOM
hooks, server stays authoritative), `Corex.loading` (disable/spinner/`aria-busy`/dedupe/restore), `Corex.notices`,
and the `corex:request:*`/`corex:form:*` events. The migration is **additive/backward-compatible**: `SubmitController`
now emits the envelope but preserves its authoritative pipeline status (e.g. 429) and mirrors `values` at the top
level for one release; the superseded `view.js` is now a thin bootstrap and `validation.js`/`validation.test.js` are
deleted (the runtime is the single validator source — no duplication). Token-only CSS with wp-admin fallbacks
(DECISIONS #71 precedent), logical/RTL, WCAG (live-region status + `aria-busy`).
Why: a uniform contract every later surface (044 admin, 045 data-pro, 046 headless, 049 starter slice) stands on,
and a reusable client primitive so a new form/request needs one `bind`/`api` call and zero bespoke plumbing.
Tests: +11 Pest (ResponseEnvelope 7 + EnvelopeResponder 4) → **426 unit green**; +11 Jest (api/forms/loading/events)
→ **40 JS green** (net of the deleted validation suite). Guard Gate clean: wp-guard (conditional enqueue, nonce,
escaped/`textContent`, REST mapping), clean-code (removed a speculative `forceFetch` flag), docs-guard (new
frontend-runtime guide + fixed a stale `validation.js` doc reference).
**US4 done:** the Insights + Data controllers now emit the envelope (additive, statuses preserved); `insights.js`
and the Data React app call `window.Corex.api` and read `envelope.data` (the dead `@wordpress/api-fetch` import
removed); `InsightsScreen` + `DataAdminScreen` declare `corex-runtime` as a script dependency. Both rebuilt; 426
Pest + 40 Jest still green. Live browser-visual confirmation is environment-gated (Apache down), as for every spec
since 018.
Status: Final (043 fully implemented across US1–US4; only the Playwright browser smoke is env-gated).

## #78 — Admin control panel + integration diagnostics (spec 044)
Date: 2026-06-13
Context: spec 044 (roadmap item, keystone-built-on-043). The Corex settings felt like a flat form; captcha was
configured blind; PageSpeed failures showed a vague "could not be read"; add-ons gave no explanation; headers
credited a non-existent "team". Reuses 032/026/037/016/012 + the 043 envelope/runtime — no new store, no new driver.
Decision: a control-panel layer of **pure services** over the existing settings. `Corex\Config\ControlPanel\
{DomainStatus,ControlPanelStatus,OnboardingStep,OnboardingChecklist}` derive a per-domain status (configured/
needs_setup/error) + an onboarding checklist from the already-stored settings; `ControlPanelView` renders status
cards (status by icon+text, not color alone — WCAG) + the checklist, wired into the autowired `AdminDashboard`
with a token/admin-fallback `control-panel.css` (conditional enqueue). Captcha: the `SettingsRegistry` section gains
a site key + v3 score-threshold/action (secret stays write-only); a **`CaptchaTestController` in the captcha add-on**
(domain ownership — corex-config gains no captcha dependency) probes the configured provider and answers with the
spec-043 envelope classified by the pure, **secret-free** `Corex\Captcha\CaptchaDiagnostic` (reads provider
error-codes to tell invalid-secret from a bad probe token). Insights: pure `SiteUrlReachability` (local/private URL
detection) + `PsiDiagnostic` (local_url/http_error/quota/invalid_key/invalid_response/ok, admin-only detail scrubbed
of key/token) now drive `PerformanceProvider` — the generic message is gone. Add-ons: the `Addon` manifest gains
summary/description/provides/needsKeys/docsUrl (additive defaults) + `needsConfiguration()`/`missingKeys()`, rendered
on the Add-ons screen. Authorship: every framework header → `Author: Mustafa Shaaban` (no "team"); convention in
CONTRIBUTING.
Why: makes the single install feel like a professional control panel and turns blind integration setup into
confident, diagnosable configuration — reusing the 043 contract for the test actions. **+38 Pest (DomainStatus 6 +
OnboardingChecklist 4 + ControlPanelView 4 + CaptchaDiagnostic 6 + SiteUrlReachability 4 + PsiDiagnostic 7 +
AddonManifest 4 + wiring) → 461 unit green.** Guard Gate clean (no secret in any response; escaped; cap+nonce).
Remaining = the two **browser-gated test buttons** (captcha + a dedicated `/insights/test` action) — the dashboard
run already shows the classified PSI message; live browser-visual confirmation is env-gated, as for every spec
since 018.
Status: Final (US1–US5 implemented + tested; the test-button JS + `/insights/test` endpoint are the env-gated tail).

## #79 — Data-management pro: queryable sources, CSV export, detail, store seam (spec 045)
Date: 2026-06-13
Context: spec 045 (roadmap). The Data tab (specs 030/038) was an unfiltered list; the brief asked for search/filter/
sort/paginate, CSV export, a readable detail view, and a decision on long-term storage (CPT vs custom table).
Decision: extend the data layer **additively** (OCP — nothing existing changed). A pure `Corex\Config\Data\DataQuery`
(clamped search/filter/sort/paginate VO) + `CsvWriter` (RFC-4180 + **CSV-formula-injection guard**; only the
source's declared columns → no secret can leak). A new `QueryableDataSource` **extends** `DataSource`
(`query`/`count`/`record`) — `SubmissionsSource` implements it (delegating to an extended `SubmissionsReader`:
`WpSubmissionsReader` adds a form-meta filter + date sort + pagination via WP_Query args, no SQL string-building),
while `TableDataSource` + the existing `DataController` payload path stay unchanged (non-queryable sources fall back
to pagination). `DataController` gains a `queryFrom`/`queryPayload` path + a GET `/data/{source}/{id}` **detail**
route (label→value fields). `DataExportController` is an `admin_post` CSV download — `manage_options` + nonce,
**bounded** to 5000 rows, only declared columns — with the pure `csvFor` unit-tested. **US4 storage seam:**
`Corex\Forms\Submission\SubmissionStore` (interface) — the existing `SubmissionRepository` (post + `corex_field_*`
postmeta) is the **default driver**; `StoreSubmissionListener` now depends on the seam (DIP), so a custom-table
driver is a swap, not a rewrite (the **custom-table driver is out of scope** — the brief's "when volume demands").
Why: makes the Data tab a real tool while keeping the change additive + backward-compatible (the existing React app
still works against the unchanged list shape). **+13 Pest → 479 unit + 40 Jest green.** Guard Gate clean (prepared/
bounded query, cap+nonce, CSV formula guard, no secret in any response). The React UI (search/sort/export/detail
controls) is the **browser-gated** follow-up, as for every spec since 018.
Status: Final (backend US1–US4 implemented + tested; the React UI controls are env-gated).

## #80 — REST resources & headless: make:api-resource + route/docs cores (spec 046, in progress)
Date: 2026-06-14
Context: spec 046 (roadmap) — make REST/headless Laravel-like but WP-native, reusing the spec-003 generator engine,
spec-005 middleware, and the spec-043 envelope.
Decision: `make:api-resource <Name>` scaffolds a complete secured resource via a pure multi-file
`ApiResourceScaffolder` (modelled on `BlockScaffolder`, render-all-before-write) + 5 stubs (controller/routes/request/
resource/test) under the app's `Api/` namespace — the controller thin + envelope-shaped, the routes declaring a
permission callback, the resource exposing only declared fields. Wired into `MakeCommand`/`CliServiceProvider`
(WP-CLI-gated). For discovery + docs: pure `Corex\Cli\Routes\{RouteDescriptor,RouteList}` (routes:list body) and a
pure `Corex\Cli\Docs\ApiDocsGenerator` (descriptors + the envelope schema + nonce/app-password security → OpenAPI 3,
**no secret**). The runtime route reader (`rest_get_server()`), the `routes:list`/`api:docs` WP-CLI commands, and the
documented headless surface (US4, nonce/app-password auth; JWT/OAuth out of scope) are the remaining boundary/docs
work.
Why: the headline DX — one command yields the correct Corex-shaped, secured, envelope REST resource — plus pure,
testable discovery/docs cores. **+16 Pest (ApiResourceScaffolder 4 + RouteList 3 + ApiDocsGenerator 5 + …) → 491
unit green.** Guard self-check clean (generated route carries a permission callback, envelope-shaped, no secret in
the OpenAPI doc; pure engine + gated command — spec-003 pattern).
Status: Final (US1 make:api-resource + routes:list + api:docs all wired; US2/US3 cores tested; RoutesReader parses rest_get_server; headless docs written.
headless docs + merge remaining).

## #81 — Asset manager & environments (spec 047)
Date: 2026-06-14
Context: spec 047 (roadmap) — a formal asset/performance layer: url/path/version helpers + per-environment
cache-busting, so a release never serves stale CSS/JS and local edits are always seen.
Decision: pure cores in corex-core `Corex\Assets` — `AssetEnvironment` (config → local/staging/production,
production-safe default; source maps only in local), `BuildManifest` (source → hashed file + hash, malformed/absent
→ empty), `AssetVersion` (local → filemtime, staging/prod → manifest hash else framework/site version; a missing
asset or a `../`/`/`/`:` traversal → safe fallback). The `AssetManager` boundary (`url`/`path`/`version`) is plain
string + native `filemtime` work (so it is unit-tested without WordPress); `AssetsServiceProvider` wires it for
corex-core (base dir/URL via `plugins_url`, env via `wp_get_environment_type()` fallback, manifest from
`build/manifest.json`, `COREX_CORE_VERSION` fallback). `assets:doctor` (pure `AssetReport`) + `cache:clear` are
WP-CLI-gated. Site plugins (spec 049) build their own manager for their own base the same way.
Why: one helper for correct, junction-safe URLs + deterministic, environment-correct cache-busting — the
asset/performance primitive the generated sites need. **+19 Pest → 512 unit + 40 Jest green.** Guard Gate clean
(traversal guard, gated CLI, no secret in the report; pure cores + thin boundary — spec-003/036 pattern). Live
enqueue/source-map behaviour is env-gated.
Status: Final.

## #82 — Media & image optimization (spec 048)
Date: 2026-06-14
Context: spec 048 (roadmap) — a real media performance plan: WebP on upload + an optimized <picture> helper +
graceful degradation + an image-support probe. Optional add-on (Principle IX).
Decision: a new optional add-on `addons/corex-media` (`Corex\Media\`, in Boot's provider list + self-gating). Pure
cores: `ImageCapability` (gd/imagick/webp/avif value object + static detect()), `ConversionPlan` (jpeg/png +
webp-capable → convert to a sibling .webp preserving the original; non-image/already-webp/unsupported → skip),
`PictureRenderer` (escaped <picture>: webp <source> + <img> fallback, lazy/async, fetchpriority=high+eager for the
LCP image, responsive srcset; no webp → plain <img>; empty alt valid), `MediaImageProbe` (advisory GD/Imagick/WebP/
AVIF → Site Health/doctor, never critical). Thin boundaries: `WebpConverter` (GD/Imagick, fail-safe — corrupt/
oversized → original), `MediaImage` helper (attachment → renderer data via WP image funcs; degrades to <img>),
`MediaServiceProvider` (gated: hooks the converter on `wp_generate_attachment_metadata` only when `canWebp()`, adds
the probe via a NEW `corex_health_probes` filter added to `HealthModule` so add-ons extend Site Health without core
depending on them). corex-media added to the AddonRegistry (rich manifest). AVIF generation + CDN out of scope.
Why: smaller, modern images by default with zero hand-written <img>, fully optional + graceful. **+9 Pest → 520
unit + 40 Jest green.** Guard Gate clean (escaped markup, fail-safe converter touching only the WP attachment path,
advisory probe, no secret; pure cores + thin boundary). Live conversion/probe behaviour is env-gated (needs GD/Imagick).
Status: Final.

## #83 — make:site client-site platform (spec 049, the agency capstone)
Date: 2026-06-14
Context: spec 049 (roadmap capstone) — the leap from "a framework" to "a platform you build client sites on with a
team + AI agents." One command should generate a correctly-namespaced client site (plugin + theme) + governance.
Decision: reuse the spec-003/046 multi-file scaffolder pattern. A pure `Corex\Cli\Site\SiteIdentity` derives, from a
name, the full client identity — namespace `<Name>Site`, plugin slug `<slug>-site`, theme slug `<slug>`, text domain
`<slug>-site`, REST namespace `<slug>/v1`, CSS prefix `--<slug>-`, option prefix `<slug>_` — **guaranteed distinct
from Corex** (a name normalising to `corex`/empty is refused). A pure `SiteScaffolder` (render-all-before-write,
like ApiResourceScaffolder) generates the site **plugin** (provider + Models/Services/Controllers/Api/Blocks/Options),
the site **theme** (valid block theme — style.css/theme.json/templates/parts, presentation only), and the
**governance** set (AGENTS.md/CLAUDE.md stating the client-only edit boundary + one-feature-one-branch-one-spec-one-PR
+ never-push-to-develop/main; README/PROGRESS/DECISIONS; a `.gitignore` ignoring local AI/cache `.corex/`/`.ai/`/
`.claude/local/` while keeping committed project memory; specs/docs scaffold). `make:site` wired into MakeCommand +
CliServiceProvider (WP-CLI-gated) with --plugin-only/--theme-only/--force/--path. Generated PHP is `php -l`-clean.
Why: the strategic centerpiece — a team/agency starts a real, correctly-bounded client site in one command, with the
client/framework separation enforced by the generated AGENTS/CLAUDE + namespacing. **+10 Pest (SiteIdentity 4 +
SiteScaffolder 6) → 530 unit + 40 Jest green.** Guard Gate clean (pure engine + gated command; generated governance
accurate; no secret). **US3 starter vertical slice (one working model→service→controller(envelope)→block→option) is
the documented follow-up** (the empty correctly-namespaced structure already works); the `wp/` repo layout + Azure
pipeline + update packaging are spec 050, the design-system SCSS depth spec 051.
Status: Final (US1 plugin+theme + US2 governance + US4 flags/command shipped; US3 starter slice is a follow-up increment).

## #84 — Team ops & distribution (spec 050)
Date: 2026-06-14
Context: spec 050 (roadmap) — close the distribution loop + enforce the client/framework boundary, on top of the
shipped spec-034 update mechanism + the spec-049 boundary.
Decision: two pure cores in `Corex\Cli\Release` — `ReleasePackagePlan` (`includes(path)` = framework src minus
tests/specs/node_modules/client/secrets; `manifest()` = the spec-034 format) and `ComplianceCheck`
(`evaluate(changedFiles, forbiddenPrefixes, allowFramework)` → {passed, violations}, matching by **path prefix** not
substring, with an override) — wrapped by thin WP-CLI-gated commands: `compliance:check` (CI fails a PR that edits a
Corex framework folder, naming the files; passes client plugin/theme/docs/specs), `package:update` (emits the
framework-only manifest), `docs:sync`/`docs:serve` (local docs access — `.corex/docs/` is git-ignored by the spec-049
generated `.gitignore`). Plus `guides/deployment.md` (Azure DevOps per-site repo + App Service + branch policies
requiring review + a green pipeline + compliance + secrets/uploads/rollback). No secret in any package/manifest/docs.
Why: the boundary that spec 049 documents is now **enforced** in CI, and the framework can actually be packaged for
the spec-034 self-update — the team/agency distribution loop. **+7 Pest → 537 unit + 40 Jest green.** Guard Gate
clean (pure cores + gated commands; prefix-match avoids false positives; no secret). The live ZIP build + git diff +
docs serve are env-gated boundaries.
Status: Final.

## #85 — Design Language System in corex-ui (spec 051)
Date: 2026-06-14
Context: spec 051 (roadmap) — give Corex a documented, WordPress-native Design Language System. The block library +
tokens ship across 027/029/033/035; what was missing is the organizing taxonomy + catalog + a couple component gaps.
Decision: the DLS lives in **`corex-ui`** (no new `corex-dls` plugin — one home, no duplication). A pure
`Corex\Ui\DesignSystemCatalog` organizes the UI into five categories (Components/Blocks/Patterns/Templates/
Guidelines) and is **drift-tested** against the on-disk `corex/*` block.json (it can never list a block that does not
exist — like the CompanyKitManifest cross-check). The component layer gains two server-rendered, token-only,
accessible, RTL blocks following the spec-004/027 pattern: `corex/alert` (role=alert + info/success/warning/error
variant) and `corex/badge` (labelled span). Documented in docs-app `guides/design-system.md` (taxonomy + catalog +
guidelines: tokens single-source, WCAG 2.2 AA, RTL). The taxonomy borrows the *structure* of public design systems,
never any system's code/brand.
Why: turns a flat block list into a coherent, navigable, drift-protected system with one home, and fills the
feedback-component gap. **+7 Pest (DesignSystemCatalog 3 + Alert/Badge 4) → 544 unit + 40 Jest green.** Blocks built
(index.js + style-index.css + RTL). Guard Gate clean (token-only, escaped, RTL, drift-tested, no secret). Live visual
smoke env-gated.
Status: Final.

## #87 — Platform roadmap closeout (spec 053) — honesty + the unfinished tails
Date: 2026-06-14
Context: a code-grounded audit found the "ROADMAP 043–052 COMPLETE / v0.25.0" status overstated. Several backends
shipped + were unit-tested, but their user-facing surfaces were never built, and docs/checkboxes claimed
completeness the code did not support: the root `README.md` still said "bootstrap stage / no framework code yet";
049 T008 was a falsely-checked task (it claimed `--starter`/`--minimal` flags `MakeCommand::runSite` never parsed);
045's React Data UI (search/sort/export button/detail) was unbuilt; corex-captcha shipped no JS for its Test
controller; 051 DLS was a catalog + alert/badge, not a full DLS.
Decision: a forward spec **053** (full Spec Kit flow, Constitution PASS) closes the gap in four independently-
shippable user stories, **adding no new architecture** (every backend already existed): **US1** rewrite README +
reconcile PROGRESS/045/049 checkboxes + add a **§D.5 documentation-in-every-PR rule** (surface↔change mapping +
honesty clause; generated reference left to `docs:generate`) + a stale-phrase sweep; **US2** build the Data screen
controls over pure, unit-tested `dataClient.js` helpers (search/filter/sort/paginate/CSV-export-button/detail
drawer/loading-error-empty), superseding the minimal DataViews table, with the export linking to the existing
`corex_data_export` admin-post handler (GET, not the POST the draft contract said — drift fixed); **US3** a vanilla,
no-build `captcha-admin.js` Test button (secret-safe: it renders only the envelope's ok+message) + an insights
failed-run now surfaced inline; **US4** the `make:site --starter` example slice (`packages/cli/stubs/starter/`) +
a standalone starter-theme asset architecture (wp-scripts build, dev maps, minified prod, hashed `*.asset.php`, an
`Assets` url/path/version helper) behind a `starter` scaffolder option + `--starter`/`--minimal` flags. Decisions
that shaped it (user, 2026-06-14): contact form = add-on; generated sites get a **standalone** starter theme (not a
child theme); WordPress core lives in a `wp/` subdirectory; CSV export only (Excel/PDF deferred); AVIF/CDN/Azure
Blob deferred to a future increment. Non-scope: new DLS atoms → spec **054-corex-full-dls**.
Why: false "complete" claims are the mechanism by which the gap hid; correcting them (and adding the docs-in-every-PR
gate) prevents recurrence, and the tails are the highest-value, lowest-risk work since the servers already exist and
are tested. Built spec-first, TDD, with the Guard Gate (wp/clean-code/test/docs) run per story. **551 Pest + 52 Jest
green** (was 544 + 40). Browser execution of the spec-052 E2E/console sweep remains the env-gated step (Apache/wp-env
+ a browser); the suites are ready in `tests/e2e/`.
Status: Final.

## #86 — Visual & E2E verification in CI (spec 052, the final roadmap spec)
Date: 2026-06-14
Context: spec 052 (roadmap finale). Every spec since 018 ended "env-gated — needs a browser." This makes browser
verification a permanent CI gate instead of a perpetual follow-up.
Decision: a dedicated `.github/workflows/e2e.yml` provisions wp-env (Docker), builds blocks, activates Corex,
installs Playwright + chromium, and runs `npm run test:e2e` on PRs + nightly (a heavier browser job, separate from
the fast `ci.yml` unit gate). A new `tests/e2e/console.spec.js` (+ shared `tests/e2e/helpers.js`) is a console-error
sweep: it attaches console/pageerror listeners and **fails on any console error** (not warning) on the block editor,
the Corex settings screen, and a front-end page — the assertion that finally surfaces item-20-class block/asset
errors. A tiny documented allow-list exempts known third-party noise (transient network, favicon); the default is
zero tolerated errors. The Definition of Done (CONTRIBUTING) now states UI changes are browser-verified via the E2E
smoke + console sweep, with the local run path (wp-env + `npm run test:e2e`). Creds come from env/wp-env defaults —
no hard-coded secret.
Why: turns "no one has looked at the console" into "CI looks every run," and closes the standing browser-unverified
gap as a durable gate. **Execution is environment-dependent by nature** (needs wp-env + a browser, which is exactly
the gate); the headless deliverable — a valid workflow + a valid E2E/console spec (node --check clean) + the DoD
docs — is complete. 544 Pest + 40 Jest still green (no unit change). Guard Gate clean (test-guard, docs-guard).
Status: Final.

## #88 — Full DLS (spec 054) — native-first: one new block, the rest core/styles/tokens/docs
Date: 2026-06-14
Context: spec 051 shipped a thin DLS (a taxonomy catalog + `corex/alert`/`corex/badge`). Spec 054 turns it into a
full Design Language System. The gap analysis (`research.md` D2) audited every candidate UI element against
WordPress core and the existing tokens, and that evidence **corrected the scope**: radius + layout tokens already
existed (the real token gaps were motion/focus/z-index), and **most "components" are core blocks to document or
Corex block styles, not new blocks.**
Decision: build native-first across four user stories (full Spec Kit flow, Constitution PASS, TDD, Guard Gate per
story). **US1** — expand `DesignSystemCatalog` to the full six-category taxonomy with a `mechanism` field, drift-
checked both ways (a corex-block entry can exist only for a registered `corex/*` block), + publish the gap analysis.
**US2** — add the only missing token groups to `theme.json` as runtime CSS custom properties — `custom.motion`
(duration + easing), `custom.focus` (width/color→accent/offset), `custom.z` (base→toast) — + a Foundations doc for
every group. **US3** — the **only justified new block is `corex/modal`** (native `<dialog>`: focus-trap, ESC,
`::backdrop`, `aria-labelledby`, degrades without JS — behavior core cannot express); everything else ships as
`register_block_style()` variants (`corex-card`/`corex-section`/`corex-empty` on `core/group`, `corex-striped` on
`core/table`, `corex-secondary`/`corex-ghost` on `core/button`) + a token-only `.corex-skeleton` utility; the
toast is the spec-043 `window.Corex.notices` runtime, not a block. **US4** — 5 section patterns in `PatternLibrary`
(section-header, content-split on `core/media-text`, stats on `corex/stat`, FAQ on `corex/accordion`, latest-news
on `corex/posts`) guarded by a pattern-drift test (a pattern may compose only blocks that exist); 3 FSE page
templates (`page-landing`/`page-contact`/`page-form`) registered in `theme.json` `customTemplates`; and a docs-app
Design System section (index/components/patterns/templates), each component with when-to-use / when-not-to-use.
Non-scope: rebuilding core-covered elements (pagination, nav submenus, links, form controls), copying any external
design system's code/brand/names, and a public marketing site. Deferred (documented in the gap analysis): drawer,
popover, JS tooltip, stepper, a forms validation-summary.
Why: a design system's value is a known, navigable, drift-proof vocabulary — not a pile of bespoke blocks that
duplicate core and rot. "Don't custom-block everything" is the deliberate, evidence-backed outcome; each new block
must earn itself, and only the modal did. **563 Pest + 55 Jest green; docs build 268 pages.** Guard Gate clean
(wp/test/docs). Env-gated tail: the spec-052 Playwright modal a11y sweep (suites ready in `tests/e2e/`).
Status: Final.

## #89 — Release v0.26.0 + spec 055 not warranted (project at a completion milestone)
Date: 2026-06-14
Context: spec 053 (closeout) and spec 054 (full DLS) were both merged to `develop` (PRs #30, #32) but unreleased.
After the 053 correction, three forward specs were named: 053 (done), 054 (done), and a conditional
**055-documentation-productization** — "if docs scope warrants a separate spec."
Decision: (1) Cut **Release v0.26.0** for the 053+054 batch following the established v0.x rhythm — `wp corex
version 0.26.0` stamped all 15 framework headers/constants, CHANGELOG `[0.26.0]` + README status updated, committed
on `develop`, merged `develop`→`main` (no-ff) as "Release v0.26.0", tagged `v0.26.0`, GitHub release published,
**main CI green**. (2) **Do not open spec 055.** The documentation scope it would have covered was substantially
absorbed: 053 rewrote the README as an honest entry point + added the documentation-in-every-PR rule (§D.5), and 054
delivered the docs-app Design System section (foundations + per-component when-to-use/when-not-to-use + patterns +
templates + gap analysis). A separate productization spec would be inventing scope (YAGNI).
Why: specs 001–054 are delivered, tested, and released (v0.18.0 → v0.26.0); there is no remaining unbuilt or unspecced
work. The only standing remainder is **environment-gated browser verification** (the spec-052 Playwright modal a11y
sweep + Data-flow + console-error E2E), which is a CI gate run via wp-env, not new build scope — and cannot run in
this headless WAMP (no Apache/browser). Reopening with new scope is a fresh user-driven direction, not a continuation.
Status: Final.

## #90 — Junctioned add-on block assets 403 (spec 040 gap, caught by the spec-052 console sweep)
Date: 2026-06-15
Context: with Apache up, the spec-052 Playwright suite was finally executed against the live site. After hardening
the suite (see below), its **console-error sweep caught a real bug**: the block editor loaded 6× `403 Forbidden`
on block asset URLs malformed as `…/wp-content/plugins/C:/wamp64/www/corex/addons/corex-careers/build/blocks/jobs/
style-index.css` — a filesystem path glued onto the plugins URL. Only `corex-careers` + `corex-kit-portfolio`
were affected; `corex-ui` (same `addons/` location) was fine.
Root cause (traced with runtime instrumentation, not guessed): the spec-040 `BlockPathResolver` correctly maps a
block dir back under `WP_PLUGIN_DIR`, but `register_block_type_from_metadata()` then `realpath()`-resolves the
block.json back to the real `addons/` path and derives the asset URL via `plugin_basename()`, which can only map a
realpath back under `WP_PLUGIN_DIR` when the symlink/junction is recorded in WordPress's `$wp_plugin_paths` global.
WordPress populates that **only for plugins it activates itself** (`active_plugins`, via `wp_register_plugin_realpath()`
in wp-settings). The affected add-ons are **loaded by Corex's Boot provider list, not WP's active_plugins**, so their
junction was never registered → broken URLs. `corex-ui`/`corex-email`/`corex-captcha` happened to also be WP-active,
which is why they worked — an inconsistency, not a real difference.
Decision: add `Corex\Blocks\PluginRealpathRegistrar` (corex-blocks). At boot, before any asset URL is derived, it
replays `wp_register_plugin_realpath()` for every junctioned mount the `PluginMountMap` knows about (pure
`pluginFiles()` computes the `<entry>/<entry>.php` candidates for symlinked entries; the WP call is the boundary).
This teaches WordPress where every Corex-loaded add-on really lives, so `plugins_url()`/`plugin_basename()` resolve
correctly for all of them — matching the WP-activated ones. A no-op for real-dir plugins and in headless tests.
Verified live: the 6 malformed URLs are gone, the console sweep is clean, and the registered `corex/jobs` /
`corex/projects` style src now resolve under `/wp-content/plugins/corex-…/`. Also hardened the env-gated E2E suite
so it runs reliably (the spec-052 gate, finally executed): WP 7.0 inserter selector ("Block Inserter"); contact-form
assertions match the native-`required` + JS-schema design; `storageState` global-setup auth (kills the cold-first-
login flake); deterministic editor-ready waits instead of `networkidle`; 60s timeout headroom.
Why: a design system / add-on platform whose block CSS/JS 403s in the editor is broken for users on a symlinked dev
or CI layout — exactly Corex's own monorepo setup. This closes the spec-040 gap for Corex-loaded add-ons. **566 Pest
(+3) green; the full Playwright suite 6/6 green twice.** Guard Gate clean (wp/clean-code/test). The console sweep
earned its keep on its first real run.
Status: Final.

## #91 -- Stable client readiness becomes spec 055 before client-site work
Date: 2026-06-18
Context: v0.26.1 is the verified release baseline at `e30b1fe`. The next project priority is using Corex for two
real company-identity websites, but the Phase 0 audit and project handoff identified framework-level risks that
should be handled before client branding begins: add-on runtime gating, metadata consistency, CI/security posture,
make:site validation, deployment readiness, component coverage, Free/Core vs Pro boundaries, and multi-agent safety.
Decision: open `specs/055-stable-client-readiness` as the next Spec Kit feature. This is a new user-approved
readiness/stability spec, not the previously rejected `055-documentation-productization` idea from Decision #89 and
not the final Corex visual redesign. The spec authorizes planning and later approved implementation phases only.
Why: starting client websites immediately would risk building on unresolved framework and workflow gaps. Starting the
visual redesign now would violate the current priority. A focused readiness spec keeps work small, reviewable, and
reversible while making the client-site phase safer.
Status: Active.

## #100 -- Dependency security is exposure-aware and fails closed
Date: 2026-06-19
Context: After Dependabot PRs #36-#45 merged, raw audits still report 50 root npm dependency instances, 4 docs-app
instances, and no Composer advisories. The npm findings are primarily transitive development-tool paths, while the
open Pest 4 update crosses two major versions and fails the required test check because intentional log output is
now classified as risky.
Decision: Spec 056 will preserve raw audit visibility and classify each advisory as shipped runtime, CI, local
development server, build/test transitive, or unreachable. High/critical runtime or CI findings cannot be excepted.
Development-only exceptions require exact advisory identity, a severity ceiling, exposure evidence, a compensating
control, an owner, a review date, and an upstream removal trigger. Automated forced downgrades are prohibited. Pest
4 remains a separate compatibility migration and must preserve unexpected-output detection rather than suppressing
it globally.
Why: raw vulnerability counts do not describe reachability, but ignoring development dependencies hides CI and
local-tool risk. Exact, expiring exceptions make accepted risk reviewable without allowing audit noise to justify
unsafe dependency downgrades or false-green security claims.
Alternatives considered: `npm audit fix --force` (rejected: it proposes unrelated breaking downgrades); omitting all
development dependencies (rejected: CI and local servers are still trust boundaries); merging Pest 4 on assertion
count alone (rejected: required CI is red and output semantics changed).
Status: Active.

## #95 -- Spec 055 US3 validates generated client scaffolds and deployment profiles through readiness
Date: 2026-06-18
Context: US3 requires Corex to be ready for generated client-site repositories before real client branding starts.
The risk is twofold: `make:site` could emit an incomplete or framework-coupled scaffold, and deployment guidance
could remain prose-only without a machine-checkable readiness surface.
Decision: make US3 readiness executable. `SiteScaffoldValidator` validates generated minimal and starter scaffolds
for isolated plugin/theme folders, governance files, specs/docs placeholders, namespace and CSS/option prefixes,
theme token strategy, starter example files, and unresolved placeholders. `wp corex readiness` now creates temporary
minimal and starter scaffolds and reports their validation as the `make-site` category. Deployment readiness is a
profile matrix modeled by `DeploymentProfile`/`DeploymentReadinessCheck`; profiles that require live infrastructure
report `ENVIRONMENT-GATED` with exact profile evidence instead of failing local readiness. Client branding
compliance wraps the existing release compliance check with Corex-framework forbidden paths.
Why: generated client-site work should be blocked by executable evidence, not manual inspection. Environment-gated
profiles keep local readiness honest without pretending Azure, Docker, wp-env, or host-panel checks ran locally.
Status: Active.

## #96 -- Spec 055 US4 treats company-site UI readiness as a native-first matrix
Date: 2026-06-19
Context: US4 asks for the minimum company-identity UI/content needs to be scoped before client-site work, while
FR-001 and SC-008 explicitly prohibit starting the final Corex visual redesign. The risk is that readiness work
could turn into custom blocks for every section or client-specific styling inside framework folders.
Decision: model US4 as a pure component coverage matrix plus readiness check. `ComponentCoverageDefaults` maps the
first company-site needs to existing Corex blocks, WordPress core block styles, patterns, form fields, admin
components, utilities, or deferred add-ons. The readiness command now reports `component-coverage` PASS only when
required needs are present, mechanisms are known, native-first violations are absent, and no visual redesign scope is
present.
Why: a matrix gives the first client sites an actionable native/FSE path without adding unnecessary block scope or
branding. It also keeps the final visual redesign as a later, explicit spec instead of letting it leak into
readiness work.
Status: Active.

## #94 -- Spec 055 US2 records multi-agent safety as explicit work-unit evidence
Date: 2026-06-18
Context: US2 requires multiple AI agents or humans to coordinate without overwriting each other and without relying on
chat memory. The spec asks for branch/spec ownership, git status first, no work on `main`, no overlapping edits
without coordination, guard gates, and final report requirements. The readiness command already inventories the
`multi-agent` category, but no persistent work-unit store exists in the plan.
Decision: model multi-agent safety as a pure release value object plus readiness check:
`Corex\Cli\Release\AgentWorkUnit` records branch, spec path, task IDs, files owned, handoff, verification, guards,
and status; `MultiAgentReadinessCheck` fails `main`, overlapping owned files, and completed work that lacks required
evidence. The governance docs define the durable handoff format, while `wp corex readiness` continues to list
`multi-agent` as a scheduled category until a later task defines a command input/source for real work-unit records.
Why: this satisfies US2's machine-checkable rule surface without hardcoding one session's transient work into the CLI
report. It also keeps storage out of scope, matching the plan's "no new persistent storage" constraint.
Status: Active.

## #92 -- Spec 055 runtime gating is a boot-level provider-resolution concern
Date: 2026-06-18
Context: `/speckit-plan` for `specs/055-stable-client-readiness` inspected the current runtime shape and found that
`plugins/corex-core/src/Boot.php` still passes every first-party provider directly into `Application`, including
optional add-ons and kits. `plugins/corex-config/src/Addons/AddonManager.php` and `AddonRegistry.php` manage the
admin Add-ons screen state and dependency toggle decisions, but they do not prevent an optional service provider from
booting and registering hooks/routes/REST endpoints/blocks/admin menus/assets/migrations/tables/cron jobs.
Decision: spec 055 implementation should introduce a lower-level provider-resolution/runtime gating contract that
`Boot` can use before optional add-on providers boot. The admin Add-ons UI may display and mutate activation state,
but it is not the runtime authority. WooCommerce gating remains both dependency-based and Corex-state-based: the
existing pure `WooKitGate` pattern is retained, and the provider resolver must prove Woo behavior is absent when
WooCommerce is unavailable or the kit is inactive.
Why: the safety requirement is "disabled add-ons register no unsafe behavior." That property must hold before UI
screens, routes, blocks, assets, or cron callbacks can register. Central provider resolution is more auditable than
scattering defensive checks across every provider and avoids treating WordPress plugin activation as the only source
of truth for Corex-loaded junctioned add-ons.
Status: Active.

## #93 -- Spec 055 foundational readiness report uses the data-model category enum
Date: 2026-06-18
Context: The readiness-report contract's prose list names WooCommerce gating separately, while
`specs/055-stable-client-readiness/data-model.md` defines the `ReadinessFinding.category` enum as
`runtime-gating`, `metadata`, `ci-security`, `make-site`, `deployment`, `component-coverage`, `free-pro`, and
`multi-agent`. Foundational task T006 explicitly says to create the skeleton using the fields from `data-model.md`.
Decision: implement the foundational `ReadinessReport` completeness check against the data-model enum. Woo-specific
runtime behavior remains a required US1 finding/check, but the separate command/report wiring can decide whether to
render it as a sub-finding under `runtime-gating` or expand the report taxonomy after the US1 tests define the
contract.
Why: the skeleton should not invent a ninth enum value before the runtime-gating tests and command output exist.
Keeping the pure model aligned with `data-model.md` lets T006/T007 close without prematurely deciding the final CLI
presentation shape.
Status: Active.

## #97 -- Spec 055 US5 protects adoption and trust basics in Free/Core
Date: 2026-06-19
Context: US5 requires Corex to distinguish the free framework baseline from future commercial scope before client-site
work starts. The risk is that accessibility, RTL, i18n, spam protection, basic forms, setup, or deployment docs could
accidentally become paid-only because they are also commercially valuable.
Decision: model the boundary as a release matrix and readiness check. `FreeProBoundaryItem` supports `free-core`,
`pro-candidate`, `deferred`, and `out-of-scope` classifications, but rejects any security-critical item classified as
`pro-candidate`. `FreeProBoundaryDefaults` seeds the FR-017 basics as Free/Core and the FR-018 advanced capabilities
as Pro candidates. `wp corex readiness` reports `free-pro` PASS only when required Free/Core capabilities are present
and security-critical basics are not Pro candidates.
Why: adoption and trust basics are part of the framework contract, not upsell leverage. Pro can exist around advanced
vertical workflows, automation, and operations tooling, but it must not weaken the baseline needed to ship safe,
accessible, RTL/i18n-ready client sites.
Status: Active.

## #98 -- Spec 055 Phase 8 closes repo-owned readiness controls and gates external controls
Date: 2026-06-19
Context: US1 readiness identified missing repo-file CI/security controls, while Phase 8 requires final verification
and durable handoff before client-site work can start. Some controls are owned by files in this repository, while
others live in GitHub repository settings or target deployment infrastructure.
Decision: add repo-owned Dependabot and CodeQL configuration now, and keep external controls explicit as
environment-gated readiness findings. `.github/dependabot.yml` covers GitHub Actions, Composer, root npm, and
`docs-app` npm dependencies. `.github/workflows/codeql.yml` runs CodeQL for JavaScript/TypeScript on pushes, pull
requests, weekly schedule, and manual dispatch; PHP remains covered by Composer validation, PHP lint, Pest, and the
CI workflow because GitHub CodeQL does not recognize `php` as a supported CodeQL analysis language. `wp corex
readiness` may report repo-file CI/security controls as PASS, but branch protection, required checks, secret
scanning, Docker/wp-env, Azure, and shared-host verification remain environment-gated until verified in those
systems.
Why: repository-owned controls should not stay as avoidable blockers once the implementation knows how to verify
them. External settings and infrastructure are still real release gates, but recording them as environment-gated keeps
local readiness honest and prevents agents from claiming they ran checks that require GitHub or Docker state.
Status: Active.

## #99 -- CodeQL readiness follows GitHub-supported languages only
Date: 2026-06-19
Context: After PR #34 merged, GitHub Actions reported `CodeQL (php)` as failed. The job log for run
`27798745963` failed during `github/codeql-action/init@v3` with `Did not recognize the following languages: php`.
The JavaScript/TypeScript CodeQL job and the PHP lint/headless test job passed.
Decision: remove `php` from the CodeQL matrix and keep CodeQL scoped to `javascript-typescript`. Treat PHP static
coverage as the existing PHP quality gate: Composer validation, PHP lint, Pest, and any future PHP-specific security
scanner selected by a dedicated spec.
Why: a repo-owned security control must be executable. Keeping an unsupported CodeQL language creates a permanent
required-check failure and weakens readiness more than a clearly scoped, passing CodeQL workflow plus explicit PHP
test/lint coverage.
Status: Active.

## #101 -- Post-readiness capabilities release as v0.27.0
Date: 2026-06-19
Context: v0.26.1 was a patch release for junctioned add-on asset URLs. The accumulated unreleased work now includes
runtime add-on gating, the `wp corex readiness` command and its client-site/deployment/component/Free-Pro matrices,
repository security controls, and the fail-closed dependency-audit policy from Specs 055 and 056.
Decision: release this batch as v0.27.0. Use the existing `wp corex version` contract to align the 15
plugin/theme/add-on version surfaces, merge the release preparation through required CI and CodeQL checks, then
create the annotated `v0.27.0` tag and GitHub release from the verified merge commit. Keep Docker/wp-env, browser
automation, and external deployment evidence explicitly environment-gated when those environments are unavailable.
Why: the batch adds user-visible framework and release-safety capabilities, so a pre-1.0 minor release communicates
the scope more accurately than v0.26.2. Tagging only after the protected-branch checks preserves the repository's
tag-as-release source of truth.
Status: Final.

## #102 -- Spec 057 production logo package: approved Core X mark + font-outlined wordmark
Date: 2026-06-20
Context: Spec 057 T059-T064 were blocked pending an owner-approved production CoreX logo package with provenance.
The owner approved the design handoff root ("Design project questions answered (3)" /
design_handoff_corex_brand_system) as the authoritative source and confirmed the locked winner: the "Core X" mark —
five rounded 12u modules on a 48x48 grid, 3u gutters, 2.5u corner radius, four corners `currentColor`, center module
brass `#c9a25e`. The handoff documents the wordmark as live Space Grotesk 600 text, not as vector paths, and the
logo contract forbids `<text>` and font-text dependencies.
Decision: (1) Extract the symbol/lockup/monochrome/contrast geometry verbatim from the documented mark; the
monochrome variant is all-`currentColor` single ink and the contrast variant uses the AA-darkened brass `#ad8643`
documented for light/high-contrast backgrounds. (2) Produce the wordmark/lockup glyphs by *mechanical outline
extraction* (fontTools) from the already self-hosted, OFL-licensed Space Grotesk variable font instanced at
wght=600 with -0.035em tracking — not by tracing, redrawing, or reinterpreting. (3) Ship five optimized SVGs under
`plugins/corex-config/assets/brand/` with a provenance manifest (`logo-manifest.json`: source, owner, rights,
approval date, viewBoxes, filenames, sha256 checksums, variants, accessible usage). (4) Retain the legacy navy/cyan
`corex-logo.svg` only as rollback/migration evidence; never ship `.dc.html` prototype runtime files. (5) Refine the
`LogoAssetContractTest` external-URL assertion (T063 scope) to forbid genuine external-resource URLs while allowing
the W3C SVG namespace literal, because standalone SVGs require `xmlns="http://www.w3.org/2000/svg"` to render and
the namespace is an identifier, never a fetched resource.
Why: faithful, deterministic extraction from the approved system and the licensed font honours "do not invent,
trace, redraw, or reinterpret the logo" while satisfying the no-`<text>`/no-external-dependency contract and keeping
the SVGs valid as standalone assets. The generator (`scripts/generate-logo-assets.py`) makes the package
reproducible.
Status: Final.

## #105 -- Spec 060 M6 admin: pure display-state resolver, reuse the existing model/screens, write-only secrets
Date: 2026-06-21
Context: M6 needed a truthful CoreX admin (add-on/settings/captcha states) + a calm scoped visual layer, without
restyling wp-admin or touching the public frontend. The runtime facts already exist (`Foundation\AddonProvider` +
`AddonRuntimeState` + the boot `AddonProviderResolver`), and the admin already has an Add-ons screen
(`Config\Addons\AddonManager`/`AddonView`), a settings screen (`SettingsForm`/`SettingsRegistry`), and the scoped
`--corex-admin-*` adapter (spec 057 US4).
Decision: (1) Add a **pure display-state resolver** — `Foundation\AddonStatus` (7 states + `isUsable`/`isInstalled`/
`canToggle`/`tone`) and `AddonStatusResolver` — ordered to agree with boot gating (`pro_required` first, then the
boot order). Bridge the existing `AddonView::status()` to it (additive `dependencyMissing`/`wooMissing`/`proRequired`
inputs, default false) so the Add-ons screen shows one honest state and toggles only installed add-ons; **no
marketplace/install-from-admin** (install is developer/CLI/deployment). (2) `Config\Settings\SettingsSectionState`
derives each settings section's display from the add-on state + a "configured" predicate; `SettingsForm` renders a
notice and disables inputs for non-active sections; the captcha section is wired so reCAPTCHA never appears active
when captcha is absent/inactive and prompts when keys are missing. (3) **Write-only secrets** — password-typed
fields (captcha secret, API keys) never render their stored value (empty input + "Saved/Not set" hint) and an empty
submit preserves the stored secret; saves stay on the shared `AdminGuard` (cap+nonce). (4) The visual layer consumes
**only** the scoped `--corex-admin-*` adapter and loads only on CoreX screens (Principle VI); the token-inventory
generator classifies `--corex-admin-*` consumer refs as the documented `raw-allowance`. Reuse the existing
screens/adapter — extend, don't rebuild.
Why: a pure typed resolver is unit-testable and keeps admin display in agreement with boot gating; reusing the model/
screens avoids duplication; write-only secrets close a real leak (passwords were rendered into `value="..."`); the
scoped adapter guarantees no global wp-admin restyle and no frontend impact. Residual (tracked): Setup-Wizard
cosmetic styling and broader US4 universal-state polish — the readiness screen already gates env-checks honestly via
the existing Insights/readiness system.
Status: Final.

## #104 -- Spec 059 M4 company kit: extend the blueprint, reuse provisioning, defer section blocks to M5
Date: 2026-06-21
Context: M4 needed full company-site page coverage with safe apply, demo levels, and SEO, without a page builder or a
broad new block library. A kit foundation already exists: `corex-kit-company`'s `CompanyBlueprint` (a pure manifest)
and `corex-core`'s provisioning (`KitProvisioner`, `ApplyPreview`, `ApplyOutcome`, `PageDisposition` =
reset/adopt/skip/conflict).
Decision: (1) **Extend, don't rebuild** — expand `CompanyBlueprint::pages()` to the full v1 content-page set and
reuse the existing provisioning for preview/apply/conflict; M4 adds no new apply engine. (2) Pages compose only the
**registered** `corex/*` patterns + the M3 header/footer + core blocks, token-only and i18n; system surfaces
(404/search/single/archive) stay owned by the universal FSE templates rather than duplicated as pages. (3) **Demo
levels** are one structure with leveled content depth (`pages($level)`; same page set/section order across
minimal/standard/full) — not three divergent copies — so the FR-005 parity holds (avoids the wrong-abstraction/
triple-maintenance trap). (4) **SEO starter** is per-page editable title/description applied as plugin-compatible
defaults — no SEO engine, no plugin dependency (Principle IX). (5) Pages whose dedicated section block does not exist
yet (services/team/case-study/locations grids) **reuse an existing pattern now and record the gap** as the M5 batch
rather than building new blocks in M4 (YAGNI; M5 scope).
Why: reusing the tested provisioning honours DRY and avoids regressions; a single leveled structure guarantees the
parity requirement; deferring section blocks keeps M4 bounded and lets the kit's real needs drive the M5 batch. A
separate recorded gap remains: `make:site` scaffolds a standalone client theme that does not yet inherit M2 tokens /
M3 parts (`specs/059-company-site-kit/make-site-verification.md`).
Status: Final.

## #103 -- Spec 058 M3 navigation: native primitives, theme markup, plugin-registered conditional assets
Date: 2026-06-20
Context: M3 needed a reusable header/mega-menu/footer system without a builder, honoring constitution Principle I
(theme is a presentation-only skin), Principle VI (assets load only where rendered), and the no-JS/RTL/WCAG
Definition of Done. The owner approved authoring the navigation/footer design handoff from ROADMAP §6 + the merged
M2 tokens (no external design package existed; none was invented — the handoff is structural/behavioral).
Decision: (1) Lean on WordPress core: the core navigation block provides the mobile-overlay/submenu a11y baseline
(focus trap, Escape, `aria-expanded`), and mega menus are the native `<details>`/`<summary>` disclosure — so they are
keyboard-operable and fully usable with **no JavaScript**, and CoreX writes minimal custom a11y. Rejected a
`role="menubar"`/custom-button-in-`core/html` approach (error-prone ARIA, broken no-JS fallback). (2) Markup lives in
the **theme**: header/footer parts and the variant patterns under `theme/patterns/*.php` (auto-registered by WP for
block themes), plus token-only `theme/assets/css/corex-navigation.css` and the buildless
`theme/assets/js/corex-navigation.js`. (3) Registration lives in a **plugin**: `Corex\Theme\NavigationServiceProvider`
(corex-core) registers the `corex` pattern category and conditionally attaches the CSS via
`wp_enqueue_block_style('core/navigation'|'corex/copyright', …)` and the JS via `render_block` only when a navigation
or `corex-mega` block renders — never globally. The asset files stay in the theme (presentation) but are
`file_exists`-guarded so the provider is a clean no-op when the CoreX theme is inactive. (4) Three layout-only
`theme.json` custom tokens (`header.height`, `header.heightCompact`, `nav.breakpoint`); no new brand values. The one
permitted raw literal is the breakpoint inside the desktop `@media` (CSS `@media` cannot read custom properties),
marked with the repo's `corex-token-allow:` mechanism. (5) The transparent/sticky header flips
`data-corex-header-state` on a passive rAF-throttled scroll listener; the visual transition is CSS, gated by
`prefers-reduced-motion`, so the module is inherently reduced-motion-safe.
Why: native primitives minimize the riskiest custom a11y code and guarantee a usable no-JS fallback; the
theme-markup/plugin-registration split keeps the theme disposable (Principle I) while honoring conditional loading
(Principle VI). Action slots (search/language/account/cart) are structural placeholders only — no optional-plugin or
commerce dependency (Principle IX); WooCommerce nav/footer deferred to M9.
Status: Final.

## #106 -- Corrective Spec 060 admin design uses one allow-listed shell and native login markup
Date: 2026-06-21
Context: PR #58 implemented the truthful-state/security foundation, but only the Add-ons badges consumed the M6
visual adapter consistently. Dashboard, Data, Insights, captcha, and control-panel CSS retained legacy WordPress/raw
values; Setup had no scoped design asset; login replaced only the logo; permission-denied callbacks rendered blank.
Decision: (1) Keep every existing state/service/action contract and add one registered-but-never-global
`corex-admin-shell` depending on `corex-admin-tokens`; `CorexAdminAssets` enqueues it only for an explicit allow-list
of current CoreX hooks. Screen styles depend on the shell and every selector is rooted in `.corex-admin`. (2) Use
`AdminPage` for the shared labelled main region, `COREX FRAMEWORK` header identity, universal states, and visible
permission denial. Split the former combined page into Overview and Settings while retaining the `corex-settings`
parent slug. (3) Keep WordPress login forms/messages/actions native; add only `body.login.corex-login`, the token
adapter, a login stylesheet, and a safe logo custom property. (4) Make dark the default semantic mapping, provide a
complete light mapping, copy the approved self-hosted M2 fonts into corex-core so plugins stay theme-independent,
and use logical CSS, responsive reflow, visible focus, text-labelled states, and reduced-motion handling. (5) Treat
browser screenshots/contrast/RTL/zoom as ENVIRONMENT-GATED when no compatible browser exists; runtime DOM evidence
does not substitute for rendered visual evidence.
Why: one scoped shell prevents drift without restyling generic wp-admin or the public frontend; native login markup
preserves WordPress authentication behavior; the shared renderer makes required states consistent; theme-independent
font assets preserve Principle II. The truthful-state model, installed-only add-on controls, no-marketplace rule,
and write-only secret behavior remain unchanged.
Status: Final.

## #107 -- Spec 060 admin visuals verified by real rendering; systemic control/heading/palette fixes
Date: 2026-06-21
Context: #106 #5 deferred rendered visual evidence as ENVIRONMENT-GATED. A browser runtime is in fact available
(Chrome + Playwright; WP live at `http://corex.local`). Rendering every CoreX admin surface authenticated, in dark
and light, exposed three systemic defects the prior source-only pass missed: (a) form inputs rendered white and
buttons rendered WP-blue because the shell styled controls through `:where(...)` (zero specificity), which WP core
admin CSS overrode; (b) card/section headings without an explicit colour inherited WP's dark heading colour and were
near-invisible on dark surfaces (also a WCAG contrast failure); (c) the `--corex-admin-*` palette (borders, raised
surfaces, semantic colours) had drifted lighter/bluer than the approved package.
Decision: (1) Style CoreX admin controls with specificity that beats WP core — `.corex-admin` + element/class
selectors for inputs, selects (single custom RTL-aware chevron), textareas, `.button`/`.button-primary`/secondary,
and Gutenberg `.components-button` variants — with a brass focus ring and dark input wells. (2) Set an explicit
heading colour for all `.corex-admin h1-h6`. (3) Realign the dark and light token adapter to the approved tokens
(border `#262a32`, raised `#1c1f26`, semantic success/warn/danger/info, plus new `--corex-admin-text-subtle`,
`--corex-admin-border-soft`, `--corex-admin-action-subtle`, `--corex-admin-shell`), keeping light-mode link/focus
darker for AA. (4) Verify by rendering each surface and comparing against the approved `.dc.html` design captures;
record real evidence in `visual-evidence.md`, superseding the ENVIRONMENT-GATED matrix.
Why: the design package is visual, so it can only be verified visually; specificity (not intent) is what makes WP
admin chrome adopt the CoreX surface. No truthful-state, security, or markup contract changed — only the visual layer
and the token values. Regenerated the Spec 057 token inventories so the consumer contract still passes.
Status: Final.

## #108 -- Company-site readiness/onboarding: docs URL resolver, add-on tiers, onboarding docs
Date: 2026-06-22
Context: A readiness/onboarding pass so a new developer can start a real company site without getting lost.
Two real runtime gaps surfaced: (a) the Add-ons screen rendered each add-on's relative docs path
(`/guides/media/`) straight into an `href`, so the browser resolved it against the *active client* WordPress
domain — never where the framework docs live; (b) neither the screen nor the docs told a developer which
packages are the required foundation vs recommended vs optional, so "what do I enable?" was guesswork. The rest
of the pass is documentation.
Decision: (1) Add `Corex\Config\Docs\DocsUrl` — one resolver that turns a relative docs path into an absolute
URL: the `docs.base_url` config key (filterable via `corex_docs_base_url`), else the framework's canonical
GitHub docs source. `AddonsScreen` resolves docs links through it and opens them in a new tab with
`rel="noopener noreferrer"`, so a docs link can never point at the client site. (2) Add `AddonTier`
(recommended / optional / site_kit / requires_woocommerce) + an `Addon.tier` field; the registry classifies
each add-on, and the screen renders an advisory tier badge beside the truthful status badge plus a "Where to
start" note (the always-on foundation — corex-core/blocks/config/forms — and "you don't need every add-on").
The tier is advisory only — the `AddonStatus` truthful-state model still governs what is installed/active.
(3) Documentation: a new end-to-end `getting-started/company-site.md`, an `guides/ai-agents.md` (framework vs
client-site boundary), named-local-site + safe-reset (WAMP), add-on tiers table, "what each layer owns" +
header/footer ownership (Company Kit), fonts (branding), don't-ship-`wp/`-deploy-`dist/` (deployment), README
"Start here" + docs-app-is-optional. A neutral **Acme** placeholder is used throughout; no real client name
enters the framework repo/docs.
Why: a relative admin docs link resolving against the client domain is a real defect; the tiers turn add-on
choice from guesswork into guidance without weakening truthful state. All additive — no existing contract
changed; the foundation plugins are deliberately not listed as toggleable add-ons.
Deferred to backlog (documented now, not built in this pass — they are real features, not docs): a CoreX Media
settings UI (WebP enable/quality/MIME, regenerate action, Site-Health probe surfacing) + frontend delivery
filter mode; a client-theme image-optimization pipeline (`src/images/` → built WebP, `npm run images`); a
`wp corex package:site` command to assemble a client-site `dist/`; `make:site` client-theme template-part
override scaffolding for header/footer; an optional curated CoreX font collection via the WP Font Library APIs.
Release: v0.27.0 predates the entire M6 admin design (now on main, 91 commits unreleased) plus this readiness
runtime. A **v0.28.0** release is warranted once this PR merges, cut via the repo's existing develop-based
git-flow-lite release flow — not unilaterally from this docs branch. Dependabot PR #60 (Astro 6→7, semver-major)
is held: its "Validate dependency advisories" check fails (changed dependency inventory needs human review), so
it is not blindly merged — it needs a dedicated branch to run docs-app install/build, handle Astro 7 breaking
changes, and refresh the dependency inventory.
Status: Final.

## #109 -- Team-safe company-site architecture: Role Gate, sites/<client>/ layout, dist builder, Azure split
Date: 2026-06-22
Context: CoreX v0.28.0 is feature-complete enough to build the first real company site, but the *team workflow*
wasn't locked: an agent/dev couldn't reliably tell whether they were editing the framework or a client site, where
client source belongs, or how a deployable artifact is produced/shipped. Spec 061 (the owner-approved final
pre-company-site goal) addresses this; it is split into PR-sized groups because some runtime (Media/WebP, the
`make:site` restructure, the client image pipeline) changes tested code and deserves its own review.
Decision: (1) **Role Gate** — every session classifies into one of four modes (CoreX Framework / Client Site /
Deployment / Docs-Planning), each with explicit allowed/forbidden edit areas, documented in root `AGENTS.md`,
`CLAUDE.md`, `COREX-WORKING-GUIDE.md` §G, the team-workflow docs, and the generated client stubs. Rule hierarchy:
Role Gate (where) → Spec Kit (what) → Guard Gate (safe to ship) → UI/UX ProMax (UI good enough). (2) **Source
layout** — repo root is the source of truth; framework source stays in `plugins/`/`addons/`/`packages/`/`theme/`/
root `specs/`/`docs/`/`docs-app/`; **client source lives in `sites/<client>/`** (`<client>-site` + `<client>-theme`
+ governance + specs/docs). `wp/wp-content/` and `dist/` are runtime/build output, never edited as source. (3)
**`dist/` is a generated, git-ignored artifact** assembled from repo source by `scripts/build-shared-host-dist.mjs`
(`npm run build:dist`, verified by `npm run verify:dist`); the server receives only its contents; it is never
committed. (4) **Deployment split** — GitHub Actions = PR/code-quality gates; Azure Pipelines (`azure-pipelines.yml`)
= build dist + SFTP deploy from release tags, credentials in Azure secrets, production runtime files
(wp-config.php/.htaccess/uploads/cache/upgrade/debug.log) protected; the SFTP step ships as a safe, approval-gated
placeholder (no real credentials). (5) Standard copy/paste AI start prompts per mode + a required SUMMARY/…/NEXT
STEP handoff format.
Why: separating framework and client work by *location* + *mode* prevents the most common multi-developer/agent
mistake (patching framework internals for one client) without new tooling; a single source-built `dist/` keeps the
symlinked dev `wp/` out of production; the Azure split keeps PR gates and deploy concerns separate.
Implemented in PR A: the role gate + prompts + layout docs + handoff format + make:site governance stubs + the
shared-host dist builder/verifier + tests + the Azure pipeline + deployment docs.
Deferred (spec 061 task groups, real features not built in PR A, each its own follow-up PR): CoreX Media settings
UI + `wp corex media regenerate-webp` CLI + frontend WebP-delivery hardening (PR B); the `make:site`
`sites/<client>/<client>-site|-theme` restructure + header/footer override scaffolding + generated-client image
pipeline (PR C, needs a back-compat/migration note for the tested generator); the optional WP Font Library curated
collection (PR D). M6 RTL/200%/keyboard acceptance remains environment-gated (recorded in the spec evidence). PR
#60 (Astro 7) stays held. Release: v0.29.0 after the runtime/generator/deployment milestone (PR A–C) merges.
Status: PR A final; B/C/D open as task groups.

## #110 -- Spec 063 new-design-gap program: truthful, batched implementation of the Gap-Closure package
Date: 2026-07-02
Context: The owner supplied a new design package (`F:\Work\CoreX.zip` — the "Corex Final Design Gap-Closure"
pass) auditing the whole CoreX product and, per its own truthfulness rule, tagging each area frozen /
owner-review / needs-another-pass / future-only. It asks to *finish the implementation-ready gaps and apply the
approved design language consistently*, explicitly forbidding fake data/charts/records/integrations/Pro/
marketplace/licensing, a full page/form/nav builder, a custom blog engine, a full AAM clone, and a full
auth/portal. This spans several roadmap areas (M6 polish + M7 Forms/Email + new admin subsystems), so it needs a
single governed program rather than ad-hoc builds.
Decision: (1) Record the design intake at `design/handoffs/063-new-design-gap-implementation.md` (path, files,
seven-state bands, exact engineering scope) and update `design/INVENTORY.md` to the package's seven-state model —
**frozen means brand + core visual system + approved admin foundation only**; every other area carries its real
state. (2) Create Spec 063 with **one parent goal** — finish the implementation-ready design gaps truthfully —
split into independently shippable batches (Phase 0–8): truthfulness/gates; Admin Overview truthful states; Forms
& Flows + Submissions Inbox + Email Studio; Data Models CRUD + import/export + migrations; Operations Mode +
Security Center + Access & Abilities (AAM-lite); Settings/media/retention/advanced; Insights + Setup Wizard; Blog
+ social sharing + Company Site Kit gaps + core blocks; docs/verification. (3) Hard invariant across all batches:
every card/metric/badge/integration shows its real state (real data or an honest empty/not-configured/gated
state); **no dead entry points** — an unbuilt or out-of-batch area is hidden or honestly gated, never stubbed as
working. (4) Owner-review bands (Operations Mode 8-mode model + real behavior changes; Security Center scope
beyond "hide wp-admin" + reversible CLI/config recovery; Access & Abilities CoreX-native, not full AAM;
Forms-vs-Flow model + extension points + retention/anonymize; Email Templates → Email Studio upgrade + safe
layout-builder boundary; safe Data-model-manager scope; Company Site Kit page coverage) **stop for owner sign-off
before implementation code**. (5) Program subsumes and sequences M7; it does not authorize Pro/commercial,
marketplace, WooCommerce internals, builders, a custom blog engine, an AAM clone, or an auth/portal.
Why: a batched, spec-first, truthful program lets the large gap list ship safely one reviewed slice at a time
without violating the package's own core rule (no fake data / no dead entry points) or the constitution
(spec-before-code, one reviewed spec at a time, Guard Gate, DoD). No existing truthful-state, security, or markup
contract changes; batches reuse the frozen M2 tokens and the merged M6 admin shell/login.
No active marketplace/Pro/ThemeForest/license/purchase wording exists to neutralize: the M6 truthful-state pass
already framed all such references as future/deferred (ROADMAP M10/M11, the free-core/paid-add-on *strategy*).
Status: Phase 0 (intake + spec + gates) final on branch `spec/063-new-design-gap-implementation`; Phase 1 (Admin
Overview truthful states) in progress; Phases 2–8 open (2–4 gated on owner sign-off).

## #111 -- Spec 064: Overview rebuilt to the approved readiness grid; rail fidelity; superseded panels
Date: 2026-07-02
Context: Owner review of the v0.32.0 admin found the Overview/Dashboard incomplete, visually unfaithful to the
approved design, confusing, and full of unintended white space. Audit (`design/audits/064-admin-dashboard-fidelity-
audit.md`) confirmed: the Overview rendered as sparse stacked full-width panels (Phase-1 summary strip + site-status
card + control-panel domain cards + activity) with duplicated submission read-outs, not the approved dense
two-column readiness grid in `Corex Admin Overview.dc.html`; and `AdminPage::railItems` mapped icons/active-state
only for the original six slugs, so the five new Spec-063 screens fell to the generic option-page icon with no
active highlight. The stale `Corex Admin Dashboard.dc.html` (event bus, repo stats, "healthy" cards) is superseded
by the truthful Overview.
Decision: (1) Rebuild the Overview as one cohesive readiness grid produced by a single `OverviewRenderer`, composing
a pure `OverviewModel` from REAL facts only — stat tiles (posts/pages/submissions/add-ons), a Launch-readiness
checklist (N of M) from real brand/kit/front-page/mail/captcha/hardening signals, an Analytics & Security panel with
honest connected/not-connected chips, a real Data-sources summary, a Forms summary, and an honest empty
Recent-activity state. No fabricated counts/scores/activity. Reuses the existing real providers (ControlPanelStatus,
HardeningChecks, DataRegistry, AddonRegistry, wp_count_posts, SubmissionsReader, lazy FormRegistry/KitProvisioner).
New dense grid CSS fixes white space/alignment/shell width. (2) Extract `HardeningFacts` so Operations & Security
and the Overview compute the hardening signal one way (DRY). (3) Fix `railItems` to map every registered CoreX
screen to a distinct icon (four new nav SVGs: forms/submissions/security/mail) and its correct active section — no
option-page fallback, no dead entry point. (4) Removed the now-superseded pure `OverviewSummary` (+ test) and fixed
a double-encoded `&amp;` in `esc_html__()` strings. (5) `SiteStatusCardRenderer` + `ControlPanelView` are no longer
used by the Overview; they are kept this pass (still tested) and slated for a follow-up cleanup rather than deleted
mid-change.
Why: the owner's complaint was fidelity + confusion + white space; the truthful direction (real Overview) wins over
the stale/fake dashboard concept, and the fix must not introduce any fake feature. All Spec-063 deferrals (mode
switching, login guard, capability editor, import/migrations, retention, Blog Pro, Portfolio, Woo, Pro, Auth) remain
deferred and honestly labelled.
Verification: rendered dark + light against the live `corex.local` install — the dense grid, all-real data, honest
empty activity, and per-screen rail icons/active state match the approved design; no white space. Pest 873 (new
OverviewModel + rail tests; updated visual contract), lint:css clean, token contract green; guards wp/clean-code/
test clean. RTL/200%/keyboard remain the environment-gated manual acceptance items.
Status: Final on branch `spec/064-admin-design-fidelity`.

## #112 -- Spec 065: admin product completion required; company-site recommendations paused
Date: 2026-07-02
Context: After v0.32.1, owner review of the admin found it not product-complete: Spec 063 shipped truthful
read-only/overview surfaces and Spec 064 corrected only part of the Overview fidelity, but many areas were left as
"future"/placeholder. The owner corrected the direction: finish the CoreX admin/dashboard/product properly, and
**stop recommending starting a company site** as the next step (a stable company-site base remains at v0.31.0
separately).
Decision: (1) Spec 065 (`specs/065-admin-product-completion/`) is the required completion milestone. Every admin
surface must show real data/state or an honest empty/error/unavailable state — no safe feature may remain a vague
future card. (2) **Only** these may remain deferred: WooCommerce kit/screens; advanced AAM / full capability-editor
/ complex role mutation; commercial/Pro/marketplace/licensing. (3) Everything else is finished now or implemented as
far as safely possible, including real Operations Mode switching, real retention behavior (with dry-run before any
deletion), Data Models record detail + import dry-run + migration overview, a safe login-protection foundation, and
a safe Access & Abilities baseline (visibility matrix). (4) **Blog is required**; Portfolio is lower-priority but
stays planned (after Blog) — Portfolio is NOT in the Woo/AAM/Pro deferral class. (5) All docs (ROADMAP/PROGRESS/
DECISIONS) remove company-site next-step recommendations and record this milestone framing.
Why: the owner is the product authority; the admin must be genuinely usable and faithful before company-site work is
recommended. The truthfulness invariant (no fake data/features) is unchanged — completion means real behavior or an
honest state, never a fabricated one.
Status: On branch `spec/065-admin-product-completion` (PR #95). Delivered + render-verified: B1 Operations Mode, B3
retention (dry-run prune), B4 Data Models CSV import dry-run + truthful migration overview, B6 Access baseline, B7
Blog (single/archive/index). B5 global fidelity verified across all ten admin screens. Portfolio next-scope is
defined (planned after Blog). Honestly deferred with an on-screen reason: visual Forms/Email builders + the
operations-mode/import commit write path. Full Pest 894, Jest 125, guards clean.

## #113 -- Spec 067 Email Studio uses real read surfaces; mutations stay gated by missing contracts
Date: 2026-07-03
Context: The owner-critical admin-completion audit requires the five Email Studio tabs and six template-detail
tabs from the approved design, while retaining the invariant that no templates, sends, routing, partials, or logs
are fabricated. The existing engine has a registry, renderer, one runtime brand layout, and an email-log repository,
but no visual-template store, partial store, per-template routing store, or result-returning test-send contract.
Decision: Build every designed navigation surface now. Read from `TemplateRegistry`, `TemplateRenderer`, the bound
`Layout`, and `EmailLogRepository`; label registry entries and site-wide logs honestly. Render HTML previews only in
sandboxed iframes. Derive the Variables browser only from detectable `{{ path }}` placeholders in registered
template output; do not guess values consumed directly in template code. Keep Edit, Routing, and Partials read-only
with their exact code/storage boundary. Keep Test Send disabled until the `Mailer` seam returns a per-send
delivered/failed result that a capability + nonce-gated action
can report truthfully; a void dispatch must not be presented as success. Use responsive token-only CSS, including
stacked template rows and a contained variables-table scroller at narrow widths.
Why: this completes the approved product surface without creating unsafe writes or false success. The existing
engine seams support trustworthy inspection; they do not yet support trustworthy mutation feedback.
Status: Final on `fix/067-admin-shell-and-completion`; dark/light routes and 375px critical views render-verified.

## #114 -- Spec 067 Access & Abilities: real denied path via the WP menu gate; audit log records only real events
Date: 2026-07-03
Context: The audit (item F) requires the designed Access tabs (Overview / Role matrix / Audit log / Access denied)
without inventing corex_* capabilities, audit history, or request-access behavior. WordPress refuses a user who
lacks a page's registered capability BEFORE the page callback runs, so an in-page denied render alone could never
be the real denied experience, and no access events existed to audit.
Decision: (1) Make the designed denied state the REAL one: a new `AccessDeniedGate` hooks core's
`admin_page_access_denied` for `corex-*` pages only, publishes a new `corex_admin_access_denied` action, and
wp_dies with the designed content at a true HTTP 403; the shared `AdminPage::permissionDenied()` renders the same
designed surface in-shell as defense-in-depth and fires the same action; the Access screen's "Access denied" tab
embeds it as a labelled preview that never fires the event. (2) A new `AccessAuditLog` (autoload-off option,
30-day window, 100-entry cap) records ONLY those real denied events; the tab states honestly that grant/revoke
entries cannot exist because CoreX never mutates roles. (3) "Request access" ships visibly disabled with the exact
reason (no workflow exists); the back action targets the WP Dashboard, not the CoreX Overview, because a refused
user cannot open the Overview either. (4) The tracked abilities remain the real WordPress capabilities CoreX
checks; role cards use real count_users() data; the permissions-plugin conflict notice appears only when a known
role-manager plugin is really active.
Why: the design's "HTTP 403 - logged to access audit" promise can only be kept at the menu gate; everything else
would be a simulated denial. Recording only real events preserves the truthfulness invariant while giving the
audit tab a live data source from day one.
Also fixed while verifying: the body-level shell canvas paint never applied (tokens lived only on the descendant
`.corex-admin` scope), leaking a light band below short pages -- tokens now bind to `body.corex-admin-screen` with
`corex-appearance-*` pinning like the login; and the matrix table leaked min-content width into the document
scroll area on phones -- contained via a mobile `minmax(0, 1fr)` shell track + `contain: layout` on the scroller.
Status: Final on `fix/067-admin-shell-and-completion`; all four tabs render-verified dark/light + 375px; real
403 + audit entry E2E-verified with a live editor-role user.

## #115 -- Spec 068: approved current design is required functionality
Date: 2026-07-03
Context: Owner review rejected the presentation-only completion boundary used by Specs 063, 065, and the early
Spec 067 audit. Multiple approved screens still exposed sample analytics, read-only inventories, planned/future
copy, disabled actions, or tabs without a persistence/service contract. The owner explicitly defined the design as
the functional contract and directed CoreX work to continue until every approved current control and workflow is
real, secure, tested, and visually verified. The owner also authorized selecting the recommended safe routine choice
without stopping for repeated approvals.
Decision: (1) Adopt `specs/068-admin-product-functional-completion/` as the authoritative completion contract while
continuing on the active PR #98 branch. It supersedes older deferral decisions only where they conflict; completed
compatible work remains. (2) Required current behavior may not be replaced by an honest disabled/read-only/future
surface. Only a genuinely absent optional dependency may gate a control, and it must provide a real resolution path.
(3) Deliver vertical slices over shared activity, ability, bounded-job, data-write, result, and mail contracts so
screens do not grow incompatible one-off backends. (4) Preserve WordPress-native posts/comments/users/roles/media
and keep the theme presentation-only. (5) Maintain direct FR/SC-to-task/test/runtime/render evidence; broad green
tests or screenshots alone do not prove completion. (6) Do not start or recommend a client/company-site project
until the Spec 068 completion audit passes.
Why: the product cannot claim completion while its designed actions are demonstrations. A single evidence-backed
contract prevents scope from shrinking around the current code, while vertical slices keep the mandated breadth
reviewable, reversible, and constitution-compliant.
Status: Final. Spec/plan/tasks approved by standing owner direction; implementation active on
`fix/067-admin-shell-and-completion`.
