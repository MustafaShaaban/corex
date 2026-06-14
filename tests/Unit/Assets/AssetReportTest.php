<?php

/**
 * Unit tests for the assets:doctor report (spec 047: US4, FR-010).
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Corex\Assets\AssetEnvironment;
use Corex\Assets\AssetReport;

it('reports the environment, manifest presence, and source-map exposure', function () {
    $report = (new AssetReport())->build(AssetEnvironment::from('production'), false, ['build/app.css' => '0.25.0']);

    expect($report['environment'])->toBe('production')
        ->and($report['manifest'])->toBe('absent')
        ->and($report['source_maps'])->toBe('hidden')
        ->and($report['samples'])->toBe(['build/app.css' => '0.25.0']);
});

it('reports source maps exposed only in local', function () {
    $report = (new AssetReport())->build(AssetEnvironment::from('local'), true, []);

    expect($report['source_maps'])->toBe('exposed')
        ->and($report['manifest'])->toBe('present');
});

it('formats the report into readable lines and carries no secret', function () {
    $lines = (new AssetReport())->lines(
        (new AssetReport())->build(AssetEnvironment::from('staging'), true, ['a.css' => 'abc'])
    );

    $text = implode("\n", $lines);

    expect($text)->toContain('staging')
        ->and($text)->toContain('a.css')
        ->and($text)->not->toContain('secret')
        ->and($text)->not->toContain('key=');
});
