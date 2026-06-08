<?php

/**
 * Unit tests for declarative middleware resolution (spec US3: FR-012, FR-014, FR-015, SC-004).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Http\Middleware\CapabilityMiddleware;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\RejectingMiddleware;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Support\BootLogger;

function authResolver(): MiddlewareResolver
{
    $container = new Container();
    $container->bind('corex.middleware.auth', fn () => fn (?string $param) => new CapabilityMiddleware($param ?? ''));

    return new MiddlewareResolver($container, new BootLogger(debug: false));
}

it('resolves an alias with a parameter and passes the parameter through', function () {
    Functions\when('current_user_can')->alias(fn ($cap) => $cap === 'manage_options');

    $middleware = authResolver()->resolve('auth:manage_options');

    expect($middleware)->toBeInstanceOf(CapabilityMiddleware::class)
        ->and($middleware->process(new Request('GET'), fn (Request $r) => Response::ok())->isOk())->toBeTrue();
});

it('resolves a list of declared middleware names', function () {
    $list = authResolver()->resolveAll(['auth:edit_posts']);

    expect($list)->toHaveCount(1)
        ->and($list[0])->toBeInstanceOf(CapabilityMiddleware::class);
});

it('fails closed for an unknown middleware name (never silently skips)', function () {
    $resolver = new MiddlewareResolver(new Container(), new BootLogger(debug: false));

    $middleware = $resolver->resolve('does-not-exist');

    expect($middleware)->toBeInstanceOf(RejectingMiddleware::class)
        ->and($middleware->process(new Request('GET'), fn (Request $r) => Response::ok('handler'))->isOk())->toBeFalse();
});
