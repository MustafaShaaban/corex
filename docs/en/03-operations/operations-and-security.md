# Operations & Security

`CoreX → Operations & Security` is where you say how this site should behave, see whether it is fit
to be live, and control who can reach the login.

It has five sections. They are ordinary links, so each has its own address: you can bookmark one,
send someone a link straight to it, and use Back and Forward normally. They work with JavaScript
turned off.

| Section | What it answers |
|---|---|
| **Overview** | Is this site live? Is anything blocking it? Anything locked out? |
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
so. That is not a conflict, and it is not warned about.

## Changing the mode

The form shows what the mode you have chosen actually means, and asks for the confirmation *that*
mode needs — and no other.

| Mode | What it asks for |
|---|---|
| **Development** | Nothing. It tells you the site is still publicly reachable. |
| **Staging** | Nothing. It reminds you about search indexing and external services. |
| **Production** | The word `PRODUCTION`, typed. It shows the readiness result and any blockers first. |
| **Maintenance** | A ticked acknowledgement that visitors are affected. |

Choosing the mode the site is already in does nothing, and the form says so rather than offering an
Apply button that would have no effect. Nothing is written and nothing is added to the history — the
mode history is a record of *changes*, and it stays one.

With JavaScript enabled, choosing a mode swaps the description immediately. Without it, submitting a
mode that needs a confirmation brings you back to the form with that mode selected and its
confirmation ready — one extra step, and the right question either way.

### What maintenance mode actually does

- Visitors who are not signed in get a maintenance page with a **503** status and a `Retry-After`
  header, so search engines treat it as temporary.
- Signed-in administrators keep using the site normally.
- The REST API, AJAX, cron and `wp-admin` are **never** intercepted, so scheduled work and
  integrations keep running.
- To come back: change the mode here. If you cannot reach this screen, the recovery command in the
  Login Protection section works from the command line.

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
