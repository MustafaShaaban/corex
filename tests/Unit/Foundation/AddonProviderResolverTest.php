<?php

/**
 * Unit tests for runtime add-on provider resolution (spec 055 T008).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Foundation\AddonProvider;
use Corex\Foundation\AddonProviderResolver;
use Corex\Foundation\AddonRuntimeState;
use Corex\Foundation\CoreServiceProvider;
use Corex\Kit\KitServiceProvider;
use Corex\Multisite\ActivationScope;
use Corex\Tests\Unit\Foundation\AddonProviderFixtures;
use Corex\Ui\UiServiceProvider;

it('includes active providers and keeps core providers first', function () {
    $resolver = new AddonProviderResolver([
        AddonProviderFixtures::active(),
        AddonProviderFixtures::inactive(),
    ]);

    $resolution = $resolver->resolve(
        [CoreServiceProvider::class],
        new AddonRuntimeState(
            activeSlugs: ['corex-ui'],
            installedPluginFiles: ['corex-ui/corex-ui.php', 'corex-captcha/corex-captcha.php'],
        ),
    );

    expect($resolution->providerClasses())->toBe([CoreServiceProvider::class, UiServiceProvider::class])
        ->and($resolution->reasonFor('corex-captcha'))->toBe('inactive');
});

it('excludes not-installed and dependency-missing providers with reasons', function () {
    $resolver = new AddonProviderResolver([
        AddonProviderFixtures::dependencyMissing(),
        AddonProviderFixtures::notInstalled(),
    ]);

    $resolution = $resolver->resolve(
        [CoreServiceProvider::class],
        new AddonRuntimeState(
            activeSlugs: ['corex-kit-company', 'corex-careers'],
            installedPluginFiles: ['corex-kit-company/corex-kit-company.php'],
        ),
    );

    expect($resolution->providerClasses())->toBe([CoreServiceProvider::class])
        ->and($resolution->reasonFor('corex-kit-company'))->toBe('missing dependencies: corex-ui')
        ->and($resolution->reasonFor('corex-careers'))->toBe('not installed');
});

it('resolves a network-activated add-on provider', function () {
    $resolver = new AddonProviderResolver([AddonProviderFixtures::active()]);
    $resolution = $resolver->resolve(
        [CoreServiceProvider::class],
        new AddonRuntimeState(
            installedPluginFiles: ['corex-ui/corex-ui.php'],
            activationScopes: ['corex-ui' => ActivationScope::Network],
        ),
    );

    expect($resolution->providerClasses())->toContain(UiServiceProvider::class)
        ->and($resolution->scopes()['corex-ui'])->toBe(ActivationScope::Network)
        ->and($resolution->reasonFor('corex-ui'))->toBeNull();
});

it('resolves dependencies independently of registry declaration order', function () {
    $dependent = AddonProviderFixtures::dependencyMissing();
    $dependency = AddonProviderFixtures::active();
    $state = new AddonRuntimeState(
        installedPluginFiles: [$dependent->pluginFile, $dependency->pluginFile],
        activationScopes: [
            $dependent->slug => ActivationScope::Site,
            $dependency->slug => ActivationScope::Network,
        ],
    );

    $forward = (new AddonProviderResolver([$dependency, $dependent]))->resolve([], $state);
    $reverse = (new AddonProviderResolver([$dependent, $dependency]))->resolve([], $state);
    $forwardClasses = $forward->providerClasses();
    $reverseClasses = $reverse->providerClasses();
    sort($forwardClasses);
    sort($reverseClasses);

    expect($forwardClasses)->toBe($reverseClasses)
        ->and($reverseClasses)->toContain(UiServiceProvider::class, KitServiceProvider::class)
        ->and($forward->exclusions())->toBe([])
        ->and($reverse->exclusions())->toBe([]);
});

it('lets a network-active dependency satisfy a site-active dependent', function () {
    $dependent = AddonProviderFixtures::dependencyMissing();
    $dependency = AddonProviderFixtures::active();
    $resolution = (new AddonProviderResolver([$dependent, $dependency]))->resolve(
        [],
        new AddonRuntimeState(
            installedPluginFiles: [$dependent->pluginFile, $dependency->pluginFile],
            activationScopes: [
                $dependent->slug => ActivationScope::Site,
                $dependency->slug => ActivationScope::Network,
            ],
            siteId: 8,
        ),
    );

    expect($resolution->providerClasses())->toContain(KitServiceProvider::class, UiServiceProvider::class)
        ->and($resolution->reasonFor($dependent->slug))->toBeNull();
});

it('drops transitive dependency failures to a fixpoint', function () {
    $leaf = new AddonProvider(
        'corex-middle',
        UiServiceProvider::class,
        'corex-middle/corex-middle.php',
        dependencies: ['corex-missing'],
    );
    $root = new AddonProvider(
        'corex-root',
        KitServiceProvider::class,
        'corex-root/corex-root.php',
        dependencies: ['corex-middle'],
    );
    $resolution = (new AddonProviderResolver([$root, $leaf]))->resolve(
        [],
        new AddonRuntimeState(
            installedPluginFiles: [$root->pluginFile, $leaf->pluginFile],
            activationScopes: [
                $root->slug => ActivationScope::Site,
                $leaf->slug => ActivationScope::Site,
            ],
        ),
    );

    expect($resolution->providerClasses())->toBe([])
        ->and($resolution->reasonFor('corex-middle'))->toBe('missing dependencies: corex-missing')
        ->and($resolution->reasonFor('corex-root'))->toBe('missing dependencies: corex-middle');
});

it('reports exclusion diagnostics with the activation scope and site id', function () {
    Functions\when('__')->returnArg();

    $provider = new AddonProvider(
        slug: 'corex-kit-company',
        providerClass: KitServiceProvider::class,
        pluginFile: 'corex-kit-company/corex-kit-company.php',
        dependencies: ['corex-ui'],
    );
    $resolution = (new AddonProviderResolver([$provider]))->resolve(
        [],
        new AddonRuntimeState(
            installedPluginFiles: [$provider->pluginFile],
            activationScopes: [$provider->slug => ActivationScope::Network],
            siteId: 42,
        ),
    );
    $exclusion = $resolution->exclusionFor($provider->slug);

    expect($resolution->reasonFor($provider->slug))->toBe('missing dependencies: corex-ui')
        ->and($exclusion)->not->toBeNull()
        ->and($exclusion?->message())->toContain('Network', '42', 'corex-ui');
});
