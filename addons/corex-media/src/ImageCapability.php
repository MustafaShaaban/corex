<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * What the server can do with images (spec 048): GD / Imagick presence and WebP / AVIF
 * support. A pure value object (so it is unit-tested) + a static `detect()` boundary that
 * reads the runtime. Conversion is gated on `canWebp()`; absence is never an error.
 */
final class ImageCapability
{
    public function __construct(
        public readonly bool $gd,
        public readonly bool $imagick,
        public readonly bool $webp,
        public readonly bool $avif,
    ) {
    }

    public static function detect(): self
    {
        $gd      = function_exists('imagecreatetruecolor');
        $imagick = class_exists('Imagick');

        $webp = function_exists('imagewebp')
            || ($imagick && self::imagickSupports('WEBP'));
        $avif = function_exists('imageavif')
            || ($imagick && self::imagickSupports('AVIF'));

        return new self($gd, $imagick, $webp, $avif);
    }

    /** A converter (GD or Imagick) exists. */
    public function canConvert(): bool
    {
        return $this->gd || $this->imagick;
    }

    /** WebP can actually be written. */
    public function canWebp(): bool
    {
        return $this->canConvert() && $this->webp;
    }

    private static function imagickSupports(string $format): bool
    {
        return class_exists('Imagick') && \Imagick::queryFormats($format) !== [];
    }
}
