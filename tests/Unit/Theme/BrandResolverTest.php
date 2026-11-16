<?php

/**
 * Unit tests for the brand-override resolver (spec US2: FR-004, FR-005, SC-003, SC-004).
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Support\BootLogger;
use Corex\Theme\BrandOverrideValidator;
use Corex\Theme\BrandResolver;

it('returns the defaults unchanged when there is no override', function () {
    $defaults = ['settings' => ['color' => ['palette' => [['slug' => 'primary', 'color' => '#000']]]]];

    expect((new BrandResolver(new BootLogger(debug: false)))->merge($defaults, []))->toBe($defaults);
});

it('deep-merges a nested override, preserving sibling keys at every level', function () {
    $defaults = ['settings' => ['color' => ['a' => '1', 'b' => '2'], 'typography' => ['c' => '3']]];
    $brand = ['settings' => ['color' => ['a' => 'OVERRIDDEN']]];

    $merged = (new BrandResolver(new BootLogger(debug: false)))->merge($defaults, $brand);

    expect($merged['settings']['color'])->toBe(['a' => 'OVERRIDDEN', 'b' => '2'])
        ->and($merged['settings']['typography'])->toBe(['c' => '3']); // untouched
});

it('adds an override at a path that does not exist in the defaults', function () {
    $merged = (new BrandResolver(new BootLogger(debug: false)))->merge(['a' => 1], ['b' => 2]);

    expect($merged)->toBe(['a' => 1, 'b' => 2]);
});

it('replaces a list value wholesale (a redefined palette)', function () {
    $defaults = ['palette' => [['slug' => 'a'], ['slug' => 'b']]];
    $brand = ['palette' => [['slug' => 'x']]];

    expect((new BrandResolver(new BootLogger(debug: false)))->merge($defaults, $brand)['palette'])
        ->toBe([['slug' => 'x']]);
});

it('replaces every list shape wholesale without merging by slug', function (array $defaults, array $override) {
    $resolver = new BrandResolver(new BootLogger(debug: false));

    expect($resolver->merge(['value' => $defaults], ['value' => $override])['value'])->toBe($override);
})->with([
    'empty replacement' => [[['slug' => 'a']], []],
    'font family replacement' => [
        [['slug' => 'heading'], ['slug' => 'arabic']],
        [['slug' => 'heading']],
    ],
    'ordered scalar list' => [['one', 'two'], ['replacement']],
]);

it('recursively merges associative maps while replacing a nested list', function () {
    $defaults = [
        'settings' => [
            'custom' => ['radius' => ['sm' => '4px', 'md' => '8px']],
            'color' => ['palette' => [['slug' => 'primary'], ['slug' => 'surface']]],
        ],
    ];
    $brand = [
        'settings' => [
            'custom' => ['radius' => ['md' => '10px']],
            'color' => ['palette' => [['slug' => 'primary']]],
        ],
    ];

    expect((new BrandResolver(new BootLogger(debug: false)))->merge($defaults, $brand))->toBe([
        'settings' => [
            'custom' => ['radius' => ['sm' => '4px', 'md' => '10px']],
            'color' => ['palette' => [['slug' => 'primary']]],
        ],
    ]);
});

it('reads a missing brand.json as empty', function () {
    expect((new BrandResolver(new BootLogger(debug: false)))->read('/no/such/brand.json'))->toBe([]);
});

it('logs and ignores a malformed brand.json', function () {
    $logger = new BootLogger(debug: false);
    $path = sys_get_temp_dir() . '/corex_brand_' . uniqid('', true) . '.json';
    file_put_contents($path, '{ not valid json');

    expect((new BrandResolver($logger))->read($path))->toBe([])
        ->and($logger->messages())->not->toBeEmpty();

    unlink($path);
});

// Spec 134 (the hand-over's open question 4): a brand whose accent is dark sets a light `onAccent`.
it('takes a brand.json\'s own onAccent over the theme\'s and keeps the other custom colours', function () {
    $defaults = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true);
    $brand = ['settings' => ['custom' => ['color' => ['onAccent' => '#ffffff']]]];
    $overrides = (new BrandOverrideValidator())->validate($defaults, $brand)['overrides'];

    $merged = (new BrandResolver(new BootLogger(debug: false)))->merge($defaults, $overrides);

    expect($merged['settings']['custom']['color']['onAccent'])->toBe('#ffffff')
        ->and($merged['settings']['custom']['color']['bandInk'])->toBe($defaults['settings']['custom']['color']['bandInk'])
        ->and($merged['settings']['custom']['radius'])->toBe($defaults['settings']['custom']['radius']);
});
