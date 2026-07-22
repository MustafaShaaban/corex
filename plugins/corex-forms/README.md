# CoreX Forms

CoreX Forms provides persisted, versioned visitor flows and a backwards-compatible code-defined form API. It requires
CoreX Core and has no hard dependency on ACF, WooCommerce, or an email provider.

## Visual flows

Open **CoreX → Forms & Flows** as an administrator. The master-detail builder supports:

- draft, published, closed, and expired lifecycle states;
- immutable version snapshots with optimistic conflict detection;
- text, email, phone, number, textarea, select, multi-select, radio, checkbox, date, time, URL, hidden, consent,
  rating, step, and registered custom field types;
- ordered validation, first-match routing with a required fallback, Email Studio bindings, success behavior, preview,
  and marked test submissions;
- inline, page, URL, and registered custom success states.

Publishing validates the current draft. Visitor submissions always use the immutable published version, so historical
submissions retain the exact schema and consent statement used when they were accepted.

## Persisted flow blocks

The following dynamic blocks select only published flows and render from the same stored snapshot:

| Block | Name | Purpose |
|---|---|---|
| Flow | `corex/flow` | General flow presentation |
| Form | `corex/form` | Persisted flow by default; registered forms remain available as a legacy source |
| Success Message | `corex/success-message` | Published inline success copy |
| Subscribe | `corex/subscribe` | Subscription presentation and label |
| Survey | `corex/survey` | Survey presentation and label |
| CTA + Flow | `corex/cta-flow` | Call-to-action content with the published form |

All form variants submit to `POST /wp-json/corex/v1/flows/{id}/submit`. Scripts and token-only logical CSS load only
when their block renders. The shared `window.Corex` runtime provides client validation, loading, accessible notices,
redirect/custom success behavior, and duplicate-submit protection. The server remains authoritative.

## Visitor pipeline

Every published visitor or marked test submission runs the same typed sequence:

```text
validation → protection → storage → routing → email → inbox → timeline
```

The REST boundary applies nonce verification, a flow-shaped sanitizer, and throttling first. The protection stage
records honeypot/captcha/spam evidence — when a reCAPTCHA v3 provider is configured and the form is protected (its
per-form **Protection** setting), it verifies a fresh per-submission token against the form's server-derived action, an
exact hostname allowlist, token age, one-time use, and a score threshold, and stores the typed outcome. The email stage
routes through CoreX Mail when active and falls back to `wp_mail()` otherwise, always recording a typed
**notification-delivery** outcome (`accepted`/`captured`/`queued`/`failed`/`rejected`/`not attempted`) — a saved
submission is never lost to a mail failure, and `wp_mail()` acceptance is recorded as *accepted*, never *sent*. Each
stage returns a traceable state and message. A failure stops later stages, preserves already-committed safe state, and
identifies whether retry is appropriate; it never reports false success.

Stored `corex_submission` records include the flow/version identity, field values, test marker, consent snapshot, UTM
and hidden metadata, spam result, routing result, email result, Inbox assignment, and timeline entry. Marked tests use
`corex_is_test=1` so ordinary metrics and exports can exclude them.

## Extension contracts

Resolve these registries from the PSR-11 container and register extensions during provider boot:

- `FieldTypeRegistry`
- `RuleRegistry`
- `FlowActionRegistry`
- routing target registration through `FlowBehaviorRegistries`
- `EmailVariableRegistry`
- `SuccessStateRegistry`

Extension identifiers are stable lowercase keys. Registered field types and validation rules appear in the builder
extension catalog; published flows fail closed if a referenced type, rule, routing fallback, or success state is not
available.

## Code-defined forms

Existing integrations may still extend `Corex\Forms\Form` and register instances with `FormRegistry`:

```php
use Corex\Forms\Form;

final class ContactForm extends Form
{
    public string $slug = 'contact';

    public function fields(): array
    {
        return [
            'email' => [
                'type' => 'email',
                'label' => __('Email', 'corex'),
                'rules' => ['required', 'email'],
            ],
        ];
    }
}
```

Select **Registered form** in the Form block to render this compatibility path. It posts to
`POST /wp-json/corex/v1/forms/{slug}` and retains the event-listener lifecycle. New administratively managed forms
should use persisted flows.

## Verification

```bash
composer test
composer test:integration
npm run test:js
npm run build --workspace=@corex/forms
```
