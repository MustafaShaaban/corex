<?php

/**
 * Validates per-site brand.json replacement lists before they merge onto the
 * theme.json defaults.
 *
 * Pure and headless: no WordPress calls. WordPress wiring lives in
 * {@see ThemeServiceProvider}; the merge itself lives in {@see BrandResolver}.
 *
 * The palette and font-family lists are replaced wholesale (never merged by slug),
 * so a per-site list that drops a required slug would silently remove a semantic
 * role. This validator reports such incomplete lists and strips them from the
 * overrides, leaving the complete defaults in place. Complete lists pass through
 * unchanged, preserving wholesale-replacement semantics (FR-009).
 *
 * @package Corex\Theme
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

final class BrandOverrideValidator
{
    /**
     * The wholesale-replacement lists and the canonical semantic roles each must
     * keep. These are the brandable roles from the approved brand foundation — not
     * every theme.json slug: compatibility aliases and fixed framework fonts
     * (`body`, `mono`) are intentionally excluded, so a brand override need not
     * restate them. Required roles are intersected with the live defaults so a role
     * the defaults no longer define is not demanded.
     *
     * @var array<string, array{path: list<string>, required: list<string>}>
     */
    private const REPLACEMENT_LISTS = [
        'settings.color.palette' => [
            'path' => ['settings', 'color', 'palette'],
            'required' => [
                'primary', 'primary-dark', 'accent', 'accent-dark', 'surface', 'surface-alt',
                'border', 'ink', 'ink-soft', 'success', 'warning', 'error', 'info',
            ],
        ],
        'settings.typography.fontFamilies' => [
            'path' => ['settings', 'typography', 'fontFamilies'],
            'required' => ['heading', 'arabic'],
        ],
    ];

    /**
     * @param array<string, mixed> $defaults the resolved theme.json defaults
     * @param array<string, mixed> $override the decoded brand.json overrides
     * @return array{issues: list<array{path: string, missing_slugs: list<string>, note: string}>, overrides: array<string, mixed>}
     */
    public function validate(array $defaults, array $override): array
    {
        $issues = [];
        $overrides = $override;

        foreach (self::REPLACEMENT_LISTS as $key => $spec) {
            $path = $spec['path'];
            $overrideList = $this->dig($override, $path);

            // A list is only validated when the override actually replaces it.
            if (! is_array($overrideList) || $overrideList === []) {
                continue;
            }

            $required = array_values(array_intersect(
                $spec['required'],
                $this->slugs($this->dig($defaults, $path)),
            ));
            $missing = array_values(array_diff($required, $this->slugs($overrideList)));

            if ($missing === []) {
                continue;
            }

            $issues[] = [
                'path' => $key,
                'missing_slugs' => $missing,
                'note' => 'Incomplete replacement list reported; safe defaults retained.',
            ];
            $overrides = $this->forget($overrides, $path);
        }

        return ['issues' => $issues, 'overrides' => $overrides];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function dig(array $data, array $path): mixed
    {
        $cursor = $data;

        foreach ($path as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param mixed $list
     * @return list<string>
     */
    private function slugs(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        $slugs = [];

        foreach ($list as $entry) {
            if (is_array($entry) && isset($entry['slug']) && is_string($entry['slug'])) {
                $slugs[] = $entry['slug'];
            }
        }

        return $slugs;
    }

    /**
     * Remove the value at $path and prune any ancestor maps left empty, so the
     * stripped override never replaces a default branch with an empty array.
     *
     * @param array<string, mixed> $data
     * @param list<string> $path
     * @return array<string, mixed>
     */
    private function forget(array $data, array $path): array
    {
        $head = $path[0];

        if (count($path) === 1) {
            unset($data[$head]);

            return $data;
        }

        if (is_array($data[$head] ?? null)) {
            /** @var array<string, mixed> $child */
            $child = $data[$head];
            $child = $this->forget($child, array_slice($path, 1));

            if ($child === []) {
                unset($data[$head]);
            } else {
                $data[$head] = $child;
            }
        }

        return $data;
    }
}
