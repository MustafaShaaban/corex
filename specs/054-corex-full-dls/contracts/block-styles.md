# Contract: Block styles + utilities (US3 — native-first, no new blocks)

Delivered via `register_block_style()` (a corex-ui `BlockStyles` registrar) + token-only SCSS. No new blocks.

## Registered styles

| Style | On block | Class | Tokens used |
|---|---|---|---|
| Card | `core/group` | `is-style-corex-card` | surface bg, border, `--wp--custom--radius--md`, shadow |
| Section | `core/group` | `is-style-corex-section` | spacing rhythm, layout container |
| Striped table | `core/table` | `is-style-corex-striped` | surface-alt, border |
| Button secondary | `core/button` | `is-style-corex-secondary` | accent/border tokens |
| Button ghost | `core/button` | `is-style-corex-ghost` | transparent bg, accent text/border |
| Empty state | `core/group` | `is-style-corex-empty` | ink-soft, spacing, centered |

## Utility (CSS only)

- **Skeleton/loading:** a token-only `.corex-skeleton` shimmer class (motion tokens, surface-alt) for placeholder
  loading; documented (the React Data screen already uses `@wordpress/components` Spinner — documented too).

## Invariants

- Each style registers with the project prefix (`corex-…`) and applies on the documented core/corex block.
- Token-only (no raw hex/size/radius/shadow/motion); logical CSS/RTL; conditional (block-style CSS enqueues with
  the block).

## Test contract (Pest)

`BlockStylesTest`: each style is registered (`WP_Block_Styles_Registry`) on its block with the expected name;
the SCSS scan finds no hardcoded color/size in the style sheet.
