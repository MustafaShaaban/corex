<?php

/**
 * @package Corex\Core
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * An access request or role change refused for what the caller sent, before anything was stored.
 *
 * It is an `InvalidArgumentException`, so a caller that catches that still catches this. It is a
 * class of its own so that a boundary can tell it from every other one: a REST route answers this
 * as the caller's mistake and names the parameter, and has to leave an `InvalidArgumentException`
 * raised by a mistake in CoreX to be answered as CoreX's failure.
 *
 * The message is for a log. What a person is shown is chosen, and translated, where it is shown.
 */
final class InvalidAccessInput extends InvalidArgumentException
{
    public const REASON_REQUIRED        = 'reason_required';
    public const REASON_TOO_LONG        = 'reason_too_long';
    public const TARGET_REQUIRED        = 'target_required';
    public const TARGET_AMBIGUOUS       = 'target_ambiguous';
    public const ABILITY_UNKNOWN        = 'ability_unknown';
    public const AREA_UNKNOWN           = 'area_unknown';
    public const ROLE_INVALID           = 'role_invalid';
    public const EFFECT_UNSUPPORTED     = 'effect_unsupported';
    public const CONFIRMATION_MALFORMED = 'confirmation_malformed';

    /**
     * @param string $parameter The part of what was sent that is refused, as its sender named it.
     * @param string $refusal   One of this class's constants: why, in a form a script can branch on.
     */
    public function __construct(
        public readonly string $parameter,
        public readonly string $refusal,
        string $message,
    ) {
        parent::__construct($message);
    }
}
