<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

defined('ABSPATH') || exit;

use Corex\Notifications\NotificationRepository;
use DateTimeImmutable;

/**
 * Retention for the Notification Center (spec 072 FR-022): a {@see PrunableStore} the shared
 * {@see RetentionSweep} cleans alongside every other store. Only resolved conditions and expired
 * notifications older than the window are removed — an unresolved condition persists however old it
 * is, because it still needs attention. The pruning itself is the repository's bounded, batched
 * `pruneOlderThan`.
 */
final class NotificationRetention implements PrunableStore
{
    private const RETENTION_DAYS = 90;

    public function __construct(private readonly NotificationRepository $repository)
    {
    }

    public function key(): string
    {
        return 'notifications';
    }

    public function label(): string
    {
        return __('Notifications', 'corex');
    }

    public function retentionDays(): int
    {
        return self::RETENTION_DAYS;
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->repository->pruneOlderThan($cutoff);
    }
}
