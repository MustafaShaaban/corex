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
        return ['archive-project', 'single-project', 'front-page', 'page', 'index'];
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
}
