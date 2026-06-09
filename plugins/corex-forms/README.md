# Corex Forms

The Corex forms engine: code-defined form schemas, a headless validator, a secured submit
lifecycle, and an FSE form block. Built on corex-core — the event seam, the spec-005
security middleware, the data layer, and the block engine. Works on a stock install with
corex-core active; no optional plugin is a hard dependency.

> Requires the `corex-core` plugin active.

## Define a form

A form is a class: a slug and a field map. Each field declares a type, an ordered rule
list, and a label.

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

Register it with the `FormRegistry` in a provider's `boot()`. Field names are normalized to
a canonical key (used for the input name and the `corex_field_*` meta); two names that
normalize to the same key are rejected at schema resolution, as is an unknown rule.

## Validation

The validator is pure (no WordPress) and runs the rules in order, recording **at most one
error per field** — the first rule to fail (bail per field):

```php
$schema = $resolver->resolve($form->fields());
$result = $validator->validate($schema, ['name' => '', 'email' => 'not-an-email']);

$result->isValid();   // false
$result->errors;      // ['name' => 'required', 'email' => 'email']  — i18n message keys
$result->values;      // normalized values for the declared fields only
```

An absent optional field is valid and not stored; values for fields not in the schema are
ignored. v1 rules:

| Rule | Fails when |
|---|---|
| `required` | value is absent, empty/whitespace, or an empty array |
| `email` | a non-empty value is not a valid email address |
| `max:N` | numeric value `> N`, or string length `> N` |
| `min:N` | numeric value `< N`, or string length `< N` |
| `numeric` | a non-empty value is not numeric |

Errors are returned as message **keys** (`required`, `email`, `max`, …) so the presentation
layer owns the translated text.

## Submit lifecycle

A submission posts to `POST /wp-json/corex/v1/forms/{slug}`. The controller is thin — it
runs the request through the declared security middleware and delegates:

```
nonce  →  form-shaped sanitize  →  throttle  →  FormSubmissionService
                                                  honeypot → validate → dispatch
```

- **Nonce** — the WP REST nonce (`wp_rest`), sent as the `X-WP-Nonce` header; a non-GET
  request without it is rejected (403).
- **Sanitize** — only the form's declared fields (plus the honeypot) survive, each through
  its type's sanitizer.
- **Throttle** — rate-limited per form + client (transient-backed; `config('security.throttle.*')`).
- **Honeypot** — a hidden `corex_hp` field; if filled, the submission is rejected silently (422).
- **Validation** — on failure, a 422 carries the per-field error keys; nothing is stored or dispatched.

A fully valid submission dispatches a `FormSubmittedEvent` (slug + validated values) to the
form's listeners. The default set:

- **StoreSubmissionListener** — persists a non-public `corex_submission` post via the data
  layer, with the form slug and each value as `corex_field_*` meta (queryable by slug).
- **SendEmailListener** — `wp_mail` to `config('forms.email.recipient')`, falling back to the
  site admin email.

Dispatch is best-effort: a listener that throws is logged; the rest still run and the
submission is still accepted.

## The form block

Add the **Corex Form** block (`corex/form`) and choose a form by slug (`formSlug`
attribute, default `contact`). It server-renders the form from its schema: every field with
a label-bound input, required markers (`aria-required`), an `aria-live` status region, the
REST nonce, and the honeypot. Styling uses `theme.json` tokens and logical properties
(RTL-correct); the view script and style load only on pages where the block is present.

## Configuration

`forms.email.recipient` — where the contact notification is sent (empty → site admin email).
Set it through the Config engine: a WordPress option `corex_forms_email_recipient`, or
`FORMS_EMAIL_RECIPIENT` in the project `.env`.

## Tests

```bash
composer test              # headless: validator, schema resolver, event dispatch, submission service, block render
composer test:integration  # real ./wp: the secured submit lifecycle for the contact form
```
