<?php

/**
 * Unit tests for the Corex branding service + the bundled identity (spec 016: FR-001, FR-002, SC-001/2).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

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

it('produces login CSS referencing the logo', function () {
    $css = (new BrandingService(brandingConfig([]), '/x.svg'))->loginCss('/x.svg');

    expect($css)->toContain('#login h1 a')->toContain('/x.svg')->toContain('background-size:contain');
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
