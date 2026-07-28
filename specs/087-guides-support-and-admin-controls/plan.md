# Implementation Plan — Spec 087

**Branch**: `spec/087-guides-support-and-admin-controls` · **Spec**: `./spec.md`

## Order

CSS first (C, D, E) because it is small, visible, and independent. Then the two feature slices.

---

## A · Guides support contact

### Config

New `addons/corex-guides/config/guides.php`:

```php
return ['support' => ['email' => 'Mustafashaaban22@gmail.com', 'enabled' => true]];
```

Addon config files are **not** auto-globbed — `CoreServiceProvider::defaults()` scans only
`plugins/corex-core/config/`. So the provider requires the file and passes its value as the
`Config::get` default, the pattern already used at `addons/corex-email/src/MailServiceProvider.php:92-96`.
`OptionsSource` maps `guides.support.email` → option `corex_guides_support_email`, which is the option
the Settings screen writes, so the two layers meet without any core change.

### `src/Support/SupportSettings.php`

Reads `guides.support.email` and `guides.support.enabled` through `ConfigInterface`, falling back to
the config file's array. Answers `recipient(): string`, `enabled(): bool`, `configured(): bool`.
Injected, never a service locator (Principle IV).

### `src/Support/SupportMailer.php`

Two rungs, mirroring `NotificationDispatcher::dispatch()`/`wpMail()` without importing it:

1. `?Mailer $mailer` — bound only by `addons/corex-email`, injected conditionally by the provider as
   `$c->has(Mailer::class) ? $c->make(Mailer::class) : null` (the shape at `FormsServiceProvider.php:198`).
   If it is an `AttemptingMailer`, use `attempt()` and keep the real result.
2. `wp_mail()` floor, capturing `wp_mail_failed` for a safe reason string.

Returns a small `SupportDeliveryResult` (sent / not sent / reason) so FR-007 can be honoured.
Reply-to is set only when the sender's address passes `FILTER_VALIDATE_EMAIL`.

### `src/Support/SupportRequestController.php`

Modelled on `plugins/corex-config/src/Access/AccessRequestFormController.php`:

- `ACTION = 'corex_guides_support'`, `NONCE = 'corex_guides_support_nonce'`, `CAPABILITY = 'read'`.
- `AdminGuard::verifiedPost(NONCE, ACTION, CAPABILITY)` — one call for nonce + capability
  (DECISIONS #58).
- Honeypot field checked before anything else; a filled honeypot redirects with the success flash and
  sends nothing.
- `sanitize_text_field` (category), `sanitize_textarea_field` (message), `sanitize_email` (from).
- Per-user transient throttle, 60s.
- POST → redirect → GET back to `admin.php?page=corex-guides` with a flash transient keyed per user,
  read and deleted once by the panel (mirror `AccessRequestFlash`).

### `src/Support/SupportPanel.php`

Rendered from `GuidesScreen::render()`, the single insertion point. `AdminPage::state()` for the
"not configured / switched off" case. Category select, message textarea, email input prefilled from
`wp_get_current_user()`, honeypot, nonce, submit. Server-rendered strings, escaped at output — the
add-on has no build step and keeps none (NFR-004).

### Settings

`plugins/corex-config/src/Settings/SettingsRegistry.php` — a `guides` section with
`guides.support.email` (email) and `guides.support.enabled` (checkbox). Precedent: the existing
`captcha` section already declares keys owned by an add-on.

### Wiring

`GuidesServiceProvider::register()` binds `SupportSettings`, `SupportMailer`, `SupportRequestController`,
`SupportPanel`. `boot()` hooks `admin_post_corex_guides_support` as a closure resolving the controller
lazily (the shape `ConfigServiceProvider` uses), and passes the panel into `GuidesScreen`.

---

## B · Notification call-to-action

| File | Change |
|---|---|
| `plugins/corex-core/src/Notifications/NotificationAction.php` | Add `label` (already translated) beside `labelKey`. `to()` takes it; `toArray()` emits `label_key`, `label`, `url`, `ability`; `fromArray()` reads both, defaulting `label` to `''`. |
| `plugins/corex-config/src/Notifications/WpNotificationRepository.php` | In `present()`, resolve the visible action once: `null` when `ability` is set and `current_user_can()` fails. Unset `$data['action']` in that case, and pass the *visible* action to `NotificationView::of()` / `needsAction()`. |
| `plugins/corex-config/src/admin/notifications/NotificationItem.js` | Read `item.action.label`. Wrap the title in `<a>` when an action exists — inside `.corex-notification__body`, outside `.corex-notification__actions`, so no control is nested in the link. |
| `plugins/corex-config/src/admin/notifications/NotificationsApp.js` | Post `until`, the parameter `NotificationController::snooze()` reads. |
| `plugins/corex-core/assets/css/corex-admin-shell.css` | `.corex-notification__title a` — token link colour, underline on hover only, focus outline. |

Producers in `plugins/corex-config/src/Notifications/Producers/` — add `action:` with the matching
`CorexAbility`, following `ReadinessNotificationProducer.php:101-108`:

| Producer | Destination | Ability |
|---|---|---|
| `SubmissionNotificationProducer` (new) | Submissions inbox filtered to the flow | `MANAGE_SUBMISSIONS` |
| `SubmissionNotificationProducer` (email failure) | Email Studio | `MANAGE_SUBMISSIONS` |
| `SubmissionAssignedNotificationProducer` | The submission | `MANAGE_SUBMISSIONS` |
| `AccessRequestNotificationProducer` | Access screen | `MANAGE_ACCESS` |
| `EmailStudioFailureNotificationProducer` | Email Studio | `MANAGE_SETTINGS` |
| `ExportReadyNotificationProducer` | Data screen | `MANAGE_DATA` |
| `JobFailureNotificationProducer` | Jobs screen | `MANAGE_SETTINGS` |
| `LoginLockoutNotificationProducer` | Operations & security | `MANAGE_SECURITY` |

Exact ability constants are read from `CorexAbility` at implementation time — the table records intent,
not a guess. `SubmissionNotificationProducer`'s body text loses the prose *"Open the Submission Inbox
to read and assign it"*, which the link now says better.

---

## C · Retention heading

`plugins/corex-config/src/Submissions/SubmissionsInboxScreen.php:131` — add
`class="corex-inbox__heading"` to the bare `<div>`. Nothing else. `submissions-admin.scss:34-44`
supplies the grid gap and zeroes the child `<p>` margins; its source order beats the
`.corex-inbox-retention p` margin at `:25`, so the specificity tie resolves correctly. Recompile
`.scss` → `.css` only if the retention form's own spacing turns out to need it.

## D · Visible controls

`plugins/corex-core/assets/css/corex-admin-shell.css`, placed **above** the variant rules at `:798`
so those keep winning:

- `.corex-admin .components-button` — `color: var(--corex-admin-text)`, `fill: currentColor` for the
  dashicon SVG, hover to `--corex-admin-text` on `--corex-admin-surface-alt`.
- `.corex-notification-drawer__close` (`:368`) — `--corex-admin-text`, min 44×44, radius, hover.
- `.corex-notifications-screen__pager button` — token styling; today they are plain `<button>`s with
  raw user-agent chrome.

## E · No blue ring

Same file. Each third-party rule answered explicitly at matching-or-higher specificity:

| Loser | Winning rule to add |
|---|---|
| `.wp-core-ui .button:focus` (0,3,0) | `.corex-admin .button:focus`, `.button-secondary:focus`, `button.button:focus`, `input.button:focus` → `box-shadow: none` + token colours |
| CoreX `.button-primary:focus` missing `box-shadow` | add `box-shadow: none` to the existing rule at `:775` |
| `.components-button:focus:not(:active)` (0,3,0) | `.corex-admin .components-button:focus:not(:active)` → `box-shadow: none`; `.is-secondary` keeps its `inset` border shadow |

Keyboard focus stays visible: an explicit `:focus-visible` outline for each family at matching
specificity. The `:where()` rule at `:730` remains as the floor for everything else.

**Browser verification is mandatory here** — this is a specificity contest against two third-party
stylesheets, and reading CSS cannot settle it.

---

## Verification

```powershell
composer test                # Pest unit,       baseline 1656
composer test:integration    # baseline 349/352
npm test                     # Jest,            baseline 423
npm run test:e2e             # Playwright,      baseline 111
composer lint; npm run lint:js; npm run lint:css
```

New tests:

- **Pest unit** — `SupportRequestController` (nonce refusal, capability refusal, sanitization,
  honeypot, throttle, disabled state); `SupportSettings` precedence (option over config default);
  `SupportMailer` falling back to `wp_mail` with no `Mailer` bound and reporting failure truthfully;
  `NotificationAction` round-trip with `label`; `present()` omitting an action the ability forbids and
  deriving `view` from the visible action; one assertion per producer for its action URL and ability.
- **Jest** — `notificationItem.test.js` rewritten against the real `present()` payload shape. The
  existing test feeds `{label: …}`, which the server has never sent; that is why the bug survived.
  Add the linked-title case and the no-action case.
- **Integration** — the snooze route with the corrected parameter, which has no coverage today.
- **Playwright** — the support form renders, submits and flashes; a notification action navigates;
  extend the existing zero-overflow acceptance matrix to the support panel.

Manual, on the running install: submissions detail X and pager legible in dark and light; click →
no blue ring; Tab → brass ring; retention heading spaced; RTL at 375px with no horizontal overflow.

## Guards

`clean-code-guard` and `wp-guard` on the PHP/JS diff, `test-guard` on the tests, `docs-guard` on the
docs. No diff is presented until all four run clean.
