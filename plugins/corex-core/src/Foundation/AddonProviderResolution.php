<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Multisite\ActivationScope;

/**
 * Provider classes that may boot plus exclusion reasons for skipped add-ons.
 */
final class AddonProviderResolution
{
    /**
     * @param list<class-string<ServiceProvider>> $providerClasses
     * @param array<string, string>               $excludedReasons
     * @param array<string, AddonExclusion>       $exclusions
     * @param array<string, ActivationScope>      $scopes
     */
    public function __construct(
        private readonly array $providerClasses,
        private readonly array $excludedReasons,
        private readonly array $exclusions = [],
        private readonly array $scopes = [],
    ) {
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providerClasses(): array
    {
        return $this->providerClasses;
    }

    public function reasonFor(string $slug): ?string
    {
        return $this->excludedReasons[$slug] ?? null;
    }

    /**
     * @return array<string, AddonExclusion>
     */
    public function exclusions(): array
    {
        return $this->exclusions;
    }

    public function exclusionFor(string $slug): ?AddonExclusion
    {
        return $this->exclusions[$slug] ?? null;
    }

    /**
     * @return array<string, ActivationScope>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}
