# Tasks: The admin shows what is coming while it loads

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

**How this was written**: by hand to the Spec Kit tasks template. `/speckit-tasks` was not run.

Tests are written first and seen to fail. `[P]` marks a task that touches no file another open task
touches. Each slice ends with its guards, a rendered check of every surface it touched (dark and
light, left-to-right and right-to-left, 1280 and 782, with the answer held back), and its notes
(`CHANGELOG.md`, `PROGRESS.md`, `DECISIONS.md`).

## Slice 1 — The placeholder, the wrapper, and Notifications (US1, US2)

- [x] T001 [P] Jest: `CorexSkeleton` — hidden from assistive technology, and holds nothing a screen reader would read
- [x] T002 [P] Jest: `CorexLoadable` — `loading` shows the skeleton and not the children; `ready` the children; `refreshing` the children, inert; `error` the shared error state with its retry; `data-corex-state` and `aria-busy` for each; a load that lasts is announced at its start and its end, a quick one and a refresh are not; focus that was inside refreshing content moves to the surface
- [x] T003 The five tokens, in both themes (`corex-admin-tokens.css`)
- [x] T004 `.corex-admin-skeleton*` and `.corex-loadable` (`corex-admin-shell.css`): the shimmer, the delay before showing, the waiting bar; each with its own reduced-motion rule and a still look that reads as what it is
- [x] T005 `CorexSkeleton`, `CorexLoadable`
- [x] T006 Jest: Notifications — the list keeps its items while it reloads; preferences and the drawer show a placeholder and never "all caught up" before an answer; a failure shows the shared error state with a retry
- [x] T007 Notifications list, preferences and drawer on the shared pieces
- [x] T008 Playwright (`loading-states.spec.js`): the Notifications screen and the drawer with the answer held back — a placeholder, then content; the first placeholder card has the left edge, the top and the width of the card that replaces it, and a height within 8px of it; under reduced motion nothing is animating; both themes, both directions
- [x] T009 The token inventory; the pieces documented on the docs site (Design System, Admin experience, "Loading states"); guards; UI/UX gate; notes

## Slice 2 — The loader, and every action (US3, US4)

An inventory at `f2908076` found 55 controls outside the Submissions inbox that send a request.
They are done a screen at a time, each in its own pull request.

- [x] T020 [P] Jest: `workingProps` — sends nothing on a second press; keeps its name and says it is busy; leaves alone a control disabled for its own reason
- [x] T021 The working control and its loader (`corex-admin-shell.css`): the ring, its still look, a button that keeps its width, the colours wp-admin takes from a disabled `.button`, and a control in a WordPress `Modal`
- [x] T022 `working.js`
- [x] T023 Notifications (8 controls): each notification's actions, "Mark all as read" on the screen and in the drawer, the preference boxes; the three that failed in silence say why
- [x] T024 The four `isBusy` buttons (export, the Data confirmation, the import dry-run, the migration confirmation); a hygiene test that `isBusy` is not used under `plugins/corex-config/src`
- [x] T025 Playwright: a held action shows working, keeps its button's width to the pixel and sends nothing on a second press; the working state reaches a control drawn where a modal is
- [x] T026 Email Studio (9) and Forms and flows (7): one flag disabled every button on each screen and nothing marked the one that was pressed. The row that opens a template and the row that opens a flow are loads, and go with slice 4
- [x] T027 Data (11): the four that ask for a change preview, whose dialogs now stay until it is back; the two downloads; the import's remap and commit; the migration previews and refresh. "View", which opens a record, is a load and goes with slice 3
- [x] T028 Blog (3), Access (2), Security (1): the labels that changed to "Saving…", "Applying…", "Refreshing…" are gone. Security's "Apply mode" is a form the server draws
- [x] T029 The screens that are not React: Insights (1), the setup wizard (3), the captcha test (1), with the three attributes written by hand. The forms the server draws (Settings, "Apply mode", four others) are left: the page loading is what a person sees
- [x] T030 Guards; UI/UX gate; notes, with each part

## Slice 3 — Data (US1, US2)

- [x] T130 Jest: `viewState()` — a first load is `loading`; a load with rows on screen is `refreshing`
- [x] T131 [P] Jest: `useDebounced` — ten changes inside the pause make one call, with the last value; nothing is asked after the screen has gone
- [x] T132 Records: skeleton rows first, rows kept and waiting after; the total waiting with them; the search box debounced and keeping focus; a slower answer not kept; `Spinner` gone from `DataExplorer.js`
- [x] T133 A record opens its dialog at once with a placeholder. WordPress's `Modal` is outside the admin's token scope, so the detail moved to `CorexDialog`
- [x] T134 Export history and migrations: a placeholder, never "no history" before an answer; a failed load said where the list is, with a retry
- [x] T135 The source list's failure is said (FR-006)
- [x] T136 Playwright: the list, a record and the export history with the answer held back; a sort keeps the rows; ten typed characters send one request
- [x] T137 Guards; UI/UX gate; notes

## Slice 4 — Forms and flows, Email Studio (US1, US2)

- [x] T040 Jest: what the catalog is in each of its seven situations; whether Email Studio is still loading, with the guess from "no templates" gone; a chosen template shows the editor that is coming and cannot be chosen twice
- [x] T041 Forms catalog and opening a flow
- [x] T042 Email Studio and a template chosen from the list
- [x] T043 `helpers.js` and `email-studio.spec.js`, which waited on a loading sentence, wait on `data-corex-state`
- [x] T044 Playwright; guards; UI/UX gate; notes

## Slice 5 — Insights and the setup wizard (US1)

- [ ] T050 Jest: the Insights cards say "loading", not "not run yet", until the results arrive; the widgets hold their place
- [ ] T051 Insights, with the class names as markup; its two silent failures said
- [ ] T052 The setup wizard's state and plan step
- [ ] T053 Playwright; guards; UI/UX gate; notes

## Slice 6 — Submissions (US1, US2, US3), with spec 105's session

- [ ] T060 Agree the order of changes to `Submissions/index.js`, `useInbox.js`, `inbox.js` and `detail/DetailPane.js` with that session
- [ ] T061 Jest: the list keeps its rows on a refresh and shows a skeleton on a first load; the header count waits; the pane shows its skeleton; each pane action is working and cannot be sent twice
- [ ] T062 The list, the pane, both export dialogs (counts and past exports); the last two `Spinner` uses gone, and a hygiene test that none is imported under `plugins/corex-config/src`
- [ ] T063 Playwright: as slice 3's, for the inbox; the existing inbox specs still wait on `data-status`
- [ ] T064 Guards; UI/UX gate; notes
