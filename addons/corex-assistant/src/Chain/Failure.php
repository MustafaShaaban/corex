<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * Why a model service did not answer, sorted by how long to leave it alone (spec 129, User Story 4).
 */
final class Failure
{
    /** Its limit is reached for now (a per-minute or unstated window). */
    public const LIMIT = 'limit';

    /** Its daily limit is reached. */
    public const DAILY = 'daily';

    /** The key or the model is wrong: retrying soon would fail the same way. */
    public const CONFIG = 'config';

    /**
     * This request was refused (its content, a moderation flag, a malformed field). The service is
     * fine for the next visitor, so it is not rested: one visitor's message must not close the
     * assistant for everyone (Perego's security review, 2026-11-09).
     */
    public const REJECTED = 'rejected';

    /** A server error, a timeout or no connection. */
    public const SERVER = 'server';

    public function __construct(
        public readonly string $kind,
        public readonly int $status = 0,
        public readonly ?int $retryAfter = null,
        public readonly string $detail = '',
    ) {
    }

    /**
     * Seconds to skip the service: the wait it asked for, else a minute; until the next UTC midnight
     * for a daily limit; an hour for a configuration error; none for a refused request.
     */
    public function waitSeconds(int $now): int
    {
        return match ($this->kind) {
            self::LIMIT => max(1, $this->retryAfter ?? 60),
            self::DAILY => max(60, (intdiv($now, 86400) + 1) * 86400 - $now),
            self::CONFIG => 3600,
            self::REJECTED => 0,
            default => 60,
        };
    }
}
