<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * How an add-on relates to building a normal company site — the guidance a developer needs
 * before deciding what to enable (spec: company-site readiness). It is advisory, not a runtime
 * gate: the truthful {@see \Corex\Foundation\AddonStatus} model still decides what is installed,
 * active, or dependency-blocked. The required *foundation* plugins (corex-core, corex-blocks,
 * corex-config, corex-forms) are not toggleable add-ons and so never carry a tier.
 */
enum AddonTier: string
{
    case Recommended = 'recommended';
    case Optional = 'optional';
    case SiteKit = 'site_kit';
    case RequiresWooCommerce = 'requires_woocommerce';

    /**
     * A short, translated badge label. Calls the WordPress i18n function at call time so the
     * enum itself stays a pure value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recommended         => __('Recommended for company sites', 'corex'),
            self::Optional            => __('Optional', 'corex'),
            self::SiteKit             => __('Site kit', 'corex'),
            self::RequiresWooCommerce => __('Requires WooCommerce', 'corex'),
        };
    }
}
