<?php

/**
 * Unit test: the SecurityModule registers the standard middleware aliases so they
 * resolve by name (spec US4: FR-016).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Container\Container;
use Corex\Container\ContainerInterface;
use Corex\Http\Middleware\CapabilityMiddleware;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\NonceMiddleware;
use Corex\Http\Middleware\SanitizeMiddleware;
use Corex\Http\Middleware\ThrottleMiddleware;
use Corex\Security\SecurityModule;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;

it('registers the standard middleware aliases resolvable by name', function () {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(BootLogger::class, new BootLogger(debug: false));
    $container->instance(ConfigInterface::class, new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }

        public function has(string $key): bool
        {
            return false;
        }
    });

    (new SecurityModule($container))->register();
    $resolver = $container->make(MiddlewareResolver::class);

    expect($resolver->resolve('nonce'))->toBeInstanceOf(NonceMiddleware::class)
        ->and($resolver->resolve('auth:edit_posts'))->toBeInstanceOf(CapabilityMiddleware::class)
        ->and($resolver->resolve('throttle'))->toBeInstanceOf(ThrottleMiddleware::class)
        ->and($resolver->resolve('sanitize'))->toBeInstanceOf(SanitizeMiddleware::class);
});
