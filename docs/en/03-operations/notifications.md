# Notifications and the Command Center

The CoreX Notification Center collects the things that need someone's attention — a new form submission, a failed
email, a background job that failed, an access request, a login lockout, a readiness blocker — and surfaces them
consistently across the admin. Every read is scoped to the current user: you only ever see what you are allowed to see.

## Where notifications appear

- **The header bell** — on every CoreX screen, the shell header shows a bell with your real unread count (capped
  visually at `99+`, with the true count in its accessible label). It is keyboard operable and opens a drawer.
- **The drawer** — opens from the bell: a modal dialog listing your notifications as the same items the full
  screen shows, minus the management controls — a glance keeps each record's own primary action and
  *Mark all as read*. It traps focus while open, closes on `Escape`, and returns focus to the bell.
- **The Notifications screen** (`CoreX → Notifications`) — the full center: the three views below, severity and
  category filters, an *assigned to me* toggle, pagination, and a Preferences tab.
- **The admin-toolbar entry** — on non-CoreX screens (and the front end), the WordPress toolbar carries a
  *Notifications* entry with your unread count. It never appears at the same time as the header bell — on a CoreX
  screen the bell owns that surface.
- **The Overview card** — the CoreX Overview shows a compact *Attention required* card next to Recent Activity.
- **The Dashboard Command Center** — a widget on the WordPress dashboard (see below).

Access to the full Notifications screen requires the **Manage notifications** ability
(`corex_manage_notifications`). Administrators hold it automatically; grant it to others through
`CoreX → Access & Abilities`.

### Views

Three views, one per state a condition can be in. Each is a bounded server-side filter — the screen never
fetches everything and narrows it in the browser.

| View | Shows |
| --- | --- |
| **Action needed** | Conditions that are live and ask something of you: a demanding severity (critical, error, warning, action) or a record carrying its own primary action. |
| **Updates** | Live, but asks nothing of you — the informational stream — plus anything you have snoozed. |
| **History** | Over: resolved, dismissed, or expired. The archive, not the audit log; permanent evidence lives in Activity. |
| **Preferences** | Your per-category toggles (see below). |

**Which view an item lands in never depends on whether you have read it.** That is the distinction the whole
screen turns on: reading is something you did, not something that happened to the condition. A readiness
blocker you have opened and not fixed stays in **Action needed**.

Category, severity, and *assigned to me* are **filters**, applied within whichever view you are in — not
views of their own. Assignment means notifications that name you personally (the assignee of a submission, or
a message addressed to you); things you can see because you hold an ability are not "yours".

Three more distinctions are easy to miss and worth stating plainly:

- **Read is yours; resolved is everyone's.** Marking something read changes only your copy. Resolving a
  condition ends it for every recipient, and needs **Manage notifications** — so the *Mark resolved* control
  is hidden entirely from anyone without it, rather than shown and refused.
- **Dismissed is not resolved.** Hiding a shared notification hides it for you alone — it never resolves the
  underlying condition for anyone else.
- **Snoozed is "not now", not "done".** A snoozed item moves to **Updates**, not History, and returns to
  **Action needed** on its own when the snooze elapses.

### What one notification tells you

Each item states what happened and, beneath it, everything the record already knows: its severity, the module
it came from, the environment, when it last occurred, how many times, whether the condition is still live, and
whether you have read it. Its controls are *Mark read* / *Mark unread*, *Snooze for a day*, *Dismiss*,
*Mark resolved*, and the record's own primary action — with anything you may not do left out rather than
disabled. The header drawer renders the same item with the management controls dropped, so the drawer and the
screen cannot tell you different things about the same record.

## What produces notifications

Producers turn framework events into notifications. Each is **dependency-aware** — a producer whose module is not
present simply produces nothing. The shipped producers are:

| Source | Notification | Goes to |
| --- | --- | --- |
| New form submission | `submission.new` (merged per form) | Submissions managers |
| Submission notification email failed | `submission.email_failed` | Submissions managers |
| Submission assigned to a person | `submission.assigned` | The assignee |
| Access request filed | `access.request` | Access managers |
| Background job failed | `job.failed` | Operations managers |
| Export finished | `export.ready` | The person who ran it |
| Sign-in lockout triggered | `security.lockout` (merged per identity) | Operations managers |
| Email Studio delivery failed | `email.delivery_failed` (merged per provider) | Email managers |
| Readiness blocker appears / clears | `readiness.blocker` | Operations managers |

Repeated occurrences of the same condition merge into one growing notification (an occurrence count) rather than a
flood. Condition-based notifications — a readiness blocker, a lockout — clear themselves when the condition ends.

## REST API

All routes live under `corex/v1/notifications` and return the standard `{ ok, message, data }` envelope. Reads
require you to be signed in; mutations additionally require a REST nonce; resolving a shared condition requires
**Manage notifications**.

- `GET /notifications` — your bounded, filtered list. Filters: `page`, `per_page`, `view`, `category`,
  `severity`, `source_module`, `status`, `assigned_to_me`, `unread_only`. Unknown filter values are dropped
  rather than guessed at, and `per_page` is clamped.
  - `view` is one of `action_needed`, `updates`, `history`, or omitted for any. It is derived from the
    record's status and severity, **not** from whether you have read it, and each returned item carries its
    own membership as `user_state.view`.
  - `status` is your **derived per-user status** — one of `unread`, `read`, `snoozed`, `dismissed`,
    `resolved`, `expired` — resolved from the shared record plus your own state, in that order of precedence
    (a resolved condition reads as `resolved` however you left your copy). Each returned item also carries it
    as `user_state.status`, so a client never has to re-derive it.
  - `assigned_to_me=1` narrows to notifications that name you personally, not ones you can see through an
    ability.
  - `unread_only=1` is **not** the same as `status=unread`: it means the *condition* is unresolved, which
    includes items you have already read. Prefer `status=unread` when you mean your own unread items.
- `GET /notifications/count` — your unread count.
- `GET /notifications/{id}` — one notification, or `404` if it is not yours to see.
- `POST /notifications/{id}/read` · `/unread` · `/dismiss` · `/snooze` — per-user state.
- `POST /notifications/read-all` — mark everything you can see as read.
- `POST /notifications/{id}/resolve` — resolve the underlying condition (Manage notifications).
- `GET` / `POST /notifications/preferences` — read and save your category preferences.

## Preferences

On the Notifications screen, the **Preferences** tab lists every category with an in-app toggle. Mute the categories
you do not want to see in-app. The mandatory categories — **security**, **system**, and **operations** — can never be
muted; they render disabled and are always shown, because you must not be able to hide something you need to see. The
server enforces this too, so a saved preference can never suppress a mandatory category.

Preferences are stored per user in user meta (`corex_notification_preferences`) — not a custom table.

## Retention and privacy

Notifications are pruned automatically by a **daily** background job (the framework's first recurring job, on
WP-Cron). Only **resolved** conditions and **expired** notifications older than the retention window (90 days) are
removed; an unresolved condition persists however old it is, because it still needs attention. Notification records
are secret-free by construction — a token, credential, or key in metadata is rejected when the notification is
created — and diagnostic detail that carries no such guarantee (a raw job error, for example) is kept off the
notification and left on the owning screen.

## The Dashboard Command Center

The **CoreX Command Center** widget on the WordPress dashboard (visible to administrators) is a server-rendered
summary of three things, each a navigation-only link into CoreX — never an action:

- **Site state** — the current operating mode.
- **Attention** — your unread notification count.
- **Readiness** — the number of blocking readiness checks.

It runs only local checks: rendering the widget never makes an outbound network request.

## Optional Dashboard widgets

The Command Center appears for everyone with CoreX visibility. Everything else is off until you ask for it.
Turn the optional widgets on under **CoreX → Settings → Dashboard**:

| Widget | Setting | Shows | Appears when |
| --- | --- | --- | --- |
| **CoreX Attention** | Attention widget | Your unread CoreX notifications, newest first (up to five) | You have at least one unread notification |
| **CoreX Development** | Development widget | The operating mode and its warnings | The site is in **Development** |

Four conditions must all hold before an optional widget is added to your dashboard:

1. **It is switched on** in Settings. An unchecked box and a setting that was never saved mean the same
   thing — off.
2. **You hold the ability it declares** — `corex_manage_notifications` for Attention,
   `corex_manage_operations` for Development. Administrators inherit both.
3. **You have something to see.** A widget with no data for you is not registered at all, rather than
   added and left empty.
4. **The mode allows it.** The Development widget appears only in Development — not in Staging, which is a
   rehearsal for production and should look like one, and not in Maintenance.

Both widgets read the same services as their full CoreX screens, make no remote calls, and offer links
only — no widget switches a mode, deletes, approves, or migrates anything. Because they are ordinary
dashboard widgets, WordPress's own **Screen Options** can still hide one per user.

## For developers: adding a producer

A producer is any class implementing `Corex\Notifications\NotificationProducer`:

1. Define a domain event in your module implementing `Corex\Events\Event`.
2. Dispatch it from your module's service through the injected `Corex\Events\EventDispatcher`.
3. Write a producer whose `register()` listens for that event and publishes a `Notification` through
   `Corex\Notifications\NotificationService`, and whose `isAvailable()` returns `class_exists(YourEvent::class)`.
4. Add it in `ConfigServiceProvider::registerNotificationProducers()`.

Publish a notification with `Notification::create(...)`, choosing a category, a severity, a recipient
(`NotificationRecipient::forUser`/`forUsers`/`forAbility`), and a **dedup key**: reuse one key for a recurring
condition so occurrences merge, or a unique key per distinct item. Put a mail-failure notification in the `email`
category so the channel policy can keep it off email — never email an email failure.
