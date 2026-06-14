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
    }
}
