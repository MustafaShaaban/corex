<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use DomainException;

/**
 * A person asked for personal data in a file and was never given the right to take it out
 * (spec 109, D3).
 *
 * Its own type because the gateway reads a refusal's status from the words of its message, and
 * this message is translated: on an Arabic site no English word is in it to read.
 */
final class PersonalDataNotAllowed extends DomainException
{
    public static function inAnExport(): self
    {
        return new self(__(
            'You are not allowed to export personal data. Export without those columns, or ask for the right on Access & Abilities.',
            'corex',
        ));
    }
}
