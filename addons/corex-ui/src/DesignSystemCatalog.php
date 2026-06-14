<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui;

defined('ABSPATH') || exit;

/**
 * The Corex Design Language System catalog (spec 051): organizes the UI into five categories
 * — Components (atoms), Blocks (sections), Patterns, Templates, and Guidelines. A pure,
 * declared registry (drift-checked against the real `corex/*` blocks in the test suite, so
 * it can never list a block that does not exist). Lives in corex-ui — the single DLS home.
 */
final class DesignSystemCatalog
{
    public const COMPONENT = 'component';
    public const BLOCK     = 'block';
    public const PATTERN   = 'pattern';
    public const TEMPLATE  = 'template';
    public const GUIDELINE = 'guideline';

    /** corex/* blocks that are UI **atoms** (the Component layer). */
    private const COMPONENT_BLOCKS = [
        'alert'       => 'Alert',
        'badge'       => 'Badge',
        'breadcrumbs' => 'Breadcrumbs',
        'copyright'   => 'Copyright',
    ];

    /** corex/* blocks that are composed **sections** (the Block layer). */
    private const SECTION_BLOCKS = [
        'hero'        => 'Hero',
        'cta'         => 'Call to action',
        'stat'        => 'Stat',
        'testimonial' => 'Testimonial',
        'pricing'     => 'Pricing',
        'accordion'   => 'Accordion',
        'tabs'        => 'Tabs',
        'team'        => 'Team',
        'gallery'     => 'Gallery',
        'posts'       => 'Posts',
    ];

    /**
     * @return list<array{name:string,category:string,block:?string}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach (self::COMPONENT_BLOCKS as $slug => $name) {
            $entries[] = ['name' => $name, 'category' => self::COMPONENT, 'block' => 'corex/' . $slug];
        }
        foreach (self::SECTION_BLOCKS as $slug => $name) {
            $entries[] = ['name' => $name, 'category' => self::BLOCK, 'block' => 'corex/' . $slug];
        }

        foreach (['Hero section', 'Content section', 'Pricing table'] as $pattern) {
            $entries[] = ['name' => $pattern, 'category' => self::PATTERN, 'block' => null];
        }
        foreach (['Front page', 'Page', 'Single', 'Archive', '404'] as $template) {
            $entries[] = ['name' => $template, 'category' => self::TEMPLATE, 'block' => null];
        }
        foreach (['Design tokens (theme.json/brand.json)', 'Accessibility (WCAG 2.2 AA)', 'RTL (logical properties)'] as $guideline) {
            $entries[] = ['name' => $guideline, 'category' => self::GUIDELINE, 'block' => null];
        }

        return $entries;
    }

    /**
     * @return list<array{name:string,category:string,block:?string}>
     */
    public function byCategory(string $category): array
    {
        return array_values(array_filter($this->entries(), static fn (array $e): bool => $e['category'] === $category));
    }

    /**
     * The `corex/*` block names the catalog references — drift-checked against the real blocks.
     *
     * @return list<string>
     */
    public function blockNames(): array
    {
        $names = [];

        foreach ($this->entries() as $entry) {
            if ($entry['block'] !== null) {
                $names[] = $entry['block'];
            }
        }

        return $names;
    }
}
