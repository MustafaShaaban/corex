<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders an image gallery as a responsive CSS grid of figures, each a real `<img>` (with alt
 * text + lazy loading) and an optional caption. Images are a repeatable array attribute from
 * the media library (spec 029); an image with no URL is skipped, and an empty gallery renders
 * nothing.
 */
final class GalleryRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $images = is_array($attributes['images'] ?? null) ? $attributes['images'] : [];

        $items = '';

        foreach ($images as $image) {
            $items .= $this->item(is_array($image) ? $image : []);
        }

        if ($items === '') {
            return '';
        }

        return '<div class="corex-gallery">' . $items . '</div>';
    }

    /**
     * @param array<string,mixed> $image
     */
    private function item(array $image): string
    {
        $url = trim((string) ($image['url'] ?? ''));

        if ($url === '') {
            return '';
        }

        $alt     = (string) ($image['alt'] ?? '');
        $caption = trim((string) ($image['caption'] ?? ''));

        $img = sprintf(
            '<img class="corex-gallery__img" src="%s" alt="%s" loading="lazy" decoding="async" />',
            esc_url($url),
            esc_attr($alt)
        );

        $html = '<figure class="corex-gallery__item">';
        // Optimized <picture> delivery when Corex Media is active (no hard dependency; class preserved).
        $html .= (string) apply_filters('corex_media_optimize_image', $img, [
            'url'     => $url,
            'alt'     => $alt,
            'class'   => 'corex-gallery__img',
            'loading' => 'lazy',
        ]);

        if ($caption !== '') {
            $html .= sprintf('<figcaption class="corex-gallery__caption">%s</figcaption>', wp_kses_post($caption));
        }

        return $html . '</figure>';
    }
}
