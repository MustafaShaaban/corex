# Contract: settings form field types

## SettingsForm::render(value, nonceField)
Builds the form from the registry; each field rendered by `field($name, $field, $value)`.

## field($name, $field, $value) per type
- `text|email|url|password` → `<input type=… value=esc_attr($value) class="regular-text">`.
- `media` → a `url` value input (id=$name) + `<img class="corex-media-preview" src=esc_url($value)>` (hidden
  when empty) + `<button class="corex-media-select" data-target="$name">Select image</button>` + a Remove button.
- `select` → `<select name=$name>` with an `<option>` per `$field['options']` (value=esc_attr, label=esc_html),
  `selected` on `$value`.
- `checkbox` → `<input type="checkbox" name=$name value="1">` (`checked` when `$value` truthy).
- All values escaped; labels via `esc_html`.

## settings.js (wp.media wiring)
- On `.corex-media-select` click: open `wp.media`, on select write the attachment URL into the `data-target`
  input and update the sibling preview. `.corex-media-remove` clears them. Enqueued only on the settings screen.

## AdminDashboard
- Enqueues `settings.js` + `wp_enqueue_media()` on its screen; renders the configured logo in the header
  (escaped, only when `BrandingService::logoUrl()` is non-empty). Saving stays nonce + cap gated (unchanged).
