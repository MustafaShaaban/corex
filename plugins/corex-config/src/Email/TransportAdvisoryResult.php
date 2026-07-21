<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Email;

defined('ABSPATH') || exit;

/**
 * A set of safe, evidence-based advisory notes about how mail leaves the site.
 *
 * Every note is credential-free and derived from a public signal. There is always at least the
 * general-guidance note; specific warnings appear only where a real signal supports them.
 */
final class TransportAdvisoryResult
{
    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';

    /** @param list<array{level:string,message:string}> $notes */
    public function __construct(private readonly array $notes)
    {
    }

    /** @return list<array{level:string,message:string}> */
    public function notes(): array
    {
        return $this->notes;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->notes as $note) {
            if ($note['level'] === self::LEVEL_WARNING) {
                return true;
            }
        }

        return false;
    }
}
