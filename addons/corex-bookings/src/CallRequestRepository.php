<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

use Corex\Repositories\TableRepository;

/**
 * Persists call requests in the `corex_call_requests` custom table.
 */
final class CallRequestRepository extends TableRepository
{
    protected function table(): string
    {
        return 'call_requests';
    }

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [];
    }
}
