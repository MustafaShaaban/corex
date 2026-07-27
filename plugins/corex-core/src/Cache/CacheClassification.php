<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * What a stored value actually is — which decides what is allowed to remove it (spec 078, FR-002).
 *
 * This exists because "cache" is a description of *where* something is kept, not of what it is
 * worth. CoreX keeps two security controls in transients: `ThrottleMiddleware` counts requests in
 * `corex_throttle_*`, and `TokenReplayGuard` marks spent captcha tokens in `corex_captcha_seen_*`.
 * Both look exactly like cache from the outside, and a sweep of `corex_*` transients — the obvious
 * implementation of "clear CoreX's caches" — would reset brute-force protection and re-open the
 * replay window, at precisely the moment an operator is most likely to press the button, because
 * something is already going wrong.
 *
 * A comment saying "do not clear these" would not have prevented that. A classification the clear
 * path has to consult does.
 */
enum CacheClassification: string
{
    /** Derived from something else and cheap to rebuild. Safe to remove at any time. */
    case SafeCache = 'safe_cache';

    /**
     * A security control that happens to be stored in a fast place. Removing it does not lose data
     * — it removes protection, silently, at the moment it is most needed.
     */
    case SecurityState = 'security_state';

    /**
     * Work the operator is part-way through: a preview token awaiting confirmation. Removing it
     * loses no record, but it does throw away someone's half-finished operation without asking.
     */
    case PendingOperation = 'pending_operation';

    /**
     * A business record. Submissions, audit entries, notifications, access requests, delivery
     * records, history. That some of these are read through a cache does not make them disposable,
     * and nothing in this feature may remove one.
     */
    case Record = 'record';

    /** Settings and declared state. Rebuilt from source, never invented, never dropped casually. */
    case Configuration = 'configuration';

    /** Generated metadata — manifests, inventories. Safe, and usually the thing people mean. */
    case GeneratedMetadata = 'generated_metadata';

    /**
     * Whether an ordinary "clear the caches" may remove this.
     *
     * The single question every clear path asks. `PendingOperation` is deliberately excluded: it is
     * removable, but only when the operator has been told it will discard an in-flight confirmation
     * (FR-004), which is a decision for the caller and not a default.
     */
    public function mayBeClearedRoutinely(): bool
    {
        return match ($this) {
            self::SafeCache, self::GeneratedMetadata => true,
            self::SecurityState, self::Record, self::Configuration, self::PendingOperation => false,
        };
    }

    /**
     * Whether this may be removed at all, by any operation in this feature.
     *
     * Security state and records are absolute: no scope, no flag and no confirmation makes them
     * clearable here. Removing a rate limit is a security operation and belongs to whatever
     * deliberately owns that; removing a record is a data operation and belongs to retention.
     */
    public function mayEverBeCleared(): bool
    {
        return match ($this) {
            self::SecurityState, self::Record => false,
            default => true,
        };
    }

    /** Why an operator was refused, in words that say what the thing is rather than what it is not. */
    public function refusalReason(): string
    {
        return match ($this) {
            self::SecurityState => __(
                'This holds active security state — rate limits and spent one-time tokens. Clearing it would remove protection rather than free space.',
                'corex',
            ),
            self::Record => __(
                'This is a stored record, not a cache. It is removed by retention, never by clearing caches.',
                'corex',
            ),
            self::PendingOperation => __(
                'This holds an operation waiting to be confirmed. Clearing it would discard work somebody is part-way through.',
                'corex',
            ),
            self::Configuration => __(
                'This is configuration. It is changed where it is set, not cleared.',
                'corex',
            ),
            default => '',
        };
    }
}
