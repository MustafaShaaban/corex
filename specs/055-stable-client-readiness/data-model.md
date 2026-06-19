# Data Model: Stable Client Readiness (055)

Spec 055 is mostly validation, runtime gating, and governance. The entities below are planning models for pure
services, reports, docs matrices, and tests; they are not new persistent database tables unless later tasks justify
storage.

## ReadinessFinding

| Field | Type | Notes |
|---|---|---|
| `category` | enum | `runtime-gating`, `metadata`, `ci-security`, `make-site`, `deployment`, `component-coverage`, `free-pro`, `multi-agent` |
| `status` | enum | `pass`, `fail`, `warning`, `environment-gated`, `not-run` |
| `summary` | string | One-line finding |
| `evidence` | list<string> | Commands, files, or checks supporting the status |
| `owner` | enum/string | `core`, `config`, `cli`, `docs`, `repo-settings`, `client-site`, or named maintainer |
| `blocking` | bool | Whether it blocks client-site work |
| `next_action` | string | Concrete remediation or follow-up |

**Rules:** every required readiness category must have at least one finding; `environment-gated` requires a reason
and the command/profile that would verify it.

## AddonRuntimeState

| Field | Type | Notes |
|---|---|---|
| `slug` | string | First-party add-on slug, e.g. `corex-ui`, `corex-kit-woo` |
| `provider_class` | class-string | Service provider candidate |
| `installed` | bool | Plugin/add-on files available |
| `active` | bool | Corex activation state says it may run |
| `dependencies` | list<string> | Required Corex add-ons |
| `missing_dependencies` | list<string> | Required add-ons or external integrations unavailable |
| `external_gate` | string|null | Example: WooCommerce availability |
| `safe_disabled_behaviors` | list<string> | Explicit behaviors allowed while disabled, if any |

**States:** `active`, `inactive`, `dependency-missing`, `not-installed`, `safe-disabled`.

**Rules:** only `active` providers may register unsafe behavior. Woo providers require both Corex active state and
WooCommerce availability. Disabled behavior is denied by default unless named safe.

## ProviderResolution

| Field | Type | Notes |
|---|---|---|
| `core_providers` | list<class-string> | Always-on core framework providers |
| `candidate_addon_providers` | list<class-string> | Optional first-party providers |
| `included_providers` | list<class-string> | Providers passed to `Application` |
| `excluded_providers` | map<class-string,string> | Provider => reason |

**Rules:** core providers stay self-booting; optional add-ons are included only when their `AddonRuntimeState` is
active and dependencies pass.

## MetadataSurface

| Field | Type | Notes |
|---|---|---|
| `path` | string | File or remote surface |
| `kind` | enum | `composer`, `package`, `plugin-header`, `constant`, `update-uri`, `readme`, `changelog`, `progress`, `docs`, `tag` |
| `expected` | string | Expected release or metadata value |
| `actual` | string|null | Read value |
| `status` | enum | `match`, `mismatch`, `missing`, `ignored-by-policy` |

**Rules:** mismatches report exact path, field, expected, and actual value. Policy exceptions must be explicit.

## ClientSiteScaffold

| Field | Type | Notes |
|---|---|---|
| `name` | string | Raw client/site name |
| `plugin_slug` | string | Generated client plugin slug |
| `theme_slug` | string | Generated client theme slug |
| `namespace` | string | Client namespace, distinct from `Corex\` |
| `css_prefix` | string | Client CSS prefix, distinct from `--corex-` |
| `option_prefix` | string | Client option prefix |
| `governance_files` | list<string> | `AGENTS.md`, `CLAUDE.md`, `PROGRESS.md`, `DECISIONS.md`, etc. |
| `token_files` | list<string> | `brand.json`, `theme.json`, or documented token placeholders |
| `mode` | enum | `minimal`, `starter`, `plugin-only`, `theme-only` |

**Rules:** generated client branding edits stay inside generated client folders. Compliance flags edits to Corex
framework folders for client-specific identity.

## DeploymentProfile

| Field | Type | Notes |
|---|---|---|
| `name` | enum | `minimal`, `standard`, `full`, `woo`, `client-site`, `shared-host`, `azure-container`, `local-docker`, `wp-env-stable`, `wp-env-trunk` |
| `package_shape` | string | What is deployed and what remains source-only |
| `build_commands` | list<string> | Required commands |
| `dependencies` | list<string> | PHP extensions, Node, MySQL, WooCommerce, Docker, etc. |
| `secrets` | list<string> | Required env vars or secret classes |
| `blockers` | list<string> | Known missing or environment-gated items |

**Rules:** a profile can be incomplete only if the blocker is named and does not masquerade as pass.

## ComponentCoverageItem

| Field | Type | Notes |
|---|---|---|
| `need` | string | Company-site need: home hero, services grid, contact form, careers list, etc. |
| `mechanism` | enum | `corex-block`, `wordpress-core-block-style`, `pattern`, `form-field`, `admin-component`, `utility`, `missing`, `deferred`, `pro-candidate` |
| `source` | string | Existing block/style/pattern/file or planned location |
| `accessibility` | string | Keyboard/ARIA/WCAG expectation |
| `token_strategy` | string | theme.json/CSS variable strategy |
| `rtl_strategy` | string | Logical CSS/RTL expectation |
| `free_pro` | enum | `free-core`, `pro-candidate`, `deferred`, `out-of-scope` |

**Rules:** native WordPress/Corex mechanisms win over new blocks. `missing` requires a task decision before build.

## FreeProBoundaryItem

| Field | Type | Notes |
|---|---|---|
| `capability` | string | Feature or service |
| `classification` | enum | `free-core`, `pro-candidate`, `deferred`, `out-of-scope` |
| `reason` | string | Adoption/security/basic vs advanced/commercial rationale |
| `security_critical` | bool | Security, a11y, i18n, RTL, privacy, or trust baseline |

**Rules:** `security_critical = true` cannot be `pro-candidate`.

## AgentWorkUnit

| Field | Type | Notes |
|---|---|---|
| `branch` | string | One task/agent branch |
| `spec_path` | string | Owning spec |
| `task_ids` | list<string> | Tasks claimed |
| `files_owned` | list<string> | Expected edit surfaces |
| `handoff` | string | Latest durable handoff note |
| `verification` | list<string> | Commands and results |
| `guards` | list<string> | Guard skills run |
| `status` | enum | `planned`, `in-progress`, `blocked`, `ready-for-review`, `done` |

**Rules:** no work on `main`; git status first; no overlapping file ownership without coordination; completion
claims include verification and guards.
