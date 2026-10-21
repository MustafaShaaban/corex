# Corex Careers

Job postings + a secure application flow with a pipeline. Optional add-on; builds on corex-core
(custom tables, blocks), Corex Mail (008), and the upload validator + captcha (012).

## Jobs

- A `corex_job` post type with **department / location / type** taxonomies and an archive.
- The **`corex/jobs`** block lists open positions as accessible cards (linked title + meta).

## Applications

- Apply via `POST /wp-json/corex/v1/careers/apply` (honeypot + captcha gated): name, email, cover
  letter, and a **CV**. The application is accepted only when the required fields are present and the
  CV passes the upload validator (allowed type/extension, size cap). A rejected application has **zero
  side effects**.
- A valid application is stored in the `corex_applications` custom table and **HR + the applicant are
  emailed** (Corex Mail).
  The table is created on the site's first admin page or cron run after the
  add-on is activated, or by `wp corex migrate`. An add-on activated from the command line has no
  table until one of those.
- Applications move through a **pipeline** — `new → reviewing → interviewed → offer → hired / rejected`
  — and only valid transitions are allowed (`StatusFlow`).

## A form that passes the captcha check

CoreX ships no visitor form for job applications: a site posts its own form to the route. With a captcha provider
on (Turnstile, hCaptcha or reCAPTCHA v3, with its keys), the route refuses a request without the
provider's check, answering `422` with `error: "captcha"` and a `message` to show the visitor. Nothing is
stored and no email is sent.

A form carries the check through `Corex\Security\FormChallenge`, which corex-captcha provides:

```php
use Corex\Security\FormChallenge;

$challenge = \Corex\Boot::app()->container()->make(FormChallenge::class);

echo '<form ' . esc_attr($challenge->formAttribute()) . ' novalidate>';
// … the form's fields …
echo $challenge->fields(\Corex\Careers\CareersServiceProvider::APPLY_ACTION); // already escaped: the token field, and the place for a widget
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
composer test              # headless: status flow, application service (validate->store->notify), jobs block
composer test:integration  # real ./wp: the application custom-table data path
```

> The validated CV file is stored via the boundary (`wp_handle_upload` to a protected location — never a
> caller path). Editor/visual + full apply-over-HTTP flows are best confirmed in a browser.
