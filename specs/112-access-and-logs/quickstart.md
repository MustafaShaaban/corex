# Quickstart: validating Access and logs

Run on a scratch WordPress site with the CoreX plugins mapped in, never on a site that delivers real mail. Tests that
send force capture (`Corex\Tests\Support\MailDelivery::answerAs(MailDelivery::CAPTURE)` in Pest, the
`X-Corex-E2E-Mail: capture` header in Playwright). Each scenario names the contract it proves; the full test cases
become `tasks.md` entries.

## Prerequisites

- WordPress 7.0 or later, CoreX on this branch, the newsletter, bookings, careers and profile (account) add-ons active.
- Users: an administrator, an editor, an author, a contributor and a subscriber.
- One admin page load after updating, which lets the migration runner add the grant columns (foundation schema 4).

## 1. Requests and grants ([contracts/rest.md](contracts/rest.md#access-requests))

1. As the subscriber, `POST /wp-json/corex/v1/access/requests` with `{"ability":"corex_manage_releases","reason":"x"}`:
   400, `details.code` `ability_not_requestable`; no row in the requests table.
2. As the subscriber, open `wp-admin/admin.php?page=corex-releases`: the access-denied page says an administrator grants
   access through the role matrix, with no form.
3. As the subscriber, request `corex_manage_data`; as the administrator, approve it: the dialog shows the ability, its
   risk and the reason; approval is refused without a note, refused with a wrong typed name, and accepted with the note,
   30 days and the ability's name typed.
4. Open the Access screen: the grant is listed with approver, date, note and expiry. Revoke it: the person no longer has
   the ability.
5. Approve another grant for 7 days, move the clock 8 days, run the daily event (`wp cron event run corex_access_grants_daily`):
   the grant ended, the activity stream has `access.grant.expired`, both people have a notification.

## 2. Refusal counting ([data-model.md](data-model.md))

As the subscriber, load a refused CoreX page 50 times within a minute: the audit tab shows one entry for that person
with count 50 and the first and last time; an earlier entry from another person is still listed. With query logging on
and no object cache, each refusal updates that one entry; with an object cache, one write is made for the hour.

## 3. Maintenance ([contracts/rest.md](contracts/rest.md#public-submissions-in-maintenance-changed-behaviour-no-new-route))

Set the mode to Maintenance. Signed out, POST to each marked route: 503 with `Retry-After`; no submission stored, no
mail captured. Sign in through the account block and request a password reset: both work. As the administrator, submit
the contact form: accepted. Switch to Coming soon and repeat the signed-out POSTs: all accepted. Read the Maintenance
panel: it names what is closed and what stays open without expanding anything.

## 4. Recovery

Add `define( 'COREX_LOGIN_UNGUARD', true );` to the scratch site's `wp-config.php` with login protection on: the Login
protection tab shows "Recovery is on", the "Default login endpoints" row says so, and `wp-login.php` answers. The
Recovery panel shows both routes with copyable code. Remove the line: the notice is gone.

## 5. Blog Pro ([contracts/rest.md](contracts/rest.md#blog-pro-changed-permissions))

For each of contributor, author, editor and administrator, call every Blog Pro route on the user's own draft, another
user's draft and a published post, and on a held comment: the answers match the permission table exactly.
`POST blog/share-click` answers `recorded: false`.

## 6. Data screen ([contracts/rest.md](contracts/rest.md#data-screen))

Give a user only "Manage data": the Data screen lists no role grants, access requests or sign-in attempts; each of their
routes answers 403 `data_forbidden`; an export set holding sign-in attempts finishes without it and says why; the access
audit records the refusals. The administrator sees all three.

## 7. Resend ([contracts/rest.md](contracts/rest.md#inbox-resend))

With delivery failing on the scratch site, submit a flow whose team notification goes to a team address: the
submission's email record holds the recipients. Resend the team notification: captured to the team address only, the
drawer says who it went to, the timeline records it. For a submission made before this branch, Resend works while the
flow's recipients are unchanged, and is not offered, with the reason, once they have changed.
