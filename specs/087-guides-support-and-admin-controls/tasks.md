# Tasks — Spec 087

## Phase 1 — CSS (independent, visible)

- [x] T001 Add `class="corex-inbox__heading"` to the retention header `<div>` (`SubmissionsInboxScreen.php:131`)
- [x] T002 Base `.corex-admin .components-button` rule (ink + `fill: currentColor` + hover)
- [x] T003 Raise `.corex-notification-drawer__close` to `--corex-admin-text` with a 44×44 target
- [x] T004 Style `.corex-notifications-screen__pager button`
- [x] T005 `:focus` `box-shadow: none` resets for `.button` / `.button-secondary` families
- [x] T006 Add the missing `box-shadow: none` to `.button-primary:focus`
- [x] T007 `.components-button:focus:not(:active)` reset, preserving `.is-secondary`'s inset border
- [x] T008 Explicit `:focus-visible` outlines for each family so keyboard focus survives
- [x] T009 `.corex-notification__title a` link styling
- [x] T010 `npm run lint:css` clean

## Phase 2 — Notification call-to-action

- [x] T011 `NotificationAction`: add `label`, emit it in `toArray()`, read it in `fromArray()`
- [x] T012 `WpNotificationRepository::present()`: resolve the visible action once; omit when the ability fails
- [x] T013 `present()`: derive `view` / `needs_action` from the visible action
- [x] T014 `NotificationItem.js`: read `action.label`; link the title outside the actions container
- [x] T015 `NotificationsApp.js`: post `until` instead of `snoozed_until`
- [x] T016 `SubmissionNotificationProducer`: action on both notifications; drop the prose destination
- [x] T017 `SubmissionAssignedNotificationProducer`: action
- [x] T018 `AccessRequestNotificationProducer`: action
- [x] T019 `EmailStudioFailureNotificationProducer`: action
- [x] T020 `ExportReadyNotificationProducer`: action
- [x] T021 `JobFailureNotificationProducer`: action
- [x] T022 `LoginLockoutNotificationProducer`: action

## Phase 3 — Guides support contact

- [x] T023 `addons/corex-guides/config/guides.php` with the support defaults
- [x] T024 `SupportSettings` — config default, option override, `configured()` / `enabled()`
- [x] T025 `SupportDeliveryResult` value object
- [x] T026 `SupportMailer` — `?Mailer` rung, `wp_mail()` floor, truthful result, validated reply-to
- [x] T027 `SupportRequestController` — nonce + capability, honeypot, sanitize, throttle, PRG flash
- [x] T028 `SupportPanel` — form, prefill, not-configured state
- [x] T029 `GuidesScreen::render()` renders the panel
- [x] T030 `GuidesServiceProvider` binds the four services and hooks `admin_post_corex_guides_support`
- [x] T031 `SettingsRegistry`: `guides` section with the two fields
- [x] T032 `assets/guides.css`: support panel styling, tokens only, logical properties

## Phase 4 — Tests

- [x] T033 Pest: `SupportSettings` precedence
- [x] T034 Pest: `SupportMailer` both rungs + truthful failure
- [x] T035 Pest: `SupportRequestController` refusals, honeypot, throttle, sanitization
- [x] T036 Pest: `NotificationAction` round-trip with `label`
- [x] T037 Pest: `present()` ability gating and view derivation
- [x] T038 Pest: one action assertion per producer
- [x] T039 Jest: rewrite `notificationItem.test.js` against the real payload shape; linked title; no-action
- [x] T040 Integration: snooze route with the corrected parameter
- [x] T041 Playwright: support form renders, submits, flashes
- [x] T042 Playwright: notification action navigates
- [x] T043 Playwright: extend the zero-overflow acceptance matrix to the support panel

## Phase 5 — Close out

- [x] T044 Docs: Guides support form + notification CTA contract
- [x] T045 Guards: `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`
- [x] T046 Full suite green; browser verification of D and E in both themes, mouse and keyboard
- [x] T047 `PROGRESS.md` + `DECISIONS.md`
- [x] T048 Push, open PR

## Not done, and stated rather than implied

- **T043** the zero-overflow acceptance matrix was not extended to the support panel. The existing
  matrix belongs to spec 079's denied surface and runs from its own capture script; adding a second
  surface to it is a change to that script, not to this one.
- **Four of the nine browser assertions are guards, not reproductions.** They were already green on
  the unfixed stylesheet — the drawer close already cleared 3:1, the detail glyph is legible on a
  light surface because the defect was dark-only, and the keyboard ring had to keep working. Kept
  because they lock the behaviour in; not counted as verification of a fix (Decision #201).
