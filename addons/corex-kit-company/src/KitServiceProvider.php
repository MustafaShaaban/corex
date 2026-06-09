<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

use Corex\Foundation\ServiceProvider;
use Corex\Kit\Company\CompanyBlueprint;

/**
 * Registers the starter-kit Blueprint registry and the Company Website blueprint.
 * The kit's FSE templates/parts live in the theme (the skin); this provider only
 * contributes the discoverable manifest.
 */
final class KitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BlueprintRegistry::class);
    }

    public function boot(): void
    {
        $this->container->make(BlueprintRegistry::class)->register(
            $this->container->make(CompanyBlueprint::class),
        );
    }
}
