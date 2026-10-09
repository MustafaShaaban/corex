<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Table;

/**
 * The call requests table. Its columns were written inside the provider, where only the hook that
 * created the table could read them; the migration runner has to be handed them too.
 */
final class CallRequestTable
{
    public const NAME = 'call_requests';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()->string('leader_id', 60)->string('name')->string('email')->string('phone', 60)
            ->string('preferred_time', 100)->text('message')->string('status', 20)->timestamps();
    }
}
