<?php

/**
 * Settings tabs + Brand-tab values contract (Spec 060 / Blockers 10-12). The Settings form
 * renders the real sections as accessible ARIA tabs in a fixed order, the Brand tab surfaces
 * the current admin-logo value (or a designed empty placeholder), the footer value, the
 * appearance setting, and the SSO-slot setting — no invented demo tabs.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsRegistry;

beforeEach(function () {
    foreach (['esc_html', 'esc_attr', 'esc_url'] as $fn) {
        Functions\when($fn)->returnArg(1);
    }
    Functions\when('__')->returnArg(1);
    Functions\when('esc_html__')->returnArg(1);
    Functions\when('esc_attr__')->returnArg(1);
});

function settingsForm(): SettingsForm
{
    return new SettingsForm(new SettingsRegistry());
}

it('renders the real sections as ARIA tabs in the fixed order', function () {
    $html = settingsForm()->render(static fn (string $key): string => '', '<nonce>');

    preg_match_all('/data-corex-tab="([a-z]+)"/', $html, $matches);

    expect($matches[1])->toBe(['brand', 'mail', 'forms', 'captcha', 'insights'])
        ->and($html)->toContain('role="tablist"')
        ->toContain('role="tab"')
        ->toContain('role="tabpanel"')
        // no invented demo tabs
        ->not->toContain('Architecture')
        ->not->toContain('Design tokens')
        ->not->toContain('Data sources');
});

it('marks exactly one tab selected — the first by default, or the requested one', function () {
    $default = settingsForm()->render(static fn (string $key): string => '', '<nonce>');
    expect(substr_count($default, 'aria-selected="true"'))->toBe(1)
        ->and($default)->toMatch('/id="corex-tab-brand"[^>]*aria-selected="true"/');

    $captcha = settingsForm()->render(static fn (string $key): string => '', '<nonce>', null, 'captcha');
    expect($captcha)->toMatch('/id="corex-tab-captcha"[^>]*aria-selected="true"/');
});

it('shows a designed empty placeholder when no admin logo is saved', function () {
    $html = settingsForm()->render(static fn (string $key): string => '', '<nonce>');

    expect($html)->toContain('No logo set')
        ->toContain('corex-media__placeholder');
});

it('shows the current admin logo as a preview when one is saved', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'brand.logo_url' ? 'https://example.test/logo.png' : '',
        '<nonce>',
    );

    expect($html)->toContain('class="corex-media-preview" src="https://example.test/logo.png"');
});

it('renders the current admin footer text value', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'brand.footer_text' ? 'Built with CoreX' : '',
        '<nonce>',
    );

    expect($html)->toContain('value="Built with CoreX"');
});

it('offers the appearance setting (System/Light/Dark) and the SSO-slot setting on the Brand tab', function () {
    $html = settingsForm()->render(static fn (string $key): string => '', '<nonce>');

    expect($html)
        ->toContain('CoreX admin appearance')
        ->toContain('brand_admin_appearance')
        ->toContain('>System<')->toContain('>Light<')->toContain('>Dark<')
        ->toContain('Enable SSO login section')
        ->toContain('brand_login_sso_enabled');
});
