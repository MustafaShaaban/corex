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
use Corex\Config\Insights\PsiDiagnostic;
use Corex\Config\Insights\SiteUrlReachability;

/**
 * The Performance provider: fetches Google PageSpeed Insights (Lighthouse) for the site URL and
 * hands the JSON to the pure {@see PsiNormalizer}. The API key is optional (PSI allows low-volume
 * keyless use); a transport error or unreadable body is a failed result with its reason and no
 * score (Principle IX) — `run()` never throws. Thin boundary: all judgement lives in the normaliser.
 */
final class PerformanceProvider implements InsightProvider
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(
        private readonly PsiNormalizer $normalizer,
        private readonly SiteUrlReachability $reachability,
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
        // PageSpeed can only crawl a public URL — catch a local/private one before the call
        // and explain it specifically (spec 044, FR-011) instead of a generic failure.
        if (! $this->reachability->isPublic($url)) {
            return $this->diagnostic(PsiDiagnostic::classify(false, 0, null));
        }

        $query = ['url' => $url, 'category' => 'performance', 'strategy' => 'mobile'];

        if ($this->apiKey !== '') {
            $query['key'] = $this->apiKey;
        }

        $response = wp_remote_get(add_query_arg($query, self::ENDPOINT), ['timeout' => 30]);

        if (is_wp_error($response)) {
            return $this->diagnostic(PsiDiagnostic::unreachable());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode((string) wp_remote_retrieve_body($response), true);
        $body   = is_array($body) ? $body : null;

        $diagnostic = PsiDiagnostic::classify(true, $status, $body);

        if ($diagnostic->kind === PsiDiagnostic::OK && $body !== null) {
            return $this->normalizer->normalize($body, time());
        }

        return $this->diagnostic($diagnostic);
    }

    /**
     * A run that could not complete: the classified, actionable reason and no score (spec 114,
     * US-SAFE-12). It was a score of 50, graded D. Still never a fatal (Principle IX).
     */
    private function diagnostic(PsiDiagnostic $diagnostic): InsightResult
    {
        return InsightResult::failed($this->id(), $this->label(), $diagnostic->kind, $diagnostic->message, time());
    }
}
