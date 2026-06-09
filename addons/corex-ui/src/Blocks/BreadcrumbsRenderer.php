<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders an accessible breadcrumb trail (`nav` + ordered list), from the site home
 * to the current page. Output is escaped and translation-ready.
 */
final class BreadcrumbsRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $items = [
            sprintf('<a href="%s">%s</a>', esc_url(home_url('/')), esc_html__('Home', 'corex')),
        ];

        $title = (string) get_the_title();
        if ($title !== '') {
            $items[] = sprintf('<span aria-current="page">%s</span>', esc_html($title));
        }

        $list = implode('', array_map(static fn (string $item): string => '<li>' . $item . '</li>', $items));

        return sprintf(
            '<nav class="corex-breadcrumbs" aria-label="%s"><ol>%s</ol></nav>',
            esc_attr__('Breadcrumb', 'corex'),
            $list
        );
    }
}
