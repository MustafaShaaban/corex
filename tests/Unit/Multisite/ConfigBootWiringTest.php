<?php

/**
 * Proves the composition root seeds what the config factory resolves.
 *
 * This exists because the failure it catches is invisible. CoreServiceProvider's ConfigInterface
 * factory resolves MultisiteContext and NetworkContext; if nothing seeded them, the first
 * resolution throws BindingResolutionException — and ProviderRepository::bootProvider() catches
 * Throwable and logs, so a framework that cannot read its own configuration boots to a
 * green-looking site with one line in a debug log. The unit suite could not see it either, because
 * every other config test builds the Repository directly instead of going through the provider
 * (spec 100 FR-003).
 *
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Container\ContainerInterface;
use Corex\Container\Exceptions\BindingResolutionException;
use Corex\Foundation\CoreServiceProvider;
use Corex\Multisite\RuntimeContexts;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;

beforeEach(function (): void {
    // CoreServiceProvider::projectRoot() reads this constant with no fallback, unlike Boot's.
    if (! defined('COREX_CORE_PATH')) {
        define('COREX_CORE_PATH', dirname(__DIR__, 3) . '/plugins/corex-core/');
    }

    // RuntimeContexts::detect() guards this with function_exists, but Brain Monkey defines a
    // WordPress function process-wide as soon as any test mocks it — so an unstubbed call here
    // fails depending on which tests ran first. Declare it rather than depend on suite order.
    Functions\when('is_multisite')->justReturn(false);
});

function configWiringContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(BootLogger::class, new BootLogger(debug: false));

    return $container;
}

it('resolves the config engine when the runtime contexts are seeded', function () {
    $container = configWiringContainer();
    RuntimeContexts::detect()->seedInto($container);

    (new CoreServiceProvider($container))->register();

    expect($container->make(ConfigInterface::class))->toBeInstanceOf(ConfigInterface::class);
});

// The negative case is the point: this is exactly what Boot::boot() must prevent, and exactly what
// ProviderRepository would otherwise swallow into a log line.
it('cannot resolve the config engine when the runtime contexts are missing', function () {
    $container = configWiringContainer();

    (new CoreServiceProvider($container))->register();

    expect(fn () => $container->make(ConfigInterface::class))
        ->toThrow(BindingResolutionException::class);
});

it('falls through both network sources to the code defaults on a single-site install', function () {
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $default);

    $container = configWiringContainer();
    RuntimeContexts::detect()->seedInto($container);

    (new CoreServiceProvider($container))->register();
    $config = $container->make(ConfigInterface::class);

    // A key only the code defaults hold, so resolution has to pass both new network sources
    // without either claiming it — the SC-007 guarantee, exercised through the real provider.
    expect($config->get('app.env'))->not->toBeNull();
});
