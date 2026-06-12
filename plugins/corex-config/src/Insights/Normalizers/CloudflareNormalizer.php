<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights\Normalizers;

defined('ABSPATH') || exit;

/**
 * Turns a Cloudflare URL Scanner result into a security signal for the readiness card — purely.
 * A scan that hasn't finished is reported as `pending` (the readiness provider then shows a
 * "scan in progress" note rather than blocking). A finished scan is scored from the overall
 * malicious verdict and the count of security violations, with recommendations when either is bad.
 */
final class CloudflareNormalizer
{
    private const VIOLATION_PENALTY = 10;

    /**
     * @param array<string,mixed> $payload
     *
     * @return array{status:string,score:int,metrics:list<array{label:string,value:string}>,recommendations:list<string>}
     */
    public function normalize(array $payload): array
    {
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $status = (string) ($result['task']['status'] ?? '');

        if ($status !== 'finished') {
            return [
                'status'          => 'pending',
                'score'           => 0,
                'metrics'         => [],
                'recommendations' => [__('A Cloudflare scan is in progress — run the check again shortly.', 'corex')],
            ];
        }

        $malicious  = ! empty($result['verdicts']['overall']['malicious']);
        $violations = is_array($result['page']['securityViolations'] ?? null)
            ? count($result['page']['securityViolations'])
            : 0;

        $score           = 100 - ($malicious ? 100 : 0) - ($violations * self::VIOLATION_PENALTY);
        $recommendations = [];

        if ($malicious) {
            $recommendations[] = __('Cloudflare flagged this site as malicious — investigate immediately.', 'corex');
        }

        if ($violations > 0) {
            $recommendations[] = sprintf(
                /* translators: %d: number of security issues. */
                __('Resolve %d security issue(s) Cloudflare flagged on the page.', 'corex'),
                $violations
            );
        }

        return [
            'status'  => 'finished',
            'score'   => max(0, $score),
            'metrics' => [
                ['label' => __('Malicious verdict', 'corex'), 'value' => $malicious ? __('Yes', 'corex') : __('No', 'corex')],
                ['label' => __('Security issues', 'corex'), 'value' => (string) $violations],
            ],
            'recommendations' => $recommendations,
        ];
    }
}
