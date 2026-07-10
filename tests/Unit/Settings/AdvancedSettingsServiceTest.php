<?php

/**
 * Unit tests for the Advanced settings service (spec 068: T203). No WordPress.
 * Contract: diagnostics report real gathered facts; danger actions require an exact typed
 * confirmation; the fail-closed gate denies any mismatch or empty phrase.
 *
 * @package Corex\Tests\Unit\Settings
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\AdvancedSettingsService;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function advancedFacts(array $overrides = []): array
{
    return array_merge([
        'phpVersion'   => '8.3.6',
        'wpVersion'    => '7.0',
        'environment'  => 'production',
        'memoryLimit'  => '256M',
        'addonsActive' => 8,
        'addonsTotal'  => 10,
        'multisite'    => false,
    ], $overrides);
}

it('reports real diagnostics from gathered facts', function () {
    $rows  = (new AdvancedSettingsService())->diagnostics(advancedFacts());
    $byLabel = array_column($rows, 'value', 'label');

    expect($rows)->toHaveCount(6)
        ->and($byLabel['PHP version'])->toBe('8.3.6')
        ->and($byLabel['Environment'])->toBe('production')
        ->and($byLabel['Active add-ons'])->toBe('8 of 10')
        ->and($byLabel['Multisite'])->toBe('No');
});

it('offers danger actions that each name a typed confirmation phrase', function () {
    $actions = (new AdvancedSettingsService())->dangerActions();

    expect(array_column($actions, 'key'))->toBe(['reset-settings', 'reset-kit'])
        ->and(array_filter($actions, static fn (array $a): bool => trim($a['confirmPhrase']) === ''))->toBe([]);
});

it('is fail-closed: proceeds only on an exact typed match', function () {
    $service = new AdvancedSettingsService();

    expect($service->confirms('RESET SETTINGS', 'RESET SETTINGS'))->toBeTrue()
        // Trimming is allowed, but the phrase itself must match exactly (case-sensitive).
        ->and($service->confirms('  RESET SETTINGS  ', 'RESET SETTINGS'))->toBeTrue()
        ->and($service->confirms('reset settings', 'RESET SETTINGS'))->toBeFalse()
        ->and($service->confirms('RESET', 'RESET SETTINGS'))->toBeFalse()
        ->and($service->confirms('', 'RESET SETTINGS'))->toBeFalse()
        // An empty expected phrase can never be satisfied.
        ->and($service->confirms('', ''))->toBeFalse();
});
