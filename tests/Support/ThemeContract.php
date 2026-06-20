<?php

/**
 * Shared readers and calculations for the Spec 057 file contracts.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class ThemeContract
{
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, mixed> */
    public static function json(string $relativePath): array
    {
        $path = self::root() . '/' . ltrim($relativePath, '/');

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Required JSON file is missing: %s', $relativePath));
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Invalid JSON file: %s', $relativePath));
        }

        return $decoded;
    }

    /** @return list<string> */
    public static function sourceFiles(): array
    {
        $files = [];

        foreach (['theme', 'plugins', 'addons', 'packages'] as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::root() . '/' . $root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(self::root()) + 1));

                if (! $file->isFile()
                    || preg_match('#/(?:node_modules|vendor|build|dist)/#', $relative)
                    || ! preg_match('/\.(?:css|scss|json|php|js)$/', $relative)
                ) {
                    continue;
                }

                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    /** @return array<string, list<string>> */
    public static function variableReferences(): array
    {
        $references = [];

        foreach (self::sourceFiles() as $relative) {
            $content = (string) file_get_contents(self::root() . '/' . $relative);
            preg_match_all('/var\((--(?:wp--(?:preset|custom)--|corex-)[a-zA-Z0-9_-]+)/', $content, $matches);

            if ($matches[1] === []) {
                continue;
            }

            $properties = array_values(array_unique($matches[1]));
            sort($properties);
            $references[$relative] = $properties;
        }

        return $references;
    }

    /** @return list<string> */
    public static function paletteSlugs(array $themeJson): array
    {
        return array_values(array_column($themeJson['settings']['color']['palette'] ?? [], 'slug'));
    }

    /** @return list<string> */
    public static function fontFamilySlugs(array $themeJson): array
    {
        return array_values(array_column($themeJson['settings']['typography']['fontFamilies'] ?? [], 'slug'));
    }

    /** @return array<string, string> */
    public static function palette(array $themeJson): array
    {
        $palette = [];

        foreach ($themeJson['settings']['color']['palette'] ?? [] as $entry) {
            $palette[(string) $entry['slug']] = (string) $entry['color'];
        }

        return $palette;
    }

    public static function contrastRatio(string $foreground, string $background): float
    {
        $lighter = max(self::luminance($foreground), self::luminance($background));
        $darker = min(self::luminance($foreground), self::luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = implode('', array_map(static fn (string $value): string => $value . $value, str_split($hex)));
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new RuntimeException(sprintf('Contrast evidence requires a six-digit hex color, got %s', $hex));
        }

        $channels = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }
}
