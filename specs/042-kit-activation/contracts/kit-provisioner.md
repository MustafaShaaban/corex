# Contract: KitProvisioner seam, Activation view, Site-status card

Internal framework interfaces. No external/REST surface (admin POST actions only).

## `Corex\Provisioning\KitProvisioner` (interface, corex-core)

```php
interface KitProvisioner
{
    /** @return list<KitSummary> kits that declare applicable starter content. */
    public function applicableKits(): array;

    /** Read-only: what applying $kit would do (no writes). */
    public function preview(string $kit): ApplyPreview;

    /** Apply $kit through the single shared apply path; returns the outcome. */
    public function apply(string $kit): ApplyOutcome;

    public function isApplicable(string $kit): bool;   // false → caller shows no prompt
}
```

**Guarantees**
- `preview()` performs no writes and its dispositions match a subsequent `apply()` for the same site state.
- `apply()` is idempotent and routes through spec 041's `BlueprintActivator` (no duplicated seeding).
- corex-config resolves this **optionally**; absence → no prompt + dashboard empty state (Principle IX).

## `Corex\Kit\Provisioning\BlueprintKitProvisioner` (corex-kit-company) implements the interface

- Maps an add-on/kit name → its `Blueprint` (via `BlueprintRegistry` + `SetupWizard::plan`).
- `preview`: builds per-slug signals (exists/empty/placeholder) and runs the core `PagePlanner` — read-only.
- `apply`: calls `BlueprintActivator::apply()`, sets `corex_kit_applied`, clears `corex_kit_pending`.
- Bound `KitProvisioner::class => BlueprintKitProvisioner::class` in `KitServiceProvider`.

## `Corex\Config\Addons\KitActivationView` (pure)

```php
final class KitActivationView
{
    /** Prompt view model from a preview (created/populate/skip rows, front target, modules). */
    public function prompt(ApplyPreview $preview): array;
    /** "What changed" view model from an outcome (created/populated/skipped + front page + links). */
    public function summary(ApplyOutcome $outcome): array;
}
```

## `Corex\Config\Dashboard\SiteStatusCard` (pure)

```php
final class SiteStatusCard
{
    /** @param list<string> $appliedKits */
    public function model(array $appliedKits, int $submissionCount, string $submissionsUrl, string $frontPage): array;
}
```

Returns the `SiteStatus` view model (data-model.md), including `isEmptyState`.

## Admin actions (corex-config, AdminGuard-gated)

- `AddonsScreen` (or a `KitActivationController`): on enabling a kit add-on → add to `corex_kit_pending` and
  render the prompt (from `preview`). POST **Apply** (`verifiedPost`) → `provisioner->apply` → render `summary`.
  POST **Not now** (`verifiedPost`) → remove from pending. Non-kit add-ons: unchanged, no prompt.
- `AdminDashboard`: renders `SiteStatusCard` using the optional provisioner + the spec-030 submissions reader +
  front-page option (degrades gracefully when either is unavailable).

## Test contract (Pest)

- `ApplyPreviewTest` / `BlueprintKitProvisionerTest`: preview is read-only and equals apply's dispositions;
  apply delegates to the shared activator; `applicableKits` lists only page-declaring kits.
- `KitActivationViewTest`: prompt + summary view models (rows, reasons, front target, links).
- `SiteStatusCardTest`: counts, applied kits, the three front-page states, and the empty state.
- `AddonsScreenKitPromptTest`: kit enable → pending + prompt; non-kit enable → none; Apply/Dismiss require
  AdminGuard (cap + nonce); unauthorized → no state change.
- `KitActivationIntegrationTest`: enable→Apply seeds via the SAME path/outcome shape as the Setup Wizard.
