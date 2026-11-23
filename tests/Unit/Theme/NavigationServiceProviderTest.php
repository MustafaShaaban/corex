<?php

/**
 * Spec 058 — NavigationServiceProvider: pattern category + conditional nav asset.
 *
 * Verifies the provider registers the `corex` block-pattern category and attaches
 * the `corex-navigation` stylesheet to `core/navigation` (conditional, Principle VI)
 * without ever globally enqueueing it.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Theme\NavigationCollapseStyle;
use Corex\Theme\NavigationServiceProvider;
use Corex\Tests\Support\ThemeContract;

it('registers the CoreX block-pattern category', function () {
    $registered = [];
    Functions\when('register_block_pattern_category')->alias(
        static function (string $name, array $properties) use (&$registered): bool {
            $registered[$name] = $properties;

            return true;
        },
    );
    Functions\when('__')->returnArg();

    (new NavigationServiceProvider(new Container()))->registerPatternCategory();

    expect($registered)->toHaveKey('corex')
        ->and($registered['corex'])->toHaveKey('label');
});

it('attaches the navigation stylesheet to core/navigation and never globally enqueues it', function () {
    $css = ThemeContract::root() . '/theme/assets/css/corex-navigation.css';
    expect($css)->toBeFile();

    if (! defined('COREX_CORE_VERSION')) {
        define('COREX_CORE_VERSION', 'test');
    }

    $blockStyles = [];
    Functions\when('get_theme_file_path')->alias(
        static fn (string $relative): string => ThemeContract::root() . '/theme/' . $relative,
    );
    Functions\when('get_theme_file_uri')->alias(
        static fn (string $relative): string => 'https://example.test/theme/' . $relative,
    );
    Functions\when('wp_enqueue_block_style')->alias(
        static function (string $blockName, array $args) use (&$blockStyles): bool {
            $blockStyles[$blockName] = $args;

            return true;
        },
    );
    Functions\expect('wp_enqueue_style')->never();

    (new NavigationServiceProvider(new Container()))->registerNavigationStyle();

    expect($blockStyles)->toHaveKey('core/navigation')
        ->and($blockStyles)->toHaveKey('corex/copyright')
        ->and($blockStyles['core/navigation']['handle'])->toBe('corex-navigation');
});

it('wires the collapse stylesheet where a navigation renders and where every block style loads (spec 137, FR-004)', function () {
    $container = new Container();
    $provider = new NavigationServiceProvider($container);

    $provider->register();
    $provider->boot();

    $collapse = $container->make(NavigationCollapseStyle::class);

    expect($collapse)->toBe($container->make(NavigationCollapseStyle::class))
        ->and(has_filter('render_block', [$collapse, 'enqueueOnRender']))->toBe(10)
        ->and(has_action('enqueue_block_assets', [$collapse, 'enqueueWhereEveryBlockLoads']))->toBe(10);
});

it('enqueues the behavior script only when a navigation or mega block renders', function () {
    Functions\when('wp_script_is')->justReturn(true);
    $enqueued = [];
    Functions\when('wp_enqueue_script')->alias(
        static function (string $handle) use (&$enqueued): bool {
            $enqueued[] = $handle;

            return true;
        },
    );

    $provider = new NavigationServiceProvider(new Container());

    $provider->maybeEnqueueNavigationScript('x', ['blockName' => 'core/paragraph']);
    expect($enqueued)->toBe([]);

    $provider->maybeEnqueueNavigationScript('x', ['blockName' => 'core/navigation']);
    $provider->maybeEnqueueNavigationScript('x', [
        'blockName' => 'core/details',
        'attrs' => ['className' => 'corex-mega corex-mega--services'],
    ]);

    expect($enqueued)->toBe(['corex-navigation', 'corex-navigation']);
});
