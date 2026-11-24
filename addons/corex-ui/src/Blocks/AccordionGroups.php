<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use WeakMap;

/**
 * "Open one at a time" for the accordion (spec 136, FR-031): every item of an accordion that has it
 * on gets the same `name` for its `<details>`, and the browser keeps one of them open, with or without
 * scripts. The name is unique to that accordion on the page, so two accordions work apart.
 *
 * WordPress filters the block context of each inner block with the block that holds it
 * (`render_block_context`); an accordion item learns its accordion's name there, as
 * `corex/accordionName`. An item of an accordion with the setting off is given an empty name, so it
 * does not keep the name of an accordion further out that reaches it as context.
 */
final class AccordionGroups
{
    public const CONTEXT = 'corex/accordionName';

    private const ACCORDION = 'corex/accordion';

    private const ITEM = 'corex/accordion-item';

    /** @var WeakMap<object,string> each accordion being drawn, and its name */
    private WeakMap $names;

    public function __construct()
    {
        $this->names = new WeakMap();
    }

    public function register(): void
    {
        add_filter('render_block_context', [$this, 'context'], 10, 3);
    }

    /**
     * @param array<string,mixed> $context     the inner block's context
     * @param array<string,mixed> $parsedBlock the inner block
     * @param mixed               $parent      the block that holds it (`WP_Block`), or null at the top
     * @return array<string,mixed>
     */
    public function context(array $context, array $parsedBlock, mixed $parent = null): array
    {
        if (($parsedBlock['blockName'] ?? null) !== self::ITEM
            || ! is_object($parent)
            || ($parent->name ?? null) !== self::ACCORDION
        ) {
            return $context;
        }

        $attributes = is_array($parent->attributes ?? null) ? $parent->attributes : [];
        $context[self::CONTEXT] = empty($attributes['oneOpen'])
            ? ''
            : ($this->names[$parent] ??= wp_unique_id('corex-accordion-'));

        return $context;
    }
}
