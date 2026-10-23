# Operations & Security

`CoreX → Operations & Security` is where you say how this site should behave, see whether it is fit
to be live, and control who can reach the login.

It has five sections. They are ordinary links, so each has its own address: you can bookmark one,
send someone a link straight to it, and use Back and Forward normally. They work with JavaScript
turned off.

| Section | What it answers |
|---|---|
| **Overview** | Is this site live? Can the public see it? Is anything blocking it? Anything locked out? |
| **Environment & Maintenance** | What mode is this site in, and how do I change it safely? |
| **Login Protection** | Where is the login, and who is currently shut out? |
| **Hardening** | What has WordPress got wrong, and what should I fix? |
| **Activity** | What has happened recently? |

## WordPress environment and CoreX Operations Mode are different things

This is the distinction the screen exists to keep clear, and it is easy to conflate.

**The WordPress environment type** is declared by your hosting or by `WP_ENVIRONMENT_TYPE` in
`wp-config.php`. It is `local`, `development`, `staging` or `production`. CoreX reads it and never
writes it.

**The CoreX Operations Mode** is what *you* have told CoreX about how this site should behave. It
drives CoreX's own safety warnings, readiness checks and maintenance behaviour.

They are usually the same, and they do not have to be. A site whose host reports `production` can
legitimately be in CoreX maintenance mode while you work on it. When they differ, the Overview says
so plainly — because reading one and assuming the other is how someone ends up surprised.

> **Changing the CoreX mode does not change your hosting environment**, your PHP configuration, your
> database, or your deployment. It changes what CoreX does and what it warns you about. Nothing on
> this screen can alter `WP_ENVIRONMENT_TYPE`.

If you have never declared a mode, CoreX follows the WordPress environment type and says it is doing
so. That is not a conflict, and it is not warned about. WordPress's `local` counts as Development.

## The mode decides where mail goes

Since spec 113 one answer says whether this is a development, staging or production site, and every
screen and every email follows it. In this order:

1. `APP_ENV` in the site's `.env` file, a value the network locks, or the `corex_app_env` option.
   Each of these **locks** the mode: the mode card says what locks it, and only that environment's
   mode, Coming soon and Maintenance can be chosen. Change it where it is set. Coming soon and
   Maintenance stay in force under any lock: they change only what visitors see.

> **Before updating a live site**, check that its `.env`, if it has one, does not say
> `APP_ENV=local`, `development` or `staging` (`.env.example` ships `APP_ENV=local`): a site set so
> keeps its mail.
2. The mode you choose here.
3. WordPress's environment type: `local` and `development` are Development, `staging` is Staging,
   anything else is Production.

| Mode | What happens to mail |
|---|---|
| **Development** | Every email is captured: kept in `CoreX → Email Studio → Delivery logs`, named by what sent it, and nobody receives it. |
| **Staging** | Captured, unless live delivery is on with a provider CoreX Mail sends through; then sent for real. |
| **Production, Coming soon, Maintenance** | Sent exactly as before. |

Captured mail includes WordPress's own emails (a password reset, a new user) and other plugins'
mail. Email Studio's Overview has a switch, **Capture only CoreX mail**, that lets those through
while CoreX's own mail stays captured. Without CoreX Mail active there is nowhere to keep a
captured email, so on a development or staging site it is not sent either: each one is recorded in
the activity log with its recipients, subject and what sent it.

A form's **Run marked test** holds every email it would send, on every site, production included:
the test's result lists each one with a link to it in Email Studio. Captured and held emails are
deleted 30 days after they were kept, and with their test submission when it is deleted for good.

Every Email Studio tab says, at the top, what happens to mail on this site, and names any kind of
email that does not follow it: on a production site with no provider, Email Studio's own sends are
refused while form notifications and the rest are still sent.

## Changing the mode

The form shows what the mode you have chosen actually means, and asks for the confirmation *that*
mode needs — and no other.

| Mode | What it asks for |
|---|---|
| **Development** | Nothing. It tells you the site is still publicly reachable, and its mail is captured (above). |
| **Staging** | Nothing. It reminds you about search indexing and external services. |
| **Production** | The word `PRODUCTION`, typed. It shows the readiness result and any blockers first. |
| **Maintenance** | A ticked acknowledgement that visitors are affected. |
| **Coming soon** | A ticked acknowledgement that visitors will see the coming-soon page instead of the site. |

Choosing the mode the site is already in does nothing, and the form says so rather than offering an
Apply button that would have no effect. Nothing is written and nothing is added to the history — the
mode history is a record of *changes*, and it stays one.

With JavaScript enabled, choosing a mode swaps the description immediately. Without it, submitting a
mode that needs a confirmation brings you back to the form with that mode selected and its
confirmation ready — one extra step, and the right question either way.

### What maintenance mode actually does

- Visitors who are not signed in get a maintenance page with a **503** status and a `Retry-After`
  header, so search engines treat it as temporary.
- Public form submissions are closed: CoreX forms, flows, newsletter sign-ups, booking requests,
  job applications and account registration answer **503** with a `site_in_maintenance` message,
  before anything is stored or mailed. Sign-in and password reset through the account block stay
  open. WordPress's own comment form is not closed; it follows the site's discussion settings.
- Signed-in administrators keep using the site normally, and can still submit a form to test it.
- Apart from those submissions, the REST API, AJAX, cron and `wp-admin` are **never** intercepted,
  so scheduled work and integrations keep running.
- A REST route of your own that takes public submissions closes with them when it declares
  `Corex\Http\PublicSubmission::MARKER => true` in its `register_rest_route()` arguments.
- To come back: change the mode here. If you cannot reach this screen, the recovery command in the
  Login Protection section works from the command line.

### What Coming soon mode does

- Visitors get the coming-soon page at the home URL with a **200**, and a temporary redirect to it
  from every other address.
- Anybody signed in who can edit posts keeps using the real site, and is told on every page that
  visitors are not seeing it.
- A preview link can show the real site to somebody without an account.
- The REST API, AJAX, cron, the login page and `wp-admin` are **never** intercepted.

It has a page of its own: [Coming soon mode](coming-soon.md).

## Production readiness

Readiness checks are grouped by what they mean: **blockers** stop a production change, **warnings**
do not, and **passed** checks are shown so you can see what was verified. Each blocker links to the
place it is fixed.

You can go live with blockers outstanding by typing the confirmation phrase — that is an override,
it is deliberate, and it is recorded in the history with your name against it.

## Login Protection

CoreX can move the login away from `wp-login.php` and shut out repeated failed attempts.

**The address shown is the address in force**, read from what is saved — not from what is currently
typed into the form. Those differ while you are editing, and showing the unsaved one is how somebody
bookmarks a URL that does not exist yet.

A login address is refused if it:

- is empty, the wrong shape, or one of the paths WordPress reserves (`wp-admin`, `wp-login`,
  `login`, `admin`, feeds, and others);
- **is already used by a published page**, or by anything else on the site that routes that
  address — a post type archive, a category base, another plugin's endpoint.

That second group matters more than it sounds. `about` is a perfectly valid slug and one of the most
common page slugs there is. Without the check, the login and that page would compete for the same
URL, and you would not find out until somebody tried to sign in.

If a value has to be adjusted before it can be used, CoreX tells you the address that is actually
in force and explains the change. It never shows you what you typed as though it were live.

### Recovery

If you are locked out or cannot reach the login, the recovery command in this section resets login
protection from the command line. It is documentation, not a button — it necessarily runs outside
the admin, because the case it exists for is the one where you cannot get to the admin.

## Lockouts

Lockouts show whether they are **active** or **expired**, what caused them, and when they end. Where
CoreX stores a hashed identity rather than an address, the hash is not shown dressed up as one.

There is no unlock button, because there is no unlock operation behind it. A control that looks like
it would do something and does not is worse than no control; use the recovery command.

## Dates

Every date on this screen uses the shared CoreX date contract — the site's timezone, your language,
and a form a person can read. See [Dates and times in the CoreX admin](dates-and-times.md).
