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
 * Loads the reCAPTCHA v3 client only when the current page actually contains a protected form.
 *
 * Flow blocks render during `the_content`, which runs after `wp_enqueue_scripts`, so the decision
 * of whether any protected form exists is only knowable at footer time. This controller reads the
 * registry the renderer populated: empty registry ⇒ nothing enqueued, so a page with no protected
 * form makes zero requests to Google (FR-001). Only the public site key reaches the browser; the
 * secret never leaves the server (FR-005).
 */
final class CaptchaAssetController
{
    public function __construct(
        private readonly ProtectedFormRegistry $registry,
        private readonly ConfigInterface $config,
    ) {
    }

    public function register(): void
    {
        // Late on wp_footer so every block on the page has had its chance to declare.
        add_action('wp_footer', [$this, 'enqueue'], 20);
    }

    public function enqueue(): void
    {
        if ($this->registry->isEmpty()) {
            return; // no protected form on this page — load nothing
        }

        $driver = (string) $this->config->get('captcha.driver', 'none');
        $siteKey = (string) $this->config->get('captcha.site_key', '');
        if ($driver !== 'recaptcha' || $siteKey === '') {
            return; // provider not configured — the honeypot still guards, but there is nothing to load
        }

        $base = dirname(__DIR__) . '/corex-captcha.php';

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
            plugins_url('assets/corex-captcha-v3.js', $base),
            ['corex-recaptcha-v3-api'],
            COREX_CAPTCHA_VERSION,
            true,
        );

        wp_localize_script('corex-captcha-v3', 'corexCaptchaV3', [
            'siteKey' => $siteKey,
            'forms'   => $this->registry->all(),
            'i18n'    => [
                // Translated server-side and handed to the buildless client, which has no
                // wp-i18n runtime of its own.
                'error' => __('We could not verify your submission. Please try again.', 'corex'),
            ],
        ]);
    }
}
