<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders recent posts as accessible, escaped, token-styled cards (each a linked
 * heading). The post count is bounded (1–12, default 3); an empty result yields an
 * accessible empty state. Posts come from the injected provider (testable).
 */
final class PostsRenderer implements BlockRenderer
{
    private const MAX = 12;

    public function __construct(private readonly PostsProvider $posts)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $count = min(max((int) ($attributes['count'] ?? 3), 1), self::MAX);
        $items = $this->posts->recent($count);

        if ($items === []) {
            return sprintf('<p class="corex-posts__empty">%s</p>', esc_html__('No posts yet.', 'corex'));
        }

        $cards = '';
        foreach ($items as $item) {
            $cards .= sprintf(
                '<article class="corex-posts__item"><h3 class="corex-posts__title"><a href="%s">%s</a></h3></article>',
                esc_url($item['url']),
                esc_html($item['title'])
            );
        }

        return sprintf('<div class="corex-posts">%s</div>', $cards);
    }
}
