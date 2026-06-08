<?php

/**
 * Resolves per-site brand.json overrides onto the theme.json token defaults.
 *
 * Pure and headless: no WordPress calls. The WP boundary (the
 * `wp_theme_json_data_theme` filter) lives in {@see ThemeServiceProvider}.
 *
 * @package Corex\Theme
 */

declare(strict_types=1);

namespace Corex\Theme;

use Corex\Support\BootLogger;

defined('ABSPATH') || exit;

final class BrandResolver
{
    public function __construct(private readonly BootLogger $logger)
    {
    }

    /**
     * Deep-merge $brand onto $defaults. Associative arrays are merged key-by-key
     * (the deepest overriding key wins, siblings preserved, unknown keys added);
     * scalars and lists are replaced wholesale.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $brand
     * @return array<string, mixed>
     */
    public function merge(array $defaults, array $brand): array
    {
        foreach ($brand as $key => $value) {
            $existing = $defaults[$key] ?? null;

            if (is_array($value) && is_array($existing) && $this->isAssoc($value) && $this->isAssoc($existing)) {
                $defaults[$key] = $this->merge($existing, $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    /**
     * Read and decode a brand.json. A missing file yields []; a malformed file
     * yields [] and is logged so the defaults stand.
     *
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            $this->logger->warning(sprintf('malformed brand.json at %s; overrides ignored.', $path));

            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * A non-empty array whose keys are not the contiguous 0..n-1 list keys is
     * treated as associative (deep-merged); a list is replaced wholesale.
     *
     * @param array<array-key, mixed> $value
     */
    private function isAssoc(array $value): bool
    {
        return $value !== [] && ! array_is_list($value);
    }
}
