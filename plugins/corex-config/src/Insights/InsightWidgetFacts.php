<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Activity\ActivitySentences;
use Corex\Config\Activity\SecurityEventsSince;
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Container\ContainerInterface;
use Corex\Environment\SiteEnvironment;
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
        private readonly OperationsMode $modes,
        private readonly SecurityEventsSince $since,
        private readonly ActivitySentences $sentences,
        private readonly string $option = 'corex_insights',
        private readonly ?SiteEnvironment $environment = null,
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
            'securityEventsSince'    => $this->securityEventsSince(),
            'cronDisabledByConstant' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true,
            'cronOverdue'            => $this->cronOverdue(),
            'phpVersion'             => PHP_VERSION,
            'wpVersion'              => (string) get_bloginfo('version'),
            'environment'            => $this->environmentFact(),
            'operationsMode'         => $this->modeStore->current(),
            'modeDeclared'           => $this->modeStore->isDeclared(),
            'formsSubmissions'       => $this->submissionsTotal(),
            'formsPublishedFlows'    => $this->flowCount(Flow::STATE_PUBLISHED),
            'formsTotalFlows'        => $this->flowCount(null),
        ];
    }

    /** The site's environment as mail and every screen read it, and where it is set (spec 113). */
    private function environmentFact(): string
    {
        return $this->environment?->current()->summary() ?? (string) wp_get_environment_type();
    }

    /**
     * The five newest events of the security, access and operations areas, asked of those areas
     * only (spec 114, US-SAFE-13). It read the twenty newest events of every area and kept the
     * security ones among them, so twenty trashed submissions hid every security event.
     *
     * @return list<array{text:string,meta:string,tone:string}>
     */
    private function securityEvents(): array
    {
        $events = [];

        foreach ($this->activity->query(['area' => self::SECURITY_AREAS], 1, 5) as $event) {
            $events[] = [
                'text' => $this->sentences->of($event)['text'],
                'meta' => $event->occurredAt->format('Y-m-d H:i'),
                'tone' => in_array($event->outcome, [ActivityEvent::OUTCOME_FAILURE, ActivityEvent::OUTCOME_DENIED], true)
                    ? 'warning'
                    : 'info',
            ];
        }

        return $events;
    }

    /** From when the activity log holds security events, as a date in the site's format. */
    private function securityEventsSince(): string
    {
        // Read first means records start now: the card never says "since" a time before it.
        $this->since->mark(time());

        return (string) wp_date((string) get_option('date_format', 'Y-m-d'), $this->since->since());
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

    /** The stored submissions, or null when they could not be counted: never a 0 for an error. */
    private function submissionsTotal(): ?int
    {
        try {
            return max(0, $this->submissions->total());
        } catch (\Throwable) {
            return null;
        }
    }

    /** The flows, 0 with the forms add-on off, or null when they could not be read. */
    private function flowCount(?string $state): ?int
    {
        if (! class_exists(FlowRepository::class)) {
            return 0;
        }

        try {
            /** @var FlowRepository $flows */
            $flows = $this->container->make(FlowRepository::class);
            $all   = $flows->all();

            if ($state === null) {
                return count($all);
            }

            return count(array_filter($all, static fn (Flow $flow): bool => $flow->state === $state));
        } catch (\Throwable) {
            return null;
        }
    }
}
