<?php

/**
 * Safe brand override compatibility contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Support\BootLogger;
use Corex\Tests\Support\ThemeContract;
use Corex\Theme\BrandOverrideValidator;
use Corex\Theme\BrandResolver;

it('defines every required brand override fixture outcome', function () {
    $cases = ThemeContract::json('tests/Fixtures/Theme/brand/cases.json')['cases'];

    expect(array_column($cases, 'shape'))->toEqualCanonicalizing([
        'associative-partial', 'complete-list', 'incomplete-list', 'malformed', 'missing',
    ]);

    foreach ($cases as $case) {
        expect($case)->toHaveKeys([
            'name', 'shape', 'input_path', 'expected_result', 'required_slugs', 'compatibility_note',
        ]);
    }
});

it('preserves associative recursive merge and wholesale list replacement', function () {
    $resolver = new BrandResolver(new BootLogger(debug: false));
    $defaults = ['settings' => [
        'custom' => ['radius' => ['sm' => '4px', 'md' => '8px']],
        'color' => ['palette' => [['slug' => 'primary'], ['slug' => 'surface']]],
    ]];
    $override = ['settings' => [
        'custom' => ['radius' => ['md' => '10px']],
        'color' => ['palette' => [['slug' => 'primary']]],
    ]];

    expect($resolver->merge($defaults, $override))->toBe(['settings' => [
        'custom' => ['radius' => ['sm' => '4px', 'md' => '10px']],
        'color' => ['palette' => [['slug' => 'primary']]],
    ]]);
});

it('accepts complete replacement lists without merge by slug', function () {
    expect(class_exists(BrandOverrideValidator::class))->toBeTrue();

    if (! class_exists(BrandOverrideValidator::class)) {
        return;
    }

    $defaults = ThemeContract::json('theme/theme.json');
    $override = ThemeContract::json('tests/Fixtures/Theme/brand/complete-list.json');
    $result = (new BrandOverrideValidator())->validate($defaults, $override);

    expect($result['issues'])->toBe([])
        ->and($result['overrides'])->toBe($override);
});

it('reports incomplete replacement lists and retains safe defaults', function () {
    expect(class_exists(BrandOverrideValidator::class))->toBeTrue();

    if (! class_exists(BrandOverrideValidator::class)) {
        return;
    }

    $defaults = ThemeContract::json('theme/theme.json');
    $override = ThemeContract::json('tests/Fixtures/Theme/brand/incomplete-list.json');
    $result = (new BrandOverrideValidator())->validate($defaults, $override);
    $merged = (new BrandResolver(new BootLogger(debug: false)))->merge($defaults, $result['overrides']);

    expect($result['issues'])->not->toBeEmpty()
        ->and(ThemeContract::paletteSlugs($merged))->toBe(ThemeContract::paletteSlugs($defaults))
        ->and(ThemeContract::fontFamilySlugs($merged))->toBe(ThemeContract::fontFamilySlugs($defaults));
});

it('leaves defaults intact for missing and malformed brand files', function () {
    $logger = new BootLogger(debug: false);
    $resolver = new BrandResolver($logger);
    $missing = ThemeContract::root() . '/tests/Fixtures/Theme/brand/missing.json';
    $malformed = ThemeContract::root() . '/tests/Fixtures/Theme/brand/malformed.json.txt';

    expect($resolver->read($missing))->toBe([])
        ->and($resolver->read($malformed))->toBe([])
        ->and($logger->messages())->not->toBeEmpty();
});
