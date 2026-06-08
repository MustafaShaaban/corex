<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * A dynamic block's server render. Resolved from the container, it stays thin —
 * returning escaped, translation-ready markup and delegating any data to an
 * injected service (spec FR-008, FR-009).
 */
interface BlockRenderer
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string;
}
