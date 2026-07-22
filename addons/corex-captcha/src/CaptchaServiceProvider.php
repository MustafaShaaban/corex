<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Security\ChallengeVerifier;
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
            TokenReplayGuard::class,
            static fn (): TokenReplayGuard => new TokenReplayGuard(),
        );

        $this->container->singleton(
            CaptchaResolver::class,
            static fn (ContainerInterface $c): CaptchaResolver => new CaptchaResolver(
                $c->make(ConfigInterface::class),
                $c->make(TokenReplayGuard::class),
            ),
        );

        $this->container->singleton(
            Captcha::class,
            static fn (ContainerInterface $c): Captcha => $c->make(CaptchaResolver::class)->resolve(),
        );
        $this->container->singleton(
            ChallengeVerifier::class,
            static fn (ContainerInterface $c): ChallengeVerifier => $c->make(Captcha::class),
        );

        $this->container->singleton(
            CaptchaTestController::class,
            static fn (ContainerInterface $c): CaptchaTestController => new CaptchaTestController($c->make(ConfigInterface::class)),
        );

        $this->container->singleton(
            CaptchaAssetController::class,
            static fn (ContainerInterface $c): CaptchaAssetController => new CaptchaAssetController(
                $c->make(ProtectedFormRegistry::class),
                $c->make(ConfigInterface::class),
            ),
        );
    }

    public function boot(): void
    {
        // The "Test verification" REST action for the Corex settings captcha card (spec 044).
        $this->container->make(CaptchaTestController::class)->register();

        // Its admin-side button (spec 053 US3) — enqueued only on the Corex settings screen.
        add_action('admin_enqueue_scripts', [$this, 'enqueueTestButton']);

        // The v3 client, loaded only on pages carrying a protected form (spec 071 US1). Guarded on
        // the Forms plugin being present — captcha protecting forms is meaningless without it, and
        // this keeps the add-on from fataling on a site that runs captcha but not forms.
        if (class_exists(ProtectedFormRegistry::class)) {
            $this->container->make(CaptchaAssetController::class)->register();
        }
    }

    /**
     * Enqueue the captcha Test-verification button on the Corex settings screen only
     * (Principle VI). Vanilla, depends on the spec-043 runtime; the secret never reaches it.
     */
    public function enqueueTestButton(string $hook): void
    {
        if ($hook !== 'corex_page_corex-settings-config') {
            return;
        }

        $base = dirname(__DIR__) . '/corex-captcha.php';

        wp_enqueue_style(
            'corex-captcha-admin',
            plugins_url('assets/captcha-admin.css', $base),
            ['corex-admin-shell'],
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
