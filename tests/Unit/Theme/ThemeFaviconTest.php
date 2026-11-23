<?php

/**
 * The CoreX theme's favicon (spec 134, US-BRD-05, FR-022, SC-007; DECISIONS #637).
 *
 * The favicon is presentation, so it is the theme's (constitution, principle I): the theme links its
 * own copies of the logo set's favicon when the site has no site icon, and nothing when it has one,
 * so WordPress's own icon links stand alone. No plugin links a favicon, so another theme gets none
 * from CoreX. The copies are held equal to the logo folder's by LogoAssetContractTest.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\ThemeContract;

require_once ThemeContract::root() . '/theme/inc/favicon.php';

/** The links the theme prints into the head, for a site with or without a site icon. */
function theme_favicon_head(bool $hasSiteIcon, string $activeTheme = 'corex'): string
{
    Functions\when('has_site_icon')->justReturn($hasSiteIcon);
    // The active theme: the CoreX theme itself, or a child of it whose stylesheet is another folder.
    Functions\when('get_stylesheet')->justReturn($activeTheme);
    Functions\when('get_template')->justReturn('corex');
    Functions\when('get_template_directory_uri')->justReturn('https://example.test/wp-content/themes/corex');
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_attr')->returnArg();

    ob_start();
    corex_theme_favicon_links();

    return (string) ob_get_clean();
}

it('links the CoreX favicon from the theme when the site has no site icon (FR-022, TC-BRD-05-04)', function () {
    $base = 'https://example.test/wp-content/themes/corex/assets/favicon/';

    expect(theme_favicon_head(false))->toBe(
        '<link rel="icon" href="' . $base . 'corex-favicon.svg" type="image/svg+xml" />' . "\n"
        . '<link rel="icon" href="' . $base . 'corex-favicon-32.png" sizes="32x32" type="image/png" />' . "\n"
        . '<link rel="icon" href="' . $base . 'corex-favicon-16.png" sizes="16x16" type="image/png" />' . "\n"
        . '<link rel="icon" href="' . $base . 'corex-favicon-512.png" sizes="512x512" type="image/png" />' . "\n"
        . '<link rel="apple-touch-icon" href="' . $base . 'corex-favicon-180.png" />' . "\n",
    );
});

it('links only files the theme holds', function () {
    preg_match_all('#/themes/corex/([^"]+)"#', theme_favicon_head(false), $files);

    expect($files[1])->toHaveCount(5);

    foreach ($files[1] as $file) {
        expect(ThemeContract::root() . '/theme/' . $file)->toBeFile();
    }
});

it('links nothing when the site has a site icon, so only the site\'s icon is linked (FR-022, scenario 7)', function () {
    expect(theme_favicon_head(true))->toBe('');
});

it('is hooked into the front end\'s head by the theme', function () {
    Functions\expect('add_action')->once()->with('wp_head', 'corex_theme_favicon_links', 99);

    require ThemeContract::root() . '/theme/functions.php';
});

it('adds no favicon from any plugin, add-on or package, so another theme gets none from CoreX (FR-022, SC-007)', function () {
    $found = [];

    foreach (['plugins', 'addons', 'packages'] as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(ThemeContract::root() . '/' . $root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if ($file->getExtension() !== 'php' || preg_match('#/(?:vendor|node_modules|build)/#', $path)) {
                continue;
            }

            if (preg_match('/rel=[\'"\\\\]*(?:shortcut )?icon|apple-touch-icon|corex-favicon/i', (string) file_get_contents($path))) {
                $found[] = substr($path, strlen(ThemeContract::root()) + 1);
            }
        }
    }

    expect($found)->toBe([]);
});

it('links nothing under a child of the CoreX theme, whose site is the client\'s (spec 135, FR-016)', function () {
    // The CoreX theme's functions.php runs under a child too: a client site must not show the CoreX mark.
    expect(theme_favicon_head(false, 'acme-theme'))->toBe('');
});
