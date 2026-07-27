---

description: "Task list for Spec 077 — Operations & Security UX and Safety Completion"
---

# Tasks: Operations & Security UX and Safety Completion

**Input**: [spec.md](spec.md), [plan.md](plan.md)

**Tests**: REQUIRED, per the constitution's Definition of Done.

**Ticking rule**: a box is ticked against a verified artifact — a passing test, a captured
screenshot, a command whose output was read — never against "I wrote the code".

---

## Phase 1: The two truthfulness fixes (independent of the rearrangement)

**Purpose**: these are defects, not layout. They are first because they are the ones that make the
screen lie, and because neither depends on the new information architecture.

- [x] T001 [US2] `OperationsModeStore::set()` returns early when the mode is already in force —
      no `update_option`, no log entry (FR-009). The guard lives in the store because the invariant
      is about the log: *the history records changes*.
      *The condition is `$from === $to && $this->isDeclared()`, not a bare `$from === $to`.
      Declaring the mode a site has merely INHERITED from `wp_get_environment_type()` is a real
      change — it moves the site from following the environment to stating its own position, which
      is exactly the transition an operator later looks for in this log. The bare comparison would
      have silently swallowed it.*
- [x] T002 [P] [US2] Pest: re-applying the current mode writes no option and appends no history;
      a real change still does both. Reproduces the `development → development` row first.
- [x] T003 [US2] `OperationsModeController` distinguishes applied from already-in-that-mode, and
      says so in its PRG notice rather than reporting success (FR-009).
- [x] T004 [P] [US2] Pest: the no-change submission redirects with the "no change required" notice,
      not the success one.
- [ ] T005 [US3] The environment section shows the WordPress environment type and the CoreX mode
      together, with a conflict warning when they differ and nothing implying the mode changes
      hosting (FR-014). `isDeclared()` distinguishes inherited from declared, so an inherited mode
      is not reported as a conflict.
- [ ] T006 [P] [US3] Pest integration: matching values show no warning; differing declared values
      show one; an inherited mode shows none.

---

## Phase 2: Progressive disclosure in the mode form

- [ ] T007 [US2] `ModeDisclosure` — per mode: meaning, warnings, and which confirmation is
      required. Pure description, no capability or readiness logic, so it is unit-testable without
      WordPress and one answer drives the render, the client swap and the server validation.
- [ ] T008 [P] [US2] Pest unit: Production requires the typed phrase and not the acknowledgement;
      Maintenance the reverse; Development and Staging neither.
- [ ] T009 [US2] The mode form renders all four blocks with the proposed mode's visible and the
      rest `hidden`; the proposed mode comes from `?mode=` when valid, else the current mode.
- [ ] T010 [US2] `operations-mode.js` swaps the visible block on `change`, and disables Apply when
      the selection equals the current mode (FR-008). Enqueued on this screen only.
- [ ] T011 [US2] No-JS path: submitting a mode whose confirmation was not shown fails validation
      and redirects to `?tab=environment&mode=<proposed>` with the confirmation now visible and a
      notice explaining why (FR-013).
- [ ] T012 [P] [US2] Pest integration: server-side validation rejects a Production change with no
      phrase and a Maintenance change with no acknowledgement, regardless of what was rendered.
- [ ] T013 [P] [US2] Jest: the swap shows exactly one block, and Apply disables on a no-op
      selection.

---

## Phase 3: The five sections

- [ ] T014 [US1] `Sections/` — one renderer per section, moving the existing markup rather than
      rewriting it, so the diff is legible and the behaviour is unchanged where it is already right.
- [ ] T015 [US1] `render()` resolves `?tab=` through an allow-list, defaults to `overview`, falls
      back to `overview` for anything unknown, and renders the strip plus one section (FR-001/004).
- [ ] T016 [US1] Reuse `AdminPage::tabs()` — links, `?tab=`, `aria-current="page"`. No ARIA
      tablist: real links satisfy the address, Back/Forward, PRG and no-JS requirements without
      JavaScript (plan §Summary).
- [ ] T017 [US1] The React island mounts per section and renders only that section's panels, so
      readiness, login policy, lockouts, recovery and activity land in the right places. One app,
      state module untouched.
- [ ] T018 [US1] The overview answers FR-005 from real values, each linking to its section.
- [ ] T019 [US1] `OperationsModeController`'s PRG redirect carries `tab=environment`, so a mode
      change returns to the section it was made in (FR-002).
- [ ] T020 [P] [US1] Pest integration: each section renders; an unknown `?tab=` falls back to
      overview; no section renders empty.
- [ ] T021 [US3] Readiness presents blockers, warnings and passed as separate counted groups with
      resolution links and the evaluation time (FR-015). The states already exist on
      `ReadinessSnapshot`; this groups by them.
- [ ] T022 [US3] Hardening checks grouped rather than one undifferentiated list (FR-005/§3.5).

---

## Phase 4: Login protection

- [ ] T023 [US3] `LoginSlugAvailability` — the collisions `LoginSlug`'s pattern cannot see: a
      published page at that path, an existing rewrite endpoint (FR-018). Typed reasons matching
      `LoginSlug`'s vocabulary. `LoginSlug` itself is not touched.
- [ ] T024 [P] [US3] Pest integration: a slug colliding with a published page is refused; a free
      one is accepted; the existing reserved and format rules still apply.
- [ ] T025 [US3] The login section shows the address actually in force, distinct from any unsaved
      value, with the recovery command and default-endpoint state (FR-016), and reports a
      substituted slug as the value in force with the substitution explained (FR-017).
- [ ] T026 [US3] Lockouts distinguish active from expired with reason and expiry, and show no raw
      IP where a hash is stored (FR-019). **No unlock control** — the audit found no real audited
      unlock path, and offering one that does not exist is the defect this project keeps removing.
- [ ] T027 [P] [US3] Pest: an active and an expired lockout render distinctly; no hash is rendered
      as though it were an address.

---

## Phase 5: Rhythm and notices

- [ ] T028 [US4] One container owns section spacing with a token `gap`; the per-section
      `margin-block-end` declarations come out (FR-020). The measured gaps are already 24px — this
      changes where the number lives, not the number.
- [ ] T029 [US4] Notices scoped to what they describe, no duplicates, and dismissal that cannot
      imply an unresolved condition was resolved (FR-021).
- [ ] T030 [P] [US4] Playwright: measured section gaps are uniform and come from the container.

---

## Phase 6: Acceptance and closeout

- [ ] T031 Playwright: every section reachable and linkable; PRG keeps the section; Back/Forward
      correct; keyboard only; unknown tab falls back.
- [ ] T032 Playwright: RTL, 375px, 200% zoom, light and dark, with no horizontal overflow beyond
      stock wp-admin's own (DECISIONS #163) and no console error.
- [ ] T033 Playwright: dates on this screen come from the spec 076 contract — verified, not
      reimplemented (FR-023).
- [ ] T034 Capture `evidence/after/` against the `before/` capture, including the mode form in each
      of the four modes.
- [ ] T035 Guards: `wp-guard` + `clean-code-guard` on production code, `test-guard` on tests,
      `docs-guard` on the documentation.
- [ ] T036 Documentation: the information architecture, environment versus mode, the production
      transition, maintenance behaviour, login protection and recovery.
- [ ] T037 Full gate: `lint:css`, `lint:js`, Jest, Pest unit, Pest integration, token inventory,
      Playwright.
- [ ] T038 `PROGRESS.md` + `DECISIONS.md` (links-not-tablist; the no-op guard's home; the two-step
      no-JS disclosure).
- [ ] T039 PR, green CI, resolve review, merge, delete branch.

---

## Dependencies

- Phase 1 is independent of everything else and ships value on its own.
- Phase 2 needs T007 before T009–T013.
- Phase 3's T014 precedes the rest of its phase; T019 needs T015.
- Phases 5 and 6 follow 3.

## Out of scope, deliberately

- `LoginSlug`'s existing rules, `MaintenanceGuard`, `ReadinessSnapshot`'s states, the security REST
  controller — all already correct (spec.md, "What is already right").
- The Cache & Performance section — spec 078 builds it against this architecture. An empty tab now
  would be the dead end the mandate forbids.
- Any lockout unlock control that does not already exist behind a real, audited implementation.
