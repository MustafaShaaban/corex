<?php

/**
 * Unit tests for spec 055 native-first UI readiness.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\ComponentCoverageDefaults;
use Corex\Cli\Release\ComponentCoverageItem;
use Corex\Cli\Release\ComponentCoverageMatrix;

it('keeps the default component matrix native-first and outside visual redesign scope', function () {
    $matrix = ComponentCoverageDefaults::matrix();

    expect($matrix->nativeFirstViolations())->toBe([])
        ->and($matrix->visualRedesignItems())->toBe([]);

    expect($matrix->itemFor('media')->mechanism)->toBe('wordpress-core-block-style')
        ->and($matrix->itemFor('navigation')->mechanism)->toBe('wordpress-core-block-style')
        ->and($matrix->itemFor('page templates')->mechanism)->toBe('pattern');
});

it('reports custom block scope when a native WordPress mechanism should be preferred', function () {
    $matrix = ComponentCoverageMatrix::fromItems([
        ComponentCoverageItem::fromArray([
            'need' => 'media',
            'mechanism' => 'corex-block',
            'source' => 'new custom block: client media gallery',
            'accessibility' => 'WCAG 2.2 AA keyboard image semantics',
            'tokenStrategy' => 'theme.json CSS variables',
            'rtlStrategy' => 'logical properties',
            'freePro' => 'free-core',
        ]),
    ]);

    expect($matrix->nativeFirstViolations())->toContain('media:new custom block: client media gallery');
});

it('reports visual redesign scope explicitly', function () {
    $matrix = ComponentCoverageMatrix::fromItems([
        ComponentCoverageItem::fromArray([
            'need' => 'site styling',
            'mechanism' => 'deferred',
            'source' => 'final Corex visual redesign',
            'accessibility' => 'WCAG 2.2 AA review required',
            'tokenStrategy' => 'theme.json CSS variables',
            'rtlStrategy' => 'logical properties',
            'freePro' => 'deferred',
        ]),
    ]);

    expect($matrix->visualRedesignItems())->toContain('site styling:final Corex visual redesign');
});

