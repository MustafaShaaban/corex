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

    /**
     * Plan one image. Honours {@see MediaSettings} (conversion on/off, per-format toggles) when given;
     * with none it uses the defaults (both formats on) so existing callers keep working.
     */
    public static function for(string $path, string $mime, ImageCapability $capability, ?MediaSettings $settings = null): self
    {
        $settings ??= MediaSettings::defaults();

        if (! $capability->canWebp() || ! $settings->enabled) {
            return self::none();
        }
        if (! in_array(strtolower($mime), $settings->convertibleMimes(), true)) {
            return self::none();
        }
        if (str_ends_with(strtolower($path), '.webp')) {
            return self::none();
        }

        $output = (string) preg_replace('/\.(jpe?g|png)$/i', '', $path) . '.webp';

        return new self(true, 'webp', $output);
    }
}
