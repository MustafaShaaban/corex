<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Subscriber;

defined('ABSPATH') || exit;

/**
 * Persistence for subscribers. The interface keeps the subscription service
 * headless-testable; the WordPress-backed implementation uses the custom-table
 * repository.
 */
interface SubscriberStore
{
    /**
     * @return array{id:int,email:string,status:string,topics:list<string>}|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * @param list<string> $topics
     *
     * @return int the new subscriber id (status: pending)
     */
    public function create(string $email, array $topics): int;

    public function setStatus(int $id, string $status): void;

    /**
     * Confirmed (non-suppressed) subscribers whose topics intersect $topics.
     *
     * @param list<string> $topics
     *
     * @return list<array{email:string,topics:list<string>}>
     */
    public function confirmedForTopics(array $topics): array;
}
