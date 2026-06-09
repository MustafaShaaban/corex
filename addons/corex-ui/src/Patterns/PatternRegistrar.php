<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Patterns;

defined('ABSPATH') || exit;

/**
 * Registers the Corex pattern category and every section pattern from the library
 * (the boundary to WordPress's pattern APIs).
 */
final class PatternRegistrar
{
    public function __construct(private readonly PatternLibrary $library)
    {
    }

    public function register(): void
    {
        register_block_pattern_category(PatternLibrary::CATEGORY, [
            'label' => __('Corex', 'corex'),
        ]);

        foreach ($this->library->patterns() as $pattern) {
            register_block_pattern($pattern['name'], [
                'title'      => $pattern['title'],
                'categories' => [PatternLibrary::CATEGORY],
                'content'    => $pattern['content'],
            ]);
        }
    }
}
