<?php

/**
 * Unit tests for the advisory image-support probe (spec 048: US4, FR-009).
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Health\HealthStatus;
use Corex\Media\ImageCapability;
use Corex\Media\MediaImageProbe;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('reports good when the server can write WebP', function () {
    $result = (new MediaImageProbe(new ImageCapability(true, false, true, false)))->run();

    expect($result->status)->toBe(HealthStatus::Good)
        ->and($result->id)->toBe('corex_media_images');
});

it('reports recommended (never critical) when WebP is unsupported, with a next action', function () {
    $result = (new MediaImageProbe(new ImageCapability(false, false, false, false)))->run();

    expect($result->status)->toBe(HealthStatus::Recommended)
        ->and($result->actions)->not->toBeEmpty();
});
