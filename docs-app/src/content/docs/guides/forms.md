---
title: Create a form
description: Define a form once; get server validation, generated client validation, and AJAX submit.
---

A Corex form is a class: a slug and a field map. That one definition is the **single
source of truth** — the server validates against it, and the same schema is exported to
the browser so client-side validation is never a second hand-kept copy.

## Define it

```php
use Corex\Forms\Form;

final class ContactForm extends Form
{
    public string $slug = 'contact';

    public function fields(): array
    {
        return [
            'name'    => ['type' => 'text', 'rules' => ['required', 'max:120'], 'label' => __('Name', 'corex')],
            'email'   => ['type' => 'email', 'rules' => ['required', 'email'], 'label' => __('Email', 'corex')],
            'message' => ['type' => 'textarea', 'rules' => ['required', 'max:2000'], 'label' => __('Message', 'corex')],
        ];
    }
}
```

Register it with the `FormRegistry` in a provider's `boot()`.

## Field definition reference

| Key | Values |
|---|---|
| `type` | `text` `email` `number` `tel` `url` `password` `date` `file` `textarea` `select` `radio` `checkbox` `checkbox-group` `toggle` |
| `rules` | `required` `email` `max:N` `min:N` `numeric` |
| `options` | `value => label` (for `select`/`radio`/`checkbox-group`) |
| `label_mode` | `visible` (default) `hidden` `inline` |
| `width` | `full` (default) `half` `third` `two-thirds` `quarter` (12-col grid) |
| `class` | extra class on the control |
| `attrs` | extra HTML attributes (whitelisted; `name/id/type/class/required` and `on*` are dropped) |

## Place the block

Add the **Corex Form** block and set its `formSlug` to your slug. It server-renders the
form (accessible labels, per-field error regions, the REST nonce, a honeypot) and embeds
the schema.

## How validation flows

1. **Client** — `view.js` reads the embedded schema and runs the shared rules
   (`validation.js`) for instant, field-level feedback.
2. **Submit** — a valid form posts JSON to the secured REST route with the WP nonce.
3. **Server** — re-validates the **same schema** (authoritative), then dispatches a
   `FormSubmittedEvent` to the form's listeners (store + email).

After defining a form, run `npm run build` so the block's `view.js`/`style.scss` compile.
The rules are tested on both sides (`composer test` and `npm run test:js`).
