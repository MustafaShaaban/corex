<?php

/**
 * @package Corex\Woo
 */

declare(strict_types=1);

namespace Corex\Woo;

defined('ABSPATH') || exit;

use Corex\Foundation\ServiceProvider;
use Corex\Kit\BlueprintRegistry;
use Corex\Support\Config\FeatureFlags;

/**
 * Boots the WooCommerce kit — but only when the gate allows it (WooCommerce active AND
 * the `woocommerce_kit` flag on). When Woo is absent or the flag is off, this provider
 * is a no-op: the framework runs fully without WooCommerce (Principle IX). It contributes
 * the WooBlueprint manifest; the shop templates live in the theme/WooCommerce.
 */
final class WooServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            WooKitGate::class,
            fn (): WooKitGate => new WooKitGate($this->container->make(FeatureFlags::class)),
        );
    }

    public function boot(): void
    {
        $gate = $this->container->make(WooKitGate::class);

        if (! $gate->isEnabled(class_exists('WooCommerce'))) {
            return; // self-disable: Woo inactive or the kit flag is off
        }

        if ($this->container->has(BlueprintRegistry::class)) {
            $this->container->make(BlueprintRegistry::class)->register(
                $this->container->make(WooBlueprint::class),
            );
        }
    }
}
