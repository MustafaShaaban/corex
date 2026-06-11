<?php

/**
 * @package Corex\Woo
 */

declare(strict_types=1);

namespace Corex\Woo;

defined('ABSPATH') || exit;

use Corex\Support\Config\FeatureFlags;

/**
 * The pure enable/disable decision for the WooCommerce kit: it runs only when
 * WooCommerce is active AND the `woocommerce_kit` feature flag is on. Keeping the
 * decision in one testable method makes the "never a hard dependency" guarantee
 * (Principle IX) explicit and verifiable headlessly — `class_exists('WooCommerce')`
 * is passed in, not called here.
 */
final class WooKitGate
{
    public function __construct(private readonly FeatureFlags $flags)
    {
    }

    public function isEnabled(bool $wooActive): bool
    {
        return $wooActive && $this->flags->enabled('woocommerce_kit');
    }
}
