# Implementation Plan: Deferred tail — mail queue, Abilities/MCP, setup wizard (024)

**Branch**: `024-deferred-tail` (uncommitted on `develop`) | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

> Retrospective plan — maps each FR to the file that already satisfies it and flags drift. No new architecture.

## Summary

Three gated, tested sub-features close the deferred tail: (a) a `QueuedMailer` decorating the `Mailer` seam,
queueing via Action Scheduler only when available + flagged, else inline, with a lazily-registered worker that
fixes the early-boot textdomain regression (DECISIONS #55); (b) read-only, cap-gated, `function_exists`-guarded
WP 7.0 Abilities (`corex/list-blocks`, `corex/site-info`); (c) a pure `SetupWizard` planner + an admin-gated
`SetupWizardScreen` that enables flags, activates modules, and seeds a demo Home page.

## Technical Context

**Language/Version**: PHP 8.3. **Primary Dependencies**: spec-008 Mail seam + `MailRequest`; Action Scheduler
(optional, transitive via Woo); WP 7.0 Abilities API (optional); spec-010 Blueprint. **Testing**: Pest (gate
truth table, payload round-trip, ability data, wizard planning). **Project Type**: WP add-on/plugin code.
**Constraints**: every optional backend gated (never a hard dependency); abilities read-only + no-fatal; wizard
apply nonce + capability gated; lazy worker registration (no early mail-stack build).

## Constitution Check (v1.2.0)

- [x] **III/IV (layering + DI)** — PASS. `MailQueueGate`/`CorexAbilities`/`SetupWizard` are pure; `QueuedMailer`
  decorates the seam; all injected. *Partial:* `SetupWizardScreen` does render+apply+activate+seed — an SRP
  split (extract a `BlueprintActivator`) is tracked **P3**; the Principle-VII admin-screen policy is **P5**.
- [x] **VII (security)** — PASS. Wizard apply verifies nonce + `manage_options`; abilities expose only
  read-only data. *Partial:* `SetupWizardScreen` hand-rolls nonce/cap (mirrors AdminDashboard) — the
  declarative-middleware-vs-admin-helper decision is **P5**; one inline-style `1.5rem` token fallback → **P3**.
- [x] **IX (optional dep)** — PASS (exemplary). Mail queue (Action Scheduler), abilities (WP 7.0 API), and the
  wizard's Woo flag are all gated; none is a hard dependency.
- [x] **X (spec)** — reconciled by this retrospective spec.
- [x] **Guard Gate / DoD** — PARTIAL. wp-guard/clean-code self-review at delivery; formal re-run is **P2**.
  Tests: MailQueue 4 + CorexAbilities 3 + SetupWizard 4, green; abilities + QueuedMailer resolution verified on
  real WP; zero-notice boot confirmed.

**Gate**: PASS (P2 guard re-run; P3 SetupWizardScreen SRP + token fix; P5 admin-security policy — all tracked).

## FR → implementation map

| FR | Satisfied by |
|---|---|
| FR-001 gated decorator | `addons/corex-email/src/Queue/{QueuedMailer,MailQueueGate}.php` |
| FR-002 dispatch + round-trip | `addons/corex-email/src/Queue/{ActionSchedulerDispatcher,MailQueueDispatcher}.php`; `Corex\Mail\MailRequest` |
| FR-003 lazy worker (boot fix) | `addons/corex-email/src/MailServiceProvider.php` (lazy worker registration; DECISIONS #55) |
| FR-004 ability data | `plugins/corex-core/src/Abilities/CorexAbilities.php` (list `corex/*` + title fallback; site summary) |
| FR-005 gated registration | `plugins/corex-core/src/Abilities/AbilitiesProvider.php` (`function_exists`-guarded, cap-gated, REST) |
| FR-006 wizard planner | `addons/corex-kit-company/src/SetupWizard.php` (`kits()`, `plan()`, de-dupe) |
| FR-007 gated apply | `addons/corex-kit-company/src/SetupWizardScreen.php` (nonce + `manage_options` → flags/modules/seed) |
| FR-008 blueprint flags | `addons/corex-kit-company/src/Blueprint.php` (`featureFlags()`) |

**Drift found:** none material. Clean-code findings (#2 `SetupWizardScreen` SRP, #3 inline-style token, #4
`AbilitiesProvider::registerAbilities()` ~40-line extract) are tracked under P3; the admin-screen Principle-VII
policy under P5.

## Project Structure (already implemented)

```text
addons/corex-email/src/Queue/{QueuedMailer,MailQueueGate,ActionSchedulerDispatcher,MailQueueDispatcher}.php
addons/corex-email/src/MailServiceProvider.php   (lazy worker)
plugins/corex-core/src/Abilities/{CorexAbilities,AbilitiesProvider}.php
addons/corex-kit-company/src/{SetupWizard,SetupWizardScreen,Blueprint}.php
tests/Unit/{Email/MailQueueTest,Abilities/CorexAbilitiesTest,Kit/SetupWizardTest}.php
```

## Complexity Tracking

| Violation | Why needed | Remediation |
|---|---|---|
| `SetupWizardScreen` does render+apply+activate+seed | shipped as one screen; mirrors AdminDashboard | **P3** — extract `BlueprintActivator` |
| `SetupWizardScreen` hand-rolls nonce/cap (not declarative middleware) | admin-menu screen, not a REST/AJAX route | **P5** — decide admin-screen security policy |
| `AbilitiesProvider::registerAbilities()` ~40 lines | inline per-ability registration | **P3** — extract per-ability registration |
| inline-style `1.5rem` token fallback in `SetupWizardScreen` | quick spacing fallback | **P3** — token-only/class |
