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
        ->and($blockStyles['core/navigation']['handle'])->toBe('corex-navigation');
});
