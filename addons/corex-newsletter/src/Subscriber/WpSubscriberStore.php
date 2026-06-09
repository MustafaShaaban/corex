<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Subscriber;

defined('ABSPATH') || exit;

use Corex\Newsletter\Subscription\SubscriptionService;

/**
 * Bridges the subscription service's store contract to the custom-table repository.
 * Topic intersection for the publish trigger is filtered in a bounded pass over the
 * confirmed subscribers.
 */
final class WpSubscriberStore implements SubscriberStore
{
    public function __construct(private readonly SubscriberRepository $repository)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $rows = $this->repository->where('email', $email);

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];

        return [
            'id'     => (int) $row['id'],
            'email'  => (string) $row['email'],
            'status' => (string) $row['status'],
            'topics' => array_values((array) $row['topics']),
        ];
    }

    public function create(string $email, array $topics): int
    {
        return $this->repository->insert([
            'email'      => $email,
            'status'     => SubscriptionService::PENDING,
            'topics'     => array_values($topics),
            'consent'    => true,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->repository->update($id, ['status' => $status, 'updated_at' => current_time('mysql')]);
    }

    public function confirmedForTopics(array $topics): array
    {
        $recipients = [];

        foreach ($this->repository->where('status', SubscriptionService::CONFIRMED) as $row) {
            $subscriberTopics = array_values((array) $row['topics']);

            if (array_intersect($topics, $subscriberTopics) !== []) {
                $recipients[] = ['email' => (string) $row['email'], 'topics' => $subscriberTopics];
            }
        }

        return $recipients;
    }
}
