<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Container\ContainerInterface;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowRepository;
use Corex\Support\Config\ConfigInterface;

defined('ABSPATH') || exit;

/**
 * Assembles the REAL facts the {@see InsightWidgets} model needs, from live WordPress and the
 * injected CoreX services — provider key configuration, the latest stored run results, recent
 * security/operations/access activity, cron and runtime signals, the declared operations mode, and
 * real Forms & Flows counts. It never fabricates a value; absent optional sources resolve to honest
 * defaults (empty events, zero counts). The Forms repository resolves lazily so corex-config never
 * hard-depends on it (Principle IX).
 */
final class InsightWidgetFacts
{
    private const SECURITY_AREAS = [
        ActivityEvent::AREA_SECURITY,
        ActivityEvent::AREA_ACCESS,
        ActivityEvent::AREA_OPERATIONS,
    ];

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly InsightStore $store,
        private readonly ActivityService $activity,
        private readonly OperationsModeStore $modeStore,
        private readonly SubmissionsReader $submissions,
        private readonly ContainerInterface $container,
        private readonly string $option = 'corex_insights',
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function gather(): array
    {
        $state = (array) get_option($this->option, []);

        return [
            'psiKeyConfigured' => (string) $this->config->get('insights.psi.key', '') !== '',
            'cfConfigured' => (string) $this->config->get('insights.cloudflare.token', '') !== ''
                && (string) $this->config->get('insights.cloudflare.account_id', '') !== '',
            'performanceLatest'      => $this->store->latest($state, 'performance'),
            'readinessLatest'        => $this->store->latest($state, 'readiness'),
            'searchVisible'          => (string) get_option('blog_public', '1') !== '0',
            'prettyPermalinks'       => (string) get_option('permalink_structure', '') !== '',
            'securityEvents'         => $this->securityEvents(),
            'cronDisabledByConstant' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true,
            'cronOverdue'            => $this->cronOverdue(),
            'phpVersion'             => PHP_VERSION,
            'wpVersion'              => (string) get_bloginfo('version'),
            'environment'            => (string) wp_get_environment_type(),
            'operationsMode'         => $this->modeStore->current(),
            'modeDeclared'           => $this->modeStore->isDeclared(),
            'formsSubmissions'       => $this->submissionsTotal(),
            'formsPublishedFlows'    => $this->flowCount(Flow::STATE_PUBLISHED),
            'formsTotalFlows'        => $this->flowCount(null),
        ];
    }

    /**
     * The five most recent real security-relevant events (mode changes, denied CoreX admin access),
     * shaped for the widget. Honest empty list when none are recorded.
     *
     * @return list<array{text:string,meta:string,tone:string}>
     */
    private function securityEvents(): array
    {
        $events = [];

        foreach ($this->activity->query([], 1, 20) as $event) {
            if (! in_array($event->area, self::SECURITY_AREAS, true)) {
                continue;
            }

            $events[] = [
                /* translators: 1: actor, 2: activity kind, 3: target */
                'text' => trim(sprintf('%1$s · %2$s %3$s', $event->actorLabel, $event->kind, $event->targetLabel)),
                'meta' => $event->occurredAt->format('Y-m-d H:i'),
                'tone' => in_array($event->outcome, [ActivityEvent::OUTCOME_FAILURE, ActivityEvent::OUTCOME_DENIED], true)
                    ? 'warning'
                    : 'info',
            ];

            if (count($events) >= 5) {
                break;
            }
        }

        return $events;
    }

    private function cronOverdue(): int
    {
        if (! function_exists('_get_cron_array')) {
            return 0;
        }

        $cron = _get_cron_array();

        if (! is_array($cron)) {
            return 0;
        }

        $now     = time();
        $overdue = 0;

        foreach (array_keys($cron) as $timestamp) {
            if ((int) $timestamp < $now) {
                $overdue++;
            }
        }

        return $overdue;
    }

    private function submissionsTotal(): int
    {
        try {
            return max(0, $this->submissions->total());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function flowCount(?string $state): int
    {
        try {
            /** @var FlowRepository $flows */
            $flows = $this->container->make(FlowRepository::class);
            $all   = $flows->all();

            if ($state === null) {
                return count($all);
            }

            return count(array_filter($all, static fn (Flow $flow): bool => $flow->state === $state));
        } catch (\Throwable) {
            return 0;
        }
    }
}
