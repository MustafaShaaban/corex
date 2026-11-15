<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * What a release from the screen did (spec 124, FR-017): released some lockout rows, found the
 * lockout already ended, found no such lockout, or found no active lockout to release.
 */
final readonly class LockoutReleaseOutcome
{
    public const RELEASED = 'released';
    public const ALREADY_ENDED = 'already_ended';
    public const NOT_FOUND = 'not_found';
    public const NONE_ACTIVE = 'none_active';

    private function __construct(public string $status, public int $released)
    {
    }

    public static function released(int $rows): self
    {
        return new self(self::RELEASED, $rows);
    }

    public static function alreadyEnded(): self
    {
        return new self(self::ALREADY_ENDED, 0);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND, 0);
    }

    public static function noneActive(): self
    {
        return new self(self::NONE_ACTIVE, 0);
    }
}
