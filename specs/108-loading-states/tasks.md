# Tasks: The admin shows what is coming while it loads

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

**How this was written**: by hand to the Spec Kit tasks template. `/speckit-tasks` was not run.

Tests are written first and seen to fail. `[P]` marks a task that touches no file another open task
touches. Each slice ends with its guards, a rendered check of every surface it touched (dark and
light, left-to-right and right-to-left, 1280 and 782, with the answer held back), and its notes
(`CHANGELOG.md`, `PROGRESS.md`, `DECISIONS.md`).

## Slice 1 — The pieces, and Notifications (US1, US4)

- [ ] T001 [P] Jest: `CorexSkeleton` — each shape renders the count asked for, is `aria-hidden`, and holds no text a screen reader would read
- [ ] T002 [P] Jest: `CorexLoadable` — `loading` shows the skeleton and not the children; `ready` the children; `refreshing` the children, inert and marked waiting; `error` the error with its retry; `data-corex-state` and `aria-busy` for each; the live region says the start and the end once each and nothing on a refresh
- [ ] T003 [P] Jest: `workingProps` — disabled, busy, the class; nothing when not working
- [ ] T004 The five tokens, in both themes (`corex-admin-tokens.css`)
- [ ] T005 `.corex-skeleton*`, `.corex-loader`, `.corex-loadable`, `.corex-is-working` (`corex-admin-shell.css`): the shimmer, the delay before showing, the waiting bar, the ring; each with its own reduced-motion rule and a still look that reads as what it is
- [ ] T006 `CorexSkeleton`, `CorexLoader`, `CorexLoadable`, `working.js`
- [ ] T007 Jest: Notifications — the list keeps its items while it reloads; preferences and the drawer show a placeholder and never "all caught up" before an answer; a failure shows the shared error state
- [ ] T008 Notifications list, preferences and drawer on the shared pieces
- [ ] T009 Playwright (`loading-states.spec.js`): the Notifications screen and the drawer with the answer held back — a placeholder, then content, and the list's box does not move by more than 4px; under reduced motion nothing is animating; both themes, both directions
- [ ] T010 The token inventory; `docs-app` guide for the four pieces; guards; UI/UX gate; notes

## Slice 2 — Data (US1, US2)

- [ ] T020 Jest: `viewState()` — a first load is `loading`; a load with rows on screen is `refreshing`
- [ ] T021 [P] Jest: `useDebounced` — ten changes inside the pause make one call, with the last value
- [ ] T022 Records: skeleton rows first, rows kept and waiting after; the two totals waiting with them; the search box debounced and keeping focus
- [ ] T023 A record opens its dialog at once with a placeholder; whether WordPress's `Modal` is inside the admin's token scope is checked first, and the dialog moved to `CorexDialog` if it is not
- [ ] T024 Export history and migrations: a placeholder, never "no history" before an answer
- [ ] T025 The source list's failure is said (FR-006)
- [ ] T026 Playwright: each of these with the answer held back; a page turn and a search keep the rows; ten typed characters send at most two requests
- [ ] T027 Guards; UI/UX gate; notes

## Slice 3 — Forms and flows, Email Studio (US1, US2)

- [ ] T030 Jest: the Forms catalog and the Email Studio panel through `CorexLoadable`; the Email heuristic that re-showed "loading" on a site with no templates is gone
- [ ] T031 Forms catalog and opening a flow
- [ ] T032 Email Studio and a template chosen from the list
- [ ] T033 `helpers.js` and the specs that wait on a loading sentence move to `data-corex-state`
- [ ] T034 Playwright; guards; UI/UX gate; notes

## Slice 4 — Insights and the setup wizard (US1)

- [ ] T040 Jest: the Insights cards say "loading", not "not run yet", until the results arrive; the widgets hold their place
- [ ] T041 Insights, with the class names as markup; its two silent failures said
- [ ] T042 The setup wizard's state and plan step
- [ ] T043 Playwright; guards; UI/UX gate; notes

## Slice 5 — Every action (US3, US4)

- [ ] T050 Jest, per screen: each control that sends a request is working while it is in flight and sends nothing on a second press
- [ ] T051 Email Studio, the Forms editor, Access, Blog, Security, Data dialogs, Insights, the captcha test: `workingProps`
- [ ] T052 `Spinner` and `isBusy` removed from the admin; a hygiene test that neither is imported under `plugins/corex-config/src`
- [ ] T053 Playwright: a held action shows working and keeps its button's width to the pixel
- [ ] T054 Guards; UI/UX gate; notes

## Slice 6 — Submissions (US1, US2, US3), with spec 105's session

- [ ] T060 Agree the order of changes to `Submissions/index.js`, `useInbox.js`, `inbox.js` and `detail/DetailPane.js` with that session
- [ ] T061 Jest: the list keeps its rows on a refresh and shows a skeleton on a first load; the header count waits; the pane shows its skeleton; each pane action is working and cannot be sent twice
- [ ] T062 The list, the pane, both export dialogs (counts and past exports)
- [ ] T063 Playwright: as slice 2's, for the inbox; the existing inbox specs still wait on `data-status`
- [ ] T064 Guards; UI/UX gate; notes
