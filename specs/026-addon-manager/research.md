# Research: Add-on manager admin screen (026)

## R1 — What happens on a dependency conflict

**Decision**: **Refuse + explain**, never silently cascade. Disabling an add-on that an active add-on requires
is refused, naming the dependent(s). Enabling an add-on whose required dependency is inactive is refused,
naming the missing dependency (the admin enables it first). The rendered list shows the reason on each blocked
add-on so the constraint is visible before acting.

**Rationale**: Silent cascades (auto-activating dependencies, or auto-disabling dependents) cause surprise
side effects — exactly what a careful admin tool must avoid. Deterministic refusal with a clear reason keeps
the admin in control and the state always consistent. The rules are pure and unit-testable.

**Alternatives considered**: auto-enable missing dependencies on enable (rejected — surprise activation of
plugins the admin didn't choose); auto-disable dependents on disable (rejected — could take a live kit down
without warning).

## R2 — Keeping the plugin activation and the feature flag in sync

**Decision**: A single toggle drives both: enable → `activate_plugins(file)` + (if the add-on has a flag)
`update_option('corex_features_<flag>', '1')`; disable → `deactivate_plugins(file)` + (if a flag)
`delete_option('corex_features_<flag>')`. Add-ons without a flag toggle only the plugin.

**Rationale**: The setup wizard already enables flags + activates modules together (spec 024); this screen is
the granular, reversible companion. Most add-ons are gated purely by plugin activation; only the Woo kit
carries a flag (`woocommerce_kit`). Modelling the flag as optional keeps the common case simple.

**Alternatives considered**: a separate flag toggle (rejected — the input asks for them together; two controls
for one concept invites drift).

## R3 — Where the screen lives and how it is gated

**Decision**: A submenu under the existing `corex-settings` menu, implemented in `corex-config` alongside
`AdminDashboard` + `SetupWizardScreen`, gated by the shared `Corex\Security\Admin\AdminGuard` (cap + nonce per
DECISIONS #58). Output escaped, strings translation-ready, WP admin `.card`/logical-CSS layout (RTL-correct).

**Rationale**: Consistency — three Corex admin screens (settings, setup wizard, add-ons) share the same menu,
the same guard, and the same rendering discipline. No new security pattern.

**Alternatives considered**: a top-level menu (rejected — clutters wp-admin; it belongs under Corex);
hand-rolling the nonce/cap (rejected — that is exactly what AdminGuard exists to prevent).

## R4 — The registry source of truth

**Decision**: A hand-maintained pure `AddonRegistry` enumerating the Corex add-ons with their plugin files,
optional flags, and `requires` edges (the kits require `corex-ui`, mirroring the blueprints). The screen reads
live active/flag state from WP into an `AddonState` and feeds it to the pure manager.

**Rationale**: The add-on set is a small, known, framework-level fact; a registry is deterministic and
testable. Dependencies mirror the existing blueprint `requiredModules()` relationships, so the two stay
conceptually aligned. The manager never reads WP — it acts only on the `AddonState` it is given.

**Alternatives considered**: deriving dependencies from each plugin's headers at runtime (rejected — more
moving parts, WP-coupled, harder to test; a small explicit registry is clearer for a fixed first-party set).
