<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings\Providers;

defined('ABSPATH') || exit;

/**
 * What a provider test found (spec 119, US-SET-03): whether the key worked, and the reason in a
 * few words, said after "Last test failed on <date>:". Never carries the key.
 */
final readonly class ProviderTestOutcome
{
    private function __construct(
        public bool $worked,
        public string $reason,
    ) {
    }

    public static function worked(): self
    {
        return new self(true, '');
    }

    public static function failed(string $reason): self
    {
        return new self(false, $reason);
    }
}
