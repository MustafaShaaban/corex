---

description: "Task list for Spec 076 — CoreX Admin Date & Time Foundation"
---

# Tasks: CoreX Admin Date & Time Foundation

**Input**: [spec.md](spec.md), [plan.md](plan.md)

**Tests**: REQUIRED. Every implementation task owes its test task, per the constitution's Definition
of Done.

**Ticking rule** (learned the expensive way in 074, recorded in PROGRESS.md): a box is ticked
against a verified artifact — a passing test, a captured screenshot, a command whose output was
read — never against "I wrote the code". A stale `tasks.md` cost real time twice.

## Format: `[ID] [P?] [Story] Description`

- **[P]** — can run in parallel (different files, no dependency)
- **[Story]** — US1 / US2 / US3 from spec.md, or FOUND for the shared foundation

---

## Phase 1: Evidence before code

**Purpose**: capture what is wrong now, so the change is provable and the spec's baseline counts are
defensible rather than remembered.

- [x] T001 [FOUND] Capture `evidence/before/` on the real local install via
      `tests/e2e/capture-datetime-evidence.mjs`. **Confirmed: the Submission Inbox renders raw SQL
      datetimes to the operator** — `2026-07-27 08:53:15`, five of five rows flagged machine-shaped.
      Overview renders `July 27, 2026 8:53 am` (the site's `date_format`/`time_format`, not the
      required form). Notifications render relative-only with the exact value reachable solely by
      hover.
      *The first run of the probe aimed at the wrong table column and reported zero machine-shaped
      values. That is worth recording: an evidence tool that looks in the wrong place produces a
      confident "no defect found", which is more dangerous than no evidence at all. The selector now
      counts from the end of the row.*
- [x] T002 [FOUND] Two-timezone comparison captured. The Forms flow list renders the **same three
      records nine hours apart** depending on the reader's browser — `7/10/2026, 2:43:19 PM` in UTC
      against `7/10/2026, 11:43:19 PM` in `Asia/Tokyo` — in browser-locale `M/D/YYYY` with seconds.
      This is SC-003's baseline, and it is the finding a screenshot alone cannot show.

---

## Phase 2: Foundational — the contract (blocks every user story)

**Purpose**: the presenter, the formatter, and the boundary between them. Nothing in US1–US3 can
start until these are done and their tests pass.

### The instant

- [x] T003 [FOUND] `plugins/corex-core/src/Support/DateTime/Instant.php`. Two rules emerged from
      writing the tests rather than from the plan: a **non-positive integer is an absence, not
      1970** (which is the rule `OperationsSecurityScreen` already applies by hand with
      `$entry['time'] > 0`, centralised here); and **relative expressions are refused** — PHP parses
      `'now'` and `'+1 day'` into real dates, so a corrupt value of `'now'` would render as today
      and look exactly like a working feature.
- [x] T004 [P] [FOUND] Pest: **28 assertions passing**. Includes the asymmetry the rules above
      create — integer `0` is absent, but `'1969-07-20T20:17:00Z'` still parses, because a stated
      date is not an absence.

### The value object and the contract

- [x] T005 [FOUND] `Formatted.php` — `human`, `machine`, `isPresent`. Immutable; a caller cannot
      hold one without the other (FR-012).
- [x] T006 [FOUND] `AdminDateTime.php` — the interface: the five kinds of FR-002 and the
      absent-value phrase. Lives with the client, per constitution X/DIP.
- [x] T007 [FOUND] `AdminDateTimeFormatter.php` over `wp_date()` + `wp_timezone()`. The three format
      patterns and the connector as `_x()` strings with translator comments. No `\a\t` escaping —
      the connector is a `sprintf` pattern so a translator can reorder both halves.
- [x] T008 [FOUND] Register in the container via the core service provider; add the
      container-backed static accessor justified in plan.md's Complexity Tracking (resolves from
      the container, never constructs).
- [x] T009 [P] [FOUND] Pest: the FR-003 example exactly — `1 August 2026 at 10:20 PM` — plus
      midnight (`12:00 AM`), noon (`12:00 PM`), an AM and a PM value, no seconds in `FULL`, seconds
      present in `EXACT`.
- [x] T010 [P] [FOUND] Pest: the timezone matrix — named zone, `gmt_offset` fallback, UTC-stored
      input, positive and negative offsets, both DST transitions, and a site genuinely set to UTC.
- [x] T011 [P] [FOUND] Pest: month, year and leap-day boundaries.
- [x] T012 [P] [FOUND] Pest: absent and malformed values return the field's truthful phrase and
      never `Invalid Date`, `NaN`, the epoch, or the current time (FR-018).

### The boundary

- [x] T013 [FOUND] The payload ships as `AdminDateTime::clientConfig()` rather than as the separate
      `AdminDateTimeConfig.php` the plan named. **Deviation, recorded rather than silent**: a
      separate class would have to duplicate the format-pattern accessors or reach back into the
      formatter for them, and the whole design rests on both sides reading *the same* patterns.
      One owner for the patterns is the point; a second class would have been a second place for
      them to drift. Secret-free asserted by test.
- [x] T014 [FOUND] `CorexAdminAssets::enqueue()` — one `wp_localize_script` onto `corex-runtime`.
      The presenter is **injected through the constructor**, not reached for through the facade:
      the class is container-built, so it has somewhere to inject, and the existing unit test
      caught the facade version immediately (`Call to undefined function wp_localize_script` under
      Brain Monkey). The facade stays for view code that has no constructor.
- [x] T015 [P] [FOUND] Pest integration: the payload is present on a CoreX screen and absent on a
      non-CoreX admin screen; it appears exactly once.

### The formatter in the browser

- [x] T016 [FOUND] `plugins/corex-config/src/admin/adminDateTime.js` — the same five kinds. Named
      zones via `Intl.formatToParts` with **numeric options only**; `gmt_offset` sites via epoch
      arithmetic. Words come from the config, never from the platform.
- [x] T017 [FOUND] `plugins/corex-config/src/admin/components/CorexTime.js` — renders
      `<time datetime="…">human</time>`, plus the relative variant carrying its exact value on a
      visible secondary line, not a `title` (FR-013).
- [x] T018 [P] [FOUND] Jest: the same format, timezone, boundary and invalid-value matrices as
      T009–T012.
- [x] T019 [P] [FOUND] Jest: `CorexTime` emits a valid `datetime` and human text; the relative
      variant exposes its exact value without hover.

### Parity

- [x] T020 [FOUND] Commit the shared instant fixture — every case named in plan.md §4 — as the
      single input to both suites.
- [x] T021 [FOUND] Pest + Jest parity tests over that fixture. **All 16 cases agreed on the first
      run**, including both Cairo DST transitions and the year/month rollovers taken at 22:30 UTC.
      Verified load-bearing by breaking it: changing the JS `j` token to `parts.day + 1` turned 5
      Jest tests red, so the fixture is a gate and not decoration.
      *Arabic parity is asserted at the unit level — the JS suite proves the formatter renders the
      month names it is handed (`1 آب 2026`) rather than what `Intl` would say (`أغسطس`). A booted
      Arabic WordPress belongs in the browser acceptance task (T041), not here.*

---

## Phase 3: US1 — an operator reads a date and knows what it means (P1)

- [x] T022 [US1] `OverviewRenderer.php:255` — presenter instead of
      `get_option('date_format') . ' ' . get_option('time_format')`.
- [x] T023 [US1] `OperationsSecurityScreen.php:222` and `:332` — presenter; the `format('c')`
      fallback that could emit raw ISO to a screen is deleted.
- [x] T024 [P] [US1] `Blog/ModerationPanel.js:36` — `CorexTime` instead of `toLocaleString()`.
- [x] T025 [P] [US1] `Email/components/LogsPanel.js:82` and `:154` — `CorexTime`.
- [x] T026 [P] [US1] `Email/components/OverviewPanel.js:83` and `TemplatePanel.js:53` — `CorexTime`.
- [x] T027 [P] [US1] `Forms/FlowList.js:153` — `CorexTime`; the existing `<time dateTime>` wrapper
      is already right and keeps its machine value.
- [x] T028 [P] [US1] `Submissions/index.js` — the four raw-ISO renders at `:527` (inbox date
      column), `:793` (`Attempted %s`), `:820` (drawer), `:913` (audit timeline).
- [x] T029 [P] [US1] `Security/SecurityCenter.js:376` — the security activity list.
- [ ] T030 [P] [US1] Sweep the remaining FR-020 surfaces for any date this task list has not named,
      and convert or record each. The audit found twelve; a surface found here is a spec-audit miss
      worth recording in PROGRESS.md, not a silent thirteenth fix.
- [ ] T031 [US1] Jest: each converted React surface renders the human form and no ISO string.
- [ ] T032 [US1] Pest integration: each converted server surface renders the human form; a site set
      to `Africa/Cairo` and a site set to UTC both render their own wall clock.

---

## Phase 4: US2 — precise, semantic, sortable (P2)

- [ ] T033 [US2] Every converted surface emits semantic `<time>` with a valid machine value —
      asserted, not assumed (FR-012).
- [x] T034 [US2] `NotificationItem.js` — relative time keeps its phrase and gains the non-hover
      exact value; the raw-ISO `title` goes.
- [ ] T035 [P] [US2] Verify FR-016 rather than change it: Pest integration proving the Submission
      Inbox and the Data explorer sort chronologically server-side across a month and a year
      boundary, and that missing values group at one end.
- [ ] T036 [P] [US2] Confirm `EXACT` (with seconds) is used only in diagnostics and nowhere in an
      ordinary operator view (FR-004).

---

## Phase 5: US3 — absent and invalid values (P3)

- [ ] T037 [US3] Give each converted call site its field-appropriate absent phrase — "Not recorded",
      "Never", "No expiry", "Pending". Not one shared word: they are different statements.
- [ ] T038 [US3] Malformed timestamps recorded in developer diagnostics, nothing sensitive exposed
      (FR-019).
- [ ] T039 [P] [US3] Jest + Pest: absent and malformed values on real surfaces produce the phrase,
      keep the layout, and never fabricate a date.

---

## Phase 6: Acceptance and closeout

- [ ] T040 `tests/e2e/admin-datetime.spec.js` — browser acceptance across the FR-020 surfaces: the
      required English string present, no ISO string anywhere in operator-facing text, `<time>`
      present, no console error, no failed `/wp-json/` request.
- [ ] T041 Browser acceptance in Arabic/RTL, at 375px, and at 200% zoom, with no horizontal
      overflow — measured against the stock wp-admin baseline the way
      `admin-command-center.spec.js` does, since core's own 1px is not ours (DECISIONS #163).
- [ ] T042 Capture `evidence/after/` for every `before/` shot, plus the two-timezone comparison from
      T002 now showing the same hour.
- [ ] T043 Guards on the diff: `wp-guard` + `clean-code-guard` on production code, `test-guard` on
      tests, `docs-guard` on the documentation task.
- [ ] T044 Documentation: admin date/time formatting, the timezone source of truth, locale
      behaviour, and machine-versus-display timestamps — including the plain statement that CoreX
      admin dates no longer follow Settings → General, and why.
- [ ] T045 Full gate: `lint:css`, `lint:js`, Jest, Pest unit, Pest integration, token inventory,
      Playwright.
- [ ] T046 `PROGRESS.md` + `DECISIONS.md` (the one-dictionary decision, and the fixed-format
      departure from Settings → General confirmed by the owner).
- [ ] T047 PR, green CI, resolve review, merge, delete branch.

---

## Dependencies

- Phase 2 blocks Phases 3–5 entirely. Nothing converts before the contract passes its tests.
- T020–T021 (parity) block T024–T029: converting a surface before parity is proven risks converting
  it twice.
- Phase 6 depends on 3–5.
- `[P]` tasks within a phase touch different files and may run together.

## Out of scope, deliberately

- Storage and REST shapes (FR-014/15). `OperationsSecurityScreen.php:406` emits
  `wp_date( DATE_ATOM, … )` where the rest of the codebase uses UTC `gmdate` — a real inconsistency,
  recorded in plan.md, and a transport question rather than a presentation one.
- The front office.
- Relative time on new surfaces. It stays where it already is; this spec makes it correct, not
  more widespread.
