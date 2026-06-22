<?php

/**
 * CoreX admin appearance contract (Spec 060 / Blocker 12). The appearance setting resolves to
 * system/light/dark (invalid → system) and the SSO-slot setting reads truthfully; the admin
 * shell pins the chosen theme via a data attribute (light/dark) or leaves it to the OS scheme
 * (system).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Admin\AdminPage;
use Corex\Config\Branding\BrandingService;
use Corex\Support\Config\ConfigInterface;

function brandingWith(string $appearanceReturn, string $ssoReturn): BrandingService
{
    $config = Mockery::mock(ConfigInterface::class);
    $config->shouldReceive('get')->with('brand.admin_appearance', 'system')->andReturn($appearanceReturn);
    $config->shouldReceive('get')->with('brand.login_sso_enabled', '')->andReturn($ssoReturn);

    return new BrandingService($config, 'https://example.test/default-logo.svg');
}

it('resolves the appearance setting, falling back to system for unknown values', function (string $stored, string $expected) {
    expect(brandingWith($stored, '')->adminAppearance())->toBe($expected);
})->with([
    ['system', 'system'],
    ['light', 'light'],
    ['dark', 'dark'],
    ['', 'system'],
    ['purple', 'system'],
]);

it('reads the SSO-slot setting truthfully', function (string $stored, bool $expected) {
    expect(brandingWith('system', $stored)->loginSsoEnabled())->toBe($expected);
})->with([
    ['1', true],
    ['', false],
    ['0', false],
]);

it('pins the admin shell theme only for an explicit light/dark appearance', function (string $appearance, bool $hasAttr) {
    foreach (['esc_html', 'esc_attr', 'esc_url', 'admin_url'] as $fn) {
        Functions\when($fn)->returnArg(1);
    }
    Functions\when('__')->returnArg(1);
    Functions\when('esc_html__')->returnArg(1);
    Functions\when('esc_attr__')->returnArg(1);
    Functions\when('apply_filters')->alias(
        static fn (string $hook, mixed $value) => $hook === 'corex_admin_appearance' ? $appearance : $value,
    );

    $html = (new AdminPage())->open('overview', 'Overview');

    if ($hasAttr) {
        expect($html)->toContain('data-corex-theme="' . $appearance . '"');
    } else {
        expect($html)->not->toContain('data-corex-theme');
    }
})->with([
    ['light', true],
    ['dark', true],
    ['system', false],
]);
