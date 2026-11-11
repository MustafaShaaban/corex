<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * A setting whose value in force is not the site's to change: set in the site's `.env` file, or
 * locked by the network (spec 119, US-SET-02).
 */
final readonly class SettingLock
{
    public const BY_DOTENV  = 'dotenv';
    public const BY_NETWORK = 'network';

    public function __construct(
        public string $by,
        public string $value,
    ) {
    }

    public function isDotenv(): bool
    {
        return $this->by === self::BY_DOTENV;
    }

    /** Where the value is set, in words a site owner can act on. */
    public function label(): string
    {
        return $this->isDotenv()
            ? __('Set in the site\'s .env file', 'corex')
            : __('Locked by the network administrator', 'corex');
    }
}
