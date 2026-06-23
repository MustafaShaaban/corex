<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a page hero — an optional eyebrow, a prominent heading, a subheadline, an optional
 * call-to-action button, and an optional background image. Text fields are edited inline with
 * RichText (spec 029) so they may carry safe inline HTML (wp_kses_post); the CTA renders only
 * when both its text and URL are set. An empty title renders nothing (graceful default).
 */
final class HeroRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $title = trim((string) ($attributes['title'] ?? ''));

        if ($title === '') {
            return '';
        }

        $eyebrow  = trim((string) ($attributes['eyebrow'] ?? ''));
        $subtitle = trim((string) ($attributes['subtitle'] ?? ''));
        $level    = $this->headingLevel($attributes['level'] ?? 2);

        $html = '<section class="corex-hero">';
        $html .= $this->backgroundImage($attributes['image'] ?? null);
        $html .= '<div class="corex-hero__inner">';

        if ($eyebrow !== '') {
            $html .= sprintf('<p class="corex-hero__eyebrow">%s</p>', wp_kses_post($eyebrow));
        }

        $html .= sprintf('<h%1$d class="corex-hero__title">%2$s</h%1$d>', $level, wp_kses_post($title));

        if ($subtitle !== '') {
            $html .= sprintf('<p class="corex-hero__subtitle">%s</p>', wp_kses_post($subtitle));
        }

        $html .= $this->cta($attributes);

        return $html . '</div></section>';
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function cta(array $attributes): string
    {
        $text = trim((string) ($attributes['ctaText'] ?? ''));
        $url  = trim((string) ($attributes['ctaUrl'] ?? ''));

        if ($text === '' || $url === '') {
            return '';
        }

        return sprintf('<a class="corex-hero__cta" href="%s">%s</a>', esc_url($url), wp_kses_post($text));
    }

    /**
     * @param mixed $image the media attribute ({url, alt})
     */
    private function backgroundImage(mixed $image): string
    {
        $url = is_array($image) ? trim((string) ($image['url'] ?? '')) : '';

        if ($url === '') {
            return '';
        }

        $alt = is_array($image) ? (string) ($image['alt'] ?? '') : '';

        $img = sprintf(
            '<img class="corex-hero__bg" src="%s" alt="%s" loading="lazy" decoding="async" />',
            esc_url($url),
            esc_attr($alt)
        );

        // Opt into optimized <picture> delivery when Corex Media is active (no hard dependency):
        // it returns this <img> unchanged when there is no gated WebP sibling, and preserves the class.
        return (string) apply_filters('corex_media_optimize_image', $img, [
            'url'     => $url,
            'alt'     => $alt,
            'class'   => 'corex-hero__bg',
            'loading' => 'lazy',
        ]);
    }

    private function headingLevel(mixed $level): int
    {
        $level = (int) $level;

        return ($level >= 1 && $level <= 6) ? $level : 2;
    }
}
