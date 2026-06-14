<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * The plan for one uploaded image (spec 048): whether to convert it to WebP and the output
 * path — **always preserving the original** (a sibling `.webp`, never replacing the source).
 * Pure: a JPEG/PNG on a WebP-capable server converts; a non-image, an already-WebP file, or
 * an unsupported server skips (no error — Principle IX).
 */
final class ConversionPlan
{
    private const CONVERTIBLE = ['image/jpeg', 'image/png'];

    private function __construct(
        public readonly bool $convert,
        public readonly string $format,
        public readonly string $outputPath,
    ) {
    }

    public static function none(): self
    {
        return new self(false, '', '');
    }

    public static function for(string $path, string $mime, ImageCapability $capability): self
    {
        if (! $capability->canWebp()) {
            return self::none();
        }
        if (! in_array(strtolower($mime), self::CONVERTIBLE, true)) {
            return self::none();
        }
        if (str_ends_with(strtolower($path), '.webp')) {
            return self::none();
        }

        $output = (string) preg_replace('/\.(jpe?g|png)$/i', '', $path) . '.webp';

        return new self(true, 'webp', $output);
    }
}
