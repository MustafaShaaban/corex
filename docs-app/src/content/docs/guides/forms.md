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

## What a form reads

A form states its own wording by overriding three methods. Each returns an empty string by default,
which means CoreX's own wording: "Send", "Thank you — your message has been sent." and "Please
review the highlighted fields and try again."

```php
public function submitLabel(): string
{
    return __('Request a call', 'my-site');
}

public function successMessage(): string
{
    return __('Thank you. We will call you within one working day.', 'my-site');
}

public function errorMessage(): string
{
    return __('Check the highlighted answers and try again.', 'my-site');
}
```

The wording is printed as text: markup in it is shown, not interpreted. A submit label of nothing
but spaces is replaced by "Send", because a button needs a name. Translate the strings with your
own text domain.

## Protect a form

A form defined in code is challenged by the site's provider when it says so:

```php
public function protection(): array
{
    return ['captcha' => 'on'];
}
```

It is off by default. With it on, and reCAPTCHA configured under **CoreX → Settings → Captcha**:

- the form carries a hidden `captcha_token` field, and its page loads the provider's script. A
  page with no protected form loads nothing from the provider;
- a submission's token is checked on the server before any answer is judged. One that fails is
  answered `422` with `code: "challenge_failed"` and the message "We could not verify your
  submission. Please try again.", which the form shows in its status line. Nothing is stored and
  no listener runs;
- the token is not stored and is not handed to a listener.

Add `'action'` or `'threshold'` to the array to override what a scoring provider is asked to
check; without them the action is `corex_form_<slug>` and the threshold is the site's. On a site
with no provider configured, a protected form is accepted under the trap field, the security token
and the rate limit, as every form is.

With Turnstile or hCaptcha configured, the form also shows that provider's widget above its
button, and a submission made before the visitor has passed is held in the browser with a
message. A form that [draws itself](#draw-a-form-your-own-way) puts `$parts->challenge()` where
the widget should appear; it prints nothing unless the form is protected by one of those two.

## Draw a form your own way

A form whose design is not the stock form's writes its own markup. Override `markup()`; it is
handed the form's parts and returns HTML built from them. Returning `null`, the default, is the
stock form.

```php
use Corex\Forms\Block\FormParts;

public function markup(FormParts $parts): ?string
{
    return '<form ' . $parts->attributes(['class' => 'lead-card']) . '>'
        . '<div class="lead-card__row" ' . $parts->fieldAttributes('phone') . '>'
        . $parts->label('phone')
        . '<input type="tel" class="lead-card__input" ' . $parts->control('phone') . ' />'
        . $parts->error('phone')
        . '</div>'
        . $parts->field('note')
        . $parts->hidden()
        . '<footer>' . $parts->submit(['class' => 'lead-card__go']) . $parts->status() . '</footer>'
        . '</form>';
}
```

| Part | Gives | Required |
|---|---|---|
| `attributes(array $extra = [])` | The `<form>` element's attributes: the class the runtime binds, the endpoint, the security token, the schema, the messages and the form's wording. A `class` in `$extra` is added; nothing CoreX sets can be replaced | yes, on the `<form>` |
| `hidden()` | The trap field, and the token field when the form [is protected](#protect-a-form) | yes, inside the form |
| `status()` | Where the confirmation and the general error are announced | yes, inside the form |
| `fieldAttributes($name)` | What a field's wrapper carries so its error can be found | on each field's wrapper |
| `error($name)` | Where that field's error is written, inside its wrapper | for each field |
| `control($name, array $extra = [])` | A hand-written control's `id`, `name`, `aria-describedby`, and `required` when it applies. You add the element, its `type` and your classes | for each control you write |
| `challenge()` | Where Turnstile's or hCaptcha's widget is shown. Empty for any other form | when the form is protected by one of them |
| `label($name)` | The field's label, tied to its control | |
| `field($name)` | The whole field as the stock form draws it | |
| `submit(array $extra = [])` | The submit button with the form's label. A `class` is added | |

Every part is already escaped. Your own text is yours to escape.

A form built this way validates in the browser, submits, shows each error in the place you put it,
and is challenged, with no script of your own. Asking for a field the form does not have throws.

If the markup leaves out `attributes()`, `hidden()` or `status()` (or `challenge()` when it is
needed), the form is not shown to
visitors: it would fail without saying so. Somebody who can edit the page sees a notice naming
what is missing, and WordPress reports it as a developer notice.

## Field definition reference

| Key | Values |
|---|---|
| `type` | `text` `email` `number` `tel` `url` `password` `date` `file` `textarea` `select` `radio` `checkbox` `checkbox-group` `toggle` |
| `rules` | `required` `email` `url` `max_length:N` `min_length:N` `max:N` `min:N` `numeric` `phone` `phone:national` `pattern:EXPRESSION` `mime:TYPE,TYPE` `max_size:MEGABYTES` |
| `options` | `value => label` (for `select`/`radio`/`checkbox-group`). The field accepts these values and no other |
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

**A choice field takes only the answers it offers.** A `select`, `radio` or `checkbox-group` that
declares `options` refuses any other value with the error `choice`, so a form that routes an enquiry
by the option chosen can trust the value. There is no rule to write. An unanswered field is left to
`required`, and a field that declares no options is not compared with anything. A `checkbox-group`
is stored as the list of every box ticked.

```php
'reply_by' => ['type' => 'radio', 'rules' => ['required'], 'options' => ['email' => 'Email', 'phone' => 'Phone']],
'services' => ['type' => 'checkbox-group', 'options' => ['design' => 'Design', 'web' => 'Web']],
```

**`pattern:` is one expression: everything after the first colon.** It is a PCRE expression matched
anywhere in the answer, so anchor it with `^` and `$` to describe the whole answer. A comma or a
colon inside it is part of it. The server checks it; the browser does not.

```php
'postcode' => ['type' => 'text', 'rules' => ['pattern:^\d{4,5}$']],
```

**A `file` field takes PDF, JPEG, PNG, WebP, DOC and DOCX, up to 10 MB.** `mime:` narrows that list
for one field and `max_size:` lowers the limit for one field, in megabytes. Neither can add a type
or raise the limit: a type outside the list is refused when the file is stored, whatever the field
declares. A file field without `required` can be left empty.

```php
'cv' => ['type' => 'file', 'rules' => ['required', 'mime:application/pdf', 'max_size:2']],
```

**A site can add a rule of its own.** It is a class that implements
`Corex\Forms\Validation\Rule` and returns an error key, or `null` when the answer passes. Register
it on the `RuleRegistry` from the container under a name no rule already has (lower-case letters,
digits and underscores, starting with a letter), then use that name in `rules`. The server checks it; the
browser skips a rule it does not know.

An optional field that is left out of the request is skipped with every rule on it. A browser sends
every field, so it is always present; a request written by hand may leave one out. A rule that
looks at other fields ("give a phone number or an email address") has to be asked then too:
implement `Corex\Forms\Validation\RuleForAbsentValue` instead of `Rule`, and it is handed `null`
for the absent answer, with the whole request as the third argument.

```php
final class AWayToReply implements RuleForAbsentValue
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        return ($value ?? '') === '' && ($allValues['email'] ?? '') === '' ? 'reply' : null;
    }
}

$container->make(RuleRegistry::class)->register('reply', new AWayToReply());

'phone' => ['type' => 'tel', 'rules' => ['reply', 'phone']],
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

### A stored submission is announced

Once the form's listeners have run, a submission one of them stored is announced with
`Corex\Forms\Submission\SubmissionProcessedEvent`, the event a form built in Forms & Flows raises
too. The notification centre listens for it and raises **New submission** for whoever manages
submissions: one item per form, keyed `submission.new:<form slug>`. A form whose listeners do not
include `StoreSubmissionListener` (or another that calls `$event->storedAs($id)`) raises nothing.

For a form registered in code the event carries `flowId` `0`
(`SubmissionProcessedEvent::NO_FLOW`) and the form's slug as `flowSlug`.

If the form's notification email fails, a second notification says so, but only when the listener
that sent the email reports how it went. The built-in `SendEmailListener` does. A listener of your
own reports it like this:

```php
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\NotificationDelivery;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

final class MyBrandedNotification
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $request = new MailRequest(to: ['sales@example.com'], subject: 'New enquiry', body: '…');

        // A mailer that can say how the email went. The one the mail add-on binds can.
        if ($this->mailer instanceof AttemptingMailer) {
            $event->notifiedAs(NotificationDelivery::fromResult($this->mailer->attempt($request)));

            return;
        }

        $this->mailer->send($request);
    }
}
```

`Corex\Mail\Mailer` is bound by the mail add-on; a site without it has no mailer to inject.
Without `notifiedAs()` nothing is raised for the email. With the mail queue on, `attempt()`
answers `queued`, which counts as gone.

### What the notification contains

The built-in notification is HTML, because every CoreX mail transport declares
`Content-Type: text/html`. Values are escaped and multi-line answers keep their line breaks.

When the submission carries a valid email address in an `email`, `e-mail`, `email_address` or
`reply_to` field, it becomes the message's `Reply-To`, so whoever receives the notification can
answer the person who wrote in. If the form never asked for an address, no `Reply-To` is set.
