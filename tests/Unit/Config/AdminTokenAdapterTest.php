<?php

/**
 * Scoped CoreX admin token adapter contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Foundation\HttpServiceProvider;
use Corex\Tests\Support\ThemeContract;

it('registers one shared admin adapter without globally enqueueing it', function () {
    $adapter = ThemeContract::root() . '/plugins/corex-core/assets/css/corex-admin-tokens.css';
    expect($adapter)->toBeFile();

    if (! is_file($adapter)) {
        return;
    }

    if (! defined('COREX_CORE_FILE')) {
        define('COREX_CORE_FILE', ThemeContract::root() . '/plugins/corex-core/corex-core.php');
    }
    if (! defined('COREX_CORE_VERSION')) {
        define('COREX_CORE_VERSION', 'test');
    }

    $styles = [];
    Functions\when('plugins_url')->alias(
        static fn (string $path): string => 'https://example.test/wp-content/plugins/corex-core/' . $path,
    );
    Functions\when('wp_register_script')->justReturn(true);
    Functions\when('wp_set_script_translations')->justReturn(true);
    Functions\when('wp_register_style')->alias(
        static function (string $handle, string $src, array $dependencies, string $version) use (&$styles): bool {
            $styles[$handle] = compact('src', 'dependencies', 'version');

            return true;
        },
    );
    Functions\expect('wp_enqueue_style')->never();

    (new HttpServiceProvider(new Container()))->registerAssets();

    expect($styles)->toHaveKey('corex-admin-tokens')
        ->and($styles['corex-admin-tokens']['dependencies'])->toBe([]);
});

it('scopes admin roles to CoreX screens and never makes them client brand authority', function () {
    $path = ThemeContract::root() . '/plugins/corex-core/assets/css/corex-admin-tokens.css';
    expect($path)->toBeFile();

    $css = (string) file_get_contents($path);
    $required = [
        '--corex-admin-surface', '--corex-admin-text', '--corex-admin-border', '--corex-admin-action',
        '--corex-admin-success', '--corex-admin-warning', '--corex-admin-error', '--corex-admin-focus',
        '--corex-admin-space-sm', '--corex-admin-radius-md',
    ];

    foreach ($required as $property) {
        expect($css)->toContain($property . ':');
    }

    expect($css)->not->toMatch('/(?:^|,)\s*(?::root|html|body(?!\.login\.corex-login))\b/m')
        ->not->toContain('--wp--preset--');
});

it('loads the adapter only through CoreX owned admin screen styles', function () {
    $owners = [
        'plugins/corex-config/src/Settings/AdminDashboard.php' => 'corex-control-panel',
        'plugins/corex-config/src/Addons/AddonsScreen.php' => 'corex-addons',
        'plugins/corex-config/src/Data/DataAdminScreen.php' => 'corex-data',
        'plugins/corex-config/src/Insights/InsightsScreen.php' => 'corex-insights',
        'addons/corex-captcha/src/CaptchaServiceProvider.php' => 'corex-captcha-admin',
    ];
    $missing = [];

    foreach ($owners as $relative => $handle) {
        $source = (string) file_get_contents(ThemeContract::root() . '/' . $relative);

        if (! str_contains($source, "'{$handle}'") || ! str_contains($source, "['corex-admin-shell']")) {
            $missing[] = $relative;
        }
    }

    expect($missing)->toBe([]);

    $provider = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-core/src/Foundation/HttpServiceProvider.php',
    );
    expect($provider)->toContain("'corex-admin-shell'")
        ->and($provider)->toContain("['corex-admin-tokens']");
});
