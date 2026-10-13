<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Reset;

defined('ABSPATH') || exit;

/**
 * Where a visitor to the account page is in a password reset.
 */
final readonly class ResetState
{
    /** No reset is under way. */
    public const NONE = 'none';

    /** The visitor holds a key WordPress takes: the new password can be chosen. */
    public const CHOOSE = 'choose';

    /** The visitor arrived with a key WordPress does not take, or no longer takes. */
    public const EXPIRED = 'expired';

    /** The new password has just been saved. */
    public const DONE = 'done';

    private function __construct(
        public string $step,
        public string $login = '',
        public string $key = '',
    ) {
    }

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public static function choose(string $login, string $key): self
    {
        return new self(self::CHOOSE, $login, $key);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function done(): self
    {
        return new self(self::DONE);
    }
}
