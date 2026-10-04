<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * What a request to change the operations mode came to (spec 101).
 *
 * `applied` and `proposed` are separate on purpose, as they are in the screen's redirect: one says
 * what the mode now is, the other what to offer the operator next. A request that still owes a
 * confirmation has applied nothing and proposes the mode that owes it.
 */
final readonly class ModeChangeResult
{
    /** The mode was changed, and the change is in the history. */
    public const SAVED = 'saved';

    /** The mode asked for was already declared. Nothing was written. */
    public const UNCHANGED = 'unchanged';

    /** The mode needs a ticked acknowledgement that was not given. Nothing was written. */
    public const NEEDS_ACKNOWLEDGEMENT = 'confirm';

    /** Production needs its typed phrase, which was absent or wrong. Nothing was written. */
    public const NEEDS_PHRASE = 'production_confirm';

    /** The production launch was refused by the launch service. */
    public const BLOCKED = 'blocked';

    /** The mode is not one the framework knows. Nothing was written. */
    public const INVALID = 'invalid';

    private function __construct(
        public string $status,
        public string $applied = '',
        public string $proposed = '',
    ) {
    }

    public static function saved(string $mode): self
    {
        return new self(self::SAVED, applied: $mode);
    }

    public static function unchanged(string $mode): self
    {
        return new self(self::UNCHANGED, applied: $mode);
    }

    public static function needsAcknowledgement(string $mode): self
    {
        return new self(self::NEEDS_ACKNOWLEDGEMENT, proposed: $mode);
    }

    public static function needsPhrase(string $mode): self
    {
        return new self(self::NEEDS_PHRASE, proposed: $mode);
    }

    public static function blocked(string $mode): self
    {
        return new self(self::BLOCKED, applied: $mode);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }
}
