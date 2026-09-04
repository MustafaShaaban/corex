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
        $selfGates = $this->selfGate($state);
        $candidates = [];
        $dependencies = [];

        foreach ($this->providers as $provider) {
            $dependencies[$provider->slug] = $provider->dependencies;

            if (($selfGates[$provider->slug] ?? null) === null) {
                $candidates[] = $provider->slug;
            }
        }

        $survivingSlugs = $this->satisfyDependencies($candidates, $dependencies);
        $excludedReasons = [];
        $exclusions = [];
        $scopes = [];

        foreach ($this->providers as $provider) {
            $slug = $provider->slug;
            $scope = $state->scopeOf($slug);
            $scopes[$slug] = $scope;
            $reason = $selfGates[$slug] ?? null;
            $detail = $this->detailFor($provider, $reason);
            $missingDependencies = array_values(array_diff($provider->dependencies, $survivingSlugs));

            if (
                $missingDependencies !== []
                && in_array($reason, [
                    null,
                    AddonBlockReason::FeatureFlagDisabled,
                    AddonBlockReason::ExternalGateUnavailable,
                ], true)
            ) {
                $reason = AddonBlockReason::MissingDependencies;
                $detail = implode(', ', $missingDependencies);
            }

            if ($reason !== null) {
                $excludedReasons[$slug] = $reason->value . ($detail !== '' ? ': ' . $detail : '');
                $exclusions[$slug] = new AddonExclusion(
                    $slug,
                    $reason,
                    $scope,
                    $state->siteId(),
                    $detail,
                );

                continue;
            }

            $providerClasses[] = $provider->providerClass;
        }

        return new AddonProviderResolution($providerClasses, $excludedReasons, $exclusions, $scopes);
    }

    /**
     * Pass one evaluates only gates intrinsic to each provider. Dependencies are
     * deliberately deferred until every viable candidate is known.
     *
     * @return array<string, ?AddonBlockReason>
     */
    private function selfGate(AddonRuntimeState $state): array
    {
        $gates = [];

        foreach ($this->providers as $provider) {
            $gates[$provider->slug] = match (true) {
                ! $state->isInstalled($provider) => AddonBlockReason::NotInstalled,
                ! $state->isActive($provider->slug) => AddonBlockReason::Inactive,
                $provider->featureFlag !== null && ! $state->flagEnabled($provider->featureFlag)
                    => AddonBlockReason::FeatureFlagDisabled,
                $provider->externalGate !== null && ! $state->externalGateOpen($provider->externalGate)
                    => AddonBlockReason::ExternalGateUnavailable,
                default => null,
            };
        }

        return $gates;
    }

    /**
     * Iteratively removes candidates whose dependencies will not load in this request.
     *
     * @param list<string>                $candidates
     * @param array<string, list<string>> $dependencies
     *
     * @return list<string>
     */
    private function satisfyDependencies(array $candidates, array $dependencies): array
    {
        do {
            $survivors = [];

            foreach ($candidates as $slug) {
                if (array_diff($dependencies[$slug] ?? [], $candidates) === []) {
                    $survivors[] = $slug;
                }
            }

            $changed = count($survivors) !== count($candidates);
            $candidates = $survivors;
        } while ($changed);

        return $candidates;
    }

    private function detailFor(AddonProvider $provider, ?AddonBlockReason $reason): string
    {
        return match ($reason) {
            AddonBlockReason::FeatureFlagDisabled => (string) $provider->featureFlag,
            AddonBlockReason::ExternalGateUnavailable => (string) $provider->externalGate,
            default => '',
        };
    }
}
