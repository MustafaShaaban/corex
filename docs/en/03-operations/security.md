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

On a single site, `/wp-signup.php` gets that same "not found" page, and `/wp-register.php` is answered as a file
that was never there. Both exist only to forward a visitor to the registration address, which is the custom one.
(WordPress has no `wp-register.php` file, so a web server that answers missing `.php` files itself, as nginx
commonly does, never asks WordPress about it.) `/wp-admin/customize.php` is answered like the rest of the admin area. On a network, `/wp-signup.php` is the public sign-up page and is left alone.

Three addresses run before WordPress loads any plugin, so CoreX cannot answer them: `/wp-activate.php`, which
redirects to `/wp-login.php` (itself hidden), and `/wp-admin/install.php` and `/wp-admin/upgrade.php`, which answer
that WordPress is already installed. They show that the site runs WordPress. None of them names the custom address.
Only a rule in the web server can change what they answer.

Hiding is obscurity, not access control. It cuts automated probing; the rate limiting above is what actually
defends credentials.

If an owner has lost the login address, is locked out, or the protected route is misconfigured, there are two ways
back in. The Recovery panel in **Operations & Security → Login Protection** gives both, with the code ready to copy.

**Without a command line** (most shared hosts): open `wp-config.php` with the host's file manager, add this line above
the line that says "That's all, stop editing", sign in at `/wp-login.php`, then remove the line:

```php
define( 'COREX_LOGIN_UNGUARD', true );
```

While the line is there, `wp-login.php` is open to everyone and the custom login address keeps working. It does not
end a lockout; a lockout ends when its lockout length has passed. The Login Protection section and the "Default login
endpoints" row on the overview say that recovery is on until the line is removed.

**With a command line:**

```bash
wp corex security reset-login
```

The command disables the protected login gates, releases active lockouts, and prints the restored `wp-login.php` URL.
It does not change users, roles, passwords, content, or unrelated CoreX settings.

## What every page says the site runs

Every front-end page's head carries one line naming CoreX, printed beside WordPress's own:

```html
<meta name="generator" content="CoreX" />
```

Technology scanners such as Wappalyzer recognise a platform by this line. It names CoreX and
carries no version, because a version tells anyone looking for a vulnerable site which release
to try. Removing WordPress's generator tag, as some security plugins do, leaves CoreX's in place.

The line is not printed on admin screens, the login page, feeds, REST responses or the Coming soon
page a visitor sees, which names no platform at all. The real site served to an administrator or a
preview link while Coming soon is on still carries it.

To leave it out of every page of one site, add this to the site's own plugin or theme:

```php
add_filter('corex_generator_tag', '__return_false');
```

A page cache keeps the line it was cached with: clear it after adding the filter.

## Behind a proxy, a load balancer or a CDN

A request that passes through a proxy reaches WordPress from the proxy's address. Until CoreX is told
which proxies to trust, that address is the client: login protection counts failed attempts against the
proxy, and a form's rate limit is one allowance shared by every visitor.

Name the proxies:

```bash
wp corex security trusted-proxies 127.0.0.1 ::1
```

Each argument is an address or a CIDR range, and the list given replaces the saved one. With no
argument the command prints the list. `--clear` trusts no proxy. The Security screen has no field for
this list; the command is how it is set.

For a request that arrives from a trusted proxy, CoreX reads `X-Forwarded-For` from the right and takes
the first address that is not itself a trusted proxy. That address is used by:

- login protection, for attempts and lockouts;
- the rate limit of every form and flow;
- the captcha check, as the address sent to the provider with a challenge.

| In front of WordPress | Trust |
|---|---|
| A tunnel or proxy on the same machine | `127.0.0.1` and `::1` |
| A load balancer on a private network | Its address, or the network's range |
| A CDN that connects to the origin directly | The ranges the CDN publishes |
| A CDN in front of a load balancer | Both |

Three things decide whether the answer is right:

- **List every proxy between the visitor and WordPress.** The first one that is not listed is taken for
  the visitor.
- **List only proxies that append to `X-Forwarded-For`.** A trusted proxy is believed about who the
  visitor is. One that passes a visitor's own header through unchanged lets the visitor choose an
  address.
- **A request from anywhere else is identified by its connection**, whatever headers it carries.

An entry that is not an address or a range is refused, and nothing is saved:

```text
Error: Not an address or a range: proxy.example.com. Nothing was changed.
```

## Who can open each CoreX screen

Every CoreX screen, its REST routes and its save actions ask for the CoreX ability that names it (spec 117). An
ability reaches a person through their role in **CoreX → Access & Abilities → Role matrix**, through a request an
administrator approved, or through site administration: anyone who can administer the site holds every CoreX
ability, and the matrix cannot deny a role that can. A Deny stored for such a role before this rule is kept, not
applied, and listed on the Access screen's Overview.

| Screen | Ability it needs |
|---|---|
| Overview, Add-ons, Insights | Access CoreX admin |
| Settings (and the captcha test) | Manage settings |
| Forms & Flows | Manage forms and flows |
| Submissions | Manage submissions (saving retention: Manage settings; applying it: Run dangerous actions) |
| Email Studio | Manage Email Studio |
| Data | Manage data or Manage data models |
| Operations & Security (and the login protection settings) | Manage operations and security |
| Access & Abilities | Manage access |
| Blog Pro | Manage Blog Pro; each post and comment then follows WordPress's own permission |
| Setup Wizard | Manage setup |
| Notifications | Manage notifications |
| Releases | Install releases |
| Guides | Anyone signed in; each guide follows its screen |

Switching the operating mode to Production or Maintenance also needs **Run dangerous actions**. In Settings → Mail,
the delivery provider and live delivery stay with site administration: anyone else sees them read-only. So does
Email Studio's **Capture only CoreX mail** switch.

Two abilities reach further than their screen's name says. Weigh them before allowing them for a role:

- **Manage setup** runs the Setup Wizard, whose kit activates CoreX plugins and add-ons and can set the site's front
  page.
- **Manage Email Studio** reads the captured emails, which can hold what people typed into the site's forms, and
  sends test mail from the site's address.

### What only a site administrator may change

A holder of **Manage access** who is not a site administrator can allow and deny abilities for roles they do not
hold, and decide other people's access requests. Four changes need site administration:

- allowing a Critical ability for a role, or setting one back to Inherit;
- approving a request for a Critical ability;
- any change to a role the person making it holds;
- deciding (approving or denying) one's own access request.

The Critical abilities are Access CoreX admin, Manage access, Manage operations and security, Install releases and
Run dangerous actions. Anyone else is refused with a sentence saying a site administrator does it, and the refusal is
listed on the Audit log tab.

**See CoreX screens without changing them** opens Overview, Settings, Operations & Security and Insights read-only for
someone who lacks their own ability: a "Read only" chip in the header, a sentence naming the ability that makes
changes, and every control marked unavailable, saying why when focused. No role holds it until an owner allows it.
Every save such a person sends anyway, to a route or a form, is refused and counted in the audit log.

A person who holds abilities but not "Access CoreX admin" sees a CoreX menu holding only the screens they may open.

## Access requests

When a signed-in user cannot open a CoreX admin area, the denied screen shows a real request-access form. The request
is stored through the Access workflow with:

- the requested CoreX ability;
- the requester;
- a required reason;
- a seven-day expiry.

Abilities that control the whole site cannot be requested: those the catalogue marks locked or critical (CoreX admin,
access, operations and security, releases, dangerous actions), any ability that implies one, and an area that holds
one. An administrator grants those through the role matrix. On the screens that need one of them (Overview, Add-ons,
Access, Operations & Security, Releases) the denied page says so and shows no form, and a request for one through REST
is answered `400` with the refusal code `ability_not_requestable` or `area_not_requestable`, storing nothing.

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
`POST /corex/v1/access/requests/<id>/decision` answer JSON, as [What the REST routes answer](#what-the-rest-routes-answer) sets out, for scripts, integrations and
the admin screen's own controls. The admin endpoint is a second door onto the same service, not a replacement for it,
and nothing in CoreX inspects `Accept` to decide between HTML and JSON.
A second `POST /corex/v1/access/requests` from the same person for the same ability or area, while their first is still
open, stores nothing. It is answered `200` with a result whose `state` is `blocked` and whose error code is
`request_already_pending`; no audit entry is written and nobody is notified again. A script has to read
`data.result.state`, not the status, to know whether a request was created. A request for a different ability or area,
or one made after the first was decided or expired, is a new request.

### What the administrator sees

Pending requests appear in **CoreX Access & Abilities → Role matrix**, each naming who asked, which ability or area
they asked for, when, and why, with **Approve** and **Deny**. Denial records the decision without granting access.

**Approve** first shows what would be granted: each ability with its risk, the area it came from and the requester's
reason. The administrator writes a note, chooses when access ends (7, 30 or 90 days; 30 is selected), and, for an
ability marked dangerous, types its key (for example `corex_manage_data`); the server checks all of it again, with the
same safety policy as a role change. Both decisions write an audit entry.

Beside the requests, **Access granted by approval** lists every grant that stands: who has it, who approved it and when,
the note, and when it ends. **Revoke** takes the ability back, unless another standing grant still gives it to the same
person. Once a day CoreX ends the grants whose time has passed and tells the person and the approver in the
Notification Center. A grant approved before expiries existed says "No expiry (granted before expiries existed)", stays
in effect and can be revoked. A grant whose ability was taken away elsewhere (a role editor, the command line) is
listed as no longer in effect.

Because that panel sits inside a tab, the screen's Overview says how many people are waiting and links straight to it.
When nobody is waiting, it says nothing at all.

The **Audit log** tab is one history of access, newest first, by day, 25 entries a page, filtered by kind and by
person: role changes (with what changed) and role changes the safety rules refused (with why), requests made and
their approval, refusal, cancellation or expiry, grants revoked or ended, and refused visits and actions. Role
changes, requests and grants are kept for 180 days; refusals for 30. One person's refusals of one screen are one line
for an hour from the first of them, with how many times and from when to when, so somebody reloading a refused page
cannot push anybody else's line out; each person keeps at most 20 lines. On a host with a persistent object cache the
repeats within the hour are counted there and stored once; without one, each repeat updates that person's line.

`GET /corex/v1/access/requests` answers the same list as JSON, to the same people: it needs a REST nonce and
"Manage access", as the screen and the decision route do. Anybody else who is signed in is answered `403`, the person
who filed a request included, and a visitor who is not signed in `401`. A person sees their own open request on the
screen that refused them, not through this route.

### A CoreX address that does not exist

`?page=corex-<something-that-is-not-a-screen>` answers **404**, with no capability explanation and no request form.
Nothing was refused, so nothing is presented as a refusal — and there is no ability to request for a screen that does
not exist. A registered CoreX screen the viewer may not open still answers 403 with the designed denied surface.

Native WordPress capabilities and third-party role plugins remain compatibility inputs. CoreX-owned abilities are the
only states edited by the CoreX Access workflow.

### What the REST routes answer

This is for a script or an integration that calls `/corex/v1/access/...`. The admin screens need none of it.

| The request | Status | Body |
|---|---|---|
| Done | `200` | From a route that changes something, `data.result.state` is `completed` |
| Read, and refused for how things stand | `200` | `data.result.state` is `blocked`, with a code in `data.result.errors` |
| Not readable | `400` | `code` is `rest_invalid_param`; `data.params` names the parameter |
| No session, an expired nonce, or not allowed | `401` or `403` | WordPress's own error body |

A `200` from a route that changes something does not say that anything was created or changed: read
`data.result.state`.

**Refused for how things stand** is a second request for what the person is already waiting on, a decision on a request
that is no longer pending, a role change the safety policy stops, or a confirmation that confirms a different change or
has run out. A preview of a role change the policy would
stop is not a refusal: it is answered `200` with `data.allowed` false and the reasons in `data.blockers`.

**Not readable** is a request that would be refused however often it was sent. Nothing is stored, no audit entry is
written and nobody is notified. The body is the one WordPress answers for a parameter of its own that it refuses:

```json
{
  "code": "rest_invalid_param",
  "message": "That is not an ability CoreX has.",
  "data": {
    "status": 400,
    "params": { "ability": "That is not an ability CoreX has." },
    "details": { "ability": { "code": "ability_unknown", "message": "That is not an ability CoreX has.", "data": null } }
  }
}
```

The message is translated and may be reworded. A script branches on the code under `data.details`:

| Parameter | Code | Sent to | Meaning |
|---|---|---|---|
| `reason` | `reason_required` | `POST access/requests` | No reason, or one that is not text |
| `reason` | `reason_too_long` | `POST access/requests` | Longer than 2,000 characters |
| `ability` | `target_required` | `POST access/requests` | Neither `ability` nor `area` was sent |
| `area` | `target_ambiguous` | `POST access/requests` | Both `ability` and `area` were sent |
| `ability` | `ability_unknown` | `POST access/requests` | Not an ability's key |
| `area` | `area_unknown` | `POST access/requests` | Not an area's key (the Forms area is `forms-flows`) |
| `changes` | `ability_unknown` | `POST access/roles/<role>/preview` and `/apply` | A key of `changes` is not an ability's key |
| `changes` | `effect_unsupported` | the same two | A value is not `allow`, `deny` or `inherit` |
| `confirmation` | `confirmation_malformed` | `POST access/roles/<role>/apply` | Missing, or a field of it is not what it has to be |
| `role` | `role_invalid` | every `access/roles/<role>` route | A `role` in the body or the query that is not a role key |

**Up to v0.45.2 these were answered `500`**, "There has been a critical error on this website", and each
wrote a fatal error to the PHP log. A script that treats any status outside `2xx` as "not done" needs no change. One
that retries on `5xx` stops retrying a request that could never succeed. One that alerts on `5xx` from these routes
stops being alerted by a caller's mistake.
