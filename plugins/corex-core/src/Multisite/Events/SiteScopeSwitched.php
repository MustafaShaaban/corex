<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite\Events;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Scalars keep listeners independent of WP_Site and therefore headless-testable (spec FR-040).
 */
final class SiteScopeSwitched implements Event
{
    public function __construct(
        public readonly int $toSiteId,
        public readonly int $fromSiteId,
    ) {
    }
}
