<?php

/**
 * Add-on toggle accessibility + truthful-state contract (Spec 060 / Blocker 8): the Add-ons
 * screen renders enable/disable as an accessible switch (`role="switch"` + `aria-checked`
 * reflecting the real state, a descriptive label, and a visible On/Off text — never colour
 * alone). Installed + freely-togglable add-ons get an actionable submit switch; everything
 * else (blocked by a dependent, dependency missing, not installed) gets a non-actionable
 * `aria-disabled` switch. The decision is the real AddonManager — this test wires real ones.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Admin\AdminPage;
use Corex\Config\Addons\AddonActivator;
use Corex\Config\Addons\AddonManager;
use Corex\Config\Addons\AddonRegistry;
use Corex\Config\Addons\AddonsScreen;
use Corex\Config\Addons\AddonState;
use Corex\Config\Addons\AddonView;
use Corex\Config\Addons\PendingKits;
use Corex\Provisioning\KitProvisioner;
use Corex\Security\Admin\AdminGuard;

beforeEach(function () {
    Functions\when('__')->returnArg(1);
    Functions\when('esc_attr')->returnArg(1);
    Functions\when('esc_html')->returnArg(1);
    Functions\when('esc_html__')->returnArg(1);
    Functions\when('wp_nonce_field')->justReturn('');
});

function toggleMarkup(AddonView $view, AddonState $state): string
{
    $registry = new AddonRegistry();
    // toggleControl() only consults the (real) AddonManager; the remaining collaborators are
    // final and unused on this path, so build them without invoking their constructors.
    $bare = static fn (string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $screen = new AddonsScreen(
        $registry,
        new AddonManager($registry),
        $bare(AddonActivator::class),
        $bare(AdminGuard::class),
        Mockery::mock(KitProvisioner::class),
        $bare(PendingKits::class),
        new AdminPage(),
    );

    $method = new ReflectionMethod($screen, 'toggleControl');
    $method->setAccessible(true);

    return (string) $method->invoke($screen, $view, $state);
}

function viewFor(string $slug, bool $active): AddonView
{
    $addon = (new AddonRegistry())->find($slug);
    expect($addon)->not->toBeNull();

    return new AddonView($addon, installed: true, active: $active, flagOn: $active);
}

it('renders an active freely-togglable add-on as an actionable switch that disables', function () {
    // corex-kit-company is active and nothing depends on it → it can be disabled.
    $markup = toggleMarkup(
        viewFor('corex-kit-company', true),
        new AddonState(activeSlugs: ['corex-ui', 'corex-kit-company']),
    );

    expect($markup)
        ->toContain('role="switch"')
        ->toContain('aria-checked="true"')
        ->toContain('type="submit"')
        ->toContain('Disable')
        ->toContain('>On<')
        ->not->toContain('aria-disabled');
});

it('renders an inactive enable-able add-on as an actionable switch that enables', function () {
    // corex-kit-company's dependency (corex-ui) is active → it can be enabled.
    $markup = toggleMarkup(
        viewFor('corex-kit-company', false),
        new AddonState(activeSlugs: ['corex-ui']),
    );

    expect($markup)
        ->toContain('aria-checked="false"')
        ->toContain('type="submit"')
        ->toContain('Enable')
        ->toContain('>Off<')
        ->not->toContain('aria-disabled');
});

it('renders a non-actionable disabled switch when a dependent blocks disabling', function () {
    // corex-kit-company (active) requires corex-ui → corex-ui cannot be disabled.
    $markup = toggleMarkup(
        viewFor('corex-ui', true),
        new AddonState(activeSlugs: ['corex-ui', 'corex-kit-company']),
    );

    expect($markup)
        ->toContain('role="switch"')
        ->toContain('aria-checked="true"')
        ->toContain('aria-disabled="true"')
        ->not->toContain('type="submit"');
});
