<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Who made an export and when, as a document says it.
 */
final class ExportAttribution
{
    /** The same form a date and time takes in a cell, so a document reads one way throughout. */
    private const WHEN = 'Y-m-d H:i';

    /**
     * @param string            $name The person's name; '' when they can no longer be named.
     * @param DateTimeImmutable $at   Already in the timezone it should be read in.
     */
    public static function line(string $name, DateTimeImmutable $at): string
    {
        $when = $at->format(self::WHEN);

        return $name === '' ? $when : $name . ', ' . $when;
    }
}
