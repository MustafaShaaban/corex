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
            new Addon(
                'corex-ui',
                'corex-ui/corex-ui.php',
                'Corex UI',
                summary: 'The Corex block library.',
                description: 'Server-rendered, token-styled component blocks the site kits compose with.',
                provides: ['corex/* component blocks (hero, CTA, team, gallery, tabs, stat, testimonial, pricing, accordion)'],
                docsUrl: '/guides/blocks/',
            ),
            new Addon(
                'corex-email',
                'corex-email/corex-email.php',
                'Corex Mail',
                summary: 'Templated transactional email.',
                description: 'A Mailer service and email templates; queues via Action Scheduler when available.',
                provides: ['Mailer service', 'email templates'],
                docsUrl: '/guides/mail/',
            ),
            new Addon(
                'corex-captcha',
                'corex-captcha/corex-captcha.php',
                'Corex Captcha',
                summary: 'Spam protection for Corex forms.',
                description: 'Pluggable captcha drivers; configured under Settings → Captcha, with a Test verification action.',
                provides: ['captcha drivers (honeypot, reCAPTCHA, Turnstile, hCaptcha)', 'POST corex/v1/captcha/test'],
                docsUrl: '/guides/configuration/',
            ),
            new Addon(
                'corex-newsletter',
                'corex-newsletter/corex-newsletter.php',
                'Corex Newsletter',
                summary: 'Newsletter signup and subscriber storage.',
                provides: ['newsletter signup form', 'subscriber storage'],
            ),
            new Addon(
                'corex-media',
                'corex-media/corex-media.php',
                'Corex Media',
                summary: 'Image optimization — WebP on upload + an optimized <picture> helper.',
                description: 'Converts uploads to WebP (original preserved) where the server supports it; degrades gracefully.',
                provides: ['WebP conversion on upload', 'MediaImage <picture> helper', 'image-support health probe'],
                docsUrl: '/guides/media/',
            ),
            new Addon(
                'corex-careers',
                'corex-careers/corex-careers.php',
                'Corex Careers',
                summary: 'Job listings.',
                provides: ['jobs block', 'job listings'],
            ),
            new Addon(
                'corex-bookings',
                'corex-bookings/corex-bookings.php',
                'Corex Bookings',
                summary: 'Booking and appointment requests.',
                provides: ['booking request form', 'booking storage'],
            ),
            new Addon(
                'corex-kit-company',
                'corex-kit-company/corex-kit-company.php',
                'Company Kit',
                requires: ['corex-ui'],
                summary: 'A ready company website.',
                description: 'Composes the UI blocks into company pages, patterns, and a front page.',
                provides: ['company pages + patterns', 'front-page setup'],
            ),
            new Addon(
                'corex-kit-portfolio',
                'corex-kit-portfolio/corex-kit-portfolio.php',
                'Portfolio Kit',
                requires: ['corex-ui'],
                summary: 'A portfolio website.',
                provides: ['corex_project content type', 'projects block', 'portfolio templates'],
            ),
            new Addon(
                'corex-kit-woo',
                'corex-kit-woo/corex-kit-woo.php',
                'WooCommerce Kit',
                flag: 'woocommerce_kit',
                requires: ['corex-ui'],
                summary: 'A WooCommerce storefront kit.',
                description: 'Self-disables unless WooCommerce is active and the WooCommerce kit flag is on.',
                provides: ['storefront templates (reuses Woo blocks)'],
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
}
