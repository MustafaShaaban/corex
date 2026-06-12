<?php

/**
 * Unit tests for the pure score→grade mapping (spec 037: FR-001).
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\Grade;
use Corex\Health\HealthStatus;

it('maps a score to a letter grade', function () {
    expect(Grade::letter(95))->toBe('A')
        ->and(Grade::letter(85))->toBe('B')
        ->and(Grade::letter(72))->toBe('C')
        ->and(Grade::letter(55))->toBe('D')
        ->and(Grade::letter(20))->toBe('F');
});

it('maps a score to a health status', function () {
    expect(Grade::status(95))->toBe(HealthStatus::Good)
        ->and(Grade::status(70))->toBe(HealthStatus::Recommended)
        ->and(Grade::status(30))->toBe(HealthStatus::Critical);
});

it('clamps a score to the 0..100 range', function () {
    expect(Grade::clamp(140))->toBe(100)
        ->and(Grade::clamp(-5))->toBe(0)
        ->and(Grade::clamp(63))->toBe(63);
});
