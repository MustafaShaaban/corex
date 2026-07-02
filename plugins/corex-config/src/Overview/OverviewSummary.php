<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Overview operational summary (spec 063, Phase 1). Given already-gathered,
 * real facts (the corex-config boundary reads them from live WordPress), it decides the truthful
 * summary rows: environment/mode, add-on health, form submissions, media delivery, readiness, and a
 * documentation pointer. It NEVER fabricates a value — where a fact is absent (e.g. the optimization
 * add-on is inactive, or submissions cannot be read), the row carries an honest empty/not-available
 * state instead of an invented number. WordPress-free, so it is unit-testable.
 */
final class OverviewSummary
{
    public const TONE_SUCCESS = 'success';
    public const TONE_WARNING = 'warning';
    public const TONE_INFO    = 'info';
    public const TONE_NEUTRAL = 'neutral';

    /**
     * @param array{
     *   environment: array{mode:string,label:string,tone:string,detail:string},
     *   addons: array{active:int,total:int},
     *   submissions: int|null,
     *   media: string,
     *   docsUrl: string,
     *   insightsUrl: string
     * } $facts
     *
     * Rows carry `external`: false means `url` is a wp-admin path the boundary resolves through
     * `admin_url()`; true means `url` is already an absolute URL (e.g. external docs) used verbatim.
     *
     * @return list<array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}>
     */
    public function rows(array $facts): array
    {
        return [
            $this->environmentRow($facts['environment']),
            $this->addonsRow($facts['addons']),
            $this->submissionsRow($facts['submissions']),
            $this->mediaRow($facts['media']),
            $this->readinessRow($facts['insightsUrl']),
            $this->docsRow($facts['docsUrl']),
        ];
    }

    /**
     * @param array{mode:string,label:string,tone:string,detail:string} $env
     *
     * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}
     */
    private function environmentRow(array $env): array
    {
        return [
            'key'    => 'environment',
            'label'  => __('Environment', 'corex'),
            'value'  => $env['label'],
            'tone'   => $env['tone'],
            'detail' => $env['detail'],
            'url'    => '',
            'external' => false,
        ];
    }

    /**
     * @param array{active:int,total:int} $addons
     *
     * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}
     */
    private function addonsRow(array $addons): array
    {
        $active = max(0, $addons['active']);
        $total  = max(0, $addons['total']);

        if ($total === 0) {
            return [
                'key'    => 'addons',
                'label'  => __('Add-ons', 'corex'),
                'value'  => __('None registered', 'corex'),
                'tone'   => self::TONE_NEUTRAL,
                'detail' => __('Installed CoreX add-ons will appear here.', 'corex'),
                'url'    => 'admin.php?page=corex-addons',
                'external' => false,
            ];
        }

        return [
            'key'   => 'addons',
            'label' => __('Add-ons', 'corex'),
            /* translators: 1: active add-on count, 2: total installed add-on count */
            'value' => sprintf(__('%1$d active of %2$d', 'corex'), $active, $total),
            'tone'  => self::TONE_INFO,
            'detail' => __('Add-ons self-disable — toggle freely.', 'corex'),
            'url'    => 'admin.php?page=corex-addons',
            'external' => false,
        ];
    }

    /**
     * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}
     */
    private function submissionsRow(?int $submissions): array
    {
        if ($submissions === null) {
            return [
                'key'    => 'submissions',
                'label'  => __('Form submissions', 'corex'),
                'value'  => __('Not available', 'corex'),
                'tone'   => self::TONE_NEUTRAL,
                'detail' => __('The forms add-on is inactive or has no data source yet.', 'corex'),
                'url'    => '',
                'external' => false,
            ];
        }

        return [
            'key'    => 'submissions',
            'label'  => __('Form submissions', 'corex'),
            'value'  => (string) max(0, $submissions),
            'tone'   => $submissions > 0 ? self::TONE_INFO : self::TONE_NEUTRAL,
            'detail' => $submissions > 0 ? __('Open CoreX Data', 'corex') : __('No submissions yet.', 'corex'),
            'url'    => 'admin.php?page=corex-data',
            'external' => false,
        ];
    }

    /**
     * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}
     */
    private function mediaRow(string $mediaSummary): array
    {
        $summary = trim($mediaSummary);

        if ($summary === '') {
            return [
                'key'    => 'media',
                'label'  => __('Media delivery', 'corex'),
                'value'  => __('Optimization inactive', 'corex'),
                'tone'   => self::TONE_NEUTRAL,
                'detail' => __('The CoreX Media add-on is not active.', 'corex'),
                'url'    => '',
                'external' => false,
            ];
        }

        return [
            'key'    => 'media',
            'label'  => __('Media delivery', 'corex'),
            'value'  => $summary,
            'tone'   => self::TONE_INFO,
            'detail' => __('Reported by the CoreX Media add-on.', 'corex'),
            'url'    => 'admin.php?page=corex-settings-config',
            'external' => false,
        ];
    }

    /**
     * Readiness is a truthful pointer, not a fabricated score: no readiness run is implied here, so it
     * links to Insights to run the real checks rather than displaying an invented grade.
     *
     * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}
     */
    private function readinessRow(string $insightsUrl): array
    {
        return [
            'key'    => 'readiness',
            'label'  => __('Readiness', 'corex'),
            'value'  => __('Not checked yet', 'corex'),
            'tone'   => self::TONE_NEUTRAL,
            'detail' => __('Open Insights to run readiness checks.', 'corex'),
            'url'    => $insightsUrl,
            'external' => false,
        ];
    }

    /**
     * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string,external:bool}
     */
    private function docsRow(string $docsUrl): array
    {
        return [
            'key'    => 'docs',
            'label'  => __('Documentation', 'corex'),
            'value'  => __('Guides & help', 'corex'),
            'tone'   => self::TONE_NEUTRAL,
            'detail' => __('Open the CoreX documentation.', 'corex'),
            'url'    => $docsUrl,
            'external' => true,
        ];
    }
}
