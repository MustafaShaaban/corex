<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

/**
 * Resolves optional add-on providers from runtime state before they can register behavior.
 */
final class AddonProviderResolver
{
    /**
     * @param list<AddonProvider> $providers
     */
    public function __construct(private readonly array $providers)
    {
    }

    /**
     * @param list<class-string<ServiceProvider>> $coreProviders
     */
    public function resolve(array $coreProviders, AddonRuntimeState $state): AddonProviderResolution
    {
        $providerClasses = $coreProviders;
        $includedSlugs = [];
        $excludedReasons = [];

        foreach ($this->providers as $provider) {
            $reason = $this->blockedReason($provider, $state, $includedSlugs);

            if ($reason !== null) {
                $excludedReasons[$provider->slug] = $reason;

                continue;
            }

            $includedSlugs[] = $provider->slug;
            $providerClasses[] = $provider->providerClass;
        }

        return new AddonProviderResolution($providerClasses, $excludedReasons);
    }

    /**
     * @param list<string> $includedSlugs
     */
    private function blockedReason(AddonProvider $provider, AddonRuntimeState $state, array $includedSlugs): ?string
    {
        if (! $state->isInstalled($provider)) {
            return 'not installed';
        }

        if (! $state->isActive($provider->slug)) {
            return 'inactive';
        }

        $missingDependencies = array_values(array_diff($provider->dependencies, $includedSlugs));

        if ($missingDependencies !== []) {
            return 'missing dependencies: ' . implode(', ', $missingDependencies);
        }

        if ($provider->featureFlag !== null && ! $state->flagEnabled($provider->featureFlag)) {
            return 'feature flag disabled: ' . $provider->featureFlag;
        }

        if ($provider->externalGate !== null && ! $state->externalGateOpen($provider->externalGate)) {
            return 'external gate unavailable: ' . $provider->externalGate;
        }

        return null;
    }
}
