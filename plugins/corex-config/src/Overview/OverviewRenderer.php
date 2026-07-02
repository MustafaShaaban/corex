<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

use Corex\Config\Addons\AddonRegistry;
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Docs\DocsUrl;

defined('ABSPATH') || exit;

/**
 * Renders the Overview operational summary (spec 063, Phase 1). This is the boundary: it reads live,
 * real WordPress state (environment type, active add-ons, submission count, media support, docs base),
 * hands those facts to the pure {@see OverviewSummary} / {@see EnvironmentMode}, then escapes and lays
 * out the resulting rows. Status is conveyed by text + tone class (never colour alone) for WCAG 2.2 AA.
 * It never fabricates a value — absent facts render as honest empty/not-available rows.
 */
final class OverviewRenderer
{
    public function __construct(
        private readonly OverviewSummary $summary,
        private readonly EnvironmentMode $mode,
        private readonly AddonRegistry $addons,
        private readonly SubmissionsReader $submissions,
        private readonly DocsUrl $docs,
    ) {
    }

    public function render(): string
    {
        $rows = $this->summary->rows($this->facts());

        $badge = $rows[0];

        $out = '<section class="corex-surface corex-overview-summary" aria-labelledby="corex-overview-summary-title">'
            . '<div class="corex-overview-summary__head">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('OPERATIONAL SUMMARY', 'corex') . '</p>'
            . '<h2 id="corex-overview-summary-title">' . esc_html__('At a glance', 'corex') . '</h2>'
            . $this->environmentBadge($badge)
            . '</div><div class="corex-overview-summary__grid">';

        foreach ($rows as $row) {
            $out .= $this->cell($row);
        }

        return $out . '</div></section>';
    }

    /**
     * @param array{value:string,tone:string} $row
     */
    private function environmentBadge(array $row): string
    {
        return sprintf(
            '<span class="corex-badge corex-badge--%1$s corex-overview-summary__env">%2$s</span>',
            esc_attr($row['tone']),
            esc_html($row['value']),
        );
    }

    /**
     * @param array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool} $row
     */
    private function cell(array $row): string
    {
        $href = $row['external'] ? $row['url'] : admin_url($row['url']);
        // External links (docs) open in a new tab with a safe rel; internal admin links stay in place.
        $attrs = $row['external'] ? ' target="_blank" rel="noopener noreferrer"' : '';
        $value = $row['url'] === ''
            ? esc_html($row['value'])
            : '<a href="' . esc_url($href) . '"' . $attrs . '>' . esc_html($row['value']) . '</a>';

        $detail = $row['detail'] === '' ? '' :
            '<p class="corex-overview-summary__detail">' . esc_html($row['detail']) . '</p>';

        return sprintf(
            '<article class="corex-overview-summary__cell is-%1$s">'
            . '<p class="corex-overview-summary__label">%2$s</p>'
            . '<p class="corex-overview-summary__value">%3$s</p>%4$s</article>',
            esc_attr($row['tone']),
            esc_html($row['label']),
            wp_kses_post($value),
            $detail,
        );
    }

    /**
     * Gathers the real facts from live WordPress. External docs links stay absolute (never resolve
     * against the client domain); internal links are admin paths resolved by the renderer.
     *
     * @return array{
     *   environment: array{mode:string,label:string,tone:string,detail:string},
     *   addons: array{active:int,total:int},
     *   submissions: int|null,
     *   media: string,
     *   docsUrl: string,
     *   insightsUrl: string
     * }
     */
    private function facts(): array
    {
        return [
            'environment' => $this->mode->resolve(
                function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production',
            ),
            'addons'      => $this->addonCounts(),
            'submissions' => $this->submissionCount(),
            'media'       => (string) apply_filters('corex_media_support_summary', ''),
            'docsUrl'     => $this->docs->resolve('/'),
            // A wp-admin path (not pre-resolved) so the renderer routes it through admin_url() like
            // the other internal links; the docs URL stays absolute (external).
            'insightsUrl' => 'admin.php?page=corex-insights',
        ];
    }

    /**
     * @return array{active:int,total:int}
     */
    private function addonCounts(): array
    {
        /** @var list<string> $active */
        $active = array_map('strval', (array) get_option('active_plugins', []));

        $addons = $this->addons->all();
        $total  = count($addons);
        $activeCount = 0;

        foreach ($addons as $addon) {
            if (in_array($addon->pluginFile, $active, true)) {
                $activeCount++;
            }
        }

        return ['active' => $activeCount, 'total' => $total];
    }

    private function submissionCount(): ?int
    {
        try {
            return $this->submissions->total();
        } catch (\Throwable) {
            // Forms add-on inactive / source unavailable — an honest "not available" row, never an error.
            return null;
        }
    }
}
