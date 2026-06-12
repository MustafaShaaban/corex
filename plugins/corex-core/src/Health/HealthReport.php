<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * Aggregates a set of health probes into one report — purely. It runs each probe once, exposes
 * the individual results, and folds them into an overall status (the worst finding wins). With no
 * probes, the site is "good": nothing is wrong. The CLI and the Site Health screen both render
 * from this same report.
 */
final class HealthReport
{
    /** @var list<HealthProbe> */
    private array $probes;

    /** @var list<ProbeResult>|null */
    private ?array $results = null;

    /**
     * @param iterable<HealthProbe> $probes
     */
    public function __construct(iterable $probes)
    {
        $this->probes = is_array($probes) ? array_values($probes) : iterator_to_array($probes, false);
    }

    /**
     * @return list<ProbeResult>
     */
    public function results(): array
    {
        if ($this->results === null) {
            $this->results = array_map(static fn (HealthProbe $probe): ProbeResult => $probe->run(), $this->probes);
        }

        return $this->results;
    }

    public function overall(): HealthStatus
    {
        $worst = HealthStatus::Good;

        foreach ($this->results() as $result) {
            if ($result->status->severity() > $worst->severity()) {
                $worst = $result->status;
            }
        }

        return $worst;
    }

    public function hasCritical(): bool
    {
        return $this->overall() === HealthStatus::Critical;
    }
}
