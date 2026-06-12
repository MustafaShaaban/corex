<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

use Corex\Health\HealthModule;
use Corex\Health\HealthStatus;

/**
 * `wp corex doctor` — renders the health report (the same probes shown in Tools → Site Health) as
 * readable terminal output and exits non-zero when any check is critical, so it works in CI and
 * over SSH. Thin by design: all judgement lives in the pure HealthReport + probes.
 */
final class DoctorCommand
{
    public function __construct(private readonly HealthModule $module)
    {
    }

    /**
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $report = $this->module->report();
        $rows   = [];

        foreach ($report->results() as $result) {
            $rows[] = [
                'status' => strtoupper($result->status->value),
                'check'  => $result->label,
                'detail' => $result->description,
            ];
        }

        if (function_exists('WP_CLI\Utils\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, ['status', 'check', 'detail']);
        } else {
            foreach ($rows as $row) {
                \WP_CLI::line(sprintf('[%s] %s — %s', $row['status'], $row['check'], $row['detail']));
            }
        }

        if ($report->hasCritical()) {
            \WP_CLI::error(__('Health check failed: one or more critical issues found.', 'corex'));

            return;
        }

        if ($report->overall() === HealthStatus::Recommended) {
            \WP_CLI::warning(__('Health check passed with recommendations.', 'corex'));

            return;
        }

        \WP_CLI::success(__('All Corex health checks passed.', 'corex'));
    }
}
