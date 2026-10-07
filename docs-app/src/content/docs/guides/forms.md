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
            'name'    => ['type' => 'text', 'rules' => ['required', 'max_length:120'], 'label' => __('Name', 'corex')],
            'email'   => ['type' => 'email', 'rules' => ['required', 'email'], 'label' => __('Email', 'corex')],
            'message' => ['type' => 'textarea', 'rules' => ['required', 'max_length:2000'], 'label' => __('Message', 'corex')],
        ];
    }
}
```

Register it with the `FormRegistry` in a provider's `boot()`.

## Field definition reference

| Key | Values |
|---|---|
| `type` | `text` `email` `number` `tel` `url` `password` `date` `file` `textarea` `select` `radio` `checkbox` `checkbox-group` `toggle` |
| `rules` | `required` `email` `max_length:N` `min_length:N` `max:N` `min:N` `numeric` `phone` `phone:national` |
| `options` | `value => label` (for `select`/`radio`/`checkbox-group`) |
| `label_mode` | `visible` (default) `hidden` `inline` |
| `width` | `full` (default) `half` `third` `two-thirds` `quarter` (12-col grid) |
| `class` | extra class on the control |
| `attrs` | extra HTML attributes (whitelisted; `name/id/type/class/required` and `on*` are dropped) |

**What `max:N` and `min:N` measure depends on the field, not on the answer.** On a field that is a
number they compare the number; on every other field they count characters, so `max:300` on a message
accepts the answer `2025`. A field is a number when its `type` is `number` or `rating`, or when its
rules include `numeric`. `max_length:N` and `min_length:N` count characters on any field.

A quantity typed into a `text` field is bounded as a number only if the field says it is one:

```php
'quantity' => ['type' => 'text', 'rules' => ['numeric', 'max:10']],  // refuses 11
'quantity' => ['type' => 'text', 'rules' => ['max:10']],             // accepts 99999: five characters
```

**`phone` is an international number; `phone:national` also takes a local one.** Both ignore spaces,
brackets, hyphens and dots. `phone` accepts E.164: an optional `+`, a first digit that is not zero,
up to fifteen digits. It refuses `010 1699 9700`, because a number that starts with its country's
trunk `0` cannot be dialled without knowing the country. `phone:national` accepts that form too: a
`0` followed by six to fourteen digits. Use it on a form whose audience is local.

```php
'phone' => ['type' => 'tel', 'rules' => ['phone']],           // +20 101 699 9700
'phone' => ['type' => 'tel', 'rules' => ['phone:national']],  // +20 101 699 9700 or 010 1699 9700
```

## Place the block

Add the **Corex Form** block and set its `formSlug` to your slug. It server-renders the
form (accessible labels, per-field error regions, the REST nonce, a honeypot) and embeds
the schema.

## How validation flows

1. **Client** — the shared [`window.Corex` runtime](/guides/frontend-runtime/) reads the embedded
   schema and runs the same rules for instant, field-level feedback.
2. **Submit** — a valid form posts JSON to the secured REST route with the WP nonce.
3. **Server** — re-validates the **same schema** (authoritative), then dispatches a
   `FormSubmittedEvent` to the form's listeners (store + email).

After defining a form, run `npm run build` so the block's `view.js`/`style.scss` compile.
The rules are tested on both sides (`composer test` and `npm run test:js`).

### Listeners belong to the form that declares them

`Form::listeners()` returns the listener service ids for **that form's** submissions, and a concrete
form may override it to add listeners or to remove them:

```php
final class ContactForm extends Form
{
    public string $slug = 'contact';

    public function listeners(): array
    {
        // Store the submission, but send our own notification instead of the built-in one.
        return [StoreSubmissionListener::class, MyBrandedNotification::class];
    }
}
```

Returning `[]` runs nothing. Only the submitted form's listeners run — a listener declared by one
form never fires for another form's submission.

Listeners are resolved when a submission arrives, not at boot, and each is a singleton: one shared
by several forms is constructed once.

A submission whose slug matches no registered `Form` — a flow defined in the database rather than in
code — runs no listeners at all.

### What the notification contains

The built-in notification is HTML, because every CoreX mail transport declares
`Content-Type: text/html`. Values are escaped and multi-line answers keep their line breaks.

When the submission carries a valid email address in an `email`, `e-mail`, `email_address` or
`reply_to` field, it becomes the message's `Reply-To`, so whoever receives the notification can
answer the person who wrote in. If the form never asked for an address, no `Reply-To` is set.
