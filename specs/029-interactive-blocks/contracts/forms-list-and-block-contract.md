# Contract: form-list route + the inline block contract

## REST: `GET corex/v1/forms`

- **Permission**: `current_user_can('edit_posts')` (editor-only; returns 401/403 otherwise).
- **Response**: `200` with `[{ "slug": "<string>", "label": "<string>" }, ...]` from `FormRegistry`.
- **Empty registry**: `200` with `[]` (the editor shows an empty state).
- **Never** returns submissions, schemas, secrets, or any field beyond `slug` + `label`.

## Block `edit` contract (inline-editable blocks)

Each component block's `edit`:
- Wraps output in `useBlockProps()`.
- Renders a `RichText` per text attribute: `tagName`, `value={attributes.<x>}`,
  `onChange={(v) => setAttributes({ <x>: v })}`, translatable `placeholder` (`__()`).
- Uses `InspectorControls` only for non-text options (e.g. a URL, a structural toggle).
- `save: () => null` (the block stays dynamic).

## Block render contract (renderers)

- Read each attribute; output **rich** fields via `wp_kses_post`, **plain** fields via `esc_url`/`esc_attr`/`esc_html`.
- Omit an element whose source attribute is empty (graceful).
- Accordion: iterate the `items` **array**; if `items` is a legacy **string**, parse it with the retained
  fallback so old content still renders.

## corex/form selector contract

- `InspectorControls` shows a `SelectControl` whose options are the `corex/v1/forms` response
  (`label` shown, `slug` stored in `formSlug`).
- No forms → an "No forms found" option/notice; never a broken control.
- The block preview stays `ServerSideRender` (the form is rendered server-side from `formSlug`).
