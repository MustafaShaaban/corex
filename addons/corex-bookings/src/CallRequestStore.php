<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

/**
 * Persistence for call requests. Keeps the service headless-testable; the
 * WordPress-backed implementation uses the custom-table repository.
 */
interface CallRequestStore
{
    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int;
}
