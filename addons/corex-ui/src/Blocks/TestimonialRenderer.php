<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a testimonial as a semantic, accessible `<figure>` with a `<blockquote>` and a
 * `<figcaption>` attribution (author and optional role). An empty quote renders nothing.
 */
final class TestimonialRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $quote = trim((string) ($attributes['quote'] ?? ''));

        if ($quote === '') {
            return '';
        }

        // Inline-RichText fields (spec 029) → wp_kses_post (safe inline HTML preserved).
        $html = '<figure class="corex-testimonial"><blockquote class="corex-testimonial__quote">'
            . wp_kses_post($quote) . '</blockquote>';

        $cite = $this->attribution(
            trim((string) ($attributes['author'] ?? '')),
            trim((string) ($attributes['role'] ?? '')),
        );

        if ($cite !== '') {
            $html .= '<figcaption class="corex-testimonial__cite">&mdash; ' . wp_kses_post($cite) . '</figcaption>';
        }

        return $html . '</figure>';
    }

    private function attribution(string $author, string $role): string
    {
        if ($author !== '' && $role !== '') {
            return $author . ', ' . $role;
        }

        return $author !== '' ? $author : $role;
    }
}
