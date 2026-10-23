<?php

/**
 * The controls of a Settings page (spec 119): an on/off setting is a switch (US-SET-09), a locked
 * value is shown and cannot be edited (US-SET-02), a saved secret can be removed (US-SET-15), and
 * every control says what is stored so the page can tell an unsaved change (US-SET-01).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\FieldState;
use Corex\Config\Settings\SettingsControls;
use Corex\Support\Config\SettingLock;

beforeEach(function () {
    foreach (['__', 'esc_attr', 'esc_html', 'esc_url', 'esc_html__', 'esc_attr__'] as $function) {
        Functions\when($function)->returnArg();
    }
});

it('draws an on/off setting as a switch that saves "1", named by its label and saying On or Off', function (string $stored, bool $checked) {
    $html = (new SettingsControls())->control('dashboard.widgets.attention', ['label' => 'Attention widget', 'type' => 'checkbox'], FieldState::plain($stored));

    expect($html)->toContain('type="checkbox" value="1"' . ($checked ? ' checked' : ' role'))
        ->and($html)->toContain('role="switch"')
        ->and($html)->toContain('data-corex-saved="' . ($checked ? '1' : '0') . '"')
        ->and($html)->toContain('<span class="corex-switch__on">On</span>')
        ->and($html)->toContain('<span class="corex-switch__off">Off</span>')
        ->and($html)->not->toContain('disabled');
})->with([
    'on'           => ['1', true],
    'off'          => ['0', false],
    'never stored' => ['', false],
]);

it('shows a switch of an add-on that is off without letting it change', function () {
    $html = (new SettingsControls())->control('media.webp.enabled', ['label' => 'Convert', 'type' => 'checkbox'], FieldState::plain('1'), true);

    expect($html)->toContain(' disabled');
});

it('shows a value set in .env, read-only, and says where it is set', function () {
    $state = new FieldState('Acme', 'Acme', new SettingLock(SettingLock::BY_DOTENV, 'Acme'));
    $html  = (new SettingsControls())->control('brand.company_name', ['label' => 'Company name', 'type' => 'text'], $state);

    expect($html)->toContain('value="Acme"')
        ->and($html)->toContain('readonly aria-readonly="true"')
        ->and($html)->toContain('aria-describedby="brand_company_name-lock"')
        ->and($html)->toContain('id="brand_company_name-lock"')
        ->and($html)->toContain('Set in the site\'s .env file');
});

it('shows a secret set in .env as set there, with no field and no way to remove it', function () {
    $state = new FieldState('', '', new SettingLock(SettingLock::BY_DOTENV, 'the-secret'));
    $html  = (new SettingsControls())->control('captcha.secret', ['label' => 'Secret key', 'type' => 'password'], $state);

    expect($html)->toContain('Set in .env')
        ->and($html)->toContain('Set in the site\'s .env file')
        ->and($html)->not->toContain('the-secret')
        ->and($html)->not->toContain('<input')
        ->and($html)->not->toContain('corex_remove_secret');
});

it('offers to remove a saved secret, saying what stops, and never draws its value', function () {
    $html = (new SettingsControls())->control(
        'captcha.secret',
        ['label' => 'Secret key', 'type' => 'password', 'remove_effect' => 'The captcha check stops.'],
        new FieldState('', '', null, true),
    );

    expect($html)->toContain('A value is saved')
        ->and($html)->toContain('type="password" value=""')
        ->and($html)->toContain('name="corex_remove_secret[]" value="captcha.secret"')
        ->and($html)->toContain('The captcha check stops.');
});

it('offers nothing to remove when no secret is saved, or the add-on is off', function () {
    $controls = new SettingsControls();
    $field    = ['label' => 'Secret key', 'type' => 'password'];

    expect($controls->control('captcha.secret', $field, new FieldState('', '', null, false)))
        ->toContain('No value saved')
        ->not->toContain('corex_remove_secret')
        ->and($controls->control('captcha.secret', $field, new FieldState('', '', null, true), true))
        ->not->toContain('corex_remove_secret');
});

it('shows a select with nothing stored at its first choice, and counts that as stored', function () {
    $html = (new SettingsControls())->control(
        'brand.admin_appearance',
        ['label' => 'Appearance', 'type' => 'select', 'options' => ['system' => 'System', 'light' => 'Light']],
        FieldState::plain(''),
    );

    expect($html)->toContain('data-corex-saved="system"')
        ->and($html)->toContain('<option value="system" selected>');
});
