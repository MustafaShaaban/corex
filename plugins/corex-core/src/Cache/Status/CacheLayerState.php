<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache\Status;

defined('ABSPATH') || exit;

/**
 * What a caching layer is doing, as distinct from what it could do (spec 078, FR-017).
 *
 * Seven states rather than a boolean, because the interesting cases are all in the middle. "A Redis
 * container is running" and "WordPress is using Redis" are different facts, and reporting the first
 * as the second is how an operator comes to believe their site is faster than it is.
 */
enum CacheLayerState: string
{
    /** In use right now, doing work. */
    case Active = 'active';

    /** Installed or reachable, but nothing is using it. Presence is not use. */
    case Available = 'available';

    /** Looked for, not found. A normal, healthy answer on most sites. */
    case NotDetected = 'not_detected';

    /** Cannot exist here — the host or the platform does not offer it. */
    case Unsupported = 'unsupported';

    /** Present and working badly: connected but erroring, or partially configured. */
    case Degraded = 'degraded';

    /** Present and failing. */
    case Error = 'error';

    /**
     * Could not be determined.
     *
     * Its own state on purpose. Many hosts disable `opcache_get_status()`, and answering "off"
     * because we were not allowed to look is a confident wrong answer — the kind this project keeps
     * finding and removing.
     */
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active', 'corex'),
            self::Available => __('Available, not in use', 'corex'),
            self::NotDetected => __('Not detected', 'corex'),
            self::Unsupported => __('Not available here', 'corex'),
            self::Degraded => __('Degraded', 'corex'),
            self::Error => __('Error', 'corex'),
            self::Unknown => __('Unknown', 'corex'),
        };
    }

    /** Whether this state is a problem, as opposed to merely not being "active". */
    public function isConcerning(): bool
    {
        return match ($this) {
            self::Degraded, self::Error => true,
            default => false,
        };
    }
}
