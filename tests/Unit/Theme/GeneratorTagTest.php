<?php

/**
 * Spec 111 — the line in the page head that tells a technology scanner the site runs CoreX.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Theme\GeneratorTag;

beforeEach(function () {
    Functions\when('esc_attr')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
});

it('is the generator meta tag naming CoreX, in the form WordPress prints its own', function () {
    expect((new GeneratorTag())->markup())->toBe('<meta name="generator" content="CoreX" />' . "\n");
});

it('names no version, so the mark cannot tell one release from another', function () {
    expect((new GeneratorTag())->markup())->not->toMatch('/\d/');
});

it('prints the name through esc_attr', function () {
    Functions\when('esc_attr')->alias(static fn (string $text): string => '[escaped ' . $text . ']');

    expect((new GeneratorTag())->markup())->toContain('content="[escaped CoreX]"');
});

it('prints nothing when the site answers the filter with false', function () {
    Filters\expectApplied('corex_generator_tag')->once()->with(true)->andReturn(false);

    expect((new GeneratorTag())->markup())->toBe('');
});
