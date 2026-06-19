<?php

/**
 * Unit tests for spec 055 deployment readiness profiles.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\DeploymentReadinessCheck;
use Corex\Cli\Release\ReadinessFinding;

it('defines every required deployment profile with complete readiness metadata', function () {
    $profiles = DeploymentReadinessCheck::defaultProfiles();
    $names = array_map(static fn ($profile): string => $profile->name, $profiles);

    expect($names)->toEqualCanonicalizing([
        'minimal',
        'standard',
        'full',
        'woo',
        'client-site',
        'shared-host',
        'azure-container',
        'local-docker',
        'wp-env-stable',
        'wp-env-trunk',
    ]);

    foreach ($profiles as $profile) {
        expect($profile->packageShape)->not->toBe('')
            ->and($profile->buildCommands)->not->toBeEmpty()
            ->and($profile->dependencies)->not->toBeEmpty()
            ->and($profile->secrets)->not->toBeEmpty()
            ->and($profile->blockers)->toBeArray();
    }
});

it('reports deployment readiness as environment-gated when any profile has blockers', function () {
    $finding = (new DeploymentReadinessCheck())->evaluate();

    expect($finding->category)->toBe('deployment')
        ->and($finding->status)->toBe(ReadinessFinding::STATUS_ENVIRONMENT_GATED)
        ->and($finding->blocking)->toBeFalse()
        ->and($finding->evidence)->toContain(
            'profile:shared-host',
            'profile:azure-container',
            'profile:local-docker',
            'profile:wp-env-stable',
            'profile:wp-env-trunk',
        )
        ->and($finding->nextAction)->toContain('Verify environment-gated deployment profiles');
});

