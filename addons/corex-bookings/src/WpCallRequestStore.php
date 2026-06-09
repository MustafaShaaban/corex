<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

/**
 * Bridges the call-request store contract to the custom-table repository.
 */
final class WpCallRequestStore implements CallRequestStore
{
    public function __construct(private readonly CallRequestRepository $repository)
    {
    }

    public function create(array $data): int
    {
        return $this->repository->insert($data + ['created_at' => current_time('mysql')]);
    }
}
