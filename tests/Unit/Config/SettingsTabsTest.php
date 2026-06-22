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

it('adds official reference links with safe external attributes for external key fields', function () {
    $html = settingsForm()->render(static fn (string $key): string => '', '<nonce>');

    expect($html)
        ->toContain('https://developers.google.com/speed/docs/insights/v5/get-started')
        ->toContain('https://developers.cloudflare.com/fundamentals/api/get-started/create-token/')
        ->toContain('https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('Get a PageSpeed Insights API key');
});

it('hides captcha provider fields + a disabled notice when the driver is None', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'captcha.driver' ? 'none' : '',
        '<nonce>',
    );

    expect($html)
        // provider rows carry the conditional attributes and are hidden server-side
        ->toContain('data-corex-show-for="captcha.driver"')
        ->toContain('data-corex-show-values="recaptcha turnstile hcaptcha" hidden')
        ->toContain('data-corex-show-values="recaptcha" hidden')
        // the disabled notice (show-values="none") is shown — no trailing hidden
        ->toContain('Captcha is disabled — no provider selected.')
        ->toContain('data-corex-show-values="none">')
        ->not->toContain('data-corex-show-values="none" hidden');
});

it('shows only the active driver provider references (Turnstile -> Cloudflare, not Google)', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'captcha.driver' ? 'turnstile' : '',
        '<nonce>',
    );

    expect($html)
        // the Turnstile help variant is visible and points to Cloudflare
        ->toContain('data-corex-show-values="turnstile">')
        ->toContain('https://developers.cloudflare.com/turnstile/get-started/')
        // the reCAPTCHA + hCaptcha variants are present but hidden (no wrong links shown)
        ->toContain('data-corex-show-values="recaptcha" hidden')
        ->toContain('https://docs.hcaptcha.com')
        ->toContain('https://www.google.com/recaptcha/admin/create');
});

it('explains Honeypot, hides key fields, and shows no provider links', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'captcha.driver' ? 'honeypot' : '',
        '<nonce>',
    );

    expect($html)
        ->toContain('Honeypot adds a hidden spam-trap field')
        ->toContain('data-corex-show-values="honeypot">')
        // the key-field rows (and their links) are hidden for honeypot
        ->toContain('data-corex-show-values="recaptcha turnstile hcaptcha" hidden');
});

it('shows reCAPTCHA fields + links and hides the disabled notice when driver is reCAPTCHA', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'captcha.driver' ? 'recaptcha' : '',
        '<nonce>',
    );

    expect($html)
        ->toContain('https://www.google.com/recaptcha/admin/create')
        // the v3 score/action rows (show-values="recaptcha") are NOT hidden
        ->not->toContain('data-corex-show-values="recaptcha" hidden')
        // the disabled notice IS hidden
        ->toContain('data-corex-show-values="none" hidden');
});

it('adds captcha reference links + helper copy with safe external attributes', function () {
    $html = settingsForm()->render(static fn (string $key): string => '', '<nonce>');

    expect($html)
        ->toContain( 'https://www.google.com/recaptcha/admin/create' )
        ->toContain( 'https://developers.google.com/recaptcha/docs/v3#interpreting_the_score' )
        ->toContain( 'https://developers.google.com/recaptcha/docs/v3#actions' )
        ->toContain( 'Create reCAPTCHA keys' )
        // practical helper copy for the v3 fields
        ->toContain( '0.5 is a common starting point' )
        ->toContain( 'contact_form or login' )
        ->toContain( 'target="_blank"' )
        ->toContain( 'rel="noopener noreferrer"' );
});

it('keeps the captcha secret field write-only even with a reference link', function () {
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'captcha.secret' ? 'captcha-secret-value' : '',
        '<nonce>',
    );

    expect($html)->not->toContain('captcha-secret-value');
});

it('keeps secret key fields write-only even with reference links', function () {
    // A saved secret value must never be rendered back into the field.
    $html = settingsForm()->render(
        static fn (string $key): string => $key === 'insights.psi.key' ? 'super-secret-key' : '',
        '<nonce>',
    );

    expect($html)->not->toContain('super-secret-key')
        ->and($html)->toContain('autocomplete="new-password"');
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
