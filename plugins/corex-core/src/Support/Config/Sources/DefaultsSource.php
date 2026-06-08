<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config\Sources;

defined('ABSPATH') || exit;

use Corex\Support\Config\Source;

/**
 * The lowest-precedence layer: framework-shipped defaults, looked up by dot key
 * into a nested array.
 */
final class DefaultsSource implements Source
{
    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(private readonly array $defaults)
    {
    }

    public function has(string $key): bool
    {
        return $this->lookup($key)['found'];
    }

    public function get(string $key): mixed
    {
        return $this->lookup($key)['value'];
    }

    /**
     * @return array{found: bool, value: mixed}
     */
    private function lookup(string $key): array
    {
        $current = $this->defaults;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return ['found' => false, 'value' => null];
            }

            $current = $current[$segment];
        }

        return ['found' => true, 'value' => $current];
    }
}
