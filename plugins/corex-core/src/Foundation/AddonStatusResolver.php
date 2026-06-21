<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

/**
 * Resolves a CoreX add-on to its single truthful display {@see AddonStatus} for the
 * admin (Spec 060 / M6). Pure and headless: it reads only the constructed
 * {@see AddonProvider} descriptor and the {@see AddonRuntimeState} snapshot — no
 * WordPress calls, no database — so it is fully unit-tested.
 *
 * Order (first match wins), aligned with the boot-time gating in
 * {@see AddonProviderResolver}: pro_required → not_installed → inactive →
 * dependency_missing → feature_off → woocommerce_missing → active.
 */
final class AddonStatusResolver
{
    /**
     * @param list<string> $satisfiedSlugs add-on slugs already resolved/active (for dependency checks)
     */
    public function resolve(
        AddonProvider $provider,
        AddonRuntimeState $state,
        array $satisfiedSlugs = [],
        bool $proRequired = false,
    ): AddonStatus {
        // A future/commercial add-on shows the disabled Pro indicator regardless of
        // install state, and is never actionable from the admin.
        if ($proRequired) {
            return AddonStatus::ProRequired;
        }

        if (! $state->isInstalled($provider)) {
            return AddonStatus::NotInstalled;
        }

        if (! $state->isActive($provider->slug)) {
            return AddonStatus::Inactive;
        }

        $missingDependencies = array_diff($provider->dependencies, $satisfiedSlugs);
        if ($missingDependencies !== []) {
            return AddonStatus::DependencyMissing;
        }

        if ($provider->hasFeatureFlag() && ! $state->flagEnabled((string) $provider->featureFlag)) {
            return AddonStatus::FeatureOff;
        }

        // WooCommerce is the only external gate in the system; a closed gate means it
        // is unavailable.
        if ($provider->hasExternalGate() && ! $state->externalGateOpen((string) $provider->externalGate)) {
            return AddonStatus::WoocommerceMissing;
        }

        return AddonStatus::Active;
    }
}
