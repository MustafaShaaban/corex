<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a footer copyright line: the current year + the site name, escaped and
 * translation-ready.
 */
final class CopyrightRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $line = sprintf(
            /* translators: 1: year, 2: site name */
            __('© %1$s %2$s. All rights reserved.', 'corex'),
            gmdate('Y'),
            get_bloginfo('name')
        );

        return sprintf('<p class="corex-copyright">%s</p>', esc_html($line));
    }
}
