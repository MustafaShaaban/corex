<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Subscriber;

defined('ABSPATH') || exit;

use Corex\Repositories\TableRepository;

/**
 * Persists subscribers in the `corex_subscribers` custom table (topics as JSON,
 * consent as bool). The only layer that runs the queries.
 */
final class SubscriberRepository extends TableRepository
{
    protected function table(): string
    {
        return 'subscribers';
    }

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return ['topics' => 'json', 'consent' => 'bool'];
    }
}
