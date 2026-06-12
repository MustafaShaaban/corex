<?php

/**
 * Unit tests for the pure health-check aggregator (spec 036 US1: FR-001). The report runs a set
 * of probes and exposes the overall (worst) status — no WordPress involved.
 *
 * @package Corex\Tests\Unit\Health
 */

declare(strict_types=1);

use Corex\Health\HealthProbe;
use Corex\Health\HealthReport;
use Corex\Health\HealthStatus;
use Corex\Health\ProbeResult;

/** A test double that simply returns a fixed result. */
function probe(HealthStatus $status, string $id = 'x'): HealthProbe
{
    return new class($status, $id) implements HealthProbe {
        public function __construct(private HealthStatus $status, private string $id)
        {
        }

        public function run(): ProbeResult
        {
            return new ProbeResult($this->status, $this->id, ucfirst($this->id), 'desc');
        }
    };
}

it('runs every probe and returns a result per probe', function () {
    $report = new HealthReport([probe(HealthStatus::Good, 'a'), probe(HealthStatus::Good, 'b')]);

    expect($report->results())->toHaveCount(2)
        ->and($report->results()[0]->id)->toBe('a')
        ->and($report->results()[0])->toBeInstanceOf(ProbeResult::class);
});

it('reports the worst status as the overall status', function () {
    expect((new HealthReport([probe(HealthStatus::Good), probe(HealthStatus::Recommended)]))->overall())
        ->toBe(HealthStatus::Recommended);

    expect((new HealthReport([probe(HealthStatus::Recommended), probe(HealthStatus::Critical), probe(HealthStatus::Good)]))->overall())
        ->toBe(HealthStatus::Critical);

    expect((new HealthReport([probe(HealthStatus::Good), probe(HealthStatus::Good)]))->overall())
        ->toBe(HealthStatus::Good);
});

it('is good when there are no probes (nothing wrong)', function () {
    expect((new HealthReport([]))->overall())->toBe(HealthStatus::Good)
        ->and((new HealthReport([]))->results())->toBe([]);
});

it('exposes whether the report has any critical finding (for a non-zero CLI exit)', function () {
    expect((new HealthReport([probe(HealthStatus::Critical)]))->hasCritical())->toBeTrue()
        ->and((new HealthReport([probe(HealthStatus::Recommended)]))->hasCritical())->toBeFalse();
});
