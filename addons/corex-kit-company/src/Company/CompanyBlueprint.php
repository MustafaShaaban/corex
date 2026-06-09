<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Company;

defined('ABSPATH') || exit;

use Corex\Kit\Blueprint;

/**
 * The Company Website kit: composes the Corex UI section patterns with the theme's
 * universal FSE templates into a neutral company site. Needs the UI library; the
 * forms + mail add-ons enhance the contact section.
 */
final class CompanyBlueprint extends Blueprint
{
    public function name(): string
    {
        return 'company';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['corex-ui'];
    }

    /**
     * @return list<string>
     */
    public function recommendedModules(): array
    {
        return ['corex-forms', 'corex-email'];
    }

    /**
     * @return list<string>
     */
    public function templates(): array
    {
        return ['front-page', 'page', 'single', 'archive', 'search', '404', 'index'];
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
        return ['corex/hero', 'corex/features', 'corex/cta', 'corex/testimonial', 'corex/contact'];
    }
}
