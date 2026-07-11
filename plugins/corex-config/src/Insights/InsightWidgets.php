<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * Pure model for the designed Insights widget set (spec 067, design: "Corex Insights"):
 * Performance · Cloudflare · Security events · SEO & indexing · AI/Agent readiness ·
 * Operations health · Forms & Flows analytics. Every widget's state is derived from REAL
 * facts passed in by the screen (stored check results, configured secrets, live local
 * signals, recorded security events) — never invented. Widgets whose data comes from a
 * runnable provider carry a `mount` id for the live run-check card; the rest render
 * truthful server-side rows or an honest disconnected/empty state with a real
 * call-to-action key. WordPress-free, so it is unit-testable.
 */
final class InsightWidgets
{
    public const STATE_LIVE         = 'live';
    public const STATE_CONNECTED    = 'connected';
    public const STATE_DISCONNECTED = 'disconnected';
    public const STATE_EMPTY        = 'empty';

    /**
     * @param array{
     *   psiKeyConfigured:bool,
     *   cfConfigured:bool,
     *   performanceLatest:?array<string,mixed>,
     *   readinessLatest:?array<string,mixed>,
     *   searchVisible:bool,
     *   prettyPermalinks:bool,
     *   securityEvents:list<array{text:string,meta:string,tone:string}>,
     *   cronDisabledByConstant:bool,
     *   cronOverdue:int,
     *   phpVersion:string,
     *   wpVersion:string,
     *   environment:string,
     *   operationsMode:string,
     *   modeDeclared:bool,
     *   formsSubmissions:int,
     *   formsPublishedFlows:int,
     *   formsTotalFlows:int
     * } $facts
     *
     * @return list<array<string,mixed>>
     */
    public function widgets(array $facts): array
    {
        return [
            $this->performance($facts),
            $this->cloudflare($facts),
            $this->security($facts),
            $this->seo($facts),
            $this->agentReadiness($facts),
            $this->operations($facts),
            $this->forms($facts),
        ];
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function performance(array $facts): array
    {
        $latest = $facts['performanceLatest'];

        $widget = $this->base(
            'performance',
            __('Performance', 'corex'),
            __('PageSpeed · Core Web Vitals', 'corex'),
            $this->providerState($latest),
        );
        $widget['mount'] = 'performance';

        if (! $facts['psiKeyConfigured']) {
            $widget['note'] = __('No PageSpeed API key is configured — checks run keyless (low volume). Add a key in Settings → Insights for reliable runs.', 'corex');
        }

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function cloudflare(array $facts): array
    {
        if (! $facts['cfConfigured']) {
            $widget = $this->base(
                'cloudflare',
                __('Cloudflare', 'corex'),
                __('URL scan · security signal', 'corex'),
                self::STATE_DISCONNECTED,
            );
            $widget['alt'] = [
                'title'    => __('Not connected', 'corex'),
                'message'  => __('Add a Cloudflare API token and account ID to include the URL-scan security signal in AI/Agent readiness runs.', 'corex'),
                'ctaLabel' => __('Open Insights settings', 'corex'),
                'ctaHref'  => 'settings',
            ];

            return $widget;
        }

        $widget = $this->base(
            'cloudflare',
            __('Cloudflare', 'corex'),
            __('URL scan · security signal', 'corex'),
            self::STATE_CONNECTED,
        );
        $widget['rows'] = [
            ['label' => __('API token', 'corex'), 'value' => __('Configured', 'corex'), 'tone' => 'success'],
            ['label' => __('Account ID', 'corex'), 'value' => __('Configured', 'corex'), 'tone' => 'success'],
            ['label' => __('URL-scan signal', 'corex'), 'value' => __('Included in AI/Agent readiness runs', 'corex'), 'tone' => 'info'],
            ['label' => __('Zone analytics (cache · WAF)', 'corex'), 'value' => __('Not tracked', 'corex'), 'tone' => 'subtle'],
        ];

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function security(array $facts): array
    {
        $events = array_slice($facts['securityEvents'], 0, 5);

        if ($events === []) {
            $widget = $this->base(
                'security',
                __('Security events', 'corex'),
                __('Mode changes · denied access', 'corex'),
                self::STATE_EMPTY,
            );
            $widget['alt'] = [
                'title'    => __('No security events recorded', 'corex'),
                'message'  => __('CoreX records operations-mode changes and denied CoreX admin access attempts here. Login-protection events arrive with the Security Center.', 'corex'),
                'ctaLabel' => __('Open Operations & Security', 'corex'),
                'ctaHref'  => 'operations',
            ];

            return $widget;
        }

        $widget = $this->base(
            'security',
            __('Security events', 'corex'),
            __('Mode changes · denied access', 'corex'),
            self::STATE_LIVE,
        );
        $widget['events'] = $events;

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function seo(array $facts): array
    {
        $widget = $this->base(
            'seo',
            __('SEO & indexing readiness', 'corex'),
            __('Visibility · permalinks · coverage', 'corex'),
            self::STATE_LIVE,
            $facts['searchVisible'] ? null : 'warning',
        );
        $widget['rows'] = [
            [
                'label' => __('Search engine visibility', 'corex'),
                'value' => $facts['searchVisible']
                    ? __('Visible to search engines', 'corex')
                    : __('Discouraged (Settings → Reading)', 'corex'),
                'tone'  => $facts['searchVisible'] ? 'success' : 'warning',
            ],
            [
                'label' => __('Pretty permalinks', 'corex'),
                'value' => $facts['prettyPermalinks'] ? __('Enabled', 'corex') : __('Plain (not SEO-friendly)', 'corex'),
                'tone'  => $facts['prettyPermalinks'] ? 'success' : 'warning',
            ],
            [
                'label' => __('Sitemap · robots · llms.txt', 'corex'),
                'value' => $facts['readinessLatest'] === null
                    ? __('Not probed yet — run AI/Agent readiness', 'corex')
                    : __('Probed by the AI/Agent readiness check', 'corex'),
                'tone'  => $facts['readinessLatest'] === null ? 'subtle' : 'info',
            ],
        ];

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function agentReadiness(array $facts): array
    {
        $widget = $this->base(
            'ai',
            __('AI / Agent readiness', 'corex'),
            __('llms.txt · sitemap · robots · MCP', 'corex'),
            $this->providerState($facts['readinessLatest']),
        );
        $widget['mount'] = 'readiness';

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function operations(array $facts): array
    {
        $cron = $this->cronRow($facts);

        $widget = $this->base(
            'ops',
            __('Operations health', 'corex'),
            __('Mode · cron · runtime versions', 'corex'),
            self::STATE_LIVE,
            $cron['tone'] === 'warning' ? 'warning' : null,
        );
        $widget['rows'] = [
            [
                'label' => __('Operations mode', 'corex'),
                'value' => $facts['operationsMode']
                    . ($facts['modeDeclared'] ? '' : ' ' . __('(inherited from environment)', 'corex')),
                'tone'  => 'info',
            ],
            ['label' => __('Environment type', 'corex'), 'value' => $facts['environment'], 'tone' => 'info'],
            $cron,
            ['label' => __('PHP version', 'corex'), 'value' => $facts['phpVersion'], 'tone' => 'info'],
            ['label' => __('WordPress version', 'corex'), 'value' => $facts['wpVersion'], 'tone' => 'info'],
            ['label' => __('Uptime monitor', 'corex'), 'value' => __('Not tracked', 'corex'), 'tone' => 'subtle'],
        ];

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array<string,mixed>
     */
    private function forms(array $facts): array
    {
        $published = (int) $facts['formsPublishedFlows'];

        $widget = $this->base(
            'forms',
            __('Forms & Flows analytics', 'corex'),
            __('Submissions · flows · routing', 'corex'),
            self::STATE_LIVE,
        );
        $widget['rows'] = [
            ['label' => __('Stored submissions', 'corex'), 'value' => (string) max(0, (int) $facts['formsSubmissions']), 'tone' => 'info'],
            ['label' => __('Published flows', 'corex'), 'value' => (string) max(0, $published), 'tone' => $published > 0 ? 'success' : 'subtle'],
            ['label' => __('Total flows', 'corex'), 'value' => (string) max(0, (int) $facts['formsTotalFlows']), 'tone' => 'info'],
        ];

        return $widget;
    }

    /**
     * @param array<string,mixed> $facts
     *
     * @return array{label:string,value:string,tone:string}
     */
    private function cronRow(array $facts): array
    {
        if ($facts['cronDisabledByConstant']) {
            return [
                'label' => __('WP-Cron', 'corex'),
                'value' => __('Disabled by constant (needs a server cron)', 'corex'),
                'tone'  => 'warning',
            ];
        }

        if ($facts['cronOverdue'] > 0) {
            return [
                'label' => __('WP-Cron', 'corex'),
                'value' => sprintf(
                    /* translators: %d: number of overdue scheduled events. */
                    _n('%d overdue event', '%d overdue events', $facts['cronOverdue'], 'corex'),
                    $facts['cronOverdue'],
                ),
                'tone'  => 'warning',
            ];
        }

        return ['label' => __('WP-Cron', 'corex'), 'value' => __('On schedule', 'corex'), 'tone' => 'success'];
    }

    /**
     * The chip state for a runnable provider from its REAL stored latest result: no run yet is an
     * honest empty; a run maps the shared good/recommended/critical health status.
     *
     * @param array<string,mixed>|null $latest
     */
    private function providerState(?array $latest): string
    {
        if ($latest === null) {
            return self::STATE_EMPTY;
        }

        return self::STATE_CONNECTED;
    }

    /**
     * @return array<string,mixed>
     */
    private function base(string $key, string $title, string $sub, string $state, ?string $attention = null): array
    {
        return [
            'key'       => $key,
            'title'     => $title,
            'sub'       => $sub,
            'state'     => $state,
            'chip'      => $this->chipLabel($state),
            'chipTone'  => $attention ?? $this->chipTone($state),
            'mount'     => null,
            'note'      => '',
            'rows'      => [],
            'events'    => [],
            'alt'       => null,
        ];
    }

    private function chipLabel(string $state): string
    {
        return match ($state) {
            self::STATE_LIVE         => __('Live', 'corex'),
            self::STATE_CONNECTED    => __('Connected', 'corex'),
            self::STATE_DISCONNECTED => __('Not connected', 'corex'),
            self::STATE_EMPTY        => __('No data yet', 'corex'),
            default                  => $state,
        };
    }

    private function chipTone(string $state): string
    {
        return match ($state) {
            self::STATE_LIVE, self::STATE_CONNECTED => 'success',
            default                                 => 'subtle',
        };
    }
}
