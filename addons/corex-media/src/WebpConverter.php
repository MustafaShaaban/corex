<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

use Throwable;

/**
 * Converts an image to WebP per a {@see ConversionPlan} (spec 048) using GD (preferred) or
 * Imagick. Fail-safe boundary: a corrupt/unreadable/oversized image returns false and leaves
 * the original untouched — never a fatal. The original is always preserved (the WebP is a
 * sibling file).
 */
final class WebpConverter
{
    /** Output quality (1-100). Defaults to the Media settings default; injected from settings at boot. */
    public function __construct(private readonly int $quality = MediaSettings::DEFAULT_QUALITY)
    {
    }

    public function convert(ConversionPlan $plan, string $mime): bool
    {
        if (! $plan->convert) {
            return false;
        }

        // The output path doubles as the source-derived base; the source is its non-.webp form.
        $source = (string) preg_replace('/\.webp$/i', '', $plan->outputPath);
        $source = $this->sourceFor($source, $mime);

        if ($source === '' || ! is_file($source)) {
            return false;
        }

        try {
            if (function_exists('imagewebp')) {
                return $this->convertWithGd($source, $mime, $plan->outputPath);
            }

            if (class_exists('Imagick')) {
                return $this->convertWithImagick($source, $plan->outputPath);
            }
        } catch (Throwable) {
            return false; // fail-safe — keep the original
        }

        return false;
    }

    private function sourceFor(string $base, string $mime): string
    {
        foreach ($this->extensions($mime) as $ext) {
            $candidate = $base . '.' . $ext;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function extensions(string $mime): array
    {
        return strtolower($mime) === 'image/png' ? ['png'] : ['jpg', 'jpeg'];
    }

    /**
     * GD's WebP encoder accepts truecolour images only.
     *
     * Handed a palette (indexed-colour) image it raises `Palette image not supported by webp` as an
     * **E_ERROR**, which ends the request. Because this runs on `wp_generate_attachment_metadata`,
     * the request it ends is a media upload: the uploader gets WordPress's critical-error page and
     * the file never arrives. Palette PNGs are ordinary — "PNG-8" is what most design tools export
     * a flat-colour logo or icon as — so this is a routine upload, not an edge case.
     *
     * `convert()` wraps this in `catch (Throwable)`, and that catch **cannot** fire: a GD E_ERROR is
     * not a Throwable. Verified by reproduction — the process dies with the catch in place. So the
     * safety here is a precondition check, not a handler: promote first, and refuse to call the
     * encoder at all unless the image is truecolour by the time we reach it.
     */
    private function convertWithGd(string $source, string $mime, string $output): bool
    {
        $image = strtolower($mime) === 'image/png'
            ? @imagecreatefrompng($source)
            : @imagecreatefromjpeg($source);

        if ($image === false) {
            return false;
        }

        if (! imageistruecolor($image) && ! @imagepalettetotruecolor($image)) {
            // Cannot promote it, so cannot encode it. Returning false keeps the original, which is
            // what the fail-safe promised and could not previously deliver.
            imagedestroy($image);

            return false;
        }

        // Defensive, and honestly labelled: on the GD build this was verified against (PHP 8.3,
        // libwebp) `imagewebp()` preserved the alpha channel with or without these two calls, for
        // both a promoted palette source and a truecolour one. The reporter observed transparency
        // loss on theirs, GD builds differ in this behaviour, and stating the intent costs nothing —
        // but it is not what fixes the fatal, and it should not be described as if it were.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if (! imageistruecolor($image)) {
            imagedestroy($image);

            return false;
        }

        $ok = imagewebp($image, $output, $this->quality);
        imagedestroy($image);

        return $ok;
    }

    private function convertWithImagick(string $source, string $output): bool
    {
        $image = new \Imagick($source);
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($this->quality);
        $ok = $image->writeImage($output);
        $image->clear();

        return $ok;
    }
}
