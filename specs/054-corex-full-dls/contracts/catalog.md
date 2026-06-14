# Contract: DesignSystemCatalog (US1)

The expanded catalog — a pure registry, drift-checked. No new runtime surface; it is read by docs/tests.

## Shape

`entries(): list<{ name, category, block, mechanism }>` — enumerates the full taxonomy.
`byCategory(string $category): list<...>` — filters to one of foundation/component/block/pattern/template/guideline.
`blockNames(): list<string>` — the `corex/*` names where `mechanism = corex-block` (the drift surface).

## Invariants (tested)

1. All six categories are non-empty (the taxonomy is fully enumerated — SC-001).
2. Every `mechanism = corex-block` entry has a non-null `block` that is **registered** in WP; the drift test
   fails if any listed block is unregistered, **or** if a registered `corex/*` UI block is missing from the
   catalog (in-sync both directions for the blocks it claims).
3. Entries with `mechanism ∈ {block-style, core-block, token, runtime, deferred}` have `block = null` and are
   **excluded** from the registered-block check (they are not blocks).
4. The new `corex/modal` appears as a `component` with `mechanism = corex-block`.

## Test contract (Pest)

`DesignSystemCatalogTest`: asserts each category present; `blockNames()` ⊆ registered `corex/*` blocks; the
section blocks + alert/badge/breadcrumbs + modal are listed; block-style/core/deferred entries carry `block:null`.
