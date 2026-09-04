<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite\Events;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/** Scalars keep creation listeners independent of WP_Site (spec 100 FR-040). */
final class SiteCreated implements Event
{
    public function __construct(
        public readonly int $siteId,
        public readonly int $networkId,
        public readonly string $url,
        public readonly string $domain,
        public readonly string $path,
        public readonly bool $isMainSite,
    ) {
    }
}
