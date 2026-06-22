<?php

/**
 * CoreX asset helper facades (spec 062): the script-options core, the base registry, and the
 * Style/Script/Image/Picture facades that resolve URL + version through an AssetManager. SCSS is
 * never enqueued; a sibling *.asset.php is merged; <picture> appears only when a .webp sibling
 * exists on disk. Pure cores are unit-tested; the WP facade calls are exercised with stubs.
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Assets\AssetEnvironment;
use Corex\Assets\AssetManager;
use Corex\Assets\AssetRegistry;
use Corex\Assets\Assets;
use Corex\Assets\AssetVersion;
use Corex\Assets\BuildManifest;
use Corex\Assets\Image;
use Corex\Assets\Picture;
use Corex\Assets\Script;
use Corex\Assets\ScriptOptions;
use Corex\Assets\Style;

function helperBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_asset_helpers_' . uniqid('', true);
    mkdir($dir . '/css', 0777, true);
    mkdir($dir . '/js', 0777, true);
    mkdir($dir . '/images', 0777, true);
    file_put_contents($dir . '/css/app.css', 'body{}');
    file_put_contents($dir . '/js/app.js', 'console.log(1)');
    file_put_contents($dir . '/images/hero.jpg', 'jpg');

    return $dir;
}

function swapRegistry(string $base): void
{
    $manager  = new AssetManager($base, 'https://acme.local/assets', AssetEnvironment::from('production'), BuildManifest::fromArray([]), '1.0.0', new AssetVersion());
    $registry = new AssetRegistry();
    $registry->register('client', $manager, true);
    Assets::swap($registry);
}

afterEach(function () {
    Assets::swap(null);
});

it('merges asset-file deps + caller deps and resolves the loading strategy and version', function () {
    $opts = ScriptOptions::from(
        ['deps' => ['jquery'], 'defer' => true, 'in_footer' => true],
        ['dependencies' => ['wp-element'], 'version' => 'abc123'],
    );

    expect($opts->deps)->toBe(['wp-element', 'jquery'])
        ->and($opts->strategy)->toBe('defer')
        ->and($opts->inFooter)->toBeTrue()
        ->and($opts->version)->toBe('abc123')        // asset-file version (no caller override)
        ->and($opts->wpArgs())->toBe(['in_footer' => true, 'strategy' => 'defer']);

    // caller version overrides the asset-file version; async strategy
    $override = ScriptOptions::from(['async' => true, 'version' => '9'], ['version' => 'x']);
    expect($override->version)->toBe('9')->and($override->strategy)->toBe('async');
});

it('registers named bases with a default and throws on an unknown base', function () {
    $m = new AssetManager('/x', 'https://x', AssetEnvironment::from('local'), BuildManifest::fromArray([]), '1', new AssetVersion());
    $r = new AssetRegistry();
    $r->register('corex', $m);
    $r->register('client', $m, true);

    expect($r->defaultName())->toBe('client')
        ->and($r->has('corex'))->toBeTrue()
        ->and($r->manager('corex'))->toBe($m)
        ->and(fn () => $r->manager('nope'))->toThrow(RuntimeException::class);
});

it('refuses to enqueue a Sass source file and reports the misuse', function () {
    swapRegistry(helperBase());
    Functions\when('esc_html')->returnArg(1);
    Functions\when('_doing_it_wrong')->justReturn(null);
    // wp_enqueue_style must NOT be called for a .scss path.
    Functions\expect('wp_enqueue_style')->never();

    expect(Style::isScss('css/app.scss'))->toBeTrue()
        ->and(Style::enqueue('app', 'scss/app.scss'))->toBeFalse();
});

it('enqueues a stylesheet with the resolved URL and version', function () {
    swapRegistry(helperBase());
    $captured = null;
    Functions\when('wp_enqueue_style')->alias(function (...$args) use (&$captured) {
        $captured = $args;
    });

    expect(Style::enqueue('app', 'css/app.css', ['deps' => ['dep']]))->toBeTrue();
    expect($captured[0])->toBe('app')
        ->and($captured[1])->toBe('https://acme.local/assets/css/app.css')
        ->and($captured[2])->toBe(['dep'])
        ->and($captured[3])->toBeString()           // a real version token
        ->and($captured[4])->toBe('all');
});

it('enqueues a script with deps, strategy, and an explicit module type', function () {
    $base = helperBase();
    file_put_contents($base . '/js/app.asset.php', "<?php return ['dependencies' => ['wp-i18n'], 'version' => 'h1'];");
    swapRegistry($base);

    $captured = null;
    Functions\when('wp_enqueue_script')->alias(function (...$args) use (&$captured) {
        $captured = $args;
    });
    Functions\expect('add_filter')->atLeast()->once(); // module tag filter

    Script::enqueueModule('app', 'js/app.js', ['defer' => true]);

    expect($captured[0])->toBe('app')
        ->and($captured[1])->toBe('https://acme.local/assets/js/app.js')
        ->and($captured[2])->toBe(['wp-i18n'])       // from the .asset.php
        ->and($captured[3])->toBe('h1')              // .asset.php version
        ->and($captured[4])->toBe(['in_footer' => true, 'strategy' => 'defer']);
});

it('renders an img tag and a picture only when a built .webp sibling exists', function () {
    $base = helperBase();
    swapRegistry($base);
    Functions\when('esc_url')->returnArg(1);
    Functions\when('esc_attr')->returnArg(1);

    $img = Image::tag('images/hero.jpg', ['alt' => 'Hero', 'class' => 'hero']);
    expect($img)->toContain('<img ')
        ->toContain('src="https://acme.local/assets/images/hero.jpg"')
        ->toContain('alt="Hero"')->toContain('class="hero"')->toContain('loading="lazy"');

    // no webp sibling yet → plain <img>
    expect(Picture::render('images/hero.jpg', ['alt' => 'Hero']))->not->toContain('<picture>');

    // add the built sibling on disk → <picture> with a webp source
    file_put_contents($base . '/images/hero.webp', 'webp');
    $picture = Picture::render('images/hero.jpg', ['alt' => 'Hero']);
    expect($picture)->toContain('<picture>')
        ->toContain('type="image/webp"')
        ->toContain('https://acme.local/assets/images/hero.webp');
});
