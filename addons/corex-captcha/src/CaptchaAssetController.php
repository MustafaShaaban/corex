<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Support\Config\ConfigInterface;

/**
 * Loads the provider's client only when the current page actually contains a protected form.
 *
 * Flow blocks render during `the_content`, which runs after `wp_enqueue_scripts`, so the decision
 * of whether any protected form exists is only knowable at footer time. This controller reads what
 * the page's forms declared: the forms plugin's registry, when that plugin is active, and
 * {@see CaptchaChallenge}, which the account, newsletter and a site's own forms print through (spec
 * 115). Nothing declared ⇒ nothing enqueued, so a page with no protected form makes zero requests to
 * the provider (FR-001). Only the public site key reaches the browser; the secret never leaves the
 * server (FR-005).
 */
final class CaptchaAssetController
{
    /**
     * The provider's own script, asked to render nothing until it is told where and to call
     * CoreX's function when it has loaded.
     */
    private const WIDGET_APIS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=corexCaptchaWidgetReady',
        'hcaptcha'  => 'https://js.hcaptcha.com/1/api.js?render=explicit&onload=corexCaptchaWidgetReady',
    ];

    /** Before `wp_print_footer_scripts`, which WordPress runs on `wp_footer` at 20. */
    public const PRIORITY = 5;

    public function __construct(
        private readonly ?ProtectedFormRegistry $registry,
        private readonly CaptchaChallenge $challenge,
        private readonly ConfigInterface $config,
    ) {
    }

    public function register(): void
    {
        // On wp_footer, once every block on the page has had its chance to declare, and before
        // WordPress prints the footer's scripts at priority 20: a script queued at 20 or later,
        // after the printer, was never printed (spec 115).
        add_action('wp_footer', [$this, 'enqueue'], self::PRIORITY);
    }

    public function enqueue(): void
    {
        if (! $this->anyFormDeclared()) {
            return; // no protected form on this page — load nothing
        }

        $driver  = (string) $this->config->get('captcha.driver', 'none');
        $siteKey = (string) $this->config->get('captcha.site_key', '');
        if ($siteKey === '') {
            return; // provider not configured — the honeypot still guards, but there is nothing to load
        }

        if ($driver === 'recaptcha') {
            $this->enqueueRecaptcha($siteKey);
        } elseif (isset(self::WIDGET_APIS[$driver])) {
            $this->enqueueWidget($driver);
        }
    }

    /**
     * reCAPTCHA v3 shows nothing: its script asks for a token as the visitor submits.
     */
    private function enqueueRecaptcha(string $siteKey): void
    {
        // The provider library. Registered once by handle, so multiple protected forms on one page
        // share a single script tag (FR-008).
        wp_enqueue_script(
            'corex-recaptcha-v3-api',
            'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($siteKey),
            [],
            null,
            true,
        );

        wp_enqueue_script(
            'corex-captcha-v3',
            plugins_url('assets/corex-captcha-v3.js', $this->pluginFile()),
            ['corex-recaptcha-v3-api'],
            COREX_CAPTCHA_VERSION,
            true,
        );

        wp_localize_script('corex-captcha-v3', 'corexCaptchaV3', [
            'siteKey' => $siteKey,
            'i18n'    => [
                // Translated server-side and handed to the buildless client, which has no
                // wp-i18n runtime of its own.
                'error' => __('We could not verify your submission. Please try again.', 'corex'),
            ],
        ]);
    }

    /**
     * Turnstile and hCaptcha show a widget. CoreX's script is loaded first and the provider's
     * after it, because the provider calls CoreX's function by name once it has loaded. The site
     * key is not sent here: each form's challenge place carries it.
     */
    private function enqueueWidget(string $driver): void
    {
        wp_enqueue_script(
            'corex-captcha-widget',
            plugins_url('assets/corex-captcha-widget.js', $this->pluginFile()),
            [],
            COREX_CAPTCHA_VERSION,
            true,
        );

        wp_localize_script('corex-captcha-widget', 'corexCaptchaWidget', [
            'i18n' => [
                'incomplete' => __('Please complete the challenge before sending.', 'corex'),
                'error'      => __('We could not verify your submission. Please try again.', 'corex'),
            ],
        ]);

        wp_enqueue_script(
            'corex-captcha-' . $driver . '-api',
            self::WIDGET_APIS[$driver],
            ['corex-captcha-widget'],
            null,
            true,
        );
    }

    private function anyFormDeclared(): bool
    {
        return $this->challenge->declared()
            || ($this->registry !== null && ! $this->registry->isEmpty());
    }

    private function pluginFile(): string
    {
        return dirname(__DIR__) . '/corex-captcha.php';
    }
}
