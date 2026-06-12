<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights\Normalizers;

defined('ABSPATH') || exit;

use Corex\Config\Insights\InsightResult;

/**
 * Turns a PageSpeed Insights (Lighthouse v5) payload into a scored {@see InsightResult} — purely.
 * It reads the performance category score, pulls the Core Web Vitals from the named audits, and
 * surfaces the highest-impact opportunities (largest estimated savings first) as recommendations.
 * A payload it cannot read degrades to a graceful "couldn't read" recommended result.
 */
final class PsiNormalizer
{
    private const PROVIDER = 'performance';

    /** The Core Web Vitals audits, in display order. */
    private const VITALS = [
        'largest-contentful-paint'  => 'Largest Contentful Paint',
        'interaction-to-next-paint' => 'Interaction to Next Paint',
        'cumulative-layout-shift'   => 'Cumulative Layout Shift',
        'first-contentful-paint'    => 'First Contentful Paint',
        'total-blocking-time'       => 'Total Blocking Time',
    ];

    /**
     * @param array<string,mixed> $payload
     */
    public function normalize(array $payload, int $now = 0): InsightResult
    {
        $lighthouse = $payload['lighthouseResult'] ?? null;
        $rawScore   = $lighthouse['categories']['performance']['score'] ?? null;

        if (! is_array($lighthouse) || ! is_numeric($rawScore)) {
            return new InsightResult(
                self::PROVIDER,
                'Performance',
                50,
                __('The performance result could not be read.', 'corex'),
                [],
                [__('Check the site URL and (optionally) the PageSpeed Insights API key, then run the check again.', 'corex')],
                $now,
            );
        }

        $audits = is_array($lighthouse['audits'] ?? null) ? $lighthouse['audits'] : [];
        $score  = (int) round(((float) $rawScore) * 100);

        return new InsightResult(
            self::PROVIDER,
            'Performance',
            $score,
            sprintf(__('Lighthouse performance score: %d/100.', 'corex'), $score),
            $this->vitals($audits),
            $this->opportunities($audits),
            $now,
        );
    }

    /**
     * @param array<string,mixed> $audits
     *
     * @return list<array{label:string,value:string}>
     */
    private function vitals(array $audits): array
    {
        $metrics = [];

        foreach (self::VITALS as $id => $label) {
            $value = $audits[$id]['displayValue'] ?? null;

            if (is_string($value) && $value !== '') {
                $metrics[] = ['label' => $label, 'value' => $value];
            }
        }

        return $metrics;
    }

    /**
     * The under-performing audits with the largest estimated savings, worst first (top 3).
     *
     * @param array<string,mixed> $audits
     *
     * @return list<string>
     */
    private function opportunities(array $audits): array
    {
        $opportunities = [];

        foreach ($audits as $audit) {
            $score   = $audit['score'] ?? null;
            $title   = $audit['title'] ?? '';
            $savings = $audit['details']['overallSavingsMs'] ?? null;

            if (is_numeric($score) && $score < 0.9 && is_string($title) && $title !== '' && is_numeric($savings)) {
                $opportunities[] = ['title' => $title, 'savings' => (float) $savings];
            }
        }

        usort($opportunities, static fn (array $a, array $b): int => $b['savings'] <=> $a['savings']);

        return array_map(static fn (array $o): string => $o['title'], array_slice($opportunities, 0, 3));
    }
}
