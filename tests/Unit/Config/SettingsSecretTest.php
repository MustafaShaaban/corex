<?php

/**
 * Spec 060 / M6 US2 — captcha/secret settings are write-only.
 *
 * Secret (password-typed) fields — captcha secret, Insights API keys — must never have
 * their stored value rendered into the settings form, and the registry must identify
 * them so an empty submit can preserve the stored secret. Non-secret fields still show
 * their value.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsRegistry;

function stubSettingsEscapers(): void
{
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_url')->returnArg();
}

it('never renders a stored secret value into the form (write-only)', function () {
    stubSettingsEscapers();

    $secret = 'SUPER-SECRET-VALUE-9XZ';
    $form = new SettingsForm(new SettingsRegistry());

    $html = $form->render(
        fn (string $key): string => $key === 'captcha.secret' ? $secret : 'plain-value',
        'NONCE',
    );

    expect($html)->not->toContain($secret)
        ->and($html)->toContain('name="captcha_secret"')   // the field is still present
        ->and($html)->toContain('plain-value');             // non-secret fields still show values
});

it('marks the captcha secret field as a password input', function () {
    stubSettingsEscapers();

    $form = new SettingsForm(new SettingsRegistry());
    $html = $form->render(fn (string $key): string => 'x', 'NONCE');

    // The secret control is a password input that carries no value attribute content.
    expect($html)->toMatch('/name="captcha_secret"[^>]*type="password"|type="password"[^>]*name="captcha_secret"/');
});

it('identifies every password-typed key as a secret to preserve on empty submit', function () {
    $secretKeys = (new SettingsRegistry())->secretKeys();

    expect($secretKeys)->toContain('captcha.secret')
        ->and($secretKeys)->toContain('insights.psi.key')
        ->and($secretKeys)->toContain('insights.cloudflare.token')
        ->and($secretKeys)->not->toContain('captcha.site_key');
});
