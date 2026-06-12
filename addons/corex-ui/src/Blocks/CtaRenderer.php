<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a call-to-action banner — a heading, a supporting line, and a single button. Text
 * fields are edited inline with RichText (spec 029 → wp_kses_post). The button renders only
 * when both its text and URL are set; an empty title renders nothing (graceful default).
 */
final class CtaRenderer implements BlockRenderer
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

        $text = trim((string) ($attributes['text'] ?? ''));

        $html = '<div class="corex-cta"><div class="corex-cta__inner">';
        $html .= sprintf('<h2 class="corex-cta__title">%s</h2>', wp_kses_post($title));

        if ($text !== '') {
            $html .= sprintf('<p class="corex-cta__text">%s</p>', wp_kses_post($text));
        }

        $html .= $this->button($attributes);

        return $html . '</div></div>';
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function button(array $attributes): string
    {
        $text = trim((string) ($attributes['ctaText'] ?? ''));
        $url  = trim((string) ($attributes['ctaUrl'] ?? ''));

        if ($text === '' || $url === '') {
            return '';
        }

        return sprintf('<a class="corex-cta__button" href="%s">%s</a>', esc_url($url), wp_kses_post($text));
    }
}
