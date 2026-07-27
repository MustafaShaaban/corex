<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * What a clear operation is being asked to cover (spec 078, FR-010/011).
 *
 * An allow-list, never a free string: a scope decides what gets removed, so an unrecognised one must
 * be refused rather than interpreted.
 */
enum CacheScope: string
{
    /** The default. CoreX's own safe caches and nothing else. */
    case Corex = 'corex';

    /** Build manifests and asset metadata — the thing people usually mean. */
    case Assets = 'assets';

    /** This request's in-memory object cache. Affects nothing beyond the current process. */
    case Runtime = 'runtime';

    /**
     * `wp_cache_flush()`. Opt-in only, because it empties the object cache for **every** plugin on
     * the site, not just CoreX.
     */
    case ObjectCache = 'object';

    /** Already-expired CoreX transients. Housekeeping; removes nothing that was still live. */
    case ExpiredTransients = 'expired-transients';

    /** Delegated to a detected page-cache provider, when there is one. */
    case Page = 'page';

    /** Delegated to a configured CDN provider, when there is one. */
    case Cdn = 'cdn';

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }

    public static function tryFromName(string $name): ?self
    {
        return self::tryFrom($name);
    }

    /**
     * Whether this scope reaches beyond CoreX and therefore needs the operator to say so explicitly.
     *
     * `object` flushes a cache shared with every other plugin; `page` and `cdn` act on
     * infrastructure serving real visitors. None of them should happen because somebody clicked the
     * nearest button.
     */
    public function requiresExplicitConfirmation(): bool
    {
        return match ($this) {
            self::ObjectCache, self::Page, self::Cdn => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Corex => __('CoreX caches', 'corex'),
            self::Assets => __('Asset build metadata', 'corex'),
            self::Runtime => __('This request’s object cache', 'corex'),
            self::ObjectCache => __('The whole persistent object cache', 'corex'),
            self::ExpiredTransients => __('Expired CoreX transients', 'corex'),
            self::Page => __('Page cache', 'corex'),
            self::Cdn => __('CDN cache', 'corex'),
        };
    }
}
