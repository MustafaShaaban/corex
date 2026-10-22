<?php

/**
 * The login screen shows no sign-in option that does not exist (spec 114, US-SET-10).
 *
 * CoreX has no single sign-on provider. The sign-in screen drew a disabled "SSO is not
 * configured yet" button and an "or" divider when a Brand setting was ticked; it draws neither
 * now, whatever is stored, and keeps its subheading. Lost-password/reset are left untouched.
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

function loginBranding(string $storedSso): AdminBranding
{
    $config = Mockery::mock(ConfigInterface::class);
    $config->shouldReceive('get')->with('brand.login_sso_enabled', '')->andReturn($storedSso);
    $config->shouldReceive('get')->andReturn('');

    return new AdminBranding(new BrandingService($config, 'https://example.test/logo.svg'));
}

/**
 * Every control the login message adds, other than the native form passed in: the screen
 * offers no button, link or field of its own.
 */
function loginControlsAdded(string $html): array
{
    $added = str_replace('<form id="loginform"></form>', '', $html);
    preg_match_all('/<(button|a|input|select)/i', $added, $controls);

    return $controls[1];
}

it('offers no sign-in control besides the form, whatever the stored SSO setting says', function (string $stored) {
    $html = loginBranding($stored)->loginMessage('<form id="loginform"></form>');

    expect(loginControlsAdded($html))->toBe([])
        ->and($html)->toContain('Sign in to your workspace')
        ->and($html)->not->toContain('SSO')
        ->and($html)->not->toContain('corex-login__divider')
        // The native form is kept, after the subheading.
        ->and($html)->toEndWith('<form id="loginform"></form>');
})->with([
    'ticked' => ['1'],
    'never set' => [''],
    'unticked' => ['0'],
]);

it('leaves lost-password and reset screens untouched', function () {
    $_REQUEST['action'] = 'lostpassword';

    $html = loginBranding('1')->loginMessage('<p class="message">Reset</p>');

    expect($html)->toBe('<p class="message">Reset</p>');
});
