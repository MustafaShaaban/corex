<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights\Providers;

defined('ABSPATH') || exit;

use Corex\Config\Insights\InsightProvider;
use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\Normalizers\CloudflareNormalizer;
use Corex\Config\Insights\ReadinessScorer;

/**
 * The Readiness provider: always scores the site's native agent-readiness signals (HTTPS, an
 * `llms.txt`, an XML sitemap, agent-permitting robots, exposed MCP abilities) via the pure
 * {@see ReadinessScorer}, then — only when a Cloudflare token + account are configured — enriches
 * the score with a Cloudflare URL-scan security signal. With no Cloudflare, the card is still
 * useful (native signals) and recommends adding one. `run()` never throws (Principle IX).
 */
final class ReadinessProvider implements InsightProvider
{
    private const CF_ENDPOINT = 'https://api.cloudflare.com/client/v4/accounts/%s/urlscanner/scan';

    public function __construct(
        private readonly ReadinessScorer $scorer,
        private readonly CloudflareNormalizer $cloudflare,
        private readonly string $cfToken = '',
        private readonly string $cfAccount = '',
    ) {
    }

    public function id(): string
    {
        return 'readiness';
    }

    public function label(): string
    {
        return __('Readiness', 'corex');
    }

    public function run(string $url): InsightResult
    {
        $native          = $this->scorer->score($this->nativeSignals($url));
        $score           = $native['score'];
        $metrics         = $native['metrics'];
        $recommendations = $native['recommendations'];

        if ($this->cfToken !== '' && $this->cfAccount !== '') {
            $cf = $this->cloudflareScan($url);

            if ($cf !== null && $cf['status'] === 'finished') {
                $score   = (int) round(($score + $cf['score']) / 2);
                $metrics = array_merge($metrics, $cf['metrics']);
            }

            if ($cf !== null) {
                $recommendations = array_merge($recommendations, $cf['recommendations']);
            }
        } else {
            $recommendations[] = __('Add a Cloudflare API token + account ID in Settings to include a security scan.', 'corex');
        }

        return new InsightResult(
            $this->id(),
            $this->label(),
            $score,
            sprintf(/* translators: %d: readiness score. */ __('Agent-readiness score: %d/100.', 'corex'), $score),
            $metrics,
            $recommendations,
            time(),
        );
    }

    /**
     * @return array<string,bool>
     */
    private function nativeSignals(string $url): array
    {
        return [
            'https'         => is_ssl() || str_starts_with((string) home_url(), 'https://'),
            'llms_txt'      => $this->remoteOk((string) home_url('/llms.txt')),
            'sitemap'       => $this->remoteOk((string) home_url('/wp-sitemap.xml'))
                || $this->remoteOk((string) home_url('/sitemap.xml')),
            'robots_agents' => $this->robotsAllowsAgents(),
            'mcp_abilities' => function_exists('wp_get_abilities') && wp_get_abilities() !== [],
        ];
    }

    private function remoteOk(string $url): bool
    {
        $response = wp_remote_head($url, ['timeout' => 8, 'redirection' => 2]);

        return ! is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200;
    }

    private function robotsAllowsAgents(): bool
    {
        $response = wp_remote_get((string) home_url('/robots.txt'), ['timeout' => 8]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = (string) wp_remote_retrieve_body($response);

        // A blanket "Disallow: /" under a wildcard agent blocks crawlers/agents.
        return stripos($body, "Disallow: /\n") === false && trim($body) !== '';
    }

    /**
     * @return array{status:string,score:int,metrics:list<array{label:string,value:string}>,recommendations:list<string>}|null
     */
    private function cloudflareScan(string $url): ?array
    {
        $response = wp_remote_post(sprintf(self::CF_ENDPOINT, $this->cfAccount), [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->cfToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode(['url' => $url]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($data) ? $this->cloudflare->normalize($data) : null;
    }
}
