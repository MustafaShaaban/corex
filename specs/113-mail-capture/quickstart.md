# Quickstart: Mail stays where it should

Run on a scratch site, never on corex.local's live mailbox. Every check uses a `pre_wp_mail` spy that records calls and
returns `true`, so nothing can leave even if a check fails.

## Prerequisites

- WordPress 7.0+, CoreX on this branch, CoreX Mail (corex-email) active; newsletter, bookings, careers, guides and
  profile add-ons active for story 2.
- No `.env`, no `corex_app_env` option.

## 1. One setting ([research R1, R2](research.md))

1. Mode Production, WordPress environment `production`: Email Studio reads Production; a test send follows the
   production rule.
2. Set the mode to Development in Operations & Security: Email Studio, the Overview, Operations and Insights read
   Development; a test send is Captured; the mode history has the change.
3. Add `APP_ENV=production` to `.env`: Operations shows the mode locked, "set in .env"; Development and Staging cannot be
   chosen; mail follows production.
4. Remove `.env` and the stored mode; set `WP_ENVIRONMENT_TYPE` to `local`: Development; to `staging`: Staging.

Automated: `tests/Unit/Environment/SiteEnvironmentTest.php`, `tests/Integration/Environment/SiteEnvironmentTest.php`.

## 2. Capture ([R3, R4](research.md))

On Development: submit the contact form, subscribe, publish a post, request a call, apply for a job, send a guides
support request, register an account, ask for a password reset, and trigger a plugin's `wp_mail()`. The spy records
nothing; the Email Studio log lists each as Captured with its path. Switch "Capture only CoreX mail" on: the WordPress
and plugin mail reach the spy, CoreX mail does not. Deactivate CoreX Mail and submit a flow: the spy records nothing and
the activity log has a `mail.refused` event.

On Production with no provider and live delivery off: the contact form and a `Mailer` send reach the spy, as before.

Automated: `tests/Unit/Mail/MailGateTest.php`, `tests/Integration/Mail/MailCaptureTest.php`.

## 3. Flow test ([R6](research.md))

On Production with a provider and live delivery on, run a marked test of a flow with two email bindings: the spy records
nothing; the result lists two held emails linked to the log. Submit the flow as a visitor: both reach the spy.

Automated: `tests/Integration/Forms/FlowTestHoldsMailTest.php`, `tests/e2e/flow-test-holds-mail.spec.js`.

## 4. What Email Studio says ([R9, R10](research.md))

Open every Email Studio tab on Development, on Staging with and without live delivery, on Production with and without a
provider: the banner states the outcome, the provider, live delivery, what decided and, where paths differ, names them.

Automated: `tests/Unit/Email/MailOutcomeTest.php`, `tests/e2e/email-studio-outcome.spec.js`.

## 5. A busy site ([R7, R8](research.md))

Seed 600 attempts and 600 captures (550 older than 30 days): the log's first row is the newest; run
`corex_email_capture_retention`: 550 captures are gone, 50 remain.

Automated: `tests/Integration/Email/StudioNewestFirstTest.php`, `tests/Integration/Email/CaptureRetentionTest.php`.
