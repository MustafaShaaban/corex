<?php

/**
 * Unit tests for the dependency-aware add-on manager (spec 026 US2: FR-004, SC-003). Pure — no WordPress.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Config\Addons\AddonManager;
use Corex\Config\Addons\AddonRegistry;
use Corex\Config\Addons\AddonState;

function manager(): AddonManager
{
    return new AddonManager(new AddonRegistry());
}

it('blocks disabling an add-on an active add-on requires, naming the dependent', function () {
    // corex-kit-company is active and requires corex-ui → corex-ui cannot be disabled.
    $state = new AddonState(activeSlugs: ['corex-ui', 'corex-kit-company']);

    expect(manager()->canDisable('corex-ui', $state))->toBeFalse()
        ->and(manager()->blockingDependents('corex-ui', $state))->toContain('corex-kit-company');
});

it('allows disabling an add-on nothing active depends on', function () {
    $state = new AddonState(activeSlugs: ['corex-ui']);

    expect(manager()->canDisable('corex-ui', $state))->toBeTrue()
        ->and(manager()->blockingDependents('corex-ui', $state))->toBe([]);
});

it('blocks enabling an add-on whose dependency is inactive, naming the missing dep', function () {
    // corex-ui inactive → corex-kit-company cannot be enabled.
    $state = new AddonState(activeSlugs: []);

    expect(manager()->canEnable('corex-kit-company', $state))->toBeFalse()
        ->and(manager()->missingDependencies('corex-kit-company', $state))->toContain('corex-ui');
});

it('allows enabling an add-on whose dependencies are active', function () {
    $state = new AddonState(activeSlugs: ['corex-ui']);

    expect(manager()->canEnable('corex-kit-company', $state))->toBeTrue()
        ->and(manager()->missingDependencies('corex-kit-company', $state))->toBe([]);
});

it('builds a view per add-on carrying state and the block reason', function () {
    $state = new AddonState(activeSlugs: ['corex-ui', 'corex-kit-company']);
    $installed = static fn (string $slug): bool => true;

    $views = manager()->views($state, $installed);
    $ui = array_values(array_filter($views, static fn ($v) => $v->addon->slug === 'corex-ui'))[0];

    expect($ui->active)->toBeTrue()
        ->and($ui->isBlocked())->toBeTrue()
        ->and($ui->blockedReason)->toContain('corex-kit-company');
});
