<?php

/**
 * Unit tests for spec 055 component coverage readiness matrix.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\ComponentCoverageDefaults;
use Corex\Cli\Release\ComponentCoverageItem;
use Corex\Cli\Release\ComponentCoverageMatrix;

it('classifies every required company-site need with complete readiness metadata', function () {
    $matrix = ComponentCoverageDefaults::matrix();

    expect($matrix->missingNeeds([
        'home',
        'about',
        'services',
        'contact',
        'careers',
        'portfolio',
        'forms',
        'listings',
        'cards',
        'testimonials',
        'ctas',
        'media',
        'navigation',
        'page templates',
    ]))->toBe([])
        ->and($matrix->unknownMechanisms())->toBe([]);

    foreach ($matrix->items() as $item) {
        expect($item->source)->not->toBe('')
            ->and($item->accessibility)->toContain('WCAG 2.2 AA')
            ->and($item->tokenStrategy)->toContain('theme.json')
            ->and($item->rtlStrategy)->toContain('logical')
            ->and($item->freePro)->toBeIn(['free-core', 'pro-candidate', 'deferred', 'out-of-scope']);
    }
});

it('rejects unknown component mechanisms before a matrix can pass readiness', function () {
    expect(fn () => ComponentCoverageItem::fromArray([
        'need' => 'experimental hero',
        'mechanism' => 'unknown',
        'source' => 'new custom block',
        'accessibility' => 'WCAG 2.2 AA keyboard and labels',
        'tokenStrategy' => 'theme.json CSS variables',
        'rtlStrategy' => 'logical properties',
        'freePro' => 'free-core',
    ]))->toThrow(InvalidArgumentException::class);
});

