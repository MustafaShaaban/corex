<?php

/**
 * Unit tests for the stub renderer (spec FR-001, FR-003).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Generators\UnresolvedPlaceholderException;

it('replaces every known placeholder', function () {
    $out = (new StubRenderer())->render(
        'class {{ class }} in {{ namespace }} prefix {{ prefix }}',
        ['class' => 'Career', 'namespace' => 'App\\Models', 'prefix' => 'corex'],
    );

    expect($out)->toBe('class Career in App\\Models prefix corex');
});

it('throws on a leftover, unprovided placeholder', function () {
    expect(fn () => (new StubRenderer())->render('{{ class }} {{ missing }}', ['class' => 'X']))
        ->toThrow(UnresolvedPlaceholderException::class);
});
