<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * Renders a source-controlled image (theme/plugin/client asset, spec 062) — distinct from a WordPress
 * Media Library upload (which is `Corex\Media\*`). `tag()` emits a plain optimized `<img>`; `picture()`
 * delegates to {@see Picture} for a WebP `<source>` + `<img>` fallback when a built `.webp` sibling
 * exists. The URL is resolved (manifest-hashed when present) through the {@see AssetManager}.
 *
 *   echo Corex\Assets\Image::picture('images/hero.jpg', ['alt' => 'Hero', 'class' => 'hero__image']);
 */
final class Image
{
    /**
     * @param array{base?:string,alt?:string,class?:string,loading?:string,decoding?:string,width?:int,height?:int} $attrs
     */
    public static function tag(string $relative, array $attrs = []): string
    {
        $url = Assets::manager($attrs['base'] ?? null)->url($relative);

        return self::img($url, $attrs);
    }

    /** Optimized `<picture>` (WebP `<source>` + `<img>` fallback) when a built `.webp` sibling exists. */
    public static function picture(string $relative, array $attrs = []): string
    {
        return Picture::render($relative, $attrs);
    }

    /**
     * Build one `<img>` from a resolved URL + attributes. Escaped; lazy + async by default.
     *
     * @param array<string,mixed> $attrs
     */
    public static function img(string $url, array $attrs = []): string
    {
        $parts = [sprintf('src="%s"', esc_url($url))];
        $parts[] = sprintf('alt="%s"', esc_attr((string) ($attrs['alt'] ?? '')));

        if (! empty($attrs['class'])) {
            $parts[] = sprintf('class="%s"', esc_attr((string) $attrs['class']));
        }
        foreach (['width', 'height'] as $dim) {
            if (isset($attrs[$dim])) {
                $parts[] = sprintf('%s="%d"', $dim, (int) $attrs[$dim]);
            }
        }
        $parts[] = sprintf('loading="%s"', esc_attr((string) ($attrs['loading'] ?? 'lazy')));
        $parts[] = sprintf('decoding="%s"', esc_attr((string) ($attrs['decoding'] ?? 'async')));

        return '<img ' . implode(' ', $parts) . ' />';
    }
}
