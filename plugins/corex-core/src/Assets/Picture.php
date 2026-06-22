<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * Renders a `<picture>` for a source-controlled image (spec 062): a WebP `<source>` + an `<img>` fallback
 * when a built `.webp` sibling exists next to the source on disk, else a plain `<img>`. This is the
 * theme/plugin/client build-asset counterpart to `Corex\Media\PictureRenderer` (Media Library uploads) —
 * it does not depend on the optional corex-media add-on.
 */
final class Picture
{
    /**
     * @param array{base?:string,alt?:string,class?:string,loading?:string,decoding?:string,width?:int,height?:int} $attrs
     */
    public static function render(string $relative, array $attrs = []): string
    {
        $manager = Assets::manager($attrs['base'] ?? null);
        $url     = $manager->url($relative);
        $img     = Image::img($url, $attrs);

        $webpRelative = self::webpSibling($relative);

        if ($webpRelative === null || ! is_file($manager->path($webpRelative))) {
            return $img;
        }

        $source = sprintf('<source type="image/webp" srcset="%s" />', esc_url($manager->url($webpRelative)));

        return '<picture>' . $source . $img . '</picture>';
    }

    /** The `.webp` sibling path for a raster source, or null for a non-raster / already-webp path. */
    private static function webpSibling(string $relative): ?string
    {
        if (preg_match('/\.(jpe?g|png)$/i', $relative) !== 1) {
            return null;
        }

        return (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $relative);
    }
}
