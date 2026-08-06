<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite\Events;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/** Scalars keep deletion listeners independent of WP_Site (spec 100 FR-040). */
final class SiteDeleted implements Event
{
    public function __construct(public readonly int $siteId)
    {
    }
}
