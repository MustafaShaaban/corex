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

## #116 -- Spec 068 Email Studio: immutable authoring, fail-closed delivery, and neutral routed consumers
Date: 2026-07-03
Context: The approved Email Studio design required real template/layout/partial editing, preview, routing, test
sends, logs, health, and resend. The old screen could inspect code-defined templates but had no trustworthy write
store or per-attempt result boundary. A live-send UI also needed to prevent a configuration label from impersonating
an installed delivery driver.
Decision: (1) Persist private Email Studio records behind typed repositories using append-only template, layout,
partial, capture, and attempt revisions; activation moves only a template pointer. (2) Restrict merge data to a
registered scalar schema, reject executable markup/header injection, require a real stored layout revision, and
compose preview output in a script-disabled iframe. (3) Always capture local/development mail. Staging/production
may call the bound driver only when the configured provider name matches it and the live-delivery switch is enabled;
otherwise write a typed rejected result. (4) Store redacted recipient evidence and correlation/provenance in attempts,
while private Development captures retain inspectable content for 30 days. Retries append a linked attempt and never
rewrite history. (5) Expose reads to `manage_options` and require both that capability and a REST nonce for writes.
(6) Let Forms and Access depend on the neutral `Corex\Mail\RoutedMailer` contract; add-on absence/failure preserves
their existing fallback or authoritative state change. (7) Install native layouts/partials idempotently and migrate
legacy layout payloads into explicit header/accent/body/button/footer regions without deleting site data. (8) Keep
route-rule resolution, active-template preparation, and delivery-policy dispatch as separate actors; compose the
browser client from focused panels and state/API hooks; inject the REST boundary with a typed repository record.
Why: immutable revisions make rendered mail explainable; explicit provider matching and a second live gate prevent
accidental delivery; neutral consumer seams keep the optional add-on optional; private captures plus redacted attempt
logs balance local debugging with operational privacy.
Status: Final on `fix/067-admin-shell-and-completion`; T042–T063 complete and fully guard/visual verified.

## #117 -- Spec 068 Forms: immutable flow snapshots and ordered extension-safe execution
Date: 2026-07-04
Context: The approved Forms & Flows builder requires editable persisted definitions without invalidating historical
submissions, plus custom fields/rules/actions/routing/variables/success states. Code-defined forms and a mutable
schema alone cannot preserve the exact consent, routing, or notification contract used by an earlier submission.
Decision: (1) Persist private Flow metadata separately from append-only FlowVersion snapshots through a typed store
and repository; canonical recursive key ordering produces stable checksums while field/rule list order remains
significant. (2) Reject stale version/checksum writes before appending and publish only a complete current draft.
(3) Model Draft, Published, Closed, and Expired transitions explicitly; publication moves a pointer to an immutable
version and never rewrites it. (4) Evaluate enabled routing rules by position with first-match-wins semantics and a
mandatory fallback. (5) Expose ordered field/rule/action/email-variable/success registries; custom email variables
must resolve to scalar or null values. (6) Store flow records in a private, non-UI WordPress post type behind
`FlowStore`, keeping WordPress calls out of aggregates and services.
Why: immutable snapshots make every submission reproducible; optimistic conflict checks prevent silent editor
overwrites; ordered routing and typed registries give extensions a stable seam without letting runtime callbacks or
mutable admin state leak into persisted history.
Decision extension: (7) Execute visitor and marked-test input through typed validation, protection, storage, routing,
email, Inbox, and timeline stages; retain already-stored state and typed retryability on a later failure. (8) Bind
flow emails to active Email Studio templates through neutral template/catalog capabilities, with persisted recipient
and reply-to mappings and environment-aware delivery. (9) Consume optional captcha through a Core security seam so
Forms remains add-on-neutral and remote providers fail closed. (10) Render Flow, Form, Success Message, Subscribe,
Survey, and CTA + Flow from the published immutable version; keep registered code forms as explicit compatibility.
Status: Final for T064–T090 on `fix/067-admin-shell-and-completion`; T091 external lint/browser evidence remains open.

## #118 -- Spec 068 Submissions: permission-scoped operations over durable flow evidence
Date: 2026-07-04
Context: A stored flow response contains personal data and is useful only when authorized teammates can find, work,
email, export, and retain it without inaccessible counts, stale overwrites, partial bulk mutations, or optional-add-on
coupling. The existing recent-submissions table and synchronous CSV link could not meet that operational contract.
Decision: (1) Pass an immutable actor scope into every Inbox repository query and apply the same scope to counts,
detail, workflow, bulk, email, export, and artifact access. (2) Keep submission workflow projections beside the private
submission record, use optimistic update timestamps, and append note/status/assignment/email/retention evidence to the
timeline. (3) Bind bulk mutations to an expiring single-use preview containing exact IDs and expected timestamps; abort
the whole action on stale or inaccessible state. (4) Run explicit-column selected/filter/accessible exports through
the shared bounded-job system, keep CSV artifacts private behind authorized REST, exclude marked tests by default, and
audit scope/count/columns without payload values. (5) Consume reply/resend/redacted logs through a core-neutral gateway
implemented by Email Studio when active. (6) Retain recoverable archive/trash plus irreversible personal-data
anonymization as separate confirmed actions, with marked-test inclusion always explicit.
Why: permission scope before persistence prevents count/detail side channels; optimistic and preview-bound operations
preserve teammate work; immutable template and timeline references make email and compliance actions explainable;
neutral ports preserve the optional add-on boundary; private job artifacts avoid public personal-data URLs.
Status: Final for T092–T110 on `fix/067-admin-shell-and-completion`; T111 external lint/browser evidence remains open.

## #119 -- Spec 068 Data: source-declared writes behind single-use mutation previews
Date: 2026-07-04
Context: Data sources already declared granular capability flags, but a flag alone cannot prove that New, Edit,
Delete, or bulk controls persist anything. The legacy `DataSource::delete()` seam also bypassed the shared operation
result and audit contracts, while FR-063/064/070 require every model mutation to preview before apply.
Decision: (1) Treat write support as the intersection of a declared capability, actor permission, and the source
implementing `WritableDataSource` with a real `DataWriteAdapter`; otherwise hide the action and reject authorization.
(2) Normalize and validate exact IDs, operation shape, source fields, required create values, and the 100-record bulk
ceiling before issuing a preview. (3) Persist previews for five minutes under a hashed transient key, bind them to the
actor, consume them once, and re-authorize at apply time. (4) Dispatch only through `DataWriteAdapter`; never call the
legacy source delete method. (5) Wrap the adapter result with the shared activity event ID while auditing operation
and counts but not submitted record values.
Why: capability truthfulness prevents dead UI; an exact one-time preview closes the gap between displayed intent and
persisted scope; a neutral adapter lets models choose their storage without moving SQL or WordPress CRUD into the UI;
value-free activity context keeps operational accountability without duplicating personal or secret data.
Status: Final for T112–T117 on `fix/067-admin-shell-and-completion`; Phase 6 import/export/migration/UI work continues.

## #120 -- Spec 068 Data imports: immutable dry runs are the only commit source
Date: 2026-07-04
Context: The prior CSV handler checked header width and empty rows, stored a five-minute display transient, and could
never write. It did not preserve mapping decisions, validate source field types, bind a later commit to the preview,
or produce a complete downloadable rejection artifact.
Decision: (1) Resolve each CSV column through an explicit mapping, exact field key, or source-declared import alias;
make unknown-column rejection or ignoring an explicit run policy. (2) Validate every row against writable required,
email, select, and length field contracts and persist the exact accepted values plus rejected source rows/reasons in
a private immutable run. (3) Hash the file/mapping/policy/result projection and accept commit only for the same actor,
valid state, and exact checksum. (4) Queue the accepted count through the shared bounded-job engine and create only
the stored accepted rows through `DataWriteAdapter`; never reparse or widen the source file at commit time. (5) Audit
validated/queued/completed counts without payload values and prefix spreadsheet-formula-leading report cells.
Why: a durable immutable plan makes preview and apply identical, prevents mapping/file substitution, supports bounded
retries, and preserves useful rejection evidence without allowing CSV formulas or public personal-data artifacts.
Status: Final for T118–T120 on `fix/067-admin-shell-and-completion`; export/migration/REST/UI work continues.

## #121 -- Spec 068 Data/Data Models: route-bound previews and artifacts
Date: 2026-07-07
Context: Data mutations, imports, exports, and migrations are previewed or queued through source-scoped REST routes.
Without binding the later apply/download/rollback request back to the route source and preview/run identity, a valid
token or run ID from one source could be replayed against a different URL shape or UI state.
Decision: (1) Bind Data mutation apply requests to an explicit `DataMutationApplyRequest` containing actor, route
source, token, actor label, and current time; reject apply when the consumed preview source differs from the route.
(2) Require import remap/commit routes to match the stored run actor and source before dry-run or commit state moves.
(3) Scope export history/download by route source and reject artifact downloads whose stored source differs from the
requested route. (4) Require migration apply/rollback queueing to match the previewed action/run identity so a
rollback-capable run cannot be substituted for an unrelated preview. (5) Return binary CSV/XLSX artifacts through an
explicit base64 JSON envelope so REST responses do not corrupt binary content.
Why: preview tokens and job artifacts are safety boundaries, not just UI conveniences. Route/source binding closes
cross-source replay and confused-deputy paths while preserving extension-friendly adapters and actor-scoped history.
Status: Final for T121–T132 implementation on `fix/067-admin-shell-and-completion`; current external integration,
lint, and browser gates remain open under T132.

## #122 -- Spec 068 Operations: Production launch is readiness-gated by a typed override
Date: 2026-07-07
Context: The Operations & Security screen already had a real operations-mode switch, but Production needed a stronger
launch gate than a generic checkbox. The approved design requires Production readiness enforcement, override evidence,
and no fake or divergent status between the visible checklist and the state-changing action.
Decision: (1) Represent Production readiness as an immutable `ReadinessSnapshot` with normalized check states and a
stable target hash. (2) Build the snapshot from the same local WordPress hardening facts used by the screen, mapping
hardening warnings to blocking Production launch checks. (3) Require the operator to type `PRODUCTION` before a
Production mode transition; blocking checks remain blocked without that actor-bound, five-minute confirmation. (4)
Route the admin-post Production transition through `ProductionLaunchService`, while Maintenance keeps its existing
explicit checkbox and no-lockout guard path. (5) Surface the same blocker count in the form so the UI and mutation
gate share one evidence source.
Why: a typed phrase makes the live-site transition intentional; shared readiness evidence prevents a screen/action
split-brain; actor/time/target-bound confirmations give future audit and UI layers a stable operation contract without
renaming WordPress core files or weakening the maintenance no-lockout invariant.
Status: Final for T133–T134 on `fix/067-admin-shell-and-completion`; T135+ login/access/UI/docs/security gates remain.

## #123 -- Spec 068 Operations: Maintenance recovery uses an explicit bypass filter
Date: 2026-07-07
Context: Maintenance mode must block anonymous front-end visitors without creating an owner lockout. Signed-in
administrators already pass through, but tests also need a reversible emergency recovery path that does not require
renaming WordPress files, changing stored mode, or poisoning the PHP process with an undefinable constant.
Decision: Add a `corex_maintenance_bypass` WordPress filter checked only after the stored mode is confirmed as
Maintenance and before request-context/user checks. The filter bypasses the response guard for the current request but
does not mutate the saved operations mode.
Why: a filter is reversible within the request/test process, works for emergency owner tooling, and preserves the
truthful persisted state. A PHP constant was rejected because it cannot be unset inside integration test processes and
would leak into unrelated tests.
Status: Source implemented for T135 on `fix/067-admin-shell-and-completion`; DB-backed integration execution remains
open while local WAMP MySQL refuses connections.

## #124 -- Spec 068 Login protection: hash-only attempts with trusted proxy resolution
Date: 2026-07-07
Context: Login protection must enforce failed-login limits, report activity, honor retention, and work behind trusted
proxies without collecting raw submitted credentials or analytics-grade raw IP data. It also must avoid spoofed
`X-Forwarded-For` headers from untrusted peers.
Decision: (1) Model login protection as immutable settings, context, decision, and attempt records. (2) Hash the
normalized identity and resolved client IP before persistence; never store raw passwords, raw submitted credentials,
or raw IP addresses in the attempt table. (3) Count recent failures by identity/network hash inside the configured
window; the threshold-crossing attempt is recorded as a lockout with `locked_until` and retention metadata. (4)
Resolve forwarded client addresses only when the immediate remote address is inside configured trusted proxy ranges,
supporting IPv4 and IPv6 CIDR checks. (5) Persist attempts through a `LoginAttemptStore` port backed by a managed
CoreX `login_attempts` table with retention pruning.
Why: the policy remains deterministic and unit-testable, the repository satisfies reporting/retention without leaking
sensitive login data, and trusted-proxy checks prevent attackers from choosing their own rate-limit bucket.
Status: Final for T136–T137 on `fix/067-admin-shell-and-completion`; custom login route/recovery command/UI remain.

## #125 -- Spec 068 Login route: honest 404 guard without moving core files
Date: 2026-07-07
Context: CoreX needs a custom login URL and default-endpoint protection, but the constitution and spec forbid moving
or renaming WordPress core files. The recovery path must also bypass the guard without depending on the protected URL.
Decision: Implement `LoginRouteGuard` as a reversible request guard: it registers the configured custom slug rewrite,
blocks anonymous `/wp-login.php` and `/wp-admin` requests with an honest 404 decision when protection is enabled, and
always allows authenticated users, disabled policy, unrelated public routes, and `COREX_LOGIN_UNGUARD` recovery.
Why: the default endpoints become non-discoverable to anonymous visitors without altering WordPress files or routing
internals, and recovery remains a configuration/runtime bypass rather than a secret core-file mutation.
Status: Final for T138 on `fix/067-admin-shell-and-completion`; reset command integration remains under T139–T141.

## #126 -- Spec 068 Login recovery: reset command disables gates and releases lockouts
Date: 2026-07-07
Context: Login protection can hide default endpoints and create active lockouts, so recovery must not depend on the
protected login URL and must not mutate users, roles, or passwords.
Decision: Implement `wp corex security reset-login` through `SecurityResetLoginCommand`. The command disables
`enabled` and `block_default_endpoints` in the login-protection settings, preserves unrelated settings such as the
custom slug, releases active lockouts through a `LoginLockoutStore` port, reports the restored `wp-login.php` URL, and
reports whether `COREX_LOGIN_UNGUARD` is active. The route guard separately honors `COREX_LOGIN_UNGUARD` as a runtime
bypass.
Why: resetting only the protection gates restores access without broad security/settings rollback and without touching
user credentials. The lockout-release port keeps the command unit-testable while the WordPress store owns persistence.
Status: Final for T140–T141 on `fix/067-admin-shell-and-completion`; T139 DB-backed integration remains pending.

## #127 -- Spec 068 Access denied: request access submits to the real Access workflow
Date: 2026-07-07
Context: Spec 067 originally shipped the denied surface with an honestly disabled "Request access" control because no
request workflow existed. Spec 068 now has the Access request service, REST controller, persistence table, notification
hooks, and review/decision flow, so keeping the disabled control would make the visible UI stale and misleading.
Decision: Replace the disabled denied-state control with a real POST form to `corex/v1/access/requests`. The form
includes the REST nonce as `_wpnonce`, maps each denied CoreX section/page slug to its corresponding CoreX-owned
ability, and requires a bounded reason. `AccessController` accepts `_wpnonce` as a progressive-enhancement fallback to
the JavaScript `X-WP-Nonce` header. The menu-level `AccessDeniedGate` reuses `AdminPage::deniedSurface()` so the true
WordPress 403 path and the in-page defense-in-depth path cannot drift.
Why: denied users get one real workflow instead of a dead button; administrators review the same request records in
Access & Abilities; and the form remains functional without requiring JavaScript to set request headers.
Status: Final for T149 on `fix/067-admin-shell-and-completion`; DB-backed integration gates from T135/T139/T143 remain
pending while local WAMP MySQL refuses WordPress bootstrap.

## #128 -- Spec 068 Security Center: client workspace layers over the server-side mode form
Date: 2026-07-07
Context: Operations & Security already had a nonce-gated server form for mode changes and shared readiness evidence for
Production launch. Spec 068 adds a richer Security Center workspace for launch checklist, typed confirmation, login
policy, lockouts, recovery, and activity. Removing the server form during the UI migration would weaken the no-JS and
fallback path for a sensitive operation.
Decision: Mount `SecurityCenter` from the shared admin bundle on `#corex-security-app`, localize real readiness,
login-protection settings, mode activity, nonce, REST root, and recovery command from `OperationsSecurityScreen`, and
preserve the existing server-side mode form as the authoritative mutation fallback. The client owns preview/state UI
for the launch checklist, typed Production modal, Maintenance confirmation, login policy, lockouts, recovery guidance,
activity, and recoverable notices; server endpoints remain responsible for sensitive mutations.
Why: this adds the designed Security Center workspace without replacing a working nonce/capability-gated operation with
a half-migrated client-only control, and it keeps the source of truth in the same PHP services already covered by unit
tests.
Status: Final for T150–T151 on `fix/067-admin-shell-and-completion`; T152 styling and T153 browser coverage remain.

## #129 -- Spec 068 Blog analytics: managed hash-only reading events
Date: 2026-07-08
Context: Blog Pro needs real first-party analytics for views, reads, share clicks, uniqueness, ranges, and reporting,
but the owner directive forbids fake counters while the constitution and Spec 068 privacy decision forbid covert raw
visitor tracking.
Decision: Store Blog analytics as consent-gated `ReadingEvent` records in a CoreX managed `blog_reading_events` table.
The analytics service hashes a salted visitor key/IP/user-agent tuple before persistence, drops events without consent
or a visitor key, and aggregates from the `ReadingEventStore` port. The WordPress repository stores only post ID,
event type, visitor hash, occurred timestamp, optional reading seconds, optional share target, and retention timestamp;
raw visitor key, raw IP address, and raw user agent are not table columns. The repository is bound as the default
`ReadingEventStore`, `BlogAnalyticsService` is container-resolved, and the foundation schema version is bumped so
existing installs can create the new table.
Why: CoreX can now report real native Blog engagement without inventing sample metrics or collecting unnecessary raw
identifiers. The store remains queryable through bounded indexes and visible in the managed Data registry while future
REST/client/chart work consumes the same injected service.
Status: Final for T156–T158 on `fix/067-admin-shell-and-completion`; editorial, comment, sharing, REST/client, theme,
and browser coverage remain in Phase 8.

## #130 -- Spec 068 Blog editorial workflow: CoreX state maps to native post status
Date: 2026-07-08
Context: Blog Pro must stop being a reference-only editorial surface, but FR-096 requires it to operate on native
WordPress posts, statuses, schedules, users, and metadata instead of replacing the publishing model.
Decision: Represent editorial workflow as CoreX metadata layered over native posts. `EditorialWorkflowService` maps
Draft and Needs Changes to native `draft`, Ready for Review and Approved to native `pending`, Scheduled to native
`future` with a required schedule timestamp, and Published to native `publish`. Assignee, due date, scheduled
timestamp, and actor-authored review notes persist through an `EditorialWorkflowStore` port; the WordPress adapter
stores those values in post meta and delegates status/schedule changes to `wp_update_post()`.
Why: CoreX gets the approved editorial states and review metadata while WordPress remains the source of truth for
publication status, scheduling, and post interoperability. The schedule guard prevents a fake Scheduled state that is
not backed by native scheduling.
Status: Final for T159–T160 on `fix/067-admin-shell-and-completion`; comment moderation, author analytics, sharing,
REST/client, theme, docs, and browser coverage remain in Phase 8.

## #131 -- Spec 068 Blog moderation and authors stay native
Date: 2026-07-08
Context: Blog Pro must moderate comments and report authors without replacing WordPress comments, users, roles, or post
counts.
Decision: Implement `CommentModerationService` directly over native comment APIs for queue classification and approve,
reply, edit, spam, and trash actions. Implement `AuthorAnalyticsService` as a projection over native author users,
native published-post counts, and first-party Blog analytics aggregates. The services are container-bound for later
REST/client consumption and do not introduce a separate Blog author/comment store.
Why: the product gains real moderation and author metrics while preserving WordPress interoperability and avoiding
duplicate comment/user state. The integration test proves the mutation path changes native comment records rather than
an invented CoreX-only queue.
Status: Final for T161–T162 on `fix/067-admin-shell-and-completion`; social sharing, REST/client, theme, docs, and
browser coverage remain in Phase 8.

## #132 -- Spec 068 Blog social sharing logs first-party clicks only when enabled
Date: 2026-07-08
Context: Blog Pro needs real share controls and click logging without inventing engagement metrics or adding an
external provider dependency.
Decision: Add `SocialSharingService` with option-backed `SocialSharingSettings`. The service builds configured
X/Facebook/LinkedIn/copy-link controls from native post permalinks/titles and records share clicks by delegating to the
consent-aware first-party `BlogAnalyticsService` only when logging is enabled. The copy-link target is a real control
with the native permalink; unknown targets are ignored for rendering and sanitized before analytics logging.
Why: sharing remains truthful and provider-neutral, click metrics reuse the same hashed reading-event store as Blog
analytics, and disabled logging produces no fake or inferred click event.
Status: Final for T163 on `fix/067-admin-shell-and-completion`; REST/client, theme, docs, and browser coverage remain
in Phase 8.

## #133 -- Spec 068 Blog REST: thin controller over native Blog services
Date: 2026-07-08
Context: Blog Pro needs client-accessible workflows for analytics, editorial state, native comments, authors, and
sharing without moving domain logic into request handlers.
Decision: Add `BlogProController` under `corex/v1` with nonce/capability-gated routes for analytics, share controls,
share-click logging, editorial transitions, comment queue/moderation, and authors. The controller sanitizes request
parameters, wraps response data consistently, and delegates to `BlogAnalyticsService`, `EditorialWorkflowService`,
`CommentModerationService`, `AuthorAnalyticsService`, and `SocialSharingService` through a `BlogProServices`
aggregate.
Why: the upcoming Blog Pro client can use one REST boundary while all persistence and WordPress-native mutation logic
stays in services/stores. This preserves the thin-controller constitution rule and keeps route tests focused on the
contract rather than service internals.
Status: Final for T164–T165 on `fix/067-admin-shell-and-completion`; client, theme, docs, and browser coverage remain
in Phase 8.

## #134 -- Spec 068 Blog Pro client: replace reference renderer with real localized state
Date: 2026-07-08
Context: The previous Blog Pro screen and model still carried future/reference/sample analytics copy. Spec 068 forbids
that state for approved current surfaces once the real services exist.
Decision: Replace the server-rendered reference Blog Pro surface with a real client mount in the shared admin bundle.
`BlogProScreen` localizes real native posts, analytics aggregates, editorial metadata, moderation queue, author
analytics, share controls, REST root, and nonce into `corexBlogPro`. `BlogProModel` is reduced to functional tab
metadata only, and `blogProState.js` owns endpoint construction, analytics normalization, payload shaping, and reducer
state for the client.
Why: the visible Blog Pro surface now consumes the same real services as REST/tests and no longer carries fake/sample
metrics or future-only messaging. Keeping state helpers pure gives chart/workflow behavior a fast Jest contract before
the richer UI polish lands.
Status: Final for T166–T167 on `fix/067-admin-shell-and-completion`; token-only styling, theme templates, docs, and
browser coverage remain in Phase 8.

## #135 -- Spec 068 approved component inventory is a reconciled contract, not prose
Date: 2026-07-09
Context: Phase 11 (FR-154) requires the approved "Blocks & Components" inventory to be real, not a design reference.
The design file marks each of 77 items with a status (approved/revision/missing/future) and a build order that defers
WooCommerce blocks and Pro items; the owner mandate makes approved current surfaces required functionality.
Decision: Encode the full inventory as `Corex\Ui\ApprovedComponentInventory` — every item carries its design status
and a concrete delivery `resolution` (`corex-block:<slug>`, `block-style:<name>`, `pattern:<name>`, `admin:<selector>`,
`runtime:<class>`, `core-block`, or `deferred:<reason>`). A reconciliation test asserts the per-category counts, that
no claimed `corex/*` block is phantom, that deferrals use only allowed reasons (`woocommerce-dependency`/`future-pro`/
`phase-2`), and that every non-deferred item resolves to an artifact that actually exists on disk. WooCommerce stays
dependency-gated; composable atoms (icon box, trust badges, button groups, pricing comparison) resolve to core blocks
or block styles per the design's own "prefer core blocks/patterns" rule.
Why: the reconciliation is the truthful gate — it converts "the design is required" into a machine-checked list and
scopes implementation to the items that are genuinely current-yet-absent, so no component can be claimed that the
framework does not ship.
Status: Final. The test scoped T208 to the slider primitive + toast + tooltip; rich-tabs finalize and other
content-block polish remain tracked for later Phase 11 passes.

## #136 -- Spec 068 slider is one scroll-snap primitive; toast/tooltip are token-only admin DLS primitives
Date: 2026-07-09
Context: The design asks for a single slider engine (1-up…6-up, no autoplay by default, reduced-motion/RTL/no-JS
fallback) plus admin Toast and core-UI Tooltip atoms. None existed. Shipping CSS nothing uses would be dead UI.
Decision: Ship `corex/carousel` as the one slider block — a CSS scroll-snap row that is swipeable and
keyboard-scrollable with no JavaScript, progressively enhanced with prev/next/dot buttons and opt-in autoplay
(paused on hover/focus/tab-blur, disabled under reduced motion), driven by a `perView` attribute (1–6) so one engine
yields every layout. Direction is handled by `scrollIntoView({inline:'start'})`, so RTL is correct for free. Deliver
`.corex-toast` and `.corex-tooltip` as token-only primitives in the corex-core admin shell and wire each to a real
consumer: the toast to a Settings-save confirmation (previously silent) and the tooltip to the settings secret field's
write-only note — so neither is dead UI, following the existing `.corex-skeleton` primitive precedent.
Why: satisfies FR-154/162/163 with real, tested, non-fake surfaces; the slider stays a single maintained engine and
the admin primitives gain genuine consumers instead of speculative CSS.
Status: Final for T208–T209 on `fix/067-admin-shell-and-completion`.

## #137 -- Spec 068 wp_die interstitials get a self-contained branded shell; the login-hiding 404 stays generic
Date: 2026-07-09
Context: The Maintenance-mode 503 and the menu-level access-denied / request-access 403 both short-circuit the normal
page lifecycle through `wp_die`, so no enqueued CoreX stylesheet is present — the designed `corex-denied` markup and
the maintenance notice rendered as bare, unstyled WordPress error pages. This was owner-reported ("maintenance mode
and request access … are not styled yet").
Decision: Add `Corex\Admin\StandalonePage`, which builds a complete `<!DOCTYPE html>` document and inlines the
`--corex-admin-*` token adapter (font URLs absolutized) plus a new self-contained `corex-admin-standalone.css` that
consumes only tokens. `MaintenanceGuard::maybeBlock()` and `AccessDeniedGate::intercept()` now emit that document with
the correct 503/403 headers instead of `wp_die`. The document body carries `corex-standalone corex-admin-screen` so
the inlined adapter resolves in dark/light/RTL exactly like the admin shell. A shared `StandalonePage::notice()`
(with a shared `StandalonePage::brandMark()`) then brands every other admin-post front-controller interstitial —
DataExportController (403 not-allowed / 403 expired / 404 unknown source), DataModelsImportController,
OperationsModeController, and RetentionController nonce/cap/expired-link failures — each with the correct status,
a Back-to-screen action, and no bare notice. The custom-login `LoginRouteGuard` 404 is deliberately left as a generic
"Not found": branding it would reveal that CoreX is hiding the login endpoint, which defeats the feature.
Why: turns the two primary front-facing framework interstitials into real designed surfaces (owner mandate: approved
design is required functionality) without introducing raw design values — the token adapter stays the single source of
truth, inlined for pages that cannot enqueue it.
Status: Final on `fix/067-admin-shell-and-completion`; live dark/light/RTL render verification on corex.local is the
recommended manual follow-up.

## #138 -- Spec 068 Phase 12 final audit: activate the Company kit, remediate accumulated lint, classify residual E2E as environment drift
Date: 2026-07-10
Context: The Phase 12 final audit was the first run of the full integration suite, JS/CSS lint, and Playwright since
earlier phases (prior sessions were blocked by the Codex app approval-credit gate and a stopped WAMP MySQL). Running
them surfaced latent, never-executed issues rather than new regressions.
Decision: (1) Activate the `corex-kit-company` add-on in `./wp` so `SetupWizardControllerTest` and the setup-wizard
E2E exercise the real Company kit — this matches how the other add-on integration suites already rely on their add-on
being active, and reflects the intended flagship state (the wizard must offer a kit, not an empty chooser).
(2) Remediate the 22 accumulated stylelint findings in place: convert `@use` string-quotes to double quotes, split an
over-long comment, merge the duplicate `.corex-data-models__card-head` selector, and apply scoped
`stylelint-disable-next-line no-descending-specificity` only where the flagged selectors are genuinely unrelated
(zero cascade effect); keep each `.css`/`.scss` pair byte-identical. (3) Regenerate the token inventory after
`theme/README.md` drift. (4) Classify the 4 residual Playwright failures as pre-existing environment / demo-content /
actor-state issues — not code regressions — with each underlying requirement independently proven by the
unit/integration suites, and record a scoped follow-up (reset dev fixtures, seed a `/contact` demo form page, reseed
forms-flow/access specs with a non-admin actor) instead of masking behavior or force-passing the specs.
Why: keeps the completion truthful (constitution: code that contradicts the truth is wrong; no fake green) while
fixing the genuine defects the audit exposed. The PR is deliberately NOT marked ready until the four E2E items are
resolved (T234).
Status: Final on `fix/067-admin-shell-and-completion`. Evidence: `specs/068-admin-product-functional-completion/evidence.md`
§Final Verification (Phase 12).

## #139 -- Spec 068 Phase 12: fix the dead front-end form block (formSlug forms never rendered)
Date: 2026-07-10
Context: The Phase 12 contact-form E2E exposed that `/contact` rendered no form at all. Root cause: the
`corex/form` block's `block.json` declares attribute defaults `flowId: 0` and `flowSlug: ""`. WordPress merges
those defaults into `$attributes` before rendering, so `FormBlockRenderer`'s branch condition
`isset($attributes['flowId']) || isset($attributes['flowSlug'])` was ALWAYS true — every block, including a legacy
`formSlug` form (the Contact form the `corex/contact` pattern uses), was routed to the flow renderer, which found
no flow and returned an empty string. The submit path was fine (SubmitLifecycleTest passed), but the form was
never visible — dead UI on a core front-end surface.
Decision: Route to the flow renderer only when a flow is actually referenced —
`((int) flowId) > 0 || trim(flowSlug) !== ''` — otherwise fall through to the registered `formSlug` form. Added
`tests/Integration/Forms/FormBlockRenderingTest.php` (3) that renders the real registered block via `do_blocks`
(so block.json defaults are merged, unlike the pure unit test which passes a null flow renderer) and asserts the
form and the `corex/contact` pattern render, and that an unknown flow stays non-fatal.
Why: the owner mandate is that approved design is required functionality and no required control may remain dead.
A front-end contact form that renders nothing is exactly that. The fix is minimal, preserves flow-block behavior
(a referenced flow still routes to the flow renderer), and is guarded by a test that exercises the real
default-merge path the unit test missed.
Status: Final on `fix/067-admin-shell-and-completion`. Verified live: `http://corex.local/contact/` now serves
`<form data-corex-schema>`; the contact-form smoke E2E passes; full unit 1,257 and integration 107 stay green.

## #140 -- Spec 069: login hiding is defeated and fails open (reproduced on corex.local, pre-implementation)
Date: 2026-07-16
Context: The owner reported for the second time that the login policy "doesn't behave like wp hide login".
Before writing any code, the four claims below were reproduced against the real runtime at `http://corex.local`
with login protection in its as-found state (`enabled: true`, `custom_slug: corex-login`,
`block_default_endpoints: true`). Note this contradicted `PROGRESS.md`, which recorded protection as *disabled*
on this box; PROGRESS was stale and is corrected in the same change.

Findings, all measured, not inferred:

1. **The custom login URL is leaked in plain text to anonymous visitors.** `GET /login` returns
   `302 -> http://corex.local/corex-login/`. WordPress core's `wp_redirect_admin_locations`
   (`template_redirect`, prio 1000) is never removed, and `LoginRouteGuard`'s `site_url`/`login_url` filters
   helpfully rewrite core's redirect target to the secret slug. One unauthenticated request defeats the entire
   feature. `/dashboard` and `/admin` likewise 302 to `/wp-admin/`. This is the headline defect: hiding that
   hands out the hiding place.

2. **The 404 is trivially fingerprintable.** A genuinely absent URL returns the theme 404: **79,968 bytes**,
   `<title>Page not found - Corex</title>`. The "hidden" `/wp-login.php` returns `LoginRouteGuard::deny()`'s
   `wp_die` page: **2,515 bytes**, `<title>Not found</title>`. Same status, ~32x size difference -- a scanner
   separates "hides its login" from "no such page" on response size alone. Corroborating evidence: when the guard
   is inactive, `/corex-login/` 404s at exactly 79,968 bytes -- byte-identical to the control. The correct
   response already exists; the guard simply does not use it.

3. **Total lockout is reachable from stored state (Trap 1, as predicted).** `LoginProtectionSettingsStore::save()`
   L30 has a `?: 'corex-login'` fallback; `current()` L52 does not. Stored slug `!!!` -> `sanitize_title()` -> `''`
   -> the empty string slips past `LoginProtectionSettings`'s `!== ''` guard (no throw) -> `register()` bails at
   L27 so no slug is served, while the *unconditional* `login_init` registration at L25 still 404s `wp-login.php`
   via `decision()`. Measured: `/wp-login.php` 404, `/corex-login/` 404. No login URL exists at all; the front
   page still serves 200, so the site looks healthy while nobody can log in.

4. **Silent fail-open + whole-plugin outage (Trap 2 -- WORSE than the predicted fatal).** A 1-2 char slug
   (e.g. `ab`) passes `sanitize_title` but fails the value object's `/^[a-z0-9][a-z0-9-]{2,80}$/` and throws.
   The container builds it on every `make()` (ConfigServiceProvider L304-308) and `boot()` L651 resolves it
   unguarded -- but provider boot is caught at `ProviderRepository.php:91`, which logs and continues. So it does
   NOT fatal. Instead the **entire ConfigServiceProvider fails to boot**: measured `corex/v1/insights/widgets`
   and `corex/v1/data/sources` -> 404, and `/wp-login.php` -> **200 while the stored setting still reads
   `enabled: true`**. The owner is told they are protected and is not. No visible symptom: front page 200; the
   only trace is one `debug.log` line, and `wp-config.php` redefines `WP_DEBUG` to `false`.

5. **Recovery reports a dead URL.** `wp corex security reset-login` correctly restores `wp-login.php` (verified
   200), but prints `Restored login URL: http://corex.local/corex-login/` -- which 404s immediately after, because
   `wp_login_url()` is called in a request where the guard's filters are still registered. The one documented
   recovery path tells a locked-out owner to visit a page that does not exist.

Decision: Treat (1) and (4) as security defects, not cosmetic gaps. (1) means the feature currently provides
*no* obscurity while implying that it does. (4) means a validation error silently downgrades a security control
the owner explicitly enabled -- and takes an entire plugin's screens and routes with it. Spec 069 gains FR-011a
("protection MUST NOT fail open") and SC-004 is widened to cover unrelated-capability loss and silent downgrade.
The Trap 2 description in `plan.md` was written predicting a fatal and has been corrected to the measured
behaviour; the prediction was wrong in the safe direction and the reality is more dangerous.

Why: the constitution puts truth above convenience -- a control that reports itself active while serving the
unprotected default is exactly the "dead/fake UI" the owner mandate forbids, in its most consequential form.
Reproducing before fixing also gives the rewrite a real before/after: the control 404 (79,968 bytes) is the
literal target for the theme-404 response FR-001 requires.

Status: Recorded pre-implementation on `fix/069-admin-correctness-and-login-parity`. corex.local was restored to
its exact as-found state after each probe (verified: `/wp-login.php` 404/2515, `/corex-login/` 200,
insights REST 401 -- i.e. provider booting again). Recovery was proven working BEFORE any login code was touched,
per tasks T002.

## #141 -- Spec 069: one selection control, because the reported bug had no CSS fix
Date: 2026-07-20
Context: The owner reported for the third time that "the select boxes still didn't get fixed", specifically the
dark-mode hover. Two previous passes had tried to fix it in CSS. It cannot be fixed in CSS: the OPEN menu of a
native `<select>` is drawn by the operating system, not the page, so no `option:hover` or `option:checked` rule
reaches it. `tasks.md` T047 anticipated exactly this and pre-authorised replacing the control.

Decision: every selection control in the CoreX admin is now `CorexSelect` -- the in-DOM ARIA listbox that
`ApprovedComponentInventory.php:142` has declared as the approved Select all along, and that
`corex-admin-shell.css` has styled all along, but that nothing in React ever rendered. ~31 sites across 20 files.
The last native `<select>` in the admin is gone.

Three boundaries were drawn deliberately and are NOT oversights:

1. **Block-editor `SelectControl` stays** (`addons/corex-ui/src/Blocks/*`, `corex-forms` block editors). Those
   render in the Gutenberg sidebar, where Gutenberg's own design language is correct and the CoreX admin shell
   is not loaded. Migrating them would make the editor inconsistent with itself.
2. **The front-end form select stays native** (`corex-forms` FieldRenderer). CorexSelect lives in the admin
   bundle and depends on admin tokens; shipping it to a public page would load an admin bundle on the front end
   (Principle VI) and make a visitor's most important control JavaScript-dependent. It is themed from
   theme.json presets instead, with `color-scheme: light dark` -- the standards-based lever for the one thing
   CSS cannot reach.
3. **The forms Preview tab stays native.** It previews what a VISITOR sees, and the front end renders native;
   previewing the admin component there would preview something the site does not serve.

What the design capture corrected. Before touching anything, `.corex-select` was checked against the "Select &
dropdown menu" block of `Corex Component Library.dc.html`. The shipped rules were off-design in five ways, which
is why even the enhanced control looked wrong. The substantive one: the design carries the hover signal
primarily in the TEXT colour (muted -> full contrast), with the background only assisting. The previous
implementation started options at full contrast, so the background had to carry the signal alone -- and
`surface-alt` is barely a step from the menu's `surface-raised`. A comment in the old CSS had noticed that
symptom and worked around it by using the accent tint, which is the SELECTED colour, so hover and selected then
looked alike. Measured in a real browser after the fix, dark and light: menu/idle/hover/selected all match the
capture.

Two defects this surfaced that would otherwise have shipped silently:

- **FormData.** Email Studio reads its forms with `new FormData( form )`. A `<button>` contributes nothing to
  FormData, so a naive swap would have dropped `template_id`, `recipient_source` and `reply_to_source` from
  every route save -- and the form would have looked like it worked. CorexSelect takes a `name` and renders a
  hidden input, plus an uncontrolled mode for the screens that used `defaultValue`.
- **Load-bearing "duplicate" CSS.** The per-screen `select { inline-size: 100% }` rules removed as duplicates
  were holding the Submissions filter row inside its grid. Without them the Form control sat on top of Owner.
  Caught by rendering the screen; no test would have. Fields now share one `.corex-field` wrapper in the shell
  rather than the four per-screen `<label>` rules that had grown up -- a `<label>` cannot label a button plus a
  listbox anyway, so those wrappers had to stop being labels regardless.

Why: the owner mandate treats approved design as required functionality, and a control that cannot be made to
match the design is not a styling gap -- it is the wrong control. Three failed CSS attempts is enough evidence.

Status: Implemented on `fix/069-admin-correctness-and-login-parity`. Unit 1,291, integration 158, Jest 294,
Playwright 45/45, stylelint clean, guards clean.

## #142 -- Spec 069: two long-standing test failures were calendar rot, not regressions
Date: 2026-07-20
Context: `ProductActivityCoverageTest` and `BlogProControllerTest` were failing at the start of this session.
Verified pre-existing by stashing all working-tree changes and re-running -- they failed identically on a clean
tree, so they were not caused by this branch.

Both were time-dependent in the same way, and both had passed when written:

- `BlogProControllerTest` seeded a page view at a hardcoded `2026-07-08` and then queried "the last 7 days".
  Once the calendar passed 2026-07-15 the seeded view fell out of the window and the test reported zero views
  for a reason that had nothing to do with the code. Fixed by anchoring the fixture to `-1 day`.
- `ProductActivityCoverageTest` seeded events at a fixed `2026-07-10` and asserted they appeared in page 1 of
  100 for their area. Results come back newest-first, so on an install that has accumulated a few hundred real
  activity events the fixtures fall past the first page. Fixed by scoping the query to the window the events
  were seeded into -- which the sibling time-window test in the same file already did, and which is why that
  one kept passing.

Why record it: both look like flakes and neither is. A test whose result depends on the wall clock or on how
much unrelated data the database happens to hold will keep coming back, and the next person to hit it will
reasonably assume their own change caused it. The same class of defect was introduced and caught within this
session -- three new Playwright tests silently depended on login protection being enabled ambiently; they now
enable it themselves and restore the exact prior value.

Status: Both fixed. Integration suite is fully green (158/158) for the first time this session.

## #143 -- Spec 070: a route's identity comes from its path, never its payload

Context: saving an Email Studio template draft answered 404 for a template that plainly existed, and the UI
showed only "Something went wrong. Please try again."

Two independent defects, stacked, which is why neither was obvious.

`WP_REST_Request::get_parameter_order()` resolves JSON body params and the query string *before* URL params.
`saveDraft()` read `get_param('id')`, and the editor was posting the stored version's own `id` in the body --
it built the draft as `{ ...EMPTY_DRAFT, ...latest }`, spreading the whole version record (`id`, `template_id`,
`version`, `checksum`, `created_by`, `created_at`) into the payload. So a save aimed at template 3859 looked up
version id 3860 as a template, found nothing, and 404'd. Only templates that already had a saved version could
trigger it -- one with none uses `emptyDraft()`, which carries no `id`. That is why it looked intermittent.

The message was lost separately. `corex-runtime.js` called `wp.apiFetch({ parse: false })` and assumed a
non-2xx *resolves*; core's `parseAndThrowError()` rethrows the raw `Response`, so the `response.ok === false`
branch was dead code and every 4xx/5xx fell into the blanket `.catch` and became a generic error. The server's
"That email template was not found." never left the wire.

Decision: fix both ends, and treat the param shadowing as a class rather than an instance. New
`Corex\Http\RouteParam` reads `get_url_params()` directly, which nothing can shadow, applied to every
route-captured identifier in `EmailStudioController`, `FlowController`, `SubmissionsController` and
`DataManagementController` (24 `::int`, 1 `::string`). `source` in `DataManagementController` deliberately
stays on `get_param()` -- `migrations()` reads it as a query filter on a route that captures no `source`, so
converting it would have broken a working endpoint.

Why record it: `get_param()` reads as the obvious accessor and is wrong for anything the route captured. Four
controllers had the same latent trap and `get_url_params()` appeared nowhere in the codebase. The integration
tests missed it because they set route ids with `set_param()`, which WordPress files under the *body* -- so the
tests were modelling the exact mistake the production code made. They now use `set_url_params()`, which is what
`WP_REST_Server::dispatch()` actually does.

Status: Fixed and verified live -- the save returns 201 with a new version. 164/164 integration green.

## #144 -- Spec 070: the hidden-admin 404 was unstyled, and 069 said that was impossible

Context: the owner reported that a hidden `/wp-admin` "redirects to unstyled page". Spec 069 had recorded the
same response as a documented, unfixable limitation.

069's analysis named `wp_should_load_separate_core_block_assets()`, which does return on `is_admin()` before its
own filter -- accurate, and it is why the two responses are still ~250 bytes apart. But it is not what emptied
the page. That was `wp_common_block_scripts_and_styles()`, which opens
`if ( is_admin() && ! wp_should_load_block_editor_scripts_and_styles() ) return;`. On a hidden admin 404 both
hold, so the response got no per-block sheets, no monolithic `wp-block-library`, no `wp-block-library-theme`,
and `enqueue_block_assets` never fired. `wp_enqueue_global_styles` has no such gate, so `theme.json` tokens
still printed -- colours and custom properties, no block layout or appearance CSS. On a block theme with no
`functions.php` and a metadata-only `style.css`, that renders as broken.

It was always reachable: that gate is hooked to `wp_enqueue_scripts`, which fires during `wp_head()`, long
after `render404()` runs on `wp_loaded`. `LoginRouteGuard::enqueueBlockStyles()` now fills it. Enqueuing
directly rather than filtering `should_load_block_editor_scripts_and_styles` is deliberate -- that filter would
also satisfy the block-*editor* branch and pull in editor assets a real front-end 404 never carries, widening
the fingerprint in the other direction.

Fixing it surfaced a second, older bug. `.corex-header__inner { max-inline-size: 100% }` has the same
specificity as core's `.is-layout-constrained > :where(...)`, so which one won depended purely on stylesheet
order. Inline block styles print later on a normal front-end request, so core won and the theme rule was
already inert; when the sheet loads as a `<link>` instead -- which is what core does whenever on-demand block
assets are off -- the order inverted and the header stretched edge to edge. The guard now applies to
`.corex-header` only.

Why record it: the 069 measurement (46,587 B vs 79,968 B) was read purely as a fingerprinting risk. A 42% gap
in a rendered page is not a fingerprint, it is missing CSS, and nobody made that connection for a release. When
a limitation is written down as impossible, it stops being re-examined -- so 069's spec was corrected in place
rather than left standing with a newer document quietly disagreeing.

Status: Fixed. 46,587 B -> 79,711 B against a 79,964 B control; computed font, layout and header geometry now
match the genuine 404 exactly. e2e now asserts `wp-block-library` is present and the size gap stays under 5%.

## #145 -- Spec 070 shipped without plan.md or tasks.md, and the backfill says so

Date: 2026-07-20

Decision: Backfill `plan.md`, `tasks.md`, and `checklists/requirements.md` into
`specs/070-transport-error-fidelity-and-admin-style-parity/` before spec 071 starts, and record the
deviation here rather than closing the gap silently.

Why: 070 shipped with `spec.md` alone. The constitution's Pre-Implementation Confirmation Rule -- added at
v1.2.0 specifically to stop code preceding a reviewed plan -- requires the full artifact set, and 070 is the
exact failure mode that rule exists to prevent. The work itself is sound and verified (164/164 integration,
298/298 Jest, 8/8 e2e, guards clean); the process record was missing, and a missing process record is what
lets the next spec skip the step too.

The backfilled documents are labelled retroactive at the top of each file. They reconstruct what was actually
done -- the file set is reconciled against commit `f9c5656`, not asserted -- and they claim no foresight the
session did not have. `tasks.md` marks every task complete because every task is verifiably complete; T028
(the backfill itself) is the one open item.

The checklist deliberately **fails four items**. `070/spec.md` is a root-cause narrative written for
engineers: it names `get_parameter_order()`, `parseAndThrowError()`, `wp_common_block_scripts_and_styles()`,
file paths and line-level mechanics, and states its outcome in response bytes. That violates the
"no implementation details / written for non-technical stakeholders" criteria, and marking those items passing
would have been the easy lie. They are marked failing, with the reason recorded: both defects were owner-
reported symptoms whose causes were not what the symptoms suggested, and 069 had already written one of them
off as unreachable -- so the mechanics were the only durable value the document had. The correct split is
069's (stakeholder statement in `spec.md`, mechanics in `plan.md`); the backfilled `plan.md` now carries the
mechanics, but `spec.md` was **not** retro-edited to make the checklist pass, because rewriting a shipped spec
to flatter a later checklist misrepresents what was actually reviewed at implementation time.

Alternatives considered: log an exception and proceed to 071 with no backfill (rejected: leaves the debt in
place and normalises the bypass); rewrite `070/spec.md` into stakeholder language so the checklist passes
cleanly (rejected: dishonest about what was reviewed, and destroys the root-cause record); treat 070 as
out of scope for this session (rejected: 070 is unmerged, local-only, and 071 branches directly off it, so
its record is this session's responsibility).

**The backfill earned its keep immediately.** Running `docs-guard` over the new artifacts caught a false claim
in `070/spec.md` that had shipped with the code and that the backfill had faithfully copied forward:
"`failureMessage()` surfaces `details.fields`". There is no `failureMessage()` in `corex-runtime.js`, and the
string `fields` does not appear in that file at all. The bullet named a symbol that never existed and
described a mechanism that never ran. What actually happens is passthrough — `normalise()` returns a valid
envelope verbatim via `isEnvelope()`, so the controller's field errors were never being transformed; they were
discarded wholesale because the rejection path fell into the blanket catch and got `genericError()`. Routing
it through `fromResponse()` is the whole fix, and the Jest case "keeps field details from a rejected
validation response" tests exactly that.

Corrected in `spec.md` in place, plus `plan.md` and `tasks.md` T018. Note the failure mode: the backfill
propagated the error by trusting `spec.md` rather than reading `corex-runtime.js` — the same shortcut that put
it in `spec.md` in the first place. A spec is not a source of truth about its own implementation; the code is.
Everything else verified clean (13 file paths, the 24 `::int` + 1 `::string` counts, the 5 `RouteParamTest`
cases including the literal 3859/3860 shadowing, the byte figures, and the in-place 069 correction).

Status: Final.

## #146 -- Spec 071: reCAPTCHA v3 gets a typed verdict, not a wider boolean

Date: 2026-07-20

Decision: Add a `Corex\Security\VerifyingChallenge` interface (`challenge(token, ChallengeContext):
ChallengeVerification`) that extends the existing boolean `ChallengeVerifier`, rather than widening
`verify()` or bolting score-handling onto `RemoteCaptcha`. reCAPTCHA v3 gets a dedicated
`RecaptchaV3Captcha` driver; turnstile/hcaptcha keep `RemoteCaptcha` unchanged.

Why: a `bool` has nowhere to carry a score, an action, or a reason -- which is precisely why
`captcha.score_threshold` and `captcha.action` were stored settings that nothing read, and why
selecting reCAPTCHA rejected every real submission (the token field was never rendered, so the
always-empty token failed closed). The typed verdict is the place the score lives. Extending rather
than changing the contract keeps honeypot/null/remote drivers working untouched -- the same
Mailer -> AttemptingMailer precedent already in the codebase. `ProtectionStage` picks the typed path
via `instanceof VerifyingChallenge` and falls back to `verify()` otherwise.

The verification runs nine checks fail-closed in a fixed order: token present -> transport ok ->
parseable -> success -> hostname (exact allowlist, never substring) -> action (server-derived) ->
expiry -> score -> replay. **Replay is deliberately last**: only a token that has passed every other
check is ever recorded, so a forged token cannot poison the replay store and a token rejected only on
score stays replayable if the threshold is later lowered. The plan's R3 table originally listed
replay before score; the plan's own stated rationale ("recorded only on an otherwise-passing token")
required last, and the code and tests follow the rationale.

Alternatives considered: widen `ChallengeVerifier::verify()` to return a result object (rejected:
breaks every existing driver and caller); read `score` inside `RemoteCaptcha` (rejected: conflates
three providers with different response shapes into one branch-heavy method); a v3-only boolean with
score checked in `ProtectionStage` (rejected: puts provider-response parsing in the pipeline stage,
and still cannot express action/hostname/expiry outcomes to the admin).

Status: Final.

## #147 -- Spec 071: CaptchaAction lives in corex-forms, not the captcha add-on

Date: 2026-07-20

Decision: Place `CaptchaAction` (the reCAPTCHA action derivation) in `Corex\Forms\Submission`, not
in the captcha add-on where plan.md and the extension-api contract originally put it.

Why: the action is a Forms concept -- it is derived from a flow slug, and both the block renderer and
the protection stage (both in corex-forms) call it. The verifier only *compares* the action it is
handed; it never derives one. Had it stayed in the add-on, corex-forms would hard-depend on an
optional add-on, breaking a site that runs forms without captcha -- a Principle IX violation. Moving
it keeps the dependency arrow pointing the right way: the add-on may depend on forms, never the
reverse. The plan and extension-api contract were corrected in place with a note.

Alternatives considered: keep it in the add-on and guard every call with `class_exists` (rejected:
scatters optional-dependency checks through the renderer); duplicate the derivation on both sides
(rejected: the browser and server would then be able to disagree on the action, which is exactly the
FR-004 failure the single shared function prevents).

Status: Final.

## #148 -- Spec 071: per-form protection stays out of the version checksum when empty

Date: 2026-07-20

Decision: `FlowConfiguration` gains a seventh `protection` array, defaulted to `[]`. `checksum()`
omits the key entirely from its canonical document when the array is empty; only a form that actually
declares protection changes its hash. Normalisation lives in `Corex\Forms\Flow\FlowProtection`, called
at both serialisation boundaries (stored-payload read and REST input), not in the validator.

Why: the checksum is compared on publish to detect version drift, and every published version on every
live site already stored a hash computed over six arrays. A new key in the canonical document would
re-hash all of them at once, reading as universal drift. Omitting an empty protection block makes the
field invisible to untouched forms -- backward compatibility by construction (FR-025), covered by a
test that a defaulted config and an explicit-empty config hash identically.

The transient-backed replay guard (`TokenReplayGuard`) deserves the same note: it keys on an
HMAC-SHA256 fingerprint of the token (never the plaintext) with a TTL just longer than the token's
own validity window. The TTL is the bound and the cleanup -- an expired entry is harmless because an
expired token fails the age check first -- so no new table and no pruning job were added, satisfying
the "bounded and prunable" requirement without new persistence.

Conditional front-end loading uses a render-time `ProtectedFormRegistry` the block renderer populates,
read by a `wp_footer`-hooked asset controller. This is a deliberate, documented deviation from
Principle VI (block.json declares assets): whether a form needs the provider script is a runtime
property of the resolved flow's stored config, not a static property of the block, so a block.json
declaration would load the script on every page containing any form -- the opposite of FR-001. The
registry approach is strictly more conditional: an empty registry enqueues nothing.

Alternatives considered: version the checksum algorithm (rejected: forces a migration of every stored
hash); a dedicated replay table with a cron prune (rejected: adds persistence and a recurring job the
TTL already makes unnecessary); scan page content for forms to decide enqueue (rejected: fragile, and
the owner request explicitly preferred a declarative projection).

Status: Final.

## #149 -- Spec 071 WS3: forms event listeners register lazily, and delivery reuses MailResult states

Date: 2026-07-20

Decision: (1) The flow submission pipeline now reaches a `wp_mail()` floor when CoreX Mail is
inactive, via a shared `NotificationDispatcher` (RoutedMailer → AttemptingMailer → Mailer →
wp_mail), instead of recording 'unrouted' and sending nothing. (2) The delivery outcome is a typed
`NotificationDelivery` that reuses the canonical `Corex\Mail\MailResult` state vocabulary rather than
inventing a parallel one, adding only `not_attempted`, `unavailable`, and `rejected`. `wp_mail()`
returning true maps to `accepted`, never `sent`. (3) Forms event listeners are registered as lazy
closures, so they (and their mail dependency graph) are built on first dispatch, not at boot.

Why (1): the flow path had no fallback below RoutedMailer, so a site with CoreX Mail inactive saved
the submission and silently sent no notification -- the single most expensive failure the system can
produce (a lost enquiry with no trace). The legacy event path already had the ladder; extracting it
into one service and using it from both closes the gap and removes the duplication.

Why (2): the owner request is explicit -- "if the existing CoreX Mail typed attempt system uses
canonical names, reuse them instead of creating competing vocabulary." Two vocabularies for one fact
is exactly the drift to avoid. `accepted != sent` is the honesty rule: a transport taking a message
is not proof it reached an inbox, so the public/admin surfaces must never imply delivery from
acceptance.

Why (3): wiring the dispatcher into `SendEmailListener` -- which is constructed at boot to register
it as a listener -- made the Email Studio `RoutedMailer` resolve at boot, loading the mail stack's
translations before `init` (a WP 6.7 "translation too early" notice). Registering the listener as a
lazy closure defers the whole graph to request time and is the more correct shape anyway: booting a
plugin should not force-construct every listener's dependencies (Principle II). Caught by a front-page
debug.log delta check, not by a test -- so the check earned its place in the evidence routine.

Alternatives considered: a parallel `enum { saved_sent, saved_failed, ... }` for form delivery
(rejected: competing vocabulary the owner forbade); keep the ladder duplicated in both paths
(rejected: two copies drift, and the legacy copy already carried the wp_mail->sent bug); inject the
mailers into the dispatcher as lazy factory closures to avoid boot resolution (rejected: the lazy
*listener* registration fixes the root cause -- eager construction of a boot-time object -- without
making the dispatcher's own constructor awkward or untestable).

Status: Final.

## #150 -- Spec 071 WS4/WS5: one timeline shape, and an evidence-only transport advisory

Date: 2026-07-20

Decision: (1) The submission timeline has one canonical event shape
(`{id, submission_id, stage, outcome, summary, created_at}`) written by both the pipeline and the
admin services; the pipeline's old `{kind, state, occurred_at}` shape is gone, and legacy rows of it
are hydrated on read rather than dropped. A new `notification` timeline event carries the delivery
outcome. (2) The FluentSMTP boundary advisory reads only public signals -- the configured
`mail.from.address` domain vs `home_url()`, and `has_filter('wp_mail_from')`/`wp_mail_from_name` --
never the transport plugin's tables or credentials, and never fabricates a "detected" state where no
signal exists.

Why (1): two writers wrote the same meta key in two shapes. The admin React list already papered
over it with `event.stage || event.kind` fallbacks, so it was not literally blank -- but a single
source of truth is correct, and adding the notification event (so an admin sees the delivery outcome
in the history, not just "submitted") needs the canonical shape anyway. Hydration on read means no
migration and no lost history for submissions written before the fix.

Why (2): the owner asked for FluentSMTP guidance without CoreX becoming FluentSMTP or depending on
it. A registered `wp_mail_from` filter is the supported, public signal that some plugin is forcing
the From header; the From-domain-vs-site-domain comparison is a second public signal. Both are
credential-free and stable. Scraping FluentSMTP's option rows or decrypting its settings would be
more precise and would violate the optional-dependency rule -- so where the public signal is silent,
the advisory shows general guidance and makes no claim. The delivery badge and this advisory both
carry the same honesty rule the whole spec turns on: a transport accepting a message is not proof it
reached an inbox.

Alternatives considered: version the timeline shape and migrate stored rows (rejected: hydration on
read is cheaper and lossless); detect FluentSMTP by its version constant to name it specifically
(rejected: borderline reliance on an internal, and unnecessary -- the filter signal is enough and
FluentSMTP is named only as the common example in guidance, not as a detected fact).
## #154 -- CI runs every PR and the JS suite; "green" must name which suites ran

Date: 2026-07-22

Decision: `.github/workflows/ci.yml` runs on every pull request regardless of base, and runs the JS unit
suite as its own job. A completion claim must state which suites were run and their counts, not that
"everything is green".

Why: three separate reporting failures surfaced in one session, each with healthy code behind it.
1. Eight unit failures across the 070/071/072 stack were recorded as "8 pre-existing unrelated failures".
   `main` had none of them; the stack introduced all eight (DECISIONS #153).
2. PRs #118 and #119 targeted feature branches, and the workflow filtered `pull_request` to
   `[main, develop]`, so neither ever ran CI. GitHub renders an empty check-rollup almost identically to
   a passing one, so "no failing checks" read as "checks passed" when it meant "never tested".
3. `tests/token-inventory.test.js` regenerates its artifacts from source and fails on drift -- a good
   guard that **nothing in CI ever invoked**. Spec 072's CSS additions left the committed inventory
   stale, and the branch gate still reported every suite green, because the JS suite was not among the
   suites it ran.

In all three the tests were adequate and the summary was not. The mitigations are therefore mechanical
rather than procedural: unfiltered `pull_request` triggers so a stacked PR cannot skip the gates, and a
`js` job so a suite cannot be silently omitted. Integration and Playwright remain environment-gated and
still need a provisioned WordPress -- that gap is real and stated here rather than papered over.

Consequence for reports: "full unit 1448 passed / 0 failed, integration 197/0, JS 306/0, e2e 6/0" is a
verification claim; "all tests pass" is not, because it hides which suites were skipped.

## #151 -- Spec 072 T015: the notification drawer is NOT added to the spec-068 ApprovedComponentInventory

Date: 2026-07-21

Decision: The `NotificationDrawer`/bell are verified by a Playwright e2e (`tests/e2e/notification-center.spec.js`)
for their accessibility contract, and are NOT declared in `addons/corex-ui/src/ApprovedComponentInventory.php`,
despite spec 072 `tasks.md` (T015) calling for it. That inventory strictly reconciles against the spec-068
design file `Corex Blocks & Components.dc.html` -- its test asserts exactly 77 approved components
(33/8/13/23 per category). The notification drawer is a spec-072 runtime component that the spec-068 design
file does not approve.

Why: adding the drawer would force `expectedCounts()` and the `->toHaveCount(77)` total to 79 and claim a
design approval that does not exist -- the precise dishonesty that inventory test guards against. The
inventory's job is "the framework never claims a component the approved design didn't", not "every runtime
widget is listed". Forcing the entry would either break the count reconciliation or require editing the
spec-068 design source, which is out of spec-072 scope. The drawer's real, harder-to-fake guarantee -- focus
trap, Escape, focus-return, aria-modal -- is proven by the live browser e2e instead, which is stronger
verification than an inventory row.

Alternatives considered: add the entry and bump the counts to 79 (rejected: falsely asserts spec-068 design
approval and breaks the strict reconciliation); extend the spec-068 design file to include the drawer
(rejected: out of scope for spec 072, and the design file is the source of truth for spec 068, not 072). If
the owner later wants CoreX-072 admin components tracked in a governed inventory, that is a new, spec-072-owned
inventory -- not an edit to spec 068's.

## #152 -- Spec 072 T020: notification preferences live in user meta, not a managed table

Date: 2026-07-21

Decision: `WpNotificationPreferenceStore` persists per-user category preferences in **user meta**
(`corex_notification_preferences`), not in a new managed table -- despite T020's wording ("a new managed
table, schema 3->4"). Only muted categories are stored; everything defaults to shown, and the mandatory
categories (security / system / operations) are enforced by the `NotificationPreference` value object, never
by what happens to be persisted.

Why: preferences are small, per-user, low-volume, and WordPress-native -- exactly the case the constitution
and wp-guard reserve for options/transients/user-meta ("simple persistent data via user meta, not a custom
table; don't reinvent the platform"). A managed table here would add a schema version, a migration, and a
join for data WordPress already stores per user for free. The notification *records* are high-volume and
shared, so they earn their tables (spec 072 T008); a user's handful of category toggles do not.

Alternatives considered: a `notification_preferences` managed table keyed by (user_id, category) with a
schema 3->4 bump (rejected: reinvents user meta for no benefit -- no cross-user query needs it, and retention
never prunes it); a single site option (rejected: preferences are per-user, not global).

Status: Final.

## #153 -- The 070/071/072 stack was never tested by CI, and its "pre-existing" failures were its own

Date: 2026-07-22

Decision: stacked PRs must be verified with a full local suite run on each branch before they are queued
for merge, and the CI trigger's blind spot is treated as a known gap rather than assumed coverage.

Why: `.github/workflows/ci.yml` triggers only on `pull_request: branches: [main, develop]`. PR #118 targeted
`spec/070` and PR #119 targeted `spec/071`, so **neither ever ran CI** -- `gh run list` for those branches
returns empty, and their status rollups were empty, which reads as "no failures" rather than "never tested".
The one branch CI did test (#117, base `main`) failed.

What that hid: eight unit failures that earlier gate notes recorded as "8 pre-existing unrelated failures
(Email mock-ordering, Security emoji-shim, Theme SCSS)". They were not pre-existing and not unrelated --
every one was introduced by this stack, and `main` has none of them:
- **Security (1)** -- spec 070 added `restoreBlockStyles()` to `dropAdminContext()`, which registers a hook
  unconditionally. A spec-069 test asserted `Functions\expect('add_action')->never()` -- a blanket
  expectation that forbids the method ever hooking anything again, rather than the emoji-specific claim its
  name made. Scoped it with `->with(...)`, and added the test `restoreBlockStyles()` never had.
- **Email (6)** -- spec 071's new `TokenReplayGuardTest` stubs `wp_salt`. Brain Monkey defines a stubbed
  function into the global namespace **permanently** (PHP cannot undefine one), so
  `function_exists('wp_salt')` in `EmailAttemptRepository::recipientHash()` stays true for the rest of the
  process, and every later unstubbed call throws. Those tests had only ever passed by taking the fallback
  branch -- i.e. by suite order. Fixed by stubbing `wp_salt` where the code path needs it.
- **Theme (1)** -- spec 071 added a raw `font-size: 1em` that token governance rejects; it now carries the
  repo's documented `corex-token-allow` escape hatch, like the `inline-size`/`block-size` beside it.

Result: 070 = 1292/0, 071 = 1370/0, 072 = 1426/0 unit; 072 integration 186/0. Lesson recorded because the
failure mode was *reporting*, not code: an empty check-rollup on a stacked PR is not evidence of green, and
"pre-existing" is a claim that must be verified against the base branch before it is written down.

Status: Final.

## #155 — CoreX admin shell chrome is echoed directly, never re-filtered through `wp_kses_post()`
Date: 2026-07-26
Decision: A CoreX screen echoes the return value of `AdminPage::open()`/`close()`/`state()`/`tabs()`/
`permissionDenied()` directly. It must not wrap that markup in `wp_kses_post()` (spec 073, `DataModelsScreen`).
Why: `AdminPage` escapes every dynamic value at the point it interpolates it (`esc_attr`/`esc_html`/`esc_url`),
and its header/rail contributors escape their own inline-SVG content — the same discipline the WordPress admin
bar uses. Its output is therefore trusted, already-escaped chrome, not request data. `wp_kses_post()`'s allowed-
tags list excludes `<svg>`, so re-filtering that markup silently deleted every inline glyph on the Data Models
screen (brand mark, rail icons, an empty notification bell) — it removed correct output rather than adding
safety. Escaping belongs at interpolation, once; a second blanket filter over trusted chrome is a defect.
Alternatives considered: extend the kses allowed tags to include `svg` and its children (rejected: a large,
attribute-sensitive allowlist to maintain, applied to markup that is already escaped and needs no filtering);
render icons as `<img>`/mask CSS only (rejected: the shell already uses inline SVG deliberately, and the other
screens render it correctly without kses). Scope: trusted CoreX chrome only — request/user data is still
escaped/sanitized at its own boundary.
Status: Final.

## #156 — The Production-readiness badge reads the readiness snapshot, not a target-mode preview
Date: 2026-07-26
Decision: Operations & Security shows one mode control — the nonce- and capability-gated server form
(`OperationsSecurityScreen::modeCard()`). The client "mode preview" (target-mode selector, typed-PRODUCTION
box, maintenance checkbox) and the `modeActionState` derivation are removed; the readiness badge reads
`state.readiness.blockingCount` directly (spec 073).
Why: the preview applied nothing — the real mode change is the server form on the same page — so the page
carried the same controls twice, one inert, which the "no dead/inert control" rule (Spec 068 / ROADMAP §17)
forbids. And `modeActionState` reported zero blockers for every mode except Production, so the badge said
**Ready** over blockers that were listed directly beneath it unless the preview happened to be set to
Production. The badge was always describing production readiness; it now reads that snapshot, so the header and
the list beneath it can no longer disagree.
Alternatives considered: keep the preview but wire it to apply the mode client-side (rejected: duplicates the
gated server form and moves a security-sensitive action off its nonce); keep `modeActionState` but fix its
per-mode zeroing (rejected: it existed only to serve the removed preview — deleting it removes the divergence
at the source). CorexSelect dropdown changes in the same spec defer to #141 and are not re-decided here.
Status: Final.

## #157 — Read is a fact about the person; needing action is a fact about the condition
Date: 2026-07-27
Decision: A notification's view membership is derived on the server from its *status* and *severity*
(`NotificationView::of()`), never from whether the actor has read it. `read` and `resolved` are separate
fields with separate controls. Snoozing moves an item to **Updates**, not History, and it returns on its own
when the snooze elapses (spec 074, FR-4.1/FR-4.3).
Why: v0.35.0's "Requires attention" filtered on the actor's unread state, so opening a production-readiness
blocker took it off the attention list while the blocker was still true. That is the whole defect: reading is
something you did, not something that happened to the condition. Deriving the view once on the server means
the screen and the drawer cannot drift into disagreeing about what still needs somebody — the drawer used to
show a shorter, differently-worded version of the same record.
Alternatives considered: keep read-as-attention and add a separate "unresolved" filter (rejected: leaves the
default view lying, and the default is what people act on); derive the view in the client from the raw fields
(rejected: two consumers, two implementations, one eventual divergence — which is the bug being fixed).
Enforcement: the derivation is covered by `tests/Unit/Notifications/NotificationViewTest.php`, and the
rendered consequence by `notificationItem.test.js`, whose read-vs-resolved spec was mutation-checked —
reintroducing `needs_action && ! read` fails that test and no other.
Status: Final.

## #158 — The eight saved notification views collapse to three; the rest were always filters
Date: 2026-07-27
Decision: The Notifications screen offers **Action needed · Updates · History**, plus Preferences. Inbox,
Requires attention, Assigned to me, Submissions, Security, and System are gone as tabs; category, severity,
and assignment are refines that apply within whichever view is active (spec 074, FR-4.2).
Why: those tabs competed with the only question the screen exists to answer — does this still need me?
Submissions/Security/System were category filters wearing tab clothing, and "Assigned to me" is an axis that
is useful *inside* Action needed, not instead of it. Three views map one-to-one onto the three states a
condition can be in, so the IA carries the model rather than a menu of saved searches.
Alternatives considered: keep the tabs and re-point "Requires attention" at the derived status (rejected:
fixes the lie but keeps five near-duplicate lists); make the views user-configurable (rejected: YAGNI, and it
would re-admit the drift the fixed set removes).
Enforcement: `tests/e2e/notification-center.spec.js` asserts the retired tabs are **absent** — every other
assertion in that file would still pass after a revert, so the absence is the guard.
Status: Final.

## #159 — A control the actor may not use is hidden, not shown and refused
Date: 2026-07-27
Decision: "Mark resolved" renders only when the record's `can_resolve` is true, which is the same ability
(`MANAGE_NOTIFICATIONS`) the REST route's own permission callback enforces. The same rule governs the Data
workspace tabs: `allowedTabs()` requires the permission **and** an eligible source (spec 074, FR-4.6/FR-3).
Why: offering a control that answers with a permission error teaches people the product is broken rather than
that they lack an ability. Deriving the flag from the same ability the route enforces means the two cannot
disagree — if they ever did, the UI would be lying about what it can do.
Alternatives considered: render disabled with a tooltip (rejected: a disabled control still advertises a
capability this actor does not have, and tooltips are not reliably reachable); let the route refuse and show
the error (rejected: that is the behaviour being removed).
Status: Final.

## #160 — Capability is summarised where the question is asked, and a secret-shaped fact is dropped, not redacted
Date: 2026-07-27
Decision: `CapabilityReport` (pure) + `CapabilityFacts` (the WordPress boundary) render a read-only summary
beneath the Data Models catalog: registered forms and where each came from, per-model capabilities, add-on
state, wired notification producers, and configuration that is selected but unfinished. Any fact carrying a
credential-shaped key is **dropped entirely**, and summaries are stripped of file paths and stack frames
(spec 074, FR-5).
Why: capability was only discoverable by walking into it — you learned a model could not be imported into by
opening Import and reading a sentence about adapters, and that a captcha driver had no keys when forms quietly
stopped accepting submissions. The summary sits under the Models catalog because that is where the question
arises, and every gap links to the screen that fixes it rather than fixing it in place. Dropping rather than
redacting is deliberate: a redaction still tells a reader *where to look*, and diagnostics are exactly what
gets screenshotted into a support thread.
Alternatives considered: a full Capability Inspector / System Map screen (deferred — ROADMAP §17 candidate;
this spec closes the truthfulness gap, not the tooling ambition); redact secret values in place (rejected per
above); resolve the facts inside the pure reporter (rejected: it would make the summary untestable without
WordPress and able to invent state).
Scope: `AddonManager::state()`/`isInstalled()` moved off `AddonsScreen` for this — "which add-ons are running"
is a fact about the site, not about that screen, and two derivations would be two chances to disagree.
Status: Final.

## #161 — Blog Pro's screen offers what the services support, and nothing they cannot honestly do
Date: 2026-07-27
Decision: Blog Pro's React screen wires the existing services and REST routes into a working editorial
workspace (spec 075). It adds **no** service, route, table, or state-machine rule. Where a requirement appeared
to need one, the constraint is recorded instead: `EditorialWorkflowService` has **no transition graph**, so the
panel offers every state but the current one rather than implying a rule the service does not enforce; and the
moderation queue offers **approve / spam / trash** only, because `queue()` returns just comments held for
review, so "unapprove" has nothing to act on.
Why: the screen was a read-only reference dashboard over a complete back end — `BlogProApp` called
`useReducer` and discarded the dispatch, so the whole client state module was unreachable while fully covered
by tests, and all seven routes had no caller in the product. Inventing domain rules to fill the UI would have
made the screen confidently wrong, which is the defect Spec 074 spent itself removing.
Alternatives considered: build a real transition graph (rejected: new domain logic, and the owner has not
decided what the graph should be); add a `GET /blog/editorial/{id}` so selection could refetch without a page
load (rejected: out of plan — instead the selection *is* `?post=<id>` and the server renders it, which FR-1
wanted anyway for linkability).
Status: Final.

## #162 — Unreachable code with passing tests is deleted, not carried
Date: 2026-07-27
Decision: Spec 075's Definition of Done item 6 — "no dead export remains in `blogProState.js`" — was enforced
literally. `buildShareClickPayload` and the reducer's `shareRecorded` case are **deleted**, as are the `chart`
and `topPosts` branches of `normalizeAnalytics`, along with their tests.
Why: `POST /blog/share-click` records that a *visitor* shared a post; the only caller that could honestly make
that claim is the visitor-facing surface, which spec 075 §10 excludes. Firing it from the admin screen would
have written analytics nobody generated — a control reporting a click that never happened is worse than no
control. Separately, no server path has ever sent `chart` or `top_posts`: `BlogAnalyticsService::aggregate()`
returns one post's counts for one window, so both branches were permanently empty, and rendering them would
need analytics capability §10 excludes. Shaping data that never arrives is the same defect as a reducer nothing
dispatches to, only quieter — and a green suite over unreachable code is precisely what let Blog Pro sit
unfinished through two releases.
Alternatives considered: keep them for the future front-end spec (rejected: that spec can add what it needs,
and dead code with passing tests reads as working software); mark them `@deprecated` (rejected: the tests would
still be green and the next reader would still believe them).
Scope: the routes themselves are untouched — `/blog/share-click` stays for the visitor-facing spec that will
own it. This is about the admin client's exports, not the API surface.
Status: Final.

## #163 — The 1px RTL admin overflow is WordPress core's, and is asserted comparatively
Date: 2026-07-27
Decision: The horizontal overflow PROGRESS.md recorded as "the CoreX admin shell overflows by 1px in RTL at
375px on every CoreX screen" is **not a CoreX defect and is not fixed in CoreX**. The browser assertion added
in its place is comparative: no CoreX route may scroll sideways any further than stock wp-admin already does
(`tests/e2e/admin-command-center.spec.js`).
Why: measured against the real local install at 375px with `dir=rtl`, wp-admin's own Dashboard, Settings and
Plugins screens scroll by exactly the same 1px, with no CoreX CSS on the page. Hiding `#wpadminbar` takes every
one of those screens — and every CoreX screen — to 0px. The cause is core's visually-hidden admin-bar chrome:
`position:absolute; margin:-1px; width:1px` with no inset, whose static position in RTL sits one pixel outside
the inline edge, and left-of-origin content extends `scrollWidth` in RTL where it does not in LTR. The reading
in PROGRESS.md was correct; the attribution was not.
Alternatives considered: override the offending core elements from `corex-admin-shell.css` (tried, then
reverted — an `inset-inline-end: 0 !important` on `.a11y-speak-region` does move that element back inside the
viewport, but the admin bar still contributes the same pixel, so the override bought no observable change while
adding an `!important` against core's inline styles); put `overflow-x: clip` on `body.corex-admin-screen`
(rejected: that hides genuine CoreX layout bugs rather than fixing them, which is the defect this branch exists
to stop); assert `scrollWidth <= clientWidth` outright (rejected: cannot pass, for a pixel CoreX does not cause).
Scope: the comparative assertion is a real gate, not a weakened one — injecting `min-inline-size: 120vw` into
`.corex-admin` makes it fail, which is how it was verified before it was trusted.
Status: Final.

## #164 — Both linters gate CI, and the tooling looks at one tree
Date: 2026-07-27
Decision: `lint:css` and `lint:js` run in CI, as steps of the existing `js` job rather than a job of their own.
`eslint.config.js` scopes ESLint to CoreX source, and `jest.config.js` now excludes `dist/` and `build/` for the
same reason the linters do.
Why: nothing ran either linter in CI, which is how spec 074 introduced 44 stylelint errors that had to be found
by hand and how ESLint accumulated 2589 problems. `lint:js` could not have been gated even if someone had
tried: `wp-scripts lint-js` with no project config lints `.` under the WordPress default flat config, whose only
global ignores are `build`, `node_modules` and `vendor` — so it walked the full WordPress install at `wp/` and
the generated bundle at `dist/`, and never finished. `@wordpress/*` imports are declared as build-time externals
(`import/core-modules`) because `dependency-extraction-webpack-plugin` rewrites them to the `window.wp.*`
globals WordPress already serves; installing them would be the actual mistake, since a bundled copy would shadow
the version the host runs. Jest had the same blind spot in reverse: it *included* `dist/`, so 17 suites ran
twice against generated copies, and the local suite count (62 suites / 385 tests) disagreed with CI's (45 / 331)
purely because `dist/` is git-ignored and never exists there.
Alternatives considered: a separate `lint` job (rejected: a second `npm ci` on every PR to say the same thing);
relaxing the rules that failed rather than fixing the code (rejected — with one recorded exception: nothing was
silenced, `jsx-a11y/label-has-associated-control` was satisfied by giving 25 controls real `for`/`id` pairs).
Status: Final.

## #165 — One dictionary: PHP owns the localized words, the browser composes with them
Date: 2026-07-27
Decision: The browser half of the CoreX date contract (`adminDateTime.js`) never translates a date. Month
names, meridiem markers and the **format patterns themselves** arrive already translated from PHP through one
localized payload, and the browser interprets the same pattern string the server does. `Intl` is used only to
extract numeric calendar parts in the site's timezone.
Why: FR-001 requires server and browser output to be character-identical. `Intl.DateTimeFormat` with the site
timezone and locale gets the timezone right and the *words* wrong — it reads CLDR while WordPress reads the
`corex` translation files, and in Arabic those disagree (`أغسطس` against `آب`). Two implementations reading two
dictionaries cannot be reconciled by testing; they can only be reconciled by having one dictionary. Shipping the
pattern too means a translator who reorders `j F Y`, or writes the connector as `%2$s — %1$s`, reorders both
sides at once — which is why the connector is a `sprintf` pattern rather than `\a\t` escaped inside a date
format, a trap for any translator who does not know `a` means meridiem and `t` means days-in-month.
Alternatives considered: `Intl` for everything (rejected: the Arabic divergence above); the server pre-formatting
every date into the payload (rejected: relative time and freshly-created records have no server render to carry).
Scope: proven by `tests/Fixtures/datetime-parity.json` — 16 instants read by both suites and compared against
the same expected strings, including both Cairo DST transitions. Verified load-bearing by breaking it.
Status: Final.

## #166 — CoreX admin dates do not follow Settings → General
Date: 2026-07-27
Decision: CoreX admin screens render dates in one fixed presentation — `1 August 2026 at 10:20 PM` in English —
rather than in the site's configured `date_format`/`time_format`. Owner-confirmed.
Why: a per-site format and a guaranteed server/browser match are mutually exclusive, and the previous behaviour
demonstrated it: three server-side surfaces built their format from those options and produced
`July 27, 2026 8:53 am`, which no browser-side renderer was ever going to reproduce. The format is still fully
translatable — `j F Y`, `g:i A` and the connector are `_x()` strings — so a locale changes it once for both
sides. The front end and the rest of wp-admin are unaffected.
Alternatives considered: honour Settings → General and drop the parity requirement (rejected by the owner: the
required presentation is fixed, and two colleagues reading different times for one event was the defect).
Status: Final.

## #167 — A value that is not credibly a timestamp is an absence, not a date
Date: 2026-07-27
Decision: `Instant` (and its JS mirror) refuse: a non-positive integer, a bare integer below 2000-01-01, a
truncated date such as `2026-08`, and a relative expression such as `now` or `+1 day`. All render the calling
field's absent phrase.
Why: each of these parses into a *convincing* date, which is the dangerous kind of wrong. `0` becomes
1 January 1970. `'2026'` is all digits, so read as seconds it becomes a January 1970 date — a year arriving in a
timestamp field and rendering as one. `new DateTimeImmutable('2026-08')` is a valid 1 August, and
`new Date('2026-08')` agrees. `'now'` renders as today and looks exactly like a working feature. FR-018 forbids
`Invalid Date`, `NaN` and the epoch; these are the same failure wearing better clothes.
The asymmetry is deliberate: integer `0` is an absence but `'1969-07-20T20:17:00Z'` still parses, because a
written-out date is a statement and a sentinel integer is not.
Scope: the `'2026'` case was found by widening the parity fixture's absent list, not by review — both
implementations had it, identically wrong. The centralised rule replaces a hand-written `$entry['time'] > 0`
guard that `OperationsSecurityScreen` already applied at one call site out of many.
Status: Final.

## #168 — The screen's sections are links, not an ARIA tablist
Date: 2026-07-27
Decision: Operations & Security is divided by `AdminPage::tabs()` — ordinary `?tab=` anchors carrying
`aria-current="page"` — rather than by a `role="tablist"` with in-page panels.
Why: every behavioural requirement the sectioning had to meet is a property links already have. The
address reflects the section, so a view can be bookmarked and shared; Back and Forward work; a
Post/Redirect/Get can land on the section the form was submitted from; keyboard navigation is the
browser's own; and all of it works with JavaScript disabled, which FR-013 requires. A tablist would
have to reimplement each of those — `aria-selected`, roving tabindex, focus management, history
entries — and still could not satisfy the no-JavaScript case without duplicating the server render.
Alternatives considered: an ARIA tablist with client-side panels (rejected above); progressive
disclosure within one column (rejected: it keeps the ~3,500px page the spec exists to break up).
Scope: `AdminPage::tabs()` already existed and Add-ons already used it, so this is reuse rather than
a new primitive. Spec 078 adds its Cache & Performance section to the same list.
Status: Final.

## #169 — Progressive disclosure degrades to a second step, never to a wrong form
Date: 2026-07-27
Decision: The mode form renders all four mode blocks with three `hidden` **and their inputs
`disabled`**, showing the block for a *proposed* mode carried in `?mode=`, defaulting to the current
one. JavaScript swaps blocks on change; without it, submitting a mode whose confirmation was not on
screen redirects back proposing that mode with its confirmation shown.
Why: the form is a plain server POST with no client behaviour, so "show only what this mode needs"
had to work where the browser cannot react to the select. Disabling — not merely hiding — is what
makes it safe: a hidden input is still submitted, so without it the server could receive a
confirmation belonging to a mode nobody chose.
Alternatives considered: fetch the right block over REST on change (rejected: adds a route, a
spinner and a failure mode to a form that has none); render only the current mode's block and
validate server-side alone (rejected: the operator would submit blind and be refused, with no way
to see what was being asked).
Scope: one bug worth recording, because only a browser test found it. The first implementation
synced from `select.value` on load — the mode the site is IN — so arriving at `?mode=production`
after a redirect, the script hid the production block the server had deliberately rendered and
showed the current mode instead. That silently closed the no-JavaScript path *for JavaScript users*:
submit Production, get redirected to the form that asks for the phrase, watch the phrase field
disappear. The server's rendering is the instruction; the selection follows it.
Status: Final.

## #170 — A well-formed login address is not necessarily a free one
Date: 2026-07-27
Decision: `LoginSlug` keeps answering shape and reserved names, unchanged and still pure. A new
`LoginSlugAvailability` answers whether anything already responds at that address — a published page
or post of any public type, or a rewrite rule that claims the path by name. The settings controller
asks both.
Why: `about` passes every rule in `LoginSlug` and is one of the most common page slugs there is. The
collision does not appear at save time; it appears later, as a login serving somebody's About page.
The two classes stay separate because `LoginSlug` runs on `plugins_loaded` before translations
exist, and its lack of a database dependency is why it has held since DECISIONS #140.
Alternatives considered: running the slug against every registered rewrite pattern (implemented
first, then removed — WordPress's page rule is the catch-all `(.?.+?)/?$`, which matches every path
by design, so it answered "taken" for every slug and no custom login address could have been saved
at all; two obviously-should-pass tests caught it). Only rules whose pattern begins with a literal
segment — `category/`, `author/`, a post type archive — actually claim a path, and the catch-all is
already covered more precisely by the published-page check.
Status: Final.

## #171 — Cache is classified, and clearing walks declarations rather than matching patterns
Date: 2026-07-28
Decision: Every value CoreX caches is declared in `CacheRegistry` with an owner, a classification, a
lifetime and an invalidation path. `CacheManager::clear()` iterates declared entries and skips those
whose classification forbids removal. **No code path in this feature deletes by key pattern.**
Why: `ThrottleMiddleware` stores rate-limit counters as `corex_throttle_*` transients and
`TokenReplayGuard` stores spent captcha tokens as `corex_captcha_seen_*`. Both are security controls
that look exactly like cache from the outside. The obvious implementation of "clear CoreX's caches"
— a sweep of `corex_*` — would reset brute-force protection and re-open the replay window, silently,
at the moment an operator is most likely to run it. A guard placed in the CLI would protect the CLI;
a guard in the registry protects every caller that will ever clear a cache. A `DELETE ... LIKE
'corex_%'` is not refactorable into this design, which is the point.
Alternatives considered: a documented list of keys not to clear (rejected: FR-002 requires the
classification in code, because the risk is a future contributor adding a prefix to a list, and a
comment does not stop that while `mayBeClearedRoutinely()` returning false does); clearing by pattern
with an exclusion list (rejected: the exclusion list is the thing that silently falls out of date).
Scope: verified by tests that iterate `CacheScope::cases()`, so a scope added later cannot ship
without satisfying them. Verified load-bearing by deliberately misclassifying the throttle entry.
Status: Final.

## #172 — CoreX refuses to flush a persistent object cache
Date: 2026-07-28
Decision: `wp corex cache:clear --scope=object` is refused outright on a site where WordPress is
using a persistent object cache, with the reason stated and `wp cache flush` named as the operator's
own route. Where there is no persistent object cache, it proceeds.
Why: with a drop-in installed, WordPress stores transients **in** the object cache. `wp_cache_flush()`
would therefore remove `corex_throttle_*` and `corex_captcha_seen_*` as collateral — not by walking
them, not by matching them, but by emptying the place they live. FR-003 says no cache operation may
remove security state, and removing it indirectly is still removing it. The registry guarantee
cannot cover this case, because the operation does not go through the registry at all.
Alternatives considered: preserve and restore the protected entries around the flush (rejected: the
protected families are key *prefixes* and object caches cannot be enumerated, so they cannot be read
back); warn and proceed (rejected: a warning that protection was removed is not the same as not
removing it); drop the scope entirely (rejected: it is legitimate where nothing durable is lost).
Scope: found by asking why a test failed for an unrelated reason on a site with no persistent object
cache — the failure was a type artifact, and the question exposed what would happen on a site that
had one.
Status: Final.

## #173 — Presence is not use, and "cannot look" is not "off"
Date: 2026-07-28
Decision: The object-cache layer reports `active` only when `wp_using_ext_object_cache()` is true,
and `available` when a drop-in exists but WordPress is not using it. OPcache reports `unknown` — a
distinct state — when the host disables `opcache_get_status()`.
Why: a running Redis container is a fact about the server; whether WordPress uses it is the fact that
affects the site, and reporting the first as the second tells an operator their site is faster than
it is with no error to correct the impression. Equally, answering "off" because CoreX was not
permitted to look would send someone to fix a problem that does not exist. Seven states exist rather
than a boolean because every interesting case is in the middle.
Scope: also why `manageable` and `safeToClear` are separate fields on a layer — CoreX *can* flush the
object cache and it is not safe to; CoreX *cannot* purge a CDN where doing so would be perfectly
safe. One flag would produce a control either missing when it should be present or dangerous when it
looks routine.
Status: Final.

## #174 — A browser form never posts to a REST route, and the REST route never becomes a page
Date: 2026-07-28
Decision: The denied screen's access-request form posts to a dedicated `admin_post` endpoint that
calls the same `AccessService::requestAccess()` the REST route calls, then redirects. The REST route
is unchanged and still answers JSON. Nothing in CoreX inspects `Accept` to choose between HTML and
JSON, and no global `wp_die_handler` filter is installed.
Why: the form's action was `rest_url('corex/v1/access/requests')`, so submitting navigated the
browser to a JSON document — and the request *succeeded*, so a person asking for help was shown an
operation envelope with no way to know anyone would see it. The tempting fix is content negotiation
on the existing route: it makes the symptom disappear and silently changes what every API consumer
receives. Two doors onto one service cannot drift; one door that behaves differently per caller can.
Scope: the posted field is the **section**, not the ability — the browser says which screen refused
it and the server decides what that screen requires, so nobody can request an arbitrary ability from
an arbitrary screen and have it look legitimate in the queue.
Status: Final.

## #175 — The confirmation is read from stored state, not carried in the redirect
Date: 2026-07-28
Decision: After a successful request the redirect carries no arguments at all. The denied surface
asks the database whether this user has an open request and renders the confirmation from that. Only
the two failure paths use a flash, stored in a short-lived, single-use, user-bound transient — never
a query string.
Why: it makes refresh-safety, back-button safety, duplicate suppression and forgery resistance
properties of the data model rather than of flash-message discipline. A `?sent=1` can be typed by
anyone, is lost on a bookmark, and says nothing tomorrow. The reason text stays out of the query
string because it is the requester's own words, and query strings are logged by the web server, kept
in browser history and forwarded in `Referer`.
Status: Final.

## #176 — Not found is not denied
Date: 2026-07-28
Decision: `admin_page_access_denied` fires for two different causes, and CoreX distinguishes them
using the same globals in the same order as WordPress's own `user_can_access_admin_page()`. A
registered CoreX screen the viewer may not open answers 403 with the designed denied surface; a
`corex-` address with no screen behind it answers 404, with no capability explanation and no request
form.
Why: matching the prefix alone told an administrator, at 403, that their role lacked
`manage_options` — false, wrong status, and it offered them a form to request access to a screen
with no ability behind it. Once the request workflow worked, that form would have created a real,
auditable request nobody could ever grant.
Scope: `$_registered_pages` alone is not the check. `add_submenu_page()` records a page the viewer
may not open in `$_wp_submenu_nopriv` and returns before touching `$_registered_pages`, so
registration is not viewer-independent — reading only that global reports every real CoreX screen as
missing to exactly the people the gate exists for.
Status: Final.

## #177 — A workflow is not shipped until both ends of it work
Date: 2026-07-28
Decision: Pending access requests are listed by the REST route and the Access screen from
`AccessRequestStore::pending()`, rendered with requester, ability, date and reason, and decided with
Approve and Deny. The Access Overview names how many people are waiting and links to the panel, and
renders nothing when nobody is.
Why: the route returned a hardcoded `[]`, the screen localized a hardcoded `[]`, and `pending()` had
no production caller — so a request was created, audited and notified, and then no surface in the
product ever read the table, while the denied screen told the requester an administrator would
review it. Fixing only the requester's side would have delivered people into that silence more
convincingly than before, because the confirmation would have looked right.
Scope: found by trying to write a test fixture, not by reading the code — the browser spec needed to
clear pending requests between runs and the only route that could do it returns nothing by
construction. A test that needs a capability the product claims to have is a good way to find out it
does not.
Status: Final.

## #178 — Dependency updates land as one deliberate refresh, not thirteen open PRs
Date: 2026-07-28
Decision: Dependabot's proposals are applied on a single branch, gated once, and merged as one
commit. The individual PRs are then closed as superseded, each with a comment naming the commit
that carries it.
Why: thirteen PRs had accumulated, every one based on a `main` old enough that its CI ran against a
four-check pipeline instead of six, and six of them reported a failure that was really staleness.
Rebasing and merging them one at a time is thirteen rebases and thirteen CI runs to reach a state
one run can prove. Closing them without applying anything is worse: Dependabot re-creates them
within a week, which is exactly how the backlog formed.
Scope: this is the *handling* policy. Whether a given bump is taken is decided per bump, on
evidence — see #179–#181 for the three that were not.
Status: Final.

## #179 — @wordpress/scripts 33 and @wordpress/components 37 are taken; the tree is proven, not assumed
Date: 2026-07-28
Decision: both majors land. `@wordpress/scripts` 32.6.0 → 33.0.0 and `@wordpress/components`
36.1.0 → 37.0.0.
Why: each was attempted and measured rather than deferred for being a major. The block and admin
builds compile, `lint:js` and `lint:css` are clean, and Jest is unchanged at 381/381. A major that
passes every gate is not a risk being taken; it is a risk that was checked.
Scope: `@wordpress/i18n` and `@wordpress/element` moved with them inside their existing ranges.
Neither `@wordpress/components` nor `@wordpress/i18n` is declared in the root manifest — they belong
to `plugins/corex-config/package.json`, which is why Dependabot's PRs for them looked rootless.
Status: Final.

## #180 — Pest stays on 2 because Pest 4 is right about 21 of our tests
Date: 2026-07-28
Decision: `pestphp/pest` is held below 3.0, with a Dependabot ignore and this entry.
Why: Pest 4 installs cleanly and runs the entire suite — **1572 passing, 0 failing**. It fails only
because it reports 21 tests as RISKY, and it is correct to: those tests exercise the boot logger and
the block and event error paths, so the code under test deliberately writes diagnostics, and the
tests let that output escape instead of asserting it. Pest 2 did not notice. The right fix is to
assert the output — which improves the tests, since a test of a logger that never checks what was
logged is barely a test — and that is a 21-test rewrite across several files.
Scope: bundling that rewrite into a dependency refresh would mix a test-quality change into a
lockfile change, and a revert of one would be a revert of the other. The migration is its own piece
of work, and this entry is the head start: the affected files are `tests/Unit/Blocks/BlockMapTest`,
`RenderDelegationTest`, `Events/EventDispatcherTest` and `Foundation/BootLoggerTest`.
Status: Final — revisit as its own task.

## #181 — Astro 7 is not blocked by Astro; it is blocked by `file:..`
Date: 2026-07-28
Decision: `astro` is held below 7 and `@astrojs/starlight` below 0.41, both by Dependabot ignore.
Why: the framework side is ready and was verified on 2026-07-22 — starlight 0.41.3 peers astro
^7.0.2, and the site builds to the same 284 pages with no config change. The blocker is packaging:
`docs-app/package.json` declares `"corex-framework": "file:.."`, so npm cannot resolve the bump in
place (ERESOLVE against the pinned lock), and regenerating `docs-app/package-lock.json` makes npm
expand the framework root into the docs tree — the npm-docs audit then covers root's dev tooling and
goes from 5 findings to 17.
Scope: recording it here because the two are repeatedly confused. Nothing about Astro 7 needs
waiting for; what needs deciding is whether docs-app keeps the `file:..` link at all. Until then the
hold is honest and the ignore stops the PR returning weekly.
Status: Final — revisit with the docs-app packaging decision.

## #182 — The advisory gate was failing on `main`, and the path filter is why nobody saw it
Date: 2026-07-28
Decision: the policy is reconciled against the real tree and the gate passes for the first time —
composer 0/0, npm-docs 6/6, npm-root 18/18.
Why: `verify-dependency-security.mjs` was reporting FAIL on `main` before this branch existed: three
`minimatch` entries whose recorded paths no longer matched the tree, plus two unbounded findings
(`postcss` in docs, `brace-expansion` in root) with no exception at all. It went unnoticed because
`.github/workflows/dependency-security.yml` is path-filtered to manifest and policy changes, so no
ordinary PR runs it — the gate is only consulted by the PRs least likely to be looking for it.
Scope: five of the six violations predate this work; one (`@opentelemetry/core`) arrived with
`@wordpress/env` 11.11.0 and its bundled Sentry instrumentation. All are dev or build-time
transitives under eslint, markdownlint-cli, rimraf and wp-env — none is in a distributed artifact,
which is what the new exceptions record with a stated control and a review date.
Note: the paths in the first attempt were wrong because they were written from a probe truncated to
three entries when the audit reported four. Read the whole list before encoding it in policy.
Status: Final.

## #183 — A fail-safe that cannot catch its own failure mode is not a fail-safe
Date: 2026-07-28
Decision: `WebpConverter::convertWithGd()` promotes an indexed-colour image to truecolour and
refuses to call `imagewebp()` unless the image is truecolour by the time it gets there. The
`catch (Throwable)` in `convert()` stays, but it is no longer what protects this path.
Why: GD raises `Palette image not supported by webp` as an **E_ERROR**, which no `catch (Throwable)`
can intercept — verified by reproduction, the process dies with the catch in place. The class
documents itself as a "fail-safe boundary… never a fatal", and for the encoder it was neither. A
palette PNG is what design tools export a flat-colour logo as, so the affected upload is routine;
the reporter's 24-logo migration stopped on the first one. Because the conversion runs on
`wp_generate_attachment_metadata`, the request that died was the upload itself.
Scope: the general lesson is the reason this is written down. Where a library signals failure
outside the exception system, safety has to be a **precondition** the caller can check, not a
handler the caller hopes will fire. `imageistruecolor()` is that check here.
Note: the explicit `imagealphablending(false)` / `imagesavealpha(true)` pair is kept but is *not*
what fixes the fatal — on the GD build this was verified against, `imagewebp()` preserved alpha with
or without it, for both promoted-palette and truecolour sources. The reporter saw transparency loss
on theirs and GD builds differ, so it stays as a stated intent; it is deliberately not described as
load-bearing, because measuring it here showed it was not.
Status: Final.

## #184 — A form's listeners are resolved per submission, not registered per site
Date: 2026-07-28
Decision: `FormsServiceProvider` registers **one** listener on `FormSubmittedEvent`, which resolves
the submitted form by slug and runs that form's list. It no longer walks every form at boot
registering each distinct listener id once.
Why: the old registration produced a **global** list. Deduplication was across all forms, so as soon
as any form declared a listener, that listener ran for every submission on the site.
`Form::listeners()` documents itself as overridable, and overriding it to *remove* a listener was a
no-op, because some other form had already registered it — a site replacing the built-in
notification with its own sent two emails per submission, with no way to stop it. Found on a real
build, not by reading the code (issue #138, item 1).
Scope: laziness is preserved deliberately — the listener graph reaches the mail stack, and building
it at boot loads translations before `init`. A slug matching no registered `Form` (a database-defined
flow) runs nothing, which is the correct answer; falling back to "every listener" would be the same
bug wearing a different hat.
Note: only two of the four tests covering this fail against the old code. The removal and
unknown-slug cases pass under it too, because the fixture forms register after boot and the old
boot-time sweep never saw them. Two-of-four green is not partial success here; it is the shape of the
defect.
Status: Final.

## #185 — A body sent as text/html is written as HTML
Date: 2026-07-28
Decision: `NotificationDispatcher::htmlBody()` is added **beside** `plainTextBody()`, and the
submission notification uses it. The plain-text builder stays.
Why: every CoreX transport sets `Content-Type: text/html`, and the notification body was built by
joining `label: value` lines with newlines. HTML collapses whitespace, so the whole submission
arrived as one unbroken run — in an email whose own header promised markup. Replacing
`plainTextBody()` in place was rejected: a genuinely plain-text transport still wants it, and
silently returning HTML from a method named for plain text is how the mismatch arose.
Scope: the notification also now carries `Reply-To` from the submission, validated with `is_email()`
before it reaches a header. `MailRequest` has accepted `replyTo` all along and `WpMailDriver` has
always emitted it — the missing piece was one argument, not a feature. No usable address means no
header, which is the previous behaviour and the right answer for a form that never asked for one.
Status: Final.

## #186 — A manual reply is the same product as an automated one
Date: 2026-07-28
Decision: `EmailStudioSubmissionGateway::reply()` wraps the operator's HTML in the brand `Layout` and
uses the configured reply-to. `MailServiceProvider::brand()` supplies a `logo` — the theme's custom
logo, falling back to the site icon, both absolute.
Why: `reply()` sent the operator's raw textarea content as the entire message body with `replyTo`
hard-coded to `null`, while `resend()` immediately below it looked up the template version and layout
and rendered through them. So a site's automated email was branded and its human reply was not, from
the same screen. Separately, `Layout::wrap()` has always had an `<img>` branch for `$brand['logo']`
and nothing anywhere wrote that key, so the branch was unreachable and every framework email was
text-branded.
Scope: the reply is deliberately **not** run through a template — there is no template for "whatever
the operator typed". The logo URL must be absolute because an email client has no page context to
resolve a relative path against. This is a visible change to shipped behaviour, not a pure bug fix,
and is recorded as such rather than presented as invisible.
Status: Final.

## #187 — The wp_die HTML handler is CoreX's, and the five machine handlers are not
Date: 2026-07-28
Decision: CoreX filters `wp_die_handler` and renders every human-facing admin refusal as a branded
document. It subscribes to none of `wp_die_ajax_handler`, `wp_die_json_handler`,
`wp_die_jsonp_handler`, `wp_die_xmlrpc_handler` or `wp_die_xml_handler`, and a test asserts that.
This amends #174, which stated as part of a different decision that "no global `wp_die_handler`
filter is installed".
Why: #174's subject was the access-request form posting a browser at a REST route, and its finding —
that content negotiation on one endpoint silently changes what every API consumer receives — still
stands and is untouched here; no route changed. But the sentence was read as scope for spec 079's
error work, and the result was measured before this decision was written: nine of eleven admin
addresses still rendered WordPress's white box to a real subscriber, two of them screens CoreX
itself registers (`specs/083-admin-error-surface/evidence/before/refusal-matrix.md`). A framework
that owns the admin experience cannot own only the eleven percent of it reachable by
`admin.php?page=corex-*`.
Scope: the boundary is core's, not ours. `wp_die()` selects its handler by request type *before* any
filter runs (`wp-includes/functions.php:3791-3849`), so a machine caller can never arrive on the
branch CoreX filters — the safety is structural rather than a check we have to remember. Nothing
inspects `Accept` anywhere. `wp-login.php` and front-end `wp_die()` are outside `is_admin()` and stay
WordPress's, as does the logout confirmation, which reaches `wp_die()` at 403 but is a prompt.
Status: Final.

## #188 — A refusal names the ability it can ask for, not a capability it cannot know
Date: 2026-07-28
Decision: the denied surface no longer names a WordPress capability. It names the CoreX ability an
access request from that screen resolves to, framed as what will be requested.
Why: it said "your role doesn't include the `manage_options` capability" on every screen. That is
false on `corex-notifications` (`corex_manage_notifications`), `corex-submissions`
(`corex_manage_submissions`), `corex-data-models` and every `corex-page-*` option page — a false
statement made at HTTP 403 to somebody trying to work out why they were stopped. A unit test
asserted the string was present, so the assertion was holding the falsehood in place; it is the
third time in this project a test has been the reason a defect survived.
Scope: the true value is genuinely unavailable, not merely inconvenient — `add_submenu_page()`
records a refused page in `$_wp_submenu_nopriv` as a bare `true` and discards the capability, so it
is gone before any CoreX code runs. Restating it from a second map beside the screens' own
declarations is what would drift; the ability, by contrast, comes from `requestAbilityFor()`, the
same resolution the submission handler performs, so the sentence and the queued request cannot
disagree.
Status: Final.

## #189 — The user manual is a registry, not a document
Date: 2026-07-28
Decision: The dashboard user manual ships as `addons/corex-guides`, an add-on owning a public
`GuideRegistry`. CoreX registers its own guides through it; a site plugin registers its own through
the same API and they render on the same screen. Spec 082, which designed the manual as Markdown in
a docs tree, is superseded.
Why: a client's guide is about the client's site — their post types, their flows — so it ships with
their plugin, versions with their plugin, and has to appear without anybody editing CoreX. A
Markdown file in `docs-app/` can express CoreX's manual and can never express Perego's. 082 also
left "where does the manual live" as an open decision and asked in its own US3 where somebody would
look; for a person handed a finished site the answer is wp-admin, not a documentation website they
were never told about.
Scope: what 082 got right is kept — the content rules (name the control, state the expected result,
warn before anything hard to undo) and the screenshot discipline (captured by a script driving the
real admin, one regeneration command, loud failure on a missing screen). Registration is
`registerDeferred()` rather than `register()` because CoreX and a site plugin both boot on
`plugins_loaded` at priority 10 and the winner depends on the plugin's directory name; resolving on
first read removes the race rather than documenting it. Sections order alphabetically by key, which
is deliberate: a separate section-order registry would be a second thing to register, get wrong, and
disagree about. Availability needs no `is_active()` check anywhere — an add-on registers its guides
from its own provider, so an inactive add-on contributes nothing by construction.
## #190 — A reported defect is a hypothesis until the tree is read
Date: 2026-07-28
Decision: Every item in issues #148, #149 and #150 was re-verified against the current tree before
any code was written. Two turned out not to need work: #150's "Correction to #138 item 3" (reply-to
still null) is stale — `EmailStudioSubmissionGateway::reply()` calls `replyToAddress()`, and the
reporter's own later comment on #149 says so — and #149 item 1b was already fixed by spec 080.
Neither was "fixed" again.
Why: this is the third round of production reports and the second time verification changed the
work. Spec 080 found #138 item 2 already solved by spec 074 and would have added a second deferral
mechanism beside a working one. A report is evidence that something looked broken to a careful
person at a point in time; it is not a statement about the tree in front of you, particularly when
the tree has moved since.
Scope: the corollary matters as much. #149's own report shows the modal rendering em dashes, but on
the current tree it renders "This record has no readable fields" — spec 080's better empty state
made the same bug read as a true statement about the record instead of as a failure. **A fix that
improves an error message can make an unrelated defect harder to see**, so a report's *symptom*
ages faster than its *cause*.
Status: Final.

## #191 — Two halves of a data-loss fix ship together or not at all
Date: 2026-07-28
Decision: `collect()` reading `selectedOptions` and `sanitizeShape()` gaining a list arm are one
change, in one commit, with one test file covering both.
Why: a `<select multiple>` stored only its first selected value, because `el.value` on a multiple
select is the first selected option. The obvious one-line fix — send the real list — makes it worse:
every arm of `sanitizeShape()` maps to a scalar sanitizer and `sanitize_text_field()` returns `''`
for an array, so the field is blanked entirely. Storing one of three answers is bad; storing none of
them, indistinguishable in the inbox from a visitor who answered nothing, is worse.
Scope: the test asserts the wrong outcome explicitly (`not->toBe('')`) rather than only the right
one, because that is the failure a future refactor of the sanitize shape would reintroduce, and it
is silent.
Status: Final.

## #192 — A protected file is protected by a capability check, not by a deny file
Date: 2026-07-28
Decision: uploads land in `uploads/corex-private/` with Apache and IIS deny rules, and the only way
to read one is `AttachmentDelivery` — an `admin-post` route that verifies a nonce and then
`current_user_can()` before reading a byte. The deny files are defence in depth; the route is the
guarantee.
Why: a `.htaccess` is a promise about server configuration that CoreX cannot verify. nginx ignores
it entirely, and plenty of hosts disable `AllowOverride`. Shipping only deny rules would mean the
protection of somebody's CV depended on a file the framework writes and never checks the effect of.
Scope: deliberately not signed URLs with an expiry — a signed URL is a bearer token that ends up in
browser history, a referrer header and a forwarded email, and the viewer is already logged in, so
asking who they are is cheaper and does not leak. Deliberately not `X-Sendfile`/`X-Accel-Redirect`,
which are faster and hand the file back to the web-server configuration this exists because we
cannot trust. `Content-Disposition: attachment` always, so a visitor-supplied SVG cannot execute on
the admin's own origin. The route refuses any id it did not itself store (`_corex_protected`), or it
would be a general file reader.
Status: Final.

## #193 — Validate the descriptor, then store; never store, then clean up
Date: 2026-07-28
Decision: `FormSubmissionService` validates uploaded descriptors in place — `mime` and `max_size`
read the temporary file — and only stores once the whole submission has passed. A form with two file
fields whose second upload fails gives the first one back.
Why: FR-005 says a refused submission leaves no stored file. The alternative — store first, delete on
rejection — is correct only for as long as nobody adds an early return between the two, and the
failure it produces is silent: bytes in a protected directory with no record pointing at them, which
no retention sweep can find because nothing knows they are there.
Scope: the same ordering in `ApplicationService`, where the row is written only after the file is
down. A row pointing at an id that does not resolve is indistinguishable, later, from the `0` that
issue #138 item 8 was about.
Status: Final.

## #194 — A file input's value is a lie, and Content-Type must not be set for FormData
Date: 2026-07-28
Decision: `corex-runtime.js` skips file inputs in `collect()` and switches the request body to
`FormData` only when the form actually carries a file; every other form keeps sending JSON.
Why: two independent reasons uploads could not work, neither mentioned in issue #138. `el.value` for
a file input is `C:\fakepath\name.pdf` — the browser's deliberate lie — so the file never reached
`collect()` at all. And `viaFetch` set `Content-Type: application/json` unconditionally, which
overrides the multipart boundary the browser generates and produces a body the server cannot parse.
Scope: scoped to forms that carry a file because this is a build-free asset enqueued on every page
with a form, and switching everything to FormData would have been a smaller diff and a much larger
change — every endpoint, test and theme currently expects JSON. `collect()` is now exposed on
`window.Corex.forms` so a theme reads a form the same way the runtime does; the two disagreeing is
the class of bug the multi-select defect was.
Status: Final.

## #195 — A framework a site cannot safely reach is not extensible
Date: 2026-07-28
Decision: `Boot::booted()` is public, `Boot::boot()` fires a `corex_booted` action, and
`Corex::onReady(callable)` is the documented way for a site plugin to reach the container. The
guides documentation now tells sites to wrap their registration in it.
Why: found by standing up a plugin that followed the published documentation exactly. CoreX boots on
`plugins_loaded` at priority 10 and the site starter this framework *generates* boots there too, so
which one WordPress runs first depends on the plugin's directory name. The loser called
`Corex::make()`, reached `Boot::app()`, and got a `RuntimeException` — not a silent no-op but a
fatal, on every request, taking the whole site down. And it could not be guarded against:
`app()` throws rather than returning null, so `Boot::app() === null` — the check a developer
naturally writes — *is* the crash.
Scope: `onReady()` covers both orderings, so there is no ordering left for a caller to get wrong;
that is the point, rather than documenting the hazard and expecting care. Perego had independently
worked around it by deferring to `init`, which is evidence the trap is real and that the workaround
is something each site has to discover for itself. Spec 084 shipped a registry whose deferred
resolution solved the *registry* race while leaving the *boot* race open — solving half a problem
and documenting the half that remained.
Status: Final.

## #196 — An acceptance matrix measures; it does not photograph
Date: 2026-07-28
Decision: `capture-denied-acceptance.mjs` asserts `scrollWidth <= clientWidth` in all 16 cells and
exits non-zero on any overflow. Four corners are photographed for design review; the other twelve
are measured only.
Why: the defect this repository keeps meeting is a **one-pixel** horizontal overflow, still open on
`corex-access`. No screenshot review has ever caught one, and asking a person to spot a pixel across
sixteen images is asking them to fail. The screenshots are for judging whether it looks right; the
measurement is for knowing whether it fits.
Scope: the record states plainly what the RTL cells do *not* prove. They force `dir="rtl"` onto
English strings, so trailing punctuation lands at the visual left — a property of the fixture, not
the product. Recording that is what stops somebody later "fixing" correct code, and what makes clear
that Arabic typography still needs an Arabic catalogue and its own pass.
Status: Final.

## #197 — A translation key is not a label, and both are carried
Date: 2026-07-28
Decision: `NotificationAction` gains an already-translated `label` beside `labelKey`, and
`toArray()` emits both. The client reads `label`.
Why: the server serialized `label_key` and the card read `item.action.label`, so every
server-produced action fell through to a hardcoded "Open" and no authored label had ever been seen.
The obvious fix — rename one side — is wrong: `labelKey` *is* a translation key, nothing in the
pipeline resolves keys to strings, and renaming it would have made the payload claim to carry a
label while carrying a key. Adding the resolved string is the honest shape; the key stays for a
resolver that may exist one day.
Scope: the Jest test that "covered" this fed `{ label: … }` — a payload shape the server has never
sent — so it passed against fiction while every real notification was broken. It now builds its
fixture from what `present()` actually emits. A fixture invented to match the component is not
coverage of the component.
Status: Final.

## #198 — A permission the client is trusted to honour is not a permission
Date: 2026-07-28
Decision: `WpNotificationRepository::present()` omits an action from the payload entirely when the
actor does not hold its declared `ability`, and derives `view` / `needs_action` from the action that
survived that check.
Why: `NotificationAction` had documented since spec 072 that "a link renders only when the actor
passes the optional `ability`", and nothing enforced it — the whole action went out on the wire and
the card rendered on `action.url` alone. Two consequences, and the second is the worse one: a viewer
could be handed a link to a screen that would refuse them on arrival, and a row could be filed under
"Action needed" on the strength of an action that viewer would never be offered.
Scope: withheld rather than hidden. A payload the client is trusted to conceal is a disclosure with
a style rule in front of it. Producers now name the ability the destination screen itself enforces —
so the notification and the screen cannot disagree about who may go there.
Status: Final.

## #199 — CoreX answers wp-admin's focus ring instead of out-specifying it from inside `:where()`
Date: 2026-07-28
Decision: explicit `:focus` resets for `.button`, `.button-primary` and `.components-button`, each
paired with a `:focus-visible` outline at matching specificity. The `:where()` rule stays as the
floor for everything else.
Why: the blue halo after every click was `.wp-core-ui .button:focus` at **(0,3,0)** beating
`.corex-admin :where(a, button, …):focus-visible` at **(0,2,0)** — `:where()` contributes zero, so
the rule meant to answer it never could. wp-admin paints on `:focus`, not `:focus-visible`, which is
why a mouse click triggered it at all. CoreX's own `.button-primary:focus` had overridden the
background, the border and the colour and never the `box-shadow`, so the ring survived underneath
the brass button.
Scope: both directions are asserted in a browser, because removing a click ring without keeping the
keyboard one trades a cosmetic complaint for a WCAG 2.4.7 failure. Also added the missing base
`.components-button` rule: only the variants were styled, so a `<Button>` with no variant kept
Gutenberg's own `#1e1e1e` ink — invisible rather than unstyled on the dark surface, and the reason
the submissions close X and the inbox pager could not be seen.
Status: Final.

## #200 — The Guides support form depends on no optional plugin
Date: 2026-07-28
Decision: `addons/corex-guides` ships its own two-rung `SupportMailer` — a nullable
`Corex\Mail\Mailer` injected by the provider, falling back to `wp_mail()` — rather than importing
`Corex\Forms\Submission\NotificationDispatcher`, which is the same ladder one plugin over.
Why: `NotificationDispatcher` lives in `plugins/corex-forms` and `Mailer` is bound only by
`addons/corex-email`. Both are optional, and Principle IX forbids either becoming a hard dependency.
A help form is the last thing that should stop working because a site does not use forms.
Scope: the cost is one small class. `NotificationDispatcher` depends only on core `Corex\Mail\*`
seams and belongs in `corex-core`; promoting it is the right fix and is deliberately **not** in this
spec, because it is a refactor across two plugins and this is a help form. Recorded here so the next
person meets the decision rather than the duplication.
Status: Final.

## #201 — A test that cannot fail reports coverage it does not have
Date: 2026-07-28
Decision: every browser assertion in `tests/e2e/admin-controls.spec.js` was checked against the
unfixed stylesheet before being kept, and the spec states which assertions reproduced a defect and
which are only guards.
Why: four of them passed against the broken CSS on the first attempt, for four different reasons,
and each would have shipped as evidence of a fix it had not verified. The versioned stylesheet was
served from cache, so the "before" run measured the "after" file. "The first visible button" on that
screen is the notification bell — a control CoreX styles itself, which never had a WordPress ring to
lose. `document.activeElement` after clicking the pager is `<body>`, which has no shadow, so the
assertion measured the document. And the ring assertion was written as
`shadow === 'none' || shadow.includes('inset')`, which accepts wp-admin's
`rgb(56,88,233) 0 0 0 2px, rgb(255,255,255) 0 0 0 1px inset` — the exact broken value it existed to
reject.
Scope: the honest result is asymmetric and is recorded as such. Five assertions reproduce a defect
and now pass; four are guards that were already green — the drawer close already cleared 3:1, the
detail glyph was legible on a light surface because the defect was dark-only, and the keyboard ring
had to keep working. Claiming nine verified fixes would have been the same category of error as the
bugs this spec closes.
Status: Final.

## #202 — One repository URL, and the one that mattered was `Update URI`
Date: 2026-07-28
Decision: every repository reference — `composer.json`, `theme/style.css`, four plugin headers and
two docs pages — now names `github.com/MustafaShaaban/corex`.
Why: twelve references said `bseit/corex` while `docs-app` and the release links said
`MustafaShaaban/corex`. Eleven of those are cosmetic. The twelfth is not: `Update URI` is how
WordPress decides where a plugin's update comes from, so this was a functional defect shipping in
every installed copy — and it had been there long enough to be invisible.
Scope: found while auditing the repository for public release, not by a bug report, which is the
argument for the audit. A URL that appears in a header is not documentation; it is configuration that
happens to look like documentation.
Status: Final.

## #203 — A public repository states what it has not built
Date: 2026-07-28
Decision: `PROJECT-STATUS.md` at the repository root lists every module as stable, partial or
planned, names what is missing for anything not stable, and cites the file that records each gap.
Mirrored into the docs site as the second sidebar entry, above Getting Started.
Why: the information already existed and was scattered across ROADMAP §15/§17, the
"open, worth picking up" block in `PROGRESS.md`, 24 entries in
`.github/dependency-security-policy.json`, and three exclusions in `tests/e2e/playwright.config.js`.
A developer evaluating this cannot be asked to assemble that, and a project that leaves it assembled
only in its own head reads as one that has not looked.
Scope: placed *above* Getting Started deliberately. Somebody deciding whether to adopt a framework
should meet its limits before its tutorial. The 24 dependency advisories are listed rather than
resolved — fixing them is behaviour-changing upgrade work under its own policy and review dates, and
burying that inside a documentation diff is the kind of thing this file exists to prevent.
Status: Final.

## #204 — The docs guard found three false numbers in the document announcing our accuracy
Date: 2026-07-28
Decision: recorded rather than quietly fixed.
Why: `PROJECT-STATUS.md` and the rewritten `README.md` were written to argue that this project's
claims are checkable. The docs-guard pass over them found that `specs/` holds 86 directories and not
40, `DECISIONS.md` holds 201 entries and not 200, and `PROGRESS.md` is 420 KB and not 424 KB — three
numbers written from a glance rather than from a count, inside the two files whose entire purpose is
that they can be checked.
Scope: this is the same failure the last four specs each closed one instance of, arriving one level
up — in the documentation rather than in the code. It is written down because "we verified the docs"
is worth exactly as much as the verification, and the verification is what caught it. Also removed: a
paragraph promising entries the page did not contain, and a source type cited in an introduction and
never used.
Status: Final.

## #205 — The release command now stamps everything its docblock already promised
Date: 2026-07-29
Decision: `wp corex version` also stamps `package.json`, `README.md`, `ROADMAP.md`,
`PROJECT-STATUS.md` and the docs-site status mirror, using patterns anchored to the sentence that
declares the *current* version.
Why: the command's docblock said the version-bearing files are stamped together "so none of them
drifts from the release tag", and five files carrying a version were outside its reach and bumped by
hand. That is exactly how `ROADMAP.md` came to sit three releases behind a correct `README.md` — the
drift spec 088 had just spent a session correcting by hand, caused by the tool whose purpose is to
prevent it. Found while cutting the release that shipped 088.
Scope: anchored patterns, not a bare version search. `ROADMAP.md` §17 and the entire `CHANGELOG.md`
are statements about the past — "released as v0.38.1" is true and rewriting it would corrupt the
record rather than update it. Seven tests cover this, one of them specifically the
historical-reference case, and one asserting that a dependency pinned to the same version as the
package root is left alone.
Status: Final.

## #206 — `overrides`, because npm's "fix" was a downgrade
Date: 2026-07-29
Decision: the 24 bounded dependency exceptions are closed with npm `overrides` raising transitive
dependencies in place, not by taking the upgrade `npm audit` proposed.
Why: `npm audit` reported a fix available for every finding. For 37 of them the "fix" was
`@wordpress/scripts@19.2.4` against an installed `^33.0.0`, and `@wordpress/env@11.8.0` against
`^11.11.0` — **downgrades**, which `CONTRIBUTING.md` forbids. For the other 27 it reported
`fixAvailable: true` and `npm audit fix` moved none of them, because each was a transitive dependency
its parent pinned below the patched version. Neither of npm's two suggestions was usable, which is
the precise reason the exceptions existed — and the policy file had recorded it only as "a pinned
wp-scripts constraint".
Scope: `overrides` is the mechanism for exactly this and is neither `--force` nor a downgrade. One
override was backed out: `minimatch@^10` removes the CommonJS default export `eslint-plugin-jsx-a11y`
calls, and `lint:js` was the only check that caught it — the block build and all 431 Jest tests
passed with the broken linter dependency in place. That is why an override has to be proven by the
parent's own tooling rather than by the advisory count going down.
Status: Final.

## #207 — Two exceptions recorded as independent were one
Date: 2026-07-29
Decision: the Astro 7 migration landed as a consequence of clearing the root workspace, not as
separate work.
Why: the docs-site exceptions were held by a measured, specific packaging problem — regenerating
`docs-app/package-lock.json` makes npm expand the `corex-framework "file:.."` workspace root into the
docs tree, taking npm-docs from 5 findings to 17. That measurement was correct and its conclusion
was time-limited: the expansion only hurts while the root tooling is dirty. With the root at zero
there is nothing for it to drag in, and the regenerated lockfile contains no `webpack`, `jest` or
`eslint` at all.
Scope: worth recording because the policy file described the two groups as independent blockers with
separate review dates, and the cheapest route to closing six of them was to fix eighteen others
first. A blocker measured once stays in the record as a fact long after it has stopped being one;
re-measuring before believing it is the actual lesson.
Status: Final.

## #208 — Every check that always runs is required; the one that does not is not
Date: 2026-07-29
Decision: `main` requires all six pull-request checks — PHP unit, JavaScript, integration, Playwright
and both CodeQL contexts. `dependency-security` is deliberately **not** required. Reviews are not
required and admins are not enforced.
Why: five of the six ran on every pull request and could not block a merge, which is the state
DECISIONS #153 already recorded the cost of — GitHub renders "no checks" the same way it renders "all
checks passed", and a stack once sat queued for merge with eight real failures in it.
Scope: `dependency-security.yml` is paths-filtered to manifests, lockfiles and the policy. A required
check that does not run leaves a pull request pending **forever**, so requiring it would block every
change that touches no dependency — the check would be enforcing on absence rather than on failure.
Reviews and `enforce_admins` are off for the same class of reason: this is a single-maintainer
repository, and both would lock the maintainer out of their own `main` rather than add a reviewer.
Each is a deliberate omission with a stated reason, not an oversight, and `PROJECT-STATUS.md` says so
where somebody evaluating the project will read it.
Status: Final.

## #209 — Both remaining open items named the wrong cause
Date: 2026-07-29
Decision: the 1px RTL overflow is fixed in `corex-admin-shell.css` against WordPress's admin-bar menu
toggle, and the leaking access-request fixtures are fixed in `AccessRequestFormTest`'s `afterEach`.
Why: neither item was where it had been recorded, and in both cases the wrong attribution is what
kept it open for several releases.
- *"`corex-access` overflows 1px in RTL"*: bisecting the document lands on
  `#wp-admin-bar-menu-toggle > a.ab-item`, which carries `margin-left: -1px` beside a 1px left border
  — a border-overlap trick written for LTR — inside a left-floating `li`. In RTL that puts it at
  `left: -1`. It is WordPress's own markup and it happens on **every** CoreX screen; access was
  simply the one somebody measured. Six screens confirmed, RTL only.
- *"`clearPendingRequests` only clears the current requester's"*: that helper is correct and its
  comment explains why it deliberately spares other specs' rows. The 311 accumulated users came from
  `AccessRequestFormTest`, which created a subscriber per test and never deleted it.
Scope: the margin is flipped to the inline-end side rather than zeroed, so the overlap the rule
exists for still happens on the side it belongs on. `tests/e2e/rtl-overflow.spec.js` measures six
screens in both directions, and was verified to fail on all six RTL cases without the fix — a
single-screen test is precisely what allowed the mis-attribution to survive this long.
Status: Final.

## #210 — The base is applied at build time, not typed into 84 links
Date: 2026-07-29
Decision: a `rehypeBaseLinks` plugin in `docs-app/astro.config.mjs` prefixes root-absolute hrefs with
`base`; `tests/docs-links.test.js` asserts the built output; the CI Jest job builds the docs so that
test cannot skip.
Why: spec 090 added `base` and Astro rewrote what it owns — assets, and Starlight's `slug:` sidebar —
leaving 84 hand-written markdown links resolving off the base path. `dist/index.html` shipped
`href="/corex/project-status/"` from the sidebar and `href="/project-status/"` from the body, on one
page. Hardcoding `/corex/` into the content would have fixed the symptom while making
`COREX_DOCS_BASE` decorative and leaving the next author free to reintroduce it.
Scope: **the test is the deliverable; the rewrite is what makes it pass.** Spec 090 verified the
deployment against six URLs and every one was a form Astro rewrites — a real check that sampled only
the working half. It was confirmed failing (29 files) before the fix and passing after, because a
link test that has never failed proves nothing. The CI step matters as much: the test skips when
`docs-app/dist` is absent, which is right locally and worthless in CI, and a silently skipped check
is the condition that published 85 broken links.
Status: Final.

## #211 — Admin docs links point at the docs site, not at its Markdown source
Date: 2026-07-29
Decision: `DocsUrl`'s fallback is `https://mustafashaaban.github.io/corex` instead of a GitHub `blob`
URL into `docs-app/src/content/docs`.
Why: the GitHub fallback was correct when written — it guaranteed an absolute URL at a time when no
site existed, and an absolute URL is the load-bearing part (a relative one resolves against the
*client's* domain in wp-admin). A site has existed since v0.40.0, and until now an operator clicking
"Documentation" was handed raw Markdown with the front matter showing.
Scope: `hasConfiguredBase()` kept, with its docblock corrected — it no longer distinguishes "a site"
from "raw source" but *whose* site, which is what a team hosting their own needs. Two tests asserted
the old contract; both updated rather than deleted.
## #212 — The support email's rendering follows its transport
Date: 2026-07-29
Decision: `SupportMailer` sends a registered HTML template through the Corex Mail rung and plain text
through the `wp_mail()` floor, both composed from one `SupportMessage`.
Why: spec 087 sent one `"\n"`-joined plain-text body to both, with a docblock arguing plain text was
deliberate. That was right about `wp_mail()`, which sends no `Content-Type`. It was wrong about the
other rung: `WpMailDriver` stamps `text/html` on every message, so on any site with Corex Mail active
— most of them — the newlines collapsed into one run-on paragraph. **The email was broken before it
was unstyled**; the design is what the HTML rendering is for.
Scope: `SupportMessage` exists so the parts are assembled once. Two renderings built from the same
fields separately is how they drift, and the drift would be invisible — both emails still arrive.
Status: Final.

## #213 — The email palette is literal, and Layout's rule still stands
Date: 2026-07-29
Decision: `SupportEmailPalette` holds the admin's light-mode tokens as literal hex values.
Why: `Layout`'s docblock forbids exactly this — *"never design tokens — the brand values are
injected, not hardcoded"* — and it is right, for `Layout`, which wraps every site's mail and must
carry no opinion. Two things make it inapplicable to a template body. An email cannot read
`--corex-admin-*` (custom properties are not supported across mail clients), so a token must become a
literal somewhere; the only question is whether in one named place or scattered through markup. And
the brass identity is not in the brand pipeline at all — `Layout`'s injected accent is `theme.json`'s
`primary`, navy — so "use the CoreX design" and "inject the brand colour" name different colours.
Scope: the visible result is a **navy shell rule around a brass-edged card**, and that is stated in
the spec rather than hidden. Overriding `Layout` from one add-on's template would make one message
lie about the site's brand. If the two should match, the fix is migrating the brass into
`theme.json` — the direction `design/handoffs/brand-foundation.md` already approves — not a
special case here. Light values, not dark: dark-mode email support is unreliable, and dark-on-dark in
a client that ignores it is worse than light everywhere.
Status: Final.

## #214 — The guide a reader needs first is gated lowest
Date: 2026-07-29
Decision: `OrientationGuide` requires `read`, not a CoreX ability, and sorts first.
Why: every other guide answers "how do I do X" and assumes the reader already knows which screen
they want. Somebody handed a finished site meets thirteen unfamiliar menu words. Gating that
explanation behind `manage_options` would withhold it from precisely the person who needs it — a
contributor with two capabilities is *more* lost than an administrator, not less.
Scope: it documents the menu rather than any one screen, so it has no ability to inherit. The guides
it points at stay individually gated, so naming a screen in the orientation costs nothing: a reader
who cannot open Submissions still learns what Submissions is, and simply finds no guide for it.
Status: Final.

## #215 — The settings guide is asserted against the registry, on labels
Date: 2026-07-29
Decision: `SettingsGuideCoverageTest` fails when any field in `SettingsRegistry::sections()` is not
named in the guide, matching on the field's **label**.
Why: "every single input described" is a promise that decays the first time somebody adds a field,
and it decays silently — the guide still renders, the field still saves, and nothing says the two
disagree. On labels rather than keys because a reader looks for "Company name"; `brand.company_name`
appears nowhere they can see, so a guide passing a key check could still be useless to them.
Scope: it earned itself on the first run, failing on `captcha.action` and `media.webp.min_saving`
where the prose had dropped the parenthetical part of a label — "Minimum size saving" against the
screen's "Minimum size saving (%)". Small, and exactly the drift that makes a reader think a guide
describes a different screen. The related browser test was changed the same way: it asserted
`toHaveCount( 1 )` and reported "expected 1, received 3", a true statement about a number that says
nothing about whether gating works. Both now name what they mean.
Status: Final.

## #216 — The Help tab is wp-admin's chrome, and CoreX admin is not wp-admin

Date: 2026-08-05 · Spec: 097 · Status: Final

Spec 084 put each guide into the WordPress contextual Help tab of the screen it describes, reasoning
that the panel was a whole surface standing empty. That reading was fair and the conclusion was
wrong: the panel belongs to wp-admin, and every CoreX screen is a full-bleed product deliberately
built to hide wp-admin (`body.corex-admin-screen`, spec 067). The tab was the one piece of core
chrome that survived that, and it opened *above* the shell, pushing the entire product down the page.

Three things about how it was removed are decisions rather than mechanics.

**It is a removal, not a stylesheet.** `remove_help_tabs()` plus an emptied sidebar means
`WP_Screen::render_screen_meta()` never enters its help branch, so the link, the toggle and the panel
are never emitted. Hiding them with CSS would leave the markup, its scripts and its reserved height
in the page — the defect made invisible rather than absent.

**It runs on `admin_head` at `PHP_INT_MAX`, not on `current_screen`.** Deleting spec 084's
registration removes the tabs *CoreX* added and nothing else. `admin-header.php` fires `admin_head`
and only then renders the screen meta, so that is the last point which still beats the render and the
first which is later than every point at which help can be registered — including by core or by a
plugin CoreX has never heard of. A screen that is clean until somebody activates an unrelated plugin
has been tidied, not fixed.

**It lives in `corex-config`, not in the Guides add-on.** `corex-guides` is optional (Principle IX);
a removal shipped with it would hand the tab back the moment somebody deactivated Guides.

`Guide::onScreen()` was kept. The Guides screen already rendered the declared address as a link to
the screen it describes, which was always the better half of spec 084's idea — searchable, carrying
the steps, and one click from the menu. Only `GuideRegistry::forScreen()`, whose sole consumer was
the help tab, was removed rather than left as dead code.

## #217 — Three CoreX admin surfaces overran their own shell, and `overflow: clip` hid it

Date: 2026-08-05 · Spec: 097 · Status: Final

The two Playwright failures that blocked the v0.41.0 release read as unrelated — a click that
"intercepts pointer events" for sixty seconds, and a boolean overflow assertion at one viewport
width. They were the same mistake in three places: **a CSS length written as a preference and read by
the engine as a floor.**

- `grid-template-columns: minmax(10rem, 2fr) repeat(3, minmax(6rem, 1fr)) minmax(9rem, 1fr)` on the
  Forms catalog row — 37rem of track minimums the row could never go under.
- `repeat(auto-fit, minmax(16rem, 1fr))` on the Blog Pro workspace, which reads as "at least 16rem
  per column" and prevents the grid dropping to one column instead of causing it.
- `min-inline-size: 11rem` on `.corex-select`, in toolbars narrower than 11rem.

`.corex-admin__shell` carries `overflow: clip`, so none of this was visible: the content simply left
the box and stopped being painted. That is why the Forms failure presented as pointer interception —
`document.elementFromPoint()` at the row button's own centre returned the `.corex-admin` wrapper,
because the button had been clipped out of existence. **It was not a flaky click. The control was not
there.**

The fixes are `minmax(0, …)` where the ratio was the real intent, and `min(Xrem, 100%)` where the
floor was. Both let the length yield to a container narrower than itself, which is what the original
declarations meant. Neither assertion was changed, and both were measured before and after.

The general rule, worth keeping: **inside a clipping container, a minimum that cannot yield is not a
minimum, it is an overflow.** `overflow: clip` on the shell is correct and stays — it is what keeps
the product inside its own frame — but it means a containment defect is silent, so containment has to
be asserted rather than seen.

## #218 — The record is kept in six places, and each answers one question

Date: 2026-08-05 · Spec: 098 · Status: Final

`PROGRESS.md` reached 4,589 lines — forty stacked `RESUME HERE` blocks, 435 KB, append-only. Only the
top was ever read. Everything beneath it was already recorded somewhere better: what changed in a
release in `CHANGELOG.md`, why in `DECISIONS.md`, the agreed contract in `specs/`, and what happened
on a given day in `git log`.

Two earlier versions of this repository *defended* the size — `README.md` and `PROJECT-STATUS.md`
both carried a section explaining that 420 KB was "the method, not clutter". The defence was half
right. The method is the record; the transcript was not the record, it was a copy of it with the
provenance stripped.

So each document now answers exactly one question, and none answers another's:

| File | Question |
|---|---|
| `PROJECT-STATUS.md` | What works, what is partial, what is not built — today. |
| `ROADMAP.md` | What is planned, in what order, what is deferred. |
| `CHANGELOG.md` | What changed in each release. |
| `PROGRESS.md` | Where to resume: baseline, in flight, next. |
| `DECISIONS.md` | Why anything non-obvious is the way it is. |
| `specs/` | The contract, written before the code. |

`DECISIONS.md` is deliberately **not** trimmed. It is 4,111 lines because it is the one file whose
value is cumulative — a decision that turned out wrong is worth as much as one that did not, and this
entry is only meaningful beside the 215 before it.

Three consequences worth stating:

- **The four `COREX-*.md` working documents moved to `docs/internal/`**, so the repository root holds
  only the canonical set. This required a PATCH amendment to the constitution (1.2.1 → 1.2.2), whose
  source-of-truth hierarchy named `COREX-FRAMEWORK.md` by its old path.
- **The docs site no longer keeps a second, hand-written status page.** It was 62 lines against the
  root file's 123, with its own opening paragraph and its own version sentence. It is generated from
  `PROJECT-STATUS.md` by `scripts/sync-project-status.mjs` and held to it by a test — the same
  arrangement the token inventory has, for the same reason.
- **`tests/repo-hygiene.test.js` enforces the shape**: the root holds exactly the canonical set, no
  generated directory or archive or export is tracked, every prose version statement agrees with
  `package.json`, and no document announces a release for which no tag exists. That last check is not
  hypothetical — `PROGRESS.md` opened with "**v0.41.0 released**" while the newest tag was v0.40.0.
Status: Final.

## #219 — A patch pin on one action is not an upgrade, and the nightly E2E workflow never ran

Date: 2026-08-05 · Spec: 099 · Status: Final

Two decisions from the same pass over the repository's GitHub state.

**`github/codeql-action` stays on `@v4`.** Dependabot proposed `@v4` → `@v4.37.4`, which reads like a
bump and is a *narrowing*: `@v4` is a moving major tag that already receives every patch. Accepting it
would freeze a security scanner at one patch, make it the only action here not pinned to a major —
`checkout`, `setup-node`, `setup-php`, `upload-artifact` and `deploy-pages` are all `@vN` — and open a
pull request for every future patch, which is noise that teaches a reviewer to skim. The ignore is
scoped to minor and patch only, so `@v4` → `@v5` will still be proposed. It lifts if the repository
adopts SHA pinning for every action, at which point the inconsistency argument is gone.

**`.github/workflows/e2e.yml` is deleted.** It ran nightly and had failed every night for at least a
week, always at the same place:

> `global-setup could not authenticate the admin user. Tried: /wp-login.php, /corex-login/.`

It could not have passed. It set no `COREX_BASE_URL`, so Playwright's config fell back to
`http://corex.local` — a developer's WAMP host that does not exist on a runner — and no
`COREX_ADMIN_USER`/`COREX_ADMIN_PASS`, and it seeded none of the fixtures the specs need. It was
written before `ci.yml` had a browser job at all.

`ci.yml`'s **Browser tests (Playwright)** job supersedes it completely and is stricter in every
dimension: it provisions a real WordPress behind nginx, seeds every fixture, runs on **every pull
request** rather than nightly, and is a required check on `main`. Keeping a second, permanently red
workflow beside it does not add a safety net — **a red that is always red carries no information**,
and the habit of ignoring it is the thing that lets a real failure through.

## #220 — Eight dependency pull requests, tested rather than trusted

Date: 2026-08-05 · Spec: 099 · Status: Final

Eight Dependabot pull requests (#172–#179) were open. Seven are consolidated into one change and one
is closed with the hold above. What matters is how the two majors were decided, because a green
"Validate dependency advisories" check would have said nothing about either.

- **`@wordpress/components` 37 → 38.** Imported by more than twenty admin modules — the Data
  explorer, every dialog, the Submissions inbox, the block editor sidebars. A component library major
  breaks at *render* time, which no advisory scan and no unit test reaches. Verified against the
  build, Jest (442), both linters, and the full Playwright suite (144) driving those screens in a real
  browser.
- **`@wordpress/scripts` 33 → 34.** The build toolchain itself — webpack, babel, the lint configs and
  the Jest transform. Verified the same way, plus the produced bundles.

Both passed everything, so both land. Had either failed, it would have been closed with a precise
`dependabot.yml` hold naming the failure, not left open and red.

The general rule this pass confirms: **a green dependency-security check proves an advisory is
closed, not that the software still works.** The two are different questions, and only one of them
has a scanner.
Status: Final.

## #221 — CI installs WordPress at `latest`, and `main` runs nightly to find out what that did

Date: 2026-09-04 · Spec: — (pre-e-commerce cleanup) · Status: Final

`.github/actions/provision-wordpress` runs `wp core download --version=latest`, so a CI result here
is a function of two inputs and only one of them is in git. On 2026-08-31 that stopped being a
theoretical observation: WordPress went 7.0.4 → 7.1 and took two browser specs with it. `main` had
not been pushed to since 2026-08-05, so nothing re-ran, and the breakage was finally read off an
unrelated Dependabot PR three weeks later — where it looked like the PR's fault.

**Decision: keep `--version=latest`, and add a nightly `schedule:` trigger on `main` to `ci.yml`.**

Pinning core was the alternative and it is the wrong one. CoreX advertises WP 7.0+ in `README.md`
and in the plugin headers; a pin does not make that claim true, it makes it unfalsifiable. The
failure mode we had was not "core moved" — that is expected and is information we want — it was
"core moved and nobody found out for three weeks." A nightly fixes the part that was actually
broken.

Consequence, stated so it is not mistaken for a regression later: **`main` can go red without a
commit.** That is the design. A red nightly means current WordPress broke us, and it should be read
the same way as a red PR.

## #222 — The hidden-admin 404 fingerprint is measured, not tolerated by accident

Date: 2026-09-04 · Spec: — (pre-e-commerce cleanup) · Status: Final

`security-access.spec.js` asserted that a hidden `/wp-admin/` 404 and a URL that never existed were
within 5% of each other by byte count. It passed for months. It was passing by coincidence: two
opposite errors were cancelling, and neither was known until the failure message was made to
decompose the gap.

Measured against WordPress 7.1:

- **+25KB** — `corex-success-message`, `corex-flow`, `corex-subscribe`, `corex-cta-flow`,
  `corex-survey`, `corex-carousel`, `corex-form`, `corex-drawer` and `corex-tabs` inline styles,
  present on the hidden admin 404 and on no real 404.
- **−16KB** — `corex-navigation`, `wp-block-library` and `wp-block-search` inline styles, present on
  a real 404 and not on the hidden admin one.

Both follow from `wp_should_load_separate_core_block_assets()`, which returns false on `is_admin()`
*before* its own filter runs — the branch `LoginRouteGuard::dropAdminContext()` already documents as
unreachable. With it false every enqueued block style prints; with it true core prints only what a
rendered block asked for. CoreX cannot reach either side of that.

**Decision: there is no size assertion any more.**

That was not the first answer. The first was to compare the responses with the stylesheets excluded,
on the theory that what remained was one 404 template rendered twice. The measurement refused it:
7264B against 10606B, still 31% apart. These are not the same document with different CSS bundling,
and no threshold anybody can derive makes them one. A tolerance that cannot be derived is not a test
— it is a number that gets widened every time WordPress moves, which is exactly the history this
entry is about.

So the byte counts live here, with their measurements, and are asserted nowhere.

What replaces it asks the question a probe actually asks — not "how many bytes" but "does this look
like wp-admin". The hidden 404 and a real one must agree on `wpadminbar`, `adminmenumain` and
`load-styles.php`. That is answerable, it is what `dropAdminContext()` exists to guarantee, and it
would have caught the admin-bar markup the hidden 404 once served to logged-out visitors. The
"is it styled at all" half — the defect spec 069 actually found — is kept as its own floor against
the control's inline-style volume.

**The fingerprint is real and it is not closed by this.** A probe that fetches both URLs and looks
for `corex-survey-style-inline-css` can still tell them apart. It predates 7.1, and the old
assertion never caught it. It is recorded here rather than quietly dropped, because the honest
statement is "known, measured, structurally unreachable from here", not "within 5%". Closing it
needs a way to make core take the front-end asset path on an `is_admin()` request, and no such hook
exists today.

## #223 — Spec 100 T072 was not achievable, and no failing commit was manufactured to satisfy it

Date: 2026-09-04 · Spec: 100 · Status: Final

T072 asked that T067 — the integration test proving a network-activated add-on loads on every site — be landed
*first*, with its assertion failing against the `Boot` of the day, so CI history would carry a record of the
defect. Good instinct: the defect was invisible precisely because nothing could see it.

It could not be done. `Boot::activePlugins()` was deleted in T036 (commit `01409b4`), five commits before
Phase 8 was written. By the time the test existed, the state it was meant to fail against did not.

**Decision: skip it, and say so, rather than reconstruct the failure.** The alternative was reverting working
code onto the branch to produce a red run documenting a bug that same branch had already fixed — a knowingly
broken commit in the history, whose only purpose is to make a checklist item true.

The behaviour is not unverified. `ProviderActivationTest` asserts it against a real three-site network — a
network-activated add-on loads on all three, a site-activated one only on its own site — and T041 and T051
pin the same facts in Pest across every install shape. What is missing is a red CI run, which is evidence
about the *past*, not about the code.

The general rule: **when a task's premise expires because earlier work removed it, record the expiry.** Do not
retrofit the world so the checklist stays literally true.

## #224 — The dependency gate runs on every pull request, and is a required check

Date: 2026-09-04 · Spec: — (v0.42.0 stabilisation) · Status: Final

`dependency-security.yml` was filtered to `paths:` — the manifests, the lockfiles, the policy file
and its two scripts. That reads as an obvious economy: only a change to a manifest can change what
is installed.

It is wrong for this particular check, and the reason is what an advisory *is*. A dependency
vulnerability is published by somebody else, at a time nobody here chooses, against a dependency
tree that did not move. Between v0.41.0 and 2026-09-04 four were published; the gate reported FAIL
on `main`; nothing ran it. `PROJECT-STATUS.md` went on saying "Dependency advisories — none open"
for a month, and the failure was finally read off an unrelated Dependabot pull request. **The filter
meant the gate was consulted only by the pull requests least likely to be looking for it.**

The filter also made the check impossible to require. A required status check that never reports
leaves a pull request pending indefinitely, so requiring a path-filtered workflow would have hung
every docs-only change. That is why eight dependency pull requests sat at `MERGEABLE/UNSTABLE` with
a red gate and could have been merged anyway.

**Decision: remove the `paths:` filter.** It costs about four minutes per pull request. The weekly
schedule stays — a week with no pull requests is exactly the week an advisory lands unnoticed.

**Requiring it is deferred, and the evidence changed the plan.** On 2026-09-04 `npm audit` returned
unparseable output on three separate runs — npm-root on #196, npm-root again on #198 — each after an
8-10 minute hang, with the same endpoint returning `503 Service Unavailable` locally that morning.
All were `UNAVAILABLE`, not findings, and each cleared on a re-run.

A fourth failure that day looked identical and was **not** transient: npm-docs failed three times in
a row on the release branch and nowhere else, because `wp corex version` does not stamp
`docs-app/package-lock.json` and its stale `file:..` link to the root package made npm hang. That is
the distinction worth keeping — **an outage hits whatever runs next; a bug hits the same target every
time.** Two re-runs were spent before anyone checked which pattern it was. Failing closed on that is correct and stays. But a *required* check
that a third-party outage can fail blocks every merge in the repository until a human re-runs it,
which trades one silent failure mode for a loud one. The gate gets a bounded retry around each audit
invocation first — retrying only an unparseable or failed request, never a successful audit that
reports findings — and becomes required once it can survive a registry hiccup.

"Integration tests (real WordPress Multisite)" is added to the required set when the advisory gate
is, since it needs no third-party service and has passed on every run. Spec 100 makes Multisite a
supported configuration rather than a claim in the README, and a supported configuration whose suite
is allowed to fail is back to being a claim.

This is the third gate in this repository found not to be gating: the workflow that never ran
(spec 099), the stacked-PR CI gap that rendered "no checks" identically to "all checks passed"
(DECISIONS #153), and now a check nothing required. The pattern worth naming: **a gate is not the
script, it is the script plus the thing that makes failing it matter.**

## #225 — No command may unregister its neighbours

Date: 2026-09-09 · Spec: — (issue #201) · Status: Final

Under `composer install --no-dev` seven WP-CLI commands disappeared, `wp corex migrate` among them.
Not an error — absent. `wp help corex` still listed the namespace and still listed commands, so the
gap read as a release that never shipped them. A production install *is* a `--no-dev` install:
`scripts/build-shared-host-dist.mjs` tells the operator to run exactly that. A site deployed by the
documented path could not run its own schema migration, and nothing said so.

Two defects, and only the second is interesting.

`nikic/php-parser` was declared nowhere in `composer.json`. `ClassDocReader` uses it at runtime and
it arrived only as a transitive dependency of `pestphp/pest`, so removing dev dependencies removed
it. That is now a production dependency.

But the missing `require` line is only what pulled the trigger. `CliServiceProvider::boot()` built
every command eagerly, inline, in one straight-line method, so the first constructor to throw
aborted the method and took every command declared after it with it. A docs-only dependency was
therefore able to delete `migrate`. Commands registered *before* the throw survived, which is
precisely what made it invisible: the namespace still answered.

**Decision: registration is a lazy map, not a construction sequence.** `commandRegistrations()`
returns name → handler and builds nothing; each handler constructs what it needs when its command
runs. A missing dependency now fails loudly on its own invocation instead of quietly deleting its
neighbours. `MediaServiceProvider` had the same shape — two commands plus, behind them, the
`delete_attachment` cleanup and a job registration — and was converted before it could bite.

The WP-CLI help surface is what makes the media half non-obvious. WP-CLI derives `wp help` from the
callable's docblock, so swapping an object for a closure silently deletes the documented options.
Moving them into a structured `synopsis` is not cosmetic: **the flag name must not be translatable.**
WP-CLI parses the option syntax, so a translated `[--dry-run]` breaks argument handling in every
locale except the one anybody testing would be using.

The suite could not have caught any of this. `CommandRegistrationTest` asserted WP-CLI was *absent*,
so `boot()` returned early and the registration path was never exercised — and the dev dependencies
that hide the bug are the ones installed to run the tests. The regression test now builds the map
with a container that throws on every resolution and asserts all 26 commands still register, having
resolved nothing.

Third instance of the pattern in DECISIONS #224's closing line, in a new place: a check that cannot
observe the failure it exists to catch. Here the test asserted the precondition that guaranteed it
would see nothing.

## #226 — The advisory gate was red on `main` for a month, and said so every week

Date: 2026-09-09 · Spec: 089 · Status: Final

DECISIONS #224 removed the `paths:` filter so the gate would run on every pull request, on the
argument that an advisory is published by somebody else against a tree that did not move. That
argument was right, and this is the bill arriving.

The scheduled run on `main` failed on 2026-08-12, 2026-08-19, 2026-08-26, 2026-09-02 and again on
2026-09-09 against `8c1467c` — five consecutive weeks, the whole time `PROGRESS.md` said
"Dependency advisories cleared — the gate passes with one bounded exception" and
`PROJECT-STATUS.md` agreed. Both were true when written. Neither was re-read. **The gate was not
silent; nobody was listening.** It surfaced only because an unrelated pull request (#202) inherited
the red — the same way the WordPress 7.1 breakage surfaced in DECISIONS #221, and the same way the
#224 failure itself surfaced. Third time. The check that reports into a void is not a weaker version
of a check that blocks; it is a different thing wearing the same name.

One of the nine findings was **critical** — `astro` GHSA-26w7-cxv4-gfx2 in `docs-app`. It sat for
some part of that month unread.

**Decision: fix what has a fix, bound only what does not.** `astro` 7.1.5 → 7.3.2 (a minor inside
the existing `^7.1.5`, so the Astro-7 hold noted at the time is not re-litigated), plus `svgo` and
`colord`. That clears seven of nine, including the critical, and leaves `npm-docs` and `composer`
completely clean.

The remaining two get bounded exceptions because **no upgrade exists to take** — not because they
were inconvenient. Every published `extract-zip` is in range (latest 2.0.1), and `adm-zip` 0.6.0 is
simultaneously the latest release and inside `>=0.5.9 <=0.6.0`. npm's offered "fix" for both is a
major *downgrade* of `@wordpress/scripts` and `@wordpress/env`, which buys a moderate dev-only
advisory at the price of a materially older toolchain. That is not a fix, and taking it to make a
gate green would be the gate managing us.

`GHSA-7pqw-9j4j-h8q3` is the interesting one: a *second* advisory against the same `extract-zip`
2.0.1 on the same dependency path as the already-excepted `GHSA-jmr9-qjv8-65gv`. An exception is
written against an advisory ID, not a package, so an existing exception does not cover its own
sibling — correctly, since each is a separate argument, but it means a package can go on generating
new red without anything about the tree changing. Its `upstreamTrigger` says the two must be removed
together, so neither outlives the reasoning that justified it.

The `adm-zip` exception carries a condition rather than only a date: it rests on there being **no
caller** — there is no `.wp-env.json`, and `plugin-zip` is referenced by no script or workflow. If
either becomes false the exception has to be re-argued, not renewed. An exception whose premise can
quietly expire is how a bounded exception becomes a permanent one.

## #227 — Ten upgrades, one override proven against its parent, and one exception that needs an owner

Date: 2026-10-04 · Spec: 089 · Status: Final — merged as #212 on the owner's instruction, with the `braces` exception as written

#203 cleared every advisory known on 2026-09-09 and merged on 2026-10-04. On the day it merged the
gate on `main` failed again: 21 findings in the root npm workspace, 18 of them unbounded, and 8 in
the docs site, all unbounded. Nothing in the tree had moved. This entry records what was done with
each.

**Taken inside the ranges already declared, by lockfile update alone:**

| Package | From → to | Tree |
|---|---|---|
| `http-cache-semantics` | 4.2.0 → 4.3.0 | root and docs |
| `ip-address` | 10.7.0 → 10.7.3 | root |
| `moment` | 2.30.1 → 2.31.0 | root |
| `webpack-dev-middleware` | 8.1.0 → 8.3.0 | root |
| `devalue` | 5.8.2 → 5.9.4 | docs |

`webpack-dev-middleware` 8.3.0 raised its own floor on `memfs` (4.64.0 → 4.80.0, with its eight
`@jsonjoy.com/fs-*` packages), moved `glob-to-regex.js` to 1.3.1 and nested a `range-parser` 1.3.0.
Those are consequences of the upgrade, not separate choices.

**Taken through overrides that already existed, with the floor raised to the patched release:**
`brace-expansion` ^5.0.12, `fast-uri` ^3.1.8, `markdown-it` ^14.3.2 and `adm-zip` ^0.6.1. The lockfile
is what installs them; the floor is there so a regenerated lockfile cannot resolve back into an
advisory range.

**`adm-zip` 0.6.1 retires an exception.** DECISIONS #226 bounded GHSA-vwc7-r8mq-g2x9 because 0.6.0
was both the latest release and inside the range. 0.6.1 is outside it, and outside the six further
`adm-zip` advisories published since. The exception is deleted, not left to go stale — a stale
exception fails the gate, which is the gate doing its job.

**One new override, and it is a major: `basic-ftp` ^6.2.2.** GHSA-c475-qrg2-pj4r covers `<=6.2.0`
and is patched only in the 6 line. Its sole parent, `get-uri`, declares `^5` in the installed
6.0.5 and still does in its latest, 8.0.1, so no parent upgrade reaches the fix. DECISIONS #206 set the bar for this: an
override has to be proven by the parent's own behaviour, not by the advisory count going down. So
`get-uri` was made to do the one thing it uses `basic-ftp` for — fetch a file over FTP — against a
loopback server, once with 5.3.1 as a control and once with 6.2.2. Both returned the same bytes and
the same last-modified time. `get-uri` calls `access`, `lastMod`, `list`, `downloadTo` and `close`,
and all five worked under both. Nothing in this repository runs that chain
(`@wordpress/scripts` → `@wordpress/e2e-test-utils-playwright` → `lighthouse` → `puppeteer-core` →
`@puppeteer/browsers` → `proxy-agent` → `pac-proxy-agent` → `get-uri`), which is a reason to care
less about the advisory, not a reason to skip the proof.

**One finding has no fix, and bounding it is a judgement rather than a lookup: `braces`
GHSA-vfj7-8cjw-p6xm.** 3.0.3 is the latest release and the advisory, published 2026-09-18, covers
`<=3.0.3` with no patched version. It cannot be removed: it arrives through `micromatch`, which the
latest `fast-glob`, `stylelint` and `http-proxy-middleware` all require, so `@wordpress/scripts` 36
would install it too.

The policy forbids excepting a high finding whose exposure is `shipped-runtime` or `ci`, and
**`braces` runs in CI** — in `npm run lint:css` and `npm run build`. The two earlier exceptions in
the file are for a package that is installed and never executed; this one is not that, and saying
it is would be the convenient lie. It is classed `build-test-transitive` on the reasoning spec 056
applied to the `minimatch` and `brace-expansion` ReDoS findings, whose own exception text said they
were "exercised on developer machines and CI runners": on that reading the class describes where
the *input* comes from, not whether the code executes. The defect needs a deeply
nested brace pattern. Every `wp-scripts` invocation here is a bare command with directory flags, no
tracked file imports `braces`, `micromatch`, `fast-glob` or `globby`, and there is no custom
webpack, stylelint or dev-server proxy configuration. Supplying a pattern therefore means committing
to this repository's tooling configuration, which already grants code execution in the same job,
and the result is one crashed process.

Two things were checked rather than assumed. The lockfile does not mark `braces` dev-only, which
looks like a shipped dependency and is not: `@wordpress/theme`, installed beneath
`@wordpress/components`, lists `stylelint` as an optional peer. `@wordpress/components` is
externalised to the `wp-components` script handle, and a search of every workspace `build/`
directory finds no `braces`, `micromatch` or `stylelint` code.

If the owner reads `ci` as "executes in CI" rather than "reachable by untrusted input in CI", this
exception is forbidden by the policy as written and the gate cannot pass until `braces` publishes a
fix. That reading is defensible. It is recorded here so the choice is made deliberately and not by
whoever wrote the JSON.

**The `extract-zip` pair has a route out it did not have in September.** `@puppeteer/browsers` 3.x
dropped `extract-zip` altogether, and `@wordpress/scripts` 36 installs it. The upstream trigger on
GHSA-jmr9-qjv8-65gv now says so. It was not taken here: 34 → 36 is two toolchain majors with ESLint
10 and stylelint 17 inside them, and an override forcing `@puppeteer/browsers` 3 under a
`puppeteer-core` that pins 2.13.2 exactly could not be proven on a chain nothing here runs.

**Not folded in:** `@wordpress/components` 38 → 40 (#186), which still needs render-time
verification in a browser, and the `@wordpress/scripts` major above.

**A local result that looked like a failure and was not.** `npm run lint:css` reported 240,743
errors, every one of them in `wp-ms/` — the git-ignored multisite install that spec 100 added and
that `.stylelintignore`, `eslint.config.js` and `jest.config.js` do not exclude, though all three
exclude `wp/`. CI has no such directory. The linters and Jest were verified with it excluded on the
command line; fixing the three ignore lists is a separate change and is not in this one.

## #228 — Two of three browser flakes were one race; the third is a 500 with no name

Date: 2026-10-04 · Spec: none (browser-test helpers and CI) · Status: Final — merged as #217; the 500 it describes is still open

The browser job is a required check, and it failed three times on diffs that changed no runtime
code: the nightly on 2026-09-21 (`guides.spec.js`), #210 (`access-request.spec.js`, on a pull
request that adds a spec and its checklist) and #211 (`submissions-inbox.spec.js`, three tests).

**The sign-in failures were a race between WordPress and Playwright.** `wp-login.php` runs
`wp_attempt_focus()`: 200ms after the form renders it focuses the username field and selects its
contents. Playwright's `fill` is two steps — focus the field, then insert the text into whatever
holds focus. A timer that fires between them sends the password into the username field, over the
selected username. Both fields are `required`, so the browser will not submit the form: no request,
no error on the page. The screenshot from #210 shows exactly that — the password in the username
field and "Please fill out this field." under an empty password field.

Measured, not inferred: against WordPress's script verbatim, 1 fill in 120 went astray when timed to
the 200ms mark. With the focus moved in a microtask after the password field takes it, 60 in 60 did.

**A second defect turned a recoverable miss into a 60-second timeout.** After a login that stays on
`wp-login.php`, `signInAs` read `#login_error` with `innerText()`, which waits for the element to
exist and has no timeout of its own. With no error on the page it waited until the test's timeout
closed the browser. The three passes the helper makes — written for exactly this — never ran. Both
September and October failures print the same two lines: pass 1 "submitted and stayed", pass 2
"abandoned — the browser context was closed".

**What was decided.**

- *Check the form, then submit it.* `fillLoginForm` fills both fields, confirms each holds its own
  credential, and fills again if not. The timer fires once, so the second fill is clean. Waiting for
  the timer instead was rejected: it is an inline script behind a filter
  (`enable_login_autofocus`), and a helper that waits on it hangs wherever it is absent. Writing
  `.value` directly was rejected: the helper would stop using the form the way a person does.
- *Read a refusal that is there; do not wait for one.* `allInnerTexts()` in place of `innerText()`.
- *No `retries` in the Playwright config.* A retry would have turned all three failures green,
  including the one below, which is a fault in the product.
- *The helpers have their own spec.* `tests/e2e/helpers.spec.js` serves a login form through
  `page.route` and holds it in the state that is otherwise a few milliseconds wide. Each fix was
  removed in turn and its test failed. The first version of the race test did not: it moved the
  focus with a zero-delay timer, which arrives ahead of the text only 58 times in 60, and the
  helper's retry passes absorbed the rest.

**The third failure is open, and is not a test fault.** On #211, `GET corex/v1/flows` answered 500
"Request could not be processed." to three specs in a row. That body is `Pipeline`'s answer to any
`Throwable`. What is known:

- Nothing else was running. The other worker had finished, so no spec interfered.
- The seed in the test before those three had succeeded, creating and publishing the flow.
- `FlowRestGateway` answers `FlowConflictException` with a 409 and `DomainException` or
  `InvalidArgumentException` with a 422, and every `throw` on the list path is one of those. So
  this was a `Throwable` the flow code does not raise deliberately.
- The same branch passed the same specs four times earlier that day, and once after.

What threw is not known. `Pipeline` wrote the message to the PHP error log, and CI kept no server
log. Two changes make the next occurrence diagnosable instead of guessing at this one:
`seedSubmission` checks every answer and reports the step, status, code and message — it had
reported `Cannot read properties of undefined (reading 'flows')` — and the browser job now copies
nginx's error and access logs and the php-fpm log into the artifact it uploads on failure. The seed
is deliberately not retried: three failures across three seconds would have outlasted a retry, and
one that did not would hide the fault.

**The log collection did not prove itself, so it was made to.** On #217's own run the step copied
12,148 access-log lines, 10 php-fpm lines and an nginx error log that was empty. An empty file says
nothing about where a PHP message would have gone. The follow-up sets the pool's `error_log` to
`/var/log/corex-php-error.log`, sends one request through php-fpm that writes a known line, and
fails the job if that line is not in the file. A green browser job now means the route works.

Not re-examined: `helpers.js` blames the failures of 2026-09-03 and 2026-09-04 on the login address
moving, and `ci.yml` blames one on lockout. Their artifacts expired, so whether either was this
race cannot be checked.

## #229 — A library the build does not bundle cannot break at render time

Date: 2026-10-04 · Spec: none (dependency maintenance, under #220) · Status: Final — merged as #208

`@wordpress/components` 38.0.0 → 41.0.0 was held open across three Dependabot pull requests (#186,
#200, #208) for two reasons recorded on #186: npm had resolved 38.0.0 against a `^39.0.0`
requirement and reported success, and #220 says a component-library major "breaks at *render* time"
and so needs the screens driven in a real browser. This entry records what was found when the hold
was tested, because the second reason turned out not to apply to this repository.

**The resolution failure is gone.** #208's lockfile resolves 41.0.0 against `^41.0.0`, hoisted to
the root `node_modules`. Why #186 resolved 38 against `^39` was not diagnosed: the peer ranges are
the same on 38.0.0 and 41.0.0 (`react`, `react-dom` and `@types/react` at `^18 || ^19`), so the
guess on #186 that a peer conflict caused it is not supported by anything seen here.

**No workspace bundles this library.** None of the six build scripts passes a webpack config, so
`wp-scripts build` applies its default dependency extraction: an import of `@wordpress/components`
compiles to a read of `window.wp.components`, and the script's `.asset.php` lists `wp-components`
as a dependency for WordPress to enqueue. What renders in wp-admin and the block editor is the copy
the installed WordPress ships. The npm package decides what Jest renders against and nothing a
browser loads.

**So the proof is the build output, not a browser run.** The tree was built twice from a clean
`npm ci`, at #208's base (`672461f1`, 38.0.0 installed) and at its head (`414f8a8d`, 41.0.0
installed), with the installed version confirmed by `npm ls` before each build. All 169 files under
the six workspaces' `build/` directories have the same SHA-256 on both. A browser given either build receives
the same bytes.

What was run, and where:

| Check | Where | Result |
|---|---|---|
| Build, seven webpack compilations | export of `414f8a8d`, local | compiled, no warnings |
| Build output, 38.0.0 against 41.0.0 | same export, local | 169 of 169 files identical |
| Jest | same export, local, both versions | 54 suites; 441 passed, 2 skipped |
| Jest | CI, `414f8a8d` | 442 passed |
| Playwright, Chromium | CI, `414f8a8d` | 141 passed |
| Linters, both integration suites, advisory gate, CodeQL | CI, `414f8a8d` | all eight checks green |

Two limits on that table. The browser run is CI's; none was made on a workstation, because the
local install was in use by another session's browser tests and identical output left nothing for a
local run to find. And CI's browser suite excludes three tests on a fresh install, two of them
block-editor tests (`tests/e2e/playwright.config.js`) — the editor being where this library is used
most. The file comparison covers them, since their bundles are among the 169; the browser run does
not.

**What the bump does change.** Forty-six source files in seven packages import the library, and
the Jest tests that render them now do so against 41.0.0 while production renders whatever version
WordPress ships. That gap existed at 38.0.0 too and is not measured here. It is the real cost of
moving this package, and the reason to keep it near the version the supported WordPress carries
rather than at the newest release.

The rule #220 stated was right about the question and wrong about the mechanism for this package:
**for a dependency the build externalises, compare the build output across the bump. Identical
output closes the render-time question; only a difference needs a browser.**

## #230 — A client site is defined by what it owns, and the framework is everything else

Spec 102. The framework told a team to build a client site under `sites/<client>/` and then
rejected that layout in four places of its own — repository hygiene, both linters and Jest — each
found by a client, in the client's repository, and each worked around by editing a framework file.
An edit to a framework file is a conflict waiting in every later update, which is the opposite of
what a client needs from a framework it has to keep updating.

**Ownership is a short list of client paths, and the framework is the complement.** The client
repository had been verifying its updates with a `git diff` over a list of framework directories.
A list is wrong in the direction that matters: what is missing from it is unchecked, and the
repository root was missing, which is how a 17 MB archive was committed there during one update.
`.github/repository-ownership.json` names two client patterns — `sites/**` and
`.github/workflows/site-*.yml` — and everything else is the framework's. A new framework directory
is covered the day it is added.

**Which repository this is defaults to "the framework's".** An explicit role, then what CI reports,
then the `origin` remote, then framework. A repository that cannot say what it is gets the rule
that forbids `sites/`. Inferring "client" from the presence of a baseline record was rejected: it
would let a client site be committed to the framework by committing its record with it.

**The check is a Node script, not a `wp corex` command.** It needs git and nothing else, and a
client's CI should not have to provision WordPress and a database to compare files.

**A stale exception fails.** The update procedure says to delete a local patch once upstream has
it. A list nobody is made to prune becomes a list of things nobody remembers the reason for.

**Three things the plan got wrong, and the work corrected.**

- *The plan linked client sites into CI's WordPress.* The provisioning action runs
  `wp plugin activate --all`, so the framework's integration and browser suites would have run
  with a client's plugin active. Dropped: nothing in the spec needs CI's WordPress to load one.
- *`client-site-layout` was given the schedule condition.* In a client repository it would have
  generated a second site with a different baseline on every pull request, and the check would
  rightly have refused the pair. It is confined to the framework's repository on every event.
- *"Linked, not activated" was not true of the setup script as first written.* `--all` switched a
  linked client plugin on at the next re-run, and the Corex theme was activated unconditionally,
  switching a client's site back to it. Both were found by running the script against a real
  install, after a harness for the discovery logic had passed.

**Two defects the work found that were not in the spec.**

- `wp corex make:site --path=<dir>` could not run. `--path` is a WP-CLI global and never reaches
  the command; the README, three guides and the readiness report all documented it. The site
  directory is `--dir`, by the owner's decision, and `client-site-layout` runs the documented form.
- Jest's `<rootDir>` ignore patterns silently stop applying on Windows in a checkout whose own
  path holds a dot-directory, because Jest leaves a backslash before a dot alone when it rewrites
  separators. That is every agent session worktree. A unit test that substituted `<rootDir>`
  itself passed while Jest ran a failing test under `sites/`; the test now asks Jest for its
  resolved configuration. The older `<rootDir>/wp/`, `/wp-ms/` and `/dist/` entries keep the
  weakness and are not this spec's to change.

**Owner settings, not files.** Making `client-site-layout` a required check is a branch-protection
setting. It passed on its first run and on each run since; it is not required yet.

**What is still unproven.** SC-001 asks for a site generated at one release and updated to the
next. The next release does not exist. It was rehearsed in a scratch repository with tags standing
in for releases — no conflict, and nothing under `sites/` changed but the baseline record — and it
is proved for real the first time a client takes the release after this one.

## #231 — A test that counts the install's rows fails once the install has rows

Date: 2026-10-04 · Spec: none (test maintenance, follows #221) · Status: Final for the tests; the retention defect it found is open

Five integration tests failed on a long-lived development install and passed in CI. The integration
suite boots the real `./wp`, so "the database" is a developer's site. This entry records what each
failure was, what the tests were doing to that site, and one production defect found on the way.

**The five failures were three assumptions.**

- *A fixed key is unique.* `ProductDataPrivacyTest` and `SubmissionsControllerTest` filter by
  `flow => 90` and expect a total of 1. Flow 90 is only a number; the install held 23 submissions
  in it. Each test now gives its submissions a submitter address minted for that test and adds it
  to the query as a search term. The alternative — deleting every flow-90 submission before the
  test — was rejected: nothing says flow 90 is not a real form on somebody's site.
- *A fixture is on the first page.* `ProductActivityCoverageTest` asked for an actor's hundred
  newest events and looked for fixtures dated 2026-07-10. The export tests record under the same
  actor id, 7, and had left 131 newer events. The actor and outcome queries are now scoped to the
  window the fixtures are seeded into, as the area query already was.
- *A bounded batch reaches the new record.* The retention test backdates a submission, prunes with
  a 30-day window and expects that submission anonymized. See the defect below.

**The cleanup was the leak.** `ProductDataPrivacyTest` and `SubmissionsControllerTest` deleted
"whatever is among the newest 500 ids now and was not before". That misses a row backdated past
the 500th — the retention test's aged submission, left behind on every run once the install held
that many — and it deletes anything else that arrived meanwhile, which on a shared install
includes another session's test run. Both now delete what they created: ids they hold, or, where a
listener creates the post and returns nothing, ids recorded from `wp_insert_post` as this process
inserts them. `SubmitLifecycleTest`, which cleaned up nothing, does the same.

**An export is four records.** `SubmissionExportService::request()` writes the export, a job row,
an event on the install's scheduler and an audit event. The test deleted the first. The scheduler
then ran the job against a missing export and recorded a failure; the audit events are the 131
above. All four go now.

**The retention test was pruning the developer's site.** `prune('anonymize')` acts on every
submission older than the window. With the window set to 30 days, each run anonymized whatever the
install held that was older — 602 submissions there carry `corex_retention_state = anonymized` —
and a failed expectation skipped the line that restored the option, leaving the install on a 30-day
window. The test now narrows the retention query to its own two records through `pre_get_posts`
and restores the raw option in `afterEach`. Narrowing through a hook depends on that query staying
filterable, so the preview count is asserted to be exactly 1 before anything is pruned: with the
narrowing removed the test fails there (`500 is identical to 1`) and the prune does not run. That
was checked by removing it. Calling `applyIds()` with the one id was the alternative; it would have
stopped testing that the window selects the record.

**Fixture-keyed deletes clean up after earlier runs.** `DataManagementControllerTest` and
`ProductMutationSecurityTest` delete audit events by a data-source key that exists only in those
files. The first run on the development install removed 292 such events left by earlier runs.

**The defect: anonymize and archive retention stop making progress at 500 records.**
`SubmissionRetention::oldIds()` selects the newest `RetentionSettings::MAX_PRUNE` (500) private
submissions older than the window, whatever the action and whatever was done to them before.
`trash` removes a record from that set. `anonymize` and `archive` leave it private, so the next
prune is handed the same records, applies the action to them again, and counts them as removed
again. Once 500 handled records are newer than the unhandled ones, no later run reaches those. On
the development install the query returned 500 ids, all 500 already anonymized, while five live
submissions past the window were never selected. Below 500 the effect is quieter: the count a
prune reports, and the count the Inbox preview shows, include records already handled.

It is not fixed here. The fix has to decide what the preview counts when it does not yet know the
action, and whether an anonymized record can still be archived or trashed — product questions —
and this change is tests only. It is recorded in `PROGRESS.md` under "Open, and not hidden". The
test that exposed it no longer does, because it no longer runs retention across the install; the
fix needs its own test, and the cheap one is that a second prune of the same record returns 0.

What was run, on the install the failures were reported from:

| Check | Result |
|---|---|
| The three failing files, unmodified, retention test excluded | 4 failed, 7 passed |
| Production retention query for a 30-day window, read-only | 500 ids, 500 already anonymized |
| The three files, changed, twice | 12 passed; posts, options, table counts and scheduled events identical before and after |
| Retention test with the narrowing removed | fails at the preview; install identical before and after |
| Whole integration suite, changed, twice | 359 passed |

**Not done.** The two Access tests leave eight audit events per run, and `AccessControllerTest`
leaves the editor role allowed to manage forms; their request rows are being removed on another
branch (#223), so that cleanup follows it rather than conflicting with it.
`DataManagementControllerTest` still finds its posts by comparing the newest 500 ids. What else the
suite still changes — three Notifications files delete every notification on the install — is
listed in `PROGRESS.md`. Rows earlier runs left on existing installs are not removed.

## #232 — Retention skips what its action has already done, and "due" means still holding personal data

Date: 2026-10-04 · Spec: none (defect fix, found in #231; spec 068 FR-056) · Status: Final

`SubmissionRetention` handed every run the newest 500 private submissions older than the window,
whatever the action and whatever had been done to them. Trashing takes a submission out of that
set. Anonymizing and archiving leave it private, so the next run got the same records, applied the
action again — a new title, another timeline event, a new update time — counted them as handled
again, and, once 500 handled records were newer than the unhandled ones, never reached an older
submission that still held personal data. #231 measured it: 500 ids selected, all 500 already
anonymized, live submissions past the window never selected.

The fix is a selection rule, and the rule needed three product answers. They are these.

**1. What "due" means before an action is chosen.** `preview()` takes no action, and its count is
the "currently due" figure on the Submissions screen, which also decides whether the apply form is
shown. A submission is due while it is older than the window and has not been anonymized — that
is, while it still holds the personal data the window exists to limit. An anonymized submission is
not due. An archived one is: archiving keeps every submitted value.

**2. What each action selects.** The due submissions it still has something to do for:

| Action | Skips | So it acts on |
|---|---|---|
| Archive | archived, anonymized | due submissions not yet archived |
| Anonymize | anonymized | every due submission, archived or not |
| Move to trash | anonymized | every due submission, archived or not |

For the same marked-test choice, every action's set is the preview's set or a subset of it, so a
run never acts on a record the count did not include. That was the class's stated contract ("only
what the preview measured") and it still holds. The one way past the count on the screen is the
"Include marked-test submissions" box, which was already so: the count there excludes marked tests
and the box adds them.

**3. Whether an anonymized submission may still be archived or trashed.** By retention, no.
Anonymizing finishes retention for a record: what is left is the non-personal workflow evidence the
operator chose to keep by anonymizing instead of trashing. Two alternatives were weighed.

- *Let trash take anonymized records too.* Then either the count includes them — the defect, a
  count of records nothing needs doing to — or it does not, and "5 currently due" followed by
  "505 moved to trash" breaks the preview contract. A screen that offers trashing anonymized
  records as its own counted choice would be honest; it is a feature, not this fix.
- *Treat archived as finished as well.* Archive is the first option in the action list. Had
  archived records left the count, one run of it would have emptied the count, hidden the form,
  and left every archived submission holding its personal data with no retention action able to
  reach it. The Inbox's bulk actions do not anonymize or trash.

Archiving an anonymized record was never useful: it overwrote `corex_retention_state` with
`archived`, which made the record look un-anonymized to the next anonymize run.

A single anonymized submission can still be trashed through the Data source
(`SubmissionsSource::delete()`); there is no bulk route. That is the cost of answer 3 and it is
accepted.

**Oldest first.** A run is still bounded at `RetentionSettings::MAX_PRUNE`. It now takes the
submissions that have been due longest (`post_date` ascending, then id) instead of the newest. With
the skip rule either order makes progress; this one handles the longest-overdue first.

**Where the rule lives.** `RetentionSettings` holds one map from action to the states it skips, and
the list of valid actions is that map's keys. `SubmissionRetention` turns the states into a
`NOT EXISTS OR NOT IN` meta clause beside the existing marked-test clause. The store methods
(`anonymizeForRetention()`, `archiveForRetention()`) are unchanged: `applyIds()` is their only
caller and `prune()` is its only caller outside tests, so a second guard there would have been the
same rule written twice.

**What an operator sees change.** On a site with anonymized submissions the due count drops by
that number. Move to trash no longer trashes anonymized submissions, Archive no longer touches
archived or anonymized ones, and runs go oldest first. Retention still runs only when someone
confirms it on the Submissions screen; nothing is scheduled. `CHANGELOG.md` carries this under
"Client impact".

**The tests.** `tests/Integration/Retention/SubmissionRetentionTest.php`, six tests on real
WordPress, written before the fix. Five failed on the unfixed code for the reason each names; the
sixth (trash still takes an archived submission) guards the other direction and was checked by
making trash skip archived records, which fails it. The suite runs on a developer's install, so
every retention query in the file is narrowed to the test's own submissions through
`pre_get_posts` — to none until one is inserted — and each test asserts the preview count before
it prunes. Two unit tests that only restated the action map were written and removed again: every
entry in the map is exercised through `prune()`.

What was run, on the long-lived development install:

| Check | Result |
|---|---|
| The new file against unfixed code | 5 failed, as expected; install identical before and after |
| The new file against the fix | 6 passed; posts, post meta, options and table counts identical before and after |
| Retention, Privacy, Submissions and Data integration tests | 45 passed |
| Unit suite | 1,853 passed |
| The fixed selection for a 30-day window, read-only | 7 ids, all without a retention state; none of the 602 anonymized submissions |
| The same query before the fix (#231) | 500 ids, all anonymized |

The local runs loaded this branch's classes over the root checkout's `vendor/` and `./wp` through a
prepended autoloader, so the shared checkout stayed on `main`. The full integration and browser
suites are CI's.

**Not done.** The retention form's confirmation label and its result notice say "trash" whichever
action ran ("Confirm moving due submissions to the recoverable trash", "N submissions moved to
trash"). That predates this change and is listed in `PROGRESS.md`. Submissions the old selection
anonymized twice keep their duplicate timeline events.

## #233 — The retention form says which action it ran, and never guesses

Date: 2026-10-04 · Spec: none (defect fix, noted at the end of #232; spec 068 FR-056) · Status: Final

The retention form on the Submissions screen offers three actions — Archive, Move to trash,
Anonymize personal data — and spoke about one. Its confirmation box read "Confirm moving due
submissions to the recoverable trash." and its result notice "N submissions moved to trash.",
whichever action was selected. An operator anonymizing submissions, which deletes the submitted
values and cannot be undone, was asked to confirm a recoverable move and then told the records were
in the trash. #232 found it while fixing what retention selects, and left it.

**1. The confirmation is about the selected action, and states the one consequence that is
permanent.** The label is now "Confirm applying the selected action to the due submissions.
Anonymizing cannot be undone." It is one fixed sentence, not one per action. A label that followed
the select would need a script written for this server-rendered form, and would change under a box
the operator may already have ticked. The old label's only statement about consequences
was "recoverable", which was false for one action in three; the new one says nothing about Archive
or Move to trash being recoverable, because the form does not need the claim, and says the true
thing about the action that is not.

**2. The action travels with the result.** `RetentionController::prune()` redirected with
`corex_status=retention-pruned` and `corex_count`, so the screen had no way to know what ran. It
now adds `corex_action`, the action it handed to `SubmissionRetention::prune()`. That call throws
on anything but the three actions, so a redirect that carries an action carries one that ran. The
handler's default when the form posts no action is `trash`, as before, and that is what it reports.

**3. The screen does not trust the address bar, and does not guess.** `corex_action` is read with
`sanitize_key()` and matched against `archive`, `trash` and `anonymize`. Each has its own sentence
with its own plural: "N submissions archived.", "N submissions moved to trash.", "N submissions
anonymized." Anything else — an action somebody typed, or none, on a link saved before this change
— gets "Retention applied to N submissions.", which names no action. Falling back to "moved to
trash" there would have been the defect again for exactly the links that used to carry it. The
trash sentence is the old string unchanged, so an existing translation of it still applies; the
other three and the confirmation label are new strings (`CHANGELOG.md`, "Client impact").

The three actions are now named in three places: the retention rules, the form's options and the
notice. The first two were already separate. #232 made the list of valid actions the keys of a
private map in `RetentionSettings`, from action to the states it skips. The form and the notice
each need words per action, which that map does not hold, so they stay written out and the map
stays private.

**Tests.** `tests/Integration/Submissions/RetentionPanelCopyTest.php` renders the confirmation and
the notice on real WordPress — each action in the singular and the plural, no "trash" in an archive
or anonymize notice, the neutral sentence for a missing or unknown action, and the two halves of
the label. It reaches `pruneForm()` and `retentionNotice()` by reflection, as
`OperationsModeControllerNoticeTest` does for its screen: `render()` prints the form only when the
install happens to hold due submissions. It reads and writes no submission.
`tests/Unit/Retention/RetentionControllerTest.php` drives the real handler with the real guard and
the real retention service, WordPress stubbed and retention off, and reads the redirect it issues.
`prune()` ends in `exit`; the `wp_safe_redirect` stub throws the destination, so the handler runs
up to the redirect and stops.

What was run:

| Check | Result |
|---|---|
| Both new files against `main`'s production code | 14 of 16 cases failed; the two that passed are the trash notices, which were already right |
| Both new files with the change | 16 passed |
| Unit suite (`pest`) | 1857 passed |
| `wp i18n make-pot` over the screen | the four plural strings extracted, each with its translator comment |
| The screen on the development install, dark and light, 1440px and 375px, and right-to-left in dark | no sideways scroll; the five notices read as above |

The render needed a retention window, which the development install does not have, so
`corex_retention_submissions_days` was set to 30 for the captures and deleted again afterwards. The
form was not submitted. The whole integration suite was not run locally — it still changes the
install it runs against (`PROGRESS.md`) — and is left to CI.

**Not done.**

- The notice for a run that was *not* confirmed ("Confirm the retention action before applying
  it") is drawn as a success state; every retention status gets the `success` tone. Recorded in
  `PROGRESS.md` under "Open, and not hidden".
- An action the service rejects reaches `SubmissionRetention` and its `InvalidArgumentException` is
  not caught by the handler. Only somebody who passes the capability and nonce check and alters the
  form's own select can send one. Read from the code, not reproduced.
- The counts are printed with `%d`, as the "currently due" figure on the same panel is, not through
  `number_format_i18n()`.
- At 1440px the confirmation and the Apply button sit on a second row of the form, below the action
  select. Whether the old, shorter label fitted on the first row was not measured.

## #234 — A test names the rows it wrote; it does not empty the table or act as the administrator

Date: 2026-10-04 · Spec: none (test maintenance, follows #231) · Status: Final for the tests; what a run still leaves is in `PROGRESS.md`

The integration suite boots the real `./wp`, so the tables it writes are a developer's. #231 fixed
the tests that failed there. This entry is about the tests that passed and changed the install
anyway, measured with a snapshot before and after a full run: post ids by type, the row count and
`CHECKSUM TABLE` of every prefixed table, option hashes, cron events, users and user meta.

**Three files emptied two tables.** `NotificationControllerTest`, `WpNotificationRepositoryTest`
and `NotificationPerformanceTest` began every test with `DELETE FROM` on the notification and
read-state tables, with no `WHERE`. A run removed every notification on the install and what each
user had read. The assertions depended on it: "the list has one item" is true of an empty table.

**The rows are found by a key of the test's own.** Each file stores under a dedup-key prefix no
producer writes (`controller.test:`, `repository.test:`, `performance.test:`) and deletes by that
prefix, before each test and after it. Before, because a run that dies leaves rows, and a repeat
of the same key merges into the old row instead of creating one.

**The assertions are scoped by who is asking, not by what is in the table.** Every notification
read is filtered by the actor, so the tests ask as actors nothing else on the install addresses.

- The repository test passes its actor as arguments. It uses two user ids no account has, and a
  capability check that holds one ability, `corex_notification_repository_test`. It used to act as
  users 7 and 8 holding every ability, which on a full table is every ability-targeted row.
- The controller test goes through REST, where the actor is whoever is signed in. It signed in as
  the first administrator, who sees everything. It now creates an account with no role, gives it
  one test-only capability, and deletes it afterwards. No role, because the install may grant
  CoreX abilities to any role. The alternative — keep the administrator and compare counts before
  and after — was rejected: the read is capped at the 500 newest rows, so on an install holding
  500 unresolved notifications one more does not change the count.

Two things followed from acting as the administrator and are gone with it. The preferences test
deleted that account's notification preferences and saved its own, so the administrator of the
development install has had the jobs category switched off by the suite. And the default fixture
key was `submission.new:contact`, which is what the submission producer writes for a form with
that slug. That did nothing while the table was emptied first; left alone, the fixture would
merge into the install's own row.

**Two operations have no scope to be given, so the test moves out of their reach.**
`pruneOlderThan()` removes whatever is resolved and older than a cutoff, and
`SecurityResetLoginCommand::restore()` releases every lockout active at a given moment. Neither
takes a filter, and adding one for a test would be production code. The prune test backdates its
row to 1999 and prunes before 2000; the reset test records its lockout in 2099 and resets as of
then. Only a row dated on purpose is on the far side of either line.

**Stopping the delete uncovered what it had been hiding.** With the tables left alone, three more
writers showed in the snapshot, and they are fixed here because this change is what exposes them.

- `FlowControllerTest` and `FlowLifecycleTest` submit to a flow, and the submission producer
  stores `submission.new:<slug>`. The slugs exist only in those files; each deletes its key.
- `CommandCenterWidgetTest` renders the widget, which evaluates readiness for real. The readiness
  producer then stores the install's own blockers under `readiness.blocker:<check>`, or raises
  the count and moves the date on the ones already there. Those rows are not fixtures and cannot
  be deleted afterwards: they may have been the developer's before the test ran. The test
  snapshots the rows under that prefix and restores them — a row that was there reads as it did,
  a row that was not is removed.
- The Access tests' notifications were the third; pull request #225 had already removed them.

**The rest of the list.** `CreatedPosts` (`tests/Support`) records post ids from the
`wp_insert_post` action for the types a test names and deletes them afterwards. It is the
listener `SubmitLifecycleTest` and `ProductDataPrivacyTest` got with #231, as a class, because
five more files needed it.

- Logged emails: `CallRequestDataPathTest` (2), `ApplicationDataPathTest` (2), `MailLifecycleTest`
  (3) and `SubscriptionLifecycleTest` (1) each send through Corex Mail, which logs a post.
- `DataManagementControllerTest` records its import, export and migration runs instead of
  comparing the newest 500 ids before and after.
- `BlogProControllerTest` deletes the reading events recorded against the post it created.
- `corex_kit_seeded_pages` grew by two ids a run. A trace of option writes named
  `SetupConflictTest`: `seedPages()` records each page it touches, and the test deleted the pages
  and left their ids. It snapshots and restores the option.

**One was found by checksum.** The login-attempt table held one row before a run and one after, so
a row count showed nothing. `LoginProtectionEnforcementTest` ran an unqualified `DELETE FROM` on
it around every test and deleted the login policy option without restoring it;
`LoginRecoveryTest` then left a lockout row. Both now find their rows by the address they record
against, which is in a range reserved for documentation (203.0.113.0/24), and the first puts the
policy back.

**Not migrated.** `SubmitLifecycleTest` and `ProductDataPrivacyTest` keep their inline listeners,
and `OptionalDashboardWidgetsTest` keeps its own delete by prefix. They work; rewriting them onto
the helpers is a separate change.

What was run, on the development install, from the root checkout:

| Check | Result |
|---|---|
| Full integration run on `main` at `5b424578`, before the change | 359 passed; 8 logged emails, 2 reading events, 2 seeded-page ids and a replaced login-attempt row left; the administrator's preferences row deleted and re-inserted. The Access tests' rows were also left, which pull request #225 has since fixed |
| Two consecutive full runs with the change, four stand-in notifications and their read state seeded first, a snapshot around each | 377 ran and none failed; 21 carried a deprecation notice raised by the tracing bootstrap's own `setted_transient` listener. The stand-ins were unchanged, row for row. Four new transients and one refreshed per run |
| A third run, with no stand-ins and no tracing | 377 passed; the same transients and no other difference |

The stand-ins used the keys the old tests collided with and were removed afterwards, as were the
rows the first run left. The 377 includes 18 integration tests `main` gained from pull requests
#228 and #229 during the work.

**Not done.**

- The transients: rate-limit counters from the three tests that submit a form, and one migration
  preview. They expire in 60 and 300 seconds. Recorded in `PROGRESS.md`.
- `FlowControllerTest` and `FlowLifecycleTest` still delete flows, submissions and Email Studio
  posts by comparing the newest 500 ids. Recorded in `PROGRESS.md`.
- Two sessions running the suite at once against one database were not tested. The fixed prefixes
  mean each would delete the other's fixtures mid-test; neither would touch the install's rows.
- Nothing puts back what earlier runs removed or changed.

## #235 — WP-CLI commands are registered on `cli_init`, and their help text stays translatable

Date: 2026-10-04 · Spec: none (defect fix) · Status: Final

Every WP-CLI request on a site with `WP_DEBUG` on wrote this to `debug.log`:

> Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the
> `corex` domain was triggered too early.

`CliServiceProvider::boot()` runs on `plugins_loaded` and called `commandRegistrations()`, which
builds each command's definition. `resetCommandDefinition()` translates its short description and
its four option descriptions, so `__()` ran before `init`. The log holds one notice per request,
for the first caller, which hid how many there were:

- `resetCommandDefinition()` — the one in the logged stack.
- `MediaServiceProvider::boot()` — two more definitions, written the same way, also built on
  `plugins_loaded`.
- `modeCommandDefinition()` on spec 101's branch (#210) — a fourth, not yet merged.

Fixing the CLI provider alone moves the notice to the next caller. Run that way, `wp corex doctor`
still logged it once, from `regenerateWebpCommandDefinition()`.

**Decision: both providers hand their commands to WP-CLI on `cli_init`.** WP-CLI fires that action
on WordPress's `init`, for plugins to add commands, and it fires nowhere else. By then a
translation is allowed, and the command has not been looked up yet: WP-CLI runs it after WordPress
has finished loading. `boot()` now only hooks; the definitions are built when the hook runs.

Three alternatives, and why not:

- *Untranslated English help text.* WP-CLI's own help is English, and no translation of these
  strings exists. But the Definition of Done says "no hardcoded user-facing text", #225 kept the
  descriptions translatable on purpose, and the `i18n:pot` script scans the files they are in. It
  would also have to be remembered at every new definition: #210 wrote its definition the way the
  neighbours were written.
- *`init` directly.* The same moment, but it fires on every request, so the handler needs its own
  "is this WP-CLI" check. `cli_init` is that check.
- *Definitions as closures.* Changes the shape `commandRegistrations()` returns, which
  `CommandRegistrationTest` and #210's tests read, for no gain over moving the call.

What did not change: the synopsis of every command, so WP-CLI still rejects a flag a command does
not take; the array `commandRegistrations()` returns; and that building it resolves nothing from
the container (#225). `modeCommandDefinition()` needs no edit — it is covered when #210 merges on
top of this, and the same patch applies to that branch without a conflict.

`boot()` no longer checks `class_exists('WP_CLI')` or the `WP_CLI` constant. Without WP-CLI the
hook is added and never fires.

**Tests.** Two unit tests, one per provider, stub `__()` to throw and call `boot()`, then assert a
callback on `cli_init`. Against `main`'s providers both fail on the hook assertion. With the hook
in place but a definition built inside `boot()`, both fail on the thrown translation, naming the
string. The media test silences one warning: Brain Monkey probes each hooked closure with
`Closure::bind()`, which warns for a static one, and `boot()` hooks several.

What was run, on the development install (WordPress 7.1.2, WP-CLI 2.12.0, `WP_DEBUG_LOG` on). Each
WP-CLI request wrote its PHP errors to a file of its own rather than the shared `debug.log`, which
other sessions write to; a "before" run on unchanged code is what shows the capture works.

| Check | Before | After |
|---|---|---|
| `wp corex doctor` | notice, stack through `resetCommandDefinition()` | no log output |
| `wp corex reset --dry-run` | notice | no log output |
| `wp help corex reset` | notice | no log output; options listed |
| `wp help corex media regenerate-webp` | — | no log output |
| `wp help corex` | — | identical to before, 93 lines |
| `wp corex reset --bogus-flag`, `wp corex media reset-webp --dry-run --nope` | — | "unknown … parameter", exit 1 |
| Spec 101's branch at `6e541962`: `wp corex mode get`, `wp corex doctor` | notice | no such notice |
| Same branch: `wp corex mode set production --acknowlege` | — | "unknown --acknowlege parameter", exit 1 |
| Unit suite (`pest`) | — | 1859 passed |
| `tests/Integration/Cli` | — | 5 passed |

The root checkout was not edited or switched for any of this. The `main` rows loaded the two
changed classes from the working tree ahead of the install's autoloader. The spec 101 rows pointed
`WP_PLUGIN_DIR`, for that one request, at an export of the branch with and without the patch.

**Not done.**

- Nothing in CI runs a WP-CLI command and reads the log afterwards, so a translation that returns
  to `plugins_loaded` from somewhere other than these two `boot()` methods would not be caught.
- The spec 101 runs still logged one notice, in both columns:
  `_wp_connectors_resolve_ai_provider_logo_url`, "Provider logo path must be located within the
  plugins or must-use plugins directory". The scratch plugin directory reached the AI provider
  plugin through a junction, so its real path was outside the directory. Not seen on the `main`
  rows, which used the install's own plugin directory.
- A provider booted after `cli_init` has fired would register nothing. `Boot` boots every provider
  on `plugins_loaded`, so there is no such path today.
- `tests/Integration/Cli` also holds `ResetExecutorTest`, which leaves `show_on_front` at `posts`
  and `page_on_front` at 0 without restoring what the install had. It ran here once. What the
  install held before was not recorded.

## #237 — A retention run that was refused is a warning

Date: 2026-10-04 · Spec: none (defect fix, left open by #233; spec 068 FR-056) · Status: Final

Applying retention on the Submissions screen without ticking the confirmation box runs nothing:
`RetentionController::prune()` redirects with `retention-confirm` before it reads the action. The
screen answered with "Confirm the retention action before applying it." in a success state, because
`SubmissionsInboxScreen::retentionNotice()` passed `success` for every status. A run that did not
happen was drawn like one that worked.

`retentionNotice()` now pairs each status with its tone. `retention-confirm` is `warning`; a saved
policy and a completed run stay `success`.

**Why warning and not error.** `OperationsSecurityScreen::statusNotice()` already answers the same
situation — a mode change submitted without its confirmation box — with `warning`, and keeps `error`
for a change that was blocked or invalid. Nothing failed here either; the operator left a step out.
`AdminPage::state()` gives a warning `role="status"`, as it gave the success, so what a screen
reader announces is unchanged.

**The sentence is unchanged.** It already says what to do, and keeping it keeps its translations.

This is numbered #237 because #236 is taken on the open spec 101 branch (#210).

What was run:

| Check | Result |
|---|---|
| The new refusal test against `main`'s production code | failed: the notice carried `corex-state--success` |
| `tests/Integration/Submissions/RetentionPanelCopyTest.php` with the change | 15 passed |
| Unit suite (`pest`) | 1859 passed |
| The notice on the development install: dark and light at 1440px, dark at 375px, light right-to-left at 375px | warning icon and border in each; no sideways scroll |

The whole integration suite and the browser suite were left to CI.

## #238 — Coming soon is a mode, and what a request gets is one table

Spec 101. Spec 063 named coming-soon as an operations mode; spec 065 shipped four modes and it was
not one of them, and the only trace was a unit test asserting it was invalid. Maintenance mode
could not stand in: it answers 503, which is right for minutes and wrong for the weeks before a
launch; its page is CoreX's; and only an administrator sees past it.

**A mode, not a second switch.** There is already one place an operator changes what the public
gets, and making it a mode means Coming soon and Maintenance cannot both be on. Launch is the
existing Production switch, with its readiness result and typed phrase. The cost is that a site in
Coming soon is not also labelled staging or production; the Overview reports the hosting
environment separately.

**The behaviour is a table, and the table is one pure function.** `ComingSoonDecision::for()` takes
the facts of a request and returns what it gets; it reads nothing. `ComingSoonGuard` is the only
class that asks WordPress who is signed in or what was asked for. The order of the rows *is* the
behaviour — the visitor view before the pass an editor gets, the sitemap before the home rule — so
every pair whose order matters has a test, and each was confirmed to fail with the rows swapped.
*Rejected:* a second mode inside `MaintenanceGuard`. Different status, audience and page, and its
tests had to keep passing unchanged.

**The default page comes from the plugin, not from the Corex theme.** A client theme generated by
`make:site` is a standalone block theme and inherits nothing from the Corex theme, so a default
shipped in `theme/templates/` would reach no client. `corex-config` registers a block template
named `coming-soon`; a theme file of that name replaces it. That precedence is WordPress's, so it
is pinned by a test against fixture themes that fails if a core release changes it.

**"Home" is the home path with no WordPress query variable on it.** The plan said the path alone.
`/?feed=rss2` has the home path, and WordPress renders a feed before it chooses any template — a
path-only rule would have served the unfinished site's posts to anybody who asked. Found by
writing the test.

**The preview link's secret is never stored, and the cookie is not the link.** The site keeps a
hash keyed with one of its own salts. A browser that opens the link is given a grant: an expiry and
a signature over the stored hash and that expiry. So a leaked cookie gives away one browser's
access and not the link; the fourteen days are enforced by the server; and regenerating, revoking
or leaving the mode ends every grant at the next request with no list of sessions to walk.
*Rejected:* a session table (state to expire for the same result), and a self-expiring signed
token with no stored state (cannot be revoked).

**A new link is shown by answering the POST, not by redirecting.** It is stored nowhere it could be
read back from, so the one moment it exists is that response. A redirect would mean putting the
secret in an address or parking it in the database for the next request to collect.

**One service changes the mode, for the screen and the command line.** `ModeChangeService` holds
the confirmation rules, so `wp corex mode set` cannot be a way round one, and it is the one place
that removes the preview link on leaving the mode — asked of the store before and after, because a
launch reaches the store by a different route from the other modes.

**The browser spec has a project of its own.** Turning the mode on changes what every signed-out
and subscriber request receives, and other specs drive both. It runs after the main project and
alone, and restores the mode it found. On its first run it found a defect the other suites could
not: the mode form's select still held the site's current mode after the form came back proposing
another, until a script moved it, so a fast or script-less submit applied the wrong mode.

Three things changed the spec while building and were approved by the owner on 2026-10-04: the
favicon passes like `robots.txt`; no sitemap is published when WordPress is set to discourage
search engines; and WordPress's `/login` and `/admin` shortcuts keep working.

## #239 — The retention handler refuses an action the form does not offer

Date: 2026-10-04 · Spec: none (defect fix, left open by #233; spec 068 FR-056) · Status: Final

The retention form's select offers three actions. `RetentionController::prune()` read the posted
value, ran it through `sanitize_key()`, and handed it to `SubmissionRetention::prune()`. That
method throws `InvalidArgumentException` for anything but `archive`, `trash` or `anonymize`, and
the handler did not catch it, so the request ended in WordPress's critical-error page. Only a user
who passes the capability and nonce check can get that far, and no record was touched — the
service checks the action before it selects anything. The operator still got a crash where an
answer was owed. #233 read this from the code; the new unit test reproduces it through the real
handler.

**The handler asks before it calls.** `RetentionSettings::isAction()` answers whether a value is
one of the three actions, and `assertAction()` now uses it, so the list of actions is still the
keys of one map. `prune()` asks after the confirmation check and before the service, and redirects
with `corex_status=retention-invalid` when the answer is no. The controller takes
`RetentionSettings` as a third constructor argument, which the container resolves with no new
wiring.

Catching the exception in the handler was the smaller change and was not taken. `prune()` also
runs a query and writes records, and a `catch (InvalidArgumentException)` around it would report
any such exception from that work as "not a valid action".

**The service still throws.** `SubmissionRetention::prune()` is public and its tests call it
directly. For a caller that is code, a wrong action is a programming error, and an exception is
the right answer to one.

**What the operator sees.** "That is not a valid retention action. Nothing was changed.", as an
error: `OperationsSecurityScreen` answers an invalid mode the same way. The redirect carries no
action and no count, because none ran.

**Unchanged.** A post with no action field still runs Move to trash, the handler's default as
before. A post without the confirmation box is still answered with the confirmation warning,
whatever action it carried.

The number was taken while spec 101 (#210) and the integration-suite cleanup (#236) were still open
branches; they landed first, as #238 and #240, and #236 is unused.

What was run:

| Check | Result |
|---|---|
| The new unit test against `main`'s production code | failed with the uncaught `InvalidArgumentException` |
| The new integration test against `main`'s production code | failed: no notice rendered |
| `tests/Unit/Retention` with the change | 15 passed |
| `tests/Integration/Submissions/RetentionPanelCopyTest.php` with the change | 16 passed |
| Unit suite (`pest`), after the rebase onto `main` past v0.43.0 | 2037 passed |
| The container builds `RetentionController` on the development install | yes |
| The notice on the development install: dark and light at 1440px, dark at 375px, light right-to-left at 375px | error icon and border in each, `role="alert"`; no sideways scroll |

The whole integration suite and the browser suite were left to CI.

## #240 — A test puts back the transients it writes; it does not delete them

Date: 2026-10-04 · Spec: none (test maintenance, follows #234) · Status: Final

This is numbered #240 because #238 went to spec 101 (#210), which merged first, and #239 is
taken on the open retention branch (#237).

#234 left two things open, both recorded in `PROGRESS.md`: a full integration run left five
transients on the install, and two flow tests still cleaned up by comparing the newest 500 ids.

**The transients.** Three tests submit a form, and every submission is counted by
`ThrottleMiddleware` in `corex_throttle_<hash>`. `DataManagementControllerTest` previews a
migration without applying it, and `WpMigrationPreviewStore` keeps the preview in
`corex_migration_preview_<hash>`. They expire in 60 and 300 seconds; the rows stay in the options
table until WordPress sweeps expired transients.

Deleting them afterwards was rejected. The counter for the `contact` form is keyed by form and
client, so it is the same key on every run: a run that starts inside the window finds a counter
already there, and deleting it would remove something the test did not create. `WatchedTransients`
(`tests/Support`) restores instead. It listens on the options API, which says what was there
before each write: `add_option` fires only for a row that does not exist yet, and `update_option`
hands over the value being replaced. Afterwards a transient that was absent is deleted, and one
that existed gets its value and its expiry back. The `set_transient` action was not used because
it fires after the write, when the old value is gone.

With a persistent object cache WordPress keeps transients in the cache and never touches the
options table, so the helper sees nothing and restores nothing. Not tested; the development
install has no object cache.

**The flow tests.** `FlowControllerTest` and `FlowLifecycleTest` use `CreatedPosts` (#234) for
their flows, submissions and Email Studio posts, in place of the before-and-after comparison.

What was run, on the development install, from the root checkout:

| Check | Result |
|---|---|
| A `contact` counter seeded at 1 with a 900-second expiry, then two consecutive full runs with a snapshot around each | 377 passed both times; the seeded counter's value and expiry unchanged |
| Differences between the snapshots | `_transient_doing_cron` added by the first run and gone after the second; nothing else |

`_transient_doing_cron` is the lock WordPress core takes in `spawn_cron()` when a scheduled event
is due. Loading WordPress does that, not a test, and no trace of it has a test file in its
backtrace.

**Not done.** `ResetExecutorTest` and the front-page options, an open item in `PROGRESS.md`
(DECISIONS #235), are not part of this change.

## #241 — A length rule counts characters; `max` and `min` keep both meanings

Date: 2026-10-06 · Spec: none (defect fix, reported from a client site on v0.43.0) · Status: Final

`RuleRegistry` registered `max_length` and `min_length` as the same two classes as `max` and
`min`. Those compare an answer that passes `is_numeric()` as a number and count the characters of
anything else. So a length rule did not measure length whenever the answer was all digits: a phone
field declared `max_length:32` refused `01016999700` as too long, and `12` satisfied
`min_length:3`. The stock contact form had the same fault under the other name: its message was
`max:2000`, and a message of `2025` was refused.

**Decided.** `max_length` and `min_length` are their own rules, `MaxLength` and `MinLength`, and
count characters whatever the answer holds. They fail with the keys `max` and `min`, so they share
the existing messages. The form runtime gains the same two rules; it had no arm for either, so a
length rule was checked only by the server. The stock contact form uses `max_length` for its name
and message.

**`max` and `min` are not changed.** Their two meanings are what they have always done, and a site
may bound a quantity with `max:10` and no `numeric` beside it; counting characters there would let
`99999` through. The alternative — compare as a number only when the field also declares
`numeric`, as Laravel does — is the cleaner contract and a behaviour change for every form that
has `max` or `min` on a field, so it is not made inside a defect fix. The rule classes and the
forms guide now say which rule to use for text.

What was run:

| Check | Result |
|---|---|
| Four of the five new cases and the contact-form case in `ValidatorTest`, before the change | three failed: `01016999700` refused by `max_length:32`, `12` accepted by `min_length:3`, `2025` refused as a contact message |
| The four mirrored cases in `corex-runtime-forms.test.js`, without the runtime change | two failed: no client arm existed |
| The unit suite | 2044 passed |
| `tests/Integration/Forms`, real WordPress | 35 passed |
| The JavaScript suite | 535 passed in 57 suites |

**Not done.** `Max`, `Min` and the two new rules cast an array answer to a string, as `Max` and
`Min` did before; a length rule on a multi-value field is not defined.

## #242 — The client's address is the nearest hop no trusted proxy vouches for

Date: 2026-10-06 · Spec: none (security fix; spec 068 FR-076, login protection) · Status: Final

With trusted-proxy mode on and the request arriving from a trusted proxy, `ClientIpResolver`
walked `X-Forwarded-For` from the left and returned the first address that was not a trusted
proxy. A proxy does not replace that header; it appends the address it saw to whatever arrived.
The left of the list is therefore written by the client. A visitor behind a trusted proxy who sent
`X-Forwarded-For: 198.51.100.1` was recorded as `198.51.100.1`, and login protection counts
failures by identity and network: a new invented address per attempt never reached the threshold,
and a chosen one put the failures against somebody else.

**Decided.** Read from the right. Skip entries that are trusted proxies; the first one that is not
is the client. An entry that is not an address ends the walk and the proxy's own address is used:
nothing to the left of an entry nobody could read is vouched for by anybody.

**What this asks of a site.** Every proxy between the visitor and WordPress has to be in the
trusted ranges. Before, a chain with an unlisted proxy in the middle resolved the visitor by
accident, because the unlisted proxy was never reached. Now that proxy is taken for the client.
That is the correct answer to "which address can be believed", and it is recorded under Client
impact because it changes what such a site sees.

**Not done here.**
- The form rate limit, the flow submission limit and the form challenge context read
  `REMOTE_ADDR` directly (`SubmitController`, `FlowSubmissionController`,
  `FormChallengeContextFactory`), so behind any proxy every visitor shares one allowance. They
  cannot use this resolver as it stands: it lives in corex-config under login protection and is
  configured by the login policy, and corex-forms does not depend on corex-config. Moving address
  resolution to corex-core with its own setting is a design change, not part of a security fix.
- A named header such as `CF-Connecting-IP`, which a CDN sets itself and a client cannot append to.
- Nothing documents trusted-proxy mode for an operator.

What was run:

| Check | Result |
|---|---|
| Three new cases in `LoginProtectionServiceTest`, before the change | all three returned the address the client wrote |
| `tests/Unit/Security` | 78 passed |
| The existing case (untrusted peer; one trusted proxy; IPv6) | unchanged and passing: with one honest entry, left and right agree |

## #243 — The contact template is the contact form's

Date: 2026-10-06 · Spec: none (defect fix, reported from a client site on v0.43.0) · Status: Final

`SendEmailListener` is the default notification for every code-defined form. It built one
`MailRequest` carrying both a template name, `contact-notification`, and a generated body listing
every submitted field. Which of the two is used depends on what sends it: `wp_mail()` sends the
body, and `RequestMailer` — CoreX Mail — renders the template whenever a name is set and never
reads the body. `ContactNotificationTemplate` prints a name, an email and a message. So with CoreX
Mail active, a form with any other field was emailed without it, under the template's subject,
"New contact form submission"; with CoreX Mail inactive the same form's email was complete.

**Decided.** The listener names the template only for the `contact` form, the form it was written
for. Every other form sends no template name, so every transport sends the generated table.

Dropping the template altogether was rejected: a site may have edited `contact-notification` in
Email Studio, and its contact form should keep that. Choosing by the submitted keys — use the
template when the submission has nothing but a name, an email and a message — was rejected as a
rule nobody could predict from outside.

A site that wants a designed email for another form already has the means: an Email Studio route
on `forms.<slug>.submitted` is tried before this listener's request is used at all.

What was run:

| Check | Result |
|---|---|
| New case in `SendEmailListenerTest`, before the change | failed: a `callback` form's request named `contact-notification` |
| `SendEmailListenerTest` | 5 passed |
| The unit suite | 2049 passed |
| `tests/Integration/Forms` and `tests/Integration/Mail`, real WordPress | 40 passed |

## #244 — A second October advisory pass: an override is tested where it can be, and two criticals are bounded

Date: 2026-10-07 · Spec: 056 (dependency security remediation) · Status: Final

The dependency gate passed on 2026-10-04 and failed on every pull request from 2026-10-06, with
nothing changed in this repository: thirteen advisories had been published against the same tree,
eleven of them on 2026-10-05 and 2026-10-06. It is not a required check, so four pull requests were
merged with it red before anybody acted on it (#244, #245, #246, #252; none touched a manifest).

**Closed by taking a patched release (six).** `npm audit fix`, never `--force`: `source-map-js`
1.2.2, `compression` 1.8.2, `proxy-addr`, `sharp` 0.35.5 and `smol-toml` 1.9.0. `shell-quote` had a
patched release inside the range its parent asks for and npm would not move it; its lockfile entry
was removed and resolved again, to 1.12.0. In `docs-app` the same command moved
`@astrojs/starlight` from 0.41.5 to 0.41.11.

**Closed by an override (two).**
- `lighthouse` `^13.5.0`. Six `@opentelemetry/instrumentation-*` packages were flagged
  (GHSA-qqmp-wf37-98f9), all beneath `lighthouse` 12 → `@sentry/node` 9. `lighthouse` 13 brings
  `@sentry/node` 10 and `puppeteer-core` 25, whose `@puppeteer/browsers` 3.x has no `extract-zip`.
  So the same override removed the package the two oldest exceptions were for, and both are deleted.
- `postcss-selector-parser` `^7.1.6` (GHSA-rj75-hqrm-r3gf), in the root and in `docs-app`. Five
  cssnano plugins and `@stylistic/stylelint-plugin` ask for `^6`, and no 6.x release has the fix.

**This reverses a refusal made three days ago, and here is why.** The `extract-zip` exception
recorded on 2026-10-04 declined to force `@puppeteer/browsers` 3 under the installed
`puppeteer-core` 24, because that pair "could not be proven on a chain nothing here runs". The
objection stands for that pair. The `lighthouse` override is at a different joint: everything
beneath it is the set upstream ships together, and the one mismatch is above it, where
`@wordpress/e2e-test-utils-playwright` 1.54 asks for `lighthouse` `^12`. Nothing here imports that
package. What can be checked was: the tree installs, and that package loads against `lighthouse`
13, including its `lighthouse/core/index.cjs` import. It has not been run, because nothing runs it.

**An override is tested before it is trusted.** `simple-git` `^4.0.2` was the obvious third
override: four advisories, two critical, all fixed in 4.x. It installed cleanly. Then wp-env's own
call sequence was run against it and failed on its first line — `SimpleGit is not a function`.
`@wordpress/env` 11.16.0 calls `require( 'simple-git' )( … )`, and 4.x exports an object. The
override would have turned a red gate green by breaking `npm run env:start`. It was removed.

**Bounded (five).** The four `simple-git` advisories and `sprintf-js`. `sprintf-js` has no patched
release, and its code is not reached: only `js-yaml`'s command-line entry point loads `argparse`.
The `simple-git` four run when a developer starts wp-env, on input that comes from `wp-env.json`
in this repository, and a person who can edit that file can already run commands through wp-env's
`lifecycleScripts`. Their exposure is recorded as `local-dev-server`, which is what the policy
requires for a critical finding to be excepted at all: no workflow uses wp-env and nothing under
`node_modules` ships. They are reviewed on 2026-11-30, a month before the others. Removing
`@wordpress/env` was not considered: it is a documented way to run CoreX.

**`npm dedupe` was run and thrown away.** It was meant to hoist `simple-git` back to where it sat
before the override was tried, and it changed twenty-five unrelated versions, ESLint among them.
`simple-git` stays nested under `@wordpress/env`; the exceptions name that path.

What was run, on the final tree:

| Check | Result |
|---|---|
| `npm run verify:dependencies` | PASS — Composer 0, docs site 0, root 6 findings under 6 exceptions |
| `npm run build`, compared file by file with a build from `main`'s lockfile | 169 files, byte-identical |
| `npm run lint:css`, `npm run lint:js` | clean |
| `npm run test:js -- --no-cache` | 535 passed in 57 suites |
| The unit suite | 2049 passed |
| `docs-app`: `npm run build`, compared with a build from `main`'s lockfile | 924 pages both times; every page differs because one stylesheet's hashed name changed, and that stylesheet differs by rules Starlight 0.41.11 adds |
| `wp-env --version` | 11.16.0; the CLI loads |

**Not done.** `wp-env start` was not run against the final tree; Docker is installed on the
machine this was done on and starting containers was not part of the task. The integration and
browser suites were left to CI. `@wordpress/scripts` 36, which would retire both overrides, is
still a separate toolchain upgrade (spec 056, US3).

## #245 — An email answer is judged as typed, not cleaned into an address and then accepted

Date: 2026-10-07 · Spec: none (defect fix, reported from a client site on v0.43.1) · Status: Final

The form and flow controllers build a sanitizer for each field from its type, and for `email` it
was `sanitize_email()`, run by `SanitizeMiddleware` before validation. That function does not
refuse anything. It removes the characters it does not accept and returns what is left:

| Typed | After `sanitize_email()` | What happened |
|---|---|---|
| `sal,ma@example.com` | `salma@example.com` | passed the `email` rule; stored and replied to under an address nobody typed |
| `josé@example.com` | `jos@example.com` | the same |
| `salma@@example.com` | `salma@example.com` | the same |
| `not-an-email` | *(empty)* | a required field answered `required`; an optional one was dropped and the submission accepted |

A comma for a dot is an ordinary typo, and the stock contact form was affected as much as a
client's. It has been so since the forms engine was written; no release introduced it.

**Decided.** An email answer is trimmed and then compared with what `sanitize_email()` would make
of it. If the two are the same, the address is one WordPress leaves alone and it is kept exactly.
If they differ, the answer goes to the rules as text (`sanitize_text_field()`), so the `email`
rule refuses it by name. The helper is `EmailAnswer` in corex-core's `Support` namespace, used by
both controllers.

**One case returns nothing.** Stripping markup can itself leave a clean address behind:
`<b>salma</b>@example.com` becomes `salma@example.com` as text. That is still not what was typed,
so the helper returns an empty string for it. A required field then answers `required`; that one
input keeps the old wrong message, which is the price of the rule that nothing the helper returns
is an address the visitor did not write.

**What it does not do.**
- It does not make the `email` rule and WordPress agree. The rule is PHP's `FILTER_VALIDATE_EMAIL`;
  an address with a quoted local part passes it and is altered by `sanitize_email()`. Such an
  address is now stored as typed, where it used to be stored altered.
- A field of type `email` with no `email` rule used to store a cleaned address or nothing, and now
  stores what was typed. The type never implied the rule, and it still does not. The senders in
  the forms and mail code check an address with `is_email()` before using one.
- `sanitize_email()` on posted input is in five add-on handlers as well: newsletter subscribe,
  bookings, careers applications, profile registration and the Guides support request. They were
  read and not changed here; each needs its own look at what its service does with a refused
  address. The helper is in corex-core so they can use it.

**A test had asserted the defect.** `FlowControllerTest` expected `not-an-email` on a required
field to be answered with `required`. It expects `email` now and says why.

What was run:

| Check | Result |
|---|---|
| Four new cases in `SubmitLifecycleTest`, before the change | three accepted with a 200 under the altered address; the fourth answered `required` |
| The new flow case in `FlowLifecycleTest`, without the flow controller's change | accepted with a 200 |
| `EmailAnswerTest`, ten inputs against WordPress's real cleaners | 10 passed |
| `tests/Integration/Forms` and `tests/Integration/Mail`, real WordPress | 55 passed |
| The unit suite | 2049 passed |

## #246 — An endpoint that needs an address takes it as typed or not at all

Date: 2026-10-07 · Spec: none (defect fix, issue #256; follows #245) · Status: Final

#245 fixed the forms engine and named five add-on handlers with the same fault. Each called
`sanitize_email()` on the posted address and handed the result to a service that validated it,
which by then it always was:

| Endpoint | What a mistyped address did |
|---|---|
| `POST corex/v1/newsletter/subscribe` | subscribed the cleaned address and sent it a confirmation |
| `POST corex/v1/bookings/request` | stored a call request, and its acknowledgement, under it |
| `POST corex/v1/careers/apply` | stored an application under it |
| `POST corex/v1/account/register`, `POST corex/v1/account/profile` | created or re-addressed an account |
| The Guides support form | sent the request with it as the reply address |

The registration case was run on the development install before the change: posting
`<name>,x@example.com` answered 200 `registered` and an account existed for
`<name>x@example.com`. The newsletter case answered 200 the same way.

**Decided.** These callers do not have rules to refuse an answer by name; they need an address or
nothing. `EmailAnswer::address()` returns the trimmed answer when `sanitize_email()` would leave
it unchanged, and an empty string otherwise. Every service here already refuses an empty address
with the reason it gives for an invalid one, so no service changed. `EmailAnswer::clean()`, the
forms engine's version, is now written in terms of it.

**Stricter than the services.** The services validate with `FILTER_VALIDATE_EMAIL`, which accepts
an address with a quoted local part; WordPress would store that address altered. `address()`
refuses it, so an account or a subscription is only ever made for an address that is stored as it
was typed.

**The Guides form** takes an optional reply address. A mistyped one is now dropped, and the
message says "no address given". Telling the person their address was not usable would be better
and needs a new state on that form; not done.

What was run:

| Check | Result |
|---|---|
| New REST test, newsletter subscribe, before the change | 200; the cleaned address was subscribed |
| New REST test, account registration, before the change | 200 `registered`; an account existed for the cleaned address |
| `EmailAnswerTest`, eight inputs to `address()` and ten to `clean()` | 18 passed |
| Integration: Forms, Mail, Newsletter, Bookings, Careers, Profile, Guides | 75 passed |
| The unit suite | 2049 passed |

**Not tested directly.** The bookings, careers and Guides handlers have no test that posts a
mistyped address to them: bookings needs a configured leader, careers a job and an upload, and the
Guides controller's test is headless. The bookings and careers services' refusal of an invalid address is
covered by their existing tests; the one-line change in each handler was read.

**Left as it is.** `SettingsSanitizer` and `OptionPageScreen` clean an address an administrator
typed into a settings screen, in the same way. The person who typed it sees what was saved.

## #247 — The mode panel says the current mode once, and describes a mode only when it is being proposed

Date: 2026-10-07 · Spec: none (remediation of the spec 065 / 077 / 101 panel, reported by the owner) · Status: Final

The owner sent a screenshot of the Operations mode panel on a site in Coming soon and called it
messy. Read against the code, five things were wrong in it:

1. **One fact, three or four times.** The mode's `detail`, the first of its `warnings()`, the
   selected block's `summary` and its first two `consequences` all described the same state.
2. **Two lists drawn as prose.** `corex-opsec__warnings` and `corex-opsec__mode-consequences` had
   no markers and no space between items, about 150 characters wide. The current mode's warnings
   could not be told from the selected mode's consequences.
3. **A row where a column was wanted.** Label, select, then the mode block beside the select, and
   the button wrapping back to the far side, nowhere near the confirmation that enables it.
4. **A confirmation of nothing.** With the select on the mode the site was in, the panel showed
   that mode's consequences and its acknowledgement beside a disabled button.
5. **The one real caution had no weight.** "Published content can still be read through the REST
   API" sat among lines that only restated the mode.

**Decided.**

- **The current mode** is its name, a pill that says in words that it is the current mode, and
  its one `detail` sentence. Under it are `OperationsMode::cautions()` only: the warnings that say
  something the detail does not. Coming soon has one, Staging has one, the others none. They are
  drawn as notices with an icon and the warning tone, and a hidden "Caution:" for a screen reader.
  `warnings()` is unchanged and still feeds the dashboard widget; it is written in terms of
  `cautions()` so the two cannot drift.
- **A block describes a proposal.** `ModeDisclosure::describe()` now separates `consequences` —
  what a switch changes for visitors and for people signed in, which is what the operator is
  agreeing to — from `reference`, how the mode works and how to leave it. A block is a heading
  ("Switching to …"), the summary, the consequences as a real list, the reference behind a closed
  "More about this mode", the confirmation, and the button directly under it.
- **The form is one column**, with what is read held to a reading measure and the divider left to
  span the card.
- **Selecting the declared mode proposes nothing.** No block, no confirmation; one line saying so,
  and with the script running the button is disabled. The button is enabled as rendered, because
  without the script it is the only way to propose a mode at all.

**Declared, not merely current — a defect found on the way.** The script disabled the button
whenever the selection equalled the current mode. But a site that inherits its mode from the
WordPress environment type has declared none, the screen tells it to "declare a mode", and
`OperationsModeStore` records that declaration as a change. So on exactly those sites, the button
was disabled for the mode they were most likely to declare. "Nothing to change" now means the mode
is declared and selected; an inherited mode's own block is offered, headed "Declaring …". The form
carries `data-mode-declared` for the script, and the browser tests read it, because a CI install
is undeclared and a developer's usually is not.

**Kept:** the `?mode=` path that makes the form work without a script, `disabled` inputs in hidden
blocks, the native select under the CoreX control, tokens and logical properties only. One CSS
rule is new in kind: a block is a grid now, and `display` set by a class outranks the browser's
rule for `hidden`, so `[hidden]` is restated for it.

What was run:

| Check | Result |
|---|---|
| New unit tests for `cautions()` and the `consequences` / `reference` split, before the change | failed: method and key did not exist |
| New integration tests in `OperationsSectionsTest`, before the change | 3 failed; 15 pass now, the four existing ones unchanged |
| New `operations-mode.test.js`, before the script change | 3 failed; 4 pass now |
| `tests/e2e/operations-security.spec.js` against the development install, mode declared | 15 passed |
| The same file's mode-form tests with the mode undeclared, as in CI | 5 passed; the install's mode was put back |
| Unit suite | 2056 passed |
| `lint:css`, `lint:js`, token inventory regenerated | clean |
| Rendered: Coming soon current; Development current; Coming soon and Production proposed (4 blockers); dark and light; 1280 and 782 wide | read on screenshots |
| "More about this mode" toggle height | 26px |

**Not done.** `tests/e2e/coming-soon.spec.js` was not run locally: it changes what the whole site
serves, and is left to its own CI project. The panel was not looked at in right-to-left; it uses
logical properties throughout, and the existing 375px / 200% / RTL overflow test covers the
overview tab, not this one. Maintenance has no caution of its own: "a 503 held for weeks tells a
search engine the site is failing" would be a true one, and is new copy nobody asked for.

## #248 — A client repository may delete two framework files, and two checks stay home

Date: 2026-10-07 · Spec: 102 (update-safe client sites), issue #239 · Status: Final

Spec 102's FR-004 kept the scheduled runs and the documentation deploy out of a client repository.
Creating the first real one from v0.43.0 showed four more things it inherits and cannot use:

| Inherited | What it did in a private client repository |
|---|---|
| `.github/dependabot.yml` | Four pull requests within minutes of the first push, against `composer.lock`, `package.json` and `docs-app/`; up to twenty at once, weekly. Each starts the whole CI. Merging one is drift. |
| `.github/CODEOWNERS` | Names the framework's reviewer for every path. |
| `codeql.yml` | Ran on every push and failed: code scanning is not available to a private repository without a paid plan. |
| `dependency-security.yml` | Failed on every pull request whenever an advisory was open against the framework's own lockfiles, which only a framework release can clear. |

**The two workflows** are given a condition, as FR-004's were. The advisory check runs in the
framework's repository only: nothing it reports can be acted on anywhere else. CodeQL runs there,
or in a client repository that is public, where code scanning works and the client's own code is
worth scanning. `tests/repository-ownership.test.js` holds both conditions to the ownership file.

**The two files cannot be given a condition.** Dependabot reads its configuration from whatever
repository it is in, and CODEOWNERS likewise. Three ways out were written up in #239:

- *Have each client record them as exceptions.* Works with no change, and the exception never
  goes stale, so it is permanent bookkeeping in every client for something every client wants.
- *Stop using Dependabot in the framework*, in favour of a scheduled job with a repository
  condition. A large change to the framework's own maintenance to solve a client's problem.
- *Let a client delete them.* Chosen. `.github/repository-ownership.json` gains
  `clientMayRemove`, naming exactly those two files. `verify-framework` reports a deleted one as
  `REMOVED` and passes; a file on the list that is present and edited is still `DRIFT`. Delete,
  not edit: the files stay the framework's.

**What it costs.** The update guide promises that a client who edits no framework path gets no
conflict. This is one exception to it: a release that changes either file meets the deletion as a
modify/delete conflict, once, resolved by deleting it again. The guide says so.

**The creation procedure removes them before the first push**, because Dependabot acts within
minutes of seeing its configuration. A repository created from v0.43.1 or earlier removes them
after taking this release.

What was run:

| Check | Result |
|---|---|
| Two new workflow-condition tests in `repository-ownership.test.js`, before the change | failed |
| Three new cases in `verify-framework.test.js` (removed; removed and committed; edited instead), before the change | the two removals failed as `DRIFT` |
| `verify-framework`, `repository-ownership` and `framework-baseline` tests | 83 passed |
| `npm run verify:framework` in the framework's repository | PASS, nothing to compare |

**Not verified where it matters most.** The two workflow conditions were not watched running in a
client repository: that needs a client to take a release containing them. `github.event.repository.private`
is read from the push and pull-request payloads; if it were absent, the negation would be true and
CodeQL would run, which is the old behaviour and not a new failure.

**Left as it is.** `wp corex readiness` lists both files as evidence for its `ci-security` row. It
is the framework's own release check; run in a client repository that has deleted them, that row
would report them missing. Nothing in the client workflow runs it.

## #249 — A bound measures what the field is for, and the form's schema is where that is decided

Date: 2026-10-07 · Spec: none (defect), issue #250 · Status: Final

`Rules\Max` and `Rules\Min` compared an answer that passed `is_numeric()` as a number and counted
the characters of anything else. The field was never consulted: `max:300` on a message refused the
answer `2025`, and `min:3` on a name accepted `12`. DECISIONS #241 fixed `max_length` and `min_length` and
left this, because changing it changes forms that exist.

**The contract now.** A field is a number when its type is `number` or `rating`, or when its rules
include `numeric`. `max:N` and `min:N` compare the number there, and count characters on every
other field. `max_length` and `min_length` count characters on any field.

**Where it is decided.** `SchemaResolver::resolve()` rewrites `max` and `min` to `max_length` and
`min_length` on a field that is not a number. Considered and not done:

- *Give the rule the field.* `Rule::validate()` takes the value, the parameters and the other
  values. Adding the field changes an interface that client sites implement.
- *Decide in the validator.* The browser validates from the exported schema, so the same decision
  would have to be written a second time in `corex-runtime.js` and kept equal by hand.

The resolver is the one thing both read: `Validator` runs the resolved rules and `SchemaExporter`
exports them. No rule class and no line of the browser runtime changed.

**What it costs.** The resolved schema no longer repeats what was declared: a text field's `max:80`
is `max_length` in `FieldSchema::$rules`. Nothing in the framework reads a resolved rule by the
names `max` or `min`; three tests asserted the old name and were updated. The error keys are the
same either way.

**No deprecation step, and why.** #250 proposed one. The forms it would have protected are those
that bound a quantity with `max:N` on a text field and do not declare `numeric`. Under the old
behaviour such a field already accepted any answer that was not a number, counted by its length:
`abcdefghij` passed `max:10`. It was not a numeric bound before this change, only one that held
for answers that happened to be digits. Neither client repository uses `max` or `min`, and the
stock contact form moved to the length rules under DECISIONS #241. The change is stated under Client impact
with the one-line fix.

**Left as it is.**

- A number field's answer that is not numeric is still measured by its length by `Max` and `Min`.
  `numeric` is the rule that refuses it.
- The messages for the keys `max` and `min` say "too long" and "too short", which is wrong for a
  number that is too large or too small. That was true before this change and is not made worse by
  it; a second pair of keys would change the error keys a front end receives.
- A field type registered by a site is a number only if its field declares `numeric`.

What was run:

| Check | Result |
|---|---|
| New resolver and validator cases, before the change | 10 failed |
| `tests/Unit/Forms` | 187 passed |
| `tests/Unit` | 2078 passed |
| `tests/Integration/Forms`, real WordPress | 60 passed |
| `corex-runtime-forms.test.js` | 21 passed, unchanged |

Not run: a browser submit of a rewritten rule. The exported schema is asserted to carry
`max_length`, and the runtime's `max_length` arm has its own tests; the two were not watched
together in a page.

## #250 — Forms ask who the client is, and corex-config answers from the proxies the site trusts

Date: 2026-10-07 · Spec: none (defect), issue #247 · Status: Final

Reported 2026-10-06 from a client site behind a tunnel. The form and flow rate limits were keyed on
`$_SERVER['REMOTE_ADDR']`, and the captcha check sent the same value to its provider. Behind a proxy
that is the proxy's address: one allowance shared by every visitor.

Login protection already resolved the address correctly (`ClientIpResolver`, corrected under
DECISIONS #242). Forms could not use it: it lives in corex-config, and corex-forms depends only on
corex-core. #247 named three things to decide.

**1. Where address resolution lives.** A contract in corex-core, `Corex\Http\ClientAddress`, with
one method. corex-core binds `RemoteAddress`, which answers with the connection's address and
believes no header. corex-config binds `TrustedProxyClientAddress` over it, which asks the same
`ClientIpResolver` login protection uses. Providers register in a fixed order with corex-config
after corex-core, and a later binding replaces an earlier one, so corex-forms gets whichever is the
better answer available and names neither.

The setting did not move. It is still `trusted_proxy_mode` and `trusted_proxy_ranges` in the
login-protection option. #247 called it "a login setting today, not a site setting"; what makes it a
site setting is who reads it, and moving the stored keys would have been a migration for no change
in behaviour. Where it is stored is now a detail behind the command below.

**2. A CDN's own header (`CF-Connecting-IP`, `True-Client-IP`).** Not added. The site that reported
this is behind a tunnel on the same machine, where the rightmost `X-Forwarded-For` entry is the
address the CDN's edge saw, and trusting the loopback addresses gives the right answer. A header
that one vendor sets is a second way to be wrong on every other host. A site that needs one can bind
its own `ClientAddress`; that changes what forms see, not what login protection sees, which calls
`ClientIpResolver` directly.

**3. Operator documentation, and a way to set the list.** Writing the documentation showed there
was nothing to document: the Security screen carries the list in its state and saves it back, and
draws no field for it. The only way to set it was a hand-written POST to
`corex/v1/security/login-protection`. So this adds `wp corex security trusted-proxies`, which
prints, replaces or clears the list and refuses an entry that is not an address or a CIDR range. It
edits the two keys in the stored option and leaves the others, as `security reset-login` does.
*Security operations* now says which addresses to list and the two ways the answer goes wrong: a
proxy left off the list, and a proxy that does not append to the header.

**Left as it is.**

- A field on the Security screen. It is a change to a built React screen with its own review; it is
  listed in `PROGRESS.md` under "Open, and not hidden".
- The REST route accepts any string as a trusted range. The resolver ignores one it cannot parse.
- `BlogProController::shareClick()` passes `REMOTE_ADDR` into a visitor hash for reading analytics.
  It is not a limit, and the hash also takes a visitor key and the user agent.
- The two form controllers each have the same one-line `clientFingerprint()`.

What was run:

| Check | Result |
|---|---|
| `ClientAddressTest`: the connection's address, and the trusted-proxy answer | 11 passed |
| `SecurityTrustedProxiesCommandTest` | 11 passed |
| `FormClientAddressTest`, real WordPress: a second visitor behind a trusted proxy keeps their allowance; without the proxy trusted they share one | 3 passed |
| `tests/Unit` | 2100 passed |
| `tests/Integration/Cli`, `Forms`, `Security` | 141 passed |
| The command on the local install: list, set, a refused entry, both arguments, clear, help | behaved as documented; the option it created was deleted afterwards |

**Not watched failing first.** The rate-limit test could not run before the change, because the
classes it constructs did not exist. Its third case is the old behaviour under the new code: with no
proxy trusted, the second visitor is refused.

**Not verified where it was reported.** Nothing here was run behind a real tunnel or CDN. Whether
`127.0.0.1` and `::1` are the right entries for the reporting site depends on what its
`REMOTE_ADDR` is, which that site has to read.

## #251 — `phone` stays international; `phone:national` also takes a number with its trunk zero

Date: 2026-10-07 · Spec: none (defect), issue #249 · Status: Final

`Rules\Phone` strips separators and requires E.164: an optional `+`, a first digit that is not zero,
up to fifteen digits. A number written the way its own country writes it starts with the trunk `0`
and was refused: `010 1699 9700`, `(020) 7946-0958`. A contact form asking a local audience for
"Phone" refused most of what it was given. Reported 2026-10-06 from a client site, which wrote its
own rule.

The refusal is deliberate. `PhoneRuleTest` has asserted since #148 that `0123456789` is "not
dialable", and it is right: without the country, a national number cannot be rung. So the rule's
default does not change, and nothing changes for a form that does not ask.

**What was added.** A parameter. `phone:national` accepts everything `phone` accepts, and also a
`0` followed by six to fourteen digits. Fifteen digits in all is the most E.164 allows a whole
number; seven is short enough for the shortest local numbers that carry a trunk zero, and long
enough that `0` and `012345` are refused. `corex-runtime.js` applies the same two patterns, and its
test runs the same table.

**Considered and not done.**

- *A second rule, `phone_national`.* It would need its own error key or share `phone`'s; a
  parameter keeps one rule, one key and one message.
- *A parameter naming the country, to normalise to E.164.* That is a numbering-plan table per
  country, which is a library, not a rule. The answer is stored as it was typed.
- *Accept a leading zero by default.* It would change what every existing `phone` field accepts.

**What `phone:national` does not know.** It accepts any `0` followed by six to fourteen digits,
including the international prefix some countries dial as `00`. It does not check that the number
exists in any country's plan. A national number with no trunk zero, such as `555 010 0199`, was
always accepted by `phone` and still is.

**Found on the way.** The rule's own docblock offered `(020) 7946-0958` as a number it accepts. It
refused it. The docblock now says which form each rule takes.

What was run:

| Check | Result |
|---|---|
| New `PhoneRuleTest` cases, before the change | 4 failed: the four numbers with a trunk zero |
| `tests/Unit/Forms` | 199 passed |
| `corex-runtime-forms.test.js`, with the same table | 31 passed |
| `wp-scripts lint-js` on the runtime and its test | clean after `--fix` |

Not run: a browser submit of a `phone:national` field. The schema a form block hands the browser
carries a rule's parameters, which `SchemaExporterTest` covers for other rules.

## #252 — In a client repository the generators write into the client's plugin, and the generated files say only what is true

Date: 2026-10-07 · Spec: 102 (update-safe client sites), issue #251 · Status: Final for items 2 and 3; item 1 open

The first client repository created from v0.43.0 found three places where the framework sends
client work into framework-owned paths. Two are fixed here.

**Item 2: `wp corex make:*`.** `CliServiceProvider::context()` read `app.path` and, finding it
empty, wrote to `wp-content/corex-app` under `App\`. Nothing sets `app.path` when a site is
generated, and the generated `AGENTS.md` said the generators "write into this client plugin".

Now, when `app.path` is empty, `ClientSitePlugin` looks for `sites/*/*-site/src/*ServiceProvider.php`
under the repository root. Exactly one match is the answer: its directory is the base, the file's
name gives the namespace, and the plugin's directory name is the prefix. The prefix is the plugin's
slug because `BlockScaffolder` uses it as the text domain, and the slug is the text domain
`make:site` gives the plugin.

Considered and not done:

- *Have `make:site` record `app.path`.* The setting is read from an option or from `.env`. An
  option is per install and would name an absolute path on one machine; `.env` is not committed.
  Neither travels with the repository, and the repository is what knows where its site is.
- *Choose among several sites.* There is nothing to choose by. With none or several the locator
  answers nothing and the old order applies; the generated `AGENTS.md` names the three `.env` keys.

**Item 3: the README.** The "Theme assets" section moved to its own stub,
`starter/readme-theme-assets`, rendered into the README only when the theme's assets are generated:
`--starter` without `--plugin-only`.

**Item 1: Spec Kit. Not fixed.** `.specify/scripts/powershell/common.ps1` resolves the specs
directory as `<root>/specs`, `/speckit-specify` writes `.specify/feature.json`, and the
agent-context extension rewrites the root `CLAUDE.md`. Teaching them a per-site root is a change to
the Spec Kit scripts and to every command that calls them, and it wants its own spec. What changed
is the instruction: the generated `AGENTS.md` no longer tells a client to run a workflow that
produces drift. It says to write the spec by hand, and names the three paths.

**Sites that exist are not corrected.** `make:site` does not rewrite a site it already generated,
and the files are the client's. The changelog says what to correct by hand.

What was run:

| Check | Result |
|---|---|
| `ClientSitePluginTest`: one site, no site, two sites, no `sites/` | 4 passed; failed before the class existed |
| `SiteScaffolderTest`: README with and without `--starter`; `AGENTS.md` content | failed before the change, pass after |
| `tests/Unit/Cli` | 168 passed |

**Not run in a client repository.** The locator is tested against a directory `make:site` filled.
That `CliServiceProvider` hands it the repository root was read, not run: `dirname(__DIR__, 3)` is
the expression the same class already uses for that root. No `make:block` was run in a client
checkout.

## #253 — A Submissions row is one control stretched over the row, and the inbox takes the width it is given

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports), slice 1 · Status: Final

The owner, on the first client site: "i need the full row to be clickable not just the first
column". Only `.corex-inbox__row-button`, in the submitter cell, opened a submission.

**How the row opens.** The button stays the row's one control and its hit area is stretched over
the row: the row is the containing block, and the button's `::after` covers it. The checkbox cell
is lifted above that area. Considered and not done:

- *A click handler on the row.* It needs a second focus stop or a row that is itself focusable, has
  to ignore clicks that came from the checkbox, and gives assistive technology a row that acts and
  a button that also acts.
- *A link per cell.* Six stops per row for one destination.

**What it costs.** Text in a row cannot be selected by dragging, because the control is above it.
The pane is where a submission is read and copied. Anything interactive added to a row later has
to be lifted above the hit area; the stylesheet says so at the rule.

**The open row** carries `aria-current` and its button `aria-expanded` and `aria-controls`. It is
drawn with a tint and a bar on its leading edge, so the state is not colour alone. The focus ring
is drawn on the row, with `:has()`, because a ring on the button's stretched area was cut where the
checkbox cell sits above it.

**Found by the test, and fixed with it.** The new browser test could not click the "Received"
cell: it was off the edge of a 1280-pixel window, and nothing scrolled. `.corex-inbox` is a grid
with one `auto` column; the filter row's six fixed-minimum columns needed 958 pixels, the column
grew to fit, and `.corex-admin__shell` clips its overflow. Two changes: the inbox's column is
`minmax(0, 1fr)`, so no child can widen it, and the filters wrap with `auto-fit`. The 1200-pixel
window breakpoint the filters had is removed: the content column is narrower than the window by two
sidebars, so a breakpoint on the window could not know when they stopped fitting.

Constraining the column squeezed the table, and the sender's address ran under the next column.
The sender's cell now has a measure, 12 to 20rem, and an ellipsis.

What was run:

| Check | Result |
|---|---|
| New Playwright test: every cell after the checkbox opens the submission, the row is marked, the checkbox opens nothing, one button per row | failed before (the cells did nothing, then "Received" was unreachable); passes |
| `submissions-inbox.spec.js` | 5 passed |
| Jest, whole suite | 556 passed |
| `lint-js`, `lint-style` on the changed files | clean |
| Looked at, rendered: dark and light at 1280, right-to-left dark at 1280, light at 782; a row open, hovered and focused | as described above |

**Seen and left for its slice.** At 782 wide the detail pane covers the window and its top is under
the admin toolbar. That is FR-046, slice 4.

**Not done.** No Jest test of the row's state: `InboxTable` is not exported and the browser test
asserts the same attributes on the real page. `tasks.md` T005 says so. The Spec Kit
`agent-context` hook was not run, so `CLAUDE.md` still points at the plan of spec 068.

## #254 — An export is a table first and a file second, and the file is written once, from every batch

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports), slice 2a · Status: Final

The owner, of the export: "the exported file should be readable not serialized cells".
`SubmissionExportCsvWriter` wrote one column per group of data and put `wp_json_encode()` of the
group in the cell.

**A table before any format.** `SubmissionExportTable` turns submissions and a form's questions
into headings and typed cells: text, number, date-time. `CsvExportWriter` writes that table, and
the Excel and PDF writers of later slices will write the same one. What a column holds is decided
in one place. A cell carries its kind because a writer needs it: the submitted time is a date, an
answer to a number question is a number, and a phone number that is all digits stays text.

**The question's wording comes through a contract.** `SubmissionQuestions`, owned by corex-config,
answered by `Forms\FormQuestions`, which asks corex-forms for a code form's fields or a flow's
published schema. With corex-forms absent, or the form gone, it answers nothing and an answer is
headed by its stored key. An answer the form no longer asks for is still exported, after the ones
it does.

**The file is written once.** The job gathers 100 submissions a run. The columns are not known
until the last run, because a late submission may answer what no earlier one did, so a file
written batch by batch could not have its headings right. Each batch is appended to a working file
of one submission per line, and the run that gathers the last one reads it back and writes the
export. Only the current batch is in memory while gathering. The old handler re-saved the whole
growing CSV to one post-meta row on every batch.

**The file is on disk, in the protected uploads directory**, under a name that cannot be guessed.
Considered and not done: keeping it in post meta, as before. An archive is not text, and a meta
row is the wrong place for a file of any size.

Only the file's name is stored. The first version stored its path, and the integration test failed
on Windows: WordPress strips backslashes from stored meta, which is every separator of a Windows
path. The test had been asserting that the stored path "contained" the directory, which a path
with no separators still did; it now asserts the file exists. A stored name also stays true when a
site is moved.

**CSV for a spreadsheet, not for a parser.** A byte-order mark, without which Excel reads UTF-8 as
the local encoding; CRLF; a separator of comma, semicolon or tab. Text beginning with `=`, `+`, `-`
or `@`, with or without leading whitespace, is prefixed with an apostrophe. A number cell is not:
a negative number begins with a minus.

**Several forms are one file each, in an archive.** Each form has its own questions. One file with
every form's columns side by side was the alternative, and is mostly empty cells.

**The site's timezone is asked for when a date is written.** The table was first built with
`wp_timezone()` at boot, and the integration test, which changes the setting, showed the stale
value. A process that outlives a request has the same problem.

**The old group names still work.** `identity`, `workflow` and `submitted_fields` in a request
stand for the columns that hold the same data, so the dialog as it is today produces the new file
without being changed. Rebuilding the dialog is slice 2b.

**Left for slice 2b.**

- The download still travels base64 inside a JSON answer, so a large export is held in memory
  twice. 2b streams it.
- The export still waits for WP-Cron or Action Scheduler, and the dialog still has to be refreshed.
- `separator` and `format` are accepted by the route and offered by no screen yet.
- Choosing single answers as columns works in the model (`answer:<key>`) and is refused by the
  route's sanitiser, which strips the colon.

What was run:

| Check | Result |
|---|---|
| `SubmissionExportTableTest` | 23 passed |
| `CsvExportWriterTest`, reading the written bytes | 13 passed |
| `SubmissionExportServiceTest`: the job over one and several batches, several forms, the formula guard, no working file left, the request's columns | 23 passed |
| `SubmissionExportFileTest`, real WordPress: two submissions of the stock contact form, exported by the real job and downloaded | passed; it failed twice on the way, on the stored path and on the timezone |
| `tests/Unit` | 2172 passed |
| Integration, whole suite, real WordPress | 515 passed |
| `submissions-inbox.spec.js`, which creates an export through the existing dialog | 5 passed |
| Jest, whole suite | 556 passed |

**Not watched failing first.** The table and writer tests were written before the classes, and a
missing class is not a failing assertion. The two faults the integration test found were real
failures.

**Not opened in a spreadsheet.** The file's bytes are asserted. Nobody double-clicked one in Excel.

**The browser test leaves a file.** `submissions-inbox.spec.js` creates an export through the
dialog and never deletes it. It used to leave a database row; it now also leaves a file in the
protected directory of the install it ran on. Expiry, in slice 5, is what removes them.

## #255 — The export dialog is a real dialog, it says what it will export, and it moves the export itself

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports), slice 2b · Status: Final

The owner, of the dialog: "why should i refresh to be able to download files? why it looks like
this in the first place".

**Why it looked like that.** Every CoreX admin rule is scoped under `.corex-admin`. The dialog was
a `@wordpress/components` `Modal`, which renders through a portal at the end of `<body>`: outside
that scope. `.corex-admin .corex-inbox__export-modal label` could not match anything, and neither
could a token. `CorexDialog` is the native `<dialog>`, opened with `showModal()`. The browser
draws a modal dialog in the top layer without moving it in the document, so it stays where it is
written and every scoped rule reaches it. The focus trap, the inert page behind it, Escape and
handing focus back are the browser's, not code here. The bulk-action confirmation uses it too.

One thing about it had to be learned from the browser test. Focus was not handed back on close:
the component closed its dialog in an effect cleanup, which React runs after it has taken the
element out of the document, and a browser cannot restore focus from an element that is gone. It
is a layout effect now, whose cleanup runs first.

**Why it needed a refresh.** Creating an export queues a bounded job. A job moves only when
WP-Cron or Action Scheduler fires, 100 submissions a run, and the dialog did not wait. Now
`POST exports/{id}/advance` takes one step and answers the job's state, and the dialog calls it
until the job completes, then fetches the file and saves it. It does not depend on the scheduler.
The job is still queued as before, so an export whose dialog is closed is finished by the
scheduler.

**That makes two callers of one job, so the runner has a lock.** `JobRunner::execute()` read a job,
did a batch and saved it, with nothing to stop a second run doing the same batch from the same
point. A step now takes `add_option('corex_job_running_<id>')`, which fails when the row exists,
and releases it in a `finally`. A lock older than two minutes is taken over, by an update that
only one of two contenders can make. It is in the runner, so every job kind has it.

**Saying what will be exported.** `POST exports/preview` counts the three scopes the way the
export would, and says whether the person may export personal data. The dialog shows each scope
with its count, puts the filters in force into words, opens on the selection when there is one,
and labels its button with the number. A scope that cannot be chosen says why. Columns that hold
personal data are not offered to somebody who cannot export them; the export refuses them
regardless.

The words are in `export/exportState.js`, as functions with no component around them, so they are
tested without a browser.

**Considered and not done.**

- *Polling a status route while the scheduler does the work.* It leaves the wait to WP-Cron, which
  is the thing that was slow.
- *Running the whole export inside the request that creates it.* A request that takes as long as
  the export does times out on the exports that matter.
- *Keeping the dialog open until the export ends.* The spec lets it be closed. Closing it stops
  the dialog asking, and the scheduler takes over.

**Not in this slice, against the spec.**

- **FR-009, a column choice remembered per person, and choosing single questions and their
  order.** The model and the route take `answer:<key>` and an order; the dialog offers groups of
  columns and remembers nothing. `tasks.md` T045 is open.
- **Streaming the download.** It still travels base64 inside a JSON answer.
- **Excel and PDF.** The format section says CSV and offers a separator. It does not show formats
  that are not built.
- **"There is nothing to export"** from the server is not translated, like the service's other
  refusals. The dialog shows its own translated line before the request is made.

What was run:

| Check | Result |
|---|---|
| `exportState.test.js`: the filters in words, the scopes and their counts, the columns, when the export can start | 32 passed |
| `corexDialog.test.js` | 7 passed |
| `SubmissionExportServiceTest`: counts per scope, and a step for the person who made the export and nobody else | 25 passed |
| `SubmissionExportFileTest`, real WordPress: an export finished by the advance route alone; a held lock stops a step and is released after one; a stale lock is taken over; the preview route's counts; somewhere to write when uploads cannot be written | 7 passed |
| `submissions-inbox.spec.js`, a real browser: the count on the chosen scope, the button disabled with its reason until the notice is confirmed, one click, a downloaded file with the questions as headings, Escape, focus back on the Export button | 5 passed |
| `tests/Unit` | 2174 passed |
| Integration, whole suite | 520 passed |
| Jest, whole suite | 595 passed |
| `lint-js`, `lint-style` | clean |
| Looked at, rendered: dark and light at 1280, right-to-left dark at 1280, light at 480 | two faults found and fixed: radios stretched by a rule written for text inputs, and the personal-data tag placed before the hint it belongs beside |

**Found by CI, in slice 2a's work.** The browser suite passed here and failed in CI, where nginx and
php-fpm run as a user that does not own the checkout. The dialog said why, in its own words:
"CoreX could not create the directory exports are written to." Since slice 2a an export needs a
directory to write to, and a host that does not let PHP write to uploads had lost exports
altogether. `ProtectedExportDirectory` now falls back to the system's temporary directory, which
is not served either. Such a host can still save an export when it is ready, and cannot download
it again after the system clears its temporary files. Slice 2a's own tests ran as the user who
owns the directory and could not have seen this.

**Not checked.** The progress bar on an export of more than one batch was not watched in a
browser: the browser test exports one submission. A screen reader was not used. The lock was
exercised by holding it, not by two processes racing.

## #256 — The Excel workbook is written by hand, and a real spreadsheet was asked what it made of it

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports), slice 3 · Status: Final

The owner: "why it is just a CSV why can't i export as modern excel extensions?"

**Written by hand, with no library.** A workbook is a zip of XML, and what the spec asks of one is
small: typed cells, a bold heading row held in view, filtering on, column widths, a sheet per form.
`XlsxExportWriter` writes exactly those parts. The Data export already built a one-sheet, all-text
workbook the same way, with `ZipArchive`. Considered and not done: a spreadsheet library. The ones
that exist are many times the size of everything else in the plugin, and the shared-host
distribution carries every dependency.

What that costs is that nothing validates the file but its tests. So the tests read the workbook
back part by part, and one produced workbook was opened in a real spreadsheet (below).

**What a cell is.** A number is a number cell. A date-time is the spreadsheet's day count with a
`yyyy-mm-dd hh:mm` format, counted on the clock the value was given in, because a spreadsheet date
has no timezone. Text is an inline string.

**No formula guard in a workbook.** A string cell is never evaluated; only a formula element is.
An apostrophe in front of `=HYPERLINK(…)` would be shown, and would be wrong. CSV keeps its guard,
and that guard has a visible cost that Excel made plain: a phone number written `+20 101 699 9700`
opens as `'+20 101 699 9700`. That is the right trade for CSV, where the alternative is a
spreadsheet parsing the value, and it is the reason the dialog now opens on Excel.

**Sheets.** Named for the form, within what a spreadsheet allows: 31 characters, none of
`[]:*?/\`, and no two the same in any capitals. A sheet for a right-to-left site is laid out right
to left; that follows the site's language at the moment the file is written.

**Rows are streamed.** A sheet's rows are written to a working file as they are read. Its column
widths and the range to filter are known only at the end, so the sheet's XML is assembled around
the rows then. Column widths come from the longest value, between 8 and 58 characters.

**What a real spreadsheet said.** One workbook and one CSV were written by the real writers and
opened in the Excel installed on the development machine, through its automation interface, and
closed without saving:

| Asked of Excel | Answer |
|---|---|
| The workbook's sheets | `Lead form`, `Careers apply` |
| The heading cell | bold |
| The submitted cell | a date, shown as `2026-10-09 18:45`, format `yyyy-mm-dd hh:mm` |
| An Arabic name | read back exactly |
| `=HYPERLINK("http://x","click")` in a workbook | text, not a formula |
| `01016999700` | text, with its leading zero |
| A budget of 2500 | a number |
| Filtering | on, over `A1:F4` |
| The first row | frozen |
| Sorting by the submitted column | IDs 41, 42, 43: chronological (SC-005) |
| The CSV, opened as a double-click opens it | six columns, the Arabic name exact, the formula-like value text |

That also closes what slice 2a's notes said had not been done: nobody had opened a file in Excel.

**Not checked.** Only Excel was asked. LibreOffice, Numbers and Google Sheets were not. A workbook
of more than a few rows was not opened. `dimension` and shared strings are left out, which a
spreadsheet tolerates and a strict validator may remark on.

What was run:

| Check | Result |
|---|---|
| `XlsxExportWriterTest`, reading the workbook back | 11 passed |
| `SubmissionExportServiceTest`, with a workbook of two forms | 26 passed |
| `SubmissionExportFileTest`, real WordPress: Excel through the route, the job and the download | 8 passed |
| `submissions-inbox.spec.js`, real browser: an `.xlsx` on one click, then the CSV | 5 passed |
| `tests/Unit` | 2186 passed |
| Integration, whole suite | 521 passed |
| Jest, whole suite | 595 passed |

## #257 — The spacing of the export dialog and the inbox filters was measured, not looked at

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports) · Status: Final

The owner, while slices 2b and 3 were being merged: "please make sure that the UI visually is
correct spaces and margins and so on".

Both had been looked at rendered before they were presented, and both had faults that looking did
not catch. So this time every gap, padding and alignment was read off the page with
`getBoundingClientRect()` and `getComputedStyle()`, and then looked at.

**What the numbers said about the export dialog.**

| Measured | Was | Is |
|---|---|---|
| A column checkbox against the middle of its label's line | 5.1px above | 0 |
| A scope's radio against its name | 2px above | 0 |
| Margin on the format hint | `0 0 12px` | `0` |
| Gap under the Format section | 36px, where the others are 24px | 24px |
| Gap between column choices, each two lines | 8px | 12px |
| A row of "Recent exports" | 18px tall | 40px, with a rule between rows |
| The band above the buttons when there is nothing to say | a line of empty height | none: the reason shares the buttons' row |
| "1 submissions" | — | "1 submission" |

The first three have one cause. WordPress gives a checkbox and a radio `margin: -4px 4px 0 0`, for
sitting in a line of text, and a paragraph a bottom margin. Neither is wrong in WordPress's own
screens. In a grid or a flex row they are. Each checkbox now stands in a box one line tall and is
centred in it, which holds whatever size the input is: WordPress makes them 25 pixels on a phone.

**What the numbers said about the filters.**

| Measured | Was | Is |
|---|---|---|
| Heights of the six controls | 50, 40, 40, 47, 54, 54 | 40 each |
| Inner padding | 16, 12, 12, 2, 16, 16 | 12 each |
| "From" and "To" | on different rows | one item, always together |
| Heading checkbox against the column | 2px off | 0 |

The filters are now laid out by the width of their own panel, with a container query. Slice 1
replaced a breakpoint on the window with `auto-fit`, which stopped the panel overflowing and let
"To" wrap alone. A container query is what the first fix was reaching for: the content column is
narrower than the window by two sidebars, and only the panel knows its own width. The container is
the filter panel, not the inbox: a container contains its fixed-position descendants, and the
detail pane is one.

**Kept measured.** Two tests in `submissions-inbox.spec.js` assert the numbers above on the real
page, the dialog in both directions and the filters at four widths. A spacing fault is plain on the
screen and invisible to a test of behaviour, so it comes back the first time somebody touches the
markup.

**One more thing the unit suite caught.** `TokenConsumerContractTest` rejected `flex: 1 1 14rem`
as an unrecorded raw value. It is a measure, and is marked as one.

**Not reviewed here.** The detail pane. It is rebuilt in slice 4, and gets the same treatment
there. The bulk toolbar and the pagination were looked at and not measured.

What was run:

| Check | Result |
|---|---|
| `submissions-inbox.spec.js`, with the two measuring tests | 7 passed |
| `admin-controls.spec.js`, `admin-datetime.spec.js`, which also read this screen | passed; 29 across the three files |
| `tests/Unit` | 2186 passed |
| Jest, whole suite | 595 passed |
| `lint-js`, `lint-style` | clean |
| Looked at after measuring: the dialog in dark, light, right-to-left dark and light at 480; the filters at 1440, 1280, 900 and 480 | as the tables say |

## #258 — A submission is not refused for lacking a token that nothing was placed to produce

Date: 2026-10-07 · Spec: none (defect) · Refs #264 · Status: Final

Reported 2026-10-07 from the first client site, by reading v0.43.1, and confirmed by reading
`main`. It had not been reproduced on a site; the unit test below reproduces it.

**What happened.** Settings offers three captcha providers that take keys. For Turnstile and
hCaptcha with a secret saved, `CaptchaResolver` binds `RemoteCaptcha`. Nothing else knows them:
`FormChallengeContextFactory::providerConfigured()` is true for `recaptcha` only, so the flow block
renders no token field, and `CaptchaAssetController` loads a script for `recaptcha` only. No
Turnstile or hCaptcha widget exists in the repository. `ProtectionStage` then took its boolean
path and called `verify('')`, which is false for an empty token. Every submission of every flow was
refused with "Submission protection rejected the request", on a site whose "Test verification" had
just answered "The captcha keys were accepted".

`CaptchaResolver` already carries a comment about this shape of fault, for a provider chosen
before its keys are pasted: "half configured is not configured". This is the other half.

**What changed.** In `ProtectionStage`: when the token is empty and the configured driver is one
of the two that place no widget, the challenge is recorded as `not_configured` and the trap field
decides. A token that does arrive is verified as before.

Considered and not done:

- *Bind no verifier for these drivers.* A site that renders a Turnstile widget itself and posts
  its token would stop being verified at all.
- *Decide by whether a token field was rendered* (`providerConfigured()`). That is false for any
  driver that is not reCAPTCHA, including a site's own, which places its own widget. A submission
  without that driver's token is a failed challenge and is still refused; there is a test for it.
- *Take the two providers out of the setting.* It would strand the keys of a site that has saved
  them, and they are wanted: placing the widgets is part of #264.

**What it costs.** A site that renders its own Turnstile or hCaptcha widget inside a CoreX flow
was protected against a submission with no token, and now is not: such a submission is guarded by
the trap field alone. Neither client repository does this, and nothing documents it as supported.

**Saying so.** Three places told an operator the site was protected:

- "Test verification" answered `ok`. It answers `no_widget` now: the keys are good and challenge
  nobody.
- The admin's list of unfinished configuration had one captcha entry, for missing keys. It has a
  second, for keys that nothing uses.
- The setting's help said "Choose a provider". It says which provider places a challenge today.

**Left as it is.** `ControlPanelStatus` still counts a key driver with both keys as complete. It
answers whether the fields are filled, which they are. The Add-ons page still lists the four
drivers the add-on has verifiers for.

What was run:

| Check | Result |
|---|---|
| `ProtectionStageTest`, new cases, before the change | 2 failed: both drivers refused a submission with no token |
| `ProtectionStageTest` | 15 passed |
| `CaptchaDiagnosticTest` | 9 passed |
| `CaptchaWidgetGapTest`, real WordPress | 4 passed |
| `tests/Unit` | 2195 passed |

**Not run.** A flow was not submitted in a browser with Turnstile selected. The stage is tested
with the real context factory and a verifier that accepts one token.

## #259 — The detail pane is read from the top down, and it is the same dialog as the export

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports) · Status: Final

The owner, from the first client site: "the right pane opened i feel that it is not well
organized so if you can help with this and make it in a better way would be better".

**What was wrong with it.** It was a fixed box of its own. The answers were the fourth thing in
it, under the keys they were stored with. The owner was a type and a key. Three sections said "No
data recorded." Status had no label. There were two controls for assignment. It took no focus, did
not close on Escape, and went blank after every change, because a change reopened it.

**The order.** A header, the answers, reply and notes, triage, the notification, technical
details, history. It is the order of the questions somebody opens a submission with: who is this
and how do I reach them, what did they say, what do I do about it. Reply and notes are above
triage because the spec says so (FR-039), and because a status is usually changed after the reply
that earns it.

**It is `CorexDialog`, docked.** The export dialog already had what the pane lacked: the browser
moves focus in, keeps it there, closes on Escape and hands focus back. A `drawer` variant docks
the same dialog to the end of the window at full height. A second implementation of focus
handling would have been a second place for it to be wrong. Being in the top layer, it is over
the admin toolbar and not under it, which is how FR-046 is met. The page behind it is inert and
dimmed; it is not usable while the pane is open, and it does not look as if it were.

**Opening is reading.** The spec assumed it and asked the owner, who has not answered. It is built
that way, with "Mark unread" in the header and a `mark_unread` on the route, because a submission
that stays "unread" after somebody has read it makes the count of unread ones mean nothing. It is
one request to take out if the owner says otherwise.

**A change no longer reopens the pane.** A change is followed by fetching the submission again
and swapping the record in. Reopening put the pane back into its loading state, which removed
whatever had focus.

**What the wording is made from.** The submission's payload now carries the form's questions, the
owner's name and the people who can own it; the pane is a set of plain functions over that record
(`detailState.js`), tested without a browser. The history is one of them. An event the function
does not know is printed as it was stored, so a new kind of event is never dropped.

**Answers in their own direction.** Each answer is in a `<bdi>`. `dir="auto"` on the cell would
have fixed the punctuation too, and would have aligned an email address to the left of an Arabic
pane, between two answers aligned to the right.

**Not done: the rest of FR-043.** It asks the pane to say to whom each attempt went and where a
failure can be fixed. A submission stores one delivery record: a result, a time, a provider, a
reason. It stores no recipient, and the inbox is not told where the mail screen is or whether the
person may open it. The pane shows what is recorded. The rest is T074b.

What was measured, on a real page, and is now asserted by a browser test:

| Measured | Result |
|---|---|
| The pane, in a 1280 by 720 window | 576px wide, the full height, at the end of the window |
| The pane, in a 480 by 720 window | the whole window, nothing off the screen |
| The bottom of the first answer | on the screen without scrolling at 720px of height (SC-007) |
| Distance between parts | 24px, all of them |
| Starting edge of the title, the header's lines, the headings, the questions | one |
| Height of the text field and the two selects | 40px |
| A question against its answer | 14px against 16px; the answer had been the smaller |
| "Mark unread" | 17px tall as first built, 24px now (WCAG 2.2, 2.5.8) |
| After Escape | focus is on the row's button |

It was looked at in dark, light, right-to-left and at 480px wide. Two things were found by
looking and not by measuring: the answers were smaller than their questions, and the history read
"read success".

What was run:

| Check | Result |
|---|---|
| `detailState.test.js`, the history cases, before the function existed | 11 failed |
| Jest, `plugins/corex-config` | 336 passed |
| `tests/Unit` | 2198 passed |
| `tests/Integration/Submissions`, real WordPress | 28 passed |
| `submissions-inbox.spec.js`, a browser | 8 passed |
| `admin-controls.spec.js`, the close button's contrast in dark and light | 2 passed |
| `lint:js`, `lint:css` | clean |

**Not run.** A screen reader was not used on the pane. A real Arabic install was not used: the
right-to-left check flipped the direction of an English one, which is why its dates read out of
order in the capture and would not on a translated site.

## #260 — A route that refuses a caller says so where routes are read

Date: 2026-10-07 · Reported from the first client site, after it took v0.43.2 · Status: Final

The report: `wp corex routes:list` prints the submissions export routes as "public" and the Data
export routes as "guarded". An anonymous request to any of them is answered 403.

**What was true.** Not four routes. All 15 under `corex/v1/submissions` and the 10 admin routes
under `corex/v1/flows` were registered with `permission_callback => __return_true`. Each handler
goes through a gateway that refuses first: the Inbox by the person's access scope, the flows by
`manage_options` in the middleware. So nothing was open. The listing reads the registration, and
the registration said open. The generated API description, which reads the same thing, gave the
25 routes no security requirement.

CoreX's own cookbook says: "Never expose a writing route with `permission_callback =>
__return_true`."

**Why they were written that way.** A refusal from a permission callback is WordPress's error
body. A refusal from the handler is CoreX's envelope. Registering open kept every answer of these
routes in one shape.

**What was chosen.** Each gateway has `permits()`, and the routes name it as their permission
callback. It answers `true` or a `WP_Error` with the status, the code and the reason the handler
gave: 403, `forbidden`, "You cannot manage submissions." The handlers keep their own check. A
route's guard is now where WordPress, the listing, the API description and a reviewer look for it.

The cost is the shape of one answer: a refused caller gets `{ code, message, data }` and not
`{ ok: false, … }`. `Corex.api` normalises both, so the admin does not change. It is in Client
impact.

**Rejected: teaching the listing.** A route could carry a mark saying "checked in the handler" and
the reader could believe it. That is a claim beside the code, which is what the listing exists not
to be.

**The flows' capability is named once.** `manage_options` was a string in two middleware lists. It
is a constant the route's check and both lists use.

**`support.js`.** The hygiene test refused the name anywhere, as the helper a design tool writes
beside a `.dc.html`. The client's Playwright helper had the name. Where a design export lands is
written down nowhere, so the rule is anchored to what is known: the root, `design/`, and any
directory that holds a `.dc.html`. A `support.js` beside a design export is still named, so both
files are removed together.

What was run:

| Check | Result |
|---|---|
| `AdminRoutesDeclareTheirGuardTest`, before the change | 2 failed: both listings held open routes. 6 passed: the refusals and the answers |
| `AdminRoutesDeclareTheirGuardTest`, real WordPress | 8 passed |
| `tests/Integration/Submissions`, `FlowControllerTest` | 34 passed |
| `repo-hygiene.test.js`, the new cases, before the change | 4 failed |
| `repo-hygiene.test.js` | 27 passed |
| `tests/Unit` | 2198 passed |

**Not run.** `wp corex routes:list` was not run on the client site after the change; the test
reads the same `RoutesReader` the command prints from.

## #261 — A framework test that also runs in a client's repository asserts what is true in both

Date: 2026-10-07 · Reported from the first client site, on its v0.43.2 update · Status: Final

The report: "Integration tests (real WordPress)" failed on the client's update pull request, 1
failed and 524 passed. `CommandRegistrationTest` expected the generators' namespace to be `App`
and got `MuvaSite`. The same client code on v0.43.1 passed.

**What was true.** v0.43.2 made the generators write into the one client plugin under `sites/`,
under its namespace (DECISIONS #252). That is what was asked for and it is right. The test was
written before a client's repository existed and asserted the framework's default. A client's
repository carries the framework's tests and runs them, so from v0.43.2 every pull request of a
client with exactly one site had a red check, on a file the client must not edit.

**It was not caught here** because the framework's repository holds no site, and the unit test of
`ClientSitePlugin` builds its sites in a temporary directory. Nothing ran the integration suite
with a site in `sites/`.

**What was chosen.** The test asserts `App` where there is no client plugin under `sites/`. Where
there is one, it asserts the namespace is a valid one and no more: which plugin is chosen and what
its namespace is belongs to `ClientSitePluginTest`, and repeating that rule here would be a second
copy of it.

**Rejected: skipping the test in a client's repository.** The rest of it, that the CLI provider
boots and the engine resolves without WP-CLI, is as true and as worth knowing there.

**Whether there are others.** The client's run is the evidence: one failure in the integration
suite, none in the unit suite or in Jest. No other test reads the generators' context.

What was run:

| Check | Result |
|---|---|
| The test, with one temporary site at `sites/acme/acme-site/`, before the change | 1 failed: `'App'` expected, `'AcmeSite'` received |
| The test, with that site, after | 5 passed |
| The test, with no site | 5 passed |

**Not run.** The whole integration suite was not run here with a site in place. The client's CI
runs it on every pull request and will say.

## #262 — An export's expiry is the site's retention counted from its date, and is not stored

Date: 2026-10-07 · Spec: 103 (submissions inbox and exports), slice 5 · Status: Final

The owner asked for "professional features" of the export and named the history among what was
poor. The spec: each past export says who made it, when, what it covered, its format, count and
size, and when it expires; it can be downloaded again until then, and deleted (FR-028 to FR-031).

**Expiry is not stored, though the plan said it would be.** D11 had a run record its expiry. CoreX
already has one daily retention sweep, and every store in it answers two things: how many days it
keeps, and "remove what is older than this". A stored expiry would be a second statement of the
same fact, and the two would disagree on the day somebody changes the period. So an export expires
`SubmissionExportRetention::DAYS` after it was made. The history shows that date and the sweep
removes the file on it. A file past the date is expired from that moment, whether or not the sweep
has been by: the history says so and the download refuses.

**Thirty days, and no setting.** The spec's assumption, "unless the site already defines a
retention for exports". No site does; nothing in CoreX had one. A setting nobody asked for is not
added.

**The file goes and the entry stays.** For an expiry and for a delete alike. The entry is what
makes an export accountable: who took a copy of people's answers, of what, and when. The run keeps
the size the file had, so the entry can still say it.

**The history is its own class.** `SubmissionExportService` asked for exports, moved them along,
listed them and handed them back, and needed a retention and a way to name people to do the last
two properly. `SubmissionExportHistory` has those: `entries()`, `download()`, `delete()`. The
service keeps asking and moving. `history()` and `download()` moved; that is in Client impact.

**What can be listed can be downloaded and deleted, and nothing else.** One check, `accessible()`:
a person's own exports, or every export for somebody who may manage all submissions.

**Deleting asks first**, in the row, and the answer focus lands on is "Keep it". After a delete
the buttons that had focus are gone, so focus goes to the section's heading.

**A file that is not there is not offered.** `file()` answers null when the file is not on disk: a
site moved without its private uploads, a host that clears them. Found on the development
install, where files had been removed by hand and every one was still listed with a "Download"
that could only fail.

**Found on the way: `[object Object]`.** The dialog put the filters in force into words with
`formatDateTime()`, which answers `{ human, machine, isPresent }`, and handed the whole answer to
the sentence. A date range read "[object Object] to [object Object]". It has done since v0.43.2.
The new browser test sets a date range for that reason; run against the released dialog it failed
with exactly that text.

**Not done.** An expired or stopped export's job is not looked up, so "No file to download" does
not say which of the two it was. Files left on disk by an export whose entry was deleted outside
CoreX are not swept. A remembered column choice (T045) and a streamed download (T043b) are still
open from slice 2.

What was measured, on a real page, and is asserted by a browser test:

| Measured | Result |
|---|---|
| What an entry covered, and its facts | 14px; they were 13px, WordPress's size for a paragraph |
| The line about the file | 12px |
| Between the lines of an entry | 4px, all of them |
| "Download" and "Delete" | 24px tall; neither wider than its box, neither past the dialog's edge |
| An entry's text against the heading | one starting edge |
| On "Delete" | focus is on "Keep it" |
| After a delete | focus is on the heading; the entry reads "Deleted by …" and has no buttons |

Looked at in dark, light, right-to-left and at 480px wide, with a kept, a deleted and an expired
export in the list.

What was run:

| Check | Result |
|---|---|
| The history's unit tests, before the classes existed | failed: `SubmissionExportHistory` not found |
| The history's wording in Jest, before the functions existed | 20 failed |
| The new browser test against the released dialog | failed: "[object Object] to [object Object]" |
| `tests/Unit` | 2209 passed |
| `tests/Integration`, real WordPress | 539 passed |
| Jest, `plugins/corex-config` | 356 passed |
| `submissions-inbox.spec.js`, a browser | 9 passed |
| `lint:js`, `lint:css` | clean |

**Not run.** The sweep was not left to run from WordPress's scheduler; its store was called with a
cutoff. A screen reader was not used on the history.

## #263 — What WordPress prints before the page is printed inside the CoreX shell, by the server

Date: 2026-10-07 · Asked for by the owner, from a browser test that fails on the development install · Status: Final

The report: `admin-help-tab.spec.js` ("the shell starts where the page starts, with no blank band
above it") fails on the development install with "corex-settings shell offset below admin bar:
expected <= 1, received 54". WordPress's update nag is in a band above the shell.

**What was true.** `wp-admin/admin-header.php` fires `admin_notices` and `all_admin_notices` as the
first thing inside `#wpbody-content`, before the page is called. WordPress's script then moves
every `div.notice` to after a `.wp-header-end` marker, or to after the first heading when the page
has no marker, and skips a notice marked `inline`. The update nag is `notice notice-warning
update-nag inline`, so it stayed above the shell: 12px of margin and a 41.6px box. A notice not
marked `inline` was moved to after the `<h1>`, which on a CoreX screen is inside the page header,
between the title and its description, in WordPress's light colours in both appearances.

**It was not caught in CI** because CI installs the latest WordPress and the nag is printed only
while an update is pending. The development install runs 7.1.2 with 7.1.3 out. Any client site with
an update pending shows it.

**What was chosen.** `Corex\Config\AdminUi\ScreenNotices` opens an output buffer on
`admin_notices` at the first priority and closes it on `all_admin_notices` at the last, on CoreX
screens only. `AdminPage::open()` reads the captured markup through a new filter,
`corex_admin_notices`, and prints it in `<div class="corex-admin__notices">` between the page
header and the content, followed by a `wp-header-end` marker. The stylesheet draws a notice there
as a CoreX alert. The region takes space only while it holds something that is drawn.

**Rejected: moving the nag with a script.** The page would be drawn with the nag above the shell
and then jump by its height on every load.

**Rejected: hiding the nag until a script has moved it.** Whenever the script did not run, an
administrator would not be told a core update is pending.

**Rejected: styling it where it is.** The band is the defect. Nothing in CSS moves an element into
another parent.

**Everything the two hooks print is taken, not only the nag.** A plugin's notice marked `inline`
has the same defect. One that is not marked is moved by WordPress's script to the marker, and the
marker is now in the region.

**The marker is last in the region.** WordPress's script inserts notices after it and leaves an
`inline` one where it is. With the marker first, the nag was printed first and drawn last.

**What cannot be lost.** If a notice callback leaves a buffer open, or closes one it did not open,
the capture is abandoned and the notices appear where WordPress printed them. If a page under the
CoreX menu draws no shell, nothing reads the filter, and the captured markup is printed after the
page callback, on the hook WordPress calls the page through.

**The markup is not escaped again.** It is output WordPress and other plugins had already sent in
the same request. Passing it through `wp_kses_post()` would remove the forms and scripts some
notices are made of.

**`:has()` is new to this stylesheet.** The region is padded only while it holds something drawn,
and the content area's top padding drops from 32px to 24px under it. A class set by the server
would be wrong the moment a notice is dismissed. A script, a style element, a template and a
hidden element do not open the region; an element a plugin hides in some other way does, and
leaves 24px of extra space above the content.

**Not a spec.** This is a defect in the shell that specs 067 and 097 describe (nothing of
wp-admin above the product), fixed to their contract. No new screen, setting or string.

What was run, on the development install (WordPress 7.1.2), the changed files served to the test
requests only:

| Check | Result |
|---|---|
| `admin-core-notices.spec.js` before the change | 8 failed; the first: "corex-settings shell offset below admin bar", 53.59 |
| `admin-core-notices.spec.js` after | 8 passed |
| `admin-help-tab.spec.js` after | 12 passed, the reported test among them |
| Unit suite | 2209 passed |
| Jest | 61 suites passed |
| Stylelint on the stylesheet, ESLint on the spec and the helpers | clean |

What the browser test measures, on all fourteen routes with an update pending: the shell's offset
below the admin bar, nothing left above the shell, one visible nag with its update link, 32px from
the page header to the first notice. On one screen, in dark and light, left-to-right and
right-to-left, at 375, 768, 1024 and 1440 wide: one gutter above the first notice (32px, 24px at
782 and under), 12px between notices, 24px to the first block, the content's inline edges, a 4px
tone edge on the side reading starts from, text and link contrast of at least 4.5:1, a 24px
dismiss button 12px inside the far edge and level with the first line. With no update pending: a
region of no height.

**Not run.** The integration suite and the rest of the browser suite were not run here; CI runs
both. Right-to-left was checked by setting the document's direction, as the other browser tests
do, not on a site whose language is right-to-left: WordPress's own right-to-left stylesheet was
not loaded. Nothing was looked at in Firefox or Safari.

**What CI found on the first run.** Seven of the eight new browser tests failed there and passed
here. CI activates every plugin a fresh WordPress ships with, and Hello Dolly prints
`<p id="dolly">` on `admin_notices`: the tests expected three things in the region and met four,
and the region they expected to be empty was 62px tall. The fix was doing what it should: before
it, that paragraph floated in the top corner and the shell was drawn beside it, narrower for its
whole height (its right edge at 1118 of 1280 with the same paragraph and style put in the page
here); now it is a line of its own in the region, in the shell's ink, spaced like a notice and
not drawn as one. The tests were wrong to assume
that only the fixture prints on the hook. They now check the spacing of everything in the region,
check the notice styling of notices only, and empty the region in the page before measuring it
empty. Run here with a stand-in that prints what Hello Dolly prints, and without: 8 passed each
way.

**Left as it is.** `admin-controls.spec.js` and `coming-soon.spec.js` each keep their own copy of
the contrast calculation that `tests/e2e/helpers.js` now exports. The comment on the fixture
`corex-e2e-client-guide.php` says `scripts/setup-wordpress.ps1` copies it into `mu-plugins`; the
script does not, for that fixture or for the new one. On a development install both are copied by
hand.

## #264 — A message reaches the driver with every field it had, and #150 was closed without that being asked

Date: 2026-10-08 · Issue #150 · Specs: 085 (Phase 6) and 081 (FR-010) · Status: Final

The report, from a client site that sends from three mailboxes: #150 is closed and its defect is
still there at v0.43.3. The client carries a local patch for it and records the patch as an
exception to its framework baseline.

**What was true.** `MailService::deliver()` removes invalid recipients, and to do that it builds a
second `EmailMessage`. It built it from seven positional arguments, the seven the class had when
the service was written (spec 008). Spec 085 added `from` as the eighth and spec 081 added
`attachments` as the ninth, both defaulted so that no existing caller had to change. This caller
did not change, and so it passed neither. `MessageBuilder::send()` is the only caller of
`deliver()` and the `Mailer` seam sends through the builder, so on every such send the driver
received a null sender and no attachments, whatever the request carried.

**Why the issue was closed.** It was closed by the merge of pull request #153 (spec 085) and
released the same day in v0.38.0. The issue's own table listed seven files to change. Tasks T030
and T031 covered six. The seventh was `MailService`, and nothing in the spec named it. The tests
(T032, `PerMessageSenderTest`) assert that a request carries a sender and that the queue restores
it. None asked what the driver was handed. FR-011 and SC-005 were marked met on those. SC-005, "a
queued message and an immediate one leave from the same address", was even true: both left from
the configured one. Spec 081 merged an hour later (#154), added its tests to the same file at the
same two points, and lost its attachments to the same call.

So the issue was closed on a change that was real, tested, and incomplete, and no check between
the request and `wp_mail()` existed to say so.

**What was chosen.** `deliver()` passes `from` and `attachments`, by name. It is the change the
client carries as its local patch, written against v0.41.0. By name, because a constructor whose
tail is reordered then fails instead of putting one value in another's place.

**Rejected: a copy that cannot leave a field out**, by spreading `get_object_vars($message)` into
the constructor with the three recipient lists replaced. It would carry a tenth field with nobody
deciding whether that field needs inspecting first, and the sender is the example: it becomes a
header. A field is carried here on purpose. What stops
the next one being forgotten is a test: `MailServiceTest` builds a message with every constructor
parameter set, fails if the class has a parameter the test does not set, and requires the message
the driver gets to equal the one sent.

**The sender is inspected.** `HeaderGuard` has said since spec 008 that it guards "a subject,
from, reply-to", and `deliver()` gave it the subject and the reply-to. The driver does clean the
address, with `sanitize_email()`, and that does not refuse a line break. It removes what it does
not like and keeps the rest: `"info@example.com\r\nBcc: victim@example.com"` comes back as
`info@example.comBccvictimexample.com`, which `is_email()` accepts. No header is injected, and the
message would leave from a domain nobody owns. With the sender in the guard the message is
refused and logged as `rejected`, as it is for the other two. A sender that is only malformed
still falls back to the configured address at the driver, as spec 085 decided.

**Not in it: Email Studio.** An operator's reply from the Submissions inbox, a routed email and a
message composed in Email Studio are built in `EmailStudioSubmissionGateway`,
`EmailRouteMessageFactory` and `EmailStudioController` with no sender, and `EmailStudioService`
hands them to the driver without `MailService`. The issue's third mailbox, `contact@` for an
operator's reply, is that path. It drops nothing, because nothing is set, and choosing a sender
there is a feature with a setting behind it. It is listed in `PROGRESS.md`.

What was run:

| Check | Result |
|---|---|
| The new unit tests, on `main` at 390554e1 | 2 failed: sender `null`, attachments `[]`, a sender with a line break `sent` |
| `tests/Unit/Mail/MailServiceTest.php`, after | 8 passed |
| One message with a sender and one attachment through the `Mailer` seam on a real install, `main`'s `MailService`, `wp_mail()`'s arguments captured at `pre_wp_mail` | `From: Corex <the configured address>`, attachments `[]` |
| The same, with this branch's `MailService` | `From: Corex <noreply@example.com>`, attachments: the file's path |
| The unit suite | 2211 passed |

**Not run here.** The integration suite: this was built in a worktree with no WordPress under it,
and the install beside it runs another branch. The new test in `MailLifecycleTest` does what the
probe above did and runs in CI. No message was sent through a relay; `wp_mail()` was stopped at
`pre_wp_mail` in every run.

## #265 — The Data export was audited first, and the audit changed what the slice is

Date: 2026-10-08 · Spec: 103 (submissions inbox and exports), slice 7a · Status: Final

The owner, in the request the spec is built from: "even in the data export the operations is
poor". The plan said to audit it against FR-048's list before writing code, and to record what
was found. It is in the plan as D12. Three findings decided the slice.

**There are two Data exports on the screen.** The plan named the panel on the Export tab. The
Records tab has a dialog of its own. They post to one route and neither hands over a file: one
closes and says the export was queued, the other says to refresh the history. So the dialog the
spec asks for replaces two things, and is slice 7b.

**Excel was never offered.** The spec says the Data export "already has an xlsx writer". It did.
The screen offers the format where a source declares it, and `SubmissionsSource`,
`TableDataSource` and the registry's default all declared `exportXlsx: false`. The writer that
was there wrote every cell as text and needed PHP's `zip` extension. It is removed. The two
sources CoreX ships now declare Excel, under the ability that already guards their CSV: the same
people, the same records, another format. A source a client wrote declares its own, as before.

**A list was written as the word "Array".** Every value was `(string) $value`. A Data source
declares what each field is, so `DataExportTable` builds typed cells from that. Three readings
in it are choices:

- A stored date and time with no offset is written as it was stored. Nothing says which timezone
  a table's column is in, and moving it would be inventing one. A value that does carry an offset
  is moved to the site's time.
- Only a value shaped like a date and time is read as one. PHP reads "next week" as a date, and a
  field holding those words is not holding one. The first version of the table did exactly that;
  its test caught it.
- A day without a time stays text. A cell has no kind for a day alone, and writing one as a date
  and time would put a midnight on it that nobody recorded.

**One writer, one place for files.** The Data export writes through `Export\ExportWriters` into
`Export\ExportDirectory`, as the Submissions export does. Its rows between batches are in a
working file; `SubmissionExportSpool`'s reading and writing became `Export\ExportSpool`, which
both use. Only the columns asked for are written to it: a record's other fields have no business
on the disk, even for a minute.

**The two screens were left alone, and still work.** 7a keeps the answers they read. They gain
Excel and lose nothing. The download's shape is unchanged; only the file's name and contents are.

**Not done.** The dialog (7b). Expiry and deletion of a Data export's file: FR-048 does not list
them, and the file is kept as its post meta was kept. A working file of an export whose job never
finishes is not swept, for either export. The third, unlinked export route from spec 045 is left
registered.

What was run:

| Check | Result |
|---|---|
| `DataExportTableTest`, before the class existed | failed: class not found |
| `DataExportTableTest`, first version of the table | 1 failed: "next week" was written as a date |
| `DataExportTableTest` | 20 passed |
| `DataExportServiceTest` | 15 passed |
| `tests/Unit`, after rebasing onto #279 | 2252 passed |
| `tests/Integration`, real WordPress, after the same rebase | 544 passed |
| `data-management.spec.js`, a browser, on the two unchanged screens | 7 passed |

**Not run.** A produced Data workbook was not opened in a real spreadsheet; the Submissions
workbook was, in slice 3, and this is the same writer. The two screens were not looked at with
"XLSX" now among their formats.

## #266 — The Data export is the Submissions export's dialog, made from the same parts

Date: 2026-10-08 · Spec: 103 (submissions inbox and exports), slice 7b · Status: Final

FR-048 asks the Data export to meet what the Submissions export meets, and the story behind it
says why: somebody who has used one should be able to use the other without learning it. The
audit (DECISIONS #265) found two Data exports on the screen and that neither handed over a file.

**The parts were lifted out, not copied.** The scopes, the columns, the format, the
confirmation, the outcome and the footer were written inside the Submissions dialog. They are in
`admin/components/export/ExportParts.js` now, and both dialogs are made from them. A second copy
of that markup would have been a second place to drift. Each export supplies its own words: what
its scopes cover, what a column is, "submissions" or "records".

**So was the waiting.** `runExport()` asks for the export, takes its steps on request, reports how
far it has got, and saves the file. It was the body of a click handler and had no test of its
own. It has nine: saved, not started, failed in the job's words, failed without any, cancelled, a
step that could not be asked for, a step that moved nothing, an export that does not finish, a
file that cannot be fetched.

**The Submissions dialog was moved onto them with its behaviour unchanged**, and its nine browser
tests are the evidence: they passed before, after the styles moved, and after the parts moved.

**The styles moved to the shell.** The dialog's rules lived in the Submissions inbox's stylesheet,
which the Data screen does not load. They are in `corex-admin-shell.css`, beside the dialog they
belong to.

**The Export tab opens the dialog too.** It has no rows and no filters behind it, so there the
dialog offers everything, counted, and nothing else. Its page keeps the history, which reloads
when an export was made; the "Refresh" button is gone. A scope that cannot apply is left out there
and shown disabled on the Records tab, where ticking a row would make it apply.

**Columns keep the source's order**, whatever order they are ticked in. That order is the order of
the file's columns, and ticking "Name" last should not move it to the end.

**A refusal is said in the server's words.** "There is nothing to export." says more than "The
export could not be started."

**Found by the browser test: an export that stops when a record arrives.** The Export tab's test
exports everything in a model. Run beside the inbox's tests, which submit forms, it stopped with
"Bounded job counters are inconsistent.": the last batch held more records than were left of
the count, and the job will not count past its total. The Submissions export cuts each batch to
what is left; the Data export never did, before this spec or after slice 7a. It does now, and a
unit test has a record arrive between two batches. An export holds what was counted when it was
asked for.

**Found by measuring the new dialog: a name smaller than its description.** A scope's name and a
column's name took WordPress's 13px; the line under each was set to 14px. The Submissions dialog
has been that way since v0.43.2. The spacing review of that dialog (DECISIONS #257) measured gaps
and alignment and did not measure text sizes. The dialog has its own text size now, and the Data
dialog's browser test asserts that a name is not smaller than its description.

**Not done.** The Data dialog has no "Recent exports" inside it; the history is on the Export
tab, as it was, without sizes, expiry or deletion (FR-048 does not list them). PDF is slice 6, for
both. The wording functions of the Data dialog were written with their tests, not after a failing
run of them.

What was measured on the Data screen, and is asserted by its browser test:

| Measured | Result |
|---|---|
| Between the dialog's parts | 24px, all of them |
| A radio against the first line of its scope's name | level |
| A checkbox against its column's name | level |
| A choice's name against the line describing it | 14px and 14px; it was 13px and 14px |
| The file type control | 40px tall |
| On opening | focus is in the dialog |
| After Escape | focus is on "Export records" |

Looked at in dark, light, right-to-left and at 480px wide.

What was run:

| Check | Result |
|---|---|
| The arriving-record test, before the batch was cut | failed: "Bounded job counters are inconsistent." |
| `DataExportServiceTest` | 16 passed |
| `runExport.test.js` | 9 passed |
| `dataExportState.test.js` | 31 passed |
| Jest, `plugins/corex-config` | 396 passed |
| `submissions-inbox.spec.js`, a browser, after each of the three moves | 9 passed each time |
| `data-management.spec.js`, a browser | 8 passed, the two export tests among them: a workbook and a CSV saved from the Records tab, a CSV from the Export tab |
| The Data, inbox and admin-controls specs together, so that forms are submitted during the export | 1 failed before the batch was cut; 26 passed after |

**Not run.** A screen reader. The Export tab's page itself was not measured, only the dialog it
opens.

## #267 — The shared-host package's project root is `wp-content/`, and Composer generates its autoloader there

Date: 2026-10-08 · Spec: 061 (the shared-host `dist` builder) · Status: Final

Reported by the Muva session from `npm run build:dist -- --client=muva` on v0.43.3, and confirmed
in the source. The package could not load the framework, and had not been able to since the
builder was written:

1. Every CoreX plugin's main file requires `vendor/autoload.php` from its own directory or from
   two directories up. In the package that is `wp-content/plugins/<name>/vendor/` or
   `wp-content/vendor/`. The builder copied `vendor/` to the package root. No plugin found it.
2. Composer's generated map is relative to the repository: `plugins/corex-core/src/`,
   `addons/<name>/src/`, `packages/cli/src/`. The package holds add-ons under
   `wp-content/plugins/` and did not hold `packages/cli` at all, so a `vendor/` moved by hand
   would have loaded nothing from an add-on and there was no `wp corex`.
3. `vendor/` was copied as it stood. The guide said to run `composer install --no-dev` on the
   checkout first; skipping that put PHPUnit, Pest and Mockery in a web root, and doing it strips
   a working checkout of its test tools.
4. `wp/.htaccess` was copied as core. The guide lists it among the files a deploy never overwrites.
5. `verify:dist` reported "OK". It checked that folders exist. Nothing started a built package.

**Two ways to make it load were considered.**

(a) The builder generates an autoloader whose paths match the packaged layout.

(b) The repository's layout is kept beside WordPress (`plugins/`, `addons/`, `packages/`,
`vendor/` in the site root) and the folders under `wp-content/plugins/` point at it.

**(a) was taken.** (b) needs the plugin folders to be links or stubs. A shared host with no shell
cannot be counted on for links: that is the whole reason this package exists. A stub, a small
plugin file that requires the real one elsewhere, puts the real plugin outside `WP_PLUGIN_DIR`,
and then `plugins_url()` and `plugin_basename()` cannot place it. `PluginRealpathRegistrar` exists
because a linked add-on already has that problem, and it solves it by telling WordPress the real
path behind each link, which a stub does not have. (b) would also put the framework's source in the web root a second time.

**Where the autoloader goes was decided by what the code already does.** All sixteen plugin and
add-on main files look two directories up. In the package that is `wp-content/`. `Boot` and
`CoreServiceProvider` take `dirname(COREX_CORE_PATH, 2)` as the project root, and the CLI package
takes three directories up from its `src/`. So `wp-content/` is the package's project root:
`wp-content/vendor/` for Composer, `wp-content/packages/cli/` for the CLI. With that, no plugin
file changes. Only a comment in `corex-core.php` does, to say that the second candidate is also
the packaged one. The alternative, leaving `vendor/` in the package root and adding a third
candidate to sixteen files, would have left the CLI's and the core's idea of the root pointing at
`wp-content/` anyway.

**Composer runs inside the package.** `planAutoload()` translates each `psr-4` entry of the root
`composer.json` through the copy plan (`addons/corex-email/src/` is copied to
`wp-content/plugins/corex-email/`, so the entry becomes `plugins/corex-email/src/`). The builder
writes that `composer.json` and a copy of `composer.lock` into `dist/wp-content/`, runs
`composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
--no-plugins` there, and removes the two files. The classmap Composer writes is relative to its
own directory, so the package can be uploaded anywhere. `require` and `require-dev` are kept as
they are so the lock file's content hash still matches; `--no-dev` is what keeps the dev packages
out. `COMPOSER` and `COMPOSER_VENDOR_DIR` are removed from the environment of that one process,
because either would send the install somewhere other than the package. The checkout's `vendor/`
is not read.

**Rejected: copying the checkout's `vendor/` and pruning it**, or running
`composer dump-autoload` over a copy. What is in a checkout's `vendor/` is whatever was last
installed there, which is not necessarily what the lock file says and usually includes the dev
packages. The lock file is the statement of what a release runs on.

**Rejected: translating every autoload kind.** Only `psr-4` is used here. `files`, `classmap`,
`psr-0` and a namespace mapped to several directories stop the build with a message that names
them, because leaving one out without saying so is the same defect again. A `psr-4` entry whose
directory is not in the package (another client's site) is left out with a warning.

**The check.** `scripts/shared-host-dist-probe.php` is run by `verifyDist()` in a PHP process of
its own. It defines `ABSPATH` and the two WordPress functions `corex-core.php` calls while it is
included, includes the packaged `corex-core.php`, and reports an error unless that file loaded
`wp-content/vendor/autoload.php`. Then, for every namespace in `corex-release.json`
(`autoload.psr4`), it walks the packaged directory in order and loads the first class it can,
which has to come from that directory. A class that extends a WordPress class cannot load there
and is passed over. Last, every file PHP included has to be inside the package. The verifier also
fails on a `.htaccess` in the package root and when Composer's `installed.json` says dev packages
were installed.

The list of namespaces comes from the manifest, which the builder writes. A namespace the builder
forgot would not be asked for. So the planner refuses to plan when a framework plugin, an add-on
or the CLI package has a `src/` directory and no namespace mapped to it.

The Jest test builds a small repository whose core plugin file is the real
`plugins/corex-core/corex-core.php`, so the places that file looks are what is tested. It needs
`php` and `composer`, which the Jest job already sets up, and downloads nothing.

**The probe does not start WordPress.** It has no database. The `client-site-layout` job does:
it builds the package for the site `make:site` generates, copies that job's `wp-config.php` into
it, and requires `Corex\Boot::booted()`, `Corex\Foundation\Application` loaded from
`dist/wp-content/plugins/corex-core/src/`, and `wp corex routes:list` to run. That job is not a
required check (`PROGRESS.md`).

**Not in it.**

- `wp-content/vendor/` can be requested over HTTP, as the plugins' own `src/` can. It holds the
  production packages only. `/wp-content/vendor/autoload.php` answered 200 with an empty body
  when the package was served here. No deny rule is written into the package: the package
  carries no `.htaccess` of any kind, and on nginx one would do nothing.
- The Azure pipeline's agent has no `wp/`, so its package has no WordPress core. That was true
  before and is listed in `PROGRESS.md`.
- The package is not checked for the built admin and block bundles.

What was run:

| Check | Result |
|---|---|
| The new Jest test against the builder as it was | 9 of 12 failed: no autoloader where the core plugin looks, `.htaccess` and `vendor/phpunit` in the package, no CLI package, `verifyDist` accepting all of it |
| The same test, after | 12 passed |
| A real build of the whole framework in a copy of the tree that had a development `vendor/` (3,115 files, PHPUnit and Pest among them) and a `wp/.htaccess` | "dist verified OK"; the probe loaded a class from each of 17 namespaces; `wp-content/vendor/` holds 377 files in 2.5 MB; no `.htaccess`; the name and size of every file in the checkout's `vendor/` the same before and after |
| `verify:dist` on that package with `wp-content/vendor/` moved away | FAILED, naming the autoloader and each namespace |
| WordPress 7.1.2 installed from that package on a database made for it, PHP 8.3.6 | 16 plugins active; `Corex\Boot::booted()` true; `Application` loaded from the package; `wp corex doctor`, `routes:list` and `mode get` ran |
| `wp corex make:site Acme --starter` run from the packaged CLI | The site was written from the packaged stubs |
| The package rebuilt with that site, its plugin and theme switched on, served by `wp server` | `/` 200, `/wp-login.php` 200, `?rest_route=/corex/v1` 200 with the routes |

**Not run.** No package was uploaded to a shared host, and none was served by Apache or
LiteSpeed. No admin screen was opened: the copy the package was built from had no built bundles.
The Azure pipeline was not run. The generated site's theme was compiled with the repository's
`sass` and `wp-scripts`, not from its own `npm install`, which the CI job does.

## #268 — The 500 on `GET corex/v1/flows` was PHP's compiled copy of a method, not a flow; and a flow that cannot be read is left out of a list

Date: 2026-10-08 · Pull request #286 · Run 37746210110, first attempt · Status: the fault is named; its cause is a lead, not a proof

The report: `GET corex/v1/flows` answers 500 `flow_error`, "Request could not be processed.", now
and then in the browser job, and goes on doing so until the run ends. On 2026-10-08 eight
`submissions-inbox` specs failed on it on #282. The brief's guess was a stored flow that cannot be
read, left by an earlier test or half-written while another worker read it.

**What was true.** The guess is wrong. The owner allowed the failed attempt's artifact to be
downloaded, and `test-results/server-logs/php-error.log` has the same line sixteen times, from
07:57:51 UTC to 07:58:03:

    Middleware pipeline error: Corex\Forms\Flow\FlowRestMapper::summary(): Argument #2 ($version)
    must be of type , Corex\Forms\Flow\FlowVersion given, called in
    .../plugins/corex-forms/src/Flow/FlowController.php on line 52

The method is declared `summary(Flow $flow, FlowVersion $version)`. PHP was handed a `FlowVersion`
and refused it against a type whose name it printed as an empty string. No stored value can do
that: the argument was built and was of the right class, and the type it failed is in the
compiled method, not in the database. The nginx access log from the same artifact gives the
order. `GET corex/v1/flows` answered 200 with one flow's summary twenty-eight times, from 07:56:05
to 07:57:49, through that same method. The seed then created flow 28, read it, published it,
submitted to it and ran its marked test, every one answered 200. From 07:57:51 the list answered
500, twice per test: once for the Forms screen and once for the seed's search. `php-fpm.log` shows
no worker dying and no restart in that time.

A method that is correct on disk, worked for a minute and three quarters, and then fails for
every worker until the run ends, with a type name PHP itself cannot print, is the copy of that
method that php-fpm's workers share: what OPcache holds. That is a reading of the evidence.
Nobody looked inside it while it was in that state, and the runner is gone.

**The lead: the job was running PHP's JIT.** The step this pull request adds printed OPcache's
status on its first run, a run that passed: 52 of 128 MB used, nothing wasted, no restart of any
kind, the string buffer 94% full (7.5 of 8 MB), and `"jit": {"enabled": true, "on": true,
"opt_level": 5, "buffer_size": 268435440}`. That is the tracing JIT with a 256 MB buffer. PHP's
default is no JIT, and nothing in the workflow asks for one. The setting is in
`/etc/php/8.3/fpm/conf.d/99-pecl.ini` on the runner: an override in a file read just before it
left the JIT on, the same override in a file read just after it switched the JIT off, and
php-fpm lists no file between the two. That file is not in this repository and was not read.
Who writes it was not established; the provisioning action sets PHP up with
`shivammathur/setup-php`, which is the first place to look. A JIT rewrites a method into machine code once it has run
often enough, and keeps the result where every worker uses it. A method that answers
twenty-eight times and then fails for every worker is what a wrong rewrite would look like.

**What is not known.** Whether that is what happened. The JIT is a lead because it fits and
because it is the one thing in that status a default PHP does not have. Nothing shows it
compiled that method, and the string buffer near its limit is a second thing an ordinary run
has. A search turned up reports against older PHP versions of type checks that fail under
OPcache until php-fpm is restarted. None was read closely, and none was matched to this version
or shown to be this fault. The three failures of 2026-10-04 on #211 (DECISIONS #228) look the
same from outside, and their log was not kept, so they are assumed to be this and not shown to
be.

**No test reproduces it, and none was written to pretend to.** The brief asked for the failure
in a test first. A PHP test cannot make PHP forget a parameter's type. Before the artifact was
asked for, the seed's eight requests were replayed against the development install, one PHP
process per request as a browser makes them, twice, and every one answered 200. Reading the code
had also shown that every state a half-written flow can be in answers 422, not 500.

**What was done about the 500.**

- `Pipeline::run()` logs the exception's class and the file and line beside its message. This
  time the message named the method. A date that will not parse names nothing.
- The browser job prints the last 40 lines of PHP's error log and php-fpm's own OPcache status
  (asked through a probe request, since the command line has a separate cache) in its output, on
  every run. A failed run is read there first, and the artifact has to be asked for and expires
  in seven days. An ordinary run's status is the thing to compare the next failure with.
- The job's php-fpm runs with the JIT off (`opcache.jit=disable`, in a `conf.d` file named to be
  read last), and a step asks php-fpm whether it is, prints where php-fpm reads its settings
  from, and stops the job if the JIT is on. That step failed the first time it ran, which is how
  the file that overruled the first attempt was found. This is the one change to how the
  job runs PHP. It is taken on a lead and not on a proof, for three reasons: no site CoreX is
  written for runs a JIT unless somebody turned one on, so the job tested a PHP its users do not
  have; it costs nothing the suite needs; and it can be shown wrong. If the fault returns with
  the JIT off, the status printed beside it is the next evidence.
- OPcache itself stays on, at the runner's limits. Switching it off would slow each request that
  reaches PHP (about 1,300 of the 13,299 in the failed run; the rest were files nginx served) by
  an amount nobody has measured, in a suite with specs that are sensitive to time. Raising the
  string buffer would be a second change on a second guess.

**The weakness the brief described is real, and separate.** Ruling flows out meant reading every
way a flow is read. One stored flow that cannot be built into a `Flow` failed every list:

- `WpFlowStore::create()` wrote the type row first and the payload last. The type row is what a
  list selects by. A request that stopped between them left a flow with no payload, and
  `FlowRepository::all()` threw for all of them: 422 on the route, for every flow.
- A stored date that will not parse threw a `DateMalformedStringException`, which the gateway
  does not map: 500.
- `FormCatalog`, the Overview count and the Insights count catch whatever `all()` throws and
  show no flows, without a word.

That was fixed here because the brief asked for it and it could be shown failing first. It did
not cause the 500 and does not prevent it.

**Decision: left out and logged, not shown as broken.** A flow that cannot be read is left out of
`FlowRepository::all()`, and the log gets a warning with the record's post id and what was wrong
with it. The Forms list leaves out, the same way, a flow whose current draft is not stored or
cannot be read. Reasons:

- Every reader of the list gets the flows that work. Before, one bad record took the Forms
  screen, the form filters and the counts down together.
- A row for a flow that cannot be opened, edited or published from the screen is a row that
  does nothing. The builder reads a flow through the same code that just refused it.
- Showing it properly is a screen change: a state for the row, what it says, how the operator
  removes the record. That is its own piece of work and was not hidden inside a defect fix.

What it costs: the record stays stored and nothing on a screen says so. Its slug stays taken, so
creating a flow with that slug is refused with "A flow already uses this slug." and the reason is
only in the log. That is recorded under Client impact in the changelog.

Three limits on the leaving-out, each with a test:

- Only what a stored value can cause is treated as unreadable: a refused argument, a date that
  will not parse (`catch (Exception)`). A `TypeError` is not caught. Had it been, the fault this
  entry opens with would have reached the screen as an empty list with a warning beside it.
- A version that cannot be read is never left out of `versions()`. Its number is part of what
  cannot be read, and `appendVersion()` asks that list whether a number is taken.
- Reading one flow by id or slug still refuses, with `UnreadableFlowRecord` (422, naming the
  record). Leaving out is for lists.

**The write path.** `WpFlowStore::create()` writes the payload, then the slug, then the type. Until
the type is there the record is in no list and `FlowRepository::find()` answers null for it, so a
request that stops part-way leaves a post nothing reads. If any of the three writes fails the post
is deleted and the call throws. Not done: `FlowService::create()` still stores the flow and then
its first draft as two steps with nothing joining them, so a request that stops between them
leaves a flow without a draft. The Forms list now leaves that flow out and says so in the log. The
orphan post an interrupted `create()` leaves is not swept.

What was run:

| Check | Result |
|---|---|
| The seed's eight requests replayed on the development install, one process each, twice | every answer 200; the 500 did not occur |
| `PipelineTest`, new case, before the change | failed: the line was `Middleware pipeline error: boom` |
| `UnreadableFlowRecordTest`, before the change | 9 failed, 1 passed (the one that keeps a `TypeError` loud) |
| The three new `FlowControllerTest` cases against unchanged code, real WordPress | 3 failed: the list answered 422; the half-written record was listed; no exception |
| `tests/Unit` | 2263 passed |
| The 13 files under `tests/Integration` that mention flows, real WordPress, this branch's classes | 76 passed |
| OPcache's status, printed by the first CI run of this pull request (166 specs passed) | JIT on, tracing, 256 MB; no restart; string buffer 94% full |
| The step that asks php-fpm about the JIT, with the override in `99-corex-no-jit.ini` | failed, as it is meant to: the JIT was still on |
| The same step with the override in `zzz-corex-no-jit.ini`, and CI on that commit | JIT off (`opcache.jit` is `disable`, buffer 0); 166 specs passed in 2.6 minutes; all six jobs passed |

**Not run.** The whole integration suite and the browser suite were not run locally; CI runs
both. No JavaScript changed. Nothing was done to make PHP fail this way on purpose. One green run
with the JIT off proves nothing about a fault that shows up now and then, and the run with it on
(2.1 minutes) against the run with it off (2.6) is one sample each, not a measurement.

## #269 — A PDF export is signed as CoreX's, written by mPDF, and holds five hundred records

Date: 2026-10-08 · Spec: 103 (submissions inbox and exports), slice 6 · Status: Final

**What "signed off with the identity" meant.** The spec read it as the site's brand and a block
naming the person who exported. The owner, asked: "use a corex signature, that what i did mean, pdf
exported should has the corex logo on it as it has the copyrights of the tool". So every page of
a PDF carries the CoreX logo, "Exported with CoreX", a copyright line and its page number. The
site is named in the header, in words. Who exported, and when, is a fact the document opens with,
not a signature. US8, FR-019 and the assumption were rewritten to say this.

**mPDF, chosen by a spike** (plan D3), on the owner's "use the best one and recommended". The
test was Arabic: an answer in Arabic has to come out shaped, joined and in order, on a page that
reads right to left. A trial document was rendered to images and read: mPDF did that from its own
fonts, and needs PHP's `gd` and `mbstring`. It is GPL-2.0-only; WordPress is GPL too. The other
candidate was a print-styled page, which is not a file until somebody prints it, and the spec
asks for a file. No other library was tried.

**What it costs: about 94MB in `vendor/`, 88MB of it fonts**, and the same in the shared-host
package. The library carries a font for every script it can write and chooses by the text. The
fonts are kept whole, so a document is right in any language a site collects answers in. Pruning
the ones a site will never use is a packaging choice, left to the builder and not made here. The
weight is the first entry under Client impact.

**Five hundred records, said before the export is asked for.** An export is written in one
request at its last step. On the development machine five hundred rows took 6.6 seconds and
68MB, and a thousand took 17.3 seconds and 116MB: the cost grows faster than the rows. No host
was measured. The server refuses a PDF of more,
and the dialog names the limit under the choice and in place of starting. A workbook has no such
limit and the sentence points to it. The limit travels with the preview's answer, so the dialog
and the server cannot disagree about the number.

**PDF is offered where it can be written.** The preview's answer lists the formats the server can
write. Without the library or either extension the dialog offers a workbook and CSV, as before,
and nothing fails.

**The logo is an image, rendered from the approved SVG.** mPDF draws the mark's squares exactly
and mangles the wordmark's outlines. `scripts/generate-logo-print.py` renders
`corex-contrast.svg` to `corex-lockup-print.png` and records its digest in the manifest under
`print`; a contract test holds the file to that digest and to the path the PDF is given. Nothing
was redrawn.

**Direction is decided cell by cell.** On a right-to-left page mPDF reordered every cell: a phone
number read "9700 699 101 20+", a date and an English sentence likewise. Each cell, heading and
line of the header and footer is given the direction of its first letter. Seen in the rendered
page, not inferred.

**More than eight columns are not a table.** They do not fit a page at a size somebody can read.
Each record is then a block of its own, a question beside its answer, kept on one page.

**The colours are written out.** A PDF has no stylesheet of the site's to read tokens from, so
`PdfExportLayout` names its ink, rule and tint as constants. The token contract is about what the
admin and the theme draw; a document on paper is neither.

**Not in the unit suite: a real PDF.** The unit harness wraps file streams, and mPDF's font reads
come back short under it, as a PHP warning. The layout is tested there as the HTML it hands over;
a real PDF is written in the integration suite and by the two browser tests.

**Found by looking at a produced document, and not fixed here.** The Data export of Form
submissions offers a column per answer and leaves each empty, in every format: the source
declares those fields and its rows do not carry them. A ticked-rows export loses "Submission" as
well, because ticked rows are read in the detail view's shape. Both predate this slice. Queued as
a task of its own.

**The shared-host builder prunes the packaged `vendor/`.** CI refused the package of a generated
client site: mPDF ships its own `.github`, Composer installed it as shipped, and `.github` is a
path the package may never hold. Copied trees were already filtered as they were copied; nothing
filtered what Composer installed, because no production package had shipped a forbidden path
before. `prunePackagedVendor()` removes the forbidden names from the packaged `vendor/` after the
install. The verifier is unchanged, so a forbidden path anywhere else still refuses the package.
The real package, built to a scratch directory with this change, verified: 191.1MB unpacked,
93.9MB of it `vendor/`, 91.9MB of that mPDF. Local checks had not built the package; CI did.

## #270 — An export row is the source's to shape, and a source says so through `ExportableDataSource`

Date: 2026-10-08 · Spec: 103 (submissions inbox and exports), after slice 7b · Status: Final

Found by opening a real export of "Form submissions" from CoreX Data. The file began
`Submitted,Form,Submission,Email,Name,Message` and its row read `"2026-10-08
13:28",corex-inbox-e2e,"email: someone@example.com",,,`. A ticked row lost "Submission" as well.

**What was wrong.** A source has three shapes. `query()` answers the row its table shows, keyed by
column. `record()` answers what its detail view shows. An export needs a third: a value under
every field the source declares, because the dialog offers a column per declared field and
`DataExportTable` reads each cell from the row by the field's key. Nothing asked for that third
shape. `DataExportJobHandler` took `query()` rows for a filtered or whole export and `record()`
for ticked rows. For a managed table the three happen to be one shape, so its exports were right.
Form submissions declare a field per answer, show one summary in their table and labelled pairs
in their detail view, so neither shape the job read held an answer under its key.

**The row shape belongs to the source.** `Corex\Config\Data\ExportableDataSource` extends
`QueryableDataSource` and `FieldAwareDataSource` and adds `exportRows(DataQuery $query)` and
`exportRowsOf(array $ids)`. Each row holds a value, or `''`, under every key `fields()` declares.
The job reads those two and nothing else, so the scope a record was chosen through cannot change
what is written for it. `SubmissionsSource` builds `fields()` and the export row from one private
map of answer key to field key, so a column the dialog offers is a column the row fills.
`TableDataSource` returns its table rows, which is what its fields are.

**It is the export's adapter, and that changes what a third-party source must do.**
`DataSourceService::hasAdapter()` answered yes for an export when a source was queryable and had
fields. It asks for `ExportableDataSource` now, as it asks for `WritableDataSource` before a
write. A source that declares `exportCsv` or `exportXlsx` without it is reported `no_adapter` and
the screen does not offer the export. The other choice was to keep reading `query()` and
`record()` from a source that has not said what its export row is. That keeps the defect for
exactly the sources nobody here can test, and writes a file that looks complete. Not offering the
export is the failure a person can see. No add-on in this repository registers such a source; the
two test fixtures that did were changed. It is the first entry under Client impact.

**Not changed, and checked.**

- The Records table shows Date, Form and Submission for form submissions, not a column per
  answer. `RecordsTable.js` renders `columns()`, and `tableRow()` is the same four keys as before.
  That is intended: which answers exist differs from form to form, and a table has one set of
  columns. The export dialog is where single answers are chosen.
- The record dialog still gets `record()`: labelled pairs for a submission, the flat row for a
  managed table, which its editor reads. Unit tests pin the table's row, the detail view's
  record and the export's row for both sources.
- Personal-data classes are as they were declared: an answer whose key reads as an email or a
  phone is contact data, one that reads as a message is content, and the summary is content. A
  unit test pins them. An answer keyed `name` is still classed as none; that is how it was
  declared and it is not this change's to decide.
- `TableDataSource::record()` already returned the row shape, so a ticked managed-table export was
  right before. It no longer depends on that.

**Changed on the way.**

- Two answers whose keys become the same field key (`Email` and `email`) were declared as two
  fields with one key. They are one field, and a row takes whichever the submission has.
- `WpSubmissionsReader::fieldKeys()` read the meta of each sampled submission with a query of its
  own. An export now asks for those keys with every batch, so the meta is loaded for all of them
  with one call to `update_postmeta_cache()`.

**Left open.**

- A managed table's attachment column is written to an export as the JSON the screen renders a
  link from (`{"id":…,"name":…,"url":…,"missing":…}`). It was so before this change. What an
  attachment should read as in a file is a decision nobody has made.
- A ticked export whose record is deleted between two batches re-reads a record: the job's offset
  is the count of rows written, not of ids consumed. A selection is at most one page, and a batch
  holds a page, so it needs a batch size below the selection to happen.
- PDF (slice 6, pull request #289) is not on `main`. Its writer takes the same sheet the CSV and
  Excel writers take, so it gets these rows when it lands; this branch did not run it.

What was run:

| Check | Result |
|---|---|
| The export through the real job, real `SubmissionsSource`, before the change (Pest unit) | ticked: `"2026-10-08 13:28",contact,,,,`; filtered and everything: summary present, answers empty; the workbook the same |
| A stored submission exported through the routes on real WordPress, the root checkout's unchanged classes | 2 failed, the same two ways |
| The same integration test on this branch's classes (the run prints which file each class came from) | 2 passed |
| `tests/Unit`, `php -d memory_limit=512M vendor/bin/pest` | 2275 passed |
| `tests/Integration`, real WordPress, this branch's classes | 549 passed |

**Not run.** The browser suite: no JavaScript, stylesheet or markup changed. The integration suite
was run from a session worktree through a bootstrap that puts this branch's classes in front of
the root checkout's, whose plugin entry files are another branch's; CI runs it on this branch
alone.

## #271 — A hidden login is not handed out by the addresses that forward to it

Date: 2026-10-08 · Spec: 069 (login protection), a defect · Status: Final

Reported from a production site that hides its login: signed out, `/wp-signup.php` answered 302
to the hidden address. Reproduced on the development install with hiding switched on for the
probe, and two more found the same way:

| Asked for, signed out | Before | After |
|---|---|---|
| `/wp-signup.php` | 302 to the hidden address | 404, the same bytes as a page that was never there |
| `/wp-register.php` | 301 to the hidden address | 404, the same bytes as a `.php` file that was never there |
| `/wp-admin/customize.php` | 302 to the hidden address | 404, as `/wp-admin/` answers |

**One cause.** Hiding is safe because every URL core builds for the login is rewritten to the
custom address (DECISIONS #140). That same rewriting turns any redirect core aims at the login
into a redirect to the secret. On a single site `wp-signup.php` does nothing but
`wp_redirect( wp_registration_url() )`; `redirect_canonical()` does the same for
`wp-register.php`; and the Customizer calls `auth_redirect()` on `setup_theme`, long before the
guard answers the request on `wp_loaded`.

**The two forwarding addresses are default endpoints now**, on a single site, whatever the
registration setting: nothing core builds links to them there, and the registration address
itself still works. On a network `wp-signup.php` is the public sign-up page, and
`wp-register.php` forwards to it, so both are left alone.

**An admin request that will be hidden is told nothing until it is answered.** On `setup_theme`,
the first moment the login state is knowable and before core's own hooks there, the guard decides
whether this request is one it will hide. If it is, the URL filters leave the default address
alone until the 404 is rendered, so anything that bounces the request to the login early sends it
to `wp-login.php`, which is hidden too. The Customizer's own check is taken off for that request,
so it reaches the 404. The rewriting is back on for the 404 itself: a real missing page links to
the custom address wherever the theme links to the login, and this one must not differ.

**Not decided at `plugins_loaded`.** The login state could be read there, and reading it would
fix the current user before a plugin that authenticates differently had hooked in.

**Not a general rule against redirecting a signed-out visitor to the login.** A theme that
protects a members' page with `auth_redirect()` sends its visitors to the custom address on
purpose. Refusing that would break the site to protect an address it chose to show.

**What CoreX cannot hide.** `/wp-activate.php`, `/wp-admin/install.php` and
`/wp-admin/upgrade.php` run with `WP_INSTALLING`, so WordPress loads no plugin for them. The
first redirects to `/wp-login.php?action=register`, which is hidden; the other two answer 200.
They show the site is WordPress and never name the custom address. Only the web server can
answer them differently, and CoreX writes no server rule. The guide says so.

**Seen on the way, and WordPress's own:** `/?wp_customize=on`, signed out, answers 500 with or
without CoreX's hiding. It names nothing.

What was run:

| Check | Result |
|---|---|
| A probe of 19 well-known addresses, signed out, hiding on, unchanged code | three redirects to the hidden address, as in the table |
| The same probe with the changed guard served to the probe's requests only | none; the three answer 404 |
| `LoginRouteGuardTest`, new cases, before the change | 4 failed |
| `tests/Unit` | 2304 passed |
| `LoginUrlRewritingTest` on real WordPress, with the changed guard | 11 passed |
| The browser test "no well-known address forwards a signed-out visitor to the hidden login", unchanged guard | failed: `/wp-signup.php` answered 302 |
| The five browser tests of a hidden endpoint, changed guard | 5 passed |

**What CI corrected.** The first version of the browser test compared `/wp-register.php` with a
WordPress "not found" page, byte for byte, and failed in CI on a fix that held. `wp-register.php`
is not a file. Apache, on the development install, hands a missing `.php` to WordPress; CI's
nginx answers it itself and WordPress is never asked, so there the address could not leak before
the fix either. The test compares it with what the server answers for a `.php` file that was
never there. Status and the absence of a redirect were right on both servers.

**Not run.** The whole integration suite and the whole browser suite; CI runs both. The root
checkout was in use by two other sessions, so this was built and verified from a scratch
worktree, with the changed class served to its own requests by a temporary must-use plugin,
since removed. No multisite install was probed: that `wp-signup.php` is left alone there is held
by a unit test only.

## #272 — `@wordpress/scripts` 36 is taken, and the tests stay on Jest

Date: 2026-10-08 · Spec: none (toolchain) · Status: Final

Dependabot's #240 proposed `@wordpress/scripts` 34 → 36 and was held as "a toolchain major that
wants its own verified pass" (PROGRESS.md; DECISIONS #244 expected it to retire two overrides).
It failed three checks. This is that pass, done on
36.1.0, and #240 is closed in its favour.

**What 36 changed for this repository.**

- It no longer ships Jest, `@wordpress/jest-preset-default` or its Babel transform, and
  `wp-scripts test-unit-js` now runs Vitest. `config/jest-unit.config.js` and
  `config/babel-transform`, which `jest.config.js` extended, are gone. That is why #240's Jest
  job failed: there was no runner.
- Its default lint config gives test files Vitest's rules.
- stylelint 17 and its WordPress config refuse the deprecated `clip` property; wp-prettier 3.9
  formats 33 of our files differently.
- It declares Node `^22.22.2 || ^24.15.0 || >=26.0.0`.

**Decision: keep Jest.** 64 suites and 715 tests are written to Jest's API and to the WordPress
preset's console assertions. Upstream documents a supported path for that ("Keep an existing Jest
suite" in its `vitest-migration.md`) and this follows it: `jest`, `jest-environment-jsdom`,
`babel-jest`, `@wordpress/jest-preset-default` 14.2.0, `@babel/core` and
`@wordpress/babel-preset-default` 8.55.0 are root dev dependencies; `jest.config.js` names the
preset and the transform; `test:js` is `wp-scripts test-unit-jest`; `eslint.config.js` is built
from `@wordpress/eslint-plugin` with `eslint-plugin-jest` on test files. `react` and `react-dom`
18.3 are named as dev dependencies because 36 declares them as peers and npm otherwise resolves
the peer to React 19 against a tree that holds 18.

**What that costs, said plainly.** npm marks `@wordpress/jest-preset-default` and
`@wordpress/jest-console` "no longer supported". Upstream's words are that maintenance covers the
adapter and this tested combination and promises no new features and no compatibility with future
Jest or Node releases. So this is a holding position: the suite moves to Vitest some day, as its
own piece of work, and nothing forces the day yet.

**Overrides.**

| Override | Before | After | Why |
|---|---|---|---|
| `lighthouse` | `^13.5.0` | removed | `@wordpress/e2e-test-utils-playwright` 3 asks for `^13.4.1` itself, and 13.5.0 is what installs |
| `postcss-selector-parser` | `^7.1.6` | kept | PROGRESS.md said it would go with 36. It does not: `cssnano` 6 still asks for 6.x in five places, and without the override GHSA-rj75-hqrm-r3gf is back |
| `markdownlint-cli` → `minimatch` | `^3.1.5` | removed | `markdownlint-cli` 0.49 imports `minimatch` as a module; on the pinned 3.x `wp-scripts lint-md-docs` died at import. It asks for 10.2 itself, which carries no advisory |
| `markdownlint-cli` → `js-yaml`, `smol-toml`; `katex` | none | `^5.4.3`, `^1.9.0`, `^0.18.2` | GHSA-r3ph-w7gj-g6xm, GHSA-r4xh-jqrq-34v2 and GHSA-238p-pmpm-9mq7, each with a patched release its parent's range stops short of. All three arrive with `markdownlint-cli` |

The six bounded exceptions are unchanged.

**Node.** `engines`, the Azure pipeline's `NODE_VERSION`, the readiness command's dependency list
and four documentation pages said Node 20. They say 22.22 now. GitHub's workflows already asked
for `'22'`, which resolves above the floor.

What was run, on the development machine (Node 22.14.0, below the new floor; npm warned and every
command ran):

| Check | Result |
|---|---|
| `npm run test:js` | 64 suites, 715 tests passed; `main`'s last CI run on the old toolchain: 64 and 715 |
| `npm run lint:js` | 0 errors, 54 warnings (`jsdoc/reject-function-type` 35, `jsdoc/reject-any-type` 11, `jsdoc/escape-inline-tags` 8, all new with the toolchain); it was 93 errors before `--fix` and two hand edits |
| `npm run lint:css` | clean, after `clip-path: inset(50%)` replaced `clip: rect(0, 0, 0, 0)` on `.corex-form__label--hidden` |
| `npm run build`, 169 files hashed before and after | every JavaScript bundle byte-identical; the 12 corex-forms stylesheets differ by the one declaration; the asset manifests differ in layout (one array entry per line) and so in hash |
| The asset manifests' dependency lists against an earlier build's | 18 compared, none differ |
| `npm run verify:dependencies` | PASS, 6 findings, 6 accepted exceptions; 10 findings and 4 unbounded before the overrides |
| `npx wp-scripts lint-md-docs README.md` | before: `SyntaxError: Named export 'minimatch' not found`; after: runs and reports six style findings in the README, which nothing gates |

**Not run.** The browser suite locally; CI runs it. The hidden label was not looked at in a
browser: the two declarations are the same visually-hidden technique, and the 1px box with
`overflow: hidden` does the hiding either way. `docs-app` is a separate npm project and was not
touched; its `postcss-selector-parser` override stays.

**Left open.** The 54 lint warnings. The README's six markdown findings. Vitest.

## #273 — A send is deferred without Action Scheduler, and the queue runs whichever dispatcher is bound

Date: 2026-10-08 · Issue: #271 · Spec: 024 (deferred tail), the mail queue · Status: Final

Reported from a client site whose lead form answered three to four seconds late: both
notification emails were sent inside the request, with the `mail_queue` flag on.

**What was wrong.** The flag decorates the Mailer seam with `QueuedMailer`, which defers a send
only when the bound `MailQueueDispatcher` says a backend is available. The only dispatcher was
`ActionSchedulerDispatcher`, and Action Scheduler is not part of CoreX. On a site without it
every send was inline, whatever the flag said. A site could not fill the gap either: running a
queued send was not on the interface, and the worker hook acted only when the bound dispatcher
was `ActionSchedulerDispatcher`.

**What changed.**

- `CronMailDispatcher` is bound when Action Scheduler is absent, the way bounded jobs already
  choose between the two (`ConfigServiceProvider`).
- `MailQueueDispatcher` gains `HOOK` (`corex_mail_send`), `name()` and `handle()`. The worker
  calls `handle()` on whichever dispatcher is bound.
- A queued attempt's provider is the dispatcher's name. It was the literal `action-scheduler`.
- The request's array form moved to `MailRequestPayload`; the static `toArray()`/`fromArray()`
  on `ActionSchedulerDispatcher` are gone. No caller outside the tests was found in CoreX or in
  the three client repositories on this machine.

**Why the request is not in the cron event.** WordPress reads the cron array on every request and
rewrites all of it on every schedule. A queued request is a subject, a body and a context. It
is kept in an option of its own (`corex_mail_queued_<id>`, not autoloaded) and the event carries
the id. The id also makes every event distinct: WordPress refuses a second event with the same
hook and arguments inside ten minutes, which would have dropped the second of two identical
notifications.

**Why the option is deleted before the send.** A send that stops PHP must not be repeated by a
later run, and WP-Cron can fire one event twice when two requests spawn it together. So a
message is sent at most once, and a run that dies mid-send loses that one message. Action
Scheduler, which records and retries, is still preferred wherever it is installed.

**Why `wp_cron()` on `shutdown`.** WordPress looks for due events when a request starts. An event
scheduled during a form's request would otherwise wait for the site's next visitor, which on a
quiet site is exactly when a lead matters. `wp_cron()` honours `DISABLE_WP_CRON` and the
one-spawn-a-minute lock, so on a site that runs cron from its own scheduler the message waits for
that.

**When nothing can be scheduled** (the option is not stored, or WordPress refuses the event) the
message is sent at once. A deferred message nothing will ever run is worse than a slow response.

**Not for long lists.** `PublishNotifier` sends one request per subscriber. Through WP-Cron that
is two writes per message and a cron array that grows by an entry each, rewritten each time: fine
for tens, slow for thousands. The README and the guide say to install Action Scheduler for that.

What was run:

| Check | Result |
|---|---|
| `tests/Unit/Email/CronMailDispatcherTest.php` | 4 passed; with the `delete_option()` before the send removed, the once-only test fails |
| `tests/Integration/Mail/MailQueueWithoutActionSchedulerTest.php`, real WordPress, flag on | passed: nothing handed to `wp_mail()` inside the request, one event asked for, the hook delivers one message and one log; with the provider binding only `ActionSchedulerDispatcher` it fails on "1 is identical to 0" |
| `tests/Unit`, `php -d memory_limit=512M vendor/bin/pest --testsuite=Unit` | 2319 passed |
| `tests/Integration/Mail` | 7 passed |

**Not run.** A real cron run: the integration test catches the event as WordPress is asked to
schedule it and fires the hook itself, because a real event on a development install could be run
by another request without the test's stand-in for `wp_mail()`. So the loopback request that
`wp_cron()` makes was not observed. No measurement of a form's response time was taken on a host.

**Left open.**

- A site with the flag on, `DISABLE_WP_CRON` set and no scheduler of its own accumulates
  `corex_mail_queued_*` options that nothing sends. Readiness does not look for that.
- Uninstalling CoreX Mail does not remove options still queued.
- The client impact of the interface change: a site's own `MailQueueDispatcher` has two methods
  to add. None is known.

## #274 — A code-defined form gets a contract, starting with what it reads (spec 104)

Date: 2026-10-08 · Spec: 104 (a code-defined form's wording, markup and protection), slice 1 · Issues: #248, #264 · Status: Final

Issues #248 and #264 each said they wanted a spec and not a patch, and that they overlap. Spec 104
is that spec: a form defined in code states its wording, states that it is protected, and may
supply its own markup from parts CoreX publishes; then Turnstile and hCaptcha get a widget. Four
slices. This is the first.

**Nobody was asked the five readings the spec is written to.** They are under its Assumptions and
are the owner's to overrule: wording and protection are stated in the form's code and not in the
block's settings; markup is supplied per form and not through a site-wide filter or a theme
template; protection is opt-in for a code-defined form; Turnstile and hCaptcha are placed as their
visible widget; no provider is called by a test.

**Why per form and not a filter.** corex-forms and the captcha add-on fire no WordPress hook at
all. They extend through the container, their registries and events. A filter on the rendered form
would be the first, and would hand every plugin on the site every form's markup as a string. A
method on the form's own class is typed, is found by reading the class, and cannot be reached by
code that does not own the form.

**Slice 1: three methods, empty by default.** `Form::submitLabel()`, `successMessage()` and
`errorMessage()` return `''`, and the renderer reads empty as CoreX's own wording. The plan first
had the defaults return the stock strings. That needed the stock label written twice, once as the
default and once in the renderer for a form whose label is blank; with empty as the default each
string is written once. A label of nothing but spaces is CoreX's label: a button with no name
cannot be operated by voice.

What was run:

| Check | Result |
|---|---|
| `tests/Unit/Forms/FormBlockRenderTest.php` before the change | 2 failed (stated wording, markup as text), 6 passed |
| The same, after | 8 passed |
| `tests/Integration/Forms/FormBlockRenderingTest.php`, real WordPress | 4 passed; a form stating "Request a call" is rendered with it through `do_blocks()`, and a `<` in its confirmation is escaped by WordPress |
| `tests/Unit` | 2323 passed |

**Not run.** The browser suite: the markup of a form that states nothing is unchanged, and the
three strings reach the page through attributes and a text node the runtime already reads. No
rendered check was made of a stated label on a page.

**Left open.** Slices 2 to 4. `captcha.action`, the global setting, has no reader in corex-forms
or the add-on; found while planning, not changed.

## #275 — The shared-host package keeps a few of the PDF library's fonts, and a PDF is written with what is installed

Date: 2026-10-08 · Spec: 103 (submissions inbox and exports), slice 6, after it merged · Status: Final

DECISIONS #269 kept the PDF library's fonts whole and left pruning to the builder "as a packaging
choice, not made here". The first client site made the choice necessary the same day: it is
updated by a zip its owner uploads through a hosting panel's file manager, the largest zip proven
there is 29.7MB, and it does not collect answers in the scripts most of the weight is for. The
owner, asked to choose between the whole set, a pruned set and no PDF library in the package:
"you decide the best for me".

**Measured**, on the framework-only package, zipped with deflate level 6:

| Package | Entries | Unpacked | Zipped |
|---|---|---|---|
| Without the PDF library | 6,278 | 99MB | 27MB |
| Every font | 6,847 | 191MB | 71MB |
| The default from now on | 6,594 | 117MB | 33MB |

The library's fonts are 87MB. Six of them, for Chinese, Korean and three ancient scripts, are
54MB.

**Kept by default: DejaVu, XB Riyaz and Lateef**, with every licence file. DejaVu has Latin,
Greek, Cyrillic and Hebrew letters and is the document's default font; the other two are what the
library writes Arabic, Persian, Urdu, Pashto and Sindhi in. `--pdf-fonts=all` keeps everything.
Not offered: naming scripts one by one. Two sets cover the sites there are, and a third can be
added when a site needs one.

**Not chosen: leaving the library out of the package.** That was the client session's
preference, and it is the smallest package. But the PDF export was the owner's own request, made
from that client's inbox, and a package without the library would withhold it from the site it
was asked for on. Six megabytes of zip buy it back.

**The library does not degrade by itself.** With a font's file missing it throws "Cannot find TTF
TrueType font file" at the first text that asks for that font, and the whole export fails:
reproduced before the change, with one Chinese answer. It checks its font list, not the disk. So
`PdfFonts` gives it the list of fonts whose every file is installed, and the directory they are
in. A font with its regular file and without its bold is left out, because a bold heading in it
would stop the document the same way. Text in a script with no font installed is written in the
default font and prints as empty boxes where that font has no letter.

**Said where it is met.** The guide to the shared-host package has the table and the option; the
Submissions guide says what a PDF from such a site prints; the package's manifest records which
set it was built with.

**A site installed with Composer is unchanged**: every font is there, and the list given to the
library is the library's own.

## #276 — A form defined in code is challenged by the same check a flow is

Date: 2026-10-08 · Spec: 104 (a code-defined form's wording, markup and protection), slice 2 · Issue: #264 · Status: Final

A site that chose reCAPTCHA and saved its keys had its flows protected and its code-defined forms
not: nothing let such a form ask, its page loaded no provider script, and the submit route's
sanitiser dropped a token before anything could check it.

**One check.** `ProtectionStage::verifyCaptcha()` is now `SubmissionChallenge::verify(token, slug,
protection)`, returning a `SubmissionChallengeOutcome`. The flow pipeline's stage calls it and
stores what it stored before; `FormSubmissionService` calls it for a code-defined form. The stage's
twelve tests run with every assertion unchanged, through a helper that builds the stage as the
container does. `FormChallengeContextFactory::forContext()` had one caller and is now
`forForm(slug, protection)`.

**Opt-in, and why the default differs from a flow's.** A flow is protected unless its Protection
tab says off. `Form::protection()` returns `['captcha' => 'off']` and a form opts in with `'on'`;
`CodeFormProtection::of()` reads anything else as off. A form drawn by a site before this cannot
carry a token. Had code-defined forms inherited the site default, such a form would have refused
every submission on the day its site took this release with a provider configured. Not asked of
the owner; it is under the spec's Assumptions.

**Where the check sits.** After the trap field and before validation, so a submission that fails
it has no answer judged, nothing stored and no listener run. The token is removed from the input
before validation: it is not an answer and reaches no listener.

**A refusal with its own code.** The route derived a refusal's `code` from its HTTP status, so a
failed challenge would have read `error`, like an unknown form. A refusal's payload was either
nothing or the field errors, keyed by field name, so a code could not ride in that array: a form
with a field named `code` would collide with it. `SubmissionRefusal` is a typed payload, and
`SubmitController` answers its code. The response is `422`, `code: "challenge_failed"`, and the
message the provider script already shows when it cannot get a token.

**The action travels with the form.** The reCAPTCHA script looked the action up in a map keyed by
the form's name, and a flow and a code-defined form can share a name. It reads the action from
the submitted form's own token field now, which already carried it and was read by nothing. The
map is no longer sent to the page. `ProtectedFormRegistry` still answers the only question the
asset controller asks of it: does this page hold a protected form.

What was run:

| Check | Result |
|---|---|
| The library, a directory holding only the kept fonts, its own font list | `MpdfException`: cannot find `Sun-ExtA.ttf` |
| The same with the list cut to the fonts present | written; Latin, Arabic and Hebrew print, Chinese, Korean, Thai and Hindi are boxes |
| `PdfFontsTest`, `PdfExportTest` (unit) | pass, with the rest of `tests/Unit` |
| `PdfExportWithFewFontsTest`, real WordPress, one font family installed, answers in six scripts | a PDF is written |
| `SubmissionExportFileTest`, real WordPress, every font | 15 passed, the PDF among them |
| `tests/build-shared-host-dist.test.js` | 16 passed, three of them new |
| The real package built with the default fonts, then verified by the builder | verified; 117.2MB, 33.4MB zipped |
| A PDF written with nothing but that package's `vendor/` and its `PdfFonts` | written; English, Greek, Russian, Hebrew, Arabic and Urdu print, Chinese, Korean and Thai are boxes |

**Not run.** The whole integration and browser suites; CI runs both. The 33MB is the framework
alone: a client's own plugin and theme add to it. Nothing was uploaded to a host, so that the
host accepts 33MB is not shown, only that it is close to the 29.7MB it has accepted.
| `tests/Unit/Forms` after the extraction, before anything was built on it | 219 passed |
| `FormSubmissionServiceTest`, the five new cases before the service changed | all failed (the service took no challenge) |
| `tests/Unit/Forms`, after | 227 passed |
| `tests/corex-captcha-v3.test.js`, the shared-name case before the script changed | failed; 7 passed after |
| `tests/Integration/Forms/ProtectedCodeFormTest.php`, real WordPress, the provider stood in for | 3 passed: refused with the code and nothing stored; stored without the token; the block as the container wires it carries the field, declares the form, and the add-on then enqueues both scripts |
| `tests/Unit` / Jest / `tests/Integration/Forms` and `Mail` | 2331 / 717 in 64 suites / 77 |

**Not run.** No provider was called: a real reCAPTCHA token was never obtained or verified here.
No browser saw a protected code-defined form. The browser test the tasks named (T021) was not
written: the browser suite has no fixture that registers a form in code, and the integration test
covers what CoreX decides (the field, the declaration, the enqueue) on real WordPress. What a
browser would add is the script tag on a served page and the token round trip against a stood-in
provider.

**Left open.**

- Turnstile and hCaptcha still place no widget; that is slice 4, and until then a protected
  code-defined form on a site that chose one is accepted without a token, as a flow is
  (DECISIONS #258).
- The four add-ons that check `captcha_token` on their own routes (bookings, careers, newsletter,
  profile) have nothing that produces one. Out of this spec's scope and still true.
- `captcha.action`, the global setting, has no reader.
- A code-defined form stores no spam evidence. A flow records the provider's verdict with the
  submission; a code-defined form's listeners are handed the answers and nothing else.

## #277 — A site draws a form from parts CoreX publishes, and the stock form is drawn from the same ones

Date: 2026-10-08 · Spec: 104 (a code-defined form's wording, markup and protection), slice 3 · Issue: #248 · Status: Final

A form whose design was not the stock form's needed its own renderer, and writing one meant
reproducing what the front-end runtime reads (the endpoint, the security token, the exported
schema, the messages, an error place per field, a status place, the trap field) from four classes
none of which said a site could rely on it.

**The seam is `Form::markup(FormParts $parts): ?string`.** `null`, the default, is the stock
form. `FormParts` is handed to the form for one render and supplies each piece by name:
`attributes()`, `hidden()`, `status()`, `fieldAttributes()`, `error()`, `control()`, `label()`,
`field()`, `submit()`. Not a filter on the rendered HTML and not a theme template, for the reason
in #274: corex-forms fires no WordPress hook, and a method on the form's own class is typed and
is the form's alone.

**The stock form is composed from the same parts.** `FormBlockRenderer` builds a `FormParts` and
either hands it to the form or draws the stock form with it. A part a site uses is therefore the
part CoreX uses, and the two cannot drift. Two recorded forms pin the stock output byte for byte:
the shipped contact form, and a form with one field of every kind and every presentation knob.
They were recorded from the renderer before it was changed and pass after.

**What a site passes in cannot displace what CoreX sets.** `attributes()` and `control()` take
the site's own attributes; a `class` is added to CoreX's and any other name CoreX already set
keeps CoreX's value. A site cannot point the form at another endpoint or give a control another
field's name by accident. `submit()` stays `type="submit"` whatever it is passed.

**A form that would fail silently is not shown.** If a form's markup lacks `attributes()`,
`hidden()` or `status()`, a visitor gets nothing, `_doing_it_wrong()` reports which, and somebody
who can edit posts sees a notice naming the missing parts in the form's place. The check looks for
one mark of each part in the returned HTML. It does not parse the site's design and does not check
each field's wrapper: a field with no error place still submits and is still refused by the
server, and its message has nowhere to go. Left open below.

**No `challenge()` part yet.** The plan lists one for a visible widget. reCAPTCHA v3 shows none and
its token field is in `hidden()`. The part arrives with the widget, in slice 4.

What was run:

| Check | Result |
|---|---|
| The stock form against its two recordings, before and after the renderer was changed | identical |
| `tests/Unit/Forms/FormPartsTest.php` | 10 passed |
| `FormBlockRenderTest`: a form's own markup; a missing part as a visitor and as an editor | 16 passed |
| `corex-runtime-hand-drawn-form.test.js`: the real runtime against the hand-drawn form the PHP test pins | 2 passed: an empty submission writes "This field is required." into the site's error place, marks the hand-written control invalid and sends nothing; a valid one posts the answers and the trap field to the form's endpoint with the security token and confirms in the site's status place |
| `FormBlockRenderingTest` on real WordPress | 5 passed: a form's own markup through `do_blocks()` with WordPress's escaping; one missing two parts renders nothing signed out and reports "hidden(), status()" |
| `tests/Unit` / Jest / `tests/Integration/Forms` | 2349 / 722 in 65 suites / 71 |

**Not run.** No browser. The tasks named a Playwright test in light and dark, left-to-right and
right-to-left (T037); it was not written, for the reason in #276: the browser suite has no
fixture that registers a form in code. In its place the runtime is run in jsdom against the
markup the contract really produces. What that does not show is anything visual: a hand-drawn
form's layout is the site's own stylesheet, and the stock styles a site may inherit through the
`corex-form` class were not looked at on one. The parts tests were written after the parts.

**Left open.**

- A field whose wrapper or error place a site left out is not detected.
- `.corex-form__notice`, the editor's notice, has no style of its own.
- With JavaScript off a form does not submit, stock or hand-drawn: it has no `action`. The runtime
  guide said it fell back to the submit route. It says what happens now; nothing was built.
- The runtime guide's contract for "anything else that renders a form" is documentation of what
  the runtime reads, not a second supported way to build a code-defined form.

## #278 — Turnstile and hCaptcha show their widget, and a submission without their token is refused

Date: 2026-10-08 · Spec: 104 (a code-defined form's wording, markup and protection), slice 4 · Issue: #264 · Status: Final

Both providers could be chosen, given keys and tested, and nothing placed their widget on any
form. For a day a submission without their token was let through on purpose (#258), because
refusing it refused every submission. This places the widget and ends that allowance in the same
change, as the spec required (FR-032): a provider that can be selected now challenges.

**One script for both.** The two providers have the same shape: load their script with explicit
rendering, ask it to render into an element with a site key, and take a token in a callback.
`corex-captcha-widget.js` renders into each `.corex-form__challenge`, writes the token into that
form's `captcha_token` field, holds a submission made before the visitor has passed (with a
message, before the runtime's handler sends anything), and resets the widget once the server has
answered, because a token is spent by one submission. A refusal by the browser's own check sends
nothing, so the token is kept. Each provider is handed the container its documentation names:
Turnstile a selector, hCaptcha the element.

**Load order.** CoreX's script is enqueued first and the provider's depends on it, because the
provider calls `corexCaptchaWidgetReady` by name when it has loaded. The script also tries on
`DOMContentLoaded`, for a provider that was already there. A place is rendered once.

**The site key is on the form.** Each challenge place carries `data-corex-sitekey`. Nothing is
localized but two messages.

**For a form drawn by hand** the place is `FormParts::challenge()`: empty unless the form is
protected and the provider shows a widget, and required then (the missing-part check names it).
A hand-drawn form made in slice 3's way has to add it to be protected by one of these two.

**What was removed.** `FormChallengeContextFactory::driverPlacesNoWidget()` and the allowance in
`SubmissionChallenge`; `CaptchaDiagnostic::NO_WIDGET`; the `captcha.widget` gap in the admin's
capability facts; the sentence in the driver setting's help. `providerConfigured()` is true for
all three providers with a secret.

What was run:

| Check | Result |
|---|---|
| The flipped tests before the change: an empty token under Turnstile and hCaptcha; the diagnostic for both | 4 failed |
| `tests/Unit/Forms` and `tests/Unit/Captcha` after | 286 passed; the stock form still byte-identical |
| `tests/Unit` / Jest | 2356 / 732 in 66 suites |
| `corex-captcha-widget.test.js`, the provider stood in for | 10 passed |
| `ProtectedCodeFormTest` on real WordPress, each provider | the block carries the token field and, for Turnstile and hCaptcha, the challenge place with the site key; the add-on enqueues that provider's two scripts |
| **Turnstile's real script**, its published always-pass test key, on a throwaway local page with CoreX's script and a bare form | rendered; `XXXX.DUMMY.TOKEN.XXXX` written into the field; the submission reached the form's own handler; after `corex:form:success` the field was empty, a submission was held with "Please complete the challenge before sending.", and a new token arrived after the reset |
| **hCaptcha's real script**, its published test key, the same page | loaded and rendered its frame into the place; no token, because its test widget waits for a click |

**Not run.**

- hCaptcha's token round trip. Its widget needs a person to click it and I do not complete
  challenges, test key or not. Rendering against the real script is what was seen.
- Neither provider's server-side check with a real token. `RemoteCaptcha` is unchanged and was
  not called against a provider.
- A CoreX page. The live check used a bare form on a local page, not a flow or a Form block on
  the development install, whose captcha settings were not changed. So the widget was not seen
  inside the form's grid, in either theme or direction; the stylesheet rule for the place is
  unverified by eye.
- The browser suite locally.

**Left open.**

- Somebody should submit one protected form on a real site with each provider before relying on
  it. That is the gap between what was checked and what was built.
- The four add-ons that check `captcha_token` on their own routes still have nothing that
  produces one.
- `captcha.action`, the global setting, still has no reader.
- An invisible or managed-size widget is not offered; the provider's default is used.

## #279 — Spec Kit in a client repository: its state is not tracked, its hook is off, and its directory is named

Date: 2026-10-08 · Issue: #251, item 1 · Spec: 102 (update-safe client sites) · Status: Final

In a client repository everything outside `sites/` is the framework's, and a change there is
drift. Running the documented Spec Kit workflow there made such changes, so a generated site's
guide said to write specs by hand. The issue named three writes and asked for the scripts to be
taught a per-site root, which it said wanted its own spec. Read again, two of the three were not
about where a spec lives, and the third needs no change to a script.

| What the issue named | What it is | What changed |
|---|---|---|
| `/speckit-specify` persists the active feature to `.specify/feature.json`, which is tracked | One checkout's working state. On `main` it named a single feature, whatever each checkout was working on | Ignored and removed from the tree. Spec Kit's scripts read it when it is there and fall back to the branch name when it is not |
| The `agent-context` extension rewrites the managed section of the root `CLAUDE.md` | An optional hook, run after `/speckit-specify` and `/speckit-plan` | Both hooks are `enabled: false`. The section it kept still said to read spec 068's plan, with spec 104 in flight; it is removed |
| `common.ps1` resolves the specs directory as `<repo root>/specs` | True only when nothing names a directory. `Get-FeaturePathsEnv` takes `SPECIFY_FEATURE_DIRECTORY` first, then the state file, and only then the branch name | Nothing in Spec Kit. The generated `AGENTS.md` gives the command: `/speckit-specify SPECIFY_FEATURE_DIRECTORY=sites/<client>/specs/<work-item>-<slug> …` |

**Why not teach the scripts a per-site root.** `.specify/scripts/` is Spec Kit's, vendored. A patch
there is a fork to carry through every Spec Kit update, to do what an existing override already
does. What a client loses by naming the directory is automatic numbering, which counted the
framework's `specs/` and was wrong for a site anyway.

**Why the hook goes for the framework too.** It is the same file in both repositories, and a
client cannot turn it off without drift. In the framework its output had been stale since
spec 068 with nobody noticing, which is its own answer to whether it was used. `CLAUDE.md` already
says to read the active spec and `PROGRESS.md`.

What was run:

| Check | Result |
|---|---|
| The three new assertions, before the change | failed: the state file tracked, both hooks on, the guide saying "by hand" |
| After: `tests/repo-hygiene.test.js`, `tests/repository-ownership.test.js`, `tests/Unit/Cli` | 77 and 168 passed |
| Spec Kit's own `check-prerequisites.ps1 -Json -PathsOnly`, with `SPECIFY_FEATURE_DIRECTORY=sites/acme/specs/001-lead-form` | `FEATURE_DIR`, `FEATURE_SPEC`, `IMPL_PLAN` and `TASKS` all under `sites\acme\specs\001-lead-form` |
| The same script with no variable and the state file naming that directory | the same paths; `git status` shows no change to the state file |

**Not run.** The whole workflow in a real client repository: no `/speckit-specify` was run in
Muva or Perego, and `npm run verify:framework` was not run there after one. What is shown is
that Spec Kit's path resolution takes a site's directory and that the two files it used to
change are no longer the framework's to protect. A client repository created before this still
tracks `.specify/feature.json` until it takes the release that removes it.

**Left open.** A site generated before this keeps the old paragraph in its `AGENTS.md`; that file
is the client's. `.agents/skills/` and `.claude/skills/` still describe the hook as something that
may run; they are Spec Kit's text.

## #280 — The hosting package leaves out what only describes the source

Date: 2026-10-08 · Spec: none (packaging) · Status: Final

Reported from the first site on shared hosting, by fetching them: each plugin's `README.md` and
`composer.json` could be read from the web. They say what is installed and at which version to
anybody who asks. Not a way in by itself, and the first thing somebody looking for one reads.

**What is left out.** `README.md`, `composer.json` and `package.json`, from every tree the
builder copies that is not WordPress core: CoreX's plugins and add-ons, its theme, the
command-line package, and a client's plugin and theme. Nothing on a running site reads one. The
only readers are release commands (`wp corex version`, `wp corex readiness`), which read the
repository's root files and are not run on a host.

**WordPress core is left as it ships.** Core carries its own (`wp-includes/sodium_compat/composer.json`
among them). They are WordPress's, the same on every WordPress site, and removing them would
make the packaged core differ from the release it claims to be.

**`vendor/` is left as Composer installs it**, and that is the part not fixed: 28 `README.md`
and `composer.json` files of third-party packages are still in a real package, and
`vendor/composer/installed.json` lists every package and version. Removing files from another
project's package is how a later update breaks; the answer for a directory nobody should fetch
from is a server rule, and the package ships none by design, because the host owns its
`.htaccess`.

An older assertion in the builder's tests said no `composer.json` exists anywhere in a package.
It meant the one Composer is given to work from, in `wp-content/`, and was true of everything
only because the fixture's WordPress had none. It asserts what it meant.

What was run:

| Check | Result |
|---|---|
| The new test on the unchanged builder | failed, listing seven files in the fixture's plugins, themes and command-line package |
| `tests/build-shared-host-dist.test.js` after | 17 passed |
| The real package, built to a scratch directory | 3,036 entries under `wp-content/`; none of the three names outside `vendor/`; 28 inside it |
| `verifyDist` on that package, and the probe that loads it in a PHP process of its own | `ok: true`, no errors; every namespace loaded |

**Not run.** No package was uploaded to a host, and no request was made to one for these paths.

**Left open.**

- `wp-content/vendor/` can be fetched from: third-party `README.md` and `composer.json`, and
  `vendor/composer/installed.json`. A host should refuse requests into it. Nothing in CoreX
  says so to an operator, and no rule is shipped.
- From the same audit and not done: the coming-soon page still prints WordPress's generator tag,
  the RSD link, feed links and the emoji script; the REST index lists every `corex/v1` route to
  anybody (they answer 401 or 403); CoreX has no setting for security headers.

## #281 — A trashed submission is CoreX's: its own record, and none of WordPress's trash clock

Date: 2026-10-08 · Spec: 105 (trash, restore and delete a submission), slice 1 · Status: Final

Reported from a client's production site: the inbox could not remove a submission, so a test lead
stayed in it. Slice 1 is the half that loses nothing: move to the trash, and restore.

**The post is `trash`, and CoreX put it there.** Every read of submissions asks for `private`
posts: the inbox, its counts, the exports, the Data source, retention, insights. A trashed
submission that is `trash` is out of all of them with no query changed and none forgotten. A flag
on a `private` post would have needed a clause in each, and the first one missed puts a trashed
lead in an export.

**Not with `wp_trash_post()`.** It deletes on the spot when `EMPTY_TRASH_DAYS` is 0. And it writes
`_wp_trash_meta_time`, the one thing WordPress's daily `wp_scheduled_delete()` selects a trashed
post by: 30 days later the submission is gone for good, with nothing recorded and a file uploaded
with it left on disk. `WpSubmissionTrashStore` sets the status itself and writes
`corex_trashed_at`, `corex_trashed_by` and `corex_trashed_via`. A test runs WordPress's clean-up
beside a post trashed the WordPress way, which it takes, and one trashed by CoreX, which it
leaves.

**So nothing deletes a submission from the trash yet.** That is deliberate for this slice and
said in the guide and under Client impact. Slice 2 deletes for good; slice 3 gives the trash its
own clock and adopts what the retention panel trashed the old way.

**One service removes.** `SubmissionTrashService` checks the person's scope, pins the version
they were shown, writes each submission's history, and records one activity entry per action:
who, when, how many, the ids. No submitted value, name or address. Several submissions are all
checked before any is moved, so a stale or forbidden one moves nothing.

**The trash is a view, not a status.** `view=trash` on the list. A submission keeps its status,
owner, notes and history while it is there. It can be opened and read; everything that changes a
submission asks `findWorkflow()`, which answers only for the inbox, so a trashed one refuses
every change without a service to teach. The controller says why: 409, "This submission is in
the trash", where the refusal was a 404 that is also true of a submission that never existed.

**Bulk trash and restore are bulk actions**, through the two-step preview that already bounds a
selection and refuses a stale one. **Undo is restore**: one mechanism.

**Found in the browser: the pane trashed a stale copy.** Opening a submission marks it read,
which changes it. The confirmation held the record as it was at the click, and the server refused
it: 409, "changed after it was loaded", with the dialog gone and nothing said. The pane trashes
the submission as it is when the person confirms. The unit and integration tests could not have
seen this; the first browser run did.

**Found by looking: an eighth column was cut off.** "Moved to trash" was added to the table's
seven and ran past its frame at 1440 pixels. It stands where "Notification" does in the inbox,
which says nothing of a trashed submission. Measured at 1280, 1440 and 1680: the trash table is
as wide as the inbox's to within 8 pixels. The inbox's own table runs 108 pixels past its frame
at 1280 and scrolls there; that is how it was and is not changed here.

What was run:

| Check | Result |
|---|---|
| `SubmissionTrashTest`, real WordPress: the record, WordPress's clean-up, the two views by scope, restore, the routes and their guard, the 409 | 8 passed |
| `tests/Integration/Submissions`, real WordPress, after merging main | 46 passed |
| `tests/Unit`, after merging main | 2365 passed |
| Jest, `Submissions/__tests__` | 111 passed, 17 of them new |
| Browser, the trash test, first run | failed: the route answered 409 for the stale copy |
| Browser, `submissions-inbox.spec.js`, this branch's inbox | 10 passed, the nine that were there and the new one |
| The Trash view and a trashed submission's pane, captured at 1440 and looked at | the cut-off column, fixed; the rest as designed |

**Not run.** The whole integration and browser suites; CI runs both. `EMPTY_TRASH_DAYS` of 0 was
not exercised: it is a constant, and the store never calls the function that reads it. A person
restricted to one team was tested in the service and the reader, not through the browser. Built
from a scratch worktree with the inbox served to the tests' own requests by a temporary must-use
plugin, since removed.

**Left as it was, and worth its own look:** when an action in the pane fails, the inbox shows
the reason behind the open pane, where it cannot be seen.

## #282 — A release package says what it holds, and four questions about installing one are decided

Date: 2026-10-08 · Spec: 107 (a release installed from the admin), the spec and slice 1 · Status: Final

**The four decisions.** The spec's first drafts ended with four questions that were the owner's.
He answered them together: "decide the best for me regarding the 4 questions and continue". What
was decided on that, with the reason and the cost of each, is in the spec under "Decided for the
owner" and is his to reopen:

1. It works on a site that already runs CoreX. A site's first installation stays a step done by
   hand, as the first client site's was.
2. Going back restores the previous release's files and leaves the data. CoreX copies its own
   tables before a release and never puts the copy back over live tables by itself: that would
   delete whatever arrived since.
3. A site's own code is always part of the package, replaced together with the framework.
4. CoreX is not the site's backup. He had asked for backup and restore "better than backup
   plugins"; a backup inside the framework is unreachable on the day the framework is broken.
   What a backup plugin cannot do, a push that never overwrites submissions and pulling
   production data down, is the next two specs.

**What planning found.** The spec said a site would check "the zip `build:dist` makes" against
"the checks `verify:dist` runs". Read at `9302016f`: `build:dist` fills a folder and nothing zips
it; the package's description held no requirement and no measure of its contents; the decisive
check in `verify:dist` loads the package in a PHP process of its own, which a host with no shell
cannot start; CoreX's Maintenance mode answers after every plugin has loaded, so it cannot hold
while plugins are replaced; and nothing of CoreX survives its own folders being swapped. The plan
answers each (plan.md, D1 to D15). Two of them are this slice.

**Slice 1: the description.** `corex-release.json` is schema 2. It adds what the release needs
(`requires`, read from `corex-core.php`'s headers, the one place it is stated), the WordPress it
was built with, `release_paths` (each plugin, each theme, `wp-content/packages`,
`wp-content/vendor`) and, for each, its files, bytes and a hash. `ReleaseManifest` reads it on a
site and refuses, each for its own reason: something that is not a package, one built before
this, one built by a newer CoreX, one that leaves something out, and one that names a folder a
release does not own.

**Which folders a release may name.** Exactly four shapes: one plugin, one theme, the
command-line package, the shared code. Not `wp-content/plugins` as a whole, not `uploads`, not
the must-use plugins, not WordPress itself, not the installer's own place, not a folder inside
a plugin. Everything a site later does with a package comes from this list, so it is checked
before anything else is believed.

**The hash, and how two languages are held to it.** SHA-256 over one line per file (path, size,
the file's SHA-256), sorted as bytes. The builder computes it in Node; a site computes it in PHP
over what it unpacked. A folder in `tests/Fixtures/Releases/hashed` and its recorded description
are asserted by a Jest test and by a Pest test, so either implementation drifting from the
record fails. A third, in Python, written only to check the record, gave the same number.
`.gitattributes` marks that folder `-text`: its bytes are the test.

**The zip.** `npm run build:dist -- --zip`, named for the client, the version and the build
time, with the description at its root. `adm-zip` was already installed for other tools and is
now a named dev dependency, because the builder imports it.

**Only `--zip` loads it.** The first push imported it at the top of the builder, and CI's client
job failed: that job builds the package before any `npm ci` in the repository root, on purpose,
and until then the builder had needed nothing but Node. It is loaded when a zip is asked for.
Without the root's packages, a plain build is unchanged, and `--zip` builds and verifies the
package and then says what the zip needs. Run with no `node_modules` above it: the builder
loads, and `zipPackage` gives that sentence.

**`verifyDist` checks the measure too.** A package changed after it was described is refused where
it is built, before a site would refuse it. Three older tests spoil a built package on purpose
to test another check; they now measure it again first, so each still fails for its one reason.

What was run:

| Check | Result |
|---|---|
| The five new builder tests, before the builder changed | failed |
| `tests/build-shared-host-dist.test.js` and `tests/release-content-hash.test.js` after | 30 passed |
| `tests/Unit/Releases` | 26 passed |
| The description a real build writes, read by the class a site reads it with, in a PHP process of its own | understood: version, client, requirements, WordPress version, every release path |
| The real framework package, built to a scratch folder | schema 2; 19 release paths holding 2,633 files and 27MB; `verifyDist` ok; a 34MB zip. Build 53 seconds, verify 1, zip 89 |
| `npm run verify:dependencies` | PASS, 6 findings, 6 accepted exceptions |

**Not run.** Nothing on a host. No zip was opened by PHP: reading the zip itself is slice 2.

**Left open.**

- The zip takes 89 seconds to write, longer than the build. `adm-zip` compresses in one thread
  in JavaScript. Tolerable for something done once a release; noted.
- A package built before this (schema 1) is refused by a site. There is none to migrate: no
  site has installed from the admin yet.
- The plan put the release-owned folders at about 90MB. Measured, they are 27MB; the rest of
  the 117MB package is WordPress, which a site does not replace.

## #283 — A submission is deleted for good with what is tied to it, and stays in the trash if a file will not go

Date: 2026-10-08 · Spec: 105 (trash, restore and delete a submission), slice 2 · Status: Final

Slice 1 lost nothing. This is the half that cannot be undone: a site can now remove what somebody
sent. What a deletion removes was left to CoreX by the owner ("you decide the best for me").

**What is tied to a submission was read from the code, not from the spec.** The answers, notes,
history and delivery records are post meta and go with the post. Stored elsewhere:

- **Uploaded files** are protected attachments with no parent. An answer's value is the
  attachment's id. Deleting the post would have left the file on disk with nothing pointing at
  it. The store finds them by the answer, and takes only an attachment that is marked protected
  and was uploaded through a form, never one an answer merely shares a number with.
- **Email attempts and captured copies** are the email add-on's own records, tied by the
  attempt's id, which the submission holds in three places. A captured copy holds the whole
  message.
- **Mail log rows are tied to nothing.** A row holds a recipient and a subject and neither a
  submission nor an attempt. FR-011 asked for "the mail log rows of those emails"; there is
  nothing to select them by, and selecting by the submitter's address would remove rows of other
  submissions. FR-011 is corrected. Removal by address is slice 6's, where the erasure is by
  address. The guide says what is not removed.

**The inbox does not learn how email is stored.** `SubmissionEmailRecords` is a seam in core,
bound to "there are none" until the email add-on binds its own, as `SubmissionEmailGateway` is.
The add-on finds an attempt by its id inside each record's serialized payload and removes a
record only when it names that attempt itself: the attempt, an attempt made again from it, and
their captured copies. Removal is an interface of its own, `EmailAttemptRemoval`, not a method
added to `EmailStudioStore`: four test doubles implement that one and none of them removes.

**The inbox does not need the forms plugin's file store either.** `AttachmentStorage` is bound
by `corex-forms`, and the inbox works without it. The trash store removes an uploaded file
itself, with the same refusal: only what is marked as a protected form upload.

**A file that will not go keeps its submission.** Files first, then email records, then the
post. If a file cannot be removed the submission is left in the trash, because deleting it would
lose the only record of where the file is. The others in the same action are still deleted, and
the result names each that was not, with why. Every earlier bulk action was all or nothing; this
one is not, because half a deletion that says so is better than none.

**A permission of its own.** `SubmissionAccessScope::$canDeletePermanently`, granted to whoever
holds `corex_run_dangerous_actions` or `manage_options`, filterable. That ability existed and
nothing in Submissions used it. The list's answer says whether this person may, so the trash can
offer the action or say why it does not.

**One entry, and it is the only record.** `submission.deleted`: who, when, how many, the forms,
and `by: person`. The submission's history is deleted with it.

**The notification is left.** "Assigned to you" stays in its recipient's list and leads to an
inbox that says the submission was not found. Removing one person's notification because of what
another did is not this feature's.

**The confirmation** lists what goes, says it cannot be undone, and keeps its action disabled
until a box is ticked. It names kept export files only when the person has one.

**Found by looking: red on brass.** The confirm button was primary and destructive. The shell
gave every destructive button the error colour as text, and a primary button the action colour
as ground, so the two together could not be read. The shell gives that combination the primary's
ink. The Data screen's migration rollback is the same combination and was unreadable the same
way. The browser test compares the button's ink with another primary button's.

**Found by a test: "not found in the trash" was answered 409.** The status is chosen by words in
the message, and "in the trash" was looked for before "not found". The order is the other way.

**A test that pinned the old behaviour was changed, on purpose.** `SubmissionBulkServiceTest`
asserted that `delete` is not a bulk action. It asserts that of an action that still is not one.

What was run:

| Check | Result |
|---|---|
| `SubmissionDeleteTest` (new), real WordPress: uploads found by their answers, attempt ids, only a trashed one is deleted, the route with a real file on disk, 404 from the inbox, 403 without the permission, the activity entry | 5 passed |
| `EmailAttemptRemovalTest` (new), real WordPress: the attempt, the one made again from it and their captures go; a record that only quotes the id stays | 1 passed |
| `tests/Integration/Email` and `tests/Integration/Submissions`, real WordPress | 59 passed |
| `tests/Unit` | 2374 passed |
| Jest, `Submissions/__tests__` | 121 passed, 10 of them new |
| Browser, `submissions-inbox.spec.js`, this branch's inbox | 11 passed, one new: the confirmation's parts one distance apart and its box level with its label in both directions, the action gated by the box, the button's ink |
| The confirmation and a trashed pane, captured at 1440 and looked at | the unreadable button, fixed |

**Not run.** The whole integration and browser suites; CI runs both. A file that cannot be
removed was tested with a store that says so, not with a real file the server could not delete.
Somebody without the permission was tested at the route and in the services, not in the browser.
A deletion of a submission with a real captured email was tested in two halves, the inbox with a
recording seam and the add-on by itself, not end to end.

## #284 — A placeholder is drawn inside the content's own markup, and a list is kept while it is replaced

**Date:** 2026-10-08. **Spec:** 108, slice 1 (T001 to T009). **Branch:** `feat/108-loading-pieces`.

The owner asked for a skeleton loader on every call and a better loader inside CoreX. This is
the first slice: the two shared pieces, and the three Notifications surfaces on them.

**A surface composes its own placeholder.** The plan's first draft had a catalogue of shapes
(rows, cards, tiles, a pane, a form). What was built is two parts, a bar and a box, that a
surface puts inside the markup its content uses. The bar is as tall as a line of the element it
is in (`1lh`), painted as a strip in the middle of that line, so a placeholder card takes its
padding, its columns and its line heights from the rules the real card takes them from, and
changes when they do. Measured on the page, against the card that replaced it:

| Surface | Placeholder | Real |
|---|---|---|
| Screen card, left to right | same left edge, top and width; 166.7px tall | 171.5px |
| Screen card, right to left | same left edge, top and width; 166.7px tall | 173.5px |
| Preferences row | 816 by 52.2px | 816 by 52.2px |
| Drawer card (wrapped text) | 197px tall | 211px, for the readiness notifications on the test site |

The difference on the screen is the pill round a severity and the chrome of an action button,
which a bar does not have. The drawer's first placeholder was the screen's and stood 135px
against 211px; the drawer has a shape of its own now, with the lines its narrower column wraps
to and no row of actions.

**The delay is CSS.** Nothing is drawn for the first 160ms: the placeholder is held transparent
by an animation's delay, with the space taken from the first frame. No timer to clear, and the
same on the two screens that are not React.

**Each animation has its own reduced-motion rule.** The shell's general rule is `.corex-admin *`,
one class strong; every selector here is stronger, so it would not have reached them. The plan's
first draft relied on it, and on an ended animation resting on its last frame, which it does
not without a fill mode. Each part is right when still instead: the band's own place is off
the block, and the waiting bar spans its edge.

**What is announced.** The sentence region is outside the element marked busy, because
assistive technology may hold a busy element's announcements until it is no longer busy. A load
is announced after it has lasted a second, and its end only if its start was. The sentence is
the surface's own, whole, not assembled from a name.

**Waiting content is inert, and focus goes to the surface.** A row of a list that is being
replaced takes no action (FR-011). Content that turns inert drops the focus it held and the
browser sends it to the top of the page; the surface takes it. The pager is outside what waits
and is now drawn through a refresh: it used to be removed on every page turn, with the focus on
the button that had just been pressed.

**A slower answer is not kept.** With the list left on screen a second view can be asked for
before the first has answered. The screen keeps the answer to the last request, whichever
arrives last. It was not reachable before only because both requests replaced the same line of
text.

**The class is `.corex-admin-skeleton`.** It was `.corex-skeleton` until the public theme's
utility of that name was read: a different thing (a pulsing block), in a stylesheet that would
have painted this one's wrapper if the two were ever loaded together.

**The loader and the working button moved to slice 2.** Nothing in this slice would have called
them.

What was run:

| Check | Result |
|---|---|
| The eight Notifications tests, before the surfaces changed | failed |
| `npx wp-scripts test-unit-jest` | 783 passed, 70 suites |
| `loading-states` and `notification-center` browser specs, on `corex.local` | 15 passed |
| The placeholder against its card in dark and light, left to right and right to left | the table above |
| Reduced motion, in the browser | the band's animation is `none`; no animation on the page repeats |
| `TokenConsumerContractTest`, `AdminAssetScopingTest` | 8 passed |
| `wp-scripts lint-js` on the changed files, `lint-style` on the two stylesheets | no errors |

**Not run.** No screen reader. The announcements are asserted as text in a live region, not
heard. Firefox and Safari were not opened; `lh` and `inert` are in all three engines' current
releases, which is from their documentation and not from a run here.

**Left open.**

- The placeholder is faint on the dark theme on purpose: the border colour on a raised surface.
  It is decoration and carries no text; whether it is too faint is the owner's eye to judge.
- A list's placeholder is four cards (three in the drawer, six rows in preferences) whatever the
  answer holds, so what is below a list still moves when the answer is longer or shorter.
- The drawer shows a placeholder on every open. It could keep the last list and mark it
  waiting; it does not, because a list from the last time it was open is not current.
- "Mark all as read", and each notification's own actions, still show nothing while their
  request is out. That is slice 2.

## #285 — A working control shows the loader in its own colour, and the loader is drawn nowhere else yet

**Date:** 2026-10-08. **Spec:** 108, slice 2 (T020 to T025). **Branch:** `feat/108-loader-and-actions`.

The second half of what the owner asked for: "loader inside coreX will take a better effect".

**What was there.** An inventory of the admin outside the Submissions inbox, at `f2908076`,
found 55 controls that send a request. While theirs was out: 4 showed WordPress's striped
`isBusy`, 5 changed their label ("Saving…"), 24 were only disabled, 1 wrote "Testing…" in a
span beside itself, and 21 showed nothing, most of which could be pressed again and sent again.

**One look.** `workingProps( working )` gives a control three attributes: `disabled`,
`aria-busy` and `data-corex-working`. The styles do the rest. The label keeps its place and
only its fill is made transparent (`-webkit-text-fill-color`), which leaves `currentcolor` for
the loader drawn over it: so the loader is the button's own ink, whatever the button, and the
button keeps its width to the pixel and its name. Measured on five kinds of button, each was
the same width working as not.

**The loader** is a ring whose colour fades round it to nothing: a conic gradient with its
middle masked out, on a pseudo-element. Under reduced motion it does not turn.

**Three things the page showed that the plan did not know.**

- wp-admin paints every disabled `.button` grey with `!important`. A working brass button
  became a pale box on the dark theme. A working `.button` restates the colours it has when it
  is not working, with `!important`, because nothing less answers wp-admin's.
- A disabled control in the admin is dimmed to 58%. A working one is not: it is busy, not
  switched off.
- WordPress draws a `Modal` at the end of `<body>`, outside `.corex-admin`, where the first
  rules did not reach. Two of the four buttons moved off `isBusy` are in such modals and would
  have been disabled with nothing drawn. The rules are scoped to the screen's body class as
  well. The inventory found this by reading; a browser test holds it.

**No loader on its own yet.** The plan had a `CorexLoader` component, the ring with a sentence.
Nothing calls for one: every place a spinner stands today is getting a placeholder in a later
slice. It is written when a surface needs it.

**Notifications, whole.** Each notification's actions, "Mark all as read" in both places and
the preference boxes. One action on a notification at a time: the pressed control works and the
others wait, since what they would act on is about to change. The preference boxes wait for one
another too, because each change sends every category as it is on screen and a second sent
before the first had answered undid it. "Mark all as read" (both) and a preference change
swallowed a failed request; each says the server's reason now.

**A test, not a lint rule, keeps `isBusy` out.** Nothing is wrong with the prop; it is wrong
here.

What was run:

| Check | Result |
|---|---|
| `npx wp-scripts test-unit-jest` | 801 passed, 72 suites |
| `loading-states` browser spec on `corex.local` | 9 passed: a held "Mark all as read" keeps its box, shows the loader, is not dimmed and sends nothing on a second press; a control appended to `<body>` gets the loader |
| Five kinds of button, working and not, dark and light, on the page | the same width each; looked at |
| `TokenConsumerContractTest`, `AdminAssetScopingTest` | 8 passed |

**Not run.** No screen reader. The two buttons in WordPress modals were not opened in their
modals: the rule that reaches them is tested on a control placed where a modal is drawn.

**Left open.**

- 43 of the 55 controls: tasks T026 to T029.
- On the screens with one busy flag for every button (Email Studio, Forms and flows, the
  import panel, Blog), marking only the pressed control needs the flag to say which. That is
  the work of T026 to T028, not a change to `workingProps`.
- The inventory also read six things that look broken and are not this spec's: they are in
  issue form, as leads from reading and not as reproduced defects.

## #287 — On a screen with one busy flag, the control that was pressed is named, and works until the screen is current

**Date:** 2026-10-08. **Spec:** 108, slice 2 (T026). **Branch:** `feat/108-working-email-forms`.

Email Studio and Forms and flows each have one flag for every request. It disabled every button
on the screen together, and after "Save immutable draft" the button beside it, "Activate latest
draft", looked exactly the same. Sixteen controls.

**The screen says which.** `PendingControl` is a context holding the name of the control whose
request is out. The screen provides it once, and each button compares it with its own name and
spreads `workingProps`. The other choice was a prop through every panel: nine components in
Email Studio, for one string.

**Where the name comes from differs, because the two screens differ.**

- Email Studio posts everything through one function that already takes the kind of thing it
  posts. Its reducer keeps that kind as `pending` from the press until the studio has been read
  again, has failed, or has only something to say.
- Forms and flows' commands are a write and then one or two reads, each of which moves the
  status on its own. A name kept in the reducer was over at the first read. The hook wraps each
  command instead, so the name lasts for the whole of it: "Save draft" works through the read
  that follows the write.

**Two rows are not in it.** The row that opens a template and the row that opens a flow send a
request too, but what they need is the placeholder of what they open, which is slice 4. A row
whose whole text is replaced by a ring says less than the row did.

What was run:

| Check | Result |
|---|---|
| `emailStudioWorking`: the name through the reload, over at each end; the pressed button works and the others are only held | 5 passed |
| `flowsWorking`: "Save draft" works through the read after the write; a refused write stops it | 3 passed |
| `npx wp-scripts test-unit-jest` | 809 passed, 74 suites |
| `forms-flow` and `email-studio` browser specs on `corex.local` | 6 passed |
| `loading-states`: Email Studio's "Create" with its request held, then dropped | keeps its box to the pixel; the only control on the tab that says it is working; enabled again after |

**Not run.** Forms and flows was not watched with a request held back: its browser spec
creates, saves, publishes and tests a flow at full speed and still passes.

**Left open.** 27 of the 55 controls: tasks T027 to T029, and the two rows.

## #288 — The rest of the admin's actions, and five controls that are not buttons to mark

**Date:** 2026-10-08. **Spec:** 108, slice 2 (T027 to T030). **Branch:** `feat/108-working-data`.

Twenty-two more controls: Data 11, Blog Pro 3, access requests 2, the Security save 1, Insights
1, the setup wizard 3, the captcha test 1. With them 50 of the 55 controls the inventory found
are on the one working state, and slice 2 is done.

**`usePending`.** A component that sends its own requests keeps the name of the control whose
request is out, and `during( name, task )` holds it for the whole of the task. It is what Forms
and flows wrote for itself in the last part (#287), moved beside `workingProps` when a second
caller arrived; ten components use it now.

**It replaced flags that were wrong, not only flags that were silent.**

- The import panel had one status for three requests and showed it on "Run dry-run" whichever
  had been pressed.
- Blog Pro had one flag: "Refresh" read "Refreshing…" while a post was being moved.
- The Migrations tab's "Refresh" was disabled by the flag and never set it; "Preview
  rollback" set it and was not disabled by it.
- Access requests kept one id: deciding a second request while the first was out handed the
  first one's buttons back before its answer. All decisions wait for the one that is out. Two
  at once would be faster and is not worth a wrong button.

**A dialog that asks for a change preview stays until it has one.** New record, Edit record,
Bulk edit and a record's Delete closed on the press. Nothing was on screen until the
confirmation appeared, and if the request failed the only sign was a notice on the page behind
where the dialog had been. Each stays with its button working and closes when the preview is
back, or has failed: the failure is still said on the page, as before.

**The labels that changed are gone.** "Saving…", "Applying…", "Refreshing…", "Running…".
A changed label changes the button's width, and a person looking for "Save" finds a button
that says something else.

**Screens that are not React write the three attributes.** Insights puts them in the markup it
draws its card from; the setup wizard and the captcha test set them on the node. The captcha
test keeps the sentence beside its button: that is what a screen reader is told, and where the
outcome is said.

**Five controls are not converted, on purpose.**

- "View" on a Data row, the row that opens an email template, the row that opens a flow.
  Each asks for something to show. What they need is the placeholder of what they open
  (slices 3 and 4); a row replaced by a ring says less than the row did.
- "Save settings" and Security's "Apply mode" are forms the server draws and the browser
  posts. The page loading is what a person sees, and a script that marked the button would
  have to know whether another script had stopped the post. Four more such forms were found
  by search and are left for the same reason.

What was run:

| Check | Result |
|---|---|
| `npx wp-scripts test-unit-jest` | 813 passed, 75 suites |
| `loading-states` on `corex.local`, each with its request held: Insights' "Run check" (keeps its box, loader drawn, enabled again); the wizard's "Next" (working, "Back" held, one request) | 12 passed |
| Nine browser specs, every one that touches a converted screen: `loading-states`, `data-management`, `blog-pro`, `access-request`, `operations-security`, `setup-settings-insights`, `forms-flow`, `email-studio`, `notification-center` | 66 passed |
| New Jest: `usePending` (2), a record's Delete stays until the preview is back (1), the captcha button (1) | passed |

**Not run.** The Data dialogs, Blog Pro, access requests and the Security save were not
watched with a request held back; their browser specs pass at full speed. No screen reader.

**Left open.**

- WordPress's component library does not load under Jest in this repository, which is why no
  test here had rendered a component that uses it. The one that does now stands plain elements
  in for `Button` and `Modal`.
- The setup wizard's "Apply plan" still says nothing when it fails (issue #313).

## #289 — The Data screen's placeholders, and a record that was being drawn as one line of JSON

**Date:** 2026-10-08. **Spec:** 108, slice 3 (T130 to T137). **Branch:** `feat/108-data-placeholders`.

**The records list.** `viewState()` is `loading` only while there is nothing on screen to keep;
with rows it is `refreshing`, and the rows stay, dimmed and inert, through a sort, a filter, a
search or a page turn. The placeholder is the table's own markup with bars in its cells.
Which columns a source shows is part of the answer, so the placeholder cannot know them; it
has the selection box and the action every source has, and four bars between.

Measured on the page, at a width where a row is one line:

| | Placeholder | Real |
|---|---|---|
| Header row | 41.8px | 41.8px |
| Body row | 48.0px | 48.0px |
| A tile, loading and ready | 112.0px | 112.0px |

The body row was 43.5px until the bar in its last cell was given the height of the button it
stands for: a row is as tall as its button, not its text. At a narrower width a real row wraps
to as many lines as its values need and no placeholder can know that.

**Nothing asked yet is loading, not empty.** Between the screen opening and the first request
being sent, and for as long as the list of sources was unread, the list's state was "idle" and
was read as an empty source: "No records yet." about a question nobody had asked. A source
list that failed left it there for good. That state is loading while an answer is coming, and
a source list that cannot be read says so with a retry.

**A slower answer is not kept.** Rows that stay on screen let a second query be made before
the first has answered. The effect that asks keeps only the answer to the query that is current.

**The search box asks when typing pauses**, 300ms, through `useDebounced`. What is typed is
the box's own state, so it shows at once and keeps its focus; it is reset with the source, by
key. Ten letters typed at 40ms apart send one request. They sent ten.

**The record's detail moved to `CorexDialog`.** It was WordPress's `Modal`, which is drawn
outside `.corex-admin`: a placeholder in it would have been the dark theme's colours on a white
box, because none of the admin's styles or tokens reach it. The task said to check first. The
detail opens on the press with a placeholder for its fields and fills in when the record
arrives; one that cannot be read closes it, and the notice on the page says why.

**And its record was being read wrong.** With the dialog in front of me the ready state showed
one field, named "Record", holding the whole record as a line of JSON. The route answers
`{ record }` (`DataManagementController::show()`, which is the one registered, and whose
integration test holds it to that). The screen read the answer itself as the record, on the
word of a comment about `DataController::show()`, a controller that is bound in the container
and not registered. Its unit test passed, because the test's transport answered what the
comment said. The read is fixed, the test's transport answers what the site was seen to
answer, and a browser test opens a record against the real route and checks its fields are
drawn as fields: the comparison between the screen and the route that serves it, which
nothing made.

**The two tiles read "Fields4".** The label and the number were bare inline elements. The
stylesheet has had classes for a label over a number all along; they were not on the elements.
They are now, which is also what lets a placeholder stand in for the number without making the
tile taller.

What was run:

| Check | Result |
|---|---|
| `npx wp-scripts test-unit-jest` | 818 passed, 76 suites |
| `loading-states` and `data-management` on `corex.local` | 25 passed |
| Held back and measured: the list, a record, the export history | the table above; a record opens before it arrives and never says "no readable fields" first; the history never says "No exports yet." first |
| A sort with its answer held | rows kept, the surface `refreshing`, the total waiting |
| Ten letters typed without a pause | one request; the box keeps its focus and its text |
| `TokenConsumerContractTest`, `AdminAssetScopingTest` | 8 passed |

**Not run.** The migration history was not watched held back; it uses the placeholder and the
code path the export history does. No screen reader. The record dialog was looked at in both
themes, left to right only.

**Left open.**

- `Corex\Config\Data\DataController` is bound in the container and registers nothing. It
  misled a comment and a test; it should be removed or said to be dead. Added to issue #313.
- "View" on a row is still not marked while its record is fetched: the dialog opening is the
  answer to the press.
- New record, Edit record and Bulk edit are still WordPress modals, white on the dark theme,
  as the record's detail was.

## #290 — Forms and flows and Email Studio are read once and stay, and a disabled button is the button, dimmed

**Date:** 2026-10-09. **Spec:** 108, slice 4 (T040 to T044). **Branch:** `feat/108-forms-email-placeholders`.

**Whether a screen has ever been answered is a fact it keeps, not one it guesses.** Email
Studio told a first load from a later one by whether it had any templates. A new site that had
been read has none, so every save there took the open form away to say "Loading Email
Studio…". Forms and flows had no way to tell at all, and said "No forms match this view."
whenever its list was empty and nothing was in flight, including the moment before it asked.
Each state has a flag now, set by its first answer.

**The catalog waits whole.** The forms defined in code are known when the page is drawn; the
flows are asked for. The list used to show the first and then grow and reorder when the second
arrived. It is a placeholder until both are known. After that its rows stay through a filter or
a new flow, dimmed and inert.

**Opening a flow, and choosing a template, show what is coming.** The flow's command has a name
(`open:` and its id), and while it is out the screen draws the editor's placeholder where the
catalog was. A template chosen from the list marks its row and draws the editor's placeholder.
The marking is done where a person chooses, not where the studio reads a template again after a
save: that would have replaced the open editor on every save.

**A reload behind a working button is not a refresh.** After a save both screens read
themselves again. Marking that `refreshing` would turn the screen inert and move the focus off
the button that was pressed, to say what the button is already saying. They stay `ready`.
Forms and flows' catalog is `refreshing` for a filter and for a new flow, whose buttons are
outside it.

**A disabled button was a pale box.** wp-admin paints every disabled `.button` light grey with
`!important`. On the dark theme "Create draft" was a white-ish block for as long as the screen
loaded, which this slice's first screenshot showed more plainly than anything it was there to
show. A disabled `.button` in the CoreX admin keeps its own colours and is dimmed by the rule
that dims every disabled control.

**A placeholder drawn in a button's markup is a disabled button**, so its cells sit in the
button's grid from the button's own rule; inside a placeholder it is not dimmed.

Measured on the page:

| | Placeholder | Real |
|---|---|---|
| A catalog row | 85px | 90px |
| The editor's toolbar bars | have a width | were 0 wide at first: a share of a heading as wide as its text, which a placeholder has none of |

What was run:

| Check | Result |
|---|---|
| `npx wp-scripts test-unit-jest` | 829 passed, 76 suites |
| `loading-states`, `forms-flow`, `email-studio` on `corex.local` | 27 passed, none skipped |
| Held back: the catalog, opening a flow, the studio's first load, a chosen template | each a placeholder, then its content; never "No forms match this view." first |
| Looked at, dark theme: the catalog, the editor on its way, the studio, a chosen template | as intended, after the two fixes in the table above and the button |

**Not run.** The light theme and right-to-left were not looked at for this slice's four
placeholders; they are built from the same bars as the ones that were. No screen reader.

**Left open.**

- Every disabled `.button` in the CoreX admin changes colour with this, not only the ones on
  these two screens. It was looked at here and on Email Studio.
- The catalog's placeholder is four rows whatever the site has.

## #291 — The two screens that are not React, and a card that cannot know how tall it will be

**Date:** 2026-10-09. **Spec:** 108, slice 5 (T050 to T053). **Branch:** `feat/108-insights-wizard-placeholders`.

Insights and the setup wizard are plain scripts that draw markup. They cannot import the
wrapper, so they write what it writes: the placeholder's class names, `data-corex-state` and
`aria-busy` on the root, the placeholder hidden from assistive technology.

**An Insights card does not know whether it has been run until the last results arrive.** It
drew "Not run yet" and a dash at once and filled in afterwards, so for as long as the request
took a check that had been run said it never had. It draws a placeholder for its score, a few
lines for its body and one for when it was last checked. Its button is held until the results
are in: a check started before them would be overwritten by them.

**It cannot know how tall it will be.** On the test site a waiting card is 319px and the card
that replaces it 524px, because that site's results carry measures and recommendations; a
site that has never run a check has a card shorter than the placeholder. The placeholder is
the middle of the two. SC-002 asks that nothing outside a surface moves by more than a few
pixels, and here it does, by what the answer holds. That is said, not hidden: the criterion
is met where the shape is known and not here.

**The widgets hold their place with three placeholders**, though the server decides how many
there are (five on the test site). The screen was two cards and then seven.

**Both of the screen's silent failures are said.** The last results failing is said on each
card, in the slot a failed check already used. The widgets failing is the shared error
state's markup, where they would have been, with a retry.

**The wizard hides the no-script form the moment its script runs**, and draws the step that
is coming. The form used to stand until the wizard's state arrived and then be swapped for
it, which is two different screens in a row. If the state cannot be read, the form comes
back: it is the one thing on the screen that still works.

**The plan step is not a placeholder.** The spec lists it. Since slice 2 the "Next" that
asks for the plan is working until the plan has arrived, and the step is drawn with it. A
step shown first and filled in after would be a second way of saying the same wait.

**A bar with no width draws nothing, three times.** A bar is a share of its parent. In this
slice the card's score and its "last checked" were as wide as their text, which a placeholder
has none of; in the last it was the flow editor's toolbar, and before that a preference's
label. Each was seen in a screenshot, not in a test, until the tests were given a width to
check. The docs say it now.

**And one mistake of mine.** The rule that gave the score a width was inserted in the middle
of a selector list, which made the card's header and footer 3rem wide blocks. The linter
passed it. It was the screenshot that showed a score box under its title.

What was run:

| Check | Result |
|---|---|
| `npx wp-scripts test-unit-jest` | 834 passed, 77 suites |
| `insights-loading`: five tests driving the real script against held answers | passed |
| `loading-states`, `setup-settings-insights`, `smoke` on `corex.local` | 33 passed |
| Held back and looked at, dark theme: the Insights cards and widgets, the wizard | as intended, after the two fixes above |

**Not run.** The light theme and right-to-left for these two screens. No screen reader.

**Left open.**

- The wizard announces nothing while it loads; Insights does. The wizard's wait is one
  request on a screen whose heading has already been read.
- The Submissions inbox is the one screen left (slice 6), with the export dialog it shares
  with the Data screen, whose counts read "…" until they arrive. It waits on the session that
  is building in the inbox.

## #292 — A choice field takes what it offers, an empty file part is no file, and a pattern is one expression

**Date:** 2026-10-09. **Spec:** none; four defects reported on 2026-10-08 from a client's contact
form, each confirmed on `main` before it was changed. **Branch:**
`fix/forms-choices-and-optional-files`.

**The check on a choice is the validator's, not a rule a form writes.** A field that declares
`options` has already said what it accepts. A rule (`in:email,phone`) would be the same list
written a second time, right until somebody edited one of the two, and every form that forgot
it would stay open. `Validator::validate()` compares the answer with the keys of the field's
options after the field's own rules pass, and reports `choice`. Both submission paths use that
validator, so forms defined in code and flows get it together.

What it does at the edges, each one a test:

| Answer | Result |
|---|---|
| Empty text, an empty list, absent | left to `required`, as every rule leaves it |
| A field that declares no options | not compared: a theme that fills a select from its own script declares none |
| A list sent to a `select` or a `radio` | refused: a single choice has one answer |
| One plain value sent to a `checkbox-group` | compared as one answer |
| The option `2025` | accepted as the text `2025`: PHP makes that array key an integer |
| A `text` field given `options` | not compared: it is not a choice |

The stock form and a flow's form print the keys the check compares with
(`FieldRenderer::select()` and `group()`), so a visitor using either cannot be refused. A form
whose markup was written by hand can be, if its values are not the declared keys. That is the
entry under Client impact.

**A checkbox group is a list on both routes.** `FlowSubmissionController` already cleaned
`checkbox-group` as a list; `SubmitController` cleaned it as one line of text, and WordPress's
`sanitize_text_field()` answers an empty string for a list. One word added to the arm that
`multi-select` already had.

**A file part with no file in it is the field left empty.** Decided in
`FormSubmissionService`, where a descriptor is put in its field's place, not in the controller
that reads the request: anything that calls the service gets the same answer. The part is
dropped before validation, so `required` sees an absent value. It saw a descriptor before,
which is not an empty value, so a required file left empty passed `required` and failed to be
stored. CoreX's own script never sent that part (it sends the file's name as text when no file
is chosen); a browser posting the form itself does, and so does any script that sends
`new FormData(form)`.

**`pattern:` keeps everything after its colon.** The registry names the rules whose parameter
is one value (`WHOLE_PARAMETER`, holding `pattern`) and does not split them. The other way was
for `Pattern` to join its parameters back together with commas. That works and is hidden: the
exported schema would still hold the two halves, for a browser rule to trip over later. A rule
an extension registers cannot ask for the same treatment. None does; when one needs it, that is
the day to let a rule say so.

**`mime:` narrows and cannot widen, and that is left as it is.** The store is built once with
six types and 10 MB and holds every file to them. A comment said a field's `mime:` widened the
list and another that `max_size:` raised the limit; the comments were wrong and the code was
not changed to match them. Letting one line in a form class add a type to what a site stores
would make the upload policy whatever the last form said. A site that needs a type outside the
list needs a setting somebody chose on purpose. Not built: nobody has asked for a type, only
pointed at the comment.

**Reported with these, and already fixed:** a form defined in code had no way to ask for a
challenge. It has had one on `main` since spec 104, slice 2 (#298), unreleased.

What was run:

| Check | Result |
|---|---|
| `ChoiceAndFileSubmissionTest` (new), the real route on real WordPress, against `main`'s classes | 4 failed, as reported |
| The same against this branch | 4 passed |
| `tests/Integration/Forms`, real WordPress | 77 passed |
| `tests/Unit` | 2425 passed; 25 new (`ChoiceAnswerTest` 17, `PatternRuleTest` 6, `FileFieldTest` 2) |
| Jest, the form runtime's four suites | 52 passed |
| `wp-scripts lint-js` on `corex-runtime.js` | no errors |

**Not run.** No browser test: no screen changed, and the script's only change is one sentence
in its fallback table. The whole integration and browser suites were left to CI. A real
multipart post from a browser was not sent; the empty part was given to the route as PHP hands
it over. The three stored copies of the stock form's markup gained the new message and nothing
else.
