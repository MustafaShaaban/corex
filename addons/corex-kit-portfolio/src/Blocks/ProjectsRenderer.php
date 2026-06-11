<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders recent projects as an accessible, escaped, token-styled grid of cards (each
 * a linked heading with an optional thumbnail). The count is bounded (1–24, default 6);
 * an empty result yields an accessible empty state. Projects come from the injected
 * provider, so the renderer is unit-testable without WordPress.
 */
final class ProjectsRenderer implements BlockRenderer
{
    private const MAX = 24;

    public function __construct(private readonly ProjectsProvider $projects)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $count = min(max((int) ($attributes['count'] ?? 6), 1), self::MAX);
        $items = $this->projects->recent($count);

        if ($items === []) {
            return sprintf('<p class="corex-projects__empty">%s</p>', esc_html__('No projects yet.', 'corex'));
        }

        $cards = '';
        foreach ($items as $item) {
            $cards .= sprintf(
                '<article class="corex-projects__item">%s<h3 class="corex-projects__title"><a href="%s">%s</a></h3></article>',
                $this->thumbnail($item),
                esc_url($item['url']),
                esc_html($item['title'])
            );
        }

        return sprintf('<div class="corex-projects">%s</div>', $cards);
    }

    /**
     * @param array{title:string,url:string,thumbnail:string} $item
     */
    private function thumbnail(array $item): string
    {
        if ($item['thumbnail'] === '') {
            return '';
        }

        return sprintf(
            '<img class="corex-projects__thumb" src="%s" alt="%s" loading="lazy" />',
            esc_url($item['thumbnail']),
            esc_attr($item['title'])
        );
    }
}
