<?php

/**
 * Unit tests for the individual health probes (spec 036 US1: FR-001). Each probe takes the one
 * value it judges (injected), so it is checked headlessly with no WordPress.
 *
 * @package Corex\Tests\Unit\Health
 */

declare(strict_types=1);

use Corex\Health\HealthStatus;
use Corex\Health\Probes\BrandPresentProbe;
use Corex\Health\Probes\PhpVersionProbe;
use Corex\Health\Probes\ThemeActiveProbe;
use Corex\Health\Probes\UploadsWritableProbe;
use Corex\Health\Probes\WpVersionProbe;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

it('flags an outdated PHP version as critical, a supported one as good', function () {
    expect((new PhpVersionProbe('8.1.0', '8.3'))->run()->status)->toBe(HealthStatus::Critical);
    expect((new PhpVersionProbe('8.3.2', '8.3'))->run()->status)->toBe(HealthStatus::Good);
});

it('flags an outdated WordPress version as critical, a supported one as good', function () {
    expect((new WpVersionProbe('6.5', '7.0'))->run()->status)->toBe(HealthStatus::Critical);
    expect((new WpVersionProbe('7.0', '7.0'))->run()->status)->toBe(HealthStatus::Good);
});

it('recommends a block theme when the active theme is classic, good when it is FSE', function () {
    expect((new ThemeActiveProbe(false))->run()->status)->toBe(HealthStatus::Recommended);
    expect((new ThemeActiveProbe(true))->run()->status)->toBe(HealthStatus::Good);
});

it('treats a missing brand.json as advisory (recommended), present as good', function () {
    expect((new BrandPresentProbe(false))->run()->status)->toBe(HealthStatus::Recommended);
    expect((new BrandPresentProbe(true))->run()->status)->toBe(HealthStatus::Good);
});

it('flags a non-writable uploads dir as critical, writable as good, with actions on failure', function () {
    $bad = (new UploadsWritableProbe(false))->run();

    expect($bad->status)->toBe(HealthStatus::Critical)
        ->and($bad->actions)->not->toBe([]);
    expect((new UploadsWritableProbe(true))->run()->status)->toBe(HealthStatus::Good);
});

it('every probe result carries a non-empty id, label and description', function () {
    $results = [
        (new PhpVersionProbe('8.3', '8.3'))->run(),
        (new ThemeActiveProbe(false))->run(),
        (new BrandPresentProbe(true))->run(),
    ];

    foreach ($results as $result) {
        expect($result->id)->not->toBe('')
            ->and($result->label)->not->toBe('')
            ->and($result->description)->not->toBe('');
    }
});
