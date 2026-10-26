# CoreX Forms

CoreX Forms provides persisted, versioned visitor flows and a backwards-compatible code-defined form API. It requires
CoreX Core and has no hard dependency on ACF, WooCommerce, or an email provider.

## Visual flows

Open **CoreX → Forms & Flows** as an administrator. The master-detail builder supports:

- draft, published, closed, and expired lifecycle states;
- immutable version snapshots with optimistic conflict detection;
- **Publish changes**: a saved draft of a live flow becomes the live version in one step, and the form stays on
  every page throughout. Each publication is recorded with its date and author, the **Versions** tab lists them,
  and any of them can be copied into the draft. **Unpublish** asks first;
- **Availability**: an open date, a close date and a response cap, with the message and the other way to get in
  touch a visitor reads when the form is not taking answers. Saved on its own, it applies without publishing;
- routing to a **user, team, role, the flow's owner, the page's author or the owner of the answer given**, and to
  registered routing extensions. Teams are made on the **Teams** view;
- text, email, phone, number, textarea, select, multi-select, radio, checkbox, date, time, URL, hidden, consent,
  rating, step, and registered custom field types;
- ordered validation, first-match routing with a required fallback, Email Studio bindings, success behavior, preview,
  and marked test submissions;
- inline, page, URL, and registered custom success states.

Publishing validates the current draft. A visitor submission is judged by the immutable version the visitor's page
was printed with, when that version was live in the last day (the life of the page's security token), and by the live
version otherwise; the spam protection is always the live version's. Historical submissions retain the exact schema
and consent statement used when they were accepted.

A flow that is closed, expired or not open yet keeps its place on the page: the block shows the flow's heading and a
notice with the owner's message and alternative, and a submission sent from a page loaded earlier answers `409`
with `code: "flow_closed"` and the notice, which the runtime draws above the form, keeping the answers. A draft or
missing flow shows nothing to visitors and a note to signed-in editors. Dates are checked when the page is served and
when a submission arrives, so they hold on a site whose WordPress cron does not run. A capped flow counts and stores
under one database lock, so the count never passes the cap.

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
validation → protection → storage → routing → email → inbox → timeline → actions
```

Routing resolves the matched target, then the flow's fallback, then the flow's owner, to a user, a team or a role,
and the inbox stores that assignee. A target that names nobody is written on the submission's timeline with the
reason. Someone who does not manage submissions sees in their inbox what is assigned to them, their team or their
role. An email's recipient `routed_owner` is that assignee's address, every member of a team, or every holder of a
role up to the flow's limit (20 by default); with nobody to reach, the flow's fallback recipient or the site
administrator gets it, and the delivery record says so.

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
- `RoutingTargetRegistry`
- `EmailVariableRegistry`
- `SuccessStateRegistry`

Extension identifiers are stable lowercase keys. Registered field types and validation rules appear in the builder
extension catalog; published flows fail closed if a referenced type, rule, routing fallback, success state or flow
action is not available.

### WordPress hooks for flow actions, email variables and routing targets

A plugin can register the last three without the container. Each filter is handed an array and returns it with its
own entries, `key => ['label' => …, 'callback' => …]`. They are read once, on `init`, so add them when your plugin
loads:

```php
// A flow action: the owner adds it on the flow's Success tab, under "After submission".
add_filter('corex_flow_actions', static function (array $actions): array {
    $actions['crm_push'] = [
        'label' => 'Send to the CRM',
        // Runs once per stored submission, after storage. `is_test` is true for a marked test,
        // and `test_notice` then says "This is a test.". What it throws is on the timeline.
        'callback' => static function (array $submission): void {
            my_crm_push($submission['values'], $submission['is_test']);
        },
    ];

    return $actions;
});

// An email variable: offered in Email Studio, written {{ crm.id }}, filled when a flow email is sent.
add_filter('corex_flow_email_variables', static function (array $variables): array {
    $variables['crm.id'] = [
        'label' => 'CRM record',
        'callback' => static fn (array $context): string => my_crm_id($context['submission']['email'] ?? ''),
    ];

    return $variables;
});

// A routing target: offered in the routing editor under its label.
add_filter('corex_flow_routing_targets', static function (array $targets): array {
    $targets['by_desk'] = [
        'label' => 'The desk team',
        // A user ID, ['type' => 'user'|'team'|'role', 'value' => …], or null: the fallback applies.
        'callback' => static fn (array $submission): array => ['type' => 'team', 'value' => 'desk'],
    ];

    return $targets;
});
```

`tests/Integration/Forms/FlowExtensionHooksTest.php` runs these three on a submission. A failing action stops neither
the submission nor its emails; a variable that fails is left empty and written to the log.

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

A form states its own wording by overriding `submitLabel()`, `successMessage()` and `errorMessage()`. Each returns
an empty string by default, which means CoreX's own: "Send", "Thank you — your message has been sent." and "Please
review the highlighted fields and try again." The wording is printed as text.

A form asks for the site's challenge by returning `['captcha' => 'on']` from `protection()`; it is off by default.
Its token is checked before any answer is judged, and a refusal answers `422` with `code: "challenge_failed"`.

A form draws itself by overriding `markup(FormParts $parts)`, building its HTML from the parts it is handed:
`attributes()`, `hidden()` and `status()` are required; `fieldAttributes()`, `label()`, `control()`, `error()`,
`field()` and `submit()` supply the rest. See the forms guide, "Draw a form your own way".

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
