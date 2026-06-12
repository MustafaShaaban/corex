<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a pricing card — plan name, price + optional period, a feature list (one feature
 * per line), and an optional call-to-action link. The CTA appears only when both its text
 * and URL are set; the href is escaped with esc_url. Empty plan AND price render nothing.
 */
final class PricingRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $plan  = trim((string) ($attributes['plan'] ?? ''));
        $price = trim((string) ($attributes['price'] ?? ''));

        if ($plan === '' && $price === '') {
            return '';
        }

        // Inline-RichText fields (spec 029) → wp_kses_post; the CTA href stays esc_url.
        $html = '<div class="corex-pricing">';

        if ($plan !== '') {
            $html .= sprintf('<h3 class="corex-pricing__plan">%s</h3>', wp_kses_post($plan));
        }

        if ($price !== '') {
            $html .= '<p class="corex-pricing__price">' . wp_kses_post($price);
            $period = trim((string) ($attributes['period'] ?? ''));

            if ($period !== '') {
                $html .= sprintf('<span class="corex-pricing__period">%s</span>', wp_kses_post($period));
            }

            $html .= '</p>';
        }

        $html .= $this->features($attributes['features'] ?? []);
        $html .= $this->cta(
            trim((string) ($attributes['ctaText'] ?? '')),
            trim((string) ($attributes['ctaUrl'] ?? '')),
        );

        return $html . '</div>';
    }

    /**
     * Feature list. Accepts the spec-029 array attribute (one rich string per item) or, as
     * a fallback, the legacy newline-delimited string so already-placed blocks still render.
     *
     * @param array<int,string>|string $raw
     */
    private function features(array|string $raw): string
    {
        $lines = is_array($raw)
            ? $raw
            : (preg_split('/\r\n|\r|\n/', $raw) ?: []);

        $lines = array_filter(array_map(static fn ($f): string => trim((string) $f), $lines));

        if ($lines === []) {
            return '';
        }

        $items = '';
        foreach ($lines as $feature) {
            $items .= sprintf('<li>%s</li>', wp_kses_post($feature));
        }

        return sprintf('<ul class="corex-pricing__features">%s</ul>', $items);
    }

    private function cta(string $text, string $url): string
    {
        if ($text === '' || $url === '') {
            return '';
        }

        return sprintf('<a class="corex-pricing__cta" href="%s">%s</a>', esc_url($url), wp_kses_post($text));
    }
}
