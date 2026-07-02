<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

use Corex\Config\Addons\AddonRegistry;
use Corex\Config\ControlPanel\ControlPanelStatus;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Security\HardeningChecks;
use Corex\Config\Security\HardeningFacts;
use Corex\Container\ContainerInterface;
use Corex\Forms\FormRegistry;
use Corex\Provisioning\KitProvisioner;

defined('ABSPATH') || exit;

/**
 * Renders the CoreX Overview (spec 064) as the approved dense two-column readiness dashboard. This is
 * the boundary: it reads live, REAL WordPress state (environment, post/page/submission/add-on counts,
 * control-panel domain configuration, hardening, data sources, applied kits, front page, registered
 * forms), hands the facts to the pure {@see OverviewModel}, then escapes and lays out the grid — stat
 * tiles, launch readiness, analytics & security, data sources, forms summary, and an honest
 * recent-activity empty state. It never fabricates a value; absent facts render as honest states.
 * Status is conveyed by text + tone (never colour alone). Optional deps (forms/provisioning) resolve
 * lazily so corex-config never hard-depends on them (Principle IX).
 */
final class OverviewRenderer
{
    public function __construct(
        private readonly OverviewModel $model,
        private readonly EnvironmentMode $mode,
        private readonly ControlPanelStatus $control,
        private readonly HardeningChecks $hardening,
        private readonly SubmissionsReader $submissions,
        private readonly DataRegistry $data,
        private readonly AddonRegistry $addons,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string,mixed> $settingValues settings values keyed by Config dot-key
     */
    public function render(array $settingValues): string
    {
        $env  = $this->mode->resolve(
            function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production',
        );
        $data = $this->model->build($this->facts($settingValues));

        return '<div class="corex-overview">'
            . $this->envBar($env)
            . $this->tiles($data['tiles'])
            . '<div class="corex-overview__grid corex-overview__grid--primary">'
            . $this->readinessCard($data['readiness'])
            . '<div class="corex-overview__col">'
            . $this->formsCard($data['forms'])
            . $this->dataSourcesCard($data['dataSources'])
            . '</div></div>'
            . '<div class="corex-overview__grid corex-overview__grid--secondary">'
            . $this->integrationsCard($data['integrations'])
            . $this->activityCard()
            . '</div></div>';
    }

    /**
     * @param array{mode:string,label:string,tone:string,detail:string} $env
     */
    private function envBar(array $env): string
    {
        return '<section class="corex-overview__env corex-surface is-' . esc_attr($env['tone']) . '">'
            . '<span class="corex-overview__env-badge">' . esc_html($env['label']) . '</span>'
            . '<span class="corex-overview__env-detail">' . esc_html($env['detail']) . '</span></section>';
    }

    /**
     * @param list<array{label:string,value:string,detail:string}> $tiles
     */
    private function tiles(array $tiles): string
    {
        $cells = '';
        foreach ($tiles as $tile) {
            $cells .= '<article class="corex-overview__tile"><p class="corex-overview__tile-label">'
                . esc_html($tile['label']) . '</p><p class="corex-overview__tile-value">'
                . esc_html($tile['value']) . '</p><p class="corex-overview__tile-detail">'
                . esc_html($tile['detail']) . '</p></article>';
        }

        return '<div class="corex-overview__tiles">' . $cells . '</div>';
    }

    /**
     * @param array{rows:list<array{label:string,note:string,done:bool,tone:string}>,done:int,total:int} $readiness
     */
    private function readinessCard(array $readiness): string
    {
        $rows = '';
        foreach ($readiness['rows'] as $row) {
            $mark = $row['done'] ? '&#10003;' : '&#8226;';
            $rows .= '<li class="corex-overview__check is-' . esc_attr($row['tone']) . '">'
                . '<span class="corex-overview__check-mark" aria-hidden="true">' . $mark . '</span>'
                . '<span class="corex-overview__check-label">' . esc_html($row['label']) . '</span>'
                . '<span class="corex-overview__check-note">' . esc_html($row['note']) . '</span></li>';
        }

        $badge = sprintf(
            /* translators: 1: completed readiness items, 2: total readiness items */
            esc_html__('%1$d of %2$d', 'corex'),
            (int) $readiness['done'],
            (int) $readiness['total'],
        );

        return '<section class="corex-surface corex-overview__card">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html__('Launch readiness', 'corex') . '</h2>'
            . '<span class="corex-overview__count">' . $badge . '</span></header>'
            . '<ul class="corex-overview__checks">' . $rows . '</ul></section>';
    }

    /**
     * @param array{count:int,note:string} $forms
     */
    private function formsCard(array $forms): string
    {
        return '<section class="corex-surface corex-overview__card corex-overview__card--compact">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html__('Forms & Flows', 'corex') . '</h2>'
            . '<span class="corex-overview__pill">' . esc_html__('Read-only', 'corex') . '</span></header>'
            . '<p class="corex-overview__big">' . esc_html((string) $forms['count']) . '</p>'
            . '<p class="corex-overview__muted">' . esc_html($forms['note']) . '</p>'
            . '<p><a href="' . esc_url(admin_url('admin.php?page=corex-forms')) . '">'
            . esc_html__('Open Forms & Flows', 'corex') . '</a></p></section>';
    }

    /**
     * @param list<array{label:string,key:string,count:int}> $sources
     */
    private function dataSourcesCard(array $sources): string
    {
        if ($sources === []) {
            return '<section class="corex-surface corex-overview__card corex-overview__card--compact">'
                . '<header class="corex-overview__card-head"><h2>' . esc_html__('Data sources', 'corex') . '</h2></header>'
                . '<p class="corex-overview__muted">' . esc_html__('No data sources registered yet.', 'corex')
                . '</p></section>';
        }

        $rows = '';
        foreach ($sources as $source) {
            $rows .= '<li><span class="corex-overview__src-name">' . esc_html($source['label']) . '</span>'
                . '<span class="corex-overview__src-count">' . esc_html((string) $source['count']) . '</span></li>';
        }

        return '<section class="corex-surface corex-overview__card corex-overview__card--compact">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html__('Data sources', 'corex') . '</h2>'
            . '<a class="corex-overview__link" href="' . esc_url(admin_url('admin.php?page=corex-data')) . '">'
            . esc_html__('Explorer', 'corex') . ' &rarr;</a></header>'
            . '<ul class="corex-overview__sources">' . $rows . '</ul></section>';
    }

    /**
     * @param list<array{label:string,note:string,tone:string}> $integrations
     */
    private function integrationsCard(array $integrations): string
    {
        $rows = '';
        foreach ($integrations as $row) {
            $rows .= '<li class="corex-overview__integration is-' . esc_attr($row['tone']) . '">'
                . '<span class="corex-overview__integration-label">' . esc_html($row['label']) . '</span>'
                . '<span class="corex-overview__integration-note">' . esc_html($row['note']) . '</span></li>';
        }

        return '<section class="corex-surface corex-overview__card">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html__('Analytics & security', 'corex') . '</h2>'
            . '<a class="corex-overview__link" href="' . esc_url(admin_url('admin.php?page=corex-operations-security')) . '">'
            . esc_html__('Details', 'corex') . ' &rarr;</a></header>'
            . '<ul class="corex-overview__integrations">' . $rows . '</ul></section>';
    }

    private function activityCard(): string
    {
        return '<section class="corex-surface corex-overview__card corex-overview__activity">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html__('Recent activity', 'corex') . '</h2>'
            . '<span class="corex-overview__pill">' . esc_html__('Empty', 'corex') . '</span></header>'
            . '<div class="corex-overview__empty"><p>'
            . esc_html__('No recent framework events yet.', 'corex') . '</p><p class="corex-overview__muted">'
            . esc_html__('Activity appears here once event logging is available.', 'corex') . '</p></div></section>';
    }

    /**
     * @param array<string,mixed> $settingValues
     *
     * @return array{
     *   counts: array{posts:int,pages:int,submissions:int|null,addonsActive:int,addonsTotal:int},
     *   domains: array<string,bool>,
     *   frontPageSet: bool,
     *   kitApplied: bool,
     *   hardeningWarnings: int,
     *   dataSources: list<array{label:string,key:string,total:int}>,
     *   formsCount: int
     * }
     */
    private function facts(array $settingValues): array
    {
        $domains = [];
        foreach ($this->control->domains($settingValues) as $domain) {
            $domains[$domain->domain] = $domain->isConfigured();
        }

        [$active, $total] = $this->addonCounts();

        return [
            'counts' => [
                'posts'        => $this->publishedCount('post'),
                'pages'        => $this->publishedCount('page'),
                'submissions'  => $this->submissionCount(),
                'addonsActive' => $active,
                'addonsTotal'  => $total,
            ],
            'domains'           => $domains,
            'frontPageSet'      => get_option('show_on_front') === 'page',
            'kitApplied'        => $this->kitApplied(),
            'hardeningWarnings' => $this->hardening->warnings($this->hardening->checks(HardeningFacts::gather())),
            'dataSources'       => $this->dataSources(),
            'formsCount'        => $this->formsCount(),
        ];
    }

    private function publishedCount(string $type): int
    {
        $counts = wp_count_posts($type);

        return (int) ($counts->publish ?? 0);
    }

    private function submissionCount(): ?int
    {
        try {
            return $this->submissions->total();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0:int,1:int} [active, total]
     */
    private function addonCounts(): array
    {
        $active  = array_map('strval', (array) get_option('active_plugins', []));
        $addons  = $this->addons->all();
        $running = 0;
        foreach ($addons as $addon) {
            if (in_array($addon->pluginFile, $active, true)) {
                $running++;
            }
        }

        return [$running, count($addons)];
    }

    /**
     * @return list<array{label:string,key:string,total:int}>
     */
    private function dataSources(): array
    {
        $sources = [];
        foreach ($this->data->all() as $source) {
            try {
                $sources[] = ['label' => $source->label(), 'key' => $source->key(), 'total' => $source->total()];
            } catch (\Throwable) {
                continue;
            }
        }

        return $sources;
    }

    private function kitApplied(): bool
    {
        try {
            /** @var KitProvisioner $provisioner */
            $provisioner = $this->container->make(KitProvisioner::class);
            foreach ($provisioner->applicableKits() as $kit) {
                if ($kit->applied) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function formsCount(): int
    {
        try {
            /** @var FormRegistry $registry */
            $registry = $this->container->make(FormRegistry::class);

            return count($registry->all());
        } catch (\Throwable) {
            return 0;
        }
    }
}
