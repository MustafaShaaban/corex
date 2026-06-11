<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * The known Corex add-ons and their dependencies — a small, explicit, first-party set.
 * The site kits require the UI block library (mirroring the kit blueprints'
 * requiredModules()); the Woo kit also carries the `woocommerce_kit` feature flag. Pure:
 * the labels are translated at render, not here.
 */
final class AddonRegistry
{
    /**
     * @return list<Addon>
     */
    public function all(): array
    {
        return [
            new Addon('corex-ui', 'corex-ui/corex-ui.php', 'Corex UI'),
            new Addon('corex-email', 'corex-email/corex-email.php', 'Corex Mail'),
            new Addon('corex-captcha', 'corex-captcha/corex-captcha.php', 'Corex Captcha'),
            new Addon('corex-newsletter', 'corex-newsletter/corex-newsletter.php', 'Corex Newsletter'),
            new Addon('corex-careers', 'corex-careers/corex-careers.php', 'Corex Careers'),
            new Addon('corex-bookings', 'corex-bookings/corex-bookings.php', 'Corex Bookings'),
            new Addon('corex-kit-company', 'corex-kit-company/corex-kit-company.php', 'Company Kit', requires: ['corex-ui']),
            new Addon('corex-kit-portfolio', 'corex-kit-portfolio/corex-kit-portfolio.php', 'Portfolio Kit', requires: ['corex-ui']),
            new Addon('corex-kit-woo', 'corex-kit-woo/corex-kit-woo.php', 'WooCommerce Kit', flag: 'woocommerce_kit', requires: ['corex-ui']),
        ];
    }

    public function find(string $slug): ?Addon
    {
        foreach ($this->all() as $addon) {
            if ($addon->slug === $slug) {
                return $addon;
            }
        }

        return null;
    }
}
