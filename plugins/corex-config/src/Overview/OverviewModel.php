<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

/**
 * Pure view model for the CoreX Overview (spec 064). It composes the approved dense readiness dashboard
 * from ALREADY-GATHERED, real facts (the corex-config boundary reads them from live WordPress): stat
 * tiles, a launch-readiness checklist, an analytics-and-security integrations panel, a data-sources
 * summary, and a forms summary. Every value is real or an honest state — it never fabricates a count,
 * an integration, a readiness score, or an activity feed. WordPress-free, so it is unit-testable.
 */
final class OverviewModel
{
    public const TONE_SUCCESS = 'success';
    public const TONE_WARNING = 'warning';
    public const TONE_INFO    = 'info';
    public const TONE_NEUTRAL = 'neutral';

    /**
     * @param array{
     *   counts: array{posts:int,pages:int,submissions:int|null,addonsActive:int,addonsTotal:int},
     *   domains: array<string,bool>,
     *   frontPageSet: bool,
     *   kitApplied: bool,
     *   hardeningWarnings: int,
     *   dataSources: list<array{label:string,key:string,total:int}>,
     *   formsCount: int,
     *   flowsCount: int
     * } $facts
     *
     * @return array{
     *   tiles: list<array{label:string,value:string,detail:string}>,
     *   readiness: array{rows:list<array{label:string,note:string,done:bool,tone:string}>,done:int,total:int},
     *   integrations: list<array{label:string,note:string,tone:string}>,
     *   dataSources: list<array{label:string,key:string,count:int}>,
     *   forms: array{count:int,flows:int,note:string}
     * }
     */
    public function build(array $facts): array
    {
        $domains = $facts['domains'];

        return [
            'tiles'        => $this->tiles($facts['counts']),
            'readiness'    => $this->readiness($domains, $facts['frontPageSet'], $facts['kitApplied'], $facts['hardeningWarnings']),
            'integrations' => $this->integrations($domains, $facts['hardeningWarnings']),
            'dataSources'  => $this->dataSources($facts['dataSources']),
            'forms'        => [
                'count' => max(0, $facts['formsCount']),
                'flows' => max(0, $facts['flowsCount'] ?? 0),
                'note'  => __('Registered code forms and versioned visitor flows.', 'corex'),
            ],
        ];
    }

    /**
     * @param array{posts:int,pages:int,submissions:int|null,addonsActive:int,addonsTotal:int} $counts
     *
     * @return list<array{label:string,value:string,detail:string}>
     */
    private function tiles(array $counts): array
    {
        $submissions = $counts['submissions'];

        return [
            ['label' => __('Posts', 'corex'), 'value' => (string) max(0, $counts['posts']), 'detail' => __('Published', 'corex')],
            ['label' => __('Pages', 'corex'), 'value' => (string) max(0, $counts['pages']), 'detail' => __('Published', 'corex')],
            [
                'label'  => __('Submissions', 'corex'),
                'value'  => $submissions === null ? __('—', 'corex') : (string) max(0, $submissions),
                'detail' => $submissions === null ? __('Forms add-on inactive', 'corex') : __('Stored', 'corex'),
            ],
            [
                'label'  => __('Add-ons', 'corex'),
                /* translators: 1: active add-on count, 2: total installed */
                'value'  => sprintf(__('%1$d / %2$d', 'corex'), max(0, $counts['addonsActive']), max(0, $counts['addonsTotal'])),
                'detail' => __('Active / installed', 'corex'),
            ],
        ];
    }

    /**
     * A launch-readiness checklist built only from real configuration/provisioning signals.
     *
     * @param array<string,bool> $domains domain key => configured
     *
     * @return array{rows:list<array{label:string,note:string,done:bool,tone:string}>,done:int,total:int}
     */
    private function readiness(array $domains, bool $frontPageSet, bool $kitApplied, int $hardeningWarnings): array
    {
        $rows = [
            $this->readyRow(__('Brand configured', 'corex'), ($domains['brand'] ?? false), __('Brand tokens & logo', 'corex')),
            $this->readyRow(__('Company Site Kit applied', 'corex'), $kitApplied, __('Starter pages provisioned', 'corex')),
            $this->readyRow(__('Front page set', 'corex'), $frontPageSet, __('A static front page is selected', 'corex')),
            $this->readyRow(__('Transactional email configured', 'corex'), ($domains['mail'] ?? false), __('From address set', 'corex')),
            $this->readyRow(__('Spam protection', 'corex'), ($domains['captcha'] ?? false), __('Captcha / honeypot ready', 'corex')),
            $this->readyRow(__('Security hardening', 'corex'), $hardeningWarnings === 0, __('WordPress hardening checks', 'corex')),
        ];

        $done = count(array_filter($rows, static fn (array $r): bool => $r['done']));

        return ['rows' => $rows, 'done' => $done, 'total' => count($rows)];
    }

    /**
     * @return array{label:string,note:string,done:bool,tone:string}
     */
    private function readyRow(string $label, bool $done, string $note): array
    {
        return [
            'label' => $label,
            'note'  => $done ? __('Done', 'corex') : $note,
            'done'  => $done,
            'tone'  => $done ? self::TONE_SUCCESS : self::TONE_WARNING,
        ];
    }

    /**
     * Analytics & security integrations — each an honest connected / not-configured chip, never a score.
     *
     * @param array<string,bool> $domains
     *
     * @return list<array{label:string,note:string,tone:string}>
     */
    private function integrations(array $domains, int $hardeningWarnings): array
    {
        return [
            $this->integrationRow(
                __('Insights / PageSpeed', 'corex'),
                ($domains['insights'] ?? false),
                __('Provider connected', 'corex'),
                __('Not connected', 'corex'),
            ),
            $this->integrationRow(
                __('Captcha', 'corex'),
                ($domains['captcha'] ?? false),
                __('Configured', 'corex'),
                __('Not configured', 'corex'),
            ),
            $this->integrationRow(
                __('Transactional email', 'corex'),
                ($domains['mail'] ?? false),
                __('From address set', 'corex'),
                __('Not configured', 'corex'),
            ),
            [
                'label' => __('Login protection', 'corex'),
                'note'  => $hardeningWarnings === 0
                    ? __('Hardening checks pass', 'corex')
                    : sprintf(
                        /* translators: %d: number of hardening checks needing attention */
                        _n('%d check to review', '%d checks to review', $hardeningWarnings, 'corex'),
                        $hardeningWarnings,
                    ),
                'tone'  => $hardeningWarnings === 0 ? self::TONE_SUCCESS : self::TONE_WARNING,
            ],
        ];
    }

    /**
     * @return array{label:string,note:string,tone:string}
     */
    private function integrationRow(string $label, bool $connected, string $on, string $off): array
    {
        return [
            'label' => $label,
            'note'  => $connected ? $on : $off,
            'tone'  => $connected ? self::TONE_SUCCESS : self::TONE_NEUTRAL,
        ];
    }

    /**
     * @param list<array{label:string,key:string,total:int}> $sources
     *
     * @return list<array{label:string,key:string,count:int}>
     */
    private function dataSources(array $sources): array
    {
        $out = [];
        foreach ($sources as $source) {
            $out[] = ['label' => $source['label'], 'key' => $source['key'], 'count' => max(0, $source['total'])];
        }

        return $out;
    }
}
