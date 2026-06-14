<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\Config\ConfigInterface;

/**
 * Binds the captcha resolver and the configured `Captcha` driver, so consumers
 * inject `Captcha` and the configured provider verifies.
 */
final class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            CaptchaResolver::class,
            static fn (ContainerInterface $c): CaptchaResolver => new CaptchaResolver($c->make(ConfigInterface::class)),
        );

        $this->container->singleton(
            Captcha::class,
            static fn (ContainerInterface $c): Captcha => $c->make(CaptchaResolver::class)->resolve(),
        );

        $this->container->singleton(
            CaptchaTestController::class,
            static fn (ContainerInterface $c): CaptchaTestController => new CaptchaTestController($c->make(ConfigInterface::class)),
        );
    }

    public function boot(): void
    {
        // The "Test verification" REST action for the Corex settings captcha card (spec 044).
        $this->container->make(CaptchaTestController::class)->register();

        // Its admin-side button (spec 053 US3) — enqueued only on the Corex settings screen.
        add_action('admin_enqueue_scripts', [$this, 'enqueueTestButton']);
    }

    /**
     * Enqueue the captcha Test-verification button on the Corex settings screen only
     * (Principle VI). Vanilla, depends on the spec-043 runtime; the secret never reaches it.
     */
    public function enqueueTestButton(string $hook): void
    {
        if ($hook !== 'toplevel_page_corex-settings') {
            return;
        }

        $base = dirname(__DIR__) . '/corex-captcha.php';

        wp_enqueue_style(
            'corex-captcha-admin',
            plugins_url('assets/captcha-admin.css', $base),
            [],
            COREX_CAPTCHA_VERSION,
        );

        wp_enqueue_script(
            'corex-captcha-admin',
            plugins_url('assets/captcha-admin.js', $base),
            ['corex-runtime'],
            COREX_CAPTCHA_VERSION,
            true,
        );

        wp_localize_script('corex-captcha-admin', 'corexCaptcha', [
            'restUrl' => esc_url_raw(rest_url('corex/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
