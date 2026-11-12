# Research: Mail stays where it should

Read on `main` at `3a547659` (v0.47.0). Every file:line below is from that commit.

## R1. One resolver for the site's environment, in corex-core

**Decision**: a `Corex\Environment\SiteEnvironment` service in corex-core answers Development, Staging or Production,
with the setting that decided it and whether that setting locks the mode. Order (FR-001):

1. `APP_ENV` in `.env` (`DotenvSource`), then the network lock (`NetworkLockSource`), then the site option
   `corex_app_env` when it exists: each is a **lock**, named on the screen ("set in .env", "locked by the network",
   "set by the `corex_app_env` option"). `local`/`development` → Development, `staging` → Staging, anything else →
   Production.
2. The operations mode (`corex_operations_mode`): `development` → Development, `staging` → Staging; `production`,
   `coming-soon` and `maintenance` → Production.
3. WordPress's environment type: `local`/`development` → Development, `staging` → Staging, anything else → Production.

**Rationale**: corex-email requires only corex-core and may run without corex-config, which owns the operations mode;
the Mailer seam is in corex-core. `Repository::resolveSource()` is private (`Support/Config/Repository.php:40`), so the
resolver reads the three lock layers directly, as `Boot.php:276-284` already does. The defaults layer's
`'env' => 'production'` (`config/app.php:16`) answers every `app.env` read today, which is why mail never followed
`WP_ENVIRONMENT_TYPE`: the resolver does not read `app.env` through the repository, so the default no longer hides the
mode or WordPress's value.

**The `corex_app_env` option** is not named by the story. No screen or command writes it; the test suite
(`tests/Support/MailDelivery.php:37-41`) and a site's own code can. It is kept as a lock so that whatever set it keeps
deciding, and the screen names it. (Decided under the owner's 2026-10-21 mandate.)

**What a production lock means for the mode selector**: a lock to Development or Staging fixes the mode; a lock to
Production still lets the owner choose among Production, Coming soon and Maintenance (they are production for mail and
change only what visitors see), with Development and Staging disabled and the reason shown. (Decided under the owner's
2026-10-21 mandate: a lock exists to stop mail being captured or delivered by mistake, not to take Coming soon away.)

**Alternatives considered**: making `OperationsModeStore` the resolver (unreachable from corex-email without
corex-config); a public `Repository::sourceOf()` (a change to a core class every site loads, for one caller).

## R2. `OperationsModeStore::current()` reads the resolver

**Decision**: `current()` keeps returning one of the five modes. A lock to Development or Staging returns that mode; a
lock to Production returns the stored mode when it is Production, Coming soon or Maintenance, else Production; with no
lock, the stored mode, else WordPress's type mapped by R1 (so `local` is Development, fixing `OperationsModeStore.php:
56-58`). `set()` refuses a mode the lock does not allow. `ModeChangeService` (admin and `wp corex mode set`) gets that
refusal.

## R3. One gate where mail meets the transport

**Decision**: `Corex\Mail\Capture\MailGate` in corex-core decides, per message: **deliver**, **capture**, **hold for
test** or **refuse**.

| Situation | Decision |
|---|---|
| A flow test is running (R6) | hold for test (refuse when no capture store) |
| Development | capture (refuse when no capture store) |
| Staging, provider set and live delivery on | deliver |
| Staging otherwise | capture (refuse when no capture store) |
| Production, Coming soon, Maintenance | deliver (SAFE-D2: unchanged) |
| "Capture only CoreX mail" on, and the message is not CoreX's | deliver |

It is asked at two points:

1. `MailService::deliver()` (`addons/corex-email/src/MailService.php:37`), before the driver: every path on the
   `Mailer` seam, the queue worker (`runQueuedSend`) included, goes through it. A capture there returns status
   `captured`, which `RequestMailer` maps to `MailResult::STATE_CAPTURED` (successful, terminal: `MailResult.php:22`,
   `:73-93`), so no retry resends it and `AttemptingMailer` callers (Muva, `NotificationDispatcher`, `SupportMailer`,
   `QueuedMailer`) see success.
2. WordPress's `pre_wp_mail` filter, registered by corex-core: everything else that calls `wp_mail()` (the
   `NotificationDispatcher` floor `:111`, the guides floor `SupportMailer.php:130`, WordPress core, other plugins).
   Returning `true` stops the send and tells WordPress it was handled; returning `false` makes `wp_mail()` fail.

Email Studio keeps `DeliveryPolicy`, now fed by the resolver (R5), so its decision and the gate's agree in every
environment (FR-011). On development, and staging without live delivery, the Studio captures itself and never calls
`wp_mail()`; when it delivers it reaches `wp_mail()`, where the gate also says deliver.

**Rationale**: `pre_wp_mail` sees every `wp_mail()` call (`WpMailDriver.php:105` included); `MailService` is the
narrowest point CoreX owns and lets a capture be reported as captured instead of sent. Production is never touched:
the gate answers deliver before reading anything else.

**Alternatives considered**: swapping the `MailDriver` binding for `CaptureMailDriver` (the Studio's deliver branch
shares the singleton, `MailServiceProvider.php:227`, and the floors and WordPress core would still send); capturing in
each of the 14 paths (any path added later would send).

## R4. What is captured and how it is named

**Decision**: a `Corex\Mail\Capture\MailCaptureStore` contract in corex-core, bound by corex-email to
`CapturedEmailRepository`, which gains `path` and `state` (`captured` or `held_for_test`) in the payload. A message
caught by `pre_wp_mail` is rebuilt as an `EmailMessage` from `wp_mail()`'s arguments (to, subject, message, headers,
attachments); one record holds every recipient.

The path name comes from a `MailPath` scope (corex-core): CoreX code that sends sets it (`MailPath::during($label,
$send)`), and the gate reads the innermost one. Without a scope: `MailService` names the message from its template
(`newsletter-confirm` → "Newsletter: confirmation", `newsletter-notify` → "Newsletter: new post", `call-request-*` →
"Bookings: call request", `careers-*` → "Careers: application", the form templates → "Form notification", anything else
→ "Site code: <template>"); WordPress core mail is named from the filter that built it, which fires just before
`wp_mail()` (`retrieve_password_notification_email` → "WordPress: password reset", `wp_new_user_notification_email` →
"WordPress: new user", `wp_new_user_notification_email_admin` → "WordPress: new user (administrator)",
`wp_password_change_notification_email` → "WordPress: password changed", `password_change_email` /
`email_change_email` → "WordPress: account change"); anything else is "Other: wp_mail".

"CoreX mail", for the capture-only switch (FR-007), is mail inside a `MailPath` scope or sent through `MailService`.

## R5. Email Studio reads the resolver

**Decision**: the three private `environment()` copies (`EmailStudioController.php:415-420`, `EmailRouteDispatcher.php:
51-59`, `EmailStudioSubmissionGateway.php:258-269`) read `SiteEnvironment`. `DeliveryPolicy` is unchanged: it already
captures on development, applies the provider-and-live rule on staging and blocks production without both.

An `APP_ENV` value the policy did not know (`UNKNOWN_ENVIRONMENT`, block) is now Production: Email Studio follows the
production rule there, which is block unless a provider is set and live delivery is on. (Decided under the owner's
2026-10-21 mandate.)

## R6. A flow test holds its mail

**Decision**: `FlowTestService::run()` runs the pipeline inside `MailHold::during()` (corex-core). While it is active,
the gate answers hold for test, and `EmailStudioService::decide()` captures with state `held_for_test` before asking
the policy. The run's result gains `held_emails` (recipient, subject, capture id, link to the log); with CoreX Mail
inactive the fallback floor's `wp_mail()` is refused by the gate and the email stage says it was not sent.

**"Clear test entries"**: CoreX has no such action (no match in `plugins/` or `addons/`). A held message's attempt id is
recorded on its test submission like any flow email's, so deleting the test submission for good from the inbox
(spec 105) removes it through `EmailStudioSubmissionRecords::forget()`; otherwise the retention window does (R7).
(Decided under the owner's 2026-10-21 mandate: no new bulk action in a patch release.)

## R7. Captured and held mail is removed after 30 days, all of it

**Decision**: corex-email schedules a daily event, `corex_email_capture_retention`, that deletes `captured_email`
records whose post date is more than 30 days old (`retention_until` is `captured_at + 30 days`,
`CapturedEmailRepository.php:54`), in batches of 200, stopping after 20 seconds and continuing on the next run. The
query is by date, so it reaches every record, not the first 500.

**Alternatives considered**: joining corex-config's `RetentionSweep` (corex-email does not depend on corex-config).

## R8. The log reads newest first

**Decision**: `EmailStudioStore` gains `latest(string $type, int $limit): list<record>` (`orderby ID DESC`).
`EmailAttemptRepository::latest()` and `CapturedEmailRepository::latest()` use it. `all()` keeps its order for
templates, layouts, partials and routes. The only other implementation is the test suite's in-memory store. One query
plus WordPress's meta cache priming: no query per row.

The overview counts say their window: "of the last 100 emails".

## R9. What Email Studio says

**Decision**: the index payload gains `outcome`: `{state: delivered|captured|blocked, decided_by, locked, provider,
live, scope: all|corex, exceptions: [path names]}`, built by a `MailOutcome` service in corex-email from the resolver,
the provider and live settings, and the capture-only switch. The banner component shows it above every tab.

| Site | State | Exceptions named |
|---|---|---|
| Development | captured | none; with capture-only on, "WordPress and other plugins' mail is delivered for real" |
| Staging, provider and live on | delivered | none |
| Staging otherwise | captured | as development |
| Production, provider and live on | delivered | none |
| Production otherwise | blocked (Email Studio's own sends) | form notifications, newsletter, bookings, careers, guides, site code through CoreX's mailer, WordPress and other plugins' mail: all still delivered (SAFE-D2) |

## R10. The capture-only switch

**Decision**: config key `mail.capture_scope` (`all` default, or `corex`), stored as option `corex_mail_capture_scope`
and written by a new route `POST corex/v1/email-studio/capture-scope` (Email Studio's manage ability and nonce). The
gate in corex-core reads it through the config repository, so a network can lock it.

## R11. Refused mail is recorded

**Decision**: when the gate refuses (no capture store), it records an activity event: area `email`, kind
`mail.refused`, outcome `denied`, sensitivity `personal`, target the path name, context with the recipients and the
subject, retention 30 days.

## R12. Screens give one answer

**Decision**: Insights' "Environment type" row, Settings' `advanced.environment` diagnostic and Email Studio's heading
read `SiteEnvironment`; the Overview badge, the Command Center and the Operations mode card read
`OperationsModeStore::current()` (R2). Operations & Security keeps a "WordPress environment" row (WordPress's own
value, as a fact) and its conflict notice compares mapped values, so `local` against Development no longer warns.
