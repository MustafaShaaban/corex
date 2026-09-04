<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite\Events;

defined('ABSPATH') || exit;

use Corex\Database\Schema\SiteMigrationResult;
use Corex\Events\Event;

/** Carries the immutable result, never the mutable WP_Site object (spec 100 FR-040). */
final class SiteMigrated implements Event
{
    public function __construct(
        public readonly int $siteId,
        public readonly SiteMigrationResult $result,
    ) {
    }
}
