<?php

/**
 * Unit tests for the rich add-on manifest (spec 044: US4, FR-014).
 *
 * @package Corex\Tests\Unit\Addons
 */

declare(strict_types=1);

use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonRegistry;

it('exposes the rich manifest fields', function () {
    $ui = (new AddonRegistry())->find('corex-ui');

    expect($ui)->not->toBeNull()
        ->and($ui->summary)->not->toBe('')
        ->and($ui->provides)->not->toBeEmpty()
        ->and($ui->docsUrl)->not->toBe('');
});

it('defaults the manifest fields safely for a registration that omits them', function () {
    $addon = new Addon('x', 'x/x.php', 'X');

    expect($addon->summary)->toBe('')
        ->and($addon->description)->toBe('')
        ->and($addon->provides)->toBe([])
        ->and($addon->needsKeys)->toBe([])
        ->and($addon->docsUrl)->toBe('')
        ->and($addon->needsConfiguration())->toBeFalse();
});

it('reports needs-configuration and the still-missing keys from Config', function () {
    $addon = new Addon('y', 'y/y.php', 'Y', needsKeys: ['svc.key', 'svc.secret']);

    $values = ['svc.key' => 'set', 'svc.secret' => ''];
    $read   = static fn (string $key): string => (string) ($values[$key] ?? '');

    expect($addon->needsConfiguration())->toBeTrue()
        ->and($addon->missingKeys($read))->toBe(['svc.secret']);
});

it('keeps the kit dependency on corex-ui', function () {
    $woo = (new AddonRegistry())->find('corex-kit-woo');

    expect($woo->requires)->toContain('corex-ui')
        ->and($woo->flag)->toBe('woocommerce_kit');
});
