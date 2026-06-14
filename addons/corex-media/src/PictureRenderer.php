<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * Renders an optimized, accessible `<picture>` (spec 048): a WebP `<source>` + an `<img>`
 * fallback (original format) with the real alt, `loading="lazy"` + `decoding="async"` by
 * default, and `fetchpriority="high"` + eager for a designated LCP/hero image. With no WebP
 * variant it degrades to a plain optimized `<img>`. Pure + escaped (esc_url/esc_attr).
 *
 * @phpstan-type ImageData array{src?:string,webp?:string,alt?:string,srcset?:string,sizes?:string,lcp?:bool}
 */
final class PictureRenderer
{
    /**
     * @param array<string,mixed> $image src, webp, alt, srcset, sizes, lcp
     */
    public function render(array $image): string
    {
        $src = (string) ($image['src'] ?? '');

        if ($src === '') {
            return '';
        }

        $img  = $this->img($image, $src);
        $webp = (string) ($image['webp'] ?? '');

        if ($webp === '') {
            return $img;
        }

        $source = sprintf('<source type="image/webp" srcset="%s" />', esc_attr($webp));

        return '<picture>' . $source . $img . '</picture>';
    }

    /**
     * @param array<string,mixed> $image
     */
    private function img(array $image, string $src): string
    {
        $isLcp  = ! empty($image['lcp']);
        $srcset = (string) ($image['srcset'] ?? '');

        $attrs = sprintf('src="%s" alt="%s" decoding="async"', esc_url($src), esc_attr((string) ($image['alt'] ?? '')));

        // LCP/hero image loads eagerly with high priority; everything else is lazy.
        $attrs .= $isLcp ? ' fetchpriority="high"' : ' loading="lazy"';

        if ($srcset !== '') {
            $attrs .= sprintf(
                ' srcset="%s" sizes="%s"',
                esc_attr($srcset),
                esc_attr((string) ($image['sizes'] ?? '100vw')),
            );
        }

        return '<img ' . $attrs . ' />';
    }
}
