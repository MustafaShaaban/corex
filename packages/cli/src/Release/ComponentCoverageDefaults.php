<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Default native-first component coverage for the first company-identity sites.
 */
final class ComponentCoverageDefaults
{
    private const ACCESSIBILITY = 'WCAG 2.2 AA keyboard, labels, semantic headings, and visible focus';
    private const TOKEN_STRATEGY = 'theme.json CSS variables with per-site brand.json overrides';
    private const RTL_STRATEGY = 'logical properties and WordPress RTL-aware layout controls';

    /**
     * @return list<string>
     */
    public static function requiredNeeds(): array
    {
        return [
            'home',
            'about',
            'services',
            'contact',
            'careers',
            'portfolio',
            'forms',
            'listings',
            'cards',
            'testimonials',
            'ctas',
            'media',
            'navigation',
            'page templates',
        ];
    }

    public static function matrix(): ComponentCoverageMatrix
    {
        return ComponentCoverageMatrix::fromItems(array_map(
            static fn (array $item): ComponentCoverageItem => ComponentCoverageItem::fromArray($item),
            self::items(),
        ));
    }

    /**
     * @return list<array{
     *     need: string,
     *     mechanism: string,
     *     source: string,
     *     accessibility: string,
     *     tokenStrategy: string,
     *     rtlStrategy: string,
     *     freePro: string
     * }>
     */
    private static function items(): array
    {
        $rows = [
            ['home', ComponentCoverageItem::MECHANISM_PATTERN, 'corex-ui PatternLibrary landing/home sections', self::ACCESSIBILITY, 'free-core'],
            ['about', ComponentCoverageItem::MECHANISM_PATTERN, 'corex-ui PatternLibrary content-split and section-header patterns', self::ACCESSIBILITY, 'free-core'],
            ['services', ComponentCoverageItem::MECHANISM_PATTERN, 'WordPress group/columns plus corex-ui cards and stats patterns', self::ACCESSIBILITY, 'free-core'],
            ['contact', ComponentCoverageItem::MECHANISM_FORM_FIELD, 'corex-forms contact form schema and corex/form block', 'WCAG 2.2 AA labels, errors, required state, keyboard submission, and status feedback', 'free-core'],
            ['careers', ComponentCoverageItem::MECHANISM_DEFERRED, 'corex-careers add-on remains optional and inactive unless the client needs jobs', self::ACCESSIBILITY, 'deferred'],
            ['portfolio', ComponentCoverageItem::MECHANISM_DEFERRED, 'corex-kit-portfolio add-on remains optional and inactive unless the client needs projects', self::ACCESSIBILITY, 'deferred'],
            ['forms', ComponentCoverageItem::MECHANISM_FORM_FIELD, 'corex-forms field schema, validator, and server-rendered form block', 'WCAG 2.2 AA labels, described errors, keyboard navigation, and announced responses', 'free-core'],
            ['listings', ComponentCoverageItem::MECHANISM_COREX_BLOCK, 'corex/posts and existing query/listing renderers', self::ACCESSIBILITY, 'free-core'],
            ['cards', ComponentCoverageItem::MECHANISM_COREX_BLOCK, 'corex/pricing, corex/stat, corex/posts, and card block styles', self::ACCESSIBILITY, 'free-core'],
            ['testimonials', ComponentCoverageItem::MECHANISM_COREX_BLOCK, 'corex/testimonial block and testimonial pattern', 'WCAG 2.2 AA quote semantics, readable contrast, and keyboard-safe links', 'free-core'],
            ['ctas', ComponentCoverageItem::MECHANISM_COREX_BLOCK, 'corex/cta block and CTA patterns', 'WCAG 2.2 AA link/button names, focus visibility, and contrast', 'free-core'],
            ['media', ComponentCoverageItem::MECHANISM_WORDPRESS_CORE_BLOCK_STYLE, 'WordPress image, gallery, media-text, cover, and corex gallery styles', 'WCAG 2.2 AA alt text, captions, keyboard-safe links, and contrast', 'free-core'],
            ['navigation', ComponentCoverageItem::MECHANISM_WORDPRESS_CORE_BLOCK_STYLE, 'WordPress navigation block and theme header/footer parts', 'WCAG 2.2 AA keyboard menus, visible focus, labels, and current-page state', 'free-core'],
            ['page templates', ComponentCoverageItem::MECHANISM_PATTERN, 'theme FSE templates plus corex-ui section patterns', self::ACCESSIBILITY, 'free-core'],
            ['admin component', ComponentCoverageItem::MECHANISM_ADMIN_COMPONENT, 'corex-config settings forms and DataViews-ready admin surfaces', 'WCAG 2.2 AA labels, keyboard navigation, notices, and focus management', 'free-core'],
            ['token utility', ComponentCoverageItem::MECHANISM_UTILITY, 'theme.json tokens, brand.json runtime overrides, and logical CSS utilities', 'WCAG 2.2 AA color contrast and focus token guidance', 'free-core'],
        ];

        return array_map(static fn (array $row): array => [
            'need' => $row[0],
            'mechanism' => $row[1],
            'source' => $row[2],
            'accessibility' => $row[3],
            'tokenStrategy' => self::TOKEN_STRATEGY,
            'rtlStrategy' => self::RTL_STRATEGY,
            'freePro' => $row[4],
        ], $rows);
    }
}
