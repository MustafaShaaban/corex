<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Http\Middleware\CapabilityMiddleware;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\NonceMiddleware;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\SanitizeMiddleware;
use Corex\Http\Middleware\ThrottleMiddleware;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;

/**
 * Registers the middleware pipeline, the resolver, and the standard middleware
 * aliases (`nonce`/`auth`/`throttle`/`sanitize`) so routes reference them by name
 * (spec FR-016). Each alias resolves to a factory `fn(?string $parameter): Middleware`.
 */
final class SecurityModule extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            Pipeline::class,
            static fn (ContainerInterface $c): Pipeline => new Pipeline($c->make(BootLogger::class)),
        );

        $this->container->singleton(
            MiddlewareResolver::class,
            static fn (ContainerInterface $c): MiddlewareResolver => new MiddlewareResolver($c, $c->make(BootLogger::class)),
        );

        $this->container->bind(
            'corex.middleware.nonce',
            static fn (): callable => static fn (?string $parameter): NonceMiddleware => new NonceMiddleware(),
        );

        $this->container->bind(
            'corex.middleware.auth',
            static fn (): callable => static fn (?string $parameter): CapabilityMiddleware
                => new CapabilityMiddleware((string) ($parameter ?? '')),
        );

        $this->container->bind(
            'corex.middleware.throttle',
            static fn (ContainerInterface $c): callable => static function (?string $parameter) use ($c): ThrottleMiddleware {
                $config = $c->make(ConfigInterface::class);

                return new ThrottleMiddleware(
                    (int) $config->get('security.throttle.limit', 60),
                    (int) $config->get('security.throttle.window', 60),
                );
            },
        );

        $this->container->bind(
            'corex.middleware.sanitize',
            static fn (): callable => static fn (?string $parameter): SanitizeMiddleware => new SanitizeMiddleware([]),
        );
    }
}
