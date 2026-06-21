<?php

/**
 * Unit tests for the Corex settings registry + form (spec 017 US1: FR-002, FR-003, SC-001/2).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsRegistry;

it('enumerates the configurable sections and field keys', function () {
    $registry = new SettingsRegistry();

    expect(array_keys($registry->sections()))->toContain('brand', 'mail', 'forms', 'captcha')
        ->and($registry->keys())->toContain('brand.logo_url', 'mail.from.address', 'forms.email.recipient', 'captcha.driver');
});

it('renders the settings form with current values and a nonce', function () {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();

    $form = new SettingsForm(new SettingsRegistry());

    $html = $form->render(
        fn (string $key): string => $key === 'brand.footer_text' ? 'Powered by Acme' : '',
        '<input type="hidden" name="corex_settings_nonce" value="abc" />'
    );

    expect($html)->toContain('corex_settings_nonce')          // the nonce field
        ->toContain('name="brand_footer_text"')               // a field, dot->underscore
        ->toContain('value="Powered by Acme"')                // its current value
        ->toContain('name="captcha_secret"')
        ->toContain('type="password"')
        ->toContain('Save settings');
});
