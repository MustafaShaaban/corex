# Feature Specification: Unified Kit Activation — Prompt-to-Apply with a "What Changed" Summary

**Feature Branch**: `feature/042-kit-activation`

**Created**: 2026-06-13

**Status**: Draft

**Input**: User description: "Make enabling and applying a site kit one coherent, transparent flow. Enabling a kit prompts the user to apply its starter content (with a preview), applying runs the shared kit-apply path and reports what changed, and a dashboard card shows what the site's add-ons have actually done."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Enabling a kit prompts me to apply its starter content (Priority: P1)

A site owner enables a site kit (e.g. the Company kit) from the Corex Add-ons screen. The plugin and its feature flag activate, and Corex immediately shows a clear, dismissible banner: "*Company kit is ready — apply its starter content?*" The banner previews exactly what applying will do — which pages it will create, which empty existing pages it will populate, which pages it will leave alone because they already have content, which page becomes the front page, and which modules/flags the kit needs — with **Apply**, **Preview details**, and **Not now** choices. Nothing on the site changes until the owner chooses Apply.

**Why this priority**: This is the core fix for the "enabling does nothing / the framework feels disconnected" problem. Today enabling a kit silently flips a plugin and a flag and produces zero visible change, so users conclude nothing is wired together. Turning enable into an honest, consent-based prompt that shows what will happen restores the connection between "I enabled this" and "here is what it will do" — without surprising anyone by changing content automatically.

**Independent Test**: Enable a kit add-on and confirm a prompt appears naming the kit and listing the pages it will create/populate/skip and the front-page target, with an explicit Apply action; confirm no pages or front-page changes occur until Apply is chosen.

**Acceptance Scenarios**:

1. **Given** a kit add-on is disabled, **When** the owner enables it, **Then** the plugin + flag activate and a prompt appears previewing the kit's pages (create/populate/skip), front-page target, and required modules.
2. **Given** the prompt is shown, **When** the owner chooses **Not now**, **Then** no content is created or changed and the prompt can be recalled later for that kit.
3. **Given** a non-kit add-on (one that declares no pages), **When** it is enabled, **Then** no apply prompt is shown (it simply activates).
4. **Given** the prompt is shown, **When** the owner opens **Preview details**, **Then** the full per-page disposition and front-page/module effects are listed without any change being made (read-only).

---

### User Story 2 - Applying a kit shows me exactly what changed (Priority: P1)

From the prompt, the site owner chooses **Apply**. Corex runs the kit's starter content through the same apply path the Setup Wizard uses, then shows a "**What changed**" summary: pages created, pages populated, pages skipped (and why), the front page that was set, and modules activated — each with a link to view the site or edit the page. The owner can immediately see their site was built and where everything is.

**Why this priority**: The other half of restoring "connection" is feedback. An apply that completes silently is almost as confusing as one that does nothing. A concrete summary with links turns the kit from a black box into an obviously-working action, and it is the single representation reused by both entry points (prompt and Setup Wizard).

**Independent Test**: Apply a kit and confirm the resulting summary accurately lists every page created/populated/skipped, the front-page assignment, and activated modules, with working links — and that re-applying produces a summary showing nothing duplicated.

**Acceptance Scenarios**:

1. **Given** the apply prompt for a kit with a home, about, and contact page on a fresh site, **When** the owner chooses Apply, **Then** the site front page renders the kit's home and the summary lists 3 pages created + the front page set + modules activated, with view/edit links.
2. **Given** a kit was already applied, **When** the owner applies it again, **Then** the summary reports pages as already-present/skipped, nothing is duplicated, and no existing content is overwritten.
3. **Given** the Setup Wizard apply for the same kit, **When** it runs, **Then** it produces the same per-page dispositions and summary representation as the Add-ons prompt (one shared apply + summary, no divergent behavior).
4. **Given** a page at a kit slug already has user content, **When** the kit is applied, **Then** the summary lists that page as skipped with the reason "existing content preserved."

---

### User Story 3 - A dashboard card shows what my add-ons actually did (Priority: P2)

A site owner opens the Corex dashboard. A "Site status" card shows which kits are currently applied, the live count of contact-form submissions (linked to Corex → Data), and the current front-page status (set to a Corex page / a blank page / the blog index). At a glance they can confirm their enabled add-ons produced real results and find their data.

**Why this priority**: Persistent visibility prevents the original confusion from recurring after the one-time prompt is dismissed. It directly answers "I couldn't find the contact submissions" (a linked live count) and "did enabling anything do something" (applied-kits + front-page status). It depends on the apply tracking from Stories 1–2 but is independently valuable and lower-urgency than the apply flow itself.

**Independent Test**: With submissions present and a kit applied, open the dashboard and confirm the card shows the correct submission count linking to the Data screen, the applied kit(s), and the front-page status; with none applied and no submissions, confirm it shows an actionable empty state.

**Acceptance Scenarios**:

1. **Given** 34 contact submissions exist, **When** the owner views the dashboard, **Then** the card shows "34 submissions" linking to Corex → Data.
2. **Given** the Company kit is applied and its home is the front page, **When** the owner views the dashboard, **Then** the card lists the Company kit as applied and the front page as a Corex page.
3. **Given** no kit is applied and the front page is blank or the blog index, **When** the owner views the dashboard, **Then** the card shows an actionable state pointing to the Add-ons screen to enable a kit.

---

### Edge Cases

- **Apply prompt across page loads**: the prompt persists until the owner applies or dismisses it (it is not lost on navigation), and a dismissed prompt for an enabled-but-unapplied kit can be recalled.
- **Required module missing/cannot activate**: the preview names the missing dependency and the apply either activates it or reports it could not, rather than partially applying silently (consistent with the Add-on Manager's existing dependency rules).
- **Multiple kits enabled before applying**: each enabled-but-unapplied kit has its own prompt/summary; applying one does not implicitly apply another.
- **Apply with no permission / stale nonce**: a user lacking the capability never sees apply controls; an expired/invalid request is rejected with no change.
- **Front-page conflict**: when the kit's home page is skipped (real user content), the summary states the front page was left as the user's existing choice (no override) — consistent with spec 041.
- **Submission count when the forms add-on is disabled**: the dashboard card shows the count as zero/unavailable gracefully, never an error.
- **Kit disabled after applying**: disabling the add-on does not delete the applied pages (content removal stays the reset path, spec 025/041); the dashboard card reflects the kit as no-longer-active.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Enabling an add-on that declares starter content (a kit) MUST, after activating the plugin + feature flag, surface an apply prompt that names the kit and offers Apply / Preview details / Not now; enabling an add-on that declares no starter content MUST NOT show a prompt.
- **FR-002**: The apply prompt and its preview MUST be a **read-only** computation over the kit's declared content and the current site state — it MUST make no changes (no pages, no options, no front-page change) until the owner chooses Apply.
- **FR-003**: The preview MUST list, per declared page, its disposition (will create / will populate an empty existing page / will skip because it has user content), the page that will become the front page, and the modules/flags the kit requires — using the same classification rules as the kit-apply path (spec 041).
- **FR-004**: Choosing Apply MUST run the kit's starter content through a **single shared apply path** also used by the Setup Wizard — there MUST be exactly one place that seeds/populates pages and sets the front page (no duplicated seeding logic across the Add-ons screen and the wizard).
- **FR-005**: After Apply, the system MUST present a "what changed" summary listing pages created, pages populated, pages skipped (with reason), the front page set, and modules activated, with links to view the site and edit each affected page.
- **FR-006**: Apply MUST be idempotent and MUST reuse the create/adopt/skip and reset-tracking rules from spec 041 — re-applying never duplicates pages and never overwrites content present at apply time.
- **FR-007**: All apply controls and the apply action MUST be capability-gated and protected against forged requests via the shared admin security helper (Principle VII admin-screen rule); a user without permission MUST NOT see or be able to trigger apply, and an invalid/expired request MUST be rejected with no change.
- **FR-008**: The "what changed" summary and the preview MUST share one representation of a per-page disposition and the overall apply outcome, so the prompt's preview and the post-apply summary describe the same things consistently.
- **FR-009**: The Corex dashboard MUST show a "Site status" card with: the kits currently applied, the live count of contact-form submissions linking to the Data screen, and the current front-page status (Corex page / blank / blog index); when nothing is applied or no submissions exist it MUST show an actionable empty state rather than appearing broken.
- **FR-010**: The submission count and applied-kit/front-page status MUST be derived from live site state at view time (not a stale cached flag), and MUST degrade gracefully (show zero/unavailable, never an error) when a contributing add-on is inactive.
- **FR-011**: All new user-facing text MUST be translation-ready and rendered with correct escaping and logical (RTL-safe) layout; the dashboard card and prompt MUST meet the same accessibility bar as the existing admin screens.
- **FR-012**: The feature MUST reuse the existing kit blueprints (Company, Portfolio) without changing them, MUST NOT change kit patterns/blocks or the block engine, MUST NOT auto-apply content on enable (apply is always user-initiated), and MUST introduce no new runtime or build dependency (the dashboard card and prompt are server-rendered like the existing admin screens, no new client build).

### Key Entities *(include if feature involves data)*

- **Apply preview**: the read-only, pre-apply description of what applying a kit would do — per-page dispositions, front-page target, required modules/flags — computed from the blueprint + current site state.
- **Page disposition**: one page's outcome (create / populate-existing-empty / skip-user-content), shared by the preview and the post-apply summary; extends the spec-041 classification with display details (title, slug, resulting link).
- **Apply summary**: the post-apply outcome — pages created/populated/skipped (+reasons), front page set, modules activated — the same shape as the preview, now reflecting what actually happened.
- **Pending apply (per kit)**: the state that a kit is enabled but not yet applied, so the prompt can persist and be recalled until the owner applies or dismisses it.
- **Site status**: the dashboard card's data — applied kits, submission count (+ link), front-page status — derived live.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After enabling a kit, 100% of the time the owner sees a prompt that accurately previews the pages to be created/populated/skipped and the front-page target, with zero content changes made before Apply is chosen.
- **SC-002**: After choosing Apply on a fresh site, the front page renders the kit's home and the summary lists every affected page with a correct disposition, in one screen, with working view/edit links.
- **SC-003**: The Setup Wizard apply and the Add-ons-prompt apply produce identical per-page dispositions and an identical summary representation for the same kit and site state (one shared path, verified by test).
- **SC-004**: Re-applying a kit any number of times creates 0 duplicate pages and overwrites 0 pieces of pre-existing content, and the summary reflects this.
- **SC-005**: From the dashboard, an owner can find their contact submissions in one click via the card's count link, and correctly see which kits are applied and the front-page status, in 100% of tested states (including the empty state).
- **SC-006**: An apply attempt without the required capability or with an invalid request results in 0 changes.
- **SC-007**: No new runtime or build dependency is introduced; no kit pattern or block is modified.

## Assumptions

- A kit is any add-on whose blueprint declares starter pages (and/or feature flags + required modules); this is how the system distinguishes "apply-able" kits from plain add-ons, with no per-kit configuration.
- The prompt is delivered through the standard admin notice/banner surface and a kit/Add-ons screen control (server-rendered), not a new full-screen wizard; "Preview details" expands the same read-only computation.
- "Pending apply" state persists via existing option/meta storage already used for kit tracking (spec 031), extended to record enabled-but-unapplied and applied kits; no new storage system.
- The shared apply path is the spec-041 `Corex\Kit` apply/seed service; this feature wires both the Setup Wizard and the Add-ons prompt to it and adds the read-only preview computation in front of it.
- The dashboard card lives on the existing Corex dashboard screen and reuses its rendering/escaping conventions and the shared admin security helper (`AdminGuard`); the submission count reads the existing submissions data source used by Corex → Data (spec 030).
- This feature **depends on spec 041** (create/adopt/skip page rules + reset tracking) being implemented first; the preview and apply describe and reuse those rules.
- The richer performance/readiness insights remain in the separate Insights dashboard (spec 037); this card is a lightweight status summary, not an analytics surface.
