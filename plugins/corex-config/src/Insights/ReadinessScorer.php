<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * Scores a site's agent- & delivery-readiness from native signals — purely. Each signal is the
 * modern checklist for AI agents and crawlers (HTTPS, an `llms.txt`, an XML sitemap, robots that
 * allow agents, exposed MCP abilities); they are weighted equally into a 0–100 score, with a
 * pass/fail metric and a concrete recommendation for each one that fails. A missing signal is
 * treated as failing (defensive). This is the readiness card's base, usable with no Cloudflare.
 */
final class ReadinessScorer
{
    private const SIGNALS = ['https', 'llms_txt', 'sitemap', 'robots_agents', 'mcp_abilities'];

    /**
     * @param array<string,bool> $signals
     *
     * @return array{score:int,metrics:list<array{label:string,value:string}>,recommendations:list<string>}
     */
    public function score(array $signals): array
    {
        $metrics         = [];
        $recommendations = [];
        $passed          = 0;

        foreach (self::SIGNALS as $key) {
            $ok = ! empty($signals[$key]);

            if ($ok) {
                $passed++;
            } else {
                $recommendations[] = $this->fix($key);
            }

            $metrics[] = [
                'label' => $this->label($key),
                'value' => $ok ? __('Yes', 'corex') : __('No', 'corex'),
            ];
        }

        $score = (int) round($passed / count(self::SIGNALS) * 100);

        return ['score' => $score, 'metrics' => $metrics, 'recommendations' => $recommendations];
    }

    private function label(string $key): string
    {
        return match ($key) {
            'https'         => __('HTTPS enabled', 'corex'),
            'llms_txt'      => __('llms.txt present', 'corex'),
            'sitemap'       => __('XML sitemap', 'corex'),
            'robots_agents' => __('Robots allow agents', 'corex'),
            'mcp_abilities' => __('MCP abilities exposed', 'corex'),
            default         => $key,
        };
    }

    private function fix(string $key): string
    {
        return match ($key) {
            'https'         => __('Serve the site over HTTPS (install an SSL certificate).', 'corex'),
            'llms_txt'      => __('Add an llms.txt so AI agents can discover and use your content.', 'corex'),
            'sitemap'       => __('Enable an XML sitemap so crawlers can find your pages.', 'corex'),
            'robots_agents' => __('Allow reputable agents and crawlers in robots.txt.', 'corex'),
            'mcp_abilities' => __('Expose Corex MCP abilities so agents can act on your site.', 'corex'),
            default         => $key,
        };
    }
}
