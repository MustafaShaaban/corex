<?php

/**
 * Unit tests for the Corex branding service + the bundled identity (spec 016: FR-001, FR-002, SC-001/2).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Branding\AdminBranding;
use Corex\Config\Branding\BrandingService;
use Corex\Support\Config\ConfigInterface;

/**
 * @param array<string,mixed> $values
 */
function brandingConfig(array $values): ConfigInterface
{
    return new class($values) implements ConfigInterface {
        /** @param array<string,mixed> $values */
        public function __construct(private array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };
}

it('keeps product logo resolution separate from client identity', function (array $config, string $expected) {
    expect((new BrandingService(brandingConfig($config), '/default.svg'))->logoUrl())->toBe($expected);
})->with([
    'bundled product default' => [[], '/default.svg'],
    'explicit product override' => [['brand.logo_url' => '/custom.svg'], '/custom.svg'],
    'empty product override falls back' => [['brand.logo_url' => ''], '/default.svg'],
    'client identity is not product branding' => [['site.logo_url' => '/client.svg'], '/default.svg'],
]);

it('produces a login-scoped logo variable without replacing WordPress form markup', function () {
    $css = (new BrandingService(brandingConfig([]), '/x.svg'))->loginCss('/x.svg');

    expect($css)->toContain('body.login.corex-login')
        ->toContain('--corex-admin-login-logo')
        ->toContain('/x.svg')
        ->not->toContain('<form')
        ->not->toContain('action=');
});

it('ships a login stylesheet scoped to the CoreX login body class and admin tokens', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 3) . '/plugins/corex-core/assets/css/corex-admin-login.css');

    expect($css)->toContain('body.login.corex-login')
        ->and($css)->toContain('var(--corex-admin-')
        ->and($css)->not->toMatch('/(?:^|,)\s*(?::root|html|body(?!\.login\.corex-login))\b/m');
});

it('adds only the CoreX login class and conditionally enqueues the login visual layer', function () {
    $branding = new AdminBranding(new BrandingService(brandingConfig([]), '/x.svg'));
    Functions\when('esc_url')->returnArg();
    Functions\expect('wp_enqueue_style')->once()->with('corex-admin-login');
    Functions\expect('wp_add_inline_style')->once()->with(
        'corex-admin-login',
        'body.login.corex-login{--corex-admin-login-logo:url("/x.svg")}',
    );

    // The CoreX login is dark-first: with no saved appearance ('system') it carries an explicit
    // dark theme class so the real logged-out page shows the approved dark design by default.
    expect($branding->loginBodyClass(['login-action-login']))
        ->toBe(['login-action-login', 'corex-login', 'corex-appearance-dark']);
    $branding->enqueueLoginAssets();
});

it('resolves the login appearance dark-first: light opts into light, system/dark stay dark', function (string $saved, string $expected) {
    $branding = new AdminBranding(
        new BrandingService(brandingConfig(['brand.admin_appearance' => $saved]), '/x.svg'),
    );

    expect($branding->loginAppearance())->toBe($expected)
        ->and($branding->loginBodyClass([]))->toContain('corex-appearance-' . $expected);
})->with([
    ['light', 'light'],
    ['dark', 'dark'],
    ['system', 'dark'],
    ['', 'dark'],
]);

it('does not hook authentication or replace native login actions', function () {
    $source = (string) file_get_contents(
        dirname(__DIR__, 3) . '/plugins/corex-config/src/Branding/AdminBranding.php',
    );

    expect($source)->not->toContain("add_filter('authenticate'")
        ->and($source)->not->toContain("add_action('login_form_")
        ->and($source)->not->toContain('<form');
});

it('exposes the configured footer text and login URL', function () {
    $service = new BrandingService(brandingConfig(['brand.footer_text' => 'Built on Corex', 'brand.login_url' => 'https://x.test']), '/d.svg');

    expect($service->configuredFooterText())->toBe('Built on Corex')
        ->and($service->configuredLoginUrl())->toBe('https://x.test');
});

it('ships a valid Corex SVG logo in the brand palette', function () {
    $svg = (string) file_get_contents(dirname(__DIR__, 3) . '/plugins/corex-config/assets/corex-logo.svg');

    expect($svg)->toContain('<svg')
        ->toContain('</svg>')
        ->toContain('#0B1F3B')   // navy
        ->toContain('#00C2FF')   // cyan
        ->toContain('Corex');
});
