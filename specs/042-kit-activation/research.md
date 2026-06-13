# Research: Unified Kit Activation

Phase 0 decisions. No `NEEDS CLARIFICATION` remained (the prompt-to-apply model was settled with the user).

## D1 — The cross-plugin seam: a corex-core interface, optionally resolved

**Decision**: Define `Corex\Provisioning\KitProvisioner` in **corex-core**. The kit framework
(corex-kit-company) binds a concrete `BlueprintKitProvisioner` to it in the container. corex-config resolves it
**optionally** (`$container->has(KitProvisioner::class)`); if unbound, no kit prompt shows and the dashboard card
degrades to an actionable empty state.

**Rationale**: corex-config is a core plugin; the kit framework is an add-on. A core→add-on hard dependency
violates Principle IX. An interface in corex-core + optional container resolution lets the two cooperate when
both are active and degrade cleanly when the kit framework is absent. The provisioner returns the spec-041 core
value objects, so there is exactly one representation of a disposition/outcome.

**Alternatives considered**:
- *corex-config references `Corex\Kit\BlueprintActivator` directly*: hard core→addon coupling; breaks if the kit
  addon is deactivated. Rejected.
- *Move the whole kit framework into corex-core*: larger refactor than warranted; the blueprints are add-on
  concerns. The interface seam is the minimal clean cut. Rejected for now.

## D2 — Read-only preview reuses the spec-041 classifier

**Decision**: `preview($kit)` builds the same per-slug signals the activator builds (page exists / is blank /
is kit placeholder) and runs spec 041's `PagePlanner::plan()` — but performs **no writes**. It returns an
`ApplyPreview` (the dispositions + front-page target + required modules/flags).

**Rationale**: Guarantees the preview matches what apply will do (FR-003) because it is literally the same
classifier over the same signals; keeps the preview pure/read-only (FR-002). The only difference from apply is
that apply also writes.

## D3 — Pending vs applied state

**Decision**: Track two option-backed facts per kit: **pending apply** (enabled but not yet applied/dismissed)
and **applied** (apply has run at least once). Enabling a kit add-on sets pending; Apply clears pending + sets
applied + records the outcome; Dismiss ("Not now") clears pending without applying (recallable from the kit row).

**Rationale**: The prompt must persist across page loads and be recallable (spec edge cases), and the dashboard
card needs "which kits are applied." Options reuse the existing kit-tracking storage style (spec 031); no new
store. Applied-ness is also derivable from `corex_kit_seeded_pages` meta, but an explicit per-kit record is
simpler and supports the "applied ✓ / re-apply" UI.

## D4 — Where the prompt and summary render

**Decision**: The prompt renders as a dismissible admin banner shown on Corex admin screens when a kit is
pending, **and** inline on the Add-ons row for that kit; the "what changed" summary renders after Apply on the
same screen. Delivery is server-rendered admin markup (notices/cards), reusing the existing screen conventions —
no new full-screen wizard, no React.

**Rationale**: Matches the spec assumption and Principle VI/V (no new build, token-only admin styles). The Setup
Wizard reuses the identical summary from the shared `ApplyOutcome`, satisfying "one representation" (FR-008).

## D5 — Dashboard "Site status" card data

**Decision**: A pure `SiteStatusCard` view model takes `(appliedKits, submissionCount, frontPageState)` and
renders applied kits, the submission count linking to Corex → Data, and the front-page status (Corex page /
blank / blog index). The corex-config boundary supplies the inputs: applied kits from the provisioner (or empty
when unavailable), the count from the spec-030 submissions reader, and the front-page state from
`get_option('show_on_front'|'page_on_front')` + a blank-content check (reuse spec 041 `PageContent::isBlank`).

**Rationale**: Directly answers "I couldn't find the submissions" (a one-click linked count) and "did enabling
do anything" (applied kits + front-page status), with the judgement pure/testable and the reads at the boundary.
Degrades gracefully: forms inactive → count shows 0/unavailable, never an error (FR-010).

## D6 — Security

**Decision**: Apply and Dismiss are POST actions gated by the shared `AdminGuard` (`authorized()` cap check +
`verifiedPost()` nonce), consistent with AddonsScreen/SetupWizardScreen (Principle VII admin-screen rule). The
preview and the dashboard card are read-only. All dynamic output escaped; all strings i18n.

**Rationale**: No new route lifecycle; admin-screen actions use the one shared admin guard, never hand-rolled
checks (constitution v1.2.1).
