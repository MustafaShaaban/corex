# Data Model: Brand Tokens and Logo System

This feature has no database model. These planning entities define the file-based contracts and evidence records
that implementation and tests must produce.

## Token Definition

| Field | Type | Rules |
|---|---|---|
| `id` | string | Unique canonical identifier, normally the WordPress slug/path. |
| `group` | enum | `color`, `font-family`, `font-size`, `spacing`, `shadow`, `radius`, `border`, `focus`, `motion`, `z`, `layout`. |
| `source_path` | path | Canonical definition file and JSON path; client-facing values resolve to `theme/theme.json`. |
| `generated_property` | string | Exact WordPress-generated custom property or documented custom property. |
| `semantic_role` | string | Stable purpose, not a component/page name. |
| `default_mapping` | string | Default/light semantic value reference. |
| `dark_mapping` | string | Dark semantic value reference. Required for client-facing color/font roles. |
| `classification` | enum | `retained`, `added`, `aliased`, `migrated`, `deprecated`. |
| `replacement_id` | string/null | Required for alias/deprecated records; points to the canonical definition. |
| `introduced_version` | string/null | Release where the definition becomes public. |
| `remove_after_version` | string/null | Earliest removal release; never earlier than one minor release after deprecation. |
| `evidence_status` | enum | `planned`, `headless-pass`, `browser-pass`, `environment-gated`, `blocked`. |

### Validation

- Canonical identifiers and generated properties are unique.
- Alias/deprecated records have a valid canonical replacement.
- Deprecated records remain while any first-party consumer exists.
- Client-facing color/font roles have complete default/light and dark mappings.
- Values are not owned by component files.

## Token Consumer

| Field | Type | Rules |
|---|---|---|
| `path` | path | Repository-relative consumer path. |
| `selector_or_context` | string | Selector, JSON path, PHP lookup, documentation example, or test fixture. |
| `property` | string | Referenced generated/custom property. |
| `owner` | enum | `theme`, `core-plugin`, `config-plugin`, `blocks-plugin`, `forms-plugin`, `ui-addon`, `other-addon`, `cli`, `docs`, `test`. |
| `surface` | enum | `front-end`, `editor`, `admin`, `email`, `docs`, `fixture`. |
| `direction_context` | enum | `logical`, `ltr-only`, `rtl-only`, `mixed`, `not-applicable`. |
| `resolution` | enum | `valid`, `alias-required`, `migration-required`, `raw-allowance`, `invalid`. |
| `target_definition` | string | Canonical token id after alignment. |

### Validation

- Every property resolves to a definition or a time-bounded alias.
- Every raw value is either a functional layout constant or a centralized admin fallback; all other raw design
  values fail inventory validation.
- Migration batches are grouped by owner and retain conditional asset behavior.

## Mode Mapping

| Field | Type | Rules |
|---|---|---|
| `mode` | enum | `light`, `dark`, `editorial`. |
| `token_id` | string | Existing Token Definition. |
| `value` | string | Final value selected during implementation. |
| `required_pair_ids` | list<string> | Evidence pairings that must pass before acceptance. |
| `status` | enum | `planned`, `pass`, `fail`, `blocked`. |

Every replacement palette/font list contains the complete required token set.

## Admin Adapter Role

| Field | Type | Rules |
|---|---|---|
| `property` | string | Scoped `--corex-admin-*` name. |
| `semantic_role` | string | Surface, text, border, action, status, focus, spacing, or radius role. |
| `wordpress_variable` | string/null | Stable WordPress admin variable when available. |
| `fallback` | string | One centrally documented WordPress palette/layout fallback. |
| `consumers` | list<path> | CoreX admin styles only. |
| `scope_selector` | string | CoreX admin root; must not be global. |

## Brand Override Fixture

| Field | Type | Rules |
|---|---|---|
| `name` | string | Unique fixture name. |
| `shape` | enum | `associative-partial`, `complete-list`, `incomplete-list`, `malformed`, `missing`. |
| `input_path` | path | Fixture JSON path. |
| `expected_result` | enum | `merge`, `replace`, `report-and-default`, `ignore-and-default`. |
| `required_slugs` | list<string> | Complete required palette/font set when a list is replaced. |
| `compatibility_note` | string | Existing behavior protected by the fixture. |

## Font Asset Record

| Field | Type | Rules |
|---|---|---|
| `family` | enum | `Space Grotesk`, `JetBrains Mono`, `IBM Plex Sans Arabic`. |
| `role` | enum | `display-heading`, `code-technical`, `arabic`. |
| `path` | path | Self-hosted WOFF2 path. |
| `weights` | range/list | 500–700, 400–600, or Arabic 400/600 as specified. |
| `script_subset` | enum | `latin` or `arabic`. |
| `license_source` | string | Verifiable upstream source/license. |
| `font_display` | literal | `swap`. |
| `preload` | boolean | Defaults false; true requires evidence id. |
| `evidence_id` | string/null | Required only for a preload exception. |

At most four records/files may ship.

## Logo Asset Record

| Field | Type | Rules |
|---|---|---|
| `variant` | enum | `symbol`, `wordmark`, `lockup`, `monochrome`, `contrast`. |
| `path` | path | Optimized SVG in the approved package. |
| `source` | string | Authoritative package/source location. |
| `author_or_owner` | string | Provenance owner. |
| `license_or_rights` | string | Usage rights. |
| `approval_date` | date | Explicit owner approval. |
| `view_box` | string | Required SVG viewBox. |
| `accessible_usage` | enum | `decorative`, `named-image`, `linked-brand`. |
| `status` | enum | `blocked`, `approved`, `validated`, `integrated`. |

No record may move beyond `blocked` without owner approval/provenance.

## Accessibility Evidence

| Field | Type | Rules |
|---|---|---|
| `id` | string | Unique evidence id. |
| `kind` | enum | `contrast`, `focus`, `forced-colors`, `zoom`, `direction`, `font-loading`, `logo-render`. |
| `mode` | enum/null | `light`, `dark`, `editorial`, or null. |
| `foreground_or_subject` | string | Token, selector, text fixture, or asset. |
| `background_or_context` | string | Paired surface/context. |
| `threshold` | number/string/null | 4.5:1, 3:1, 200%, or a qualitative manual contract. |
| `method` | enum | `automated`, `manual`, `browser`, `network`. |
| `result` | enum | `pass`, `fail`, `blocked`, `environment-gated`. |
| `artifact` | path/url/null | Test output, screenshot, or recorded note when available. |

## State Transitions

### Token migration

`discovered → classified → alias-added (if needed) → consumer-migrated → deprecated → removal-eligible`

Removal eligibility requires at least one minor release of deprecation and zero first-party consumers.

### Logo package

`blocked → owner-approved → provenance-validated → asset-validated → integrated → rendered-evidence-pass`

Planning ends with `blocked` until the owner package is available.

### Evidence

`planned → headless-pass → browser-pass`

If the required environment is unavailable, use `environment-gated`; never translate it to `pass`.
