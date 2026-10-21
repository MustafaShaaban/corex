# Corex Bookings

A "request a call with one of our leaders" flow. Optional add-on; builds on corex-core (custom
tables), Corex Mail (008), and Corex Captcha (012).

## Flow

1. Configure your leaders (`bookings.leaders` — a list of `{id, name, email}`).
2. A visitor picks a leader and submits name + email (+ phone, preferred time, message) via
   `POST /wp-json/corex/v1/bookings/request` (honeypot + captcha gated).
3. A valid request (known leader + valid contact) is stored in the `corex_call_requests` custom table,
   and the **leader is notified** + the **visitor confirmed** (Corex Mail). A rejected request has
   **zero side effects**.
   The table is created on the site's first admin page or cron run after the
   add-on is activated, or by `wp corex migrate`. An add-on activated from the command line has no
   table until one of those.

## A form that passes the captcha check

CoreX ships no visitor form for call requests: a site posts its own form to the route. With a captcha provider
on (Turnstile, hCaptcha or reCAPTCHA v3, with its keys), the route refuses a request without the
provider's check, answering `422` with `error: "captcha"` and a `message` to show the visitor. Nothing is
stored and no email is sent.

A form carries the check through `Corex\Security\FormChallenge`, which corex-captcha provides:

```php
use Corex\Security\FormChallenge;

$challenge = \Corex\Boot::app()->container()->make(FormChallenge::class);

echo '<form ' . esc_attr($challenge->formAttribute()) . ' novalidate>';
// … the form's fields …
echo $challenge->fields(\Corex\Bookings\BookingsServiceProvider::REQUEST_ACTION); // already escaped: the token field, and the place for a widget
echo '<button type="submit">' . esc_html__('Send', 'corex') . '</button>';
echo '<p role="status" aria-live="polite" data-corex-captcha-message></p>';
echo '</form>';
```

- `fields()` prints nothing, and `formAttribute()` returns `''`, when no provider is on. Printing the
  check also loads the provider's script on that page, and on no other.
- Send the form's `captcha_token` field with the others.
- After the answer, dispatch `corex:form:success` or `corex:form:error` on the form, with
  `{ detail: { envelope } }`. The provider's check then resets for the next attempt, since a token is
  spent by one request.
- The provider's script writes its own messages (check not passed, provider unreachable) into the
  element marked `data-corex-captcha-message`.

## Tests

```bash
composer test              # headless: leader directory + the request service (validate -> store -> notify)
composer test:integration  # real ./wp: the call-request custom-table data path
```

> Real availability calendars, time-zone handling, and reminders are deferred. The full request-over-HTTP
> flow + email rendering are best confirmed in a browser.
