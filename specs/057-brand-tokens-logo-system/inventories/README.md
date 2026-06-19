# Spec 057 Inventory Schema

This directory records the pre-implementation token and consumer baseline for Spec 057. It is evidence, not a
second runtime token source. `theme/theme.json` remains the canonical client-facing authority.

## Files

| File | Purpose |
|---|---|
| `baseline.md` | Commands, environment status, task boundary, and inventory summary. |
| `definitions.json` | Current canonical definitions and their exact JSON paths. |
| `variations.json` | Dark and Editorial replacement arrays, mappings, and completeness gaps. |
| `generated-properties.json` | Deterministic WordPress property name for each current definition. |
| `consumers.json` | Tracked token consumers under `theme/`, `plugins/`, `addons/`, and `packages/`. |
| `docs-and-brand.json` | Documentation references, current override example shape, resolver behavior, and fixtures. |
| `admin-and-aliases.json` | Admin literals/fallback chains, enqueue owners/scopes, and legacy references. |
| `classifications.json` | Retained/added/aliased/migrated/deprecated categories, owner batches, compatibility, and blockers. |

## Record contracts

The field contracts come from `specs/057-brand-tokens-logo-system/data-model.md`:

- Token definitions identify `id`, `group`, canonical `source_path`, `generated_property`, semantic role, mode
  mappings, classification, compatibility metadata, and evidence status.
- Token consumers identify the owning path/context, referenced property, owner/surface/direction context,
  resolution, and canonical target.
- Variation records treat palette and font arrays as wholesale replacements and report missing required slugs.
- Admin records separate repeated design fallbacks from functional layout allowances; neither becomes a client
  branding authority.
- Classifications use only `retained`, `added`, `aliased`, `migrated`, and `deprecated`. An empty category is
  explicit; it is not omitted.

## Status vocabulary

- `planned`: inventory or evidence exists but its implementation contract has not run.
- `headless-pass`: the named headless check executed and passed.
- `browser-pass`: the named browser check executed and passed.
- `BLOCKED`: required owner input or asset approval is missing.
- `ENVIRONMENT-GATED`: the required runtime was unavailable; this is never a pass.

## Scan boundary and regeneration

This baseline uses Git-tracked CSS, SCSS, JSON, PHP, JavaScript, and Markdown. Ignored dependency/build output and
untracked files are excluded. Documentation and tests are recorded separately from runtime consumers. T028 will
replace this one-time baseline process with a deterministic repository script and drift tests; until then, changes
to token sources or consumers require an explicit inventory refresh.

No file in this directory changes token values, CSS, assets, or runtime behavior.
