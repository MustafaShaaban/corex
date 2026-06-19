# Contract: Token Inventory, Mapping, and Compatibility

## Authority

- `theme/theme.json` is the single client-facing runtime token authority.
- WordPress-generated `--wp--preset--*` and `--wp--custom--*` properties are the public consumption layer.
- `theme/styles/*.json` provides complete alternate mappings, not a second vocabulary.
- `brand.json` overrides the theme data at runtime through the existing brand resolver.
- `--corex-admin-*` is a scoped adapter for CoreX wp-admin screens only, not a client token source.

## Inventory record

Every token and consumer must be represented using the entities in [data-model.md](../data-model.md). An inventory
is complete only when:

1. every theme/style-variation definition is recorded;
2. every generated property name is deterministic;
3. every repository reference resolves to a canonical definition or active alias;
4. every raw design value has an approved allowance; and
5. every mode, admin, and client-override impact is classified.

## Classification contract

| Classification | Required behavior |
|---|---|
| `retained` | Name and semantic meaning remain stable. Values may change only with mode/accessibility evidence. |
| `added` | Proven semantic gap; no duplicate existing role; documented in every required mapping. |
| `aliased` | Legacy name resolves to one canonical role; carries replacement and introduction version. |
| `migrated` | First-party consumer uses the canonical role; owning package tests pass. |
| `deprecated` | Alias remains functional and documented for at least one minor release. |

An alias is removal-eligible only when scans show zero first-party consumers and the minimum deprecation window has
elapsed.

## Initial classification targets

- Retain the currently defined stable palette, size, spacing, shadow, radius, motion, focus, z, and layout names
  unless inventory evidence proves a semantic collision.
- Add only roles required for system body, mono/code, inverse text, complete raised/strong surface semantics, and
  reusable border width/strength where no current role can express them. Extend focus, radius, spacing, or shadow
  scales only when inventory evidence proves a missing reusable role.
- Alias and migrate undefined legacy references such as `background → surface`, `foreground → ink`,
  `danger → error`, `small → sm`, and `medium → base`. The inventory determines the final mapping before code.
- Classify `corex-*` and `white` preset references per consumer: migrate to canonical client roles or the scoped
  admin adapter; do not create permanent duplicate public vocabulary.

## Mode and style-variation contract

- Default/light and dark include every required palette/font slug.
- Editorial remains compatible and includes every required replacement-list slug.
- Components consume canonical names; mode files change values only.
- No accepted value may leave a required semantic or focus pair below its evidence threshold.

## `brand.json` contract

- Associative maps merge recursively.
- Lists replace wholesale.
- Palette and font-preset list replacements are complete.
- Incomplete required lists produce an explicit validation report and retain safe defaults; they do not trigger
  merge-by-slug behavior.
- Missing/malformed files retain existing default behavior.
- Documentation examples must show complete replacement arrays or associative-only overrides.

## Admin adapter contract

- Registered once by `corex-core` and enqueued only as a dependency of CoreX-owned admin screen styles.
- Independently booted add-ons consume the shared handle without depending on `corex-config`.
- Defined on CoreX admin root selectors, not the global admin document.
- Uses `--corex-admin-*` names for the minimum semantic role set.
- Maps to stable WordPress admin variables when verified; otherwise uses one centralized documented fallback.
- Admin component files contain no duplicated palette fallback chains after migration.
- Adapter properties do not appear on unrelated admin screens and do not accept client `brand.json` values.

## Rollback contract

- Restore the previous complete theme/style-variation arrays and active aliases together.
- Keep client merge semantics unchanged.
- Restore consumer references to an active canonical/alias name; never use emergency local literals.
- Asset rollback removes font/logo integration without changing stored content or runtime boot.
