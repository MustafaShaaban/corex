<?php

/**
 * Unit tests for the Corex branding service + the bundled identity (spec 016: FR-001, FR-002, SC-001/2).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
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
    expect((new BrandingService(brandingConfig($config), '/default.svg', '/light.svg'))->logoUrl())->toBe($expected);
})->with([
    'bundled product default' => [[], '/default.svg'],
    'explicit product override' => [['brand.logo_url' => '/custom.svg'], '/custom.svg'],
    'empty product override falls back' => [['brand.logo_url' => ''], '/default.svg'],
    'client identity is not product branding' => [['site.logo_url' => '/client.svg'], '/default.svg'],
]);

it('produces a login-scoped logo variable without replacing WordPress form markup', function () {
    Functions\when('esc_url_raw')->returnArg();
    $css = (new BrandingService(brandingConfig([]), '/x.svg', '/x.svg'))->loginCss('/x.svg', '/x.svg');

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
    $branding = new AdminBranding(new BrandingService(brandingConfig([]), '/dark.svg', '/light.svg'));
    Functions\when('esc_url_raw')->returnArg();
    Functions\expect('wp_enqueue_style')->once()->with('corex-admin-login');
    Functions\expect('wp_enqueue_script')->once()->with('corex-admin-login');
    Functions\expect('wp_add_inline_style')->once()->with(
        'corex-admin-login',
        \Mockery::on(static fn (string $css): bool => str_starts_with(
            $css,
            'body.login.corex-login{--corex-admin-login-logo:url("/dark.svg")}',
        ) && str_contains($css, 'url("/light.svg")')),
    );

    // The CoreX login is dark-first: with no saved appearance ('system') it carries an explicit
    // dark theme class so the real logged-out page shows the approved dark design by default.
    expect($branding->loginBodyClass(['login-action-login']))
        ->toBe(['login-action-login', 'corex-login', 'corex-appearance-dark']);
    $branding->enqueueLoginAssets();
});

it('resolves the login appearance dark-first: light opts into light, system/dark stay dark', function (string $saved, string $expected) {
    $branding = new AdminBranding(
        new BrandingService(brandingConfig(['brand.admin_appearance' => $saved]), '/x.svg', '/y.svg'),
    );

    expect($branding->loginAppearance())->toBe($expected)
        ->and($branding->loginBodyClass([]))->toContain('corex-appearance-' . $expected);
})->with([
    ['light', 'light'],
    ['dark', 'dark'],
    ['system', 'dark'],
    ['', 'dark'],
]);

it('ships the login design layer: inline field icons, motion, and reduced-motion respect', function () {
    $css = (string) file_get_contents(
        dirname(__DIR__, 3) . '/plugins/corex-core/assets/css/corex-admin-login.css',
    );

    expect($css)
        // leading field icons via the CoreX icon system (masked, theme-aware)
        ->toContain('login-user.svg')
        ->toContain('login-lock.svg')
        ->toContain('.corex-login__field')
        // login motion + explicit reduced-motion guard
        ->toContain('@keyframes corex-login-')
        ->toContain('animation: corex-login-')
        ->toContain('prefers-reduced-motion: reduce')
        // no raster/base64 artwork
        ->not->toContain('data:image');

    $user = dirname(__DIR__, 3) . '/plugins/corex-core/assets/icons/login-user.svg';
    $lock = dirname(__DIR__, 3) . '/plugins/corex-core/assets/icons/login-lock.svg';
    expect($user)->toBeFile()->and($lock)->toBeFile();
});

it('does not hook authentication or replace native login actions', function () {
    $source = (string) file_get_contents(
        dirname(__DIR__, 3) . '/plugins/corex-config/src/Branding/AdminBranding.php',
    );

    expect($source)->not->toContain("add_filter('authenticate'")
        ->and($source)->not->toContain("add_action('login_form_")
        ->and($source)->not->toContain('<form');
});

it('exposes the configured footer text and login URL', function () {
    $service = new BrandingService(brandingConfig(['brand.footer_text' => 'Built on Corex', 'brand.login_url' => 'https://x.test']), '/d.svg', '/l.svg');

    expect($service->configuredFooterText())->toBe('Built on Corex')
        ->and($service->configuredLoginUrl())->toBe('https://x.test');
});

it('draws the login with the Core X lockup, and the retired navy and cyan logo is gone', function () {
    $assets = dirname(__DIR__, 3) . '/plugins/corex-config/assets';

    // Spec 121, US-BRD-08 (DECISIONS #465): the old file is removed, not kept as evidence. That no
    // logo file holds the old colours is NavyEraValuesTest's (spec 134, FR-009).
    expect($assets . '/corex-logo.svg')->not->toBeFile()
        ->and((string) file_get_contents($assets . '/brand/corex-lockup-dark.svg'))->toContain('#c9a25e')->toContain('#f4f5f7')
        ->and((string) file_get_contents($assets . '/brand/corex-lockup-light.svg'))->toContain('#ad8643')->toContain('#14151a');
});

/*
 * Spec 134, US-BRD-05, FR-020: the login draws the fixed-colour lockup that suits its card. A file
 * drawn in `currentColor` cannot be a background image (its modules were drawn black), so the login
 * takes the lockup for a dark ground or for a light one, by the same rules the card takes its
 * colours: the dark set by default, the light set when the body is pinned Light, and on a light
 * computer when it is pinned to neither (corex-admin-tokens.css).
 */
it('draws the dark lockup on the dark card and the light lockup on the light card (FR-020)', function () {
    Functions\when('esc_url_raw')->returnArg();
    $css = (new BrandingService(brandingConfig([]), '/dark.svg', '/light.svg'))->loginCss('/dark.svg', '/light.svg');

    expect($css)->toBe(
        'body.login.corex-login{--corex-admin-login-logo:url("/dark.svg")}'
        . 'body.login.corex-login.corex-appearance-light{--corex-admin-login-logo:url("/light.svg")}'
        . '@media (prefers-color-scheme: light){'
        . 'body.login.corex-login:not(.corex-appearance-light):not(.corex-appearance-dark)'
        . '{--corex-admin-login-logo:url("/light.svg")}}',
    );
});

it('resolves the bundled lockup for each ground, and a logo set in Branding for both (FR-020, TC-BRD-05-06)', function () {
    Functions\when('esc_url_raw')->returnArg();
    $bundled = new BrandingService(brandingConfig([]), '/dark.svg', '/light.svg');
    $custom = new BrandingService(brandingConfig(['brand.logo_url' => '/client.png']), '/dark.svg', '/light.svg');

    expect($bundled->logoUrl())->toBe('/dark.svg')
        ->and($bundled->lightLogoUrl())->toBe('/light.svg')
        ->and($custom->logoUrl())->toBe('/client.png')
        ->and($custom->lightLogoUrl())->toBe('/client.png')
        // One logo for every ground: no switch is written.
        ->and($custom->loginCss('/client.png', '/client.png'))
        ->toBe('body.login.corex-login{--corex-admin-login-logo:url("/client.png")}');
});

it('passes the lockup for each ground to the login, and a site logo for both (FR-020)', function (array $config, string $expected) {
    $service = new BrandingService(brandingConfig($config), '/dark.svg', '/light.svg');
    Functions\when('esc_url_raw')->alias(static fn (string $url): string => 'raw:' . $url);
    Functions\when('wp_enqueue_style')->justReturn();
    Functions\when('wp_enqueue_script')->justReturn();
    Functions\expect('wp_add_inline_style')->once()->with('corex-admin-login', $expected);

    (new AdminBranding($service))->enqueueLoginAssets();
})->with([
    'the bundled lockups' => [[], 'body.login.corex-login{--corex-admin-login-logo:url("raw:/dark.svg")}'
        . 'body.login.corex-login.corex-appearance-light{--corex-admin-login-logo:url("raw:/light.svg")}'
        . '@media (prefers-color-scheme: light){'
        . 'body.login.corex-login:not(.corex-appearance-light):not(.corex-appearance-dark)'
        . '{--corex-admin-login-logo:url("raw:/light.svg")}}'],
    'a logo set in Branding' => [['brand.logo_url' => '/client.png'], 'body.login.corex-login{--corex-admin-login-logo:url("raw:/client.png")}'],
]);

/*
 * The logo's address is written into a CSS string, not into HTML: `esc_url()` would turn `&` into
 * `&#038;` and break an address with two query parameters. It is escaped for that string, once.
 */
it('writes a logo address with query parameters into the login CSS as it is', function () {
    Functions\when('esc_url_raw')->returnArg();
    $url = 'https://cdn.test/logo.png?a=1&b=2';

    expect((new BrandingService(brandingConfig(['brand.logo_url' => $url]), '/d.svg', '/l.svg'))->loginCss($url, $url))
        ->toBe('body.login.corex-login{--corex-admin-login-logo:url("https://cdn.test/logo.png?a=1&b=2")}');
});

it('escapes a backslash, a quote and a line break for the CSS string', function () {
    Functions\when('esc_url_raw')->returnArg();
    $url = "https://cdn.test/a\\b\"c\nd.png";

    expect((new BrandingService(brandingConfig([]), '/d.svg', '/l.svg'))->loginCss($url, $url))
        ->toBe('body.login.corex-login{--corex-admin-login-logo:url("https://cdn.test/a\\\\b\\"c\\a d.png")}');
});

it('cleans the logo address with esc_url_raw before it reaches the CSS', function () {
    Functions\expect('esc_url_raw')->twice()->andReturnUsing(static fn (string $url): string => 'clean:' . $url);

    expect((new BrandingService(brandingConfig([]), '/d.svg', '/l.svg'))->loginCss('/d.svg', '/l.svg'))
        ->toContain('url("clean:/d.svg")')
        ->toContain('url("clean:/l.svg")');
});

it('names the CoreX lockup "CoreX" on the login, and leaves the name of a site\'s own logo to WordPress', function () {
    // WordPress prints the link's text as it is (wp-login.php), so the name comes escaped.
    Functions\when('esc_html__')->alias(static fn (string $text): string => 'escaped:' . $text);

    $bundled = new AdminBranding(new BrandingService(brandingConfig([]), '/dark.svg', '/light.svg'));
    $custom = new AdminBranding(new BrandingService(brandingConfig(['brand.logo_url' => '/client.png']), '/dark.svg', '/light.svg'));

    expect($bundled->loginHeaderText('Powered by WordPress'))->toBe('escaped:CoreX')
        ->and($custom->loginHeaderText('Powered by WordPress'))->toBe('Powered by WordPress');
});

it('names the login\'s logo link through the login_headertext filter', function () {
    $branding = new AdminBranding(new BrandingService(brandingConfig([]), '/dark.svg', '/light.svg'));
    Filters\expectAdded('login_headertext')->once()->with([$branding, 'loginHeaderText']);

    $branding->register();
});
