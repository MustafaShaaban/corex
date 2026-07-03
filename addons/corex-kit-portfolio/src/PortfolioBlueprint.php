<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio;

defined('ABSPATH') || exit;

use Corex\Kit\Blueprint;

/**
 * The Portfolio kit: a `corex_project` CPT, the dynamic projects-grid block, and the
 * portfolio FSE templates, composed into a creative/portfolio site. Needs the blocks
 * engine; the UI library enhances it with section patterns.
 */
final class PortfolioBlueprint extends Blueprint
{
    public function name(): string
    {
        return 'portfolio';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['corex-blocks'];
    }

    /**
     * @return list<string>
     */
    public function recommendedModules(): array
    {
        return ['corex-ui', 'corex-forms'];
    }

    /**
     * @return list<string>
     */
    public function templates(): array
    {
        return ['archive-corex_project', 'single-corex_project', 'front-page', 'page', 'index'];
    }

    /**
     * @return list<string>
     */
    public function parts(): array
    {
        return ['header', 'footer'];
    }

    /**
     * @return list<string>
     */
    public function patterns(): array
    {
        return ['corex/hero', 'corex/cta'];
    }

    /**
     * @return list<array{title:string,slug:string,content:string,front?:bool}>
     */
    public function pages(): array
    {
        return [
            [
                'title'   => __('Home', 'corex'),
                'slug'    => 'home',
                'front'   => true,
                'content' => '<!-- wp:pattern {"slug":"corex/hero"} /-->'
                    . '<!-- wp:heading {"level":2} --><h2>' . esc_html__('Selected work', 'corex') . '</h2><!-- /wp:heading -->'
                    . '<!-- wp:corex/projects /-->'
                    . '<!-- wp:pattern {"slug":"corex/cta"} /-->',
            ],
            [
                'title'   => __('Projects', 'corex'),
                'slug'    => 'projects',
                'content' => '<!-- wp:heading {"level":1} --><h1>' . esc_html__('Projects', 'corex') . '</h1><!-- /wp:heading -->'
                    . '<!-- wp:corex/projects /-->',
            ],
        ];
    }
}
