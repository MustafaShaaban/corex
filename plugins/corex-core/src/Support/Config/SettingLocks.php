<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Which settings are locked, and by what (spec 119, US-SET-02).
 *
 * The config engine reads the site's `.env` file first and a network lock second, before the
 * site's own option. A screen that edits the option has to know when one of those two answers
 * instead, or it offers a field whose saves change nothing. Given the same two layers the engine
 * reads, so the answer here is the one the engine gives.
 */
final class SettingLocks
{
    public function __construct(
        private readonly Source $dotenv,
        private readonly Source $network,
    ) {
    }

    public function lockOf(string $key): ?SettingLock
    {
        if ($this->dotenv->has($key)) {
            return new SettingLock(SettingLock::BY_DOTENV, self::asText($this->dotenv->get($key)));
        }

        if ($this->network->has($key)) {
            return new SettingLock(SettingLock::BY_NETWORK, self::asText($this->network->get($key)));
        }

        return null;
    }

    private static function asText(mixed $value): string
    {
        return match (true) {
            is_bool($value)   => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default           => '',
        };
    }
}
