<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui;

defined('ABSPATH') || exit;

/**
 * The Corex Design Language System catalog (specs 051 + 054): organizes the UI into six
 * categories — Foundations, Components, Blocks, Patterns, Templates, and Guidelines. A pure,
 * declared registry. Each entry carries a `mechanism` recording how it is delivered
 * (corex-block / block-style / core-block / token / runtime / pattern / template / deferred),
 * so a documented core block or a block style is never mistaken for a registered block. The
 * `corex/*` blocks it claims are **drift-checked** against the real blocks in the test suite,
 * so the catalog can never list a Corex block that does not exist. Lives in corex-ui — the
 * single DLS home.
 */
final class DesignSystemCatalog
{
    public const FOUNDATION = 'foundation';
    public const COMPONENT  = 'component';
    public const BLOCK      = 'block';
    public const PATTERN    = 'pattern';
    public const TEMPLATE   = 'template';
    public const GUIDELINE  = 'guideline';

    // How an entry is delivered (data-model §1).
    public const MECH_COREX_BLOCK = 'corex-block';
    public const MECH_BLOCK_STYLE = 'block-style';
    public const MECH_CORE_BLOCK  = 'core-block';
    public const MECH_TOKEN       = 'token';
    public const MECH_RUNTIME     = 'runtime';
    public const MECH_PATTERN     = 'pattern';
    public const MECH_TEMPLATE    = 'template';
    public const MECH_DEFERRED    = 'deferred';

    /** Foundations — the theme.json token groups (radius/layout already exist; motion/focus/z added in 054 US2). */
    private const FOUNDATIONS = [
        'Color', 'Typography', 'Spacing', 'Shadow', 'Radius', 'Layout & grid', 'Motion', 'Focus', 'Z-index',
    ];

    /** `corex/*` blocks that are UI **atoms** (the Component layer). */
    private const COMPONENT_BLOCKS = [
        'alert'       => 'Alert',
        'badge'       => 'Badge',
        'breadcrumbs' => 'Breadcrumbs',
        'copyright'   => 'Copyright',
        'modal'       => 'Modal / dialog',
        'drawer'      => 'Drawer',
    ];

    /** Component atoms delivered by a mechanism other than a Corex block (gap analysis, research D2). */
    private const COMPONENT_NON_BLOCK = [
        ['name' => 'Card',          'mechanism' => self::MECH_BLOCK_STYLE],
        ['name' => 'Section',       'mechanism' => self::MECH_BLOCK_STYLE],
        ['name' => 'Empty state',   'mechanism' => self::MECH_BLOCK_STYLE],
        ['name' => 'Skeleton',      'mechanism' => self::MECH_BLOCK_STYLE],
        ['name' => 'Button',        'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Link',          'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Search',        'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Dropdown / menu', 'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Pagination',    'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Table / list',  'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Form controls', 'mechanism' => self::MECH_CORE_BLOCK],
        ['name' => 'Toast / notification', 'mechanism' => self::MECH_RUNTIME],
        // Modal / dialog is now the corex/modal block (054 US3); Drawer is the corex/drawer block
        // (068 US9) — both in COMPONENT_BLOCKS.
        ['name' => 'Popover',       'mechanism' => self::MECH_DEFERRED],
        ['name' => 'Tooltip',       'mechanism' => self::MECH_DEFERRED],
        ['name' => 'Stepper',       'mechanism' => self::MECH_DEFERRED],
    ];

    /** `corex/*` blocks that are composed **sections** (the Block layer). */
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
        'carousel'    => 'Carousel / slider',
    ];

    /** Composed section patterns (corex-ui PatternLibrary). */
    private const PATTERNS = ['Hero', 'Features', 'Call to action', 'Testimonial', 'Contact'];

    /** FSE page-type templates (the theme). */
    private const TEMPLATES = ['Front page', 'Page', 'Single', 'Archive', 'Search', '404'];

    /** Cross-cutting guidelines. */
    private const GUIDELINES = [
        'Design tokens (theme.json/brand.json)',
        'Accessibility (WCAG 2.2 AA)',
        'RTL (logical properties)',
        'Motion',
        'Focus states',
        'Icon guidance',
    ];

    /**
     * @return list<array{name:string,category:string,block:?string,mechanism:string}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach (self::FOUNDATIONS as $name) {
            $entries[] = $this->entry($name, self::FOUNDATION, null, self::MECH_TOKEN);
        }

        foreach (self::COMPONENT_BLOCKS as $slug => $name) {
            $entries[] = $this->entry($name, self::COMPONENT, 'corex/' . $slug, self::MECH_COREX_BLOCK);
        }
        foreach (self::COMPONENT_NON_BLOCK as $atom) {
            $entries[] = $this->entry($atom['name'], self::COMPONENT, null, $atom['mechanism']);
        }

        foreach (self::SECTION_BLOCKS as $slug => $name) {
            $entries[] = $this->entry($name, self::BLOCK, 'corex/' . $slug, self::MECH_COREX_BLOCK);
        }

        foreach (self::PATTERNS as $name) {
            $entries[] = $this->entry($name, self::PATTERN, null, self::MECH_PATTERN);
        }
        foreach (self::TEMPLATES as $name) {
            $entries[] = $this->entry($name, self::TEMPLATE, null, self::MECH_TEMPLATE);
        }
        foreach (self::GUIDELINES as $name) {
            $entries[] = $this->entry($name, self::GUIDELINE, null, self::MECH_TOKEN);
        }

        return $entries;
    }

    /**
     * @return array{name:string,category:string,block:?string,mechanism:string}
     */
    private function entry(string $name, string $category, ?string $block, string $mechanism): array
    {
        return ['name' => $name, 'category' => $category, 'block' => $block, 'mechanism' => $mechanism];
    }

    /**
     * @return list<array{name:string,category:string,block:?string,mechanism:string}>
     */
    public function byCategory(string $category): array
    {
        return array_values(array_filter($this->entries(), static fn (array $e): bool => $e['category'] === $category));
    }

    /**
     * The `corex/*` block names the catalog references — drift-checked against the real blocks.
     * Only entries delivered as a Corex block (not styles/core/deferred) contribute a name.
     *
     * @return list<string>
     */
    public function blockNames(): array
    {
        $names = [];

        foreach ($this->entries() as $entry) {
            if ($entry['mechanism'] === self::MECH_COREX_BLOCK && $entry['block'] !== null) {
                $names[] = $entry['block'];
            }
        }

        return $names;
    }
}
