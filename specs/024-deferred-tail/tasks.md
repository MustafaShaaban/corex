# Tasks: Deferred tail — mail queue, Abilities/MCP, setup wizard (024)

**Retrospective spec** — the three sub-features exist, are unit-tested, and verified on real WP (both abilities
registered; `Mailer` resolves to `QueuedMailer`; wizard lists company+portfolio; zero-notice boot). These are
**reconciliation/verification** tasks: confirm each FR against the mapped file/behaviour (most already
satisfied, marked `[x]`), plus the tracked debts (a formal Guard Gate re-run → **P2**; SetupWizardScreen SRP +
token fix + AbilitiesProvider extract → **P3**; the admin-screen Principle-VII policy → **P5**). The FR→file
map is in `plan.md`.

**No new implementation work** beyond the tracked debts — flag any mismatch found as a defect rather than
scope.

## Phase 1: Setup (verification context)

- [x] T001 Confirm the bases: spec-008 `Corex\Mail\{Mailer,MailRequest}` seam; spec-001 Config/boot hooks; spec-010 `Blueprint`.
- [x] T002 Confirm Action Scheduler is present (transitive via WooCommerce) and the WP 7.0 Abilities API may or may not be present (gated either way).

## Phase 2: Foundational (the boot-safety fix — affects every request)

- [x] T003 Verify FR-003 + SC-003: the mail worker registers lazily (not at `plugins_loaded`) so the mail stack isn't built early; a normal request boots with zero errors/notices (`addons/corex-email/src/MailServiceProvider.php`; DECISIONS #55).

## Phase 3: User Story 1 — Bulk mail can queue when a backend exists (P1) 🎯 MVP

**Goal**: queue via Action Scheduler only when available + flagged, else inline; seam unchanged.
**Independent test**: gate truth table; `MailRequest` payload round-trip.

- [x] T004 [US1] Verify FR-001 + SC-001: `MailQueueGate` enables queueing only when the backend is available AND `mail_queue` is on; `QueuedMailer` enqueues when the gate says queue and sends inline otherwise (`tests/Unit/Email/MailQueueTest.php` — "queues only when the backend is available and the flag is on", "enqueues instead of sending…", "sends inline when the gate says do not queue"); on real WP `Mailer` resolves to `QueuedMailer`.
- [x] T005 [US1] Verify FR-002 + SC-002: `ActionSchedulerDispatcher` enqueues a scalar `MailRequest` and a worker sends it; the `MailRequest` round-trips through the payload intact (MailQueueTest — "round-trips a MailRequest through the queue payload").

## Phase 4: User Story 2 — Read-only, gated AI abilities on WP 7.0 (P2)

**Goal**: read-only, cap-gated, REST-exposed abilities, no-fatal where the API is absent.
**Independent test**: build the ability data headlessly; confirm guarded registration.

- [x] T006 [US2] Verify FR-004: `CorexAbilities` lists only `corex/*` blocks with titles (name fallback) and summarises the site/framework (`tests/Unit/Abilities/CorexAbilitiesTest.php` — "lists only corex/* blocks with their titles", "falls back to the block name when a title is missing", "summarises the site/framework").
- [x] T007 [US2] Verify FR-005 + SC-004: `AbilitiesProvider` registers `corex/list-blocks` + `corex/site-info` as read-only, cap-gated, REST-exposed abilities on the API init hooks, `function_exists`-guarded; both registered on real WP, never fatal where the API is absent.

## Phase 5: User Story 3 — A setup wizard to compose a site (P2)

**Goal**: plan a kit into de-duped modules + flags; apply (nonce + cap gated) enables flags, activates modules, seeds Home.
**Independent test**: list kits; plan a kit; unknown kit → empty plan.

- [x] T008 [US3] Verify FR-006 + FR-008 + SC-005: `SetupWizard::kits()` lists each kit with its needs; `plan(name)` returns de-duped modules + the kit's `Blueprint::featureFlags()`; an unknown kit yields an empty plan (`tests/Unit/Kit/SetupWizardTest.php` — all four cases); lists company+portfolio on real WP.
- [x] T009 [US3] Verify FR-007: `SetupWizardScreen` is admin-only — verifies a nonce + `manage_options` before enabling flags, activating modules, and seeding a demo Home page.

## Phase 6: Polish & cross-cutting

- [ ] T010 [P] **(P2)** Run the Guard Gate formally on this feature's diff: `clean-code-guard` + `wp-guard` (abilities registration / wizard nonce+cap / mail dispatch) + `test-guard` (the 11 new cases) + `docs-guard` (corex-email + corex-kit-company READMEs); fix any reported violation. _Tracked as remediation P2._
- [x] T011 **(P3 — DONE 2026-06-11)** Applied the clean-code fixes: extracted `Corex\Kit\BlueprintActivator` from `SetupWizardScreen` (SRP); replaced the inline-style `1.5rem` fallback with WP core's admin `.card` class; extracted `AbilitiesProvider::registerReadOnlyAbility()`. 269 unit green. DECISIONS #57. _(audit findings #2, #3, #4)_
- [x] T012 **(P5 — DONE 2026-06-11)** Decided: admin-menu screens are exempt from the route middleware pipeline but use the shared `Corex\Security\Admin\AdminGuard` (cap + nonce). Refactored `SetupWizardScreen` + `AdminDashboard` onto it; 5 `AdminGuardTest` Pest cases; constitution v1.2.1 clarifies Principle VII's scope. DECISIONS #58.
- [x] T013 Confirm docs: corex-email + corex-kit-company READMEs updated; DECISIONS #53 (deferred tail) + #55 (boot fix) record the approach; PROGRESS reflects completion.

## Dependencies

- Phase 2 (the boot-safety fix) affects every request and is verified first. US1/US2/US3 are independent
  (different add-ons/dirs) and independently verifiable.
- T010 (P2), T011 (P3), and T012 (P5) are the **open** tasks; all are already tracked as remediation items.

## Implementation strategy

This spec is retrospective: the mail queue (US1), abilities (US2), and setup wizard (US3) are already delivered,
unit-tested (4 + 3 + 4 cases), and verified on real WP, with the boot regression fixed (zero-notice boot). The
remaining work is the tracked debts (T010 → P2; T011 → P3; T012 → P5) — **not** new feature work. Full REST/MCP
behaviour and the rendered wizard screen are documented browser follow-ups.

## Parallel opportunities

- The three sub-features touch independent directories — US1/US2/US3 verification can proceed in parallel.
- T010 [P] (guard run) is independent of the code fixes in T011/T012.
