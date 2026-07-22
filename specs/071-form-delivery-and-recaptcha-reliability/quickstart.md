# Quickstart: Exercising Form Delivery & reCAPTCHA v3 Reliability

How to drive each requirement end to end on `corex.local`. This doubles as the manual half of the
Phase A gate.

## Prerequisites

- `wp --path=wp plugin list` shows `corex-core`, `corex-forms`, `corex-captcha`, `corex-email` active.
- A published flow with a page placement (the Company Site Kit contact form works).
- A Google reCAPTCHA **v3** site key + secret registered for `corex.local` (or your dev host, if
  Google permits it for that hostname).

## 1. Protected form accepts a real person (US1)

1. **CoreX → Settings → Captcha:** driver `reCAPTCHA`, paste the v3 site key and secret, leave the
   threshold at the default `0.3`, save. The summary should read *configured*.
2. Open the page with the form, logged out. **View source / Network:** confirm
   `recaptcha/api.js?render=…` loads, and the form has a `captcha_token` hidden input carrying a
   `data-corex-captcha-action`. The **secret** must appear nowhere (search the page + network payloads).
3. Submit the form normally. It should be accepted.
4. Open a page with **no** form. Confirm `recaptcha/api.js` does **not** load (SC-003).
5. Submit twice; capture both tokens (Network). They must differ (SC-004).

## 2. A saved submission survives a mail failure (US2)

1. Break delivery: point the notification route at an invalid template, or set an impossible
   recipient, so the attempt fails.
2. Submit the form. The visitor should see a *received* message (FR-020).
3. **CoreX → Submissions:** the submission is present, tagged **Saved — notification failed**
   (SC-005). Open it: a safe reason and an attempt time, no server/credential detail (SC-008).

## 3. wp_mail fallback when CoreX Mail is inactive (US2)

1. `wp --path=wp plugin deactivate corex-email`.
2. Submit the form.
3. The submission is saved **and** carries a recorded delivery attempt with provider `wp-mail` and
   status `accepted` or `failed` — not `sent`, and not *no record* (SC-006). This is the case that
   produced nothing before this feature.
4. `wp --path=wp plugin activate corex-email` to restore.

## 4. Administrator can read every outcome (US3)

Cause each outcome in turn (working send, broken send, dev capture via `corex-email` in development
mode, an unrouted form) and confirm the inbox list distinguishes each without opening the record
(SC-007), and that each detail view redacts sensitive data (SC-008).

## 5. Per-form protection (US4)

1. In the flow builder, set one form's captcha to `on` with threshold `0.7`; leave another at
   `inherit`.
2. Submit both. Only the strict form applies `0.7` (visible in its stored evidence); the other uses
   the global `0.3`.
3. Load and submit a flow published before this branch. It must still work unchanged (SC-010) — and
   its stored checksum must be unchanged (verify the version's `checksum` did not move).

## 6. FluentSMTP boundary guidance (US5)

1. Read **CoreX → Email Studio** help and the Forms notification-routing help. Both must state who
   composes vs who transports, and that a transport can override the sender.
2. With no `wp_mail_from` filter registered, the advisory shows general guidance, not a "detected"
   claim (FR-029).

## Automated verification

```
composer test:integration -- --filter=Captcha
composer test:integration -- --filter=Submission
composer test -- --filter=Captcha
npm run test:js -- captcha
npx playwright test tests/e2e/forms-flow.spec.js tests/e2e/submissions-inbox.spec.js
```

Render-verify the inbox and captcha settings in dark, light, RTL, and 375px, keyboard-only.
