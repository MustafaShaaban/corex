<?php

/**
 * Login SSO slot contract (Spec 060 / Blocker 13). The SSO slot is reserved on the sign-in
 * screen only when the setting is on, and is then an honest disabled "not configured yet"
 * control — never a fake provider. Lost-password/reset screens are left untouched.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Branding\AdminBranding;
use Corex\Config\Branding\BrandingService;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    foreach (['esc_html', 'esc_attr'] as $fn) {
        Functions\when($fn)->returnArg(1);
    }
    Functions\when('esc_html__')->returnArg(1);
    Functions\when('sanitize_key')->returnArg(1);
    Functions\when('wp_unslash')->returnArg(1);
    unset($_REQUEST['action']);
});

function loginBranding(bool $ssoEnabled): AdminBranding
{
    $config = Mockery::mock(ConfigInterface::class);
    $config->shouldReceive('get')->with('brand.login_sso_enabled', '')->andReturn($ssoEnabled ? '1' : '');
    $config->shouldReceive('get')->andReturn('');

    return new AdminBranding(new BrandingService($config, 'https://example.test/logo.svg'));
}

it('reserves a disabled, honest SSO slot on sign-in when the setting is on', function () {
    $html = loginBranding(true)->loginMessage('<form id="loginform"></form>');

    expect($html)
        ->toContain('Sign in to your workspace')
        ->toContain('corex-login__sso')
        ->toContain('SSO is not configured yet.')
        ->toContain('disabled')
        // the key glyph is an inline CoreX SVG (icon system), not an emoji or a dashicon
        ->toContain('<svg class="corex-login__sso-icon"')
        ->not->toContain('dashicons-admin-network')
        // the native form is preserved, appended after the slot
        ->toContain('<form id="loginform">');
});

it('omits the SSO slot when the setting is off, keeping the subheading', function () {
    $html = loginBranding(false)->loginMessage('<form id="loginform"></form>');

    expect($html)
        ->toContain('Sign in to your workspace')
        ->not->toContain('corex-login__sso')
        ->not->toContain('SSO is not configured');
});

it('leaves lost-password and reset screens untouched', function () {
    $_REQUEST['action'] = 'lostpassword';

    $html = loginBranding(true)->loginMessage('<p class="message">Reset</p>');

    expect($html)->toBe('<p class="message">Reset</p>');
});
