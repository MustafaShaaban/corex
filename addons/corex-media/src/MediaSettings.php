<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

use Corex\Support\Config\ConfigInterface;

defined('ABSPATH') || exit;

/**
 * The Corex Media WebP settings (spec 061): whether conversion is on, the output quality, and the
 * per-format toggles — read from Config dot-keys (settings UI / .env), each overridable by a filter.
 * A pure value object so it is unit-tested; `fromConfig()` is the only boundary. Originals are
 * **always preserved** (never a setting) — the WebP is a sibling file.
 *
 *   media.webp.enabled       (bool, default true)   filter: corex_media_webp_enabled
 *   media.webp.quality       (1-100, default 82)    filter: corex_media_webp_quality
 *   media.webp.convert_jpeg  (bool, default true)   filter: corex_media_convert_jpeg
 *   media.webp.convert_png   (bool, default true)   filter: corex_media_convert_png
 */
final class MediaSettings
{
    public const DEFAULT_QUALITY = 82;

    public function __construct(
        public readonly bool $enabled,
        public readonly int $quality,
        public readonly bool $convertJpeg,
        public readonly bool $convertPng,
    ) {
    }

    public static function defaults(): self
    {
        return new self(true, self::DEFAULT_QUALITY, true, true);
    }

    public static function fromConfig(ConfigInterface $config): self
    {
        $enabled = self::flag($config->get('media.webp.enabled', true), true);
        $enabled = (bool) apply_filters('corex_media_webp_enabled', $enabled);

        $quality = self::clampQuality($config->get('media.webp.quality', self::DEFAULT_QUALITY));
        $quality = self::clampQuality(apply_filters('corex_media_webp_quality', $quality));

        $jpeg = self::flag($config->get('media.webp.convert_jpeg', true), true);
        $jpeg = (bool) apply_filters('corex_media_convert_jpeg', $jpeg);

        $png = self::flag($config->get('media.webp.convert_png', true), true);
        $png = (bool) apply_filters('corex_media_convert_png', $png);

        return new self($enabled, $quality, $jpeg, $png);
    }

    /**
     * The MIME types that should be converted given the per-format toggles, filterable as a whole.
     *
     * @return list<string>
     */
    public function convertibleMimes(): array
    {
        $mimes = [];
        if ($this->convertJpeg) {
            $mimes[] = 'image/jpeg';
        }
        if ($this->convertPng) {
            $mimes[] = 'image/png';
        }

        /** @var list<string> $filtered */
        $filtered = (array) apply_filters('corex_media_convertible_mimes', $mimes, $this);

        return array_values(array_filter(array_map('strval', $filtered)));
    }

    /** Clamp any config/filter input to a valid 1-100 quality, falling back to the default. */
    private static function clampQuality(mixed $value): int
    {
        $int = is_numeric($value) ? (int) $value : self::DEFAULT_QUALITY;

        return max(1, min(100, $int));
    }

    /** Coerce a config flag (which may be '0'/'1'/'true'/bool) to a real bool. */
    private static function flag(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if ($v === '' ) {
                return $default;
            }

            return ! in_array($v, ['0', 'false', 'no', 'off'], true);
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        return $default;
    }
}
