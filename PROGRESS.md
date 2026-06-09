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
- _(nothing mid-flight — spec 015 complete; pick up at **Next**.)_

> **✅ SPEC 015 — Call Request — COMPLETE (2026-06-10).** US1. **192 unit + 28 integration green.** New
> add-on **`addons/corex-bookings`** (`Corex\Bookings`). Core (pure, tested): `LeaderDirectory` (configured
> leaders) + `CallRequestService` (validate leader + contact → store → notify leader + confirm visitor; zero
> side effects on rejection). Boundary: `CallRequestRepository` (`corex_call_requests` custom table) + store,
> request REST route (honeypot+captcha), leader/confirm email templates; leaders from `bookings.leaders`.
> 3 unit + 1 integration; data path verified on real WP. DECISIONS #40. README added. **Completes the
> Blackstone feature set (contact + newsletter + careers + call).** On `feature/015-call-request`.

> **✅ SPEC 014 — Careers — COMPLETE (2026-06-10).** US1–US3. **189 unit + 27 integration green.** New
> add-on **`addons/corex-careers`** (`Corex\Careers`). Core (pure, tested): `StatusFlow` (valid pipeline
> transitions), `ApplicationService` (validate fields + CV via spec-012 → store → notify; zero side effects
> on rejection), `JobsRenderer` (accessible job cards). Boundary: `corex_job` CPT + dept/location/type
> taxonomies, `corex/jobs` block, `ApplicationRepository` (`corex_applications` custom table) + store, apply
> REST route (honeypot+captcha), HR/applicant email templates. 4 unit + 1 integration; CPT/block + data path
> verified on real WP. DECISIONS #39. README added. **CV file-move + apply-over-HTTP need a browser.** On
> `feature/014-careers`.

> **✅ SPEC 013 — Newsletter / Subscriptions — COMPLETE (2026-06-10).** US1–US3. **185 unit + 26
> integration green.** New add-on **`addons/corex-newsletter`** (`Corex\Newsletter`). Core (pure, tested):
> `TokenSigner` (HMAC, fail-closed) + `SubscriptionService` (double opt-in subscribe/confirm/unsubscribe;
> consent required; no dup/enumeration) + `PublishNotifier` (topic-intersection targeting). Boundary:
> `SubscriberRepository` (`corex_subscribers` custom table) + `WpSubscriberStore`, `newsletter_topic`
> taxonomy, signed confirm/unsubscribe link handler, subscribe REST route (honeypot+captcha),
> transition_post_status listener, confirm/notify Corex Mail templates. 8 unit + 1 integration; data path
> verified on real WP. DECISIONS #38. README added. **Email rendering + full REST/publish-over-HTTP need a
> browser; bulk send via the mail queue is deferred.** On `feature/013-newsletter`.

> **✅ SPEC 012 — Captcha drivers + Secure uploads — COMPLETE (2026-06-10).** US1–US2. **177 unit + 25
> integration green.** Upload (core, pure): `Security\Upload\UploadValidator` (rejects upload errors,
> empty/oversized, disallowed MIME, mismatched extension; descriptor-only, path-safe). Captcha (new addon
> **`addons/corex-captcha`**, `Corex\Captcha`): `Captcha` interface + `NullCaptcha`/`HoneypotCaptcha`/
> `RemoteCaptcha` (reCAPTCHA/Turnstile/hCaptcha, fail-closed, secret never logged) + config-driven
> `CaptchaResolver`. 5 + 5 unit tests. DECISIONS #37. README added. Enablers for Newsletter (013) + Careers
> (014). On `feature/012-captcha-uploads`.

> **✅ SPEC 011 — Custom Tables + TableRepository — COMPLETE (2026-06-10).** US1–US2. **167 unit + 25
> integration green.** Core data foundation (corex-core) for many-row entities. Pure: `Database\Schema\Table`
> (fluent columns → dbDelta-friendly CREATE TABLE) + `Database\Casts\Caster` (int/bool/string/decimal/
> array-json/datetime both directions; malformed json → []). Boundary: `Database\Schema\Migrator` (create/
> drop/exists via dbDelta, `{prefix}corex_` namespace) + `Repositories\TableRepository` (typed CRUD +
> where; `$wpdb->prepare` for all variables; validated identifiers). 3 unit + 3 integration; CRUD verified
> on real WP. DECISIONS #36. corex-core README "Custom tables" added. On `feature/011-custom-tables`.

> **✅ SPEC 010 — Company Website Kit (MVP) — COMPLETE (2026-06-09).** US1–US3. **164 unit + 22
> integration green.** New add-on **`addons/corex-kit-company`** (`Corex\Kit`): `Blueprint` + `BlueprintRegistry`
> (pure) + `CompanyBlueprint` manifest (required corex-ui; recommended forms/mail; templates/parts/patterns).
> Theme gained the universal FSE templates — `front-page` (composes the corex/* hero/features/cta/contact
> patterns), `page`, `single`, `archive`, `search`, `404` — + enhanced `header` (site title + nav) and
> `footer` (`corex/copyright` block) parts; token-only, RTL, accessible. 5 unit tests (registry/manifest +
> template presence + token-only scan); blueprint + front-page verified on real WP. DECISIONS #35. README
> added. **Visual/editor validity of templates/patterns needs a browser to confirm.** On `feature/010-company-kit`.

> **✅ SPEC 009 — Corex UI block library (MVP) — COMPLETE (2026-06-09).** US1–US3. **159 unit + 22
> integration green.** New add-on **`addons/corex-ui`** (`Corex\Ui`). Three server-rendered `corex/*`
> dynamic blocks (posts/breadcrumbs/copyright; injected PostsProvider for testability; bounded, escaped,
> token-styled) + five section patterns (hero/features/cta/testimonial/contact, the last composing
> `corex/form`) under a "Corex" inserter category, all token-only (theme.json presets) + RTL + i18n +
> neutral, + a `UiManifest` (reads the actual block.json files; for kit discovery). All blocks + patterns
> + category verified registered on real WP. Guard Gate clean. DECISIONS #34. README added. _No-JS-build_
> MVP; custom JS-edit blocks + the build pipeline deferred. **Editor/visual validity of pattern markup
> needs a browser to confirm.** Built on `feature/009-corex-ui` off develop.

> **✅ SPEC 008 — Corex Mail (MVP) — COMPLETE (2026-06-09).** All 29 tasks; US1–US4 + polish.
> **151 unit + 22 integration green** on real `./wp`. New add-on **`addons/corex-email`** (`Corex\Email`)
> + the neutral **`Corex\Mail\Mailer`** seam in corex-core. Delivered: pure cores — `Template\{MailContext
> (whitelisted dotted get), TemplateRenderer ({{ path }} merge, htmlspecialchars-escaped, brand Layout from
> theme.json/brand.json), EmailTemplate, TemplateRegistry}`, `Security\HeaderGuard` (CR/LF/control reject),
> `Recipients\RecipientResolver` (fixed/role/dynamic, validated); the boundary — `MailService` (guard →
> validate → driver → log; best-effort, never throws), `Driver\WpMailDriver` (wp_mail, config from-identity),
> `Log\{EmailLog, EmailLogRepository}` (`corex_email_log` CPT via the data layer, byStatus), `WpUserDirectory`
> (capped), the `Mail` facade + `MessageBuilder`, `RequestMailer` binding the seam, `ContactNotificationTemplate`.
> **Forms `SendEmailListener` now delegates to the Mailer seam when bound, else wp_mail** (detect-and-defer,
> Principle IX). Guard Gate clean each story. DECISIONS #29–#32. READMEs: corex-email (new) + corex-core
> "Mail seam". Built on `feature/008-corex-mail` off develop.

> **✅ SPEC 007 — Forms engine — COMPLETE (2026-06-09).** All 33 tasks; US1–US4 + polish.
> **131 unit + 19 integration green** on real `./wp`; `corex/form` block registered with a per-block
> view script (conditional asset). New plugin **`plugins/corex-forms`** (`Corex\Forms`) + the shared
> event seam in corex-core (`Corex\Events`). Delivered: pure cores — `Validation\{Validator (bail per
> field), RuleRegistry, Rules/*, ValidationResult}` + `Schema\{SchemaResolver, FieldSchema}`;
> `Events\{Event, ListenerProvider, EventDispatcher (ordered, best-effort), EventServiceProvider}`;
> the secured lifecycle — `Submission\{SubmitController (REST corex/v1/forms/{slug} → nonce→sanitize→
> throttle pipeline), FormSubmissionService (honeypot→validate→dispatch), FormSubmittedEvent,
> Submission + SubmissionRepository}`, `Listeners\{StoreSubmissionListener, SendEmailListener}`,
> `Form`/`FormRegistry` + `Forms\ContactForm`; the `corex/form` FSE block (`Block\FormBlockRenderer` +
> block.json/view.js/token-only style). `Response::reject` gained an optional payload (DECISIONS #27).
> Guard Gate clean each story (clean-code + wp-guard + test-guard + docs-guard). DECISIONS #24–#28.
> READMEs: corex-forms (new) + corex-core "Events" section. Built under the new git flow — see Workflow.

> **✅ SPEC 006 — Theme + design tokens — COMPLETE (2026-06-08).** All 15 tasks; US1–US4 + polish.
> `Corex\Theme\BrandResolver` (pure deep-merge: assoc merged key-by-key, siblings preserved, unknown
> added, scalars/lists replaced; read missing/malformed → [], malformed logged) + `ThemeServiceProvider`
> (binds the resolver; hooks `wp_theme_json_data_theme` to read brand.json from `config('theme.brand_path')`
> or the active theme root and merge it). `theme/theme.json` is the v3 token source; `theme/styles/dark.json`
> a token-only variation. 10 theme tests (BrandResolver, theme.json/dark.json validity, skin discipline).
> **126 tests green (111 unit + 15 integration); site HTTP 200; real-WP smoke confirms siblings preserved.**
> README "Theme & design tokens" section added. Followed the plan as written (no new DECISIONS entry).

> **✅ SPEC 005 — Middleware + Security — COMPLETE (2026-06-08).** All 22 tasks; US1–US4 + polish.
> 101 unit + 15 integration green; site HTTP 200. Principle VII delivered: onion `Pipeline` (value
> short-circuit; throw→fail-closed reject), four middleware (Nonce/Capability/Throttle/Sanitize),
> `MiddlewareResolver` (alias:param, unknown→RejectingMiddleware), `SecurityModule` (aliases
> nonce/auth/throttle/sanitize), wired into Boot. WP security fns at the middleware boundary;
> fully headless-testable. Commits: f7dc649 (US1), f84ed36 (US2), fdc4820 (US3). Build log below.

- **SPEC 005 — Middleware + Security.** Spec written: `specs/005-middleware-security/spec.md`
  (Draft); checklist passed. 4 developer journeys (P1 pipeline, P1 four core middleware, P2 declarative
  attachment, P2 SecurityModule); 18 FRs, 6 SCs. `/speckit-clarify` done (recommended): Response value short-circuit (throw→reject fail-closed); nonce gates non-GET; throttle transient 60/60s. `/speckit-plan` done (Constitution PASS): onion `Pipeline`, `MiddlewareResolver` (alias:param, unknown→fail-closed), four middleware, `SecurityModule`; all headless-testable. `/speckit-tasks` done: 22 tasks (Setup → interface/Request/Response → US1 Pipeline → US2 four middleware → US3 resolver → US4 SecurityModule → polish). Next: `/speckit-implement`.

> **✅ SPEC 004 — corex-blocks (block engine) — COMPLETE (2026-06-08).** All 22 tasks; US1–US4 +
> example block + polish. 89 unit + 15 integration green; verified on real WP (block
> `corex/entity-field` registered on init; connector registers as a Block Bindings source; site
> HTTP 200). `register_block_type`/`register_block_bindings_source` confined to DynamicBlockRegistrar +
> ConnectorRegistry (Principle VI). Delivered: BlockMap (convention discovery, headless),
> DynamicBlockRegistrar (container-resolved, non-fatal render), Connectors\{Connector,
> RepositoryConnector,ConnectorRegistry} (escaped/empty-safe), the entity-field example block
> (theme.json tokens + logical CSS), BlocksServiceProvider. DECISIONS #23 (renderer FQCN in block.json).
> Commits: 0cb5aca (US1), 5c543b8 (US3+US4). Detailed build log below.

- **SPEC 004 — corex-blocks (block engine).** Spec written: `specs/004-block-engine/spec.md`
  (Draft); checklist passed. 4 developer journeys (P1 auto-discovery+registration, P1 conditional
  assets, P2 dynamic render via container, P2 model→block connector seam) + one example block; 18 FRs,
  7 SCs. `/speckit-clarify` done (recommended): example = dynamic server-rendered block bound to a Repository field; connectors via WP Block Bindings API (Corex source fallback); server-rendered PHP only (no JS build). `/speckit-plan` done (Constitution PASS): BlockMap (discover src/blocks/*/block.json, headless), DynamicBlockRegistrar (register_block_type + container-resolved render_callback), Connectors via register_block_bindings_source (RepositoryConnector, escaped/empty-safe), BlocksServiceProvider on init; example dynamic block. `/speckit-tasks` done: 22 tasks (Setup → interfaces → US1 BlockMap → US3 render delegation → US4 connectors → US2 example block + conditional assets → polish). Inline analyze: full coverage. Next: `/speckit-implement`.

> **✅ SPEC 003 — CLI generators — COMPLETE (2026-06-08).** All 26 tasks; US1–US4 + polish.
> 80 unit + 12 integration green; verified on real WP-CLI (`wp corex make:model` creates a lint-clean,
> namespaced, ABSPATH-guarded Model; idempotent + --force). `WP_CLI` confined to MakeCommand +
> CliServiceProvider (Principle IX). Engine (StubRenderer/Naming/GeneratorEngine) fully headless.
> Generators: model/repository/controller/service. Commits: 819c66a (engine+make:model), e4d2316
> (set+safety), 2bb5688 (WP-CLI). Detailed build log below.

- **SPEC 003 — CLI generators (`wp corex make:*`).** Spec written:
  `specs/003-cli-generators/spec.md` (Draft); quality checklist passed. 4 developer journeys (P1 stub
  engine + make:model, P1 the make:repository/controller/service set, P2 safety/--force/validation, P2
  WP-CLI-optional); 16 FRs, 6 SCs. `/speckit-clarify` done (recommended options auto-selected): output path/namespace/prefix from Config (FR-002); `{{ }}` placeholders (FR-001); make:model scaffolds class only. `/speckit-plan` done (Constitution PASS): engine (StubRenderer/GeneratorEngine/GeneratorResult/Naming) is pure+headless-testable; `MakeCommand`/`CliServiceProvider` are the only WP-CLI seam (registered when `class_exists(WP_CLI)`); stubs in packages/cli/stubs. `/speckit-tasks` done: 26 tasks, TDD-ordered (Setup → Foundational renderer/naming/context → US1 engine+make:model → US2 the set → US3 safety → US4 WP-CLI → polish). Inline analyze: 100% FR/SC coverage, 0 critical. Next: `/speckit-implement`.

> **✅ SPEC 002 — data layer — COMPLETE (2026-06-08).** All 29 tasks; US1–US4 + wiring/polish.
> **77 tests green** (66 unit headless + 11 integration on real `./wp`); site HTTP 200. Guard Gate
> clean (incl. a final whole-module pass: `WP_Query` confined to `QueryExecutor`, WP data calls to
> their layers). Definition of Done met. Delivered: `Models\Model` (read-only value object) ·
> `Repositories\{RepositoryInterface,Hydrator,PostRepository}` (sole data caller) ·
> `Fields\{FieldDriver,MetaFieldDriver,AcfFieldDriver,FieldResolver}` (ACF-optional, native default —
> Principle IX) · `Database\{Collection,QueryBuilder,QueryExecutor}` (capped, value-bound, belongs-to
> eager loading, no N+1) · `Foundation\DataServiceProvider`. DECISIONS #22 (multi-file config).
> Commits: `5f83de0` (Model+US2), `b32a0a7` (US1), `aa05419` (US3), `b6c1c08` (US4),
> `3a044e8` (wiring). Detailed build log below.

- **SPEC 002 — data layer (Model + Field driver + Repository + QueryBuilder).** Spec written:
  `specs/002-data-layer/spec.md` (Draft); quality checklist passed. 4 developer journeys (P1 Model+
  Repository, P1 ACF-optional Field driver, P2 fluent QueryBuilder, P2 eager loading); 23 FRs, 7 SCs.
  `/speckit-clarify` done (2026-06-08, 5 decisions). `/speckit-plan` done (2026-06-08): `plan.md` +
  `research.md` + `data-model.md` + `contracts/data-layer-contracts.md` + `quickstart.md`. Constitution
  Check PASS. Architecture: `Models\Model` (read-only value object) · `Repositories\{RepositoryInterface,
  PostRepository}` (sole data caller) · `Fields\{FieldDriver,FieldResolver,Meta/AcfFieldDriver}` (ACF-
  optional) · `Database\{QueryBuilder (builds capped WP_Query args) → QueryExecutor (only WP_Query
  caller) → Collection}` · `DataServiceProvider`. Key testability split: QueryBuilder is a pure
  arg-builder (unit), QueryExecutor runs the query (integration).
  `/speckit-tasks` done (2026-06-08): `tasks.md` — 29 tasks, TDD-ordered. Setup (T001) → Foundational
  Model (T002–T003) → US2 Field driver (T004–T009) → US1 Repository (T010–T014) → US3 QueryBuilder
  (T015–T021) → US4 eager loading (T022–T024) → Wiring/Polish (T025–T029). Next: `/speckit-implement`
  — ONE task at a time with the Guard Gate, starting at T001.

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

## Workflow (git-flow-lite — adopted 2026-06-09)
Per COREX-FRAMEWORK §19. `main` = production-ready, tagged releases only; `develop` = integration;
`feature/*` = short-lived work off develop. Foundation tagged **`v0.6.0`** (specs 001–006). Spec 007
was built on `feature/007-forms-engine` off `develop` (setup commit on develop), Conventional Commits,
per-story commits with the Guard Gate. **Pending (not yet done):** open the PR `feature/007-forms-engine
→ develop`, push branches/tag to origin, add CI (lint+test+guards) before the first merge, add
`CONTRIBUTING.md`/`CHANGELOG.md`, GitHub branch protection. See DECISIONS #11.

## Next (recommended order)
Per **`ROADMAP.md`** (the locked 009–017 plan). Published to origin through **v0.8.1** (`main`/`develop`
+ tags, green CI). Releases since are local until pushed.
1. **Spec 016 — Corex Brand Identity + Admin Branding** (`corex-config`) — define Corex's identity (navy +
   cyan, an SVG mark) + a configurable logo replacing the WP admin-bar/login/footer logo. Testable parts:
   the SVG/asset + the config + the login/admin-bar hooks; the visual result needs a browser.
2. **Spec 017 — Admin Dashboard / Settings** (React/DataViews) — **needs a Node build + a browser to author
   and verify; cannot be built/verified in this headless environment.** Will be scaffolded + flagged for
   the user's build/browser env.
2. **Browser-verified follow-ups** (need a browser/build env): company-kit visuals + more page compositions;
   custom JS-edit blocks; the React admin dashboard (017).

<!-- prev --> **SPEC 012 — Captcha drivers + Secure uploads** [PHASE 16] — ✅ COMPLETE (2026-06-10). Captcha driver system (corex-captcha) + core upload validator. _(superseded note below)_

<!-- prev --> **SPEC 011 — Custom Tables + TableRepository** [PHASE 15] — ✅ COMPLETE (2026-06-10). Schema builder + Migrator + typed TableRepository + casts in corex-core. _(superseded note below)_

<!-- prev --> **SPEC 010 — Company Website Kit** [PHASE 14] — ✅ COMPLETE (2026-06-09). Blueprint manifest + universal FSE templates (front-page composes corex/* patterns) + header/footer parts; new add-on corex-kit-company. _(superseded note below)_

<!-- prev --> **SPEC 009 — Corex UI block library** [PHASE 13] — ✅ COMPLETE (2026-06-09). Server-rendered corex/* dynamic blocks + Corex section patterns + UI manifest; new add-on corex-ui. _(superseded note below)_

<!-- prev --> **SPEC 008 — Corex Mail (MVP)** [PHASE 12] — ✅ COMPLETE (2026-06-09). Templated secure send + event-seam Mailer + wp_mail driver + email log; new add-on corex-email; Forms delegates to it. _(superseded note below)_

<!-- prev --> **SPEC 007 — Forms** [PHASE 11] — ✅ COMPLETE (2026-06-09). Headless validator + event seam + secured REST submit + FSE form block; new plugin corex-forms. _(superseded note below)_

<!-- prev --> **SPEC 006 — Theme + design tokens** [PHASE 10] — ✅ COMPLETE (2026-06-08). theme.json token source + brand.json runtime overrides (BrandResolver) + style variations + skin discipline. _(superseded note for 005 below)_

<!-- prev --> **SPEC 005 — Middleware + Security** [PHASE 9] — next per COREX-SPECKIT-START. Declarative route middleware (nonce/auth/throttle/sanitize) + the SecurityModule; controllers declare middleware, applied automatically (Principle VII). Built on corex-core + data layer. _(superseded note for 004 below)_

<!-- prev --> **SPEC 004 — corex-blocks (block engine)** [PHASE 8] — next per COREX-SPECKIT-START. FSE blocks with auto-discovery, conditional assets (block.json), Interactivity API, model→block connectors. Built on corex-core + data layer. _(superseded planning note for 003 below)_

<!-- prev --> **SPEC 003 — CLI generators (`wp corex make:*`)** [PHASE 7] — the next module per
   COREX-SPECKIT-START "The rhythm from here". Spec Kit flow: `/speckit-specify` → `/clarify` →
   `/plan` → `/tasks` → `/implement`, ONE task at a time with the Guard Gate + Pest tests. Stub-based
   generators (`make:model`, `make:controller`, `make:repository`, …) built on the corex-core CLI
   surface (`packages/cli`, namespace `Corex\Cli`), scaffolding the patterns specs 001–002 established.

Module build order after CLI generators (COREX-SPECKIT-START.md "The rhythm from here"):
CLI generators → corex-blocks → Middleware + Security → theme + design tokens → Forms →
Abilities/MCP → Corex Mail → other add-ons (profile-manager, woo) → setup wizard + demo content.

## Environment quick reference
- **Site:** http://corex.local · **Admin:** http://corex.local/wp-admin/ (`admin` / `123456`)
- **WP core:** `./wp/` (gitignored, WP 7.0) · **monorepo → WP:** junctions in `wp/wp-content/`
- **WP-CLI:** target the install with `--path=wp`. For `wp db …` commands, prepend the MySQL client:
  `export PATH="/c/wamp64/bin/mysql/mysql8.3.0/bin:$PATH"`
- Full procedure + rationale: DECISIONS.md #18; rule: constitution "Environment Gate" (v1.1.0).
- **DB/Apache start without admin:** the WAMP services (`wampmysqld64`/`wampapache64`) need an elevated
  shell to start via the Service Manager. If they're stopped, launch the MySQL binary directly (no
  elevation): `Start-Process "C:\wamp64\bin\mysql\mysql8.3.0\bin\mysqld.exe" -ArgumentList '--defaults-file="C:\wamp64\bin\mysql\mysql8.3.0\my.ini"' -WindowStyle Hidden`
  (DB `corex` lives in that instance's data dir; port 3306). WP-CLI + the integration suite only need
  MySQL. The browser **HTTP-200 smoke needs Apache** — start full WAMP from the tray (the agent can't
  elevate). Done 2026-06-09: started mysqld this way to satisfy the Environment Gate.
- **Folder-rename gotcha:** the `wp/wp-content/` junctions store the repo's **absolute path**, so
  renaming/moving the repo folder breaks all four. Repoint them (theme + 3 plugins) to the new path
  with `cmd /c rmdir <link>` then `cmd /c mklink /J <link> <target>`. (Done 2026-06-08 after the
  rename `blackstone-new-site` → `corex`; vhost still serves http://corex.local.)

## Open decisions
- **Deploy target** — undecided (DECISIONS.md #11, status Open). Does not block current work.

## Last session summary
2026-06-07 — PHASE 0–4 complete + WordPress environment bootstrapped. Verified env, installed Spec
Kit + guard skills, git on `main` (GitHub remote: github.com/MustafaShaaban/corex), continuity scaffolding, constitution
(now v1.1.0 — added the Environment Gate), §4 monorepo skeleton (guards clean). Then fixed the
missing-WordPress gap: installed WP 7.0 into `./wp/` via WP-CLI on WAMP, mapped the monorepo in via
junctions, activated the Corex theme + 3 plugins (site boots at http://corex.local). Decisions
#15–18 logged. Next: PHASE 5 — corex-core foundation via the Spec Kit flow, one task at a time.
