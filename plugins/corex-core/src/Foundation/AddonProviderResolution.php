<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

/**
 * Provider classes that may boot plus exclusion reasons for skipped add-ons.
 */
final class AddonProviderResolution
{
    /**
     * @param list<class-string<ServiceProvider>> $providerClasses
     * @param array<string, string>               $excludedReasons
     */
    public function __construct(
        private readonly array $providerClasses,
        private readonly array $excludedReasons,
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
}
