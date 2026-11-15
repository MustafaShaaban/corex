<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Facades\AdminDate;

/**
 * One remembered provider test: when, whether the keys worked, and why not (spec 119, US-SET-03).
 */
final readonly class ProviderTestRecord
{
    public function __construct(
        public int $at,
        public bool $worked,
        public string $reason,
    ) {
    }

    /** "Last tested on <date>: working", or "Last test failed on <date>: <reason>". */
    public function line(): string
    {
        $when = (string) AdminDate::date($this->at);

        return $this->worked
            /* translators: %s: the date of the test. */
            ? sprintf(__('Last tested on %s: working', 'corex'), $when)
            /* translators: 1: the date of the test. 2: why the test failed, in words. */
            : sprintf(__('Last test failed on %1$s: %2$s', 'corex'), $when, $this->reason);
    }

    /** What a provider never tested, or tested with other values, reads. */
    public static function untested(): string
    {
        return __('Not tested yet', 'corex');
    }
}
