<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Operations;

defined('ABSPATH') || exit;

/**
 * What a closed mode tells visitors (spec 124, US-OPS-06 and US-VIS-04): the owner's message in each
 * site language, and the time the site expects to be back, or none.
 *
 * It also owns the one rule for `Retry-After`, so the maintenance page and the refused submission
 * cannot tell a client two different times: the seconds until the back-by time (at least a
 * minute), ten minutes once that time has passed, an hour when there is none.
 */
final readonly class VisitorMessage
{
    public const MAX_LENGTH = 300;

    private const RETRY_WITHOUT_TIME = 3600;
    private const RETRY_ONCE_LATE    = 600;
    private const RETRY_AT_LEAST     = 60;

    /**
     * @param array<string,string> $messages locale => the owner's text, never empty strings
     * @param int                  $backBy   a Unix time, 0 for none
     */
    public function __construct(
        public array $messages = [],
        public int $backBy = 0,
    ) {
    }

    /** The owner's text for a language, else the site language's, else the first written; '' for none. */
    public function messageFor(string $locale, string $siteLocale = ''): string
    {
        foreach ([$locale, $siteLocale] as $candidate) {
            if ($candidate !== '' && ($this->messages[$candidate] ?? '') !== '') {
                return $this->messages[$candidate];
            }
        }

        return (string) (array_values($this->messages)[0] ?? '');
    }

    public function hasBackBy(): bool
    {
        return $this->backBy > 0;
    }

    /** Whether the back-by time is set and has passed. */
    public function isLate(int $now): bool
    {
        return $this->hasBackBy() && $now >= $this->backBy;
    }

    /** The seconds a client is told to wait before asking again. */
    public function retryAfter(int $now): int
    {
        if (! $this->hasBackBy()) {
            return self::RETRY_WITHOUT_TIME;
        }

        if ($this->isLate($now)) {
            return self::RETRY_ONCE_LATE;
        }

        return max(self::RETRY_AT_LEAST, $this->backBy - $now);
    }
}
