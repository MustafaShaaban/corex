<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Typed read access to `features.*` flags over the layered Config engine. A flag is
 * ON only for a truthy value (`true`, `1`, `'1'`, `'true'`, `'on'`, `'yes'`) — every
 * other value (including absent) is OFF, so a flag never accidentally enables itself
 * from a stray string. Because it reads through Config, env (`FEATURES_<FLAG>`) and
 * WP options (`corex_features_<flag>`) override the code defaults with no extra wiring.
 */
final class FeatureFlags
{
    private const TRUTHY = ['1', 'true', 'on', 'yes'];

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function enabled(string $flag, bool $default = false): bool
    {
        $value = $this->config->get('features.' . $flag, $default);

        return $this->toBool($value);
    }

    public function disabled(string $flag, bool $default = false): bool
    {
        return ! $this->enabled($flag, $default);
    }

    /**
     * The resolved state of every registered flag (the `features` config namespace),
     * each layered through env/options. Useful for an admin/debug view.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        $registry = $this->config->get('features', []);

        if (! is_array($registry)) {
            return [];
        }

        $resolved = [];

        foreach (array_keys($registry) as $flag) {
            $resolved[(string) $flag] = $this->enabled((string) $flag);
        }

        return $resolved;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), self::TRUTHY, true);
        }

        return false;
    }
}
