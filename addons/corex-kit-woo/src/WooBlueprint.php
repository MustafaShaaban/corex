<?php

/**
 * @package Corex\Woo
 */

declare(strict_types=1);

namespace Corex\Woo;

defined('ABSPATH') || exit;

use Corex\Kit\Blueprint;

/**
 * The WooCommerce store kit: composes the Corex UI patterns with WooCommerce's own
 * shop/product blocks and the theme's templates into a store. WooCommerce (external)
 * is required at runtime but is gated, not hard-coupled — see WooKitGate.
 */
final class WooBlueprint extends Blueprint
{
    public function name(): string
    {
        return 'woocommerce';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['corex-blocks'];
    }

    /**
     * @return list<string>
     */
    public function recommendedModules(): array
    {
        return ['corex-ui', 'corex-forms', 'corex-email'];
    }

    /**
     * @return list<string>
     */
    public function templates(): array
    {
        return ['front-page', 'page', 'index'];
    }

    /**
     * @return list<string>
     */
    public function parts(): array
    {
        return ['header', 'footer'];
    }

    /**
     * @return list<string>
     */
    public function patterns(): array
    {
        return ['corex/hero', 'corex/features', 'corex/cta'];
    }

    /**
     * @return list<string>
     */
    public function featureFlags(): array
    {
        return ['woocommerce_kit'];
    }
}
