# Research: Interactive, inline-editable blocks (029)

## R1 — Inline editing without giving up dynamic/server rendering

**Decision**: Use the **hybrid** pattern — the `edit` component renders `RichText` (inline canvas editing) bound
to block **attributes**; `save: () => null`; the PHP `render_callback` reads those attributes and outputs the
markup. The block stays dynamic (Principle VI) and gains inline editing.

**Rationale**: WordPress supports exactly this — `RichText` writes HTML to an attribute; a dynamic block's
renderer reads it. You get one source of truth (attributes), an editor that matches the front end, and the
modern "type in the canvas" UX, without converting to a static save-markup block.

**Alternatives considered**: static save-based blocks (rejected — would store markup in post content, drifting
from the dynamic-block principle and the server-render contract); keep `ServerSideRender`-only (rejected — it is
a read-only preview, no inline editing — the exact complaint).

## R2 — Escaping rich text safely

**Decision**: Rich-text attributes (heading, quote, content) render with **`wp_kses_post`** (allows the safe
post-content HTML subset RichText produces, strips scripts/handlers). Plain fields (a URL, a CSS value) keep
`esc_url`/`esc_attr`/`esc_html`.

**Rationale**: `esc_html` would double-escape RichText's `<strong>`/`<a>`. `wp_kses_post` is the WordPress
function for "user-authored rich content" and is wp-guard-approved for this context.

## R3 — Where the form list comes from

**Decision**: A new read-only REST route `corex/v1/forms` (GET, `permission_callback` = `current_user_can(
'edit_posts')`) returns `[{slug,label}]` from the existing `FormRegistry`. The editor populates the form
`SelectControl` via `apiFetch`.

**Rationale**: `FormRegistry` already knows the registered forms; a thin cap-gated route exposes just
slug+label for the editor. REST is the natural editor data source and reuses the secured-route discipline.
Returning only slug+label means no submissions or secrets leak.

**Alternatives considered**: a global JS variable printed at enqueue (rejected — staler, not cap-scoped per
request); a block-variation per form (rejected — doesn't scale, and forms are runtime data).

## R4 — Accordion data shape (string → array)

**Decision**: Move the accordion from a single delimited `items` string to an **array attribute**
`items: [{title, content}]`, edited as repeatable RichText rows, so each panel's title + content are inline-rich
and individually editable. The renderer keeps the **old delimited-string parse as a fallback** so accordions
already placed with the string still render (FR-008).

**Rationale**: A delimited string can't be inline-rich per panel; an array of `{title,content}` is the natural
shape for repeatable rich content and matches how core list-like blocks store data. The fallback avoids
breaking existing content.

**Alternatives considered**: InnerBlocks of a child "accordion-item" block (rejected for this spec — heavier;
deferred to spec 035 if needed); keep the string (rejected — blocks the inline-rich requirement).
