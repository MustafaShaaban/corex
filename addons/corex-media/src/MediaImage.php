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

    public function render(int $attachmentId, string $size = 'large', bool $lcp = false): string
    {
        $src = wp_get_attachment_image_url($attachmentId, $size);

        if (! is_string($src) || $src === '') {
            return '';
        }

        return $this->renderer->render([
            'src'    => $src,
            'webp'   => $this->webpSibling($src),
            'alt'    => (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
            'srcset' => (string) wp_get_attachment_image_srcset($attachmentId, $size),
            'sizes'  => (string) wp_get_attachment_image_sizes($attachmentId, $size),
            'lcp'    => $lcp,
        ]);
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
