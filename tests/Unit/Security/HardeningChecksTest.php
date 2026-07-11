<?php

/**
 * Unit tests for the pure Security Center hardening checks (spec 063, Phase 4). No WordPress.
 * Contract: each row reflects a REAL verified fact; pass/warn is truthful; nothing invented.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\HardeningChecks;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/**
 * @return array{ssl:bool,fileEditDisabled:bool,debugDisplayOff:bool,defaultAdminAbsent:bool,indexingAllowed:bool,authSaltsConfigured:bool}
 */
function hardeningFacts(array $overrides = []): array
{
    return array_merge([
        'ssl'                => true,
        'fileEditDisabled'   => true,
        'debugDisplayOff'    => true,
        'defaultAdminAbsent' => true,
        'indexingAllowed'    => true,
        'authSaltsConfigured' => true,
    ], $overrides);
}

it('passes every check on a fully hardened install', function () {
    $checks = (new HardeningChecks())->checks(hardeningFacts());

    expect($checks)->toHaveCount(6)
        ->and(array_column($checks, 'status'))->each->toBe(HardeningChecks::PASS);
});

it('warns on the exact check whose fact is false — never a fabricated pass', function () {
    $checks = (new HardeningChecks())->checks(hardeningFacts(['ssl' => false]));
    $ssl    = array_values(array_filter($checks, static fn (array $c): bool => $c['key'] === 'ssl'))[0];
    $others = array_filter($checks, static fn (array $c): bool => $c['key'] !== 'ssl');

    expect($ssl['status'])->toBe(HardeningChecks::WARN)
        ->and(array_column($others, 'status'))->each->toBe(HardeningChecks::PASS);
});

it('counts the warnings truthfully', function () {
    $engine = new HardeningChecks();

    expect($engine->warnings($engine->checks(hardeningFacts())))->toBe(0)
        ->and($engine->warnings($engine->checks(hardeningFacts(['ssl' => false, 'defaultAdminAbsent' => false]))))->toBe(2);
});

it('gives a warning check an actionable remediation detail', function () {
    $checks    = (new HardeningChecks())->checks(hardeningFacts(['fileEditDisabled' => false]));
    $fileEdit  = array_values(array_filter($checks, static fn (array $c): bool => $c['key'] === 'file_edit'))[0];

    expect($fileEdit['status'])->toBe(HardeningChecks::WARN)
        ->and($fileEdit['detail'])->toContain('DISALLOW_FILE_EDIT');
});

it('checks production indexing and authentication salts as explicit launch hardening signals', function () {
    $checks = (new HardeningChecks())->checks(hardeningFacts([
        'indexingAllowed' => false,
        'authSaltsConfigured' => false,
    ]));
    $byKey = array_column($checks, null, 'key');

    expect($byKey['search_indexing']['status'])->toBe(HardeningChecks::WARN)
        ->and($byKey['search_indexing']['detail'])->toContain('Search Engine Visibility')
        ->and($byKey['auth_salts']['status'])->toBe(HardeningChecks::WARN)
        ->and($byKey['auth_salts']['detail'])->toContain('authentication unique keys and salts');
});
