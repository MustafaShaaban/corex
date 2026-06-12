<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights\Providers;

defined('ABSPATH') || exit;

use Corex\Config\Insights\InsightProvider;
use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\Normalizers\PsiNormalizer;

/**
 * The Performance provider: fetches Google PageSpeed Insights (Lighthouse) for the site URL and
 * hands the JSON to the pure {@see PsiNormalizer}. The API key is optional (PSI allows low-volume
 * keyless use); a transport error or unreadable body degrades to a graceful recommended result
 * (Principle IX) — `run()` never throws. Thin boundary: all judgement lives in the normaliser.
 */
final class PerformanceProvider implements InsightProvider
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(
        private readonly PsiNormalizer $normalizer,
        private readonly string $apiKey = '',
    ) {
    }

    public function id(): string
    {
        return 'performance';
    }

    public function label(): string
    {
        return __('Performance', 'corex');
    }

    public function run(string $url): InsightResult
    {
        $query = ['url' => $url, 'category' => 'performance', 'strategy' => 'mobile'];

        if ($this->apiKey !== '') {
            $query['key'] = $this->apiKey;
        }

        $response = wp_remote_get(add_query_arg($query, self::ENDPOINT), ['timeout' => 30]);

        if (is_wp_error($response)) {
            return $this->unavailable();
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($data)) {
            return $this->unavailable();
        }

        return $this->normalizer->normalize($data, time());
    }

    private function unavailable(): InsightResult
    {
        return new InsightResult(
            $this->id(),
            $this->label(),
            50,
            __('The performance check could not reach PageSpeed Insights.', 'corex'),
            [],
            [__('Check connectivity and run the check again; adding a PageSpeed Insights API key in Settings improves reliability.', 'corex')],
            time(),
        );
    }
}
