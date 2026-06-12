<?php

/**
 * Unit tests for the settings form's per-field-type rendering (spec 032: FR-001/006).
 * WordPress escaping is stubbed at the boundary.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsRegistry;

beforeEach(function () {
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

function formHtml(callable $value): string
{
    return (new SettingsForm(new SettingsRegistry()))->render($value, '<nonce>');
}

it('renders the logo as a media control (preview + select/remove + value input)', function () {
    $html = formHtml(fn (string $k): string => $k === 'brand.logo_url' ? 'https://x/logo.png' : '');

    expect($html)->toContain('corex-media-select')
        ->toContain('data-target="brand_logo_url"')
        ->toContain('corex-media-remove')
        ->toContain('corex-media-preview')
        ->toContain('src="https://x/logo.png"')
        ->toContain('id="brand_logo_url"'); // the value input still present (no-JS degrade)
});

it('renders the captcha driver as a select of its options with the current value selected', function () {
    $html = formHtml(fn (string $k): string => $k === 'captcha.driver' ? 'turnstile' : '');

    expect($html)->toContain('<select id="captcha_driver" name="captcha_driver"')
        ->toContain('<option value="turnstile" selected>')
        ->toContain('<option value="honeypot">');
});

it('renders a plain input for text/email/password fields', function () {
    $html = formHtml(fn (string $k): string => '');

    expect($html)->toContain('id="mail_from_name" name="mail_from_name" type="text"')
        ->toContain('type="password"');
});

it('includes the nonce and a save button', function () {
    expect(formHtml(fn (string $k): string => ''))->toContain('<nonce>')->toContain('button-primary');
});
