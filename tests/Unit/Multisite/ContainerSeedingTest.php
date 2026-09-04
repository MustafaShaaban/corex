<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Corex\Container\Container;
use Corex\Foundation\Application;
use Corex\Foundation\CoreServiceProvider;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkCapabilities;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\PluginActivationInspector;
use Corex\Multisite\RuntimeContexts;
use Corex\Multisite\SingleSiteCapabilities;
use Corex\Multisite\SingleSiteContext;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\SingleSiteNetworkContext;
use Corex\Multisite\SingleSiteScope;
use Corex\Multisite\SiteContext;
use Corex\Multisite\SiteScope;
use Corex\Multisite\WpPluginActivationInspector;

function seedableRuntimeContexts(): RuntimeContexts
{
    return new RuntimeContexts(
        new SingleSiteMultisiteContext(),
        new SingleSiteContext(),
        new SingleSiteNetworkContext(),
        new SingleSiteCapabilities(),
        new SingleSiteScope(),
    );
}

it('seeds every runtime primitive under its interface id', function () {
    $contexts = seedableRuntimeContexts();
    $container = new Container();
    $contexts->seedInto($container);

    expect($container->make(MultisiteContext::class))->toBe($contexts->multisite)
        ->and($container->make(SiteContext::class))->toBe($contexts->site)
        ->and($container->make(NetworkContext::class))->toBe($contexts->network)
        ->and($container->make(NetworkCapabilities::class))->toBe($contexts->capabilities)
        ->and($container->make(SiteScope::class))->toBe($contexts->scope);
});

it('keeps root-seeded primitives after the core provider registers', function () {
    $contexts = seedableRuntimeContexts();
    $container = new Container();
    $inspector = new WpPluginActivationInspector($contexts->multisite);
    $contexts->seedInto($container, $inspector);

    (new CoreServiceProvider($container))->register();

    expect($container->make(MultisiteContext::class))->toBe($contexts->multisite)
        ->and($container->make(SiteContext::class))->toBe($contexts->site)
        ->and($container->make(NetworkContext::class))->toBe($contexts->network)
        ->and($container->make(NetworkCapabilities::class))->toBe($contexts->capabilities)
        ->and($container->make(SiteScope::class))->toBe($contexts->scope)
        ->and($container->make(PluginActivationInspector::class))->toBe($inspector);
});

it('seeds runtime contexts during application boot', function () {
    $contexts = seedableRuntimeContexts();
    $inspector = new WpPluginActivationInspector($contexts->multisite);
    $application = new Application(
        debug: false,
        providers: [],
        contexts: $contexts,
        pluginActivationInspector: $inspector,
    );

    $application->boot();
    $container = $application->container();

    expect($container->make(MultisiteContext::class))->toBe($contexts->multisite)
        ->and($container->make(SiteContext::class))->toBe($contexts->site)
        ->and($container->make(NetworkContext::class))->toBe($contexts->network)
        ->and($container->make(NetworkCapabilities::class))->toBe($contexts->capabilities)
        ->and($container->make(SiteScope::class))->toBe($contexts->scope)
        ->and($container->make(PluginActivationInspector::class))->toBe($inspector);
});

it('keeps the two-argument application constructor and binds no multisite primitive', function () {
    $application = new Application(false, []);

    $application->boot();

    expect($application->isBooted())->toBeTrue()
        ->and($application->container()->has(MultisiteContext::class))->toBeFalse()
        ->and($application->container()->has(SiteContext::class))->toBeFalse()
        ->and($application->container()->has(NetworkContext::class))->toBeFalse()
        ->and($application->container()->has(NetworkCapabilities::class))->toBeFalse()
        ->and($application->container()->has(SiteScope::class))->toBeFalse();
});
