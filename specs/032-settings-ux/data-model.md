# Data Model: Modern settings UX (032)

## Field definition (SettingsRegistry)
| Field | Type | Notes |
|---|---|---|
| `label` | string | shown in the form |
| `type` | `text\|email\|url\|password\|media\|select\|checkbox` | drives the control |
| `options` | `array<string,string>` | value => label, for `select` only |

## Per-type rendering (SettingsForm::field)
- **input** (`text/email/url/password`): `<input type=… class="regular-text" value=esc_attr>`.
- **media**: `<input type="url" id=name value=esc_attr>` + `<img class="corex-media-preview" src=esc_url>` +
  `<button class="corex-media-select" data-target=name>Select image</button>` + `<button class="corex-media-remove">Remove</button>`.
- **select**: `<select name=name>` with `<option value=esc_attr>esc_html(label)</option>` per option,
  `selected` on the current value.
- **checkbox**: `<input type="checkbox" name=name value="1" checked?>`.

## Persisted values
- media → the image **URL** (string). select → an option value. checkbox → `1` or absent/empty.
- Read back via the Config option layer exactly as before (`SettingsStore` unchanged).

## Registry changes
- `brand.logo_url` → `media`.
- `captcha.driver` → `select` options: none / honeypot / recaptcha / turnstile / hcaptcha.
