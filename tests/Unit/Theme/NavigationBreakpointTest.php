<?php

/**
 * Spec 137, slice 1 (US-NAV-11; FR-004; DECISIONS #692): the width a navigation breakpoint token
 * stands for.
 *
 * Only a whole or decimal number of `px` is taken. Anything else is the CoreX theme's own width, and
 * a width below WordPress's own collapse point is read as that point.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Theme\NavigationBreakpoint;

it('takes a whole or a decimal number of px as it is (FR-004)', function (string $token, string $width) {
    expect((new NavigationBreakpoint())->width($token))->toBe($width);
})->with([
    'the default' => ['782px', '782px'],
    'a child\'s whole number' => ['1024px', '1024px'],
    'a decimal' => ['1023.5px', '1023.5px'],
    'the default written with a decimal' => ['782.0px', '782px'],
    'the floor itself' => ['600px', '600px'],
]);

it('reads a width below WordPress\'s 600px as 600px (FR-004)', function (string $token) {
    expect((new NavigationBreakpoint())->width($token))->toBe('600px');
})->with([
    'just below' => ['599px'],
    'a small decimal' => ['320.5px'],
    'zero' => ['0px'],
]);

it('falls back to the default for anything that is not a number of px (FR-004)', function (mixed $token) {
    expect((new NavigationBreakpoint())->width($token))->toBe('782px');
})->with([
    'another unit' => ['64em'],
    'rem' => ['48rem'],
    'a percentage' => ['50%'],
    'no unit' => ['1024'],
    'negative' => ['-800px'],
    'a word' => ['wide'],
    'empty' => [''],
    'a space before' => [' 1024px'],
    'a line after' => ["1024px\n"],
    'CSS after it' => ['1024px) { body { display: none } } @media (width >= 1px'],
    'a custom property' => ['var(--wp--custom--nav--breakpoint)'],
    'a leading dot' => ['.5px'],
    'not set: WordPress answers with the whole settings array' => [['custom' => []]],
    'a number, not a string' => [1024],
    'null' => [null],
    'a number too large to be finite' => [str_repeat('9', 400) . 'px'],
]);

it('says whether a width is the default the stylesheet is written with', function () {
    $breakpoint = new NavigationBreakpoint();

    expect($breakpoint->isDefault('782px'))->toBeTrue()
        ->and($breakpoint->isDefault($breakpoint->width('782.0px')))->toBeTrue()
        ->and($breakpoint->isDefault('1024px'))->toBeFalse();
});
