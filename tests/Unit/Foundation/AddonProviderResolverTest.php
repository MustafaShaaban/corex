<?php

/**
 * Unit tests for runtime add-on provider resolution (spec 055 T008).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Foundation\AddonProviderResolver;
use Corex\Foundation\AddonRuntimeState;
use Corex\Foundation\CoreServiceProvider;
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
