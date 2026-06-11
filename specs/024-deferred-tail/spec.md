# Feature Specification: Deferred tail — mail queue, Abilities/MCP, setup wizard

**Feature Branch**: `024-deferred-tail`

**Created**: 2026-06-11

**Status**: Draft (RETROSPECTIVE — documents delivered, tested, real-WP-verified code; item 13 of the "Finish Corex" initiative; reconciled to the implementation across corex-email, corex-core, and corex-kit-company)

**Input**: "Close the deferred tail: bulk mail can queue via Action Scheduler when available; Corex exposes read-only, capability-gated AI abilities on WP 7.0; and a setup wizard lets an operator pick a kit, turn on its flags, activate its modules, and seed a starter Home page."

> **Retrospective note.** Written after the code shipped, to restore spec-first compliance (Principle X). It
> closes three deferred sub-features on top of spec 008 (Mail seam), spec 001 (Config/abilities boot hooks),
> and spec 010 (Blueprint). It also records the boot-notice regression fix (DECISIONS #55). Requirements
> describe the existing code.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Bulk mail can queue when a backend exists (Priority: P1)

When a backend (Action Scheduler) is available **and** the operator turns on the mail-queue flag, mail is
enqueued and sent by a worker; otherwise it is sent inline. The `Mailer` seam is unchanged for callers.

**Why this priority**: Bulk sends (newsletter) must not block a request, but queueing must be optional and
never a hard dependency — it degrades gracefully to inline send.

**Independent Test**: Resolve the gate across (backend present?) × (flag on?); confirm enqueue only when both
true, inline otherwise; confirm a `MailRequest` round-trips through the queue payload.

**Acceptance Scenarios**:

1. **Given** Action Scheduler available AND the `mail_queue` flag on, **When** the gate resolves, **Then**
   queueing is enabled; otherwise it is disabled.
2. **Given** queueing enabled, **When** a message is sent, **Then** `QueuedMailer` enqueues a scalar
   `MailRequest` (via `ActionSchedulerDispatcher`) instead of sending inline; a worker later sends it.
3. **Given** queueing disabled, **When** a message is sent, **Then** `QueuedMailer` sends inline (delegates to
   the underlying mailer).
4. **Given** a queued message, **When** the payload round-trips, **Then** the `MailRequest` is reconstructed
   intact.

---

### User Story 2 - Read-only, gated AI abilities on WP 7.0 (Priority: P2)

Corex registers a small set of read-only, capability-gated, REST-exposed "abilities" (list blocks, site info)
on WP 7.0's Abilities/MCP init hooks, guarded so it is a no-op where the API is absent.

**Why this priority**: Forward-looking AI/MCP integration, but it must never fatal on a WP without the API and
must expose nothing privileged.

**Independent Test**: Build the ability data (list `corex/*` blocks with titles; site/framework summary)
headlessly; confirm registration is `function_exists`-guarded and capability-gated.

**Acceptance Scenarios**:

1. **Given** the block registry, **When** `CorexAbilities` lists blocks, **Then** it returns only `corex/*`
   blocks with their titles, falling back to the block name when a title is missing.
2. **Given** the site, **When** `CorexAbilities` summarises it, **Then** it returns a site/framework summary
   (read-only).
3. **Given** the WP 7.0 Abilities API, **When** `AbilitiesProvider` boots, **Then** it registers
   `corex/list-blocks` + `corex/site-info` as read-only, cap-gated, REST-exposed abilities on the API's init
   hooks — `function_exists`-guarded so it is a no-op where the API is absent.

---

### User Story 3 - A setup wizard to compose a site (Priority: P2)

An admin opens a setup wizard, picks a kit, and the wizard plans the work — the de-duped modules to activate
and the feature flags to enable — then (nonce + capability gated) enables those flags, activates the modules,
and seeds a starter Home page.

**Why this priority**: First-run onboarding turns the kits + flags + blueprints into a one-screen "compose my
site" experience.

**Independent Test**: List the registered kits with what each needs; plan a kit into de-duped modules + its
flags; confirm an unknown kit yields an empty plan.

**Acceptance Scenarios**:

1. **Given** the registered kits, **When** the wizard lists them, **Then** each is shown with what it needs
   (modules + flags).
2. **Given** a chosen kit, **When** planned, **Then** the plan is its de-duped modules and its feature flags
   (`Blueprint::featureFlags()`).
3. **Given** an unknown kit, **When** planned, **Then** the plan is empty (non-fatal).
4. **Given** the admin screen, **When** submitted, **Then** it verifies a nonce + `manage_options`, enables the
   flags, activates the modules, and seeds a demo Home page.

### Edge Cases

- Action Scheduler is present transitively via WooCommerce; the queue still requires the flag on.
- The mail worker is registered **lazily** (not at `plugins_loaded`) so the mail stack is not eagerly built
  early — the regression that loaded the `corex` textdomain too soon (DECISIONS #55) is fixed; a normal request
  boots with zero notices.
- Abilities expose nothing privileged and never fatal where the API is missing.
- The wizard's flag/module/seed actions are capability + nonce gated.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A `QueuedMailer` MUST decorate the `Mailer` seam and queue via Action Scheduler only when it is
  available AND the `mail_queue` flag is on (a pure `MailQueueGate`), else send inline — leaving the seam
  unchanged for callers.
- **FR-002**: `ActionSchedulerDispatcher` MUST enqueue a scalar `MailRequest`; a worker MUST send it; the
  `MailRequest` MUST round-trip through the payload intact.
- **FR-003**: The mail worker MUST be registered lazily (not eagerly at `plugins_loaded`) so the mail stack is
  not built early (fixes the textdomain/`headers already sent` regression — DECISIONS #55).
- **FR-004**: `CorexAbilities` MUST produce read-only data — list `corex/*` blocks with titles (name fallback)
  and a site/framework summary — with no class loading of WP internals beyond the block registry.
- **FR-005**: `AbilitiesProvider` MUST register `corex/list-blocks` + `corex/site-info` as read-only,
  capability-gated, REST-exposed abilities on the WP 7.0 Abilities API init hooks, `function_exists`-guarded
  (a no-op where the API is absent).
- **FR-006**: A pure `SetupWizard` MUST expose `kits()` (each with its needs) and `plan(name)` (de-duped
  modules + the kit's feature flags); an unknown kit MUST yield an empty plan.
- **FR-007**: `SetupWizardScreen` MUST be admin-only — verify a nonce + `manage_options` before it enables
  flags, activates modules, and seeds a demo Home page.
- **FR-008**: `Blueprint::featureFlags()` MUST report the flags a kit needs, so the wizard can enable them.

### Key Entities

- **QueuedMailer / MailQueueGate**: the gated decorator over the `Mailer` seam.
- **CorexAbilities / AbilitiesProvider**: the read-only ability data + its gated registration.
- **SetupWizard / SetupWizardScreen**: the pure planner + its admin-gated applier.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Mail is queued only when the backend is present AND the flag is on; inline otherwise (verified
  headlessly); on real WP the `Mailer` resolves to `QueuedMailer`.
- **SC-002**: A `MailRequest` round-trips through the queue payload with no loss.
- **SC-003**: A normal request boots with **zero** errors/notices (the DECISIONS #55 regression is fixed).
- **SC-004**: Abilities register on real WP, expose only read-only data, and never fatal where the API is
  absent.
- **SC-005**: The wizard plans a kit into the correct de-duped modules + flags; an unknown kit yields an empty
  plan; the apply path is nonce + capability gated.

## Assumptions

- Built on spec 008 (Mail seam + `MailRequest`), spec 001 (Config/boot hooks), and spec 010 (Blueprint).
- Action Scheduler is available transitively (WooCommerce) in this environment; queueing still requires the
  flag.
- Full REST/MCP behaviour and the wizard's rendered screen need a browser; the data/planning paths are verified
  headlessly + on real WP.
