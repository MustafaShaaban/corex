# Feature Specification: Reaching a human from Guides, notifications that lead somewhere, and admin controls you can see

**Feature Branch**: `spec/087-guides-support-and-admin-controls`

**Created**: 2026-07-28

**Status**: Draft

**Input**: Owner direction — *"In the guideline addon I want to add a way to contact support if the
user needs further help, or if they didn't find what they need in this guideline, or have a
suggestion… send a request to my email (I should be able to change the email easily in the code).
Also in the notifications, when I receive one, sometimes I need a call to action, or the notification
card itself to navigate me to where the notification guides me. Also fix the padding for these
titles 'Privacy operations / Submission retention'. Also the X icon in the popups opened, like
notifications and the inbox details, is not visible, as are the next and previous buttons in the
paginations. Also there is a blue colour effect that appears after clicking on any button — we need
to clear it."*

## Why this spec exists

Every item here was found by **using** the admin, not by reading it. That is the same pattern specs
085 and 086 recorded, and it is worth stating plainly again: four of the five defects below sit
behind code that reads correctly, and two of them sit behind tests that pass.

### 1. The Guides add-on is a dead end

`addons/corex-guides` renders help and accepts nothing back. A repo-wide search across the add-on for
`wp_ajax|admin_post|register_rest_route|nonce` returns exactly one hit, and it is a `current_user_can`
call. A reader who does not find their answer, or who spots something wrong, or who has a suggestion,
has nowhere to put it. There is no `mailto:` support link anywhere in product code either.

Spec 084 built the registry so a *site* can add guides. It did not give a *reader* a way to say the
guides are not enough.

### 2. Notifications cannot take you where they point

The call-to-action plumbing exists end to end and does not work. `NotificationAction` is defined, the
`corex_notifications` table has an `action_json` column, `WpNotificationRepository` writes it and
rehydrates it. Three independent faults keep it dark:

- **The label never renders.** PHP serialises `label_key` (`NotificationAction::toArray()`); the card
  reads `item.action.label` (`NotificationItem.js:181`). Every server-produced action therefore falls
  through to the hardcoded `__('Open')`. The Jest test that covers this feeds `{ label: … }` — a
  payload shape the server has never sent — so it passes against fiction.
- **The `ability` gate is never enforced.** The class docblock says *"A link renders only when the
  actor passes the optional `ability`"*, and spec 072's data model repeats it as FR-012.
  `present()` emits the whole action verbatim; the card renders on `action.url` alone. A link can be
  offered to a screen that will refuse the viewer on arrival.
- **Seven of eight producers set no action at all.** `SubmissionNotificationProducer` writes *"Open
  the Submission Inbox to read and assign it"* into the body as prose, with no link — the exact gap.

A fourth defect lives in the same component: the snooze control posts `snoozed_until`
(`NotificationsApp.js:158-163`) while the route reads `until` (`NotificationController.php:153`), so
`futureDate('')` returns null and **every snooze click answers 422**. No integration test covers the
route.

### 3. The retention panel's heading has no rhythm

`SubmissionsInboxScreen.php:131` emits a bare `<div>` around the eyebrow and the title. The React
header two hundred lines away emits `<div className="corex-inbox__heading">`. The CSS that fixes
this — a grid gap plus zeroed child margins — already exists, was written for exactly this problem,
and simply never applies here. So "Privacy operations" and "Submission retention" collide.

### 4. Controls that are the same colour as what they sit on

`corex-admin-shell.css` styles `.components-button.is-primary`, `.is-secondary`, `.is-link` and
`.is-destructive`, and has **no base `.components-button` rule**. A Gutenberg `<Button>` with no
variant keeps Gutenberg's own `#1e1e1e` ink, which on the dark CoreX surface is very nearly the
background. That is the submissions detail close X, its two text Close buttons, and the inbox
Previous/Next pager. The notification drawer's close is a different implementation with a different
problem: it is `--corex-admin-text-muted` at font-size `lg` with no padding, so it is both dim and a
small target.

### 5. A blue ring after every click

CoreX's focus rule is `.corex-admin :where(a, button, input, select, textarea, [tabindex]):focus-visible`
— specificity **(0,2,0)**, because `:where()` contributes nothing. WordPress core's
`.wp-core-ui .button:focus` is **(0,3,0)** and is `:focus`, not `:focus-visible`, so it fires on a
mouse click. CoreX's own `.button-primary:focus` sets background, border and colour but never
`box-shadow`, so the ring survives underneath. Gutenberg's `.components-button:focus:not(:active)` is
not answered at all.

The codebase convention is right — `:focus-visible` appears 45 times across first-party CSS. The
problem is specificity, and it cannot be won from inside `:where()`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A reader who did not find their answer can reach a person (Priority: P1)

Somebody reads the Guides screen, does not find what they need, scrolls to the bottom, writes what
they were looking for, and sends it. It arrives as email at the address the site owner configured.

**Independent Test**: open `admin.php?page=corex-guides` as any user who can read the admin, submit
the form, and confirm a mail is dispatched to the configured recipient with the sender as reply-to.

### User Story 2 — The site owner changes the support address without touching a controller (Priority: P1)

The default address ships in one config file, one line. The same value is editable in CoreX Settings
by a site owner who will never open PHP.

**Independent Test**: change `guides.support.email` in the Settings screen, submit the form, and
confirm the new recipient receives it; then confirm the code default still applies on a site that has
never saved the setting.

### User Story 3 — A notification takes you to the thing it is about (Priority: P1)

A new submission arrives. The notification says so, and the title and the button both go to that
form's rows in the Submission Inbox. A viewer who lacks the ability to manage submissions is not
offered the link at all.

**Independent Test**: publish a submission notification, open the Notification Center as a manager and
follow the link to the filtered inbox; repeat as an editor without the ability and confirm no link
renders.

### User Story 4 — Every control on a CoreX screen is visible and behaves when clicked (Priority: P1)

The close X on the submissions detail and the notification drawer are legible in both themes and are
a comfortable target. Previous/Next in every pager look like the buttons they are. Clicking any
button leaves no blue halo; tabbing to it shows the brass focus ring.

**Independent Test**: at 375px and 1280px, LTR and RTL, light and dark — measure the computed colour
contrast of each control against its surface, click each button and assert no `box-shadow` is applied,
then focus it by keyboard and assert the outline is present.

### Edge Cases

- No support address configured, or the support form switched off → the panel states that plainly
  rather than rendering a form that discards its input.
- Corex Mail inactive → the message still leaves, via `wp_mail()`.
- Neither transport accepts it → the reader is told it did not send, not told it did.
- A logged-in user with no email address on their account → the form still submits; reply-to is
  omitted rather than invented.
- Somebody submits the form repeatedly → throttled per user, with a message that says so.
- A notification whose action points at a screen the viewer may not open → no link, and the row is not
  filed under "needs action" on the strength of an action they cannot take.
- A notification with no action → the title is plain text, exactly as today.

## Requirements *(mandatory)*

### Functional — Guides support contact

- **FR-001**: The Guides screen MUST offer a way to send a message to site support.
- **FR-002**: The recipient MUST default to a value declared in one add-on config file, changeable by
  editing one line.
- **FR-003**: The same recipient MUST be editable from the CoreX Settings screen, and the saved value
  MUST take precedence over the code default.
- **FR-004**: The form MUST carry a category, a message, and the sender's email, and MUST prefill the
  email from the current user.
- **FR-005**: Submission MUST verify a nonce and a capability before doing anything, and MUST sanitize
  every field.
- **FR-006**: Delivery MUST NOT hard-depend on any optional plugin or add-on (Constitution IX). It
  MUST use Corex Mail when bound and `wp_mail()` otherwise.
- **FR-007**: The result MUST be reported truthfully: sent, or not sent, never "sent" for an
  unattempted or failed dispatch.
- **FR-008**: Repeated submissions from one user MUST be throttled.
- **FR-009**: When no recipient is configured, or the feature is switched off, the screen MUST say so
  instead of rendering the form.

### Functional — Notification call-to-action

- **FR-010**: A notification's action label MUST render as authored. The server payload and the client
  reader MUST agree on the field.
- **FR-011**: An action whose declared `ability` the viewer does not hold MUST NOT be present in that
  viewer's payload — not merely hidden by the client.
- **FR-012**: The derived `view` and `needs_action` state MUST be computed from the action the viewer
  can actually see, so a row is never filed under "needs action" for an action it will not be offered.
- **FR-013**: A notification with an action MUST offer its title as a link to that action, in addition
  to the existing button. Secondary controls MUST NOT be nested inside that link.
- **FR-014**: Every producer that names a destination in its text MUST carry that destination as an
  action, gated by the matching ability.
- **FR-015**: The snooze control MUST send the parameter the route reads.

### Functional — Visibility and focus

- **FR-016**: Every Gutenberg `<Button>` on a CoreX admin surface MUST inherit CoreX ink, whatever its
  variant, including icon-only buttons.
- **FR-017**: Dialog close controls MUST meet WCAG 2.2 AA target size (24×24 minimum; 44×44 adopted
  here) and MUST reach AA contrast against their surface in both themes.
- **FR-018**: Clicking a button MUST NOT leave a focus ring from WordPress core or Gutenberg.
- **FR-019**: Moving focus to a control by keyboard MUST still show the CoreX focus outline.
- **FR-020**: The retention panel's eyebrow and title MUST use the same heading rhythm as the inbox
  header, by reusing the existing class rather than adding a second implementation.

### Non-functional

- **NFR-001**: No hardcoded colours, sizes or fonts — tokens only.
- **NFR-002**: Logical CSS properties throughout; no horizontal overflow at 375px in either direction.
- **NFR-003**: Every user-facing string translation-ready in the `corex` text domain.
- **NFR-004**: The Guides add-on MUST remain build-step-free — server-rendered markup, no bundler.

## Success Criteria *(mandatory)*

- **SC-001**: A message sent from the Guides screen reaches the configured address, with the sender as
  reply-to, on an install where Corex Mail is active **and** on one where it is not.
- **SC-002**: Changing the address in Settings changes the recipient, with no code edit.
- **SC-003**: A submission notification's title and button both open the Submission Inbox filtered to
  that form; the same notification renders no link for a viewer without the ability.
- **SC-004**: Snoozing a notification succeeds and the row leaves the active view.
- **SC-005**: In an automated pass over 375px/1280px × LTR/RTL × light/dark, every close control and
  pager button meets AA contrast and the 44×44 target, and no button carries a `box-shadow` after a
  mouse click while every one shows an outline after a keyboard focus.
- **SC-006**: The retention eyebrow and title are separated by the same gap as the inbox header, at
  both breakpoints and in both directions.

## Out of scope

- Promoting `NotificationDispatcher` from `plugins/corex-forms` into `corex-core`. It depends only on
  core `Corex\Mail\*` seams and belongs there, but moving it is a refactor across two plugins and is
  not what this spec is for. This spec builds a small local ladder in the add-on instead.
- Publishing a CoreX notification for each support request. Deliberate: email-only was the owner's
  choice, and the notification path can be added later without changing the controller's contract.
- Resolving `label_key` translation keys to strings. Nothing in the pipeline does this today; this
  spec adds an already-translated `label` beside the key rather than inventing a resolver.
