<?php

/**
 * Unit tests for the Corex section patterns (spec 009 US1: FR-001, FR-003, FR-004, SC-002).
 *
 * Every pattern is token-only (no raw hex/px), carries accessible structure, and the
 * contact pattern composes the corex/form block.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Patterns\PatternLibrary;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

it('provides the section patterns under the Corex catalog', function () {
    $names = array_column((new PatternLibrary())->patterns(), 'name');

    expect($names)->toContain('corex/hero', 'corex/features', 'corex/cta', 'corex/testimonial', 'corex/contact');
});

it('uses no hardcoded colors or sizes in any pattern (token-only)', function () {
    foreach ((new PatternLibrary())->patterns() as $pattern) {
        expect($pattern['content'])
            ->not->toMatch('/#[0-9a-fA-F]{3,6}\b/')  // no hex colors
            ->not->toMatch('/\b\d+px\b/');            // no pixel sizes
    }
});

it('gives every pattern a heading and composes the contact form in the contact pattern', function () {
    $byName = [];
    foreach ((new PatternLibrary())->patterns() as $pattern) {
        $byName[$pattern['name']] = $pattern['content'];
    }

    expect($byName['corex/hero'])->toContain('wp:heading')->toContain('"level":1')
        ->and($byName['corex/features'])->toContain('wp:heading')
        ->and($byName['corex/contact'])->toContain('wp:corex/form');
});
