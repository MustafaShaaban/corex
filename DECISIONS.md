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
