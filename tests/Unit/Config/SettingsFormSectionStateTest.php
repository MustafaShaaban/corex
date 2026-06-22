<?php

/**
 * Spec 060 / M6 US2 — the settings form reflects each section's runtime add-on state.
 *
 * When a section resolves to a SettingsSectionState, the form shows the matching notice
 * and never presents usable fields for a non-active add-on: Hidden hides the fields
 * behind a "not installed" notice; Disabled shows a disabled notice and disables the
 * inputs; ConfigurationNeeded shows a prompt with the (enterable) fields; Normal/none is
 * unchanged.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsSectionState;

function formWithCaptchaState(?SettingsSectionState $state): string
{
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();

    return (new SettingsForm(new SettingsRegistry()))->render(
        fn (string $key): string => '',
        'NONCE',
        fn (string $sectionKey): ?SettingsSectionState => $sectionKey === 'captcha' ? $state : null,
    );
}

it('hides a not-installed section behind a notice', function () {
    $html = formWithCaptchaState(SettingsSectionState::Hidden);

    expect($html)->toContain('corex-section-notice')
        ->and($html)->not->toContain('name="captcha_site_key"'); // fields not rendered
});

it('disables the fields of an inactive section', function () {
    $html = formWithCaptchaState(SettingsSectionState::Disabled);

    expect($html)->toContain('corex-section-notice')
        ->and($html)->toContain('name="captcha_site_key"')
        ->and($html)->toContain('disabled'); // inputs disabled, not usable
});

it('prompts for configuration when active but unconfigured', function () {
    $html = formWithCaptchaState(SettingsSectionState::ConfigurationNeeded);

    expect($html)->toContain('corex-settings-section')
        ->and($html)->toContain('id="corex-settings-section-captcha"')
        ->and($html)->toContain('corex-section-notice--configuration-needed')
        ->and($html)->toContain('role="status"')
        ->and($html)->toContain('name="captcha_site_key"'); // fields present to configure
});

it('renders a normal section with no notice when no state is supplied', function () {
    $html = formWithCaptchaState(null);

    expect($html)->toContain('name="captcha_site_key"')
        ->and($html)->not->toContain('corex-section-notice');
});
