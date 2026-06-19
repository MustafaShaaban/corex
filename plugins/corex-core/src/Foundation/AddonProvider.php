<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

/**
 * Metadata for a first-party optional add-on service provider.
 *
 * This is the boot-time contract used before an optional provider can register
 * hooks, routes, blocks, admin screens, assets, tables, migrations, or cron.
 */
final class AddonProvider
{
    /**
     * @param class-string<ServiceProvider> $providerClass
     * @param list<string> $dependencies add-on slugs this provider requires
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $providerClass,
        public readonly string $pluginFile,
        public readonly array $dependencies = [],
        public readonly ?string $featureFlag = null,
        public readonly ?string $externalGate = null,
    ) {
    }

    public function hasFeatureFlag(): bool
    {
        return $this->featureFlag !== null && $this->featureFlag !== '';
    }

    public function hasExternalGate(): bool
    {
        return $this->externalGate !== null && $this->externalGate !== '';
    }
}

