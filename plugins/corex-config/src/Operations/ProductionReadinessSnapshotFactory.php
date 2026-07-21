<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Config\Security\HardeningChecks;
use Corex\Config\Security\HardeningFacts;
use Corex\Events\EventDispatcher;
use DateTimeImmutable;

/**
 * Builds the shared readiness evidence used before Production launch.
 */
final class ProductionReadinessSnapshotFactory
{
    public function __construct(
        private readonly HardeningChecks $hardening,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    public function fromCurrentSite(DateTimeImmutable $now): ReadinessSnapshot
    {
        $checkedAt = $now->format(DATE_ATOM);

        $snapshot = new ReadinessSnapshot(array_map(
            static fn (array $check): array => [
                'key'            => $check['key'],
                'label'          => $check['label'],
                'state'          => $check['status'] === HardeningChecks::PASS ? 'pass' : 'blocking',
                'summary'        => $check['detail'],
                'resolution_url' => '',
                'checked_at'     => $checkedAt,
                'evidence_hash'  => hash('sha256', json_encode([
                    'key'    => $check['key'],
                    'status' => $check['status'],
                    'detail' => $check['detail'],
                ], JSON_THROW_ON_ERROR)),
            ],
            $this->hardening->checks(HardeningFacts::gather()),
        ));

        // Announce the evaluation so the Notification Center can reconcile readiness conditions. The
        // checks are local, so this is safe on the render path (FR-015).
        $this->events?->dispatch(new ReadinessEvaluatedEvent($snapshot));

        return $snapshot;
    }
}
