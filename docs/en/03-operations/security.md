# Operations and Security

CoreX Operations & Security is the owner-facing control plane for launch readiness, operating mode, login protection,
lockouts, recovery, and security activity.

## Production launch

Production mode is guarded by the same readiness snapshot shown on screen. Before switching to Production:

1. Review every launch checklist item.
2. Resolve blocking checks when possible.
3. If you intentionally override blockers, type `PRODUCTION` exactly in the confirmation field.

CoreX records the mode transition in the operations-mode history. Maintenance mode remains separately confirmed because
it affects real visitors.

## Login protection and recovery

Login protection can:

- rate-limit failed login attempts by hashed identity and network evidence;
- block default login endpoints behind a custom login slug;
- retain only bounded login-attempt evidence;
- release lockouts through the recovery command.

### What a hidden endpoint looks like from outside

With hiding enabled, a signed-out request to `/wp-login.php` or `/wp-admin` gets the site's ordinary "not found"
page — the same 404 your theme serves for any address that was never there. Nothing in the response names the
custom address, and the conventional shortcuts (`/login`, `/dashboard`, `/admin`) stop redirecting to it.

`/wp-login.php` is byte-identical to a genuine miss. `/wp-admin` carries the same page with fewer of WordPress's
per-block stylesheets, because whether the front-end asset pipeline registers at all is decided while the request
is still identified as an admin one. Someone comparing response sizes of two "not found" pages can therefore
still infer that the admin address is handled specially — but not where the login moved to.

Hiding is obscurity, not access control. It cuts automated probing; the rate limiting above is what actually
defends credentials.

If an owner is locked out or the protected route is misconfigured, run:

```bash
wp corex security reset-login
```

The command disables the protected login gates, releases active lockouts, and prints the restored `wp-login.php` URL.
It does not change users, roles, passwords, content, or unrelated CoreX settings.

For emergency request-level bypasses, define `COREX_LOGIN_UNGUARD` only long enough to regain access, then remove it.

## Access requests

When a signed-in user cannot open a CoreX admin area, the denied screen shows a real request-access form. The request
is stored through the Access workflow with:

- the requested CoreX ability;
- the requester;
- a required reason;
- a seven-day expiry.

### What the requester sees

The form submits to a CoreX admin endpoint, which validates it, calls the Access service and redirects back to the
screen they were refused from. They never leave wp-admin, and the browser is never sent to a REST endpoint.

That screen then shows a confirmation naming when the request was sent, in place of the form. It is read from the
stored request rather than from the redirect, so it survives a refresh, a bookmark and a return the next day — and
refreshing creates no second request. Submitting again while one is open creates nothing: the duplicate is refused on
the server, not by disabling a button.

An empty or invalid reason returns them to the form with the field error shown and their text preserved. A failure on
CoreX's side shows a short reference and says plainly that nothing was recorded, so retrying is safe. No error state
shows an operation identifier, a stack trace, a path or a raw API payload.

**The Access Request REST routes remain JSON APIs.** `POST /corex/v1/access/requests` and
`POST /corex/v1/access/requests/<id>/decision` answer JSON with unchanged status codes, for scripts, integrations and
the admin screen's own controls. The admin endpoint is a second door onto the same service, not a replacement for it,
and nothing in CoreX inspects `Accept` to decide between HTML and JSON.

### What the administrator sees

Pending requests appear in **CoreX Access & Abilities → Role matrix**, each naming who asked, which ability they asked
for, when, and why, with **Approve** and **Deny**. Approval grants the requested CoreX-owned ability immediately;
denial records the decision without granting access. Both write an audit entry.

Because that panel sits inside a tab, the screen's Overview says how many people are waiting and links straight to it.
When nobody is waiting, it says nothing at all.

### A CoreX address that does not exist

`?page=corex-<something-that-is-not-a-screen>` answers **404**, with no capability explanation and no request form.
Nothing was refused, so nothing is presented as a refusal — and there is no ability to request for a screen that does
not exist. A registered CoreX screen the viewer may not open still answers 403 with the designed denied surface.

Native WordPress capabilities and third-party role plugins remain compatibility inputs. CoreX-owned abilities are the
only states edited by the CoreX Access workflow.
