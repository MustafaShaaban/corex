<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Bookings\BookingsServiceProvider;
use Corex\Captcha\CaptchaServiceProvider;
use Corex\Careers\CareersServiceProvider;
use Corex\Email\MailServiceProvider;
use Corex\Kit\KitServiceProvider;
use Corex\Media\MediaServiceProvider;
use Corex\Newsletter\NewsletterServiceProvider;
use Corex\Portfolio\PortfolioServiceProvider;
use Corex\Profile\ProfileServiceProvider;
use Corex\Ui\UiServiceProvider;
use Corex\Woo\WooServiceProvider;

/**
 * The first-party optional provider manifest used by runtime gating.
 */
final class AddonProviderRegistry
{
    /**
     * @return list<AddonProvider>
     */
    public function all(): array
    {
        return [
            new AddonProvider(
                'corex-ui',
                UiServiceProvider::class,
                'corex-ui/corex-ui.php',
            ),
            new AddonProvider(
                'corex-email',
                MailServiceProvider::class,
                'corex-email/corex-email.php',
            ),
            new AddonProvider(
                'corex-captcha',
                CaptchaServiceProvider::class,
                'corex-captcha/corex-captcha.php',
            ),
            new AddonProvider(
                'corex-newsletter',
                NewsletterServiceProvider::class,
                'corex-newsletter/corex-newsletter.php',
            ),
            new AddonProvider(
                'corex-media',
                MediaServiceProvider::class,
                'corex-media/corex-media.php',
            ),
            new AddonProvider(
                'corex-profile',
                ProfileServiceProvider::class,
                'corex-profile/corex-profile.php',
            ),
            new AddonProvider(
                'corex-careers',
                CareersServiceProvider::class,
                'corex-careers/corex-careers.php',
            ),
            new AddonProvider(
                'corex-bookings',
                BookingsServiceProvider::class,
                'corex-bookings/corex-bookings.php',
            ),
            new AddonProvider(
                'corex-kit-company',
                KitServiceProvider::class,
                'corex-kit-company/corex-kit-company.php',
                dependencies: ['corex-ui'],
            ),
            new AddonProvider(
                'corex-kit-portfolio',
                PortfolioServiceProvider::class,
                'corex-kit-portfolio/corex-kit-portfolio.php',
                dependencies: ['corex-ui'],
            ),
            new AddonProvider(
                'corex-kit-woo',
                WooServiceProvider::class,
                'corex-kit-woo/corex-kit-woo.php',
                dependencies: ['corex-ui'],
                featureFlag: 'woocommerce_kit',
                externalGate: 'woocommerce',
            ),
        ];
    }

    public function find(string $slug): ?AddonProvider
    {
        foreach ($this->all() as $provider) {
            if ($provider->slug === $slug) {
                return $provider;
            }
        }

        return null;
    }
}

