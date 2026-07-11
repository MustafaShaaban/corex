<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

use Corex\Foundation\AddonProvider;
use Corex\Foundation\AddonProviderRegistry as RuntimeAddonProviderRegistry;
use RuntimeException;

/**
 * The admin add-on manifest. Runtime metadata comes from corex-core; this class
 * adds labels, summaries, descriptions, and docs links for the config screen.
 */
final class AddonRegistry
{
    public function __construct(private readonly RuntimeAddonProviderRegistry $providers = new RuntimeAddonProviderRegistry())
    {
    }

    /**
     * @return list<Addon>
     */
    public function all(): array
    {
        return [
            $this->addon(
                'corex-ui',
                'Corex UI',
                summary: 'The Corex block library.',
                description: 'Server-rendered, token-styled component blocks the site kits compose with.',
                provides: ['corex/* component blocks (hero, CTA, team, gallery, tabs, stat, testimonial, pricing, accordion)'],
                docsUrl: '/guides/blocks/',
                tier: AddonTier::Recommended,
            ),
            $this->addon(
                'corex-email',
                'Corex Mail',
                summary: 'Templated transactional email.',
                description: 'A Mailer service and email templates; queues via Action Scheduler when available.',
                provides: ['Mailer service', 'email templates'],
                docsUrl: '/guides/mail/',
                tier: AddonTier::Optional,
            ),
            $this->addon(
                'corex-captcha',
                'Corex Captcha',
                summary: 'Spam protection for Corex forms.',
                description: 'Pluggable captcha drivers; configured under Settings -> Captcha, with a Test verification action.',
                provides: ['captcha drivers (honeypot, reCAPTCHA, Turnstile, hCaptcha)', 'POST corex/v1/captcha/test'],
                docsUrl: '/guides/configuration/',
                tier: AddonTier::Optional,
            ),
            $this->addon(
                'corex-newsletter',
                'Corex Newsletter',
                summary: 'Newsletter signup and subscriber storage.',
                provides: ['newsletter signup form', 'subscriber storage'],
                tier: AddonTier::Optional,
            ),
            $this->addon(
                'corex-media',
                'Corex Media',
                summary: 'Image optimization - WebP on upload plus an optimized picture helper.',
                description: 'Converts uploads to WebP (original preserved) where the server supports it; degrades gracefully.',
                provides: ['WebP conversion on upload', 'MediaImage picture helper', 'image-support health probe'],
                docsUrl: '/guides/media/',
                tier: AddonTier::Recommended,
            ),
            $this->addon(
                'corex-profile',
                'Corex Profile',
                summary: 'Front-office accounts — login, registration, recovery, profile, sessions.',
                description: 'Layers login, registration, forgot/reset password, profile editing, notifications, and active-session management over WordPress auth, kept separate from wp-admin. Presentation lives in the theme.',
                provides: ['account blocks (login, register, reset, profile, sessions, notifications)', 'corex/v1/account/* REST'],
                docsUrl: '/guides/profile/',
                tier: AddonTier::Optional,
            ),
            $this->addon(
                'corex-careers',
                'Corex Careers',
                summary: 'Job listings.',
                provides: ['jobs block', 'job listings'],
                tier: AddonTier::Optional,
            ),
            $this->addon(
                'corex-bookings',
                'Corex Bookings',
                summary: 'Booking and appointment requests.',
                provides: ['booking request form', 'booking storage'],
                tier: AddonTier::Optional,
            ),
            $this->addon(
                'corex-kit-company',
                'Company Kit',
                summary: 'A ready company website.',
                description: 'Composes the UI blocks into company pages, patterns, and a front page.',
                provides: ['company pages + patterns', 'front-page setup'],
                docsUrl: '/guides/company-kit/',
                tier: AddonTier::Recommended,
            ),
            $this->addon(
                'corex-kit-portfolio',
                'Portfolio Kit',
                summary: 'A portfolio website.',
                provides: ['corex_project content type', 'projects block', 'portfolio templates'],
                tier: AddonTier::SiteKit,
            ),
            $this->addon(
                'corex-kit-woo',
                'WooCommerce Kit',
                summary: 'A WooCommerce storefront kit.',
                description: 'Self-disables unless WooCommerce is active and the WooCommerce kit flag is on.',
                provides: ['storefront templates (reuses Woo blocks)'],
                tier: AddonTier::RequiresWooCommerce,
            ),
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

    /**
     * @param list<string> $provides
     * @param list<string> $needsKeys
     */
    private function addon(
        string $slug,
        string $label,
        string $summary = '',
        string $description = '',
        array $provides = [],
        array $needsKeys = [],
        string $docsUrl = '',
        ?AddonTier $tier = null,
    ): Addon {
        $provider = $this->provider($slug);

        return new Addon(
            $provider->slug,
            $provider->pluginFile,
            $label,
            flag: $provider->featureFlag,
            requires: $provider->dependencies,
            summary: $summary,
            description: $description,
            provides: $provides,
            needsKeys: $needsKeys,
            docsUrl: $docsUrl,
            tier: $tier,
        );
    }

    private function provider(string $slug): AddonProvider
    {
        return $this->providers->find($slug)
            ?? throw new RuntimeException(sprintf('Missing runtime add-on provider metadata for "%s".', $slug));
    }
}
