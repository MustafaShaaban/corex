# Corex — Progress

> Live status file. A new session's first action: read this, then continue from **Next**.
> Updated at the end of every working session.

## Done
- [x] **Bootstrap** — environment verified (PHP 8.3.6, Composer 2.4.2, Node 22.14, npm 10.9,
      WP-CLI 2.11, git 2.33, uvx 0.11.16).
- [x] **Tooling** — Spec Kit initialized in place (`.specify/`, `.claude/skills/speckit-*`,
      commands namespaced `speckit-*`). Five guard skills installed
      (`wp-guard`, `woo-guard`, `clean-code-guard`, `test-guard`, `docs-guard`).
- [x] **Git** — repo on `main`, `.gitignore` for WP+PHP+Node (no commit yet).
- [x] **Continuity scaffolding** — `CLAUDE.md`, `AGENTS.md`, `PROGRESS.md`, `DECISIONS.md`.
- [x] **Constitution** — `.specify/memory/constitution.md` v1.0.0 (10 principles + Next Step Rule +
      Guard Gate + Definition of Done + source-of-truth hierarchy). `specs/constitution.md` pointer
      stub; plan-template Constitution Check gate pre-filled with the 10 Corex gates.
- [x] **Repo structure (Phase 4)** — monorepo skeleton per §4: `theme/` (block theme: style.css,
      theme.json v3, templates/parts), `plugins/corex-{core,blocks,config}` (WP headers + guarded
      autoloader, no logic), `addons/`, `packages/{cli,build-tools}`, `docs/`, `tests/`. Root
      `composer.json` (PSR-4 `Corex\` + 4 sub-prefixes, single authoritative autoload) and root
      `package.json` (npm workspaces). Verified: php -l clean, all JSON valid, `composer install`
      wires all 5 prefixes, WP header parser recognizes 3 plugins + the theme. Guards clean
      (wp-guard, clean-code-guard, docs-guard).
- [x] **WordPress environment (Phases C–D)** — installed WP **7.0** into `./wp/` (WP-CLI on WAMP;
      added missing `wp-cli/wp-cli-bundle`), DB `corex` on MySQL 8.3.0, prefix `cx_`. Mapped the
      monorepo into `wp/wp-content/` via **junctions** (theme + 3 plugins). Theme + all 3 plugins
      **activated**; site boots at **http://corex.local** (admin `/wp-admin/`), no Corex fatals.
      Constitution amended to **v1.1.0** (Environment Gate). Details: DECISIONS.md #18. The exact
      install/mapping procedure is recorded so this never repeats.

> Environment is correctly bootstrapped. Skeleton loads cleanly in a real WP install; still no
> framework business logic — that begins in Phase 5.

## In progress
- **SPEC 002 — data layer (Model + Field driver + Repository + QueryBuilder).** Spec written:
  `specs/002-data-layer/spec.md` (Draft); quality checklist passed. 4 developer journeys (P1 Model+
  Repository, P1 ACF-optional Field driver, P2 fluent QueryBuilder, P2 eager loading); 23 FRs, 7 SCs.
  `/speckit-clarify` done (2026-06-08, 5 decisions). `/speckit-plan` done (2026-06-08): `plan.md` +
  `research.md` + `data-model.md` + `contracts/data-layer-contracts.md` + `quickstart.md`. Constitution
  Check PASS. Architecture: `Models\Model` (read-only value object) · `Repositories\{RepositoryInterface,
  PostRepository}` (sole data caller) · `Fields\{FieldDriver,FieldResolver,Meta/AcfFieldDriver}` (ACF-
  optional) · `Database\{QueryBuilder (builds capped WP_Query args) → QueryExecutor (only WP_Query
  caller) → Collection}` · `DataServiceProvider`. Key testability split: QueryBuilder is a pure
  arg-builder (unit), QueryExecutor runs the query (integration). Next: `/speckit-tasks`.

> **✅ SPEC 001 — corex-core foundation — COMPLETE (2026-06-08).** All 38 tasks done; US1–US4 +
> Polish. 46 tests green (42 unit headless + 4 integration on real `./wp`); site HTTP 200. Guard Gate
> clean on every increment. Definition of Done met: constitution-compliant, Pest tests green, guards
> clean, admin-notice UI i18n/escaped, docs (`corex-core/README.md`, `.env.example`) accurate
> (docs-guard clean), PROGRESS + DECISIONS (#19–21) updated. Commits: `c7acfca` (baseline+US1a),
> `3aad291` (US1b), `9ac5b4a` (US2), `56b92c3` (US3), `f46d022` (US4). Delivered: `Boot` (self-init on
> plugins_loaded), custom PSR-11 `Container`, Service-Provider lifecycle, layered `Config`,
> `HookRegistry`, `ControllerMap`. The detailed build log for spec 001 is below.

- **PHASE 5 — corex-core foundation.** Spec written: `specs/001-corex-core-foundation/spec.md`
  (Draft). Quality checklist passed (`checklists/requirements.md`). 5 prioritized developer
  journeys: P1 Boot+Container, P1 Config, P2 HookRegistry, P3 ControllerMap; 22 FRs, 7 success
  criteria. `/speckit-clarify` done (2026-06-08, 5 Qs answered → Clarifications section): controller
  discovery = directory + PSR-4 scan; interface resolution = explicit bindings (FR-007a); `.env`
  loader = `vlucas/phpdotenv`; container access = bounded global accessor, framework-boundary only
  (FR-008a); error surfacing = debug log always + admin notice on `WP_DEBUG` (FR-023).
  `/speckit-plan` done (2026-06-08): `plan.md` + `research.md` + `data-model.md` +
  `contracts/foundation-contracts.md` + `quickstart.md`. Constitution Check PASS (no violations).
  Architecture: `Boot` → `Foundation\Application` → `Container` (wraps `league/container`) →
  Service-Provider register/boot lifecycle; `Support\Config` engine (`.env`/`vlucas/phpdotenv` →
  options → defaults); `Hooks\HookRegistry` + `SubscribesToHooks`; `Http\ControllerMap` (PSR-4 scan).
  **Service Provider is the single extension seam** for all future modules/add-ons (scalability).
  Config-home conflict resolved → DECISIONS #19 + FRAMEWORK §2 amended.
  `/speckit-tasks` done (2026-06-08): `tasks.md` — 38 tasks, TDD-ordered, grouped by the 4 user
  stories. Phase 1 Setup (T001–T004) → Phase 2 Foundational/BootLogger (T005–T006) → US1 Boot+
  Container [MVP] (T007–T017) → US2 Config (T018–T024) → US3 Hooks (T025–T029) → US4 ControllerMap
  (T030–T033) → Polish (T034–T038).
  **Implementation — Phase 1 Setup DONE (T001–T004, 2026-06-08):** added `psr/container`,
  `league/container` 4.x, `vlucas/phpdotenv` 5.6.3 (root + corex-core composer.json) and dev deps
  `pestphp/pest` 2.36.1 + `brain/monkey`; created the Pest harness (`tests/bootstrap.php`,
  `tests/Pest.php`, `Unit`/`Integration` base `TestCase`s, `phpunit.xml.dist`, `composer test*`
  scripts); scaffolded `src/{Foundation,Hooks,Http,Container/Exceptions,Support/Config/Sources,
  Support/Facades}`. Verified: unit suite green, WP still boots HTTP 200 with new deps. test-guard
  run clean (removed a framework-guarantee smoke test per Rule 7).
  **Phase 2 DONE (T005–T006, 2026-06-08):** `BootLogger` (`src/Support/BootLogger.php`) TDD'd —
  6 Pest tests red-first then green (14 assertions); always writes the debug log, surfaces a single
  capability-gated, escaped, i18n admin notice only when debug; never throws (FR-023, SC-008).
  Guard Gate clean (wp-guard + clean-code-guard + test-guard). Added the **ABSPATH direct-access
  guard convention** for all src class files + test-bootstrap `ABSPATH` define (DECISIONS #20).
  **US1 checkpoint (a) DONE (T007, T010, T011, 2026-06-08) — the Container:** `Corex\Container\`
  `Container` + `ContainerInterface` (PSR-11 + bind/singleton/instance/make) + 4 exceptions. TDD: 11
  Pest tests red-first then green (full suite 17 passed). Autowiring, shared vs transient, param
  overrides, optional defaults, cycle detection, FR-007a/009 precise messages. **Engine reversal:**
  dropped `league/container` for a focused custom container (it can't detect cycles / clean unbound
  messages) — DECISIONS #21; research.md R1 + plan.md corrected. WP still boots HTTP 200.
  Guard Gate clean (clean-code-guard; wp-guard N/A beyond the ABSPATH guard; test-guard).
  **US1 checkpoint (b) DONE (T008/T009, T012–T017, 2026-06-08) — boot lifecycle:**
  `Corex\Foundation\` ServiceProvider (register/boot seam) + ProviderRepository (two-pass
  register→boot, dedupe, failure isolation) + Application (composition root). `Corex\Boot` self-hooks
  `plugins_loaded` (idempotent) + `Corex\Support\Facades\Corex` bounded accessor; `corex-core.php`
  wired. TDD: unit 23 passed (38 assertions) + **integration 2 passed against real WP** (self-boots,
  container resolves services — SC-001). Guard Gate clean (clean-code + wp-guard + test-guard).
  Per-suite test bootstraps added (unit defines ABSPATH; integration loads `./wp`) →
  `phpunit-integration.xml.dist` + `composer test:integration`. Deferred to their stories:
  `subscribers()`/`controllerPaths()` on ServiceProvider (US3/US4); config/composer-extra provider
  sources (US2). **US1 (the MVP) is COMPLETE.**
  **US2 DONE (T018–T024, 2026-06-08) — layered Config engine:** `Corex\Support\Config\`
  ConfigInterface + Source + Repository (first-source-wins precedence) + Sources/{Defaults, Options
  (`corex_`-prefixed `get_option`), Dotenv (`vlucas/phpdotenv` array-backed; absent→empty FR-013,
  malformed→log+empty FR-014)}. `CoreServiceProvider` binds `ConfigInterface` (defaults
  `config/app.php`), registered in `Boot`; `Config` facade. TDD: 10 ConfigTest cases (precedence
  SC-003, fallback, absent, malformed) + integration `Config::get`=='Corex' on real WP. Unit 32 /
  integration 3 green; Guard Gate clean. `.env` at `dirname(COREX_CORE_PATH,2)`. `.gitattributes` added.
  **US3 DONE (T025–T029, 2026-06-08) — declarative HookRegistry:** `Corex\Hooks\` SubscribesToHooks
  (`hooks(): array`) + HookRegistry (resolves subscriber from container, `add_filter` for actions +
  filters, normalizes `hook=>method | [method,priority,args]`, dedups by `class::method@hook`).
  Wired via `ServiceProvider::subscribers()` (re-added, now consumed) → `ProviderRepository`
  `wireSubscribers()` in the boot pass; `Application` builds+binds the registry. TDD: 4 HookRegistry
  tests + a provider-wiring test. Unit 37 / integration 3 green; Guard Gate clean.
  **Next: US4 (T030–T033) — controller auto-discovery (ControllerMap, PSR-4 scan).**

## Interruption note
The environment gap (no WordPress core) was discovered **between Phase 4 and Phase 5**, before any
corex-core foundation code was written. So **no module files are half-built** — the interruption did
not leave broken code. The last completed unit of work is the Phase 4 skeleton + this environment
bootstrap; the next unit is the Phase 5 corex-core foundation (not yet begun).

## Next (recommended order)
1. **SPEC 002 — Model + Field driver (ACF-optional) + QueryBuilder** [PHASE 6] — the next module per
   COREX-SPECKIT-START "The rhythm from here". Spec Kit flow: `/speckit-specify` → `/clarify` →
   `/plan` → `/tasks` → `/implement`, ONE task at a time with the Guard Gate + Pest tests, building
   on the corex-core foundation (providers, container, config). Models as value objects; a Field
   driver behind an interface (native meta vs ACF, Principle IX — ACF never a hard dependency); a
   fluent QueryBuilder wrapper (not a full ORM, FRAMEWORK §7).

Module build order after Model/QueryBuilder (COREX-SPECKIT-START.md "The rhythm from here"):
CLI generators → corex-blocks → Middleware + Security → theme + design tokens → Forms →
Abilities/MCP → Corex Mail → other add-ons (profile-manager, woo) → setup wizard + demo content.

## Environment quick reference
- **Site:** http://corex.local · **Admin:** http://corex.local/wp-admin/ (`admin` / `123456`)
- **WP core:** `./wp/` (gitignored, WP 7.0) · **monorepo → WP:** junctions in `wp/wp-content/`
- **WP-CLI:** target the install with `--path=wp`. For `wp db …` commands, prepend the MySQL client:
  `export PATH="/c/wamp64/bin/mysql/mysql8.3.0/bin:$PATH"`
- Full procedure + rationale: DECISIONS.md #18; rule: constitution "Environment Gate" (v1.1.0).
- **Folder-rename gotcha:** the `wp/wp-content/` junctions store the repo's **absolute path**, so
  renaming/moving the repo folder breaks all four. Repoint them (theme + 3 plugins) to the new path
  with `cmd /c rmdir <link>` then `cmd /c mklink /J <link> <target>`. (Done 2026-06-08 after the
  rename `blackstone-new-site` → `corex`; vhost still serves http://corex.local.)

## Open decisions
- **Deploy target** — undecided (DECISIONS.md #11, status Open). Does not block current work.

## Last session summary
2026-06-07 — PHASE 0–4 complete + WordPress environment bootstrapped. Verified env, installed Spec
Kit + guard skills, git on `main` (Azure DevOps remote), continuity scaffolding, constitution
(now v1.1.0 — added the Environment Gate), §4 monorepo skeleton (guards clean). Then fixed the
missing-WordPress gap: installed WP 7.0 into `./wp/` via WP-CLI on WAMP, mapped the monorepo in via
junctions, activated the Corex theme + 3 plugins (site boots at http://corex.local). Decisions
#15–18 logged. Next: PHASE 5 — corex-core foundation via the Spec Kit flow, one task at a time.
