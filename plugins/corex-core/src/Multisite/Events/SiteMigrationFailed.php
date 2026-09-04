<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite\Events;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/** Scalars keep failure diagnostics usable in headless listeners (spec 100 FR-040). */
final class SiteMigrationFailed implements Event
{
    public function __construct(
        public readonly int $siteId,
        public readonly string $error,
        public readonly string $componentId,
        public readonly string $fromVersion,
        public readonly string $toVersion,
    ) {
    }
}
