<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * The optimized-image helper (spec 048): resolves a WordPress attachment into the data the
 * {@see PictureRenderer} needs (the source, the WebP sibling if it exists, srcset/sizes, alt)
 * and renders the `<picture>`. A thin WP boundary; the markup judgement is the pure renderer.
 * Degrades to a plain `<img>` when there is no WebP sibling.
 */
final class MediaImage
{
    public function __construct(private readonly PictureRenderer $renderer)
    {
    }

    /**
     * Optimized markup for a raw image URL (spec 061): a `<picture>` with a WebP `<source>` when a
     * sibling exists on disk, else a plain `<img>`. The seam CoreX UI blocks (which hold URLs, not
     * attachment IDs) can opt into via the `corex_media_optimize_image` filter — no hard dependency.
     * `$opts` may carry `class`, `loading`, and `lcp` so a block keeps its markup when it opts in.
     *
     * @param array{class?:string,loading?:string,lcp?:bool} $opts
     */
    public function pictureForUrl(string $url, string $alt = '', array $opts = []): string
    {
        if ($url === '') {
            return '';
        }

        $image = [
            'src'   => $url,
            'webp'  => $this->webpSibling($url),
            'alt'   => $alt,
            'class' => (string) ($opts['class'] ?? ''),
            'lcp'   => ! empty($opts['lcp']),
        ];

        if (isset($opts['loading'])) {
            $image['loading'] = (string) $opts['loading'];
        }

        return $this->renderer->render($image);
    }

    public function render(int $attachmentId, string $size = 'large', bool $lcp = false): string
    {
        $src = wp_get_attachment_image_url($attachmentId, $size);

        if (! is_string($src) || $src === '') {
            return '';
        }

        return $this->renderer->render([
            'src'    => $src,
            'webp'   => $this->gatedWebp($attachmentId, $src),
            'alt'    => (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
            'srcset' => (string) wp_get_attachment_image_srcset($attachmentId, $size),
            'sizes'  => (string) wp_get_attachment_image_sizes($attachmentId, $size),
            'lcp'    => $lcp,
        ]);
    }

    /**
     * The WebP URL only when the activation gate (spec 062) allows it. When a CoreX `_corex_webp` record
     * exists it is authoritative: a derivative that failed the gate (`active_for_delivery` false) is never
     * served. With no record (e.g. a pre-gate or hand-made sibling) it falls back to the on-disk check.
     */
    private function gatedWebp(int $attachmentId, string $url): string
    {
        $meta = get_post_meta($attachmentId, WebpMeta::META_KEY, true);

        if (is_array($meta) && array_key_exists('active_for_delivery', $meta)) {
            return empty($meta['active_for_delivery']) ? '' : $this->webpSibling($url);
        }

        return $this->webpSibling($url);
    }

    /**
     * The URL of the WebP sibling if the file exists on disk, else '' (the renderer then
     * degrades to a plain <img>).
     */
    private function webpSibling(string $url): string
    {
        $webpUrl  = (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        $uploads  = wp_get_upload_dir();
        $baseUrl  = (string) ($uploads['baseurl'] ?? '');
        $baseDir  = (string) ($uploads['basedir'] ?? '');

        if ($webpUrl === $url || $baseUrl === '' || ! str_starts_with($webpUrl, $baseUrl)) {
            return '';
        }

        $path = $baseDir . substr($webpUrl, strlen($baseUrl));

        return is_file($path) ? $webpUrl : '';
    }
}
