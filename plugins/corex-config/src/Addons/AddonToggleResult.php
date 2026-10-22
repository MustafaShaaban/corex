<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * What happened when an add-on was turned on or off (spec 114, US-SAFE-08): whether it worked
 * and, when it did not, WordPress's reason in its own words.
 */
final class AddonToggleResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly string $reason,
    ) {
    }

    public static function succeeded(): self
    {
        return new self(true, '');
    }

    public static function failed(string $reason): self
    {
        return new self(false, $reason);
    }
}
